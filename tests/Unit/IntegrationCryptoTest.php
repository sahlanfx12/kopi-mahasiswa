<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class IntegrationCryptoTest extends TestCase
{
    private string|false $previousEncryptionKey = false;

    protected function setUp(): void
    {
        $this->previousEncryptionKey = getenv('INTEGRATION_ENCRYPTION_KEY');
        putenv('INTEGRATION_ENCRYPTION_KEY=' . base64_encode(str_repeat("K", 32)));
    }

    protected function tearDown(): void
    {
        if ($this->previousEncryptionKey === false) {
            putenv('INTEGRATION_ENCRYPTION_KEY');
        } else {
            putenv('INTEGRATION_ENCRYPTION_KEY=' . $this->previousEncryptionKey);
        }
    }

    public function testEncryptedSettingRoundTripsOnlyForItsPurpose(): void
    {
        $packed = integration_encrypt('synthetic-test-secret', 'ai-provider-key');

        self::assertStringStartsWith('v1.', $packed);
        self::assertSame('synthetic-test-secret', integration_decrypt($packed, 'ai-provider-key'));
        self::assertNull(integration_decrypt($packed, 'fonnte-device-token'));
    }

    public function testMalformedOrTamperedCiphertextFailsClosed(): void
    {
        self::assertNull(integration_decrypt('plaintext', 'ai-provider-key'));

        $packed = integration_encrypt('synthetic-test-secret', 'ai-provider-key');
        $binary = base64_decode(substr($packed, 3), true);
        self::assertIsString($binary);
        $binary[12] = chr(ord($binary[12]) ^ 1);
        self::assertNull(integration_decrypt('v1.' . base64_encode($binary), 'ai-provider-key'));
    }
}
