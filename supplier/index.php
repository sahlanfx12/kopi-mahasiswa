<?php
if (session_status() == PHP_SESSION_NONE) session_start();
if (!isset($_SESSION["user_id"]) || strtolower($_SESSION["role"]) !== "supplier") {
    header("Location: ../login.php");
    exit;
}
require "../config.php";
require_once "../includes/csrf.php";
require_once "../includes/interaction_ui.php";
require_once "../includes/notification_events.php";
// [SECURITY] Live suspended-account guard — deny access if account was suspended after login
if (isset($conn)) {
    $__uid = (int)$_SESSION["user_id"];
    $__sq = $conn->prepare("SELECT status FROM users WHERE id=? LIMIT 1");
    $__sq->bind_param("i", $__uid);
    $__sq->execute();
    $__row = $__sq->get_result()->fetch_assoc();
    if (($__row["status"] ?? "active") === "suspended") {
        session_destroy();
        header("Location: ../login.php?err=suspended");
        exit;
    }
    unset($__sq, $__row, $__uid);
}
if (!isset($_SESSION["role_id"])) {
    $r = $conn->query("SELECT role_id FROM users WHERE id=" . (int)$_SESSION["user_id"])->fetch_assoc();
    if ($r) $_SESSION["role_id"] = $r["role_id"];
}
$conn->query("UPDATE users SET last_active=NOW() WHERE id=".(int)$_SESSION["user_id"]);
require_once __DIR__ . '/../includes/notification_center.php';
$notification_unread = notification_center_unread_count($conn, (int)$_SESSION['user_id']);

// Ambil data supplier berdasarkan user_id yang login
$user_id = (int)$_SESSION["user_id"];
$supplier_res = $conn->query("SELECT * FROM suppliers WHERE user_id = $user_id LIMIT 1");
$supplier = $supplier_res->fetch_assoc();

// Update status PO jika ada request
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["update_status"]) && $supplier) {
    $transaction_started = false;
    $po_id = filter_var($_POST["po_id"] ?? null, FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]]);
    $status = $_POST["status"] ?? "";

    if ($po_id === false || $status !== "SHIPPED") {
        $_SESSION["ui_notification"] = ["type" => "error", "message" => "Permintaan perubahan status tidak valid."];
    } else {
        try {
            if (!$conn->begin_transaction()) throw new RuntimeException("Supplier PO status transaction could not start.");
            $transaction_started = true;
            $stmt = $conn->prepare(
                "UPDATE purchase_orders SET status = ?
                 WHERE id = ? AND supplier_id = ? AND status = 'ORDERED'"
            );
            if (!$stmt) throw new RuntimeException("Supplier PO status update could not be prepared.");
            $stmt->bind_param("sii", $status, $po_id, $supplier["id"]);
            if (!$stmt->execute()) {
                throw new RuntimeException("Purchase order status update failed.");
            }
            $changed = $stmt->affected_rows > 0;
            if ($changed) {
                $po_number = str_pad((string)$po_id, 5, "0", STR_PAD_LEFT);
                if (!notification_event_send_roles($conn, ['Admin'], 'Status Purchase Order berubah', 'Purchase Order #' . $po_number . ' berubah menjadi SHIPPED.', 'info')) {
                    throw new RuntimeException("Admin PO notification failed.");
                }
                if (!notification_event_send($conn, (int)$user_id, 'Status Purchase Order berubah', 'Purchase Order #' . $po_number . ' berubah menjadi SHIPPED.', 'info')) {
                    throw new RuntimeException("Supplier PO notification failed.");
                }
            }
            if (!$conn->commit()) throw new RuntimeException("Supplier PO status transaction could not commit.");
            $transaction_started = false;
            $_SESSION["ui_notification"] = $stmt->affected_rows > 0
                ? ["type" => "success", "message" => "Status PO #" . str_pad((string)$po_id, 5, "0", STR_PAD_LEFT) . " berhasil diperbarui menjadi " . app_status_label($status) . "."]
                : ["type" => "info", "message" => "Tidak ada perubahan. PO mungkin tidak ditemukan, bukan milik akun Anda, atau statusnya sudah berubah."];
        } catch (Throwable $e) {
            if (!empty($transaction_started)) $conn->rollback();
            error_log("Supplier purchase order status update failed");
            $_SESSION["ui_notification"] = ["type" => "error", "message" => "Status PO belum berhasil diperbarui. Silakan coba kembali."];
        }
    }
    header("Location: index.php");
    exit;
}

// Ambil Purchase Orders milik supplier ini
$pos = [];
if ($supplier) {
    $res = $conn->query(
        "SELECT po.*,
                GROUP_CONCAT(p.name SEPARATOR ', ') as product_names,
                SUM(poi.quantity) as total_items
         FROM purchase_orders po
         LEFT JOIN purchase_order_items poi ON po.id = poi.purchase_order_id
         LEFT JOIN products p ON poi.product_id = p.id
         WHERE po.supplier_id = {$supplier['id']}
         GROUP BY po.id
         ORDER BY po.created_at DESC"
    );
    while ($row = $res->fetch_assoc()) {
        $pos[] = $row;
    }
}
$po_status_totals = array_count_values(array_map(static fn($po) => (string)($po['status'] ?? ''), $pos));

// Status color map (Bybit style trading badges)
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
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/css/supplier-portal.css">
    <title>Supplier Portal — Kopi Mahasiswa</title>
    <?php require_once __DIR__ . '/../includes/theme_head.php'; ?>
</head>
<body class="bg-[#090c12] text-stone-200 min-h-screen flex flex-col antialiased selection:bg-amber-500/20 selection:text-amber-300">

<?php $supplier_navigation_active = 'dashboard'; require_once __DIR__ . '/../includes/supplier_navigation.php'; ?>

<main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 w-full flex-grow">
    <!-- Header Supplier Profile -->
    <div class="bg-[#0e131d]/90 border border-stone-800/80 rounded-2xl p-6 shadow-xl mb-8">
        <div>
            <div class="flex items-center gap-2 text-amber-400 mb-1">
                <i data-lucide="truck" class="w-4 h-4"></i>
                <span class="text-xs uppercase font-bold tracking-wider">Dashboard Supplier</span>
            </div>
            <h1 class="text-2xl font-extrabold text-white tracking-tight">Ringkasan Purchase Order</h1>
            <?php if ($supplier): ?>
            <p class="text-xs text-stone-400 mt-1 flex flex-wrap items-center gap-2">
                <strong class="text-white"><?= htmlspecialchars($supplier["name"]) ?></strong>
                <span class="text-stone-600">&bull;</span>
                <span>Kontak: <?= htmlspecialchars($supplier["contact_name"] ?? "-") ?></span>
                <span class="text-stone-600">&bull;</span>
                <span class="font-mono"><?= htmlspecialchars($supplier["phone"] ?? "-") ?></span>
            </p>
            <?php else: ?>
            <div class="mt-3 bg-amber-500/10 border border-amber-500/30 rounded-xl p-3 text-xs text-amber-300 flex items-center gap-2">
                <i data-lucide="alert-triangle" class="w-4 h-4 text-amber-400 flex-shrink-0"></i>
                <span>Akun Anda belum terhubung ke entitas data supplier. Hubungi Admin Kopi Mahasiswa untuk penautan ID.</span>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <section class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 mb-8" aria-label="Ringkasan Purchase Order">
        <article class="rounded-2xl border border-stone-800/80 bg-[#0e131d]/90 p-4 sm:p-5 shadow-lg">
            <p class="text-xs font-semibold text-stone-400">Total Purchase Order</p>
            <p class="mt-2 text-2xl font-extrabold text-white"><?= count($pos) ?></p>
            <p class="mt-1 text-xs text-stone-500">Seluruh pesanan yang ditugaskan</p>
        </article>
        <article class="rounded-2xl border border-cyan-500/20 bg-[#0e131d]/90 p-4 sm:p-5 shadow-lg">
            <p class="text-xs font-semibold text-stone-400">Menunggu Pengiriman</p>
            <p class="mt-2 text-2xl font-extrabold text-cyan-300"><?= (int)($po_status_totals['ORDERED'] ?? 0) ?></p>
            <p class="mt-1 text-xs text-stone-500">Perlu dikonfirmasi saat dikirim</p>
        </article>
        <article class="rounded-2xl border border-purple-500/20 bg-[#0e131d]/90 p-4 sm:p-5 shadow-lg">
            <p class="text-xs font-semibold text-stone-400">Dalam Pengiriman</p>
            <p class="mt-2 text-2xl font-extrabold text-purple-300"><?= (int)($po_status_totals['SHIPPED'] ?? 0) ?></p>
            <p class="mt-1 text-xs text-stone-500">Sudah dikonfirmasi dikirim</p>
        </article>
        <article class="rounded-2xl border border-emerald-500/20 bg-[#0e131d]/90 p-4 sm:p-5 shadow-lg">
            <p class="text-xs font-semibold text-stone-400">Telah Diterima</p>
            <p class="mt-2 text-2xl font-extrabold text-emerald-300"><?= (int)($po_status_totals['RECEIVED'] ?? 0) ?></p>
            <p class="mt-1 text-xs text-stone-500">Pesanan yang selesai diproses</p>
        </article>
    </section>

    <!-- Panduan Status (Trading Info Box) -->
    <div class="bg-[#0e131d]/60 border border-stone-800/80 rounded-2xl p-4 mb-6">
        <h3 class="text-xs font-bold text-white uppercase tracking-wider mb-2.5 flex items-center gap-1.5">
            <i data-lucide="info" class="w-3.5 h-3.5 text-amber-400"></i> Alur Status Purchase Order:
        </h3>
        <div class="flex flex-wrap gap-2 text-[11px]">
            <span class="bg-stone-800 text-stone-400 px-2.5 py-1 rounded-lg border border-stone-700"><strong><?= htmlspecialchars(app_status_label('DRAFT')) ?>:</strong> Persiapan oleh Admin</span>
            <span class="bg-cyan-500/10 text-cyan-400 px-2.5 py-1 rounded-lg border border-cyan-500/30"><strong><?= htmlspecialchars(app_status_label('ORDERED')) ?>:</strong> Pesanan resmi masuk ke Anda</span>
            <span class="bg-purple-500/10 text-purple-400 px-2.5 py-1 rounded-lg border border-purple-500/30"><strong><?= htmlspecialchars(app_status_label('SHIPPED')) ?>:</strong> Konfirmasi bahwa barang sedang dikirim</span>
            <span class="bg-emerald-500/10 text-emerald-400 px-2.5 py-1 rounded-lg border border-emerald-500/30"><strong><?= htmlspecialchars(app_status_label('RECEIVED')) ?>:</strong> Diterima dan masuk stok gudang</span>
            <span class="bg-rose-500/10 text-rose-400 px-2.5 py-1 rounded-lg border border-rose-500/30"><strong><?= htmlspecialchars(app_status_label('CANCELLED')) ?>:</strong> Dibatalkan</span>
        </div>
    </div>

    <!-- PO Table -->
    <section id="purchase-orders" aria-labelledby="purchase-orders-heading" class="scroll-mt-28 bg-[#0e131d]/90 border border-stone-800/80 rounded-2xl overflow-hidden shadow-xl">
        <div class="p-4 sm:p-5 border-b border-stone-800/80 flex justify-between items-center gap-3">
            <div class="flex items-center gap-2">
                <i data-lucide="clipboard-list" class="w-4 h-4 text-amber-400"></i>
                <h2 id="purchase-orders-heading" class="text-sm font-bold text-white">Purchase Order yang Ditugaskan</h2>
            </div>
            <span class="text-xs font-mono text-stone-500"><?= count($pos) ?> Record</span>
        </div>

        <?php if (empty($pos)): ?>
        <div class="text-center py-16 text-stone-500">
            <i data-lucide="package-open" class="w-12 h-12 mx-auto mb-3 text-stone-600"></i>
            <p class="font-medium text-white text-sm">Belum ada Purchase Order</p>
            <p class="text-xs text-stone-400 mt-1">PO akan otomatis tampil di tabel ini saat Admin membuat pesanan bahan baku ke mitra Anda.</p>
        </div>
        <?php else: ?>
        <div class="supplier-po-table-container overflow-x-auto" role="region" aria-label="Tabel Purchase Order, geser untuk melihat kolom lainnya" tabindex="0">
            <table class="w-full min-w-[900px] text-left text-xs">
                <thead class="bg-[#141b29] text-stone-400 uppercase tracking-wider text-[11px]">
                    <tr>
                        <th class="p-4">No PO</th>
                        <th class="p-4">Produk / Bahan</th>
                        <th class="p-4">Volume Item</th>
                        <th class="p-4">Total Nilai</th>
                        <th class="p-4">Status</th>
                        <th class="p-4">Tanggal Masuk</th>
                        <th class="p-4 text-center">Tindakan Pengiriman</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-800/60">
                    <?php foreach ($pos as $po): ?>
                    <tr class="hover:bg-stone-800/30 transition-colors">
                        <td class="p-4 font-mono font-bold text-amber-400">#<?= str_pad($po["id"], 5, "0", STR_PAD_LEFT) ?></td>
                        <td class="p-4 text-stone-200 font-medium max-w-xs truncate"><?= htmlspecialchars($po["product_names"] ?? "-") ?></td>
                        <td class="p-4 text-stone-400 font-mono"><?= (int)($po["total_items"]) ?> item</td>
                        <td class="p-4 font-mono font-bold text-stone-200">Rp <?= number_format($po["total_amount"], 0, ',', '.') ?></td>
                        <td class="p-4">
                            <span class="px-2.5 py-1 rounded-full text-[10px] font-bold tracking-wide <?= poStatusClass($po["status"]) ?>">
                                <?= htmlspecialchars(app_status_label($po["status"])) ?>
                            </span>
                        </td>
                        <td class="p-4 text-stone-500 font-mono text-[11px]"><?= date("d M Y", strtotime($po["created_at"])) ?></td>
                        <td class="p-4 text-center">
                            <?php if ($po["status"] === "ORDERED"): ?>
                            <form method="POST" class="inline" data-loading data-confirm data-confirm-title="Konfirmasi pengiriman PO" data-confirm-message="Konfirmasikan bahwa PO #{object} sudah dikirimkan?" data-confirm-object="<?= str_pad((string)$po["id"], 5, "0", STR_PAD_LEFT) ?>" data-confirm-label="Ya, tandai dikirim">
                                <?php if(function_exists("csrf_input")) echo csrf_input(); ?>
                                <input type="hidden" name="po_id" value="<?= $po["id"] ?>">
                                <input type="hidden" name="status" value="SHIPPED">
                                <button name="update_status" type="submit"
                                        class="bg-gradient-to-r from-purple-600 to-purple-700 hover:from-purple-500 hover:to-purple-600 text-white text-xs font-bold px-3.5 py-1.5 rounded-lg shadow-md transition-all flex items-center gap-1.5 mx-auto"
                                        >
                                    <i data-lucide="truck" class="w-3.5 h-3.5"></i> Tandai Dikirim
                                </button>
                            </form>
                            <?php elseif ($po["status"] === "SHIPPED"): ?>
                                <span class="text-purple-400 text-xs font-medium flex items-center justify-center gap-1">
                                    <i data-lucide="check" class="w-3.5 h-3.5"></i> Sedang Dikirim
                                </span>
                            <?php elseif ($po["status"] === "RECEIVED"): ?>
                                <span class="text-emerald-400 text-xs font-semibold flex items-center justify-center gap-1">
                                    <i data-lucide="check-circle" class="w-3.5 h-3.5"></i> Diterima &amp; Selesai
                                </span>
                            <?php elseif ($po["status"] === "CANCELLED"): ?>
                                <span class="text-rose-400 text-xs flex items-center justify-center gap-1">
                                    <i data-lucide="x-circle" class="w-3.5 h-3.5"></i> Dibatalkan
                                </span>
                            <?php else: ?>
                                <span class="text-stone-500 text-xs">Menunggu Konfirmasi...</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </section>
</main>

<?php require_once __DIR__ . '/../includes/theme_footer.php'; ?>
</body>
</html>
