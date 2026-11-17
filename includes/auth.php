<?php
/**
 * KOPI MAHASISWA — Auth Guard & Permission Helper
 * Digunakan oleh semua halaman role untuk proteksi server-side.
 */

if (session_status() == PHP_SESSION_NONE) session_start();

/**
 * Paksa user login sebelum mengakses halaman.
 * Redirect ke login.php jika belum login.
 */
function require_login() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: ../login.php');
        exit;
    }
}

/**
 * Paksa role tertentu. Redirect / die jika role tidak sesuai.
 * $allowed_roles: array of role names (case-insensitive), contoh: ['Admin', 'Manager']
 */
function require_role(array $allowed_roles) {
    require_login();
    $current = strtolower($_SESSION['role'] ?? '');
    $allowed_lower = array_map('strtolower', $allowed_roles);
    if (!in_array($current, $allowed_lower)) {
        http_response_code(403);
        die('403 Forbidden — Anda tidak memiliki akses ke halaman ini.');
    }
}

/**
 * Cek permission spesifik dari tabel role_permissions.
 * Return true/false.
 */
function has_permission(string $permission_name): bool {
    global $conn;
    if (!isset($_SESSION['role_id']) || !$conn) return false;
    $role_id = (int)$_SESSION['role_id'];
    $stmt = $conn->prepare("SELECT 1 FROM role_permissions WHERE role_id = ? AND permission_name = ? LIMIT 1");
    $stmt->bind_param("is", $role_id, $permission_name);
    $stmt->execute();
    return $stmt->get_result()->num_rows > 0;
}

/**
 * Require specific permission. Die if not allowed.
 */
function require_permission(string $permission_name) {
    if (!has_permission($permission_name)) {
        http_response_code(403);
        die("403 Forbidden — Permission '$permission_name' tidak dimiliki oleh role Anda.");
    }
}

/**
 * Update last_active timestamp dan status user (untuk presence).
 */
function update_presence() {
    global $conn;
    if (!isset($_SESSION['user_id']) || !$conn) return;
    $uid = (int)$_SESSION['user_id'];
    $conn->query("UPDATE users SET last_active = NOW() WHERE id = $uid");
}

/**
 * Get presence status berdasarkan last_active.
 * Online < 5m, Away 5-30m, Offline > 30m.
 */
function get_presence_status(?string $last_active): string {
    if (!$last_active) return 'offline';
    $diff = time() - strtotime($last_active);
    if ($diff < 300) return 'online';
    if ($diff < 1800) return 'away';
    return 'offline';
}

/**
 * Cek apakah account user berstatus active.
 * Return false jika suspended.
 */
function is_account_active(int $user_id): bool {
    global $conn;
    $stmt = $conn->prepare("SELECT status FROM users WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return ($row['status'] ?? '') === 'active';
}
?>
