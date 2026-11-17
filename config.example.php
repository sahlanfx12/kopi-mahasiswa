<?php
// Copy this file to config.php and replace every placeholder with local values.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$host = 'YOUR_LOCAL_DB_HOST';
$user = 'YOUR_LOCAL_DB_USER';
$pass = 'YOUR_LOCAL_DB_PASSWORD';
$dbname = 'YOUR_LOCAL_DB_NAME';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli($host, $user, $pass, $dbname);
    $conn->set_charset('utf8mb4');
} catch (mysqli_sql_exception $e) {
    error_log('Database connection failed: ' . $e->getMessage());
    if (php_sapi_name() === 'cli') {
        die('Database connection failed. Check the local database configuration.');
    }
    http_response_code(503);
    die('Service temporarily unavailable.');
}
