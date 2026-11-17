<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_role(['Owner']);
$userId = filter_var($_SESSION['user_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!$userId || !$conn || !is_account_active((int)$userId)) { header('Location: ../login.php'); exit; }
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/notification_center.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = filter_var($_POST['notification_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $ok = ($_POST['action'] ?? '') === 'mark_read' && $id && notification_center_mark_one_read($conn, (int)$id, (int)$userId);
    $_SESSION['notification_center_message'] = $ok ? 'Notifikasi diperbarui.' : 'Notifikasi belum dapat diperbarui.';
    header('Location: notifications.php'); exit;
}
?>
<!DOCTYPE html>
<html lang="id"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Notifikasi Owner — Kopi Mahasiswa</title>
<?php require_once __DIR__ . '/../includes/theme_head.php'; ?>
</head>
<body class="bg-[#090c12] text-stone-200 min-h-screen flex flex-col antialiased">
<nav class="sticky top-0 z-40 bg-[#0e131d]/95 border-b border-stone-800/80"><div class="max-w-7xl mx-auto px-4 h-16 flex justify-between items-center">
<a href="index.php" class="font-extrabold text-white">Kopi Mahasiswa <span class="text-purple-400 text-xs">Owner</span></a>
<div class="flex items-center gap-3"><?php require_once __DIR__ . '/../includes/theme_toggle.php'; ?><span class="hidden sm:inline text-sm text-stone-400"><?= htmlspecialchars($_SESSION['name'] ?? 'Owner', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span><a href="../logout.php" class="text-rose-400 text-sm">Keluar</a></div>
</div></nav>
<main class="flex-grow">
<?php if (isset($_SESSION['notification_center_message'])): ?><p class="max-w-4xl mx-auto px-4 pt-5 text-sm text-stone-300" role="status"><?= htmlspecialchars($_SESSION['notification_center_message'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p><?php unset($_SESSION['notification_center_message']); endif; ?>
<?php notification_center_render($conn, (int)$userId); require_once __DIR__ . '/../includes/theme_footer.php'; ?>
