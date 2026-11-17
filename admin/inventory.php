<?php
require "../config.php"; require_once "../includes/csrf.php";
require_once "../includes/interaction_ui.php";
require_once "../includes/notification_events.php";

if (empty($_SESSION["user_id"]) || strtolower($_SESSION["role"] ?? "") !== "admin") {
    header("Location: ../login.php");
    exit;
}
$auth_stmt = $conn->prepare("SELECT status FROM users WHERE id = ? LIMIT 1");
$auth_user_id = (int)$_SESSION["user_id"];
$auth_stmt->bind_param("i", $auth_user_id);
$auth_stmt->execute();
$auth_user = $auth_stmt->get_result()->fetch_assoc();
if (($auth_user["status"] ?? "active") === "suspended") {
    session_destroy();
    header("Location: ../login.php?err=suspended");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $raw_sid = $_POST["stock_id"] ?? null;
    $raw_qty = $_POST["qty"] ?? null;
    $sid = is_scalar($raw_sid) ? filter_var($raw_sid, FILTER_VALIDATE_INT) : false;
    $qty = is_scalar($raw_qty) ? filter_var($raw_qty, FILTER_VALIDATE_INT) : false;
    $type = $_POST["type"] ?? '';

    if ($sid === false || $sid <= 0 || $qty === false || $qty <= 0 || !in_array($type, ['IN', 'OUT'], true)) {
        $_SESSION["ui_notification"] = ["type" => "error", "message" => "Data mutasi stok tidak valid."];
    } else {
        $transaction_started = false;
    try {
        if (!$conn->begin_transaction()) throw new Exception("Gagal memulai transaksi.");
        $transaction_started = true;

        $stock_info_stmt = $conn->prepare("SELECT s.current_stock, s.minimum_stock, p.name FROM stock s JOIN products p ON p.id = s.product_id WHERE s.id = ? FOR UPDATE");
        if (!$stock_info_stmt) throw new Exception("Gagal memeriksa stok.");
        $stock_info_stmt->bind_param("i", $sid);
        if (!$stock_info_stmt->execute()) throw new Exception("Gagal memeriksa stok.");
        $stock_info = $stock_info_stmt->get_result()->fetch_assoc();
        if (!$stock_info) throw new Exception("Item stok tidak ditemukan.");
        $old_stock = (int)$stock_info['current_stock'];

        if ($type === 'IN') {
            $update_stmt = $conn->prepare("UPDATE stock SET current_stock = current_stock + ? WHERE id = ?");
            if (!$update_stmt) throw new Exception("Gagal menyiapkan pembaruan stok.");
            $update_stmt->bind_param("ii", $qty, $sid);
            $updated = $update_stmt->execute();
        } else {
            $update_stmt = $conn->prepare("UPDATE stock SET current_stock = current_stock - ? WHERE id = ? AND current_stock >= ?");
            if (!$update_stmt) throw new Exception("Gagal menyiapkan pembaruan stok.");
            $update_stmt->bind_param("iii", $qty, $sid, $qty);
            $updated = $update_stmt->execute();
        }
        if (!$updated || $update_stmt->affected_rows !== 1) {
            throw new Exception($type === 'OUT' ? "Stok kurang atau item tidak ditemukan!" : "Item stok tidak ditemukan atau gagal diperbarui!");
        }

        $log_stmt = $conn->prepare("INSERT INTO stock_transactions (stock_id, type, quantity, reference_type, notes) VALUES (?, ?, ?, 'MANUAL', 'Adjustment Admin')");
        if (!$log_stmt) throw new Exception("Gagal menyiapkan mutasi stok.");
        $log_stmt->bind_param("isi", $sid, $type, $qty);
        if (!$log_stmt->execute()) throw new Exception("Gagal mencatat mutasi stok.");
        $new_stock = $type === 'IN' ? $old_stock + $qty : $old_stock - $qty;
        if (!notification_event_stock_change($conn, (string)$stock_info['name'], $old_stock, $new_stock, (int)$stock_info['minimum_stock'])) {
            throw new Exception("Gagal mencatat notifikasi stok.");
        }
        if (!$conn->commit()) throw new Exception("Gagal menyelesaikan transaksi.");
        $transaction_started = false;
        $_SESSION["ui_notification"] = ["type" => "success", "message" => "Mutasi stok berhasil dicatat."];
    } catch(Exception $e) {
        if ($transaction_started) $conn->rollback();
        error_log("Admin stock adjustment failed");
        $_SESSION["ui_notification"] = ["type" => "error", "message" => "Stok belum berhasil diperbarui. Pastikan jumlah stok mencukupi, lalu coba kembali."];
    }
    }
    header("Location: inventory.php");
    exit;
}
$stocks = $conn->query("SELECT s.*, p.name FROM stock s JOIN products p ON s.product_id = p.id ORDER BY s.current_stock ASC");
include "header.php";
?>

<div class="mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
    <div>
        <h1 class="text-2xl font-extrabold text-white tracking-tight">Manajemen Inventory &amp; Stok</h1>
        <p class="text-xs text-stone-400 mt-1">Audit dan penyesuaian langsung stok fisik gudang Kopi Mahasiswa.</p>
    </div>
</div>

<!-- AI Restock Recommendation Panel (Trading Intelligence Style) -->
<div class="mb-6 bg-[#0e131d]/90 border border-stone-800/80 p-5 rounded-2xl shadow-xl relative overflow-hidden">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-stone-800/80">
        <div class="flex items-center gap-2.5">
            <div class="w-8 h-8 rounded-lg bg-amber-500/10 text-amber-400 flex items-center justify-center">
                <i data-lucide="bot" class="w-4 h-4"></i>
            </div>
            <div>
                <h2 class="text-sm font-bold text-white">AI Restock Intelligence</h2>
                <p class="text-[11px] text-stone-400">Analisis rasio stok saat ini terhadap histori penjualan</p>
            </div>
        </div>
        <a href="inventory.php?ai_insight=1" class="bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-400 hover:to-amber-500 text-stone-950 font-bold px-4 py-2 rounded-xl text-xs transition-all shadow-md shadow-amber-950/40 flex items-center gap-1.5 self-start sm:self-auto">
            <i data-lucide="sparkles" class="w-3.5 h-3.5"></i> Tampilkan Analisis AI
        </a>
    </div>

    <?php if(isset($_GET["ai_insight"])):
        require_once "../includes/ai_service.php";
        $st_query = $conn->query("SELECT p.name, s.current_stock, IFNULL(SUM(st.quantity), 0) as total_sold FROM products p JOIN stock s ON p.id = s.product_id LEFT JOIN stock_transactions st ON s.id = st.stock_id AND st.type = 'OUT' GROUP BY p.id");
        $data_str = "";
        while($r = $st_query->fetch_assoc()){ $data_str .= "- {$r['name']}: Stok {$r['current_stock']}, Terjual {$r['total_sold']}\n"; }
        $prompt = "Tolong berikan ringkasan 2 paragraf singkat dalam bahasa Indonesia sebagai Manager Gudang F&B. Data saat ini:\n" . $data_str . "\n\nBerikan rekomendasi restock mana saja yang stoknya menipis dibandingkan penjualannya.";
        $reply = get_ai_response($prompt);
    ?>
    <div class="mt-4 p-4 bg-[#141b29] rounded-xl border border-stone-800 text-xs text-stone-300 leading-relaxed font-sans">
        <div class="flex items-center gap-2 text-amber-400 font-semibold mb-2">
            <i data-lucide="info" class="w-4 h-4"></i> Hasil Analisis AI:
        </div>
        <?= nl2br(htmlspecialchars($reply)) ?>
    </div>
    <?php endif; ?>
</div>

<?php if(isset($msg)): ?>
<div class="bg-emerald-500/10 border border-emerald-500/30 text-emerald-300 px-4 py-3 rounded-xl text-xs mb-5 flex items-center gap-2">
    <i data-lucide="check-circle" class="w-4 h-4 text-emerald-400"></i>
    <span><?= htmlspecialchars($msg) ?></span>
</div>
<?php endif; ?>

<?php if(isset($err)): ?>
<div class="bg-rose-500/10 border border-rose-500/30 text-rose-300 px-4 py-3 rounded-xl text-xs mb-5 flex items-center gap-2">
    <i data-lucide="alert-circle" class="w-4 h-4 text-rose-400"></i>
    <span><?= htmlspecialchars($err) ?></span>
</div>
<?php endif; ?>

<!-- Inventory Table -->
<div class="bg-[#0e131d]/90 border border-stone-800/80 rounded-2xl overflow-hidden shadow-xl">
    <div class="p-4 border-b border-stone-800/80 flex items-center justify-between">
        <div class="flex items-center gap-2">
            <i data-lucide="boxes" class="w-4 h-4 text-amber-400"></i>
            <h2 class="text-xs font-bold text-white uppercase tracking-wider">Tabel Posisi Stok Barang</h2>
        </div>
        <span class="text-xs text-stone-500 font-mono"><?= $stocks->num_rows ?> Item Terpantau</span>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-left text-xs">
            <thead class="bg-[#141b29] text-stone-400 uppercase tracking-wider text-[11px]">
                <tr>
                    <th class="p-4">Nama Produk</th>
                    <th class="p-4">Stok Saat Ini</th>
                    <th class="p-4">Status</th>
                    <th class="p-4">Penyesuaian Manual Stok</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-800/60">
                <?php while($s = $stocks->fetch_assoc()):
                    $min = $s['minimum_stock'] ?? 5;
                    $is_low = $s['current_stock'] <= $min;
                ?>
                <tr class="hover:bg-stone-800/30 transition-colors">
                    <td class="p-4 font-bold text-white text-sm"><?= htmlspecialchars($s['name']) ?></td>
                    <td class="p-4 font-mono font-bold text-base <?= $is_low ? 'text-rose-400' : 'text-emerald-400' ?>">
                        <?= $s['current_stock'] ?>
                    </td>
                    <td class="p-4">
                        <?php if ($is_low): ?>
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-rose-500/10 text-rose-400 border border-rose-500/30">
                                Stok Menipis
                            </span>
                        <?php else: ?>
                            <span class="px-2 py-0.5 rounded text-[10px] font-medium bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                                Aman
                            </span>
                        <?php endif; ?>
                    </td>
                    <td class="p-4">
                        <form method="POST" data-loading data-confirm data-confirm-title="Catat mutasi stok" data-confirm-message="Yakin ingin mencatat {status} untuk produk “{object}”?" data-confirm-object="<?= htmlspecialchars($s['name'], ENT_QUOTES, 'UTF-8') ?>" data-confirm-label="Catat mutasi">
                            <?php if(function_exists("csrf_input")) echo csrf_input(); ?>
                            <input type="hidden" name="stock_id" value="<?= $s['id'] ?>">
                            <select name="type" class="bg-[#131926] border border-stone-800 text-stone-200 text-xs rounded-lg px-2.5 py-1.5 outline-none focus:border-amber-500 font-semibold">
                                <option value="IN">+ Stok Masuk</option>
                                <option value="OUT">− Stok Keluar</option>
                            </select>
                            <input type="number" name="qty" required min="1" placeholder="Qty"
                                   class="bg-[#131926] border border-stone-800 text-stone-200 text-xs rounded-lg px-3 py-1.5 w-20 outline-none focus:border-amber-500 font-mono">
                            <button type="submit" class="bg-[#1a2233] hover:bg-[#232d42] text-stone-200 border border-stone-700 px-3 py-1.5 rounded-lg text-xs font-semibold transition-all">
                                Perbarui
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include "footer.php"; ?>
