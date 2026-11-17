<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
// Proteksi Route (RBAC) Customer
if (!isset($_SESSION["user_id"]) || strtolower($_SESSION["role"]) !== "customer") {
    header("Location: ../login.php");
    exit;
}
// [SECURITY] Live suspended-account guard — deny access if account was suspended after login
if (!isset($conn)) { require_once __DIR__ . '/../config.php'; }
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

// Menghitung jumlah item di cart
$cart_count = 0;
if (isset($_SESSION["cart"])) {
    foreach ($_SESSION["cart"] as $qty) {
        $cart_count += $qty;
    }
}
$current_page = basename($_SERVER['PHP_SELF']);
require_once __DIR__ . '/../includes/notification_center.php';
$notification_unread = isset($conn) ? notification_center_unread_count($conn, (int)$_SESSION['user_id']) : 0;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Hub — Kopi Mahasiswa</title>
    <?php require_once __DIR__ . '/../includes/theme_head.php'; ?>
</head>
<body class="bg-[#090c12] text-stone-200 flex flex-col min-h-screen antialiased selection:bg-amber-500/20 selection:text-amber-300">
    <!-- Navbar Customer Hub -->
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
                            <span class="ml-2 text-[10px] tracking-widest uppercase px-2 py-0.5 rounded-full bg-amber-500/10 text-amber-400 border border-amber-500/30 font-bold">Pelanggan</span>
                        </div>
                    </a>
                </div>

                <!-- Nav Links Desktop -->
                <div class="hidden md:flex items-center space-x-1 sm:space-x-2 text-xs font-medium">
                    <a href="index.php" class="px-3.5 py-2 rounded-lg transition-all flex items-center gap-1.5 <?= $current_page == 'index.php' ? 'bg-amber-500/10 text-amber-400 border border-amber-500/30 font-semibold' : 'text-stone-400 hover:text-stone-200 hover:bg-stone-800/50' ?>">
                        <i data-lucide="layout-dashboard" class="w-3.5 h-3.5"></i> Dashboard
                    </a>
                    <a href="menu.php" class="px-3.5 py-2 rounded-lg transition-all flex items-center gap-1.5 <?= $current_page == 'menu.php' ? 'bg-amber-500/10 text-amber-400 border border-amber-500/30 font-semibold' : 'text-stone-400 hover:text-stone-200 hover:bg-stone-800/50' ?>">
                        <i data-lucide="utensils" class="w-3.5 h-3.5"></i> Katalog Menu
                    </a>
                    <a href="history.php" class="px-3.5 py-2 rounded-lg transition-all flex items-center gap-1.5 <?= $current_page == 'history.php' ? 'bg-amber-500/10 text-amber-400 border border-amber-500/30 font-semibold' : 'text-stone-400 hover:text-stone-200 hover:bg-stone-800/50' ?>">
                        <i data-lucide="receipt" class="w-3.5 h-3.5"></i> Riwayat Pesanan
                    </a>
                    <a href="cart.php" class="relative px-3.5 py-2 rounded-lg transition-all flex items-center gap-1.5 <?= $current_page == 'cart.php' ? 'bg-amber-500/10 text-amber-400 border border-amber-500/30 font-semibold' : 'text-stone-400 hover:text-stone-200 hover:bg-stone-800/50' ?>">
                        <i data-lucide="shopping-bag" class="w-3.5 h-3.5"></i> Keranjang
                        <?php if($cart_count > 0): ?>
                            <span class="inline-flex items-center justify-center px-1.5 py-0.5 text-[10px] font-bold leading-none text-stone-950 bg-amber-400 rounded-full font-mono"><?= $cart_count ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="notifications.php" class="px-3.5 py-2 rounded-lg text-stone-400 hover:text-stone-200 flex items-center gap-1.5" aria-label="Notifikasi<?= $notification_unread ? ' belum dibaca: ' . $notification_unread : '' ?>"><i data-lucide="bell" class="w-3.5 h-3.5"></i> Notifikasi<?php if ($notification_unread > 0): ?><span class="rounded-full bg-rose-500 px-1.5 py-0.5 text-[10px] font-bold text-white"><?= $notification_unread ?></span><?php endif; ?></a>

                    <?php require_once __DIR__ . '/../includes/theme_toggle.php'; ?>

                    <div class="hidden lg:flex items-center gap-2 pl-3 border-l border-stone-800 text-xs">
                        <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                        <span class="text-stone-400">Halo, <strong class="text-white"><?= htmlspecialchars($_SESSION["name"] ?? 'Pelanggan') ?></strong></span>
                    </div>

                    <a href="../logout.php" class="bg-rose-500/10 border border-rose-500/30 hover:bg-rose-500/20 text-rose-400 px-3 py-1.5 rounded-lg text-xs font-semibold transition-all flex items-center gap-1 ml-2">
                        <i data-lucide="log-out" class="w-3.5 h-3.5"></i> Keluar
                    </a>
                </div>

                <!-- Mobile Header Right -->
                <div class="md:hidden flex items-center gap-2">
                    <?php require_once __DIR__ . '/../includes/theme_toggle.php'; ?>
                    <a href="cart.php" class="relative p-2 text-stone-300">
                        <i data-lucide="shopping-bag" class="w-5 h-5"></i>
                        <?php if($cart_count > 0): ?>
                            <span class="absolute top-1 right-1 inline-flex items-center justify-center px-1.5 py-0.5 text-[10px] font-bold text-stone-950 bg-amber-400 rounded-full font-mono"><?= $cart_count ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="notifications.php" class="relative p-2 text-stone-300" aria-label="Notifikasi<?= $notification_unread ? ' belum dibaca: ' . $notification_unread : '' ?>"><i data-lucide="bell" class="w-5 h-5"></i><?php if ($notification_unread > 0): ?><span class="absolute top-0 right-0 rounded-full bg-rose-500 px-1 text-[9px] font-bold text-white"><?= $notification_unread ?></span><?php endif; ?></a>
                    <a href="../logout.php" class="text-xs bg-rose-500/10 border border-rose-500/30 text-rose-400 px-2.5 py-1 rounded-lg">Keluar</a>
                </div>
            </div>

            <!-- Mobile Navigation Bar -->
            <div class="md:hidden flex items-center justify-around py-2 border-t border-stone-800/60 text-xs">
                <a href="index.php" class="px-2.5 py-1 rounded <?= $current_page == 'index.php' ? 'text-amber-400 font-bold' : 'text-stone-400' ?>">Dashboard</a>
                <a href="menu.php" class="px-2.5 py-1 rounded <?= $current_page == 'menu.php' ? 'text-amber-400 font-bold' : 'text-stone-400' ?>">Menu</a>
                <a href="history.php" class="px-2.5 py-1 rounded <?= $current_page == 'history.php' ? 'text-amber-400 font-bold' : 'text-stone-400' ?>">Riwayat</a>
                <a href="cart.php" class="px-2.5 py-1 rounded <?= $current_page == 'cart.php' ? 'text-amber-400 font-bold' : 'text-stone-400' ?>">Cart (<?= $cart_count ?>)</a>
                <a href="notifications.php" class="px-2.5 py-1 rounded <?= $current_page == 'notifications.php' ? 'text-amber-400 font-bold' : 'text-stone-400' ?>">Notifikasi<?= $notification_unread > 0 ? ' (' . $notification_unread . ')' : '' ?></a>
            </div>
        </div>
    </nav>
    <main class="flex-grow">
