<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_role(['Supplier']);
$userId = filter_var($_SESSION['user_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!$userId || !$conn || !is_account_active((int)$userId)) { header('Location: ../login.php'); exit; }
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/notification_center.php';
$notification_unread = notification_center_unread_count($conn, (int)$userId);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = filter_var($_POST['notification_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $ok = ($_POST['action'] ?? '') === 'mark_read' && $id && notification_center_mark_one_read($conn, (int)$id, (int)$userId);
    $_SESSION['notification_center_message'] = $ok ? 'Notifikasi diperbarui.' : 'Notifikasi belum dapat diperbarui.';
    header('Location: notifications.php'); exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/css/supplier-portal.css">
    <title>Notifikasi Supplier — Kopi Mahasiswa</title>
    <?php require_once __DIR__ . '/../includes/theme_head.php'; ?>
</head>
<body class="bg-[#090c12] text-stone-200 min-h-screen flex flex-col antialiased selection:bg-amber-500/20 selection:text-amber-300">
<?php $supplier_navigation_active = 'notifications'; require_once __DIR__ . '/../includes/supplier_navigation.php'; ?>

<main class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8 w-full flex-grow">
    <div class="bg-[#0e131d]/90 border border-stone-800/80 rounded-2xl p-6 shadow-xl mb-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-2 text-amber-400 mb-1">
                    <i data-lucide="bell" class="w-4 h-4"></i>
                    <span class="text-xs uppercase font-bold tracking-wider">Pusat Informasi Supplier</span>
                </div>
                <h1 class="text-2xl font-extrabold text-white tracking-tight">Notifikasi</h1>
            </div>
            <div class="flex items-center gap-2">
                <span class="rounded-full border border-stone-700 bg-stone-900 px-3 py-1 text-xs font-semibold text-stone-300">
                    <?= $notification_unread ?> belum dibaca
                </span>
            </div>
        </div>
    </div>

    <?php if (isset($_SESSION['notification_center_message'])): ?>
        <p class="max-w-4xl mx-auto mb-4 text-sm text-stone-300" role="status"><?= htmlspecialchars($_SESSION['notification_center_message'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
        <?php unset($_SESSION['notification_center_message']); ?>
    <?php endif; ?>

    <?php notification_center_render($conn, (int)$userId, true); ?>
</main>

<?php require_once __DIR__ . '/../includes/theme_footer.php'; ?>
</body>
</html>
