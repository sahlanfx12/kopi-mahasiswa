<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PureHelpersTest extends TestCase
{
    #[DataProvider('otpPhoneCases')]
    public function testOtpNormalizePhone(?string $expected, string $input): void
    {
        self::assertSame($expected, otp_normalize_phone($input));
    }

    public static function otpPhoneCases(): array
    {
        return [
            'local number with separators' => ['6281234567890', ' 0812-3456-7890 '],
            'international number with punctuation' => ['6281234567890', '+62 (812) 3456-7890'],
            'local number without leading zero' => ['6281234567890', '81234567890'],
            'minimum accepted length' => ['62812345678', '812345678'],
            'maximum accepted length' => ['62812345678901', '812345678901'],
            'empty input' => [null, ''],
            'too short' => [null, '81234567'],
            'too long' => [null, '8123456789012'],
            'foreign country code' => [null, '+1 212 555 0100'],
            'dangerous characters' => [null, '0812<script>'],
        ];
    }

    public function testOtpDigestIsStableBinarySha256Hmac(): void
    {
        $digest = otp_digest(42, '6281234567890', 'challenge-1', '004201', 'unit-test-secret');

        self::assertSame(32, strlen($digest));
        self::assertSame(
            otp_digest(42, '6281234567890', 'challenge-1', '004201', 'unit-test-secret'),
            $digest
        );
        self::assertSame(
            'b242ae926e85cae3d0d89de7bcb2b7e49e5102ffd172433990ce5a1adc5effcb',
            bin2hex(otp_digest(
                42,
                '628123456789',
                'challenge-test-001',
                '123456',
                'unit-test-secret'
            ))
        );
    }

    public function testOtpDigestChangesWhenAnyInputChanges(): void
    {
        $base = otp_digest(42, '6281234567890', 'challenge-1', '004201', 'unit-test-secret');
        $variants = [
            otp_digest(43, '6281234567890', 'challenge-1', '004201', 'unit-test-secret'),
            otp_digest(42, '6281234567891', 'challenge-1', '004201', 'unit-test-secret'),
            otp_digest(42, '6281234567890', 'challenge-2', '004201', 'unit-test-secret'),
            otp_digest(42, '6281234567890', 'challenge-1', '004202', 'unit-test-secret'),
            otp_digest(42, '6281234567890', 'challenge-1', '004201', 'another-test-secret'),
        ];

        foreach ($variants as $variant) {
            self::assertNotSame($base, $variant);
        }
    }

    public function testIntegrationSecretHintUsesLastFourCharactersAtEightCharacterBoundary(): void
    {
        self::assertSame('', integration_secret_hint('short7'));
        self::assertSame('5678', integration_secret_hint('12345678'));
        self::assertSame('7890', integration_secret_hint('  abcdef7890  '));
        self::assertSame('', integration_secret_hint(''));
    }

    public function testIntegrationMaskHintMasksEmptyAndAppendsHint(): void
    {
        $mask = str_repeat('•', 8);

        self::assertSame($mask, integration_mask_hint(null));
        self::assertSame($mask, integration_mask_hint(''));
        self::assertSame($mask . 'AB12', integration_mask_hint('AB12'));
    }

    #[DataProvider('secretWellFormedCases')]
    public function testIntegrationSecretValidation(bool $expected, string $secret): void
    {
        self::assertSame($expected, integration_secret_is_well_formed($secret));
    }

    public static function secretWellFormedCases(): array
    {
        return [
            'empty' => [false, ''],
            'one below minimum' => [false, '1234567'],
            'minimum length' => [true, '12345678'],
            'maximum length' => [true, str_repeat('A', 512)],
            'one above maximum' => [false, str_repeat('A', 513)],
            'space is not printable secret content' => [false, '1234 678'],
            'newline is rejected' => [false, "1234567\n"],
            'control character is rejected' => [false, "1234567\0"],
            'non ASCII is rejected' => [false, '1234567é'],
            'printable punctuation is accepted' => [true, 'Ab!#$%&*'],
        ];
    }

    public function testIntegrationSecretValidationSupportsExplicitBounds(): void
    {
        self::assertFalse(integration_secret_is_well_formed('123', 4, 10));
        self::assertTrue(integration_secret_is_well_formed('1234', 4, 10));
        self::assertTrue(integration_secret_is_well_formed(str_repeat('x', 10), 4, 10));
        self::assertFalse(integration_secret_is_well_formed(str_repeat('x', 11), 4, 10));
    }

    #[DataProvider('fonntePhoneCases')]
    public function testIntegrationFonnteNormalizeNumber(?string $expected, string $input): void
    {
        self::assertSame($expected, integration_fonnte_normalize_number($input));
    }

    public static function fonntePhoneCases(): array
    {
        return [
            'local number becomes Indonesia country code' => ['6281234567890', '0812-3456-7890'],
            'international number' => ['6281234567890', '+62 (812) 3456-7890'],
            'minimum accepted length' => ['12345678', '12345678'],
            'maximum accepted length' => ['123456789012345', '123456789012345'],
            'empty input' => [null, ''],
            'too short' => [null, '1234567'],
            'too long' => [null, '1234567890123456'],
            'dangerous characters' => [null, '0812<script>'],
        ];
    }

    #[DataProvider('safeLinkCases')]
    public function testNotificationSafeLink(?string $expected, ?string $input): void
    {
        self::assertSame($expected, notification_center_safe_link($input));
    }

    public static function safeLinkCases(): array
    {
        return [
            'simple relative PHP path' => ['customer/detail.php', 'customer/detail.php'],
            'allowed query string' => ['orders.php?status=READY&page=2', 'orders.php?status=READY&page=2'],
            'null input' => [null, null],
            'empty input' => [null, ''],
            'path traversal' => [null, '../login.php'],
            'absolute URL' => [null, 'https://example.test/login.php'],
            'javascript URL' => [null, 'javascript:alert(1)'],
            'backslash' => [null, 'customer\\detail.php'],
            'space' => [null, 'customer detail.php'],
            'encoded punctuation' => [null, 'index.php?next=%3Cscript%3E'],
            'maximum length' => [str_repeat('a', 251) . '.php', str_repeat('a', 251) . '.php'],
            'over maximum length' => [null, str_repeat('a', 252) . '.php'],
        ];
    }

    #[DataProvider('htmlEscapingCases')]
    public function testNotificationHtmlEscaping(string $expected, string $input): void
    {
        self::assertSame($expected, notification_center_h($input));
    }

    public static function htmlEscapingCases(): array
    {
        return [
            'empty input' => ['', ''],
            'HTML metacharacters and quotes' => ['&lt;tag attr=&quot;x&quot;&gt;&#039;&amp;', '<tag attr="x">\'&'],
            'script element' => [
                '&lt;script&gt;alert(&#039;x&#039;)&lt;/script&gt;',
                "<script>alert('x')</script>",
            ],
            'invalid UTF-8 is substituted' => ["�(", "\xC3("],
        ];
    }

    #[DataProvider('statusLabelCases')]
    public function testAppStatusLabel(string $expected, ?string $status): void
    {
        self::assertSame($expected, app_status_label($status));
    }

    public static function statusLabelCases(): array
    {
        return [
            'known status' => ['Menunggu', 'PENDING'],
            'case and surrounding whitespace' => ['Dikonfirmasi', ' confirmed '],
            'unknown status is normalized' => ['FUTURE_STATE', ' future_state '],
            'empty status' => ['Tidak diketahui', ''],
            'whitespace status' => ['Tidak diketahui', " \t\n"],
            'null status' => ['Tidak diketahui', null],
        ];
    }

    public function testAppStatusLabelsContainsExpectedStatusMappings(): void
    {
        self::assertSame([
            'PENDING' => 'Menunggu',
            'CONFIRMED' => 'Dikonfirmasi',
            'PREPARING' => 'Sedang Disiapkan',
            'READY' => 'Siap Disajikan',
            'COMPLETED' => 'Selesai',
            'CANCELLED' => 'Dibatalkan',
            'DRAFT' => 'Draf',
            'ORDERED' => 'Dipesan',
            'SHIPPED' => 'Dikirim',
            'RECEIVED' => 'Diterima',
            'ACTIVE' => 'Aktif',
            'INACTIVE' => 'Tidak Aktif',
            'SUSPENDED' => 'Ditangguhkan',
            'IN' => 'Stok Masuk',
            'OUT' => 'Stok Keluar',
        ], app_status_labels());
    }
}
