<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AiServiceHelpersTest extends TestCase
{
    public function testCurlErrorSanitizerRemovesUrlsAndCredentialValues(): void
    {
        $sanitized = ai_sanitize_curl_error(
            "POST https://provider.example/v1?token=secret Authorization: Bearer abc123 api_key=xyz789\nnext"
        );

        self::assertStringNotContainsString('provider.example', $sanitized);
        self::assertStringNotContainsString('abc123', $sanitized);
        self::assertStringNotContainsString('xyz789', $sanitized);
        self::assertStringContainsString('[url]', $sanitized);
        self::assertStringContainsString('[redacted]', $sanitized);
    }

    public function testCurlErrorSanitizerCapsDiagnosticLength(): void
    {
        self::assertLessThanOrEqual(240, strlen(ai_sanitize_curl_error(str_repeat('x', 500))));
    }

    #[DataProvider('aiErrorCases')]
    public function testAiErrorRecognitionUsesConfiguredErrorMessages(bool $expected, ?string $message): void
    {
        self::assertSame($expected, is_ai_error($message));
    }

    public static function aiErrorCases(): array
    {
        return [
            'null response' => [true, null],
            'empty response' => [true, '  '],
            'connection error' => [true, AI_ERROR_CONNECTION],
            'rate limit error' => [true, AI_ERROR_RATE_LIMIT],
            'service error' => [true, AI_ERROR_SERVICE],
            'ordinary apology is still a reply' => [false, 'Maaf, pesanan Anda sedang disiapkan.'],
            'ordinary failure wording is still a reply' => [false, 'Saya gagal memahami konteks itu.'],
        ];
    }

    #[DataProvider('invalidTokenLimits')]
    public function testInvalidTokenLimitIsRejectedBeforeProviderResolution(int $maxTokens): void
    {
        $this->expectException(InvalidArgumentException::class);
        get_ai_response('test prompt', 'test persona', [], $maxTokens);
    }

    public static function invalidTokenLimits(): array
    {
        return [
            'zero' => [0],
            'above supported maximum' => [8193],
        ];
    }
}
