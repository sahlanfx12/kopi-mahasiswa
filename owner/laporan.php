<?php
if (session_status() == PHP_SESSION_NONE) session_start();
if (!isset($_SESSION["user_id"]) || strtolower($_SESSION["role"]) !== "owner") { header("Location: ../login.php"); exit; }
require "../config.php";
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

$start_date = isset($_GET['start_date']) ? $conn->real_escape_string($_GET['start_date']) : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $conn->real_escape_string($_GET['end_date']) : date('Y-m-d');

$query = "SELECT SUM(total_amount) as total_sales, COUNT(id) as total_tx FROM orders WHERE status = 'COMPLETED' AND DATE(created_at) >= '$start_date' AND DATE(created_at) <= '$end_date'";
$summary = $conn->query($query)->fetch_assoc();

$items_query = "SELECT p.name, SUM(oi.quantity) as qty, SUM(oi.subtotal) as subtotal
                FROM order_items oi JOIN orders o ON oi.order_id = o.id JOIN products p ON oi.product_id = p.id
                WHERE o.status = 'COMPLETED' AND DATE(o.created_at) >= '$start_date' AND DATE(o.created_at) <= '$end_date'
                GROUP BY p.id ORDER BY qty DESC";
$items = $conn->query($items_query);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Penjualan — Kopi Mahasiswa</title>
    <?php require_once __DIR__ . '/../includes/theme_head.php'; ?>
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: #fff !important; color: #000 !important; }
            .print-card { background: #fff !important; border: 1px solid #ccc !important; color: #000 !important; }
            .print-text-dark { color: #000 !important; }
        }
    </style>
</head>
<body class="bg-[#090c12] text-stone-200 min-h-screen flex flex-col antialiased selection:bg-amber-500/20 selection:text-amber-300">

<!-- Executive Navbar -->
<nav class="sticky top-0 z-40 bg-[#0e131d]/95 backdrop-blur-xl border-b border-stone-800/80 no-print">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center h-16">
            <div class="flex items-center gap-3">
                <a href="index.php" class="flex items-center gap-2.5">
                    <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-amber-500 to-amber-700 flex items-center justify-center text-stone-950 shadow-md shadow-amber-950/40">
                        <i data-lucide="coffee" class="w-5 h-5"></i>
                    </div>
                    <div>
                        <span class="font-extrabold text-base sm:text-lg tracking-tight text-white">Kopi Mahasiswa</span>
                        <span class="ml-2 text-[10px] tracking-widest uppercase px-2 py-0.5 rounded-full bg-purple-500/10 text-purple-400 border border-purple-500/30 font-bold">Owner</span>
                    </div>
                </a>
            </div>

            <div class="flex items-center space-x-2 text-xs font-medium">
                <a href="index.php" class="px-3.5 py-2 rounded-lg text-stone-300 hover:text-white bg-[#141b29] border border-stone-800 transition-all flex items-center gap-1.5">
                    <i data-lucide="line-chart" class="w-3.5 h-3.5 text-stone-400"></i> Ringkasan
                </a>
                <a href="laporan.php" class="px-3.5 py-2 rounded-lg bg-amber-500/10 text-amber-400 border border-amber-500/30 font-semibold flex items-center gap-1.5">
                    <i data-lucide="file-text" class="w-3.5 h-3.5"></i> Laporan
                </a>
                <?php require_once __DIR__ . '/../includes/theme_toggle.php'; ?>
                <a href="../logout.php" class="bg-rose-500/10 border border-rose-500/30 hover:bg-rose-500/20 text-rose-400 px-3 py-1.5 rounded-lg text-xs font-semibold transition-all flex items-center gap-1 ml-2">
                    <i data-lucide="log-out" class="w-3.5 h-3.5"></i> Keluar
                </a>
            </div>
        </div>
    </div>
</nav>

<main class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8 w-full flex-grow">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between mb-6 gap-4">
        <div>
            <h1 class="text-2xl font-extrabold text-white tracking-tight">Laporan Penjualan &amp; Omzet</h1>
            <p class="text-xs text-stone-400 mt-1">Audit rincian performa transaksi berdasarkan rentang tanggal.</p>
        </div>
    </div>

    <!-- Filter Bar (No print) -->
    <form method="GET" class="bg-[#0e131d]/90 border border-stone-800/80 p-5 rounded-2xl shadow-xl mb-6 flex flex-wrap gap-4 items-end no-print">
        <div>
            <label class="block text-xs font-semibold text-stone-300 uppercase tracking-wider mb-1.5">Mulai Tanggal</label>
            <input type="date" name="start_date" value="<?= $start_date ?>"
                   class="bg-[#131926] border border-stone-800 focus:border-amber-500 focus:ring-1 focus:ring-amber-500 rounded-xl px-3.5 py-2 text-xs text-stone-200 outline-none">
        </div>
        <div>
            <label class="block text-xs font-semibold text-stone-300 uppercase tracking-wider mb-1.5">Sampai Tanggal</label>
            <input type="date" name="end_date" value="<?= $end_date ?>"
                   class="bg-[#131926] border border-stone-800 focus:border-amber-500 focus:ring-1 focus:ring-amber-500 rounded-xl px-3.5 py-2 text-xs text-stone-200 outline-none">
        </div>
        <div class="flex items-center gap-2">
            <button type="submit" class="bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-400 hover:to-amber-500 text-stone-950 font-bold px-5 py-2 rounded-xl text-xs shadow-md transition-all flex items-center gap-1.5">
                <i data-lucide="filter" class="w-3.5 h-3.5"></i> Terapkan Filter
            </button>
            <button type="button" onclick="window.print()" class="bg-[#1a2233] hover:bg-[#232d42] text-stone-200 border border-stone-700 px-4 py-2 rounded-xl text-xs font-semibold transition-all flex items-center gap-1.5">
                <i data-lucide="printer" class="w-3.5 h-3.5"></i> Cetak Dokumen
            </button>
        </div>
    </form>

    <!-- Metric summary cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5 mb-8">
        <div class="bg-[#0e131d]/90 border border-stone-800/80 rounded-2xl p-6 shadow-xl print-card">
            <div class="flex items-center justify-between text-stone-400 mb-2">
                <span class="text-xs font-semibold uppercase tracking-wider print-text-dark">Total Penjualan (Periode Terpilih)</span>
                <i data-lucide="wallet" class="w-5 h-5 text-emerald-400"></i>
            </div>
            <p class="text-3xl font-extrabold font-mono text-emerald-400 mt-2 print-text-dark">Rp <?= number_format($summary['total_sales'] ?? 0, 0, ',', '.') ?></p>
            <span class="text-[11px] text-stone-500 font-mono mt-1 block"><?= date("d M Y", strtotime($start_date)) ?> &mdash; <?= date("d M Y", strtotime($end_date)) ?></span>
        </div>

        <div class="bg-[#0e131d]/90 border border-stone-800/80 rounded-2xl p-6 shadow-xl print-card">
            <div class="flex items-center justify-between text-stone-400 mb-2">
                <span class="text-xs font-semibold uppercase tracking-wider print-text-dark">Total Volume Transaksi</span>
                <i data-lucide="receipt" class="w-5 h-5 text-blue-400"></i>
            </div>
            <p class="text-3xl font-extrabold font-mono text-white mt-2 print-text-dark"><?= number_format($summary['total_tx'] ?? 0) ?> Transaksi</p>
            <span class="text-[11px] text-stone-500 mt-1 block">Status: <?= htmlspecialchars(app_status_label('COMPLETED')) ?></span>
        </div>
    </div>

    <!-- Product sales breakdown table -->
    <div class="bg-[#0e131d]/90 border border-stone-800/80 rounded-2xl overflow-hidden shadow-xl print-card">
        <div class="p-4 border-b border-stone-800/80 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <i data-lucide="layers" class="w-4 h-4 text-amber-400"></i>
                <h3 class="text-xs font-bold text-white uppercase tracking-wider print-text-dark">Rincian Produk Terjual</h3>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-[#141b29] text-stone-400 uppercase tracking-wider text-[11px]">
                    <tr>
                        <th class="p-4">Nama Produk</th>
                        <th class="p-4 text-center">Kuantitas Terjual</th>
                        <th class="p-4 text-right">Akumulasi Subtotal</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-800/60">
                    <?php if ($items && $items->num_rows > 0): ?>
                        <?php while($i = $items->fetch_assoc()): ?>
                        <tr class="hover:bg-stone-800/30 transition-colors">
                            <td class="p-4 font-bold text-white print-text-dark"><?= htmlspecialchars($i['name']) ?></td>
                            <td class="p-4 text-center font-mono font-bold text-amber-400 print-text-dark"><?= $i['qty'] ?></td>
                            <td class="p-4 text-right font-mono font-bold text-stone-200 print-text-dark">Rp <?= number_format($i['subtotal'], 0, ',', '.') ?></td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="3" class="text-center py-10 text-stone-500">Tidak ada riwayat penjualan pada periode ini.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<div class="no-print">
    <?php require_once __DIR__ . '/../includes/theme_footer.php'; ?>
</div>
</body>
</html>