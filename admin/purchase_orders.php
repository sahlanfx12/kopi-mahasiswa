<?php
require "../config.php";
require_once "../includes/csrf.php";
require_once "../includes/interaction_ui.php";
require_once "../includes/notification_events.php";

if (empty($_SESSION["user_id"]) || strtolower($_SESSION["role"] ?? "") !== "admin") {
    header("Location: ../login.php");
    exit;
}
$auth_user_id = (int)$_SESSION["user_id"];
$auth_stmt = $conn->prepare("SELECT status FROM users WHERE id = ? LIMIT 1");
$auth_stmt->bind_param("i", $auth_user_id);
$auth_stmt->execute();
$auth_user = $auth_stmt->get_result()->fetch_assoc();
if (($auth_user["status"] ?? "active") === "suspended") {
    session_destroy();
    header("Location: ../login.php?err=suspended");
    exit;
}

$action = $_GET['action'] ?? 'list';

if ($action === 'store' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $supplier_id = (int)$_POST['supplier_id'];
    $notes = $conn->real_escape_string($_POST['notes'] ?? "");
    $products = $_POST['products'] ?? [];
    $quantities = $_POST['quantities'] ?? [];

    if ($supplier_id > 0 && is_array($products) && !empty($products) && is_array($quantities) && count($products) === count($quantities)) {
        $transaction_started = false;
        try {
            if (!$conn->begin_transaction()) throw new Exception("Gagal memulai transaksi PO.");
            $transaction_started = true;

            $stmt = $conn->prepare("INSERT INTO purchase_orders (supplier_id, notes, status, total_amount) VALUES (?, ?, 'DRAFT', 0)");
            $stmt->bind_param("is", $supplier_id, $notes);
            if (!$stmt->execute()) throw new Exception("Gagal membuat PO.");
            $po_id = $conn->insert_id;

            $total_amount = 0;
            $item_count = 0;
            $stmt_item = $conn->prepare("INSERT INTO purchase_order_items (purchase_order_id, product_id, quantity, unit_price, subtotal) VALUES (?, ?, ?, ?, ?)");

            foreach ($products as $index => $product_id) {
                $raw_qty = $quantities[$index] ?? null;
                if (!is_scalar($product_id) || !is_scalar($raw_qty)) throw new Exception("Data item PO tidak valid.");
                $pid = filter_var($product_id, FILTER_VALIDATE_INT);
                $qty = filter_var($raw_qty, FILTER_VALIDATE_INT);
                if ($pid === false || $pid <= 0 || $qty === false || $qty <= 0) throw new Exception("Data item PO tidak valid.");

                $res = $conn->query("SELECT price FROM products WHERE id = $pid");
                if (!$res || $res->num_rows === 0) throw new Exception("Produk untuk PO tidak ditemukan.");
                $p = $res->fetch_assoc();
                $price = $p['price'];
                $subtotal = $price * $qty;
                $total_amount += $subtotal;

                $stmt_item->bind_param("iiidd", $po_id, $pid, $qty, $price, $subtotal);
                if (!$stmt_item->execute()) throw new Exception("Gagal menyimpan item PO.");
                $item_count++;
            }

            if ($item_count === 0) throw new Exception("PO harus memiliki setidaknya satu item valid.");

            $total_stmt = $conn->prepare("UPDATE purchase_orders SET total_amount = ? WHERE id = ?");
            if (!$total_stmt) throw new Exception("Gagal menyiapkan total PO.");
            $total_stmt->bind_param("di", $total_amount, $po_id);
            if (!$total_stmt->execute()) throw new Exception("Gagal memperbarui total PO.");
            if (!notification_event_send_roles($conn, ['Admin'], 'Purchase Order baru', 'Purchase Order #' . str_pad((string)$po_id, 5, '0', STR_PAD_LEFT) . ' berhasil dibuat dengan status DRAFT.', 'info')) {
                throw new Exception("Gagal mencatat notifikasi Purchase Order.");
            }
            if (!$conn->commit()) throw new Exception("Gagal menyelesaikan transaksi PO.");
            $transaction_started = false;
            $_SESSION["ui_notification"] = ["type" => "success", "message" => "Purchase Order berhasil dibuat dengan status " . app_status_label("DRAFT") . "."];
            header("Location: purchase_orders.php");
            exit;
        } catch (Exception $e) {
            if ($transaction_started) $conn->rollback();
            error_log("Admin purchase order creation failed");
            $error = "Purchase Order belum berhasil dibuat. Periksa kembali data dan coba lagi.";
        }
    } else {
        $error = "Data tidak lengkap!";
    }
    $action = 'create';
}

if ($action === 'update_status' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $po_id = (int)$_POST['po_id'];
    $new_status = $_POST['status'];

    $transaction_started = false;
    try {
        if (!$conn->begin_transaction()) throw new Exception("Gagal memulai transaksi status PO.");
        $transaction_started = true;

        $po_res_stmt = $conn->prepare("SELECT status, supplier_id FROM purchase_orders WHERE id = ? FOR UPDATE");
        if (!$po_res_stmt) throw new Exception("Gagal menyiapkan pembacaan status PO.");
        $po_res_stmt->bind_param("i", $po_id);
        if (!$po_res_stmt->execute()) throw new Exception("Gagal membaca status PO.");
        $po_res = $po_res_stmt->get_result();
        if (!$po_res) throw new Exception("Gagal membaca status PO.");
        $po = $po_res->fetch_assoc();
        if (!$po) throw new Exception("PO tidak ditemukan!");
        if ($po['status'] === 'RECEIVED' || $po['status'] === 'CANCELLED') throw new Exception("Status PO final.");

        $valid = false;
        if ($po['status'] === 'DRAFT' && in_array($new_status, ['ORDERED', 'CANCELLED'])) $valid = true;
        if ($po['status'] === 'SHIPPED' && $new_status === 'RECEIVED') $valid = true;
        if ($po['status'] === 'ORDERED' && $new_status === 'RECEIVED') $valid = true;

        if (!$valid) throw new Exception("Transisi tidak diizinkan.");

        $status_stmt = $conn->prepare("UPDATE purchase_orders SET status = ? WHERE id = ? AND status = ?");
        if (!$status_stmt) throw new Exception("Gagal menyiapkan pembaruan status PO.");
        $status_stmt->bind_param("sis", $new_status, $po_id, $po['status']);
        if (!$status_stmt->execute() || $status_stmt->affected_rows !== 1) throw new Exception("Gagal memperbarui status PO.");

        if ($new_status === 'RECEIVED') {
            $items_res = $conn->query("SELECT product_id, quantity FROM purchase_order_items WHERE purchase_order_id = $po_id");
            if (!$items_res) throw new Exception("Gagal membaca item PO.");
            while ($item = $items_res->fetch_assoc()) {
                $pid = $item['product_id'];
                $qty = $item['quantity'];

                $stock_stmt = $conn->prepare("SELECT s.id, s.current_stock, s.minimum_stock, p.name FROM stock s JOIN products p ON p.id = s.product_id WHERE s.product_id = ? FOR UPDATE");
                if (!$stock_stmt) throw new Exception("Gagal menyiapkan pembacaan stok produk PO.");
                $stock_stmt->bind_param("i", $pid);
                if (!$stock_stmt->execute()) throw new Exception("Gagal membaca stok produk PO.");
                $stock_res = $stock_stmt->get_result();
                if ($stock_res->num_rows === 0) throw new Exception("Baris stok untuk item PO tidak ditemukan.");
                $stock = $stock_res->fetch_assoc();
                $sid = $stock['id'];
                $stock_update_stmt = $conn->prepare("UPDATE stock SET current_stock = current_stock + ? WHERE id = ?");
                if (!$stock_update_stmt) throw new Exception("Gagal menyiapkan pembaruan stok produk PO.");
                $stock_update_stmt->bind_param("ii", $qty, $sid);
                if (!$stock_update_stmt->execute() || $stock_update_stmt->affected_rows !== 1) throw new Exception("Gagal memperbarui stok produk PO.");
                $stock_log_stmt = $conn->prepare("INSERT INTO stock_transactions (stock_id, type, quantity, reference_type, reference_id, notes) VALUES (?, 'IN', ?, 'PURCHASE_ORDER', ?, ?)");
                if (!$stock_log_stmt) throw new Exception("Gagal menyiapkan mutasi stok PO.");
                $stock_note = 'Barang diterima dari PO #' . $po_id;
                $stock_log_stmt->bind_param("iiis", $sid, $qty, $po_id, $stock_note);
                if (!$stock_log_stmt->execute()) throw new Exception("Gagal mencatat mutasi stok PO.");
                $old_stock = (int)$stock['current_stock'];
                if (!notification_event_stock_change($conn, (string)$stock['name'], $old_stock, $old_stock + (int)$qty, (int)$stock['minimum_stock'])) {
                    throw new Exception("Gagal mencatat notifikasi stok.");
                }
            }
        }
        $po_number = str_pad((string)$po_id, 5, '0', STR_PAD_LEFT);
        if (!notification_event_send_roles($conn, ['Admin'], 'Status Purchase Order berubah', 'Purchase Order #' . $po_number . ' berubah dari ' . $po['status'] . ' menjadi ' . $new_status . '.', 'info')) {
            throw new Exception("Gagal mencatat notifikasi status PO.");
        }
        $supplier_user_id = notification_event_supplier_user_id($conn, (int)$po['supplier_id']);
        if ($supplier_user_id !== null) {
            $supplier_title = $new_status === 'ORDERED' ? 'Purchase Order baru diterbitkan' : 'Status Purchase Order berubah';
            $supplier_body = $new_status === 'ORDERED'
                ? 'Purchase Order #' . $po_number . ' telah diterbitkan dan menunggu pemrosesan.'
                : 'Purchase Order #' . $po_number . ' berubah dari ' . $po['status'] . ' menjadi ' . $new_status . '.';
            if (!notification_event_send($conn, $supplier_user_id, $supplier_title, $supplier_body, 'info')) {
                throw new Exception("Gagal mencatat notifikasi Supplier.");
            }
        }
        if (!$conn->commit()) throw new Exception("Gagal menyelesaikan transaksi status PO.");
        $transaction_started = false;
        $_SESSION["ui_notification"] = ["type" => "success", "message" => "Status PO #" . str_pad((string)$po_id, 5, "0", STR_PAD_LEFT) . " berhasil diperbarui menjadi " . app_status_label($new_status) . "."];
        header("Location: purchase_orders.php?action=detail&id=$po_id");
        exit;
    } catch (Exception $e) {
        if ($transaction_started) $conn->rollback();
        error_log("Admin purchase order status update failed");
        $_SESSION["ui_notification"] = ["type" => "error", "message" => "Status PO belum berhasil diperbarui. Silakan coba kembali."];
        header("Location: purchase_orders.php?action=detail&id=$po_id");
        exit;
    }
}

function poStatusClass($status) {
    return match($status) {
        'DRAFT'     => 'bg-stone-800 text-stone-300 border border-stone-700',
        'ORDERED'   => 'bg-cyan-500/10 text-cyan-400 border border-cyan-500/30',
        'SHIPPED'   => 'bg-purple-500/10 text-purple-400 border border-purple-500/30',
        'RECEIVED'  => 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/30',
        'CANCELLED' => 'bg-rose-500/10 text-rose-400 border border-rose-500/30',
        default     => 'bg-stone-800 text-stone-400 border border-stone-700'
    };
}
include "header.php";
?>

<div class="flex flex-col sm:flex-row sm:items-center justify-between mb-6 gap-4">
    <div>
        <h1 class="text-2xl font-extrabold text-white tracking-tight">Purchase Orders (PO)</h1>
        <p class="text-xs text-stone-400 mt-1">Pengadaan dan pemesanan stok bahan baku ke mitra supplier.</p>
    </div>
    <?php if ($action === 'list'): ?>
    <a href="?action=create" class="bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-400 hover:to-amber-500 text-stone-950 font-bold px-5 py-2.5 rounded-xl text-xs shadow-md shadow-amber-950/40 transition-all flex items-center gap-1.5 self-start sm:self-auto">
        <i data-lucide="plus-circle" class="w-4 h-4"></i> Buat PO Baru
    </a>
    <?php else: ?>
    <a href="purchase_orders.php" class="bg-[#1a2233] hover:bg-[#232d42] text-stone-300 border border-stone-700 px-4 py-2.5 rounded-xl text-xs font-semibold transition-all flex items-center gap-1.5 self-start sm:self-auto">
        <i data-lucide="arrow-left" class="w-4 h-4"></i> Kembali ke Daftar
    </a>
    <?php endif; ?>
</div>


<?php if (isset($error)): ?>
<div class="bg-rose-500/10 border border-rose-500/30 text-rose-300 px-4 py-3 rounded-xl text-xs mb-5 flex items-center gap-2">
    <i data-lucide="alert-circle" class="w-4 h-4 text-rose-400"></i>
    <span><?= htmlspecialchars($error) ?></span>
</div>
<?php endif; ?>

<?php if ($action === 'list'): ?>
    <div class="bg-[#0e131d]/90 border border-stone-800/80 rounded-2xl overflow-hidden shadow-xl">
        <div class="p-4 border-b border-stone-800/80 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <i data-lucide="clipboard-list" class="w-4 h-4 text-amber-400"></i>
                <h2 class="text-xs font-bold text-white uppercase tracking-wider">Histori Purchase Order</h2>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-[#141b29] text-stone-400 uppercase tracking-wider text-[11px]">
                    <tr>
                        <th class="p-4">No PO</th>
                        <th class="p-4">Tanggal Buat</th>
                        <th class="p-4">Supplier</th>
                        <th class="p-4">Total Nilai</th>
                        <th class="p-4">Status</th>
                        <th class="p-4 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-800/60">
                    <?php
                    $pos = $conn->query("SELECT po.*, s.name as supplier_name FROM purchase_orders po JOIN suppliers s ON po.supplier_id = s.id ORDER BY po.created_at DESC");
                    while ($po = $pos->fetch_assoc()):
                    ?>
                    <tr class="hover:bg-stone-800/30 transition-colors">
                        <td class="p-4 font-mono font-bold text-amber-400">#<?= str_pad($po['id'], 5, '0', STR_PAD_LEFT) ?></td>
                        <td class="p-4 text-stone-400 font-mono text-[11px]"><?= date("d M Y", strtotime($po['created_at'])) ?></td>
                        <td class="p-4 font-semibold text-white"><?= htmlspecialchars($po['supplier_name']) ?></td>
                        <td class="p-4 font-mono font-bold text-stone-200">Rp <?= number_format($po['total_amount'], 0, ',', '.') ?></td>
                        <td class="p-4">
                            <span class="px-2.5 py-1 rounded-full text-[10px] font-bold tracking-wide <?= poStatusClass($po["status"]) ?>">
                                <?= htmlspecialchars(app_status_label($po['status'])) ?>
                            </span>
                        </td>
                        <td class="p-4 text-center">
                            <a href="?action=detail&id=<?= $po['id'] ?>" class="bg-amber-500/10 hover:bg-amber-500/20 text-amber-400 border border-amber-500/30 px-3 py-1.5 rounded-lg text-xs font-semibold transition-all inline-flex items-center gap-1">
                                <i data-lucide="eye" class="w-3.5 h-3.5"></i> Detail
                            </a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                    <?php if ($pos->num_rows === 0): ?>
                    <tr><td colspan="6" class="p-8 text-center text-stone-500">Belum ada Purchase Order.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

<?php elseif ($action === 'create'): ?>
    <div class="bg-[#0e131d]/90 border border-stone-800/80 p-6 rounded-2xl shadow-xl">
        <form method="POST" action="?action=store" id="poForm" data-loading>
            <?= csrf_input() ?>
            <div class="mb-5">
                <label class="block text-xs font-semibold text-stone-300 uppercase tracking-wider mb-2">Pilih Mitra Supplier</label>
                <select name="supplier_id" required class="w-full bg-[#131926] border border-stone-800 focus:border-amber-500 focus:ring-1 focus:ring-amber-500 rounded-xl p-3 text-sm text-stone-200 transition-all outline-none">
                    <option value="">-- Pilih Supplier --</option>
                    <?php
                    $sups = $conn->query("SELECT * FROM suppliers");
                    while ($s = $sups->fetch_assoc()):
                    ?>
                    <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="mb-6">
                <div class="flex justify-between items-center mb-3">
                    <label class="block text-xs font-semibold text-stone-300 uppercase tracking-wider">Daftar Produk yang Dipesan</label>
                    <button type="button" onclick="addProductRow()" class="text-xs bg-[#1a2233] text-amber-400 hover:text-amber-300 px-3 py-1.5 rounded-lg border border-stone-700 transition-all flex items-center gap-1">
                        <i data-lucide="plus" class="w-3.5 h-3.5"></i> Tambah Item
                    </button>
                </div>

                <div id="product-rows" class="space-y-3">
                    <div class="flex gap-3 items-center">
                        <select name="products[]" required class="flex-grow bg-[#131926] border border-stone-800 focus:border-amber-500 rounded-xl p-2.5 text-sm text-stone-200 outline-none">
                            <option value="">-- Pilih Produk --</option>
                            <?php
                            $prods = $conn->query("SELECT * FROM products");
                            $options = "";
                            while ($p = $prods->fetch_assoc()){
                                $options .= "<option value='{$p['id']}'>" . htmlspecialchars($p['name']) . "</option>";
                            }
                            echo $options;
                            ?>
                        </select>
                        <input type="number" name="quantities[]" required min="1" placeholder="Qty" class="w-28 bg-[#131926] border border-stone-800 focus:border-amber-500 rounded-xl p-2.5 text-sm text-stone-200 outline-none font-mono">
                    </div>
                </div>
            </div>

            <div class="flex justify-end gap-3 pt-4 border-t border-stone-800/80">
                <a href="purchase_orders.php" class="bg-[#1a2233] hover:bg-[#232d42] text-stone-300 border border-stone-700 px-5 py-2.5 rounded-xl text-sm transition-all">Batal</a>
                <button type="submit" class="bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-400 hover:to-amber-500 text-stone-950 font-bold px-6 py-2.5 rounded-xl text-sm shadow-md shadow-amber-950/40 transition-all flex items-center gap-1.5">
                    <i data-lucide="save" class="w-4 h-4"></i> Simpan DRAFT PO
                </button>
            </div>
        </form>
    </div>
    <script>
    const productOptions = `<?= $options ?>`;
    function addProductRow() {
        const row = document.createElement('div');
        row.className = 'flex gap-3 items-center mt-3';
        row.innerHTML = `
            <select name="products[]" required class="flex-grow bg-[#131926] border border-stone-800 focus:border-amber-500 rounded-xl p-2.5 text-sm text-stone-200 outline-none">
                <option value="">-- Pilih Produk --</option>
                ${productOptions}
            </select>
            <input type="number" name="quantities[]" required min="1" placeholder="Qty" class="w-28 bg-[#131926] border border-stone-800 focus:border-amber-500 rounded-xl p-2.5 text-sm text-stone-200 outline-none font-mono">
            <button type="button" onclick="this.parentElement.remove()" class="text-rose-400 font-bold p-2 hover:bg-rose-500/10 rounded-lg">✕</button>
        `;
        document.getElementById('product-rows').appendChild(row);
    }
    </script>

<?php elseif ($action === 'detail' && isset($_GET['id'])):
    $po_id = (int)$_GET['id'];
    $po_res = $conn->query("SELECT po.*, s.name as supplier_name, s.contact_name, s.phone, s.address
                            FROM purchase_orders po JOIN suppliers s ON po.supplier_id = s.id
                            WHERE po.id = $po_id");
    if ($po_res->num_rows === 0) { echo "<div class='text-rose-400 p-4'>PO Tidak ditemukan.</div>"; exit; }
    $po = $po_res->fetch_assoc();
?>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="md:col-span-2">
            <div class="bg-[#0e131d]/90 border border-stone-800/80 rounded-2xl overflow-hidden shadow-xl mb-6">
                <div class="bg-[#141b29] px-6 py-4 border-b border-stone-800/80 flex justify-between items-center">
                    <h2 class="text-sm font-bold text-white uppercase tracking-wider">Detail Order #<?= str_pad($po['id'], 5, '0', STR_PAD_LEFT) ?></h2>
                    <span class="px-2.5 py-1 rounded-full text-[10px] font-bold tracking-wide <?= poStatusClass($po["status"]) ?>">
                        Status: <?= htmlspecialchars(app_status_label($po['status'])) ?>
                    </span>
                </div>
                <div class="p-6">
                    <table class="w-full text-xs text-left">
                        <thead class="bg-[#141b29] text-stone-400 uppercase tracking-wider text-[11px]">
                            <tr>
                                <th class="p-3">Produk</th>
                                <th class="p-3 text-center">Harga Satuan</th>
                                <th class="p-3 text-center">Qty</th>
                                <th class="p-3 text-right">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-stone-800/60">
                            <?php
                            $items = $conn->query("SELECT poi.*, p.name FROM purchase_order_items poi JOIN products p ON poi.product_id = p.id WHERE poi.purchase_order_id = $po_id");
                            while ($item = $items->fetch_assoc()):
                            ?>
                            <tr class="hover:bg-stone-800/30 transition-colors">
                                <td class="p-3 font-semibold text-white"><?= htmlspecialchars($item['name']) ?></td>
                                <td class="p-3 text-center font-mono text-stone-300">Rp <?= number_format($item['unit_price'], 0, ',', '.') ?></td>
                                <td class="p-3 text-center font-mono font-bold text-amber-400"><?= $item['quantity'] ?></td>
                                <td class="p-3 text-right font-mono font-bold text-stone-200">Rp <?= number_format($item['subtotal'], 0, ',', '.') ?></td>
                            </tr>
                            <?php endwhile; ?>
                            <tr class="font-bold text-white text-base bg-[#141b29]">
                                <td colspan="3" class="p-4 text-right text-xs uppercase text-stone-400">TOTAL PO:</td>
                                <td class="p-4 text-right text-amber-400 font-mono">Rp <?= number_format($po['total_amount'], 0, ',', '.') ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="space-y-6">
            <div class="bg-[#0e131d]/90 border border-stone-800/80 rounded-2xl p-6 shadow-xl">
                <h3 class="text-xs font-bold text-white uppercase tracking-wider mb-4 border-b border-stone-800/80 pb-2.5 flex items-center gap-2">
                    <i data-lucide="building" class="w-4 h-4 text-amber-400"></i> Informasi Supplier
                </h3>
                <p class="text-sm font-bold text-white"><?= htmlspecialchars($po['supplier_name']) ?></p>
                <p class="text-xs text-stone-400 mt-1"><?= htmlspecialchars($po['contact_name'] ?? '-') ?></p>
                <p class="text-xs font-mono text-stone-400 mt-1"><?= htmlspecialchars($po['phone'] ?? '-') ?></p>
            </div>

            <div class="bg-[#0e131d]/90 border border-stone-800/80 rounded-2xl p-6 shadow-xl">
                <h3 class="text-xs font-bold text-white uppercase tracking-wider mb-4 border-b border-stone-800/80 pb-2.5 flex items-center gap-2">
                    <i data-lucide="check-square" class="w-4 h-4 text-amber-400"></i> Aksi Transaksi
                </h3>

                <?php if ($po['status'] === 'DRAFT'): ?>
                    <form method="POST" action="?action=update_status" class="mb-3" data-loading data-confirm data-confirm-title="Kirim PO ke supplier" data-confirm-message="Purchase Order #{object} akan dikirim kepada supplier. Lanjutkan?" data-confirm-object="<?= str_pad((string)$po_id, 5, '0', STR_PAD_LEFT) ?>" data-confirm-label="Kirim PO">
                        <?= csrf_input() ?>
                        <input type="hidden" name="po_id" value="<?= $po_id ?>">
                        <input type="hidden" name="status" value="ORDERED">
                        <button type="submit" class="w-full bg-cyan-600 hover:bg-cyan-500 text-white py-2.5 rounded-xl shadow-md text-xs font-bold transition-all flex items-center justify-center gap-1.5">
                            <i data-lucide="send" class="w-3.5 h-3.5"></i> Kirim ke Supplier
                        </button>
                    </form>
                    <form method="POST" action="?action=update_status" data-loading data-confirm data-confirm-title="Batalkan PO" data-confirm-message="Purchase Order #{object} akan dibatalkan dan tidak dapat diproses lagi. Yakin ingin melanjutkan?" data-confirm-object="<?= str_pad((string)$po_id, 5, '0', STR_PAD_LEFT) ?>" data-confirm-label="Batalkan PO" data-confirm-danger="true">
                        <?= csrf_input() ?>
                        <input type="hidden" name="po_id" value="<?= $po_id ?>">
                        <input type="hidden" name="status" value="CANCELLED">
                        <button type="submit" class="w-full bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 border border-rose-500/30 py-2.5 rounded-xl text-xs font-semibold transition-all">
                            Batalkan PO
                        </button>
                    </form>
                <?php elseif ($po['status'] === 'ORDERED' || $po['status'] === 'SHIPPED'): ?>
                    <form method="POST" action="?action=update_status" data-loading data-confirm data-confirm-title="Konfirmasi penerimaan barang" data-confirm-message="Konfirmasi penerimaan seluruh barang pada PO #{object}. Stok produk akan diperbarui." data-confirm-object="<?= str_pad((string)$po_id, 5, '0', STR_PAD_LEFT) ?>" data-confirm-label="Ya, barang diterima">
                        <?= csrf_input() ?>
                        <input type="hidden" name="po_id" value="<?= $po_id ?>">
                        <input type="hidden" name="status" value="RECEIVED">
                        <button type="submit" class="w-full bg-gradient-to-r from-emerald-500 to-emerald-600 hover:from-emerald-400 hover:to-emerald-500 text-stone-950 py-3 rounded-xl shadow-lg shadow-emerald-950/40 text-xs font-bold transition-all flex items-center justify-center gap-1.5">
                            <i data-lucide="check-circle-2" class="w-4 h-4"></i> Terima Barang &amp; Update Stok
                        </button>
                    </form>
                <?php elseif ($po['status'] === 'RECEIVED'): ?>
                    <div class="text-center p-4 bg-emerald-500/10 text-emerald-400 rounded-xl border border-emerald-500/30 text-xs font-semibold flex items-center justify-center gap-2">
                        <i data-lucide="check-circle" class="w-4 h-4"></i> Selesai - Stok masuk inventory
                    </div>
                <?php elseif ($po['status'] === 'CANCELLED'): ?>
                    <div class="text-center p-4 bg-rose-500/10 text-rose-400 rounded-xl border border-rose-500/30 text-xs font-semibold flex items-center justify-center gap-2">
                        <i data-lucide="x-circle" class="w-4 h-4"></i> Dibatalkan
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php include "footer.php"; ?>
