<?php

declare(strict_types=1);

final class TestDatabase
{
    public const DATABASE_NAME = 'kopi_mahasiswa_test';

    public static function connectionOrSkip(PHPUnit\Framework\TestCase $testCase): mysqli
    {
        $database = getenv('KM_TEST_DB_NAME');
        if ($database === false || trim($database) === '') {
            $testCase->markTestSkipped('KM_TEST_DB_NAME is not configured; no database connection was attempted.');
        }
        if ($database !== self::DATABASE_NAME) {
            throw new RuntimeException('Refusing database access: KM_TEST_DB_NAME must exactly match kopi_mahasiswa_test.');
        }

        $host = getenv('KM_TEST_DB_HOST');
        $user = getenv('KM_TEST_DB_USER');
        $password = getenv('KM_TEST_DB_PASSWORD');
        $port = getenv('KM_TEST_DB_PORT');
        if ($host === false || $user === false || $password === false || $port === false) {
            $testCase->markTestSkipped('Test database connection variables are incomplete; no database connection was attempted.');
        }
        if (!in_array(strtolower($host), ['localhost', '127.0.0.1', '::1'], true)) {
            throw new RuntimeException('Refusing database access: the test database host must be loopback.');
        }
        if (!ctype_digit($port) || (int)$port < 1 || (int)$port > 65535) {
            throw new RuntimeException('Refusing database access: KM_TEST_DB_PORT is invalid.');
        }

        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        $connection = new mysqli($host, $user, $password, self::DATABASE_NAME, (int)$port);
        $selectedDatabase = $connection->query('SELECT DATABASE() AS database_name')->fetch_assoc()['database_name'] ?? null;
        if ($selectedDatabase !== self::DATABASE_NAME) {
            $connection->close();
            throw new RuntimeException('Refusing database operations: connected database verification failed.');
        }
        $connection->set_charset('utf8mb4');

        return $connection;
    }
}
