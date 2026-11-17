<?php

declare(strict_types=1);

$projectRoot = dirname(__DIR__, 2);
$endpointRelative = $argv[1] ?? '';
$requestMethod = $argv[2] ?? 'GET';
$sessionRole = $argv[3] ?? '';
$sessionDirectory = $argv[4] ?? '';
$allowedEndpoints = [
    'admin/ai_api.php',
    'customer/ai_api.php',
    'kasir/ai_api.php',
    'supplier/ai_api.php',
    'owner/ai_api.php',
];

if (!in_array($endpointRelative, $allowedEndpoints, true) || !is_dir($sessionDirectory)) {
    fwrite(STDERR, 'Invalid local chatbot guard probe configuration.');
    exit(2);
}

session_save_path($sessionDirectory);
session_id('codex-chatbot-guard-probe');
session_start();
if ($sessionRole !== '') {
    $_SESSION['user_id'] = 987654321;
    $_SESSION['role'] = $sessionRole;
}
session_write_close();

$_SERVER['REQUEST_METHOD'] = $requestMethod;
$_SERVER['HTTP_ACCEPT'] = 'application/json';
$_SERVER['CONTENT_TYPE'] = 'application/json';
$_SERVER['SCRIPT_FILENAME'] = $projectRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $endpointRelative);
include $_SERVER['SCRIPT_FILENAME'];
