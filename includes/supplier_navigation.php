<?php
$supplierActive = (string)($supplier_navigation_active ?? 'dashboard');
$supplierUnread = max(0, (int)($notification_unread ?? 0));
$supplierName = (string)($_SESSION['name'] ?? 'Supplier');
$supplierLinks = [
    ['dashboard', 'Dashboard', 'index.php'],
    ['purchase-orders', 'Purchase Order', 'index.php#purchase-orders'],
    ['notifications', 'Notifikasi', 'notifications.php'],
];
?>
<nav aria-label="Navigasi Supplier" class="supplier-portal-navbar sticky top-0 z-40 border-b border-stone-800/80 bg-[#0e131d]/95 backdrop-blur-xl">
    <div class="mx-auto flex max-w-7xl flex-wrap items-center gap-x-4 gap-y-3 px-4 py-3 sm:px-6 lg:px-8 md:min-h-[72px] md:flex-nowrap md:py-0">
        <a href="index.php" class="flex min-w-0 flex-1 items-center gap-2.5 md:flex-none" aria-label="Kopi Mahasiswa, Dashboard Supplier">
            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-amber-500 to-amber-700 text-stone-950 shadow-md shadow-amber-950/40">
                <i data-lucide="coffee" class="h-5 w-5"></i>
            </span>
            <span class="min-w-0">
                <span class="block truncate text-base font-extrabold tracking-tight text-white sm:text-lg">Kopi Mahasiswa</span>
                <span class="hidden text-[10px] font-bold uppercase tracking-widest text-cyan-400 sm:inline">Supplier Portal</span>
            </span>
        </a>
        <div class="order-2 flex shrink-0 items-center gap-2 md:order-3 md:gap-3">
            <?php require __DIR__ . '/theme_toggle.php'; ?>
            <div class="hidden max-w-[150px] items-center gap-2 truncate text-xs text-stone-400 xl:flex">
                <span class="h-2 w-2 shrink-0 rounded-full bg-emerald-400"></span>
                <span class="truncate">Halo, <strong class="text-white"><?= htmlspecialchars($supplierName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></strong></span>
            </div>
            <a href="../logout.php" class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-lg border border-rose-500/30 bg-rose-500/10 px-2.5 text-xs font-semibold text-rose-400 transition-colors hover:bg-rose-500/20 sm:px-3" aria-label="Keluar">
                <i data-lucide="log-out" class="h-4 w-4"></i><span class="hidden sm:inline">Keluar</span>
            </a>
        </div>
        <div class="order-3 grid w-full grid-cols-3 gap-1 rounded-2xl border border-stone-800 bg-[#111827]/70 p-1 md:order-2 md:mx-auto md:w-auto md:rounded-full md:p-1.5">
            <?php foreach ($supplierLinks as [$key, $label, $href]): ?>
                <a href="<?= htmlspecialchars($href, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" data-supplier-nav="<?= htmlspecialchars($key, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="supplier-nav-link px-2 py-2 text-center"<?= $supplierActive === $key ? ' aria-current="page"' : '' ?><?= $key === 'notifications' ? ' aria-label="Notifikasi' . ($supplierUnread > 0 ? ', belum dibaca: ' . $supplierUnread : '') . '"' : '' ?>>
                    <span><?= htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
                    <?php if ($key === 'notifications' && $supplierUnread > 0): ?><span class="ml-1 inline-flex min-w-5 items-center justify-center rounded-full bg-rose-500 px-1.5 py-0.5 text-[10px] font-bold leading-none text-white"><?= $supplierUnread ?></span><?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</nav>
<?php if ($supplierActive !== 'notifications'): ?>
<script>
(() => {
    const links = document.querySelectorAll('[data-supplier-nav]');
    const syncActiveLink = () => {
        const active = window.location.hash === '#purchase-orders' ? 'purchase-orders' : 'dashboard';
        links.forEach((link) => {
            if (link.dataset.supplierNav === 'dashboard' || link.dataset.supplierNav === 'purchase-orders') {
                if (link.dataset.supplierNav === active) link.setAttribute('aria-current', 'page');
                else link.removeAttribute('aria-current');
            }
        });
    };
    window.addEventListener('hashchange', syncActiveLink);
    syncActiveLink();
})();
</script>
<?php endif; ?>
