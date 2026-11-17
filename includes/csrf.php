<?php
if (session_status() == PHP_SESSION_NONE) session_start();
function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}
function csrf_input() {
    return '<input type="hidden" name="csrf_token" value="' . generate_csrf_token() . '">';
}
function verify_csrf_token($token) {
    if (isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token)) {
        return true;
    }
    die("CSRF token validation failed. (Keamanan diaktifkan, token tidak valid).");
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        die("CSRF validation failed");
    }
}
?>