<?php
require '../config.php';
include 'header.php';

// Stats
$total_products = $conn->query("SELECT COUNT(id) as c FROM products")->fetch_assoc()['c'] ?? 0;
$total_orders = $conn->query("SELECT COUNT(id) as c FROM orders")->fetch_assoc()['c'] ?? 0;
$total_users = $conn->query("SELECT COUNT(id) as c FROM users")->fetch_assoc()['c'] ?? 0;
$low_stock = $conn->query("SELECT COUNT(id) as c FROM stock WHERE current_stock <= minimum_stock")->fetch_assoc()['c'] ?? 0;
$pending_orders = $conn->query("SELECT COUNT(id) as c FROM orders WHERE status = 'PENDING'")->fetch_assoc()['c'] ?? 0;

// Recent orders
$recent_orders = $conn->query(
    "SELECT o.id, u.name as customer_name, o.total_amount, o.status, o.created_at, o.order_type
     FROM orders o JOIN users u ON o.user_id = u.id
     ORDER BY o.created_at DESC LIMIT 5"
);
?>

<div class="mb-8 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
    <div>
        <h1 class="text-2xl font-extrabold text-white tracking-tight">Admin Overview Terminal</h1>
        <p class="text-xs text-stone-400 mt-1">Status dan metriks ringkasan operasional sistem saat ini.</p>
    </div>
    <div class="flex items-center gap-2">
        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
            <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span> Sistem Normal
        </span>
    </div>
</div>

<!-- Stats Metric Cards (Trading Style Depth) -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 mb-8">
    <div class="bg-[#0e131d]/90 border border-stone-800/80 rounded-2xl p-5 hover:border-amber-500/30 transition-all">
        <div class="flex items-center justify-between text-stone-400 mb-3">
            <span class="text-xs font-semibold uppercase tracking-wider">Total Produk</span>
            <div class="w-8 h-8 rounded-lg bg-amber-500/10 text-amber-400 flex items-center justify-center">
                <i data-lucide="package" class="w-4 h-4"></i>
            </div>
        </div>
        <div class="text-2xl font-bold font-mono text-white"><?= number_format($total_products) ?></div>
        <div class="text-[11px] text-stone-500 mt-1">Item aktif di katalog</div>
    </div>

    <div class="bg-[#0e131d]/90 border border-stone-800/80 rounded-2xl p-5 hover:border-amber-500/30 transition-all">
        <div class="flex items-center justify-between text-stone-400 mb-3">
            <span class="text-xs font-semibold uppercase tracking-wider">Total Pesanan</span>
            <div class="w-8 h-8 rounded-lg bg-blue-500/10 text-blue-400 flex items-center justify-center">
                <i data-lucide="shopping-bag" class="w-4 h-4"></i>
            </div>
        </div>
        <div class="text-2xl font-bold font-mono text-white"><?= number_format($total_orders) ?></div>
        <div class="text-[11px] text-stone-500 mt-1">Seluruh riwayat order</div>
    </div>

    <div class="bg-[#0e131d]/90 border border-stone-800/80 rounded-2xl p-5 hover:border-amber-500/30 transition-all">
        <div class="flex items-center justify-between text-stone-400 mb-3">
            <span class="text-xs font-semibold uppercase tracking-wider">Total Pengguna</span>
            <div class="w-8 h-8 rounded-lg bg-purple-500/10 text-purple-400 flex items-center justify-center">
                <i data-lucide="users" class="w-4 h-4"></i>
            </div>
        </div>
        <div class="text-2xl font-bold font-mono text-white"><?= number_format($total_users) ?></div>
        <div class="text-[11px] text-stone-500 mt-1">Semua role terdaftar</div>
    </div>

    <div class="bg-[#0e131d]/90 border border-stone-800/80 rounded-2xl p-5 hover:border-amber-500/30 transition-all">
        <div class="flex items-center justify-between text-stone-400 mb-3">
            <span class="text-xs font-semibold uppercase tracking-wider">Pending Orders</span>
            <div class="w-8 h-8 rounded-lg bg-amber-500/10 text-amber-400 flex items-center justify-center">
                <i data-lucide="clock" class="w-4 h-4"></i>
            </div>
        </div>
        <div class="text-2xl font-bold font-mono text-amber-400"><?= number_format($pending_orders) ?></div>
        <div class="text-[11px] text-stone-500 mt-1">Perlu konfirmasi/proses</div>
    </div>

    <div class="bg-[#0e131d]/90 border <?= $low_stock > 0 ? 'border-rose-500/40 bg-rose-950/10' : 'border-stone-800/80' ?> rounded-2xl p-5 hover:border-rose-500/50 transition-all">
        <div class="flex items-center justify-between text-stone-400 mb-3">
            <span class="text-xs font-semibold uppercase tracking-wider">Stok Menipis</span>
            <div class="w-8 h-8 rounded-lg <?= $low_stock > 0 ? 'bg-rose-500/10 text-rose-400' : 'bg-emerald-500/10 text-emerald-400' ?> flex items-center justify-center">
                <i data-lucide="alert-triangle" class="w-4 h-4"></i>
            </div>
        </div>
        <div class="text-2xl font-bold font-mono <?= $low_stock > 0 ? 'text-rose-400' : 'text-emerald-400' ?>"><?= number_format($low_stock) ?></div>
        <div class="text-[11px] text-stone-500 mt-1">Item di bawah batas minimum</div>
    </div>
</div>

<!-- Quick Navigation Bar -->
<div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-5 gap-4 mb-8">
    <a href="orders.php" class="bg-[#0e131d]/70 hover:bg-[#141b29] border border-stone-800/80 hover:border-amber-500/40 p-4 rounded-xl transition-all flex items-center gap-3.5 group">
        <div class="w-10 h-10 rounded-xl bg-blue-500/10 text-blue-400 flex items-center justify-center group-hover:scale-105 transition-transform flex-shrink-0">
            <i data-lucide="shopping-bag" class="w-5 h-5"></i>
        </div>
        <div>
            <div class="text-sm font-bold text-white group-hover:text-amber-400 transition-colors">Kelola Pesanan</div>
            <div class="text-[11px] text-stone-500">Lihat daftar &amp; detail</div>
        </div>
    </a>

    <a href="products.php" class="bg-[#0e131d]/70 hover:bg-[#141b29] border border-stone-800/80 hover:border-amber-500/40 p-4 rounded-xl transition-all flex items-center gap-3.5 group">
        <div class="w-10 h-10 rounded-xl bg-amber-500/10 text-amber-400 flex items-center justify-center group-hover:scale-105 transition-transform flex-shrink-0">
            <i data-lucide="package-plus" class="w-5 h-5"></i>
        </div>
        <div>
            <div class="text-sm font-bold text-white group-hover:text-amber-400 transition-colors">Kelola Produk</div>
            <div class="text-[11px] text-stone-500">Edit &amp; tambah menu</div>
        </div>
    </a>

    <a href="inventory.php" class="bg-[#0e131d]/70 hover:bg-[#141b29] border border-stone-800/80 hover:border-amber-500/40 p-4 rounded-xl transition-all flex items-center gap-3.5 group">
        <div class="w-10 h-10 rounded-xl bg-cyan-500/10 text-cyan-400 flex items-center justify-center group-hover:scale-105 transition-transform flex-shrink-0">
            <i data-lucide="boxes" class="w-5 h-5"></i>
        </div>
        <div>
            <div class="text-sm font-bold text-white group-hover:text-cyan-400 transition-colors">Stok &amp; Gudang</div>
            <div class="text-[11px] text-stone-500">Monitor persediaan</div>
        </div>
    </a>

    <a href="purchase_orders.php" class="bg-[#0e131d]/70 hover:bg-[#141b29] border border-stone-800/80 hover:border-amber-500/40 p-4 rounded-xl transition-all flex items-center gap-3.5 group">
        <div class="w-10 h-10 rounded-xl bg-purple-500/10 text-purple-400 flex items-center justify-center group-hover:scale-105 transition-transform flex-shrink-0">
            <i data-lucide="clipboard-check" class="w-5 h-5"></i>
        </div>
        <div>
            <div class="text-sm font-bold text-white group-hover:text-purple-400 transition-colors">Purchase Order</div>
            <div class="text-[11px] text-stone-500">Restock ke supplier</div>
        </div>
    </a>

    <a href="users.php" class="bg-[#0e131d]/70 hover:bg-[#141b29] border border-stone-800/80 hover:border-amber-500/40 p-4 rounded-xl transition-all flex items-center gap-3.5 group">
        <div class="w-10 h-10 rounded-xl bg-emerald-500/10 text-emerald-400 flex items-center justify-center group-hover:scale-105 transition-transform flex-shrink-0">
            <i data-lucide="shield" class="w-5 h-5"></i>
        </div>
        <div>
            <div class="text-sm font-bold text-white group-hover:text-emerald-400 transition-colors">Manajemen User</div>
            <div class="text-[11px] text-stone-500">Akses &amp; peran akun</div>
        </div>
    </a>
</div>

<!-- Recent Orders Table (Trading Terminal Style) -->
<div class="bg-[#0e131d]/90 rounded-2xl border border-stone-800/80 overflow-hidden shadow-xl">
    <div class="p-5 border-b border-stone-800/80 flex items-center justify-between">
        <div class="flex items-center gap-2">
            <i data-lucide="history" class="w-4 h-4 text-amber-400"></i>
            <h2 class="text-sm font-bold text-white uppercase tracking-wider">Transaksi Terbaru</h2>
        </div>
        <span class="text-xs text-stone-500 font-mono">5 Transaksi Terakhir</span>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-left text-xs">
            <thead class="bg-[#141b29] text-stone-400 uppercase tracking-wider text-[11px]">
                <tr>
                    <th class="px-5 py-3">Order ID</th>
                    <th class="px-5 py-3">Pelanggan</th>
                    <th class="px-5 py-3">Tipe</th>
                    <th class="px-5 py-3">Total Nilai</th>
                    <th class="px-5 py-3">Status</th>
                    <th class="px-5 py-3">Waktu</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-800/60">
                <?php if ($recent_orders && $recent_orders->num_rows > 0): ?>
                    <?php while ($o = $recent_orders->fetch_assoc()):
                        $status_badge = match($o['status']) {
                            'PENDING' => 'bg-amber-500/10 text-amber-400 border border-amber-500/30',
                            'CONFIRMED' => 'bg-cyan-500/10 text-cyan-400 border border-cyan-500/30',
                            'PREPARING' => 'bg-purple-500/10 text-purple-400 border border-purple-500/30',
                            'READY' => 'bg-indigo-500/10 text-indigo-400 border border-indigo-500/30',
                            'COMPLETED' => 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/30',
                            'CANCELLED' => 'bg-rose-500/10 text-rose-400 border border-rose-500/30',
                            default => 'bg-stone-800 text-stone-400'
                        };
                    ?>
                    <tr class="hover:bg-stone-800/30 transition-colors">
                        <td class="px-5 py-3.5 font-mono font-bold"><a href="orders.php?id=<?= (int)$o['id'] ?>" class="text-amber-400 hover:text-amber-300">#<?= str_pad($o['id'], 5, '0', STR_PAD_LEFT) ?></a></td>
                        <td class="px-5 py-3.5 font-medium text-white"><?= htmlspecialchars($o['customer_name']) ?></td>
                        <td class="px-5 py-3.5 text-stone-400"><?= htmlspecialchars($o['order_type'] ?? '-') ?></td>
                        <td class="px-5 py-3.5 font-mono font-bold text-stone-200">Rp <?= number_format($o['total_amount'], 0, ',', '.') ?></td>
                        <td class="px-5 py-3.5">
                            <span class="px-2.5 py-1 rounded-full text-[10px] font-bold tracking-wide <?= $status_badge ?>">
                                <?= htmlspecialchars(app_status_label($o['status'])) ?>
                            </span>
                        </td>
                        <td class="px-5 py-3.5 text-stone-500 font-mono text-[11px]"><?= date('d M Y H:i', strtotime($o['created_at'])) ?></td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="text-center py-12 text-stone-500">
                            Belum ada pesanan tercatat di sistem.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include 'footer.php'; ?>
