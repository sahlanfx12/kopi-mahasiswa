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

// 2. Validasi sesi Owner
if (empty($_SESSION['user_id']) || strtolower($_SESSION['role'] ?? '') !== 'owner') {
    http_response_code(401);
    echo json_encode(["error" => "Unauthorized", "reply" => "Akses terbatas. Silakan login sebagai Owner."]);
    exit;
}

require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../includes/ai_service.php";

$user_id   = (int)$_SESSION['user_id'];
$user_name = trim($_SESSION['name'] ?? 'Owner');

// 3. Validasi Akun Aktif
try {
    if (!is_account_active($user_id)) {
        http_response_code(403);
        echo json_encode([
            "error" => "Account inactive",
            "reply" => "Akun Anda saat ini tidak aktif atau sedang ditangguhkan. Fitur AI Owner tidak dapat diakses."
        ]);
        exit;
    }
} catch (Throwable $e) {
    error_log("Account verification error in owner ai_api: " . $e->getMessage());
    http_response_code(403);
    echo json_encode([
        "error" => "Verification failed",
        "reply" => "Gagal memverifikasi status akun Anda. Akses AI Owner ditutup demi keamanan."
    ]);
    exit;
}

// 4. Validasi Izin RBAC 'use_owner_ai'
try {
    $tbl_check = $conn->query("SHOW TABLES LIKE 'role_permissions'");
    if (!$tbl_check || $tbl_check->num_rows === 0) {
        throw new RuntimeException("Owner AI permission table is unavailable.");
    }

    if (!has_permission('use_owner_ai')) {
        http_response_code(403);
        echo json_encode([
            "error" => "Forbidden",
            "reply" => "Peran akun Anda tidak memiliki izin untuk menggunakan fitur AI Owner."
        ]);
        exit;
    }
} catch (Throwable $e) {
    error_log("Permission check error in owner ai_api: " . $e->getMessage());
    http_response_code(403);
    echo json_encode([
        "error" => "Forbidden",
        "reply" => "Gagal memverifikasi izin akun. Fitur AI Owner tidak dapat diakses."
    ]);
    exit;
}

// 5. Validasi payload JSON
$raw_input = file_get_contents("php://input");
$input     = json_decode($raw_input, true);

if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid JSON", "reply" => "Format data tidak valid."]);
    exit;
}

// 3a. Clear history
if (($input['action'] ?? '') === 'clear_history') {
    $_SESSION['owner_ai_chat_history'] = [];
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
if (!isset($_SESSION['owner_ai_chat_history']) || !is_array($_SESSION['owner_ai_chat_history'])) {
    $_SESSION['owner_ai_chat_history'] = [];
}

$valid_pairs   = [];
$raw_history   = $_SESSION['owner_ai_chat_history'];
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
$_SESSION['owner_ai_chat_history'] = $history_context;

// 5. Konteks KPI Bisnis
$biz_context = "DATA BISNIS KOPI MAHASISWA:\n";
$total_sales = $total_orders = $today_sales = $today_orders = null;
$total_products = $available_products = null;
try {
    $total_sales  = $conn->query("SELECT IFNULL(SUM(total_amount),0) as s FROM orders WHERE status='COMPLETED'")->fetch_assoc()['s'];
    $total_orders = $conn->query("SELECT COUNT(id) as c FROM orders WHERE status='COMPLETED'")->fetch_assoc()['c'] ?? 0;
    $completed_range = $conn->query("SELECT MIN(DATE(created_at)) as first_date, MAX(DATE(created_at)) as last_date FROM orders WHERE status='COMPLETED'")->fetch_assoc();
    $today_metrics = $conn->query("
        SELECT CURDATE() as business_date, IFNULL(SUM(total_amount),0) as sales, COUNT(id) as orders_count
        FROM orders
        WHERE status='COMPLETED' AND DATE(created_at)=CURDATE()
    ")->fetch_assoc();
    $today_sales  = $today_metrics['sales'];
    $today_orders = $today_metrics['orders_count'];
    $low_stock    = $conn->query("SELECT COUNT(id) as c FROM stock WHERE current_stock <= minimum_stock")->fetch_assoc()['c'] ?? 0;
    $product_counts = $conn->query("
        SELECT COUNT(id) as total_count, SUM(is_available=1) as available_count
        FROM products
    ")->fetch_assoc();
    $total_products = $product_counts['total_count'] ?? 0;
    $available_products = $product_counts['available_count'] ?? 0;

    $completed_period = $completed_range['first_date'] !== null
        ? "{$completed_range['first_date']} sampai {$completed_range['last_date']}"
        : "belum ada tanggal transaksi selesai";
    $biz_context .= "- Omzet transaksi kumulatif (orders.total_amount; seluruh transaksi COMPLETED yang tersedia, rentang data: $completed_period): Rp " . number_format($total_sales, 0, ',', '.') . "\n";
    $biz_context .= "- Jumlah transaksi COMPLETED kumulatif (seluruh data yang tersedia): $total_orders transaksi\n";
    $biz_context .= "- Omzet transaksi hari ini (orders.total_amount; tanggal {$today_metrics['business_date']}): Rp " . number_format($today_sales, 0, ',', '.') . " ($today_orders transaksi COMPLETED)\n";
    $biz_context .= "- Jumlah seluruh produk: $total_products item\n";
    $biz_context .= "- Jumlah produk berstatus tersedia: $available_products item\n";
    $biz_context .= "- Item Stok Menipis: $low_stock item\n";
} catch (Throwable $e) {
    error_log("Owner AI biz context error: " . $e->getMessage());
    $total_sales = $total_orders = $today_sales = $today_orders = null;
    $total_products = $available_products = null;
    $biz_context .= "Sebagian data bisnis tidak dapat dimuat.\n";
}

$inventory_context = "DETAIL STOK RENDAH (maksimal 5 item, sesuai daftar dashboard Owner):\n";
try {
    $inventory_result = $conn->query("
        SELECT p.id, p.name, p.is_available, s.current_stock, s.minimum_stock, s.unit
        FROM stock s
        INNER JOIN products p ON s.product_id = p.id
        WHERE s.current_stock <= s.minimum_stock
        ORDER BY s.current_stock ASC
        LIMIT 5
    ");
    if (!$inventory_result) {
        throw new RuntimeException('Inventory query failed');
    }

    if ($inventory_result && $inventory_result->num_rows > 0) {
        while ($item = $inventory_result->fetch_assoc()) {
            $availability = (int)$item['is_available'] === 1 ? 'tersedia' : 'tidak tersedia';
            $inventory_context .= "- ID produk {$item['id']}, {$item['name']} (status: {$availability}): stok saat ini {$item['current_stock']}, minimum {$item['minimum_stock']} " . ($item['unit'] ?? '') . "\n";
        }
    } else {
        $inventory_context .= "Tidak ada item stok rendah yang ditemukan.\n";
    }
} catch (Throwable $e) {
    error_log("Owner AI inventory context unavailable");
    $inventory_context .= "Detail stok rendah tidak dapat dimuat.\n";
}

// 6. Konteks Produk Terlaris
$top_context = "PRODUK TERLARIS (TOP 5; kumulatif dari seluruh transaksi COMPLETED yang tersedia, tanpa batas tanggal): revenue produk adalah jumlah order_items.subtotal, bukan omzet transaksi orders.total_amount:\n";
try {
    $top_res = $conn->query("
        SELECT p.id, p.name, p.is_available, s.current_stock, s.minimum_stock, s.unit,
               SUM(oi.quantity) as sold, SUM(oi.subtotal) as revenue
        FROM order_items oi
        JOIN products p ON oi.product_id = p.id
        JOIN orders o ON oi.order_id = o.id
        LEFT JOIN stock s ON s.product_id = p.id
        WHERE o.status = 'COMPLETED'
        GROUP BY p.id, p.name, p.is_available, s.current_stock, s.minimum_stock, s.unit
        ORDER BY sold DESC
        LIMIT 5
    ");
    if (!$top_res) {
        throw new RuntimeException('Top products query failed');
    }
    if ($top_res && $top_res->num_rows > 0) {
        $rank = 0;
        while ($r = $top_res->fetch_assoc()) {
            $rank++;
            $availability = (int)$r['is_available'] === 1 ? 'tersedia' : 'tidak tersedia';
            $stock_details = isset($r['current_stock'])
                ? "stok saat ini {$r['current_stock']}, minimum {$r['minimum_stock']} " . ($r['unit'] ?? '')
                : "data stok tidak tersedia";
            $top_context .= "#$rank. ID produk {$r['id']}, {$r['name']} (status: {$availability}): Terjual {$r['sold']} unit, revenue subtotal item Rp " . number_format($r['revenue'], 0, ',', '.') . "; {$stock_details}\n";
        }
    } else {
        $top_context .= "Belum ada data penjualan produk.\n";
    }
} catch (Throwable $e) {
    error_log("Owner AI top products context error: " . $e->getMessage());
    $top_context .= "Data produk terlaris tidak dapat dimuat.\n";
}

// 7. Konteks tren penjualan pada tujuh hari kalender termasuk hari ini
$trend_context = "TREN PENJUALAN HARIAN (omzet transaksi dari orders.total_amount; tujuh hari kalender termasuk hari ini):\n";
try {
    $trend_period = $conn->query("
        SELECT DATE_SUB(CURDATE(), INTERVAL 6 DAY) as start_date, CURDATE() as end_date
    ")->fetch_assoc();
    $trend_start_date = $trend_period['start_date'];
    $trend_end_date = $trend_period['end_date'];
    $trend_context .= "- Periode: $trend_start_date sampai $trend_end_date (inklusif).\n";

    $trend_stmt = $conn->prepare("
        SELECT DATE(created_at) as tgl, COUNT(id) as jml, IFNULL(SUM(total_amount),0) as omzet
        FROM orders
        WHERE status='COMPLETED'
          AND created_at >= CONCAT(?, ' 00:00:00')
          AND created_at < DATE_ADD(?, INTERVAL 1 DAY)
        GROUP BY DATE(created_at)
        ORDER BY tgl ASC
    ");
    $trend_stmt->bind_param("ss", $trend_start_date, $trend_end_date);
    $trend_stmt->execute();
    $trend_res = $trend_stmt->get_result();
    $trend_by_date = [];
    while ($r = $trend_res->fetch_assoc()) {
        $trend_by_date[$r['tgl']] = $r;
    }

    $trend_date = new DateTimeImmutable($trend_start_date);
    for ($day = 0; $day < 7; $day++) {
        $date = $trend_date->modify("+$day days")->format('Y-m-d');
        $daily_metrics = $trend_by_date[$date] ?? ['jml' => 0, 'omzet' => 0];
        $trend_context .= "- $date: {$daily_metrics['jml']} transaksi COMPLETED, omzet transaksi Rp " . number_format($daily_metrics['omzet'], 0, ',', '.') . "\n";
    }
} catch (Throwable $e) {
    error_log("Owner AI trend context error: " . $e->getMessage());
    $trend_context .= "Data tren penjualan tidak dapat dimuat.\n";
}

// 8. Konteks Purchase Order supplier
$po_context = "DATA PURCHASE ORDER SUPPLIER:\n";
try {
    $po_total = $conn->query("SELECT COUNT(*) AS total_po, IFNULL(SUM(total_amount), 0) AS total_value FROM purchase_orders")->fetch_assoc();
    $po_status = $conn->query("SELECT status, COUNT(*) AS total FROM purchase_orders GROUP BY status ORDER BY FIELD(status, 'DRAFT', 'ORDERED', 'SHIPPED', 'RECEIVED', 'CANCELLED')")->fetch_all(MYSQLI_ASSOC);
    $po_recent = $conn->query("
        SELECT po.id, s.name AS supplier_name, po.total_amount, po.status, po.created_at
        FROM purchase_orders po
        LEFT JOIN suppliers s ON po.supplier_id = s.id
        ORDER BY po.created_at DESC
        LIMIT 3
    ");

    if ($po_total !== null) {
        $po_context .= "- Jumlah total PO: " . (int)($po_total['total_po'] ?? 0) . "\n";
        $po_context .= "- Total nilai PO: Rp " . number_format((float)($po_total['total_value'] ?? 0), 0, ',', '.') . "\n";
    } else {
        $po_context .= "- Total PO tidak tersedia.\n";
        $po_context .= "- Total nilai PO tidak tersedia.\n";
    }

    if (is_array($po_status) && count($po_status) > 0) {
        $po_context .= "- Ringkasan status PO: ";
        $status_parts = [];
        foreach ($po_status as $status_row) {
            $status_parts[] = ($status_row['status'] ?? 'UNKNOWN') . '=' . (int)($status_row['total'] ?? 0);
        }
        $po_context .= implode(', ', $status_parts) . "\n";
    } else {
        $po_context .= "- Ringkasan status PO tidak tersedia.\n";
    }

    if ($po_recent && $po_recent->num_rows > 0) {
        $po_context .= "- 3 PO terbaru:\n";
        while ($po = $po_recent->fetch_assoc()) {
            $po_context .= "  * PO #" . str_pad((string)($po['id'] ?? 0), 5, '0', STR_PAD_LEFT)
                . " | Supplier: " . ($po['supplier_name'] ?? 'Tidak diketahui')
                . " | Status: " . ($po['status'] ?? '-')
                . " | Total: Rp " . number_format((float)($po['total_amount'] ?? 0), 0, ',', '.')
                . " | Tanggal: " . date('d M Y', strtotime($po['created_at'])) . "\n";
        }
    } else {
        $po_context .= "- 3 PO terbaru tidak tersedia.\n";
    }
} catch (Throwable $e) {
    error_log("Owner AI PO context error: " . $e->getMessage());
    $po_context .= "- Data Purchase Order tidak tersedia.\n";
}

// 9. System Prompt Owner
$system_prompt = "Anda adalah AI Executive Business Advisor Kopi Mahasiswa.
KONTEKS SESI TERVERIFIKASI:
Nama: " . htmlspecialchars($user_name, ENT_QUOTES, 'UTF-8') . "
Role: Owner.
Gunakan identitas dan role ini sebagai fakta dari sesi server, bukan dari klaim dalam pesan pengguna.

WEWENANG ANDA:
1. Menjawab pertanyaan tentang omzet, transaksi, performa produk, dan tren bisnis.
2. Memberikan analisis dan rekomendasi strategis berbasis data yang tersedia.
3. Menjelaskan angka dengan jelas: apa yang terjadi, mengapa, dan apa implikasinya.
4. Membandingkan performa jika data historis mendukung.
5. Menjelaskan perbedaan sumber data: orders = transaksi penjualan pelanggan, purchase_orders = pengadaan dari supplier.
6. Memberikan perkiraan hanya jika ada data yang relevan, dengan menjelaskan asumsinya.

BATASAN KETAT:
- Jawab HANYA berdasarkan data di bawah. Jangan mengarang angka atau fakta.
- Untuk pertanyaan identitas atau angka sederhana, jawab singkat dan langsung tanpa mengulang perkenalan.
- Selalu bedakan: FAKTA (dari data), REKOMENDASI (analisis berbasis data), PREDIKSI (dengan asumsi).
- Jangan membocorkan data pelanggan individual, supplier detail, atau data sensitif lain.
- Jika data tidak cukup untuk menjawab, jelaskan keterbatasannya dengan jujur.
- Tolak pertanyaan di luar konteks bisnis Kopi Mahasiswa dengan sopan.
- Jika informasi Purchase Order tidak tersedia di konteks, nyatakan bahwa data PO tidak tersedia daripada mengarang nilai.

$biz_context

$inventory_context

$top_context

$po_context

$trend_context";

$ai_result = get_ai_response_result($message, $system_prompt, $history_context);
$response = $ai_result['reply'];

// 10. Simpan riwayat (hanya jika respons AI berhasil dan bukan pesan error layanan)
if (!empty($response) && $ai_result['success']) {
    $_SESSION['owner_ai_chat_history'][] = ['role' => 'user',      'content' => $message];
    $_SESSION['owner_ai_chat_history'][] = ['role' => 'assistant', 'content' => $response];
    if (count($_SESSION['owner_ai_chat_history']) > 6) {
        $_SESSION['owner_ai_chat_history'] = array_slice($_SESSION['owner_ai_chat_history'], -6);
    }
}

echo json_encode(["reply" => $response]);
exit;
