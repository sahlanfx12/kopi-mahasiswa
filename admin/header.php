<?php
if (session_status() == PHP_SESSION_NONE) session_start();
if (!isset($_SESSION["user_id"]) || strtolower($_SESSION["role"]) !== "admin") { header("Location: ../login.php"); exit; }
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
// Sync role_id jika belum ada di session (backward compat)
if (!isset($_SESSION["role_id"]) && isset($conn)) {
    $r = $conn->query("SELECT role_id FROM users WHERE id=" . (int)$_SESSION["user_id"])->fetch_assoc();
    if ($r) $_SESSION["role_id"] = $r["role_id"];
}
// Update presence
if (isset($conn)) $conn->query("UPDATE users SET last_active=NOW() WHERE id=".(int)$_SESSION["user_id"]);
$current_page = basename($_SERVER['PHP_SELF']);
require_once __DIR__ . '/../includes/notification_center.php';
$notification_unread = isset($conn) ? notification_center_unread_count($conn, (int)$_SESSION['user_id']) : 0;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Terminal — Kopi Mahasiswa</title>
    <?php require_once __DIR__ . '/../includes/theme_head.php'; ?>
</head>
<body class="bg-[#090c12] text-stone-200 min-h-screen flex flex-col antialiased selection:bg-amber-500/20 selection:text-amber-300">
    <!-- Sleek Top Navigation Bar -->
    <nav class="sticky top-0 z-40 bg-[#0e131d]/95 backdrop-blur-xl border-b border-stone-800/80">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <!-- Brand & Role Badge -->
                <div class="flex items-center gap-3">
                    <a href="index.php" class="flex items-center gap-2.5">
                        <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-amber-500 to-amber-700 flex items-center justify-center text-stone-950 shadow-md shadow-amber-950/40">
                            <i data-lucide="coffee" class="w-5 h-5"></i>
                        </div>
                        <div>
                            <span class="font-extrabold text-base sm:text-lg tracking-tight text-white">Kopi Mahasiswa</span>
                            <span class="ml-2 text-[10px] tracking-widest uppercase px-2 py-0.5 rounded-full bg-amber-500/10 text-amber-400 border border-amber-500/30 font-bold">Admin</span>
                        </div>
                    </a>
                </div>

                <!-- Navigation Links (Desktop) -->
                <div class="hidden lg:flex items-center space-x-1 text-xs font-medium">
                    <a href="index.php" class="px-3 py-2 rounded-lg transition-all flex items-center gap-1.5 <?= $current_page == 'index.php' ? 'bg-amber-500/10 text-amber-400 border border-amber-500/30 font-semibold' : 'text-stone-400 hover:text-stone-200 hover:bg-stone-800/50' ?>">
                        <i data-lucide="layout-dashboard" class="w-3.5 h-3.5"></i> Dashboard
                    </a>
                    <a href="orders.php" class="px-3 py-2 rounded-lg transition-all flex items-center gap-1.5 <?= $current_page == 'orders.php' ? 'bg-amber-500/10 text-amber-400 border border-amber-500/30 font-semibold' : 'text-stone-400 hover:text-stone-200 hover:bg-stone-800/50' ?>">
                        <i data-lucide="shopping-bag" class="w-3.5 h-3.5"></i> Pesanan
                    </a>
                    <a href="products.php" class="px-3 py-2 rounded-lg transition-all flex items-center gap-1.5 <?= $current_page == 'products.php' ? 'bg-amber-500/10 text-amber-400 border border-amber-500/30 font-semibold' : 'text-stone-400 hover:text-stone-200 hover:bg-stone-800/50' ?>">
                        <i data-lucide="package" class="w-3.5 h-3.5"></i> Produk
                    </a>
                    <a href="categories.php" class="px-3 py-2 rounded-lg transition-all flex items-center gap-1.5 <?= $current_page == 'categories.php' ? 'bg-amber-500/10 text-amber-400 border border-amber-500/30 font-semibold' : 'text-stone-400 hover:text-stone-200 hover:bg-stone-800/50' ?>">
                        <i data-lucide="tags" class="w-3.5 h-3.5"></i> Kategori
                    </a>
                    <a href="inventory.php" class="px-3 py-2 rounded-lg transition-all flex items-center gap-1.5 <?= $current_page == 'inventory.php' ? 'bg-amber-500/10 text-amber-400 border border-amber-500/30 font-semibold' : 'text-stone-400 hover:text-stone-200 hover:bg-stone-800/50' ?>">
                        <i data-lucide="boxes" class="w-3.5 h-3.5"></i> Inventory
                    </a>
                    <a href="mutasi.php" class="px-3 py-2 rounded-lg transition-all flex items-center gap-1.5 <?= $current_page == 'mutasi.php' ? 'bg-amber-500/10 text-amber-400 border border-amber-500/30 font-semibold' : 'text-stone-400 hover:text-stone-200 hover:bg-stone-800/50' ?>">
                        <i data-lucide="arrow-left-right" class="w-3.5 h-3.5"></i> Mutasi Stok
                    </a>
                    <a href="purchase_orders.php" class="px-3 py-2 rounded-lg transition-all flex items-center gap-1.5 <?= $current_page == 'purchase_orders.php' ? 'bg-amber-500/10 text-amber-400 border border-amber-500/30 font-semibold' : 'text-stone-400 hover:text-stone-200 hover:bg-stone-800/50' ?>">
                        <i data-lucide="clipboard-list" class="w-3.5 h-3.5"></i> PO
                    </a>
                    <a href="suppliers.php" class="px-3 py-2 rounded-lg transition-all flex items-center gap-1.5 <?= $current_page == 'suppliers.php' ? 'bg-amber-500/10 text-amber-400 border border-amber-500/30 font-semibold' : 'text-stone-400 hover:text-stone-200 hover:bg-stone-800/50' ?>">
                        <i data-lucide="truck" class="w-3.5 h-3.5"></i> Supplier
                    </a>
                    <a href="users.php" class="px-3 py-2 rounded-lg transition-all flex items-center gap-1.5 <?= $current_page == 'users.php' ? 'bg-amber-500/10 text-amber-400 border border-amber-500/30 font-semibold' : 'text-stone-400 hover:text-stone-200 hover:bg-stone-800/50' ?>">
                        <i data-lucide="users" class="w-3.5 h-3.5"></i> User
                    </a>
                    <a href="settings_integrasi.php" class="px-3 py-2 rounded-lg transition-all flex items-center gap-1.5 <?= $current_page == 'settings_integrasi.php' ? 'bg-amber-500/10 text-amber-400 border border-amber-500/30 font-semibold' : 'text-stone-400 hover:text-stone-200 hover:bg-stone-800/50' ?>">
                        <i data-lucide="plug" class="w-3.5 h-3.5"></i> Konfigurasi Integrasi
                    </a>
                    <a href="notifications.php" class="relative px-3 py-2 rounded-lg text-stone-400 hover:text-stone-200 hover:bg-stone-800/50 flex items-center gap-1.5" aria-label="Notifikasi<?= $notification_unread ? ' belum dibaca: ' . $notification_unread : '' ?>"><i data-lucide="bell" class="w-3.5 h-3.5"></i> Notifikasi<?php if ($notification_unread > 0): ?><span class="rounded-full bg-rose-500 px-1.5 py-0.5 text-[10px] font-bold text-white"><?= $notification_unread ?></span><?php endif; ?></a>
                </div>

                <!-- User info & Logout -->
                <div class="flex items-center gap-2.5">
                    <?php require_once __DIR__ . '/../includes/theme_toggle.php'; ?>
                    <div class="hidden sm:flex items-center gap-2 pl-3 border-l border-stone-800 text-xs">
                        <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                        <span class="text-stone-400">Halo, <strong class="text-white"><?= htmlspecialchars($_SESSION['name'] ?? 'Admin') ?></strong></span>
                    </div>
                    <a href="../logout.php" class="bg-rose-500/10 border border-rose-500/30 hover:bg-rose-500/20 text-rose-400 hover:text-rose-300 px-3 py-1.5 rounded-lg text-xs font-semibold transition-all flex items-center gap-1.5">
                        <i data-lucide="log-out" class="w-3.5 h-3.5"></i> Keluar
                    </a>
                </div>
            </div>

            <!-- Mobile Horizontal Nav Scrollbar -->
            <div class="lg:hidden flex items-center gap-1 overflow-x-auto py-2 border-t border-stone-800/60 no-scrollbar text-xs">
                <a href="index.php" class="px-2.5 py-1 rounded whitespace-nowrap <?= $current_page == 'index.php' ? 'text-amber-400 font-bold bg-amber-500/10' : 'text-stone-400' ?>">Dashboard</a>
                <a href="orders.php" class="px-2.5 py-1 rounded whitespace-nowrap <?= $current_page == 'orders.php' ? 'text-amber-400 font-bold bg-amber-500/10' : 'text-stone-400' ?>">Pesanan</a>
                <a href="products.php" class="px-2.5 py-1 rounded whitespace-nowrap <?= $current_page == 'products.php' ? 'text-amber-400 font-bold bg-amber-500/10' : 'text-stone-400' ?>">Produk</a>
                <a href="categories.php" class="px-2.5 py-1 rounded whitespace-nowrap <?= $current_page == 'categories.php' ? 'text-amber-400 font-bold bg-amber-500/10' : 'text-stone-400' ?>">Kategori</a>
                <a href="inventory.php" class="px-2.5 py-1 rounded whitespace-nowrap <?= $current_page == 'inventory.php' ? 'text-amber-400 font-bold bg-amber-500/10' : 'text-stone-400' ?>">Inventory</a>
                <a href="mutasi.php" class="px-2.5 py-1 rounded whitespace-nowrap <?= $current_page == 'mutasi.php' ? 'text-amber-400 font-bold bg-amber-500/10' : 'text-stone-400' ?>">Mutasi</a>
                <a href="purchase_orders.php" class="px-2.5 py-1 rounded whitespace-nowrap <?= $current_page == 'purchase_orders.php' ? 'text-amber-400 font-bold bg-amber-500/10' : 'text-stone-400' ?>">PO</a>
                <a href="suppliers.php" class="px-2.5 py-1 rounded whitespace-nowrap <?= $current_page == 'suppliers.php' ? 'text-amber-400 font-bold bg-amber-500/10' : 'text-stone-400' ?>">Supplier</a>
                <a href="users.php" class="px-2.5 py-1 rounded whitespace-nowrap <?= $current_page == 'users.php' ? 'text-amber-400 font-bold bg-amber-500/10' : 'text-stone-400' ?>">User</a>
                <a href="settings_integrasi.php" class="px-2.5 py-1 rounded whitespace-nowrap <?= $current_page == 'settings_integrasi.php' ? 'text-amber-400 font-bold bg-amber-500/10' : 'text-stone-400' ?>">Integrasi</a>
                <a href="notifications.php" class="px-2.5 py-1 rounded whitespace-nowrap <?= $current_page == 'notifications.php' ? 'text-amber-400 font-bold bg-amber-500/10' : 'text-stone-400' ?>">Notifikasi<?= $notification_unread > 0 ? ' (' . $notification_unread . ')' : '' ?></a>
            </div>
        </div>
    </nav>
    <main class="p-4 sm:p-6 lg:p-8 max-w-7xl mx-auto w-full flex-grow">
