<?php
/**
 * KOPI MAHASISWA — Activity Log Helper
 * Catat setiap action penting ke tabel activity_logs.
 */

function log_activity(
    string $action,
    string $description = '',
    ?string $target_type = null,
    ?int $target_id = null,
    string $status = 'SUCCESS'
) {
    global $conn;
    if (!$conn) return;

    $user_id   = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
    $user_role = $_SESSION['role'] ?? 'unknown';
    $ip        = $_SERVER['REMOTE_ADDR'] ?? 'cli';

    // Hindari menyimpan data sensitif di description
    $stmt = $conn->prepare(
        "INSERT INTO activity_logs (user_id, user_role, action, target_type, target_id, description, ip_address, status)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->bind_param(
        "isssisss",
        $user_id, $user_role, $action, $target_type, $target_id,
        $description, $ip, $status
    );
    $stmt->execute();
}
?>
