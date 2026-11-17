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
if (!isset($_SESSION["role_id"])) {
    $r = $conn->query("SELECT role_id FROM users WHERE id=" . (int)$_SESSION["user_id"])->fetch_assoc();
    if ($r) $_SESSION["role_id"] = $r["role_id"];
}
$conn->query("UPDATE users SET last_active=NOW() WHERE id=".(int)$_SESSION["user_id"]);
require_once __DIR__ . '/../includes/notification_center.php';
$notification_unread = notification_center_unread_count($conn, (int)$_SESSION['user_id']);

$total_sales = $conn->query("SELECT SUM(total_amount) as s FROM orders WHERE status = 'COMPLETED'")->fetch_assoc()['s'] ?? 0;
$total_orders = $conn->query("SELECT COUNT(id) as c FROM orders WHERE status = 'COMPLETED'")->fetch_assoc()['c'] ?? 0;
$total_products = $conn->query("SELECT COUNT(id) as c FROM products")->fetch_assoc()['c'] ?? 0;
$low_stock = $conn->query("SELECT COUNT(id) as c FROM stock WHERE current_stock <= minimum_stock")->fetch_assoc()['c'] ?? 0;
$today_sales = $conn->query("SELECT SUM(total_amount) as s FROM orders WHERE status='COMPLETED' AND DATE(created_at)=CURDATE()")->fetch_assoc()['s'] ?? 0;
$today_orders = $conn->query("SELECT COUNT(id) as c FROM orders WHERE status='COMPLETED' AND DATE(created_at)=CURDATE()")->fetch_assoc()['c'] ?? 0;

// Top products
$top_products = $conn->query(
    "SELECT p.name, SUM(oi.quantity) as sold
     FROM order_items oi JOIN products p ON oi.product_id = p.id
     JOIN orders o ON oi.order_id = o.id WHERE o.status = 'COMPLETED'
     GROUP BY p.id ORDER BY sold DESC LIMIT 5"
);

// Low stock items
$low_items = $conn->query(
    "SELECT p.name, s.current_stock, s.minimum_stock, s.unit
     FROM stock s JOIN products p ON s.product_id = p.id
     WHERE s.current_stock <= s.minimum_stock ORDER BY s.current_stock ASC LIMIT 5"
);

// Recent orders
$recent = $conn->query(
    "SELECT o.id, u.name as customer, o.total_amount, o.status, o.created_at
     FROM orders o JOIN users u ON o.user_id = u.id ORDER BY o.created_at DESC LIMIT 10"
);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Owner Executive Terminal — Kopi Mahasiswa</title>
    <?php require_once __DIR__ . '/../includes/theme_head.php'; ?>
</head>
<body class="bg-[#090c12] text-stone-200 min-h-screen flex flex-col antialiased selection:bg-amber-500/20 selection:text-amber-300">

<!-- Executive Navbar -->
<nav class="sticky top-0 z-40 bg-[#0e131d]/95 backdrop-blur-xl border-b border-stone-800/80">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center h-16">
            <div class="flex items-center gap-3">
                <a href="index.php" class="flex items-center gap-2.5">
                    <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-amber-500 to-amber-700 flex items-center justify-center text-stone-950 shadow-md shadow-amber-950/40">
                        <i data-lucide="coffee" class="w-5 h-5"></i>
                    </div>
                    <div>
                        <span class="font-extrabold text-base sm:text-lg tracking-tight text-white">Kopi Mahasiswa</span>
                        <span class="ml-2 text-[10px] tracking-widest uppercase px-2 py-0.5 rounded-full bg-purple-500/10 text-purple-400 border border-purple-500/30 font-bold">Owner Executive</span>
                    </div>
                </a>
            </div>

            <div class="flex items-center space-x-2 text-xs font-medium">
                <a href="index.php" class="px-3.5 py-2 rounded-lg bg-amber-500/10 text-amber-400 border border-amber-500/30 font-semibold flex items-center gap-1.5">
                    <i data-lucide="line-chart" class="w-3.5 h-3.5"></i> Ringkasan Bisnis
                </a>
                <a href="laporan.php" class="px-3.5 py-2 rounded-lg text-stone-300 hover:text-white bg-[#141b29] border border-stone-800 transition-all flex items-center gap-1.5">
                    <i data-lucide="file-text" class="w-3.5 h-3.5 text-stone-400"></i> Laporan Keuangan
                </a>
                <a href="notifications.php" class="relative px-2.5 py-2 rounded-lg text-stone-300 hover:text-white flex items-center gap-1" aria-label="Notifikasi<?= $notification_unread ? ' belum dibaca: ' . $notification_unread : '' ?>"><i data-lucide="bell" class="w-4 h-4"></i><span class="hidden sm:inline">Notifikasi</span><?php if ($notification_unread > 0): ?><span class="rounded-full bg-rose-500 px-1.5 py-0.5 text-[10px] font-bold text-white"><?= $notification_unread ?></span><?php endif; ?></a>

                <?php require_once __DIR__ . '/../includes/theme_toggle.php'; ?>

                <div class="hidden sm:flex items-center gap-2 pl-3 border-l border-stone-800 text-xs">
                    <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                    <span class="text-stone-400"><strong class="text-white"><?= htmlspecialchars($_SESSION["name"] ?? 'Owner') ?></strong></span>
                </div>

                <a href="../logout.php" class="bg-rose-500/10 border border-rose-500/30 hover:bg-rose-500/20 text-rose-400 px-3 py-1.5 rounded-lg text-xs font-semibold transition-all flex items-center gap-1 ml-2">
                    <i data-lucide="log-out" class="w-3.5 h-3.5"></i> Keluar
                </a>
            </div>
        </div>
    </div>
</nav>

<main class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8 w-full flex-grow">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between mb-8 gap-4">
        <div>
            <h1 class="text-2xl font-extrabold text-white tracking-tight">Executive Business Intelligence</h1>
            <p class="text-xs text-stone-400 mt-1">Dashboard performa finansial, omzet penjualan, dan analitik operasional.</p>
        </div>
        <a href="laporan.php" class="bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-400 hover:to-amber-500 text-stone-950 font-bold px-5 py-2.5 rounded-xl text-xs shadow-md shadow-amber-950/40 transition-all flex items-center gap-1.5 self-start sm:self-auto">
            <i data-lucide="printer" class="w-4 h-4"></i> Cetak Laporan Finansial
        </a>
    </div>

    <!-- Executive Stats Cards (Bybit trading style) -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 mb-8">
        <div class="bg-[#0e131d]/90 border border-stone-800/80 rounded-2xl p-6 hover:border-emerald-500/40 transition-all">
            <div class="flex items-center justify-between text-stone-400 mb-3">
                <span class="text-xs font-semibold uppercase tracking-wider">Total Omzet Penjualan</span>
                <div class="w-8 h-8 rounded-lg bg-emerald-500/10 text-emerald-400 flex items-center justify-center">
                    <i data-lucide="trending-up" class="w-4 h-4"></i>
                </div>
            </div>
            <div class="text-2xl lg:text-3xl font-extrabold font-mono text-emerald-400">Rp <?= number_format($total_sales, 0, ',', '.') ?></div>
            <div class="text-[11px] text-stone-500 mt-1">Akumulasi transaksi berhasil</div>
        </div>

        <div class="bg-[#0e131d]/90 border border-stone-800/80 rounded-2xl p-6 hover:border-blue-500/40 transition-all">
            <div class="flex items-center justify-between text-stone-400 mb-3">
                <span class="text-xs font-semibold uppercase tracking-wider">Transaksi Selesai</span>
                <div class="w-8 h-8 rounded-lg bg-blue-500/10 text-blue-400 flex items-center justify-center">
                    <i data-lucide="check-check" class="w-4 h-4"></i>
                </div>
            </div>
            <div class="text-2xl lg:text-3xl font-extrabold font-mono text-white"><?= number_format($total_orders) ?></div>
            <div class="text-[11px] text-stone-500 mt-1">Pesanan berstatus <?= htmlspecialchars(app_status_label('COMPLETED')) ?></div>
        </div>

        <div class="bg-[#0e131d]/90 border border-stone-800/80 rounded-2xl p-6 hover:border-amber-500/40 transition-all">
            <div class="flex items-center justify-between text-stone-400 mb-3">
                <span class="text-xs font-semibold uppercase tracking-wider">Penjualan Hari Ini</span>
                <div class="w-8 h-8 rounded-lg bg-amber-500/10 text-amber-400 flex items-center justify-center">
                    <i data-lucide="calendar" class="w-4 h-4"></i>
                </div>
            </div>
            <div class="text-2xl lg:text-3xl font-extrabold font-mono text-amber-400">Rp <?= number_format($today_sales, 0, ',', '.') ?></div>
            <div class="text-[11px] text-stone-500 mt-1"><?= $today_orders ?> transaksi di hari ini</div>
        </div>

        <div class="bg-[#0e131d]/90 border <?= $low_stock > 0 ? 'border-rose-500/40 bg-rose-950/10' : 'border-stone-800/80' ?> rounded-2xl p-6 hover:border-rose-500/50 transition-all">
            <div class="flex items-center justify-between text-stone-400 mb-3">
                <span class="text-xs font-semibold uppercase tracking-wider">Peringatan Stok</span>
                <div class="w-8 h-8 rounded-lg <?= $low_stock > 0 ? 'bg-rose-500/10 text-rose-400' : 'bg-emerald-500/10 text-emerald-400' ?> flex items-center justify-center">
                    <i data-lucide="alert-triangle" class="w-4 h-4"></i>
                </div>
            </div>
            <div class="text-2xl lg:text-3xl font-extrabold font-mono <?= $low_stock > 0 ? 'text-rose-400' : 'text-emerald-400' ?>"><?= $low_stock ?> Item</div>
            <div class="text-[11px] text-stone-500 mt-1"><?= $low_stock > 0 ? 'Perlu tindakan restock segera' : 'Semua persediaan normal' ?></div>
        </div>
    </div>

    <!-- AI Business Insight Layer (Executive Trading Intelligence) -->
    <div class="bg-[#0e131d]/90 border border-stone-800/80 rounded-2xl p-6 mb-8 shadow-xl relative overflow-hidden">
        <div class="flex items-start gap-4">
            <div class="w-10 h-10 rounded-xl bg-purple-500/10 border border-purple-500/20 text-purple-400 flex items-center justify-center flex-shrink-0">
                <i data-lucide="sparkles" class="w-5 h-5"></i>
            </div>
            <div class="flex-grow">
                <div class="flex items-center justify-between gap-2 mb-2">
                    <h3 class="font-bold text-white text-sm">AI Executive Business Advisor</h3>
                    <span class="text-[10px] uppercase font-mono px-2 py-0.5 rounded-full bg-purple-500/10 text-purple-300 border border-purple-500/20">Automated Insight</span>
                </div>
                <?php
                if (file_exists("../includes/ai_service.php")) {
                    require_once "../includes/ai_service.php";
                    $prompt = "Tolong berikan ringkasan 2 paragraf singkat dalam bahasa Indonesia sebagai Konsultan Bisnis F&B. Data saat ini: Total Transaksi Selesai: $total_orders. Penjualan Hari Ini: Rp " . number_format($today_sales,0,',','.') . ". Jumlah item stok menipis: $low_stock. Berikan rekomendasi tindakan.";
                    echo "<p class='text-xs text-stone-300 leading-relaxed font-sans'>" . nl2br(htmlspecialchars(get_ai_response($prompt))) . "</p>";
                } else {
                    echo "<p class='text-xs text-rose-400 mt-1'>Modul AI belum terinstal.</p>";
                }
                ?>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <!-- Top Products -->
        <div class="bg-[#0e131d]/90 border border-stone-800/80 rounded-2xl overflow-hidden shadow-xl">
            <div class="p-4 border-b border-stone-800/80 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <i data-lucide="award" class="w-4 h-4 text-amber-400"></i>
                    <h2 class="text-xs font-bold text-white uppercase tracking-wider">Produk Terlaris (Top Performers)</h2>
                </div>
            </div>
            <div class="p-5">
                <?php if ($top_products && $top_products->num_rows > 0): $rank = 0; ?>
                    <div class="space-y-3">
                    <?php while ($tp = $top_products->fetch_assoc()): $rank++; ?>
                    <div class="flex items-center justify-between p-3 rounded-xl bg-[#131926]/60 border border-stone-800/60 hover:border-amber-500/30 transition-all">
                        <div class="flex items-center gap-3">
                            <span class="w-7 h-7 rounded-lg <?= $rank == 1 ? 'bg-amber-500 text-stone-950' : 'bg-stone-800 text-stone-300' ?> flex items-center justify-center text-xs font-bold font-mono">
                                #<?= $rank ?>
                            </span>
                            <span class="text-xs font-bold text-white"><?= htmlspecialchars($tp['name']) ?></span>
                        </div>
                        <span class="text-xs font-mono font-bold text-amber-400 bg-amber-500/10 border border-amber-500/20 px-2.5 py-1 rounded-md">
                            <?= $tp['sold'] ?> Terjual
                        </span>
                    </div>
                    <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <p class="text-stone-500 text-xs text-center py-8">Belum ada riwayat penjualan produk.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Low Stock Warning -->
        <div class="bg-[#0e131d]/90 border border-stone-800/80 rounded-2xl overflow-hidden shadow-xl">
            <div class="p-4 border-b border-stone-800/80 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <i data-lucide="alert-triangle" class="w-4 h-4 text-rose-400"></i>
                    <h2 class="text-xs font-bold text-white uppercase tracking-wider">Perhatian Inventori &amp; Stok Menipis</h2>
                </div>
            </div>
            <div class="p-5">
                <?php if ($low_items && $low_items->num_rows > 0): ?>
                    <div class="space-y-3">
                    <?php $i = 0; while ($li = $low_items->fetch_assoc()): $i++;
                        $is_zero = ($li['current_stock'] == 0);
                    ?>
                    <div class="flex items-center justify-between p-3 rounded-xl bg-[#131926]/60 border border-stone-800/60">
                        <span class="text-xs font-semibold text-white"><?= htmlspecialchars($li['name']) ?></span>
                        <div class="text-right">
                            <span class="text-xs font-bold font-mono <?= $is_zero ? 'text-rose-400' : 'text-amber-400' ?>">
                                Sisa: <?= $li['current_stock'] ?>
                            </span>
                            <span class="text-[11px] text-stone-500 font-mono"> / Min: <?= $li['minimum_stock'] ?> <?= $li['unit'] ?? '' ?></span>
                        </div>
                    </div>
                    <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="py-8 text-center text-emerald-400 text-xs flex flex-col items-center justify-center gap-2">
                        <i data-lucide="shield-check" class="w-8 h-8"></i>
                        <span>Semua posisi stok aman terkendali.</span>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Recent Orders Table -->
    <div class="bg-[#0e131d]/90 border border-stone-800/80 rounded-2xl overflow-hidden shadow-xl">
        <div class="p-4 border-b border-stone-800/80 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <i data-lucide="receipt" class="w-4 h-4 text-amber-400"></i>
                <h2 class="text-xs font-bold text-white uppercase tracking-wider">Audit Transaksi Terkini</h2>
            </div>
            <span class="text-xs text-stone-500 font-mono">10 Pesanan Terakhir</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-[#141b29] text-stone-400 uppercase tracking-wider text-[11px]">
                    <tr>
                        <th class="p-4">ID Transaksi</th>
                        <th class="p-4">Customer</th>
                        <th class="p-4">Nominal Total</th>
                        <th class="p-4">Status Pesanan</th>
                        <th class="p-4">Waktu Transaksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-800/60">
                    <?php if ($recent && $recent->num_rows > 0): ?>
                        <?php while ($r = $recent->fetch_assoc()):
                            $sc = match($r['status']) {
                                'PENDING' => 'bg-amber-500/10 text-amber-400 border border-amber-500/30',
                                'COMPLETED' => 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/30',
                                'CANCELLED' => 'bg-rose-500/10 text-rose-400 border border-rose-500/30',
                                default => 'bg-stone-800 text-stone-400'
                            };
                        ?>
                        <tr class="hover:bg-stone-800/30 transition-colors">
                            <td class="p-4 font-mono font-bold text-amber-400">#<?= str_pad($r['id'], 5, '0', STR_PAD_LEFT) ?></td>
                            <td class="p-4 font-semibold text-white"><?= htmlspecialchars($r['customer']) ?></td>
                            <td class="p-4 font-mono font-bold text-stone-200">Rp <?= number_format($r['total_amount'], 0, ',', '.') ?></td>
                            <td class="p-4"><span class="px-2.5 py-1 rounded-full text-[10px] font-bold tracking-wide <?= $sc ?>"><?= htmlspecialchars(app_status_label($r['status'])) ?></span></td>
                            <td class="p-4 text-stone-500 font-mono text-[11px]"><?= date('d M Y H:i', strtotime($r['created_at'])) ?></td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="5" class="text-center py-12 text-stone-500">Belum ada transaksi tercatat</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/../includes/theme_footer.php'; ?>
</body>
</html>
