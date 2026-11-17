<?php
if (is_file(__DIR__ . '/integration_settings.php')) {
    require_once __DIR__ . '/integration_settings.php';
}
// Konstanta Pesan Error Standar Layanan AI
if (!defined('AI_ERROR_CONNECTION')) {
    define('AI_ERROR_CONNECTION', 'Layanan AI sedang mengalami gangguan koneksi. Silakan coba kembali.');
}
if (!defined('AI_ERROR_RATE_LIMIT')) {
    define('AI_ERROR_RATE_LIMIT', 'Permintaan AI sedang mencapai batas sementara. Silakan coba beberapa saat lagi.');
}
if (!defined('AI_ERROR_DAILY_QUOTA')) {
    define('AI_ERROR_DAILY_QUOTA', 'Kuota harian AI telah habis. Silakan coba kembali setelah kuota tersedia.');
}
if (!defined('AI_ERROR_AUTH')) {
    define('AI_ERROR_AUTH', 'Layanan AI sedang tidak dapat diakses saat ini. Silakan coba kembali nanti.');
}
if (!defined('AI_ERROR_SERVICE')) {
    define('AI_ERROR_SERVICE', 'Layanan AI sedang tidak dapat memproses permintaan saat ini. Silakan coba kembali nanti.');
}
if (!defined('AI_ERROR_NOT_CONFIGURED')) {
    define('AI_ERROR_NOT_CONFIGURED', AI_ERROR_SERVICE);
}

if (!function_exists('ai_sanitize_curl_error')) {
    function ai_sanitize_curl_error(string $message): string {
        $message = preg_replace('/[\r\n\t]+/', ' ', $message);
        $message = preg_replace('~https?://\S+~i', '[url]', $message);
        $message = preg_replace('/\bauthorization\s*[:=]\s*(?:Bearer\s+)?[^\s,;]+/i', 'Authorization=[redacted]', $message);
        $message = preg_replace('/\bBearer\s+\S+/i', 'Bearer [redacted]', $message);
        $message = preg_replace('/\b(api[_-]?key|token|secret|password)\b\s*[:=]\s*\S+/i', '$1=[redacted]', $message);
        $message = trim(preg_replace('/\s+/', ' ', $message));

        return substr($message, 0, 240);
    }
}

if (!function_exists('ai_http_request')) {
    function ai_http_request(string $url, array $headers, string $body, int $connect_timeout, int $timeout): array {
        $ch = curl_init($url);
        if ($ch === false) {
            return [
                'body' => null,
                'http_status' => 0,
                'failure_category' => 'curl_error',
                'curl_errno' => null,
                'curl_error' => 'curl_init_failed',
                'connect_time_ms' => null,
                'total_time_ms' => null,
            ];
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $connect_timeout);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

        $response_body = curl_exec($ch);
        $http_status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_errno = curl_errno($ch);
        $curl_error = $curl_errno !== 0 ? ai_sanitize_curl_error(curl_error($ch)) : '';
        $connect_time_ms = (int)round((float)curl_getinfo($ch, CURLINFO_CONNECT_TIME) * 1000);
        $total_time_ms = (int)round((float)curl_getinfo($ch, CURLINFO_TOTAL_TIME) * 1000);
        curl_close($ch);

        return [
            'body' => is_string($response_body) ? $response_body : null,
            'http_status' => $http_status,
            'curl_errno' => $curl_errno,
            'curl_error' => $curl_error,
            'connect_time_ms' => $connect_time_ms,
            'total_time_ms' => $total_time_ms,
            'failure_category' => $curl_errno === CURLE_OPERATION_TIMEDOUT
                ? 'timeout'
                : ($curl_errno !== 0 ? 'curl_error' : null),
        ];
    }
}

function get_ai_response($prompt, $system_prompt = "", array $history = [], ?int $max_tokens = null) {
    if ($max_tokens !== null && ($max_tokens < 1 || $max_tokens > 8192)) {
        throw new InvalidArgumentException("AI response token limit is outside the supported range.");
    }
    $provider_max_tokens = $max_tokens ?? 1024;
    $GLOBALS['ai_last_result'] = [
        'success' => false,
        'reply' => AI_ERROR_SERVICE,
        'error_code' => 'service_unavailable',
        'provider_failures' => [],
    ];
    $xkiro_resolved = function_exists('integration_xkiro_resolve') ? integration_xkiro_resolve() : null;
    $xkiro_key = is_array($xkiro_resolved) ? $xkiro_resolved['key'] : getenv('XKIRO_API_KEY');
    $xkiro_model = is_array($xkiro_resolved) ? $xkiro_resolved['model'] : getenv('XKIRO_MODEL');
    $nvidia_key = getenv('NVIDIA_API_KEY');
    $gemini_key = getenv('GEMINI_API_KEY');
    $deepseek_key = getenv('DEEPSEEK_API_KEY');
    $failure_categories = [];

    $make_messages = static function () use ($prompt, $system_prompt, $history): array {
        $messages = [];
        if (!empty($system_prompt)) {
            $messages[] = ['role' => 'system', 'content' => $system_prompt];
        }

        foreach ($history as $item) {
            if (isset($item['role'], $item['content']) && in_array($item['role'], ['user', 'assistant'], true)) {
                $messages[] = [
                    'role' => $item['role'],
                    'content' => (string)$item['content'],
                ];
            }
        }

        $messages[] = ['role' => 'user', 'content' => $prompt];
        return $messages;
    };

    $xkiro_key = is_string($xkiro_key) ? trim($xkiro_key) : '';
    $xkiro_model = is_string($xkiro_model) ? trim($xkiro_model) : '';
    if ($xkiro_key !== '' && $xkiro_model === '') {
        error_log('AI Provider diagnostic provider=xKiro duration_ms=0 http_status=0 failure=missing_model');
        $failure_categories[] = 'missing_model';
    }

    $request_provider = static function (
        string $provider,
        string $url,
        array $headers,
        array $data,
        int $timeout,
        callable $extract_content,
        ?string &$failure_category
    ): ?string {
        $started_at = microtime(true);
        $http_status = 0;
        $failure_category = 'invalid_request';
        $body = json_encode($data);

        if ($body !== false) {
            $result = ai_http_request($url, $headers, $body, 5, $timeout);
            $http_status = (int)($result['http_status'] ?? 0);

            if (in_array($result['failure_category'] ?? null, ['timeout', 'curl_error'], true)) {
                $failure_category = $result['failure_category'];
            } elseif ($http_status < 200 || $http_status >= 300) {
                $failure_category = match ($http_status) {
                    401, 403 => 'authentication_error',
                    402 => 'payment_required',
                    429 => 'rate_limited',
                    default => ($http_status >= 500 ? 'provider_server_error' : 'http_error'),
                };
                $json = json_decode((string)($result['body'] ?? ''), true);
                $provider_code = is_array($json)
                    ? ($json['error']['code'] ?? $json['error']['type'] ?? $json['code'] ?? null)
                    : null;
                if ($http_status === 429 && is_string($provider_code)
                    && in_array(strtolower($provider_code), [
                        'daily_quota_exceeded',
                        'daily_free_token_limit',
                        'free_token_limit_exceeded',
                        'free_tokens_exhausted',
                    ], true)
                ) {
                    $failure_category = 'daily_quota_exhausted';
                }
            } else {
                $json = json_decode((string)($result['body'] ?? ''), true);
                if (!is_array($json)) {
                    $failure_category = 'invalid_json';
                } else {
                    $content = $extract_content($json);
                    if (is_string($content) && trim($content) !== '') {
                        $failure_category = 'none';
                        error_log(sprintf(
                            'AI Provider diagnostic provider=%s duration_ms=%d http_status=%d failure=%s',
                            $provider,
                            (int)round((microtime(true) - $started_at) * 1000),
                            $http_status,
                            $failure_category
                        ));
                        return $content;
                    }
                    $failure_category = 'missing_content';
                }
            }
        }

        $diagnostic = isset($result) && is_array($result)
            ? sprintf(
                ' curl_errno=%s curl_error=%s connect_time_ms=%s total_time_ms=%s',
                isset($result['curl_errno']) ? (string)$result['curl_errno'] : 'unavailable',
                !empty($result['curl_error']) ? ai_sanitize_curl_error((string)$result['curl_error']) : 'none',
                isset($result['connect_time_ms']) ? (string)$result['connect_time_ms'] : 'unavailable',
                isset($result['total_time_ms']) ? (string)$result['total_time_ms'] : 'unavailable'
            )
            : '';
        error_log(sprintf(
            'AI Provider diagnostic provider=%s duration_ms=%d http_status=%d failure=%s%s',
            $provider,
            (int)round((microtime(true) - $started_at) * 1000),
            $http_status,
            $failure_category,
            $diagnostic
        ));
        return null;
    };

    $xkiro_failure = null;
    if ($xkiro_key !== '' && $xkiro_model !== '') {
        $xkiro_data = [
            'model' => $xkiro_model,
            'messages' => $make_messages(),
            'temperature' => 0.2,
            'max_tokens' => $provider_max_tokens,
            'stream' => false,
        ];
        $xkiro_response = $request_provider(
            'xKiro',
            'https://api.xkiro.com/v1/chat/completions',
            [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $xkiro_key,
            ],
            $xkiro_data,
            12,
            static function (array $json) {
                return $json['choices'][0]['message']['content'] ?? null;
            },
            $xkiro_failure
        );

        if ($xkiro_response !== null) {
            $GLOBALS['ai_last_result'] = [
                'success' => true,
                'reply' => $xkiro_response,
                'error_code' => null,
                'provider_failures' => [],
            ];
            return $xkiro_response;
        }

        $failure_categories[] = $xkiro_failure ?? 'unknown_error';
    }

    $nvidia_failure = null;
    if ($nvidia_key) {
        $nvidia_data = [
            'model' => 'meta/llama-3.2-11b-vision-instruct',
            'messages' => $make_messages(),
            'temperature' => 0.2,
            'max_tokens' => $provider_max_tokens,
        ];
        $nvidia_response = $request_provider(
            'NVIDIA',
            'https://integrate.api.nvidia.com/v1/chat/completions',
            [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $nvidia_key,
            ],
            $nvidia_data,
            12,
            static function (array $json) {
                return $json['choices'][0]['message']['content'] ?? null;
            },
            $nvidia_failure
        );

        if ($nvidia_response !== null) {
            $GLOBALS['ai_last_result'] = [
                'success' => true,
                'reply' => $nvidia_response,
                'error_code' => null,
                'provider_failures' => [],
            ];
            return $nvidia_response;
        }
        $failure_categories[] = $nvidia_failure ?? 'unknown_error';
    }

    if ($gemini_key) {
        $gemini_contents = [];
        foreach ($history as $item) {
            if (isset($item['role'], $item['content']) && in_array($item['role'], ['user', 'assistant'], true)) {
                $gemini_contents[] = [
                    'role' => $item['role'] === 'assistant' ? 'model' : 'user',
                    'parts' => [['text' => (string)$item['content']]],
                ];
            }
        }
        $gemini_contents[] = [
            'role' => 'user',
            'parts' => [['text' => $prompt]],
        ];

        $gemini_data = ['contents' => $gemini_contents];
        if (!empty($system_prompt)) {
            $gemini_data['system_instruction'] = [
                'parts' => [['text' => $system_prompt]],
            ];
        }
        if ($max_tokens !== null) {
            $gemini_data['generationConfig'] = [
                'maxOutputTokens' => $max_tokens,
            ];
        }

        $gemini_failure = null;
        $gemini_response = $request_provider(
            'Gemini',
            'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.8-flash:generateContent',
            [
                'Content-Type: application/json',
                'x-goog-api-key: ' . $gemini_key,
            ],
            $gemini_data,
            12,
            static function (array $json) {
                return $json['candidates'][0]['content']['parts'][0]['text'] ?? null;
            },
            $gemini_failure
        );

        if ($gemini_response !== null) {
            $GLOBALS['ai_last_result'] = [
                'success' => true,
                'reply' => $gemini_response,
                'error_code' => null,
                'provider_failures' => [],
            ];
            return $gemini_response;
        }

        $failure_categories[] = $gemini_failure ?? 'unknown_error';
    }

    if ($deepseek_key) {
        $deepseek_data = [
            'model' => 'deepseek-flash',
            'messages' => $make_messages(),
            'temperature' => 0.2,
            'max_tokens' => $provider_max_tokens,
            'stream' => false,
        ];

        $deepseek_failure = null;
        $deepseek_response = $request_provider(
            'DeepSeek',
            'https://api.deepseek.com/chat/completions',
            [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $deepseek_key,
            ],
            $deepseek_data,
            12,
            static function (array $json) {
                return $json['choices'][0]['message']['content'] ?? null;
            },
            $deepseek_failure
        );

        if ($deepseek_response !== null) {
            $GLOBALS['ai_last_result'] = [
                'success' => true,
                'reply' => $deepseek_response,
                'error_code' => null,
                'provider_failures' => [],
            ];
            return $deepseek_response;
        }

        $failure_categories[] = $deepseek_failure ?? 'unknown_error';
    }

    if (!$xkiro_key && !$nvidia_key && !$gemini_key && !$deepseek_key) {
        $GLOBALS['ai_last_result'] = [
            'success' => false,
            'reply' => AI_ERROR_NOT_CONFIGURED,
            'error_code' => 'not_configured',
            'provider_failures' => [],
        ];
        return AI_ERROR_NOT_CONFIGURED;
    }

    $all_are = static function (array $categories, array $allowed): bool {
        return $categories !== []
            && count(array_filter($categories, static function ($category) use ($allowed) {
                return !in_array($category, $allowed, true);
            })) === 0;
    };
    if ($all_are($failure_categories, ['daily_quota_exhausted'])) {
        $error_code = 'daily_quota_exhausted';
        $reply = AI_ERROR_DAILY_QUOTA;
    } elseif ($all_are($failure_categories, ['rate_limited'])) {
        $error_code = 'rate_limited';
        $reply = AI_ERROR_RATE_LIMIT;
    } elseif ($all_are($failure_categories, ['timeout', 'curl_error'])) {
        $error_code = 'connection_error';
        $reply = AI_ERROR_CONNECTION;
    } elseif ($all_are($failure_categories, ['authentication_error'])) {
        $error_code = 'authentication_error';
        $reply = AI_ERROR_AUTH;
    } else {
        $error_code = 'service_unavailable';
        $reply = AI_ERROR_SERVICE;
    }

    $GLOBALS['ai_last_result'] = [
        'success' => false,
        'reply' => $reply,
        'error_code' => $error_code,
        'provider_failures' => $failure_categories,
    ];
    return $reply;
}

function get_ai_response_result($prompt, $system_prompt = "", array $history = [], ?int $max_tokens = null): array {
    $reply = get_ai_response($prompt, $system_prompt, $history, $max_tokens);
    $result = $GLOBALS['ai_last_result'] ?? null;
    if (!is_array($result) || !array_key_exists('success', $result)) {
        return [
            'success' => !is_ai_error($reply),
            'reply' => $reply,
            'error_code' => is_ai_error($reply) ? 'service_unavailable' : null,
            'provider_failures' => [],
        ];
    }
    return $result;
}

/**
 * Memeriksa apakah teks respons merupakan pesan error dari layanan AI.
 * Menggunakan perbandingan string eksak terhadap pesan error sistem
 * sehingga respons normal AI yang mengandung kata seperti "maaf" atau "gagal"
 * tidak disalahartikan sebagai error.
 *
 * @param string|null $response Teks respons dari get_ai_response()
 * @return bool True jika respons adalah error layanan AI, False jika respons berhasil
 */
function is_ai_error(?string $response): bool {
    if ($response === null || trim($response) === '') {
        return true;
    }

    $system_errors = [
        AI_ERROR_CONNECTION,
        AI_ERROR_RATE_LIMIT,
        AI_ERROR_DAILY_QUOTA,
        AI_ERROR_AUTH,
        AI_ERROR_SERVICE,
        AI_ERROR_NOT_CONFIGURED,
    ];

    return in_array(trim($response), $system_errors, true);
}
?>
