<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_role(['Kasir']);
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
require_once __DIR__ . '/header.php';
if (isset($_SESSION['notification_center_message'])) { echo '<p class="max-w-4xl mx-auto px-4 pt-5 text-sm text-stone-300" role="status">' . htmlspecialchars($_SESSION['notification_center_message'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>'; unset($_SESSION['notification_center_message']); }
notification_center_render($conn, (int)$userId);
require_once __DIR__ . '/footer.php';
