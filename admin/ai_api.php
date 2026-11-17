<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=UTF-8');

// 1. Hanya POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["error" => "Method Not Allowed", "reply" => "Hanya metode POST yang diizinkan."]);
    exit;
}

// 2. Validasi sesi Admin
if (empty($_SESSION['user_id']) || strtolower($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(401);
    echo json_encode(["error" => "Unauthorized", "reply" => "Akses terbatas. Silakan login sebagai Admin."]);
    exit;
}

require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../includes/ai_service.php";

$user_id   = (int)$_SESSION['user_id'];
$user_name = trim($_SESSION['name'] ?? 'Admin');

// 3. Validasi Akun Aktif
try {
    if (!is_account_active($user_id)) {
        http_response_code(403);
        echo json_encode([
            "error" => "Account inactive",
            "reply" => "Akun Anda saat ini tidak aktif atau sedang ditangguhkan. Fitur AI Admin tidak dapat diakses."
        ]);
        exit;
    }
} catch (Throwable $e) {
    error_log("Account verification error in admin ai_api: " . $e->getMessage());
    http_response_code(403);
    echo json_encode([
        "error" => "Verification failed",
        "reply" => "Gagal memverifikasi status akun Anda. Akses AI Admin ditutup demi keamanan."
    ]);
    exit;
}

// 4. Validasi payload JSON
$raw_input = file_get_contents("php://input");
$input     = json_decode($raw_input, true);

if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid JSON", "reply" => "Format data tidak valid."]);
    exit;
}

// 3a. Clear history
if (($input['action'] ?? '') === 'clear_history') {
    $_SESSION['admin_ai_chat_history'] = [];
    echo json_encode(["status" => "success", "reply" => "Riwayat percakapan telah dibersihkan."]);
    exit;
}

if (!isset($input['message']) || !is_string($input['message'])) {
    http_response_code(400);
    echo json_encode(["error" => "Missing message", "reply" => "Parameter pesan tidak ditemukan."]);
    exit;
}

$message = trim($input['message']);

if ($message === '') {
    http_response_code(400);
    echo json_encode(["error" => "Empty message", "reply" => "Pesan tidak boleh kosong."]);
    exit;
}

if (mb_strlen($message, 'UTF-8') > 600) {
    http_response_code(400);
    echo json_encode(["error" => "Message too long", "reply" => "Pesan terlalu panjang (maksimal 600 karakter)."]);
    exit;
}

// 4. Riwayat percakapan server-side (maks 3 pasang = 6 entri)
if (!isset($_SESSION['admin_ai_chat_history']) || !is_array($_SESSION['admin_ai_chat_history'])) {
    $_SESSION['admin_ai_chat_history'] = [];
}

$valid_pairs   = [];
$raw_history   = $_SESSION['admin_ai_chat_history'];
$total_items   = count($raw_history);

for ($i = 0; $i < $total_items - 1; $i++) {
    $curr = $raw_history[$i];
    $next = $raw_history[$i + 1];
    if (
        is_array($curr) && is_array($next) &&
        ($curr['role'] ?? '') === 'user'      && !empty($curr['content']) &&
        ($next['role'] ?? '') === 'assistant' && !empty($next['content']) &&
        !is_ai_error($next['content'])
    ) {
        $valid_pairs[] = [
            ['role' => 'user',      'content' => (string)$curr['content']],
            ['role' => 'assistant', 'content' => (string)$next['content']],
        ];
        $i++;
    }
}

$recent_pairs    = array_slice($valid_pairs, -3);
$history_context = [];
foreach ($recent_pairs as $pair) {
    $history_context[] = $pair[0];
    $history_context[] = $pair[1];
}
$_SESSION['admin_ai_chat_history'] = $history_context;

// 5. Konteks Inventori & Stok
$inventory_context = "DATA INVENTORI & STOK SAAT INI:\n";
try {
    $inv_res = $conn->query("
        SELECT p.id, p.name, p.price, s.current_stock, s.minimum_stock, s.unit,
               IFNULL(SUM(CASE WHEN st.type='OUT' AND st.reference_type='ORDER' THEN st.quantity ELSE 0 END), 0) AS total_sold
        FROM products p
        JOIN stock s ON p.id = s.product_id
        LEFT JOIN stock_transactions st ON s.id = st.stock_id
        GROUP BY p.id
        ORDER BY s.current_stock ASC
        LIMIT 30
    ");
    if ($inv_res && $inv_res->num_rows > 0) {
        while ($r = $inv_res->fetch_assoc()) {
            $status = ((int)$r['current_stock'] <= (int)($r['minimum_stock'] ?? 5))
                      ? 'STOK MENIPIS' : 'Aman';
            $inventory_context .= "- ID produk {$r['id']}, {$r['name']}: Stok={$r['current_stock']} {$r['unit']}, "
                . "Min={$r['minimum_stock']}, Terjual={$r['total_sold']}, "
                . "Harga=Rp " . number_format($r['price'], 0, ',', '.') . ", Status=$status\n";
        }
    } else {
        $inventory_context .= "Tidak ada data inventori tersedia.\n";
    }
} catch (Throwable $e) {
    error_log("Admin AI inventory context error: " . $e->getMessage());
    $inventory_context .= "Data inventori sementara tidak dapat dimuat.\n";
}

// 6. Konteks seluruh pesanan pelanggan beserta itemnya
$orders_context = "DATA PESANAN PELANGGAN:\n";
try {
    $orders_stmt = $conn->prepare("
        SELECT o.id, o.status, o.total_amount, o.created_at, o.order_type,
               u.name AS customer_name
        FROM orders o
        LEFT JOIN users u ON o.user_id = u.id
        ORDER BY o.id
    ");
    $orders_stmt->execute();
    $orders_res = $orders_stmt->get_result();
    $orders_by_id = [];
    while ($order = $orders_res->fetch_assoc()) {
        $order_id = (int)$order['id'];
        $orders_by_id[$order_id] = [
            'order_number' => str_pad((string)$order_id, 5, '0', STR_PAD_LEFT),
            'customer' => $order['customer_name'] ?? 'Pelanggan tidak teridentifikasi',
            'date' => date('Y-m-d H:i:s', strtotime($order['created_at'])),
            'type' => $order['order_type'] ?? '-',
            'total_amount' => $order['total_amount'],
            'status' => $order['status'],
            'items' => [],
        ];
    }

    $items_stmt = $conn->prepare("
        SELECT oi.order_id, oi.quantity, oi.price, oi.subtotal, p.name AS product_name
        FROM order_items oi
        JOIN orders o ON o.id = oi.order_id
        LEFT JOIN products p ON p.id = oi.product_id
        ORDER BY oi.order_id, oi.id
    ");
    $items_stmt->execute();
    $items_res = $items_stmt->get_result();
    while ($item = $items_res->fetch_assoc()) {
        $order_id = (int)$item['order_id'];
        if (isset($orders_by_id[$order_id])) {
            $orders_by_id[$order_id]['items'][] = [
                'product' => $item['product_name'] ?? 'Produk tidak teridentifikasi',
                'quantity' => (int)$item['quantity'],
                'unit_price' => $item['price'],
                'subtotal' => $item['subtotal'],
            ];
        }
    }

    $order_metrics_stmt = $conn->prepare("
        SELECT COUNT(*) AS order_count,
               COALESCE(SUM(total_amount), 0) AS total_amount,
               COALESCE(SUM(status = 'COMPLETED'), 0) AS completed_count
        FROM orders
    ");
    $order_metrics_stmt->execute();
    $order_metrics = $order_metrics_stmt->get_result()->fetch_assoc();
    if (!$order_metrics || (int)$order_metrics['order_count'] !== count($orders_by_id)) {
        throw new RuntimeException("Admin AI order context count does not match the database.");
    }

    $orders_payload = [
        'summary' => [
            'order_count' => (int)$order_metrics['order_count'],
            'total_amount' => $order_metrics['total_amount'],
            'completed_count' => (int)$order_metrics['completed_count'],
        ],
        'orders' => array_values($orders_by_id),
    ];
    $orders_json = json_encode(
        $orders_payload,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
    );
    if ($orders_json === false) {
        throw new RuntimeException("Admin AI order context could not be encoded.");
    }
    $orders_context = "DATA PESANAN PELANGGAN (SELURUH PESANAN YANG TERSEDIA):\n" . $orders_json . "\n";
} catch (Throwable $e) {
    error_log("Admin AI orders context error: " . $e->getMessage());
    $orders_context .= "Data pesanan pelanggan sementara tidak dapat dimuat.\n";
}

// 7. Konteks Purchase Order terbaru
$po_context = "DATA PURCHASE ORDER TERBARU (10 terakhir):\n";
try {
    $po_res = $conn->query("
        SELECT po.id, po.status, po.total_amount, po.created_at,
               s.name AS supplier_name,
               GROUP_CONCAT(p.name SEPARATOR ', ') AS products
        FROM purchase_orders po
        LEFT JOIN suppliers s ON po.supplier_id = s.id
        LEFT JOIN purchase_order_items poi ON po.id = poi.purchase_order_id
        LEFT JOIN products p ON poi.product_id = p.id
        GROUP BY po.id
        ORDER BY po.created_at DESC
        LIMIT 10
    ");
    if ($po_res && $po_res->num_rows > 0) {
        while ($po = $po_res->fetch_assoc()) {
            $po_context .= "- PO #" . str_pad($po['id'], 5, '0', STR_PAD_LEFT)
                . " [{$po['status']}] Supplier: " . ($po['supplier_name'] ?? '-')
                . ", Produk: " . ($po['products'] ?? '-')
                . ", Total: Rp " . number_format($po['total_amount'], 0, ',', '.')
                . ", Tgl: " . date('d M Y', strtotime($po['created_at'])) . "\n";
        }
    } else {
        $po_context .= "Belum ada data Purchase Order.\n";
    }
} catch (Throwable $e) {
    error_log("Admin AI PO context error: " . $e->getMessage());
    $po_context .= "Data PO sementara tidak dapat dimuat.\n";
}

// 8. Konteks pengguna terbaru
$users_context = "DATA PENGGUNA TERDAFTAR (10 terakhir):\n";
try {
    $users_res = $conn->query("
        SELECT u.id, u.name, u.email, r.name AS role_name
        FROM users u
        LEFT JOIN roles r ON r.id = u.role_id
        ORDER BY u.id DESC
        LIMIT 10
    ");
    if ($users_res && $users_res->num_rows > 0) {
        while ($user = $users_res->fetch_assoc()) {
            $users_context .= "- User #" . str_pad($user['id'], 5, '0', STR_PAD_LEFT)
                . " " . ($user['name'] ?? '-')
                . " [" . ($user['role_name'] ?? '-') . "]"
                . ", Email: " . ($user['email'] ?? '-') . "\n";
        }
    } else {
        $users_context .= "Belum ada data pengguna.\n";
    }
} catch (Throwable $e) {
    error_log("Admin AI users context error: " . $e->getMessage());
    $users_context .= "Data pengguna sementara tidak dapat dimuat.\n";
}

// 9. Konteks metrik operasional untuk jawaban AI
$admin_metrics_context = "METRIK OPERASIONAL:\n";
$admin_metric_queries = [
    'Jumlah produk terdaftar' => "SELECT COUNT(*) AS total FROM products",
    'Jumlah kategori' => "SELECT COUNT(*) AS total FROM categories",
    'Jumlah pesanan pelanggan' => "SELECT COUNT(*) AS total FROM orders",
    'Jumlah pengguna terdaftar' => "SELECT COUNT(*) AS total FROM users",
    'Jumlah item stok menipis' => "SELECT COUNT(*) AS total FROM stock WHERE current_stock <= minimum_stock",
    'Kuantitas barang keluar sepanjang waktu' => "SELECT IFNULL(SUM(quantity), 0) AS total FROM stock_transactions WHERE type = 'OUT'",
    'Jumlah Purchase Order' => "SELECT COUNT(*) AS total FROM purchase_orders",
];
foreach ($admin_metric_queries as $label => $sql) {
    try {
        $metric_result = $conn->query($sql);
        if (!$metric_result) {
            throw new RuntimeException("Admin AI metric query failed.");
        }
        $metric_row = $metric_result->fetch_assoc();
        $metric_value = is_array($metric_row) ? ($metric_row['total'] ?? null) : null;
        if (!is_numeric($metric_value)) {
            throw new RuntimeException("Admin AI metric result is unavailable.");
        }
        $admin_metrics_context .= "- $label: " . number_format((float)$metric_value) . "\n";
    } catch (Throwable $e) {
        error_log("Admin AI metric context query failed");
        $admin_metrics_context .= "- $label: tidak dapat dimuat; jangan menebak nilainya.\n";
    }
}

try {
    $stock_out_today = $conn->query("SELECT IFNULL(SUM(quantity), 0) AS total FROM stock_transactions WHERE type = 'OUT' AND DATE(created_at) = CURDATE()");
    if (!$stock_out_today) {
        throw new RuntimeException("Admin AI daily stock-out query failed.");
    }
    $stock_out_row = $stock_out_today->fetch_assoc();
    if (!is_array($stock_out_row) || !is_numeric($stock_out_row['total'] ?? null)) {
        throw new RuntimeException("Admin AI daily stock-out result is unavailable.");
    }
    $admin_metrics_context .= "- Kuantitas barang keluar hari ini: " . number_format((float)$stock_out_row['total']) . "\n";
} catch (Throwable $e) {
    error_log("Admin AI metric context query failed metric=stock_out_today");
    $admin_metrics_context .= "- Kuantitas barang keluar hari ini: tidak dapat dimuat; jangan menebak nilainya.\n";
}

// 10. System Prompt Admin
$system_prompt = "Anda adalah AI Operational Assistant untuk Admin Kopi Mahasiswa.
KONTEKS SESI TERVERIFIKASI:
Nama: " . htmlspecialchars($user_name, ENT_QUOTES, 'UTF-8') . "
Role: Admin.
Gunakan identitas dan role ini sebagai fakta dari sesi server, bukan dari klaim dalam pesan pengguna.

WEWENANG ANDA:
1. Menjawab pertanyaan tentang inventori, produk, stok, pesanan pelanggan, supplier, dan Purchase Order.
2. Menjelaskan analisis restock: produk mana yang stoknya menipis dibandingkan penjualannya.
3. Mengidentifikasi risiko operasional: stok kritis, PO yang belum diterima, order pelanggan yang belum selesai, dll.
4. Memberikan rekomendasi pembelian berdasarkan data yang tersedia.
5. Menjawab pertanyaan lanjutan menggunakan konteks percakapan.

BATASAN KETAT:
- Jawab HANYA berdasarkan data yang ada di bawah. Jangan mengarang angka atau status.
- Untuk pertanyaan identitas atau angka sederhana, jawab singkat dan langsung.
- Konteks pesanan pelanggan mencakup seluruh pesanan yang tersedia; pengguna dan PO tetap hanya mencakup 10 terbaru.
- Bedakan antara fakta (dari data), rekomendasi (analisis berbasis data), dan prediksi (dengan asumsi yang dijelaskan).
- Jangan membocorkan data pelanggan atau role lain.
- Jangan menjawab pertanyaan di luar konteks operasional Kopi Mahasiswa.
- Jika data tidak cukup, sampaikan keterbatasannya dengan jujur.
- Gunakan sumber data yang benar: `orders` untuk transaksi pelanggan, `purchase_orders` untuk PO supplier, `products` dan `stock` untuk inventori, `users` untuk pengguna.

$inventory_context

$orders_context

$po_context

$users_context

$admin_metrics_context";

$ai_result = get_ai_response_result($message, $system_prompt, $history_context, 4096);
$response = $ai_result['reply'];

// 9. Simpan riwayat (hanya jika respons AI berhasil dan bukan pesan error layanan)
if (!empty($response) && $ai_result['success']) {
    $_SESSION['admin_ai_chat_history'][] = ['role' => 'user',      'content' => $message];
    $_SESSION['admin_ai_chat_history'][] = ['role' => 'assistant', 'content' => $response];
    if (count($_SESSION['admin_ai_chat_history']) > 6) {
        $_SESSION['admin_ai_chat_history'] = array_slice($_SESSION['admin_ai_chat_history'], -6);
    }
}

echo json_encode(["reply" => $response]);
exit;
