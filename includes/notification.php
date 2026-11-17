<?php
/**
 * KOPI MAHASISWA — Notification Helper
 */

function send_notification(int $user_id, string $title, string $body = '', string $type = 'info', ?string $link = null, ?int $created_by = null) {
    global $conn;
    if (!$conn) return false;
    $stmt = $conn->prepare(
        "INSERT INTO notifications (user_id, title, body, type, link, created_by) VALUES (?, ?, ?, ?, ?, ?)"
    );
    $stmt->bind_param("issssi", $user_id, $title, $body, $type, $link, $created_by);
    return $stmt->execute();
}

function get_unread_count(int $user_id): int {
    global $conn;
    $res = $conn->query("SELECT COUNT(*) c FROM notifications WHERE user_id = $user_id AND is_read = 0");
    return (int)($res->fetch_assoc()['c'] ?? 0);
}

function mark_notifications_read(int $user_id) {
    global $conn;
    $conn->query("UPDATE notifications SET is_read = 1 WHERE user_id = $user_id");
}
?>
