<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ChatbotEndpointGuardTest extends TestCase
{
    private string $sessionDirectory;

    protected function setUp(): void
    {
        $this->sessionDirectory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'km-chatbot-test-' . bin2hex(random_bytes(8));
        if (!mkdir($this->sessionDirectory, 0700) && !is_dir($this->sessionDirectory)) {
            self::fail('Could not create isolated temporary session directory.');
        }
    }

    protected function tearDown(): void
    {
        if (!isset($this->sessionDirectory) || !is_dir($this->sessionDirectory)) {
            return;
        }
        foreach (glob($this->sessionDirectory . DIRECTORY_SEPARATOR . '*') ?: [] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
        rmdir($this->sessionDirectory);
    }

    #[DataProvider('endpointRoles')]
    public function testEndpointRejectsWrongSessionRole(string $endpoint, string $expectedRole): void
    {
        $wrongRole = $expectedRole === 'admin' ? 'customer' : 'admin';
        $output = $this->runProbe($endpoint, 'POST', $wrongRole);
        $payload = json_decode($output, true);

        self::assertIsArray($payload);
        self::assertSame('Unauthorized', $payload['error'] ?? null);
    }

    #[DataProvider('endpointRoles')]
    public function testEndpointRejectsNonPostRequest(string $endpoint, string $expectedRole): void
    {
        $output = $this->runProbe($endpoint, 'GET', '');
        $payload = json_decode($output, true);

        self::assertIsArray($payload);
        self::assertSame('Method Not Allowed', $payload['error'] ?? null);
    }

    public static function endpointRoles(): array
    {
        return [
            'admin' => ['admin/ai_api.php', 'admin'],
            'customer' => ['customer/ai_api.php', 'customer'],
            'kasir' => ['kasir/ai_api.php', 'kasir'],
            'supplier' => ['supplier/ai_api.php', 'supplier'],
            'owner' => ['owner/ai_api.php', 'owner'],
        ];
    }

    private function runProbe(string $endpoint, string $method, string $role): string
    {
        $probe = __DIR__ . '/../Support/ProbeChatbotEndpoint.php';
        $pipes = [];
        $process = proc_open(
            [PHP_BINARY, $probe, $endpoint, $method, $role, $this->sessionDirectory],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            dirname(__DIR__, 2)
        );
        if (!is_resource($process)) {
            self::fail('Could not start isolated endpoint guard probe.');
        }

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        self::assertSame(0, $exitCode, 'Endpoint probe failed: ' . $stderr);
        self::assertSame('', trim($stderr), 'Endpoint probe wrote diagnostics to stderr.');

        return trim((string)$stdout);
    }
}
