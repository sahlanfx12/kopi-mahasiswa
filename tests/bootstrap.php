<?php

declare(strict_types=1);

$projectRoot = dirname(__DIR__);
$autoloadCandidates = [
    getenv('PHPUNIT_AUTOLOAD') ?: null,
    $projectRoot . '/vendor/autoload.php',
];

$autoloadLoaded = false;
foreach ($autoloadCandidates as $autoloadPath) {
    if (is_string($autoloadPath) && is_file($autoloadPath)) {
        require_once $autoloadPath;
        $autoloadLoaded = true;
        break;
    }
}

if (!$autoloadLoaded) {
    throw new RuntimeException(
        'PHPUnit dependencies are missing. Run composer install or set PHPUNIT_AUTOLOAD.'
    );
}

// Load only helper files that declare the functions under test. No app bootstrap,
// database connection, session initialization, or external integration is loaded.
require_once $projectRoot . '/includes/otp_service.php';
require_once $projectRoot . '/includes/integration_settings.php';
require_once $projectRoot . '/includes/notification_center.php';
require_once $projectRoot . '/includes/interaction_ui.php';
require_once $projectRoot . '/includes/ai_service.php';
