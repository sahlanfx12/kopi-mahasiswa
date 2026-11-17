<?php
/**
 * PRESENCE HEARTBEAT
 * Dipanggil oleh JS setiap 2 menit via fetch() untuk update last_active.
 * Ringan: hanya UPDATE satu row berdasarkan session user_id.
 */
if (session_status() == PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'unauthenticated']);
    exit;
}

require 'config.php';
$uid = (int)$_SESSION['user_id'];
$conn->query("UPDATE users SET last_active = NOW() WHERE id = $uid");

echo json_encode(['status' => 'ok']);
?>
