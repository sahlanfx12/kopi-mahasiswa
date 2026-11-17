<?php
/**
 * KOPI MAHASISWA — Konfigurasi Integrasi (xKiro & Fonnte)
 *
 * Tanggung jawab file ini:
 * - Menyimpan/membaca pengaturan integrasi dari tabel `integration_settings`
 *   dan `integration_fonnte_devices` (lihat database/migrations/20261109_add_integration_settings.sql).
 * - Enkripsi rahasia (API Key xKiro, Device Token Fonnte) memakai AES-256-GCM.
 *   Kunci enkripsi utama HANYA dibaca dari environment `INTEGRATION_ENCRYPTION_KEY`.
 * - Resolver yang dipakai includes/ai_service.php dan includes/otp_service.php.
 *   Jika tabel belum ada, kunci enkripsi belum diset, atau data tidak dapat didekripsi,
 *   resolver memakai API key dari XKIRO_API_KEY dan model utama dari registry xKiro.
 *
 * Aturan keamanan:
 * - Plaintext rahasia tidak pernah dicatat ke log, tidak dikembalikan ke browser,
 *   dan tidak pernah disimpan ke database tanpa enkripsi.
 * - Tidak ada fallback yang menyimpan rahasia sebagai plaintext.
 */

if (!defined('INTEGRATION_CIPHER')) {
    define('INTEGRATION_CIPHER', 'aes-256-gcm');
}
if (!defined('INTEGRATION_CIPHER_PREFIX')) {
    define('INTEGRATION_CIPHER_PREFIX', 'v1.');
}
if (!defined('INTEGRATION_XKIRO_BASE_URL')) {
    define('INTEGRATION_XKIRO_BASE_URL', 'https://api.xkiro.com/v1');
}
if (!defined('INTEGRATION_FONNTE_MAX_DEVICES')) {
    define('INTEGRATION_FONNTE_MAX_DEVICES', 5);
}

/* ------------------------------------------------------------------ */
/* Dasar: koneksi database & kunci enkripsi                            */
/* ------------------------------------------------------------------ */

function integration_db(): ?mysqli
{
    $conn = $GLOBALS['conn'] ?? null;
    return $conn instanceof mysqli ? $conn : null;
}

/**
 * Kunci enkripsi utama 32 byte dari environment.
 * Format yang diterima: base64 (hasil `base64_encode(random_bytes(32))`) atau 64 karakter hex.
 */
function integration_encryption_key(): ?string
{
    $raw = getenv('INTEGRATION_ENCRYPTION_KEY');
    if (!is_string($raw)) return null;
    $raw = trim($raw);
    if ($raw === '') return null;

    if (strlen($raw) === 64 && ctype_xdigit($raw)) {
        $bin = hex2bin($raw);
        return (is_string($bin) && strlen($bin) === 32) ? $bin : null;
    }
    $bin = base64_decode($raw, true);
    return (is_string($bin) && strlen($bin) === 32) ? $bin : null;
}

/** Status kesiapan enkripsi untuk ditampilkan di halaman Admin (tanpa membocorkan nilai). */
function integration_encryption_status(): array
{
    if (!function_exists('openssl_encrypt') || !in_array(INTEGRATION_CIPHER, array_map('strtolower', openssl_get_cipher_methods()), true)) {
        return ['ok' => false, 'reason' => 'openssl_unavailable'];
    }
    $raw = getenv('INTEGRATION_ENCRYPTION_KEY');
    if (!is_string($raw) || trim($raw) === '') {
        return ['ok' => false, 'reason' => 'missing_key'];
    }
    if (integration_encryption_key() === null) {
        return ['ok' => false, 'reason' => 'invalid_key'];
    }
    return ['ok' => true, 'reason' => null];
}

/**
 * Enkripsi authenticated. `$purpose` dipakai sebagai AAD sehingga ciphertext
 * satu field tidak bisa dipindahkan ke field lain.
 *
 * @throws RuntimeException jika enkripsi tidak tersedia (TIDAK ada fallback plaintext).
 */
function integration_encrypt(string $plaintext, string $purpose): string
{
    $key = integration_encryption_key();
    if ($key === null || !function_exists('openssl_encrypt')) {
        throw new RuntimeException('Integration encryption is not available.');
    }
    $nonce = random_bytes(12);
    $tag = '';
    $ciphertext = openssl_encrypt($plaintext, INTEGRATION_CIPHER, $key, OPENSSL_RAW_DATA, $nonce, $tag, $purpose, 16);
    if (!is_string($ciphertext) || strlen($tag) !== 16) {
        throw new RuntimeException('Integration encryption failed.');
    }
    return INTEGRATION_CIPHER_PREFIX . base64_encode($nonce . $tag . $ciphertext);
}

/** Dekripsi; mengembalikan null bila format salah, kunci salah, atau tag autentikasi gagal. */
function integration_decrypt(?string $packed, string $purpose): ?string
{
    if (!is_string($packed) || !str_starts_with($packed, INTEGRATION_CIPHER_PREFIX)) return null;
    $key = integration_encryption_key();
    if ($key === null || !function_exists('openssl_decrypt')) return null;

    $bin = base64_decode(substr($packed, strlen(INTEGRATION_CIPHER_PREFIX)), true);
    if (!is_string($bin) || strlen($bin) < 12 + 16 + 1) return null;

    $nonce = substr($bin, 0, 12);
    $tag = substr($bin, 12, 16);
    $ciphertext = substr($bin, 28);
    $plaintext = openssl_decrypt($ciphertext, INTEGRATION_CIPHER, $key, OPENSSL_RAW_DATA, $nonce, $tag, $purpose);
    return is_string($plaintext) ? $plaintext : null;
}

/** 4 karakter terakhir untuk ditampilkan tersamar. */
function integration_secret_hint(string $secret): string
{
    $secret = trim($secret);
    return strlen($secret) >= 8 ? substr($secret, -4) : '';
}

function integration_mask_hint(?string $hint): string
{
    $hint = (string)$hint;
    return $hint !== '' ? '••••••••' . $hint : '••••••••';
}

/** Validasi bentuk rahasia (API key / token): karakter ASCII tampak, tanpa spasi. */
function integration_secret_is_well_formed(string $secret, int $min = 8, int $max = 512): bool
{
    $len = strlen($secret);
    return $len >= $min && $len <= $max && preg_match('/\A[\x21-\x7E]+\z/', $secret) === 1;
}

/* ------------------------------------------------------------------ */
/* Tabel integration_settings                                           */
/* ------------------------------------------------------------------ */

function integration_tables_ready(): bool
{
    static $ready = null;
    if ($ready !== null) return $ready;
    $conn = integration_db();
    if (!$conn) return $ready = false;
    try {
        $stmt = $conn->prepare("SELECT COUNT(*) AS c FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('integration_settings', 'integration_fonnte_devices')");
        if (!$stmt || !$stmt->execute()) return $ready = false;
        $row = $stmt->get_result()->fetch_assoc();
        return $ready = ((int)($row['c'] ?? 0) === 2);
    } catch (Throwable $e) {
        error_log('Integration settings table check failed.');
        return $ready = false;
    }
}

function integration_setting_get(string $key): ?string
{
    $conn = integration_db();
    if (!$conn) return null;
    try {
        $stmt = $conn->prepare('SELECT setting_value FROM integration_settings WHERE setting_key = ? LIMIT 1');
        if (!$stmt) return null;
        $stmt->bind_param('s', $key);
        if (!$stmt->execute()) return null;
        $row = $stmt->get_result()->fetch_assoc();
        return $row && $row['setting_value'] !== null ? (string)$row['setting_value'] : null;
    } catch (Throwable $e) {
        error_log('Integration setting read failed.');
        return null;
    }
}

/** @throws RuntimeException */
function integration_setting_set(string $key, ?string $value, bool $isSecret, ?int $userId): void
{
    $conn = integration_db();
    if (!$conn) throw new RuntimeException('Database is not available.');
    $secretFlag = $isSecret ? 1 : 0;
    $stmt = $conn->prepare('INSERT INTO integration_settings (setting_key, setting_value, is_secret, updated_by) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), is_secret = VALUES(is_secret), updated_by = VALUES(updated_by)');
    if (!$stmt) throw new RuntimeException('Integration setting write could not be prepared.');
    $stmt->bind_param('ssii', $key, $value, $secretFlag, $userId);
    if (!$stmt->execute()) throw new RuntimeException('Integration setting write failed.');
}

/** @throws RuntimeException */
function integration_setting_delete(string $key): void
{
    $conn = integration_db();
    if (!$conn) throw new RuntimeException('Database is not available.');
    $stmt = $conn->prepare('DELETE FROM integration_settings WHERE setting_key = ?');
    if (!$stmt) throw new RuntimeException('Integration setting delete could not be prepared.');
    $stmt->bind_param('s', $key);
    if (!$stmt->execute()) throw new RuntimeException('Integration setting delete failed.');
}

function integration_setting_get_json(string $key): ?array
{
    $raw = integration_setting_get($key);
    if ($raw === null) return null;
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : null;
}

/* ------------------------------------------------------------------ */
/* xKiro: katalog model & resolver                                     */
/* ------------------------------------------------------------------ */

/** Daftar model xKiro yang sudah diverifikasi dan boleh dipilih Admin. */
function integration_xkiro_models(): array
{
    return [
        'mistral_small_4' => [
            'label' => 'Mistral Small 4',
            'model_id' => 'mistralai/mistral-small-2603',
            'source' => 'catalog',
            'is_default' => true,
            'context' => '256K (katalog xKiro)',
            'note' => 'Model utama xKiro yang sudah diuji dengan API Key environment.',
        ],
        'command_a_plus' => [
            'label' => 'Command A+',
            'model_id' => 'cohere/command-a-plus',
            'source' => 'catalog',
            'is_default' => false,
            'context' => '436K (katalog xKiro)',
            'note' => 'Model alternatif xKiro yang sudah diuji dengan API Key environment.',
        ],
    ];
}

function integration_xkiro_active_slug(): string
{
    $slug = integration_setting_get('xkiro.active_model');
    $models = integration_xkiro_models();
    return (is_string($slug) && isset($models[$slug])) ? $slug : 'mistral_small_4';
}

/** API key xKiro tersimpan di database (sudah didekripsi), atau null. */
function integration_xkiro_db_key(): ?string
{
    $packed = integration_setting_get('xkiro.api_key');
    if ($packed === null) return null;
    $plain = integration_decrypt($packed, 'xkiro.api_key');
    if ($plain === null) {
        error_log('Integration xKiro key could not be decrypted; environment key will be used.');
        return null;
    }
    $plain = trim($plain);
    return $plain !== '' ? $plain : null;
}

function integration_xkiro_env_key(): string
{
    $key = getenv('XKIRO_API_KEY');
    return is_string($key) ? trim($key) : '';
}

/**
 * Dipanggil oleh includes/ai_service.php. Mengembalikan API key dan Model ID xKiro.
 * Urutan: konfigurasi database (jika ada & valid) → environment (perilaku lama).
 */
function integration_xkiro_resolve(): array
{
    static $cache = null;
    if ($cache !== null) return $cache;

    $envKey = integration_xkiro_env_key();
    $models = integration_xkiro_models();
    $defaultModel = $models['mistral_small_4']['model_id'];
    $result = ['key' => $envKey, 'model' => $defaultModel];

    try {
        if (!integration_tables_ready()) return $cache = $result;

        $dbKey = integration_xkiro_db_key();
        if ($dbKey !== null) $result['key'] = $dbKey;

        $slug = integration_setting_get('xkiro.active_model');
        if (is_string($slug) && isset($models[$slug]) && $models[$slug]['model_id'] !== '') {
            $result['model'] = $models[$slug]['model_id'];
        }
    } catch (Throwable $e) {
        error_log('Integration xKiro resolve failed; environment configuration will be used.');
        $result = ['key' => $envKey, 'model' => $defaultModel];
    }
    return $cache = $result;
}

/** Status API key untuk UI. Tidak pernah mengembalikan nilai key. */
function integration_xkiro_key_status(): array
{
    $packed = integration_tables_ready() ? integration_setting_get('xkiro.api_key') : null;
    $dbKey = $packed !== null ? integration_xkiro_db_key() : null;
    $envKey = integration_xkiro_env_key();
    $meta = integration_tables_ready() ? integration_setting_get_json('xkiro.api_key_meta') : null;

    if ($dbKey !== null) {
        return [
            'source' => 'database',
            'configured' => true,
            'hint' => (string)($meta['hint'] ?? ''),
            'updated_at' => (string)($meta['updated_at'] ?? ''),
            'db_unreadable' => false,
            'env_available' => $envKey !== '',
        ];
    }
    return [
        'source' => $envKey !== '' ? 'environment' : 'none',
        'configured' => $envKey !== '',
        'hint' => $envKey !== '' ? integration_secret_hint($envKey) : '',
        'updated_at' => '',
        'db_unreadable' => $packed !== null,
        'env_available' => $envKey !== '',
    ];
}

/** GET sederhana (tanpa kredensial) untuk katalog publik xKiro. */
function integration_http_get_json(string $url, int $timeout = 10): ?array
{
    if (!function_exists('curl_init')) return null;
    $ch = curl_init($url);
    if ($ch === false) return null;
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
    ]);
    $body = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $errno = curl_errno($ch);
    curl_close($ch);
    if (!is_string($body) || $errno !== 0 || $status < 200 || $status >= 300) return null;
    $json = json_decode($body, true);
    return is_array($json) ? $json : null;
}

/** Cek apakah Model ID ada di katalog publik GET /v1/models. found=null bila katalog tidak dapat diakses. */
function integration_xkiro_catalog_lookup(string $modelId): array
{
    $json = integration_http_get_json(INTEGRATION_XKIRO_BASE_URL . '/models');
    if (!is_array($json) || !isset($json['data']) || !is_array($json['data'])) {
        return ['found' => null, 'context' => null];
    }
    foreach ($json['data'] as $item) {
        if (is_array($item) && ($item['id'] ?? null) === $modelId) {
            $context = $item['context_length'] ?? $item['context_window'] ?? ($item['top_provider']['context_length'] ?? null);
            return ['found' => true, 'context' => is_numeric($context) ? (int)$context : null];
        }
    }
    return ['found' => false, 'context' => null];
}

/**
 * Uji kompatibilitas model dengan mekanisme chatbot existing.
 * Request meniru ai_service.php: chat completions, messages system+user, temperature 0.2,
 * max_tokens 1024, stream false, ekstraksi choices[0].message.content, batas waktu produksi 12 detik.
 * Membutuhkan ai_http_request() dari includes/ai_service.php.
 */
function integration_xkiro_test(string $apiKey, string $modelId): array
{
    $result = [
        'ok' => false,
        'checked_at' => gmdate('Y-m-d H:i:s'),
        'model_id' => $modelId,
        'http_status' => 0,
        'failure' => 'not_run',
        'duration_ms' => null,
        'catalog_found' => null,
        'catalog_context' => null,
        'finish_reason' => null,
    ];
    if (!function_exists('ai_http_request')) {
        $result['failure'] = 'ai_service_unavailable';
        return $result;
    }
    if ($apiKey === '' || $modelId === '') {
        $result['failure'] = $apiKey === '' ? 'missing_key' : 'missing_model';
        return $result;
    }

    $catalog = integration_xkiro_catalog_lookup($modelId);
    $result['catalog_found'] = $catalog['found'];
    $result['catalog_context'] = $catalog['context'];

    $body = json_encode([
        'model' => $modelId,
        'messages' => [
            ['role' => 'system', 'content' => 'Kamu asisten uji koneksi. Jawab sangat singkat.'],
            ['role' => 'user', 'content' => 'Balas hanya dengan kata: OK'],
        ],
        'temperature' => 0.2,
        'max_tokens' => 1024,
        'stream' => false,
    ]);
    if ($body === false) {
        $result['failure'] = 'invalid_request';
        return $result;
    }

    $http = ai_http_request(
        INTEGRATION_XKIRO_BASE_URL . '/chat/completions',
        ['Content-Type: application/json', 'Authorization: Bearer ' . $apiKey],
        $body,
        5,
        25
    );
    $status = (int)($http['http_status'] ?? 0);
    $result['http_status'] = $status;
    $result['duration_ms'] = isset($http['total_time_ms']) ? (int)$http['total_time_ms'] : null;

    if (in_array($http['failure_category'] ?? null, ['timeout', 'curl_error'], true)) {
        $result['failure'] = (string)$http['failure_category'];
        return $result;
    }
    if ($status < 200 || $status >= 300) {
        $result['failure'] = match (true) {
            in_array($status, [401, 403], true) => 'authentication_error',
            $status === 402 => 'payment_required',
            $status === 404 => 'model_not_found',
            $status === 429 => 'rate_limited',
            $status >= 500 => 'provider_server_error',
            default => 'http_error',
        };
        return $result;
    }
    $json = json_decode((string)($http['body'] ?? ''), true);
    if (!is_array($json)) {
        $result['failure'] = 'invalid_json';
        return $result;
    }
    $content = $json['choices'][0]['message']['content'] ?? null;
    $result['finish_reason'] = isset($json['choices'][0]['finish_reason']) ? (string)$json['choices'][0]['finish_reason'] : null;
    if (!is_string($content) || trim($content) === '') {
        $result['failure'] = 'missing_content';
        return $result;
    }
    if ($result['duration_ms'] !== null && $result['duration_ms'] > 12000) {
        // ai_service.php memakai timeout 12 detik; respons lebih lambat akan gagal di produksi.
        $result['failure'] = 'too_slow_for_production_timeout';
        return $result;
    }
    // Catalog lookup bersifat informasi; keberhasilan chat completion adalah bukti utama.
    $result['ok'] = true;
    $result['failure'] = 'none';
    return $result;
}

function integration_xkiro_failure_message(string $failure): string
{
    return match ($failure) {
        'missing_key' => 'API Key xKiro belum tersedia.',
        'missing_model' => 'Model ID belum tersedia pada konfigurasi xKiro.',
        'authentication_error' => 'API Key ditolak oleh xKiro (tidak valid atau tidak memiliki akses).',
        'payment_required' => 'xKiro menolak permintaan karena saldo/paket tidak mencukupi.',
        'model_not_found', 'model_not_in_catalog' => 'Model ID tidak ditemukan di katalog xKiro.',
        'rate_limited' => 'xKiro sedang membatasi permintaan (rate limit). Coba beberapa saat lagi.',
        'provider_server_error' => 'Server xKiro sedang bermasalah. Coba beberapa saat lagi.',
        'timeout', 'curl_error' => 'Tidak dapat terhubung ke xKiro (timeout/jaringan).',
        'invalid_json', 'missing_content' => 'Format respons model tidak sesuai dengan yang dibutuhkan chatbot.',
        'too_slow_for_production_timeout' => 'Model merespons lebih dari 12 detik; chatbot existing akan menganggapnya gagal.',
        'ai_service_unavailable' => 'Layanan AI internal tidak termuat.',
        default => 'Validasi xKiro gagal. Konfigurasi sebelumnya tetap dipakai.',
    };
}

/* ------------------------------------------------------------------ */
/* Fonnte: profil device & resolver                                    */
/* ------------------------------------------------------------------ */

/** Normalisasi nomor pengirim ke format internasional tanpa '+', contoh 6281234567890. */
function integration_fonnte_normalize_number(string $input): ?string
{
    $input = trim($input);
    if ($input === '' || preg_match('/[^0-9+().\s-]/', $input)) return null;
    $digits = preg_replace('/[+().\s-]/', '', $input);
    if (!is_string($digits) || $digits === '') return null;
    if (str_starts_with($digits, '0')) $digits = '62' . substr($digits, 1);
    if (!preg_match('/\A[1-9][0-9]{7,14}\z/', $digits)) return null;
    return $digits;
}

function integration_fonnte_env_token(): string
{
    $token = getenv('FONNTE_API_KEY');
    return is_string($token) ? trim($token) : '';
}

/**
 * Cek device lewat endpoint resmi Fonnte POST https://api.fonnte.com/device
 * dengan header Authorization berisi DEVICE TOKEN. Endpoint ini tidak mengirim pesan WhatsApp.
 */
function integration_fonnte_check_token(string $token): array
{
    $result = [
        'ok' => false,
        'status' => 'error',
        'device' => null,
        'package' => null,
        'quota' => null,
        'expired' => null,
        'reason' => 'not_run',
    ];
    if (!function_exists('curl_init')) {
        $result['reason'] = 'curl_unavailable';
        return $result;
    }
    $curl = curl_init('https://api.fonnte.com/device');
    if ($curl === false) {
        $result['reason'] = 'curl_unavailable';
        return $result;
    }
    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => [],
        CURLOPT_HTTPHEADER => ['Authorization: ' . $token],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    $response = curl_exec($curl);
    $httpCode = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $curlError = curl_errno($curl);
    curl_close($curl);

    if (!is_string($response) || $curlError !== 0 || $httpCode < 200 || $httpCode >= 300) {
        error_log('Fonnte device check failed at transport level.');
        $result['reason'] = 'transport_error';
        return $result;
    }
    $decoded = json_decode($response, true);
    if (!is_array($decoded)) {
        $result['reason'] = 'invalid_json';
        return $result;
    }
    if (($decoded['status'] ?? false) !== true) {
        $result['status'] = 'invalid';
        $result['reason'] = 'token_invalid';
        return $result;
    }
    $deviceStatus = strtolower((string)($decoded['device_status'] ?? ''));
    $rawDevice = isset($decoded['device']) ? (string)$decoded['device'] : '';
    $normalizedDevice = $rawDevice !== '' ? integration_fonnte_normalize_number($rawDevice) : null;
    $result['device'] = $normalizedDevice ?? ($rawDevice !== '' ? preg_replace('/[^0-9]/', '', $rawDevice) : null);
    $result['package'] = isset($decoded['package']) ? substr((string)$decoded['package'], 0, 50) : null;
    $result['quota'] = isset($decoded['quota']) ? substr((string)$decoded['quota'], 0, 30) : null;
    $result['expired'] = isset($decoded['expired']) ? substr((string)$decoded['expired'], 0, 50) : null;
    $result['status'] = $deviceStatus === 'connect' ? 'connect' : ($deviceStatus === 'disconnect' ? 'disconnect' : 'unknown');
    $result['ok'] = true;
    $result['reason'] = 'none';
    return $result;
}

function integration_fonnte_devices(): array
{
    $conn = integration_db();
    if (!$conn || !integration_tables_ready()) return [];
    try {
        $res = $conn->query('SELECT id, device_name, sender_number, token_hint, (token_ciphertext IS NOT NULL) AS has_token, is_enabled, last_check_status, last_check_device, last_check_package, last_check_quota, last_check_expired, last_check_at, updated_at FROM integration_fonnte_devices ORDER BY id ASC');
        return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    } catch (Throwable $e) {
        error_log('Integration Fonnte device list failed.');
        return [];
    }
}

function integration_fonnte_device(int $id): ?array
{
    $conn = integration_db();
    if (!$conn) return null;
    try {
        $stmt = $conn->prepare('SELECT id, device_name, sender_number, token_ciphertext, token_hint, is_enabled, last_check_status, last_check_device FROM integration_fonnte_devices WHERE id = ? LIMIT 1');
        if (!$stmt) return null;
        $stmt->bind_param('i', $id);
        if (!$stmt->execute()) return null;
        $row = $stmt->get_result()->fetch_assoc();
        return $row ?: null;
    } catch (Throwable $e) {
        error_log('Integration Fonnte device lookup failed.');
        return null;
    }
}

function integration_fonnte_device_token_from_row(array $row): ?string
{
    $plain = integration_decrypt($row['token_ciphertext'] ?? null, 'fonnte.device_token');
    if ($plain === null) return null;
    $plain = trim($plain);
    return $plain !== '' ? $plain : null;
}

function integration_fonnte_active_id(): ?int
{
    $raw = integration_setting_get('fonnte.active_device_id');
    return ($raw !== null && ctype_digit($raw) && (int)$raw > 0) ? (int)$raw : null;
}

/**
 * Dipanggil oleh includes/otp_service.php. Token device pengirim aktif, atau null
 * (null → otp_service memakai FONNTE_API_KEY dari environment seperti sebelumnya).
 */
function integration_fonnte_active_token(): ?string
{
    static $cache = false;
    if ($cache !== false) return $cache;
    try {
        if (!integration_tables_ready()) return $cache = null;
        $id = integration_fonnte_active_id();
        if ($id === null) return $cache = null;
        $row = integration_fonnte_device($id);
        if (!$row || (int)$row['is_enabled'] !== 1) {
            error_log('Integration Fonnte active device is unavailable; environment token will be used.');
            return $cache = null;
        }
        $token = integration_fonnte_device_token_from_row($row);
        if ($token === null) {
            error_log('Integration Fonnte device token could not be decrypted; environment token will be used.');
        }
        return $cache = $token;
    } catch (Throwable $e) {
        error_log('Integration Fonnte resolve failed; environment token will be used.');
        return $cache = null;
    }
}

/** Simpan hasil cek koneksi (tanpa token). @throws RuntimeException */
function integration_fonnte_store_check(int $id, array $check): void
{
    $conn = integration_db();
    if (!$conn) throw new RuntimeException('Database is not available.');
    $status = (string)$check['status'];
    $device = $check['device'];
    $package = $check['package'];
    $quota = $check['quota'];
    $expired = $check['expired'];
    $stmt = $conn->prepare('UPDATE integration_fonnte_devices SET last_check_status = ?, last_check_device = ?, last_check_package = ?, last_check_quota = ?, last_check_expired = ?, last_check_at = UTC_TIMESTAMP() WHERE id = ?');
    if (!$stmt) throw new RuntimeException('Fonnte check update could not be prepared.');
    $stmt->bind_param('sssssi', $status, $device, $package, $quota, $expired, $id);
    if (!$stmt->execute()) throw new RuntimeException('Fonnte check update failed.');
}

function integration_fonnte_failure_message(array $check, ?string $expectedNumber = null): string
{
    if (($check['reason'] ?? '') === 'token_invalid') return 'Device Token ditolak Fonnte (token tidak valid). Pastikan memakai Device Token, bukan Account Token.';
    if (($check['reason'] ?? '') === 'transport_error') return 'Tidak dapat terhubung ke Fonnte (jaringan/timeout).';
    if (($check['reason'] ?? '') === 'invalid_json') return 'Respons Fonnte tidak dapat dibaca.';
    if (($check['reason'] ?? '') === 'curl_unavailable') return 'Ekstensi cURL PHP tidak tersedia.';
    if (($check['status'] ?? '') === 'disconnect') return 'Device terdaftar tetapi sedang tidak terhubung (disconnect). Hubungkan/scan ulang di dashboard Fonnte.';
    if ($expectedNumber !== null && ($check['device'] ?? null) !== null && $check['device'] !== $expectedNumber) {
        return 'Nomor device di Fonnte (' . $check['device'] . ') tidak sama dengan nomor pengirim yang diisi.';
    }
    return 'Validasi device Fonnte gagal. Konfigurasi sebelumnya tetap dipakai.';
}
