<?php
require '../config.php';
include 'header.php';

$today_sales = $conn->query("SELECT SUM(total_amount) as s FROM orders WHERE status='COMPLETED' AND DATE(created_at)=CURDATE()")->fetch_assoc()['s'] ?? 0;
$today_orders = $conn->query("SELECT COUNT(id) as c FROM orders WHERE DATE(created_at)=CURDATE()")->fetch_assoc()['c'] ?? 0;
$pending = $conn->query("SELECT COUNT(id) as c FROM orders WHERE status='PENDING'")->fetch_assoc()['c'] ?? 0;
?>

<div class="mb-8 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
    <div>
        <h1 class="text-2xl font-extrabold text-white tracking-tight">Kasir Terminal Dashboard</h1>
        <p class="text-xs text-stone-400 mt-1">Ringkasan transaksi kasir, pendapatan shift hari ini, dan order pending.</p>
    </div>
    <a href="pos.php" class="bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-400 hover:to-amber-500 text-stone-950 font-bold px-5 py-2.5 rounded-xl text-xs shadow-lg shadow-amber-950/40 transition-all flex items-center gap-2 self-start sm:self-auto">
        <i data-lucide="shopping-cart" class="w-4 h-4"></i> Buka Terminal POS
    </a>
</div>

<!-- Shift Metrics Cards (Bybit trading style) -->
<div class="grid grid-cols-1 sm:grid-cols-3 gap-5 mb-8">
    <div class="bg-[#0e131d]/90 border border-stone-800/80 rounded-2xl p-6 hover:border-emerald-500/30 transition-all">
        <div class="flex items-center justify-between text-stone-400 mb-4">
            <span class="text-xs font-semibold uppercase tracking-wider">Penjualan Hari Ini</span>
            <div class="w-9 h-9 rounded-xl bg-emerald-500/10 text-emerald-400 flex items-center justify-center">
                <i data-lucide="wallet" class="w-5 h-5"></i>
            </div>
        </div>
        <div class="text-3xl font-extrabold font-mono text-emerald-400">Rp <?= number_format($today_sales, 0, ',', '.') ?></div>
        <div class="text-[11px] text-stone-500 mt-1.5 flex items-center gap-1.5">
            <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span> Transaksi selesai (COMPLETED)
        </div>
    </div>

    <div class="bg-[#0e131d]/90 border border-stone-800/80 rounded-2xl p-6 hover:border-blue-500/30 transition-all">
        <div class="flex items-center justify-between text-stone-400 mb-4">
            <span class="text-xs font-semibold uppercase tracking-wider">Volume Transaksi</span>
            <div class="w-9 h-9 rounded-xl bg-blue-500/10 text-blue-400 flex items-center justify-center">
                <i data-lucide="receipt" class="w-5 h-5"></i>
            </div>
        </div>
        <div class="text-3xl font-extrabold font-mono text-white"><?= number_format($today_orders) ?></div>
        <div class="text-[11px] text-stone-500 mt-1.5">Total struk diterbitkan hari ini</div>
    </div>

    <div class="bg-[#0e131d]/90 border <?= $pending > 0 ? 'border-amber-500/40 bg-amber-950/10' : 'border-stone-800/80' ?> rounded-2xl p-6 hover:border-amber-500/50 transition-all">
        <div class="flex items-center justify-between text-stone-400 mb-4">
            <span class="text-xs font-semibold uppercase tracking-wider">Order Menunggu</span>
            <div class="w-9 h-9 rounded-xl <?= $pending > 0 ? 'bg-amber-500/20 text-amber-300' : 'bg-stone-800 text-stone-400' ?> flex items-center justify-center">
                <i data-lucide="clock" class="w-5 h-5"></i>
            </div>
        </div>
        <div class="text-3xl font-extrabold font-mono <?= $pending > 0 ? 'text-amber-400' : 'text-stone-300' ?>"><?= number_format($pending) ?></div>
        <div class="text-[11px] text-stone-500 mt-1.5">Pesanan pending perlu dikonfirmasi</div>
    </div>
</div>

<!-- Action Cards -->
<div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
    <a href="pos.php" class="bg-[#0e131d]/90 hover:bg-[#141b29] border border-stone-800/80 hover:border-amber-500/40 p-6 rounded-2xl transition-all shadow-xl flex items-center gap-5 group">
        <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-amber-500 to-amber-700 text-stone-950 flex items-center justify-center group-hover:scale-105 transition-transform shadow-lg shadow-amber-950/40 flex-shrink-0">
            <i data-lucide="shopping-bag" class="w-7 h-7"></i>
        </div>
        <div>
            <h3 class="text-lg font-bold text-white group-hover:text-amber-400 transition-colors">Buka POS Kasir</h3>
            <p class="text-xs text-stone-400 mt-1">Terminal kasir langsung, input pesanan cepat, dan cetak struk pembayaran.</p>
        </div>
    </a>

    <a href="transactions.php" class="bg-[#0e131d]/90 hover:bg-[#141b29] border border-stone-800/80 hover:border-amber-500/40 p-6 rounded-2xl transition-all shadow-xl flex items-center gap-5 group">
        <div class="w-14 h-14 rounded-2xl bg-[#141b29] border border-stone-700 text-amber-400 flex items-center justify-center group-hover:scale-105 transition-transform flex-shrink-0">
            <i data-lucide="history" class="w-7 h-7"></i>
        </div>
        <div>
            <h3 class="text-lg font-bold text-white group-hover:text-amber-400 transition-colors">Riwayat Transaksi</h3>
            <p class="text-xs text-stone-400 mt-1">Cari dan audit detail transaksi, status order, serta cetak ulang struk.</p>
        </div>
    </a>
</div>

<?php include 'footer.php'; ?>
