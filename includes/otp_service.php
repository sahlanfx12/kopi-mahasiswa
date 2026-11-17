<?php
/** Customer WhatsApp OTP service. Secrets are read only from the process environment. */
if (is_file(__DIR__ . '/integration_settings.php')) {
    require_once __DIR__ . '/integration_settings.php';
}

function otp_environment_config(): ?array
{
    $hmacKey = getenv('OTP_HMAC_KEY');
    $fonnteKey = function_exists('integration_fonnte_active_token') ? integration_fonnte_active_token() : null;
    if (!is_string($fonnteKey) || trim($fonnteKey) === '') $fonnteKey = getenv('FONNTE_API_KEY');
    if (!is_string($hmacKey) || trim($hmacKey) === '' || !is_string($fonnteKey) || trim($fonnteKey) === '') return null;
    return ['hmac_key' => $hmacKey, 'fonnte_api_key' => trim($fonnteKey)];
}

function otp_normalize_phone(string $input): ?string
{
    $input = trim($input);
    if ($input === '' || preg_match('/[^0-9+().\s-]/', $input)) return null;
    $digits = preg_replace('/[+().\s-]/', '', $input);
    if (!is_string($digits) || $digits === '') return null;
    if (str_starts_with($digits, '08')) $digits = '62' . substr($digits, 1);
    elseif (str_starts_with($digits, '8')) $digits = '62' . $digits;
    if (!preg_match('/\A628[0-9]{8,11}\z/', $digits)) return null;
    return $digits;
}

function otp_digest(int $userId, string $phone, string $challengeId, string $otp, string $secret): string
{
    return hash_hmac('sha256', $userId . "\0" . $phone . "\0" . $challengeId . "\0" . $otp, $secret, true);
}

function otp_customer_lock_status(mysqli $conn, int $userId): array
{
    try {
        $stmt = $conn->prepare('SELECT failed_otp_attempts, otp_locked_until, (otp_locked_until IS NOT NULL AND otp_locked_until > UTC_TIMESTAMP()) AS is_locked, GREATEST(0, TIMESTAMPDIFF(SECOND, UTC_TIMESTAMP(), otp_locked_until)) AS retry_after FROM users WHERE id = ?');
        if (!$stmt) throw new RuntimeException('OTP lock lookup could not be prepared.');
        $stmt->bind_param('i', $userId);
        if (!$stmt->execute()) throw new RuntimeException('OTP lock lookup failed.');
        $row = $stmt->get_result()->fetch_assoc();
        if (!$row) return ['locked' => false, 'unavailable' => true, 'retry_after' => 0, 'failed_otp_attempts' => 0, 'locked_until' => null];

        $failedAttempts = (int)$row['failed_otp_attempts'];
        if ((int)$row['is_locked'] === 1) {
            return ['locked' => true, 'unavailable' => false, 'retry_after' => max(1, (int)$row['retry_after']), 'failed_otp_attempts' => $failedAttempts, 'locked_until' => (string)$row['otp_locked_until']];
        }

        if ($row['otp_locked_until'] !== null && $row['otp_locked_until'] !== '') {
            $reset = $conn->prepare('UPDATE users SET failed_otp_attempts = 0, otp_locked_until = NULL WHERE id = ? AND otp_locked_until IS NOT NULL AND otp_locked_until <= UTC_TIMESTAMP()');
            if (!$reset) throw new RuntimeException('Expired OTP lock reset could not be prepared.');
            $reset->bind_param('i', $userId);
            if (!$reset->execute()) throw new RuntimeException('Expired OTP lock reset failed.');
            $failedAttempts = 0;
        }

        return ['locked' => false, 'unavailable' => false, 'retry_after' => 0, 'failed_otp_attempts' => $failedAttempts, 'locked_until' => null];
    } catch (Throwable $e) {
        error_log('OTP lock status lookup failed.');
        return ['locked' => false, 'unavailable' => true, 'retry_after' => 0, 'failed_otp_attempts' => 0, 'locked_until' => null];
    }
}

/** Call only while the caller owns an open transaction. */
function otp_issue_customer_in_transaction(mysqli $conn, int $userId, string $phone, string $hmacKey): array
{
    $userStmt = $conn->prepare("SELECT u.phone, u.status, u.phone_verified_at, u.failed_otp_attempts, u.otp_locked_until, r.name AS role_name FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = ? FOR UPDATE");
    if (!$userStmt) throw new RuntimeException('OTP account lookup could not be prepared.');
    $userStmt->bind_param('i', $userId);
    if (!$userStmt->execute()) throw new RuntimeException('OTP account lookup failed.');
    $user = $userStmt->get_result()->fetch_assoc();
    if (!$user || strcasecmp((string)$user['role_name'], 'Customer') !== 0 || $user['status'] !== 'active' || (string)$user['phone'] !== $phone || $user['phone_verified_at'] !== null) {
        throw new RuntimeException('OTP account is not eligible.');
    }

    $lockedUntil = $user['otp_locked_until'] ?? null;
    if ($lockedUntil !== null && $lockedUntil !== '') {
        $lockAt = new DateTimeImmutable((string)$lockedUntil, new DateTimeZone('UTC'));
        $nowUtc = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        if ($lockAt > $nowUtc) throw new DomainException('locked:' . max(1, $lockAt->getTimestamp() - $nowUtc->getTimestamp()));
        $reset = $conn->prepare('UPDATE users SET failed_otp_attempts = 0, otp_locked_until = NULL WHERE id = ? AND otp_locked_until IS NOT NULL AND otp_locked_until <= UTC_TIMESTAMP()');
        if (!$reset) throw new RuntimeException('Expired OTP lock reset could not be prepared.');
        $reset->bind_param('i', $userId);
        if (!$reset->execute()) throw new RuntimeException('Expired OTP lock reset failed.');
    }

    $rateInsert = $conn->prepare('INSERT INTO customer_otp_rate_limits (user_id) VALUES (?) ON DUPLICATE KEY UPDATE user_id = ?');
    if (!$rateInsert) throw new RuntimeException('OTP rate limit could not be prepared.');
    $rateInsert->bind_param('ii', $userId, $userId);
    if (!$rateInsert->execute()) throw new RuntimeException('OTP rate limit initialization failed.');

    $rateSelect = $conn->prepare('SELECT send_1_at, send_2_at, send_3_at, send_4_at, send_5_at, last_send_at FROM customer_otp_rate_limits WHERE user_id = ? FOR UPDATE');
    if (!$rateSelect) throw new RuntimeException('OTP rate limit lookup could not be prepared.');
    $rateSelect->bind_param('i', $userId);
    if (!$rateSelect->execute()) throw new RuntimeException('OTP rate limit lookup failed.');
    $rate = $rateSelect->get_result()->fetch_assoc();
    if (!$rate) throw new RuntimeException('OTP rate limit row is unavailable.');

    $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
    if (!empty($rate['last_send_at'])) {
        $lastSend = new DateTimeImmutable((string)$rate['last_send_at'], new DateTimeZone('UTC'));
        $cooldownRemaining = 60 - ($now->getTimestamp() - $lastSend->getTimestamp());
        if ($cooldownRemaining > 0) throw new DomainException('cooldown:' . $cooldownRemaining);
    }

    $recent = [];
    $cutoff = $now->getTimestamp() - 3600;
    foreach (['send_1_at', 'send_2_at', 'send_3_at', 'send_4_at', 'send_5_at'] as $slot) {
        if (empty($rate[$slot])) continue;
        $sentAt = new DateTimeImmutable((string)$rate[$slot], new DateTimeZone('UTC'));
        if ($sentAt->getTimestamp() > $cutoff && $sentAt->getTimestamp() <= $now->getTimestamp()) $recent[] = $sentAt->format('Y-m-d H:i:s');
    }
    if (count($recent) >= 5) throw new DomainException('hourly_limit');
    $recent[] = $now->format('Y-m-d H:i:s');
    sort($recent, SORT_STRING);
    $recent = array_slice($recent, -5);
    $slots = array_pad($recent, 5, null);

    $rateUpdate = $conn->prepare('UPDATE customer_otp_rate_limits SET send_1_at = ?, send_2_at = ?, send_3_at = ?, send_4_at = ?, send_5_at = ?, last_send_at = ? WHERE user_id = ?');
    if (!$rateUpdate) throw new RuntimeException('OTP rate limit update could not be prepared.');
    $slot1 = $slots[0]; $slot2 = $slots[1]; $slot3 = $slots[2]; $slot4 = $slots[3]; $slot5 = $slots[4]; $lastSendAt = $now->format('Y-m-d H:i:s');
    $rateUpdate->bind_param('ssssssi', $slot1, $slot2, $slot3, $slot4, $slot5, $lastSendAt, $userId);
    if (!$rateUpdate->execute()) throw new RuntimeException('OTP rate limit update failed.');

    $otp = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $challengeId = random_bytes(16);
    $digest = otp_digest($userId, $phone, $challengeId, $otp, $hmacKey);

    $deleteOld = $conn->prepare('DELETE FROM customer_phone_otps WHERE user_id = ?');
    if (!$deleteOld) throw new RuntimeException('OTP challenge replacement could not be prepared.');
    $deleteOld->bind_param('i', $userId);
    if (!$deleteOld->execute()) throw new RuntimeException('OTP challenge replacement failed.');

    $insertOtp = $conn->prepare('INSERT INTO customer_phone_otps (user_id, challenge_id, phone_snapshot, otp_hash, expires_at, attempts, created_at) VALUES (?, ?, ?, ?, DATE_ADD(UTC_TIMESTAMP(), INTERVAL 5 MINUTE), 0, UTC_TIMESTAMP())');
    if (!$insertOtp) throw new RuntimeException('OTP challenge insert could not be prepared.');
    $insertOtp->bind_param('isss', $userId, $challengeId, $phone, $digest);
    if (!$insertOtp->execute()) throw new RuntimeException('OTP challenge insert failed.');

    return ['otp' => $otp, 'challenge_id' => $challengeId, 'challenge_hex' => bin2hex($challengeId), 'phone' => $phone];
}

function otp_issue_customer(mysqli $conn, int $userId, string $hmacKey): array
{
    try {
        if (!$conn->begin_transaction()) return ['ok' => false, 'reason' => 'unavailable'];
        $accountStmt = $conn->prepare("SELECT u.phone, u.status, u.phone_verified_at, r.name AS role_name FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = ? FOR UPDATE");
        if (!$accountStmt) throw new RuntimeException('OTP account lookup could not be prepared.');
        $accountStmt->bind_param('i', $userId);
        if (!$accountStmt->execute()) throw new RuntimeException('OTP account lookup failed.');
        $account = $accountStmt->get_result()->fetch_assoc();
        if (!$account || strcasecmp((string)$account['role_name'], 'Customer') !== 0 || $account['status'] !== 'active' || $account['phone_verified_at'] !== null) {
            $conn->rollback();
            return ['ok' => false, 'reason' => 'unavailable'];
        }
        $phone = otp_normalize_phone((string)$account['phone']);
        if ($phone === null || $phone !== (string)$account['phone']) {
            $conn->rollback();
            return ['ok' => false, 'reason' => 'phone_invalid'];
        }
        $challenge = otp_issue_customer_in_transaction($conn, $userId, $phone, $hmacKey);
        if (!$conn->commit()) throw new RuntimeException('OTP challenge transaction could not commit.');
        return ['ok' => true] + $challenge;
    } catch (DomainException $e) {
        $conn->rollback();
        if (str_starts_with($e->getMessage(), 'cooldown:')) return ['ok' => false, 'reason' => 'cooldown', 'retry_after' => max(1, (int)substr($e->getMessage(), 9))];
        if (str_starts_with($e->getMessage(), 'locked:')) return ['ok' => false, 'reason' => 'locked', 'retry_after' => max(1, (int)substr($e->getMessage(), 7))];
        return ['ok' => false, 'reason' => 'hourly_limit'];
    } catch (Throwable $e) {
        $conn->rollback();
        error_log('OTP challenge issue failed.');
        return ['ok' => false, 'reason' => 'unavailable'];
    }
}

function otp_send_fonnte(string $apiKey, string $phone, string $otp): bool
{
    if (!function_exists('curl_init')) return false;
    $message = 'Kode verifikasi Kopi Mahasiswa Anda: ' . $otp . '. Kode berlaku 5 menit. Jangan bagikan kode ini kepada siapa pun.';
    $curl = curl_init('https://api.fonnte.com/send');
    if ($curl === false) return false;
    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => ['target' => $phone, 'message' => $message],
        CURLOPT_HTTPHEADER => ['Authorization: ' . $apiKey],
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
        error_log('Fonnte OTP request failed at transport level.');
        return false;
    }
    $decoded = json_decode($response, true);
    $accepted = is_array($decoded) && (($decoded['status'] ?? $decoded['Status'] ?? false) === true);
    if (!$accepted) error_log('Fonnte OTP request was rejected by the API.');
    return $accepted;
}

function otp_delete_matching_challenge(mysqli $conn, int $userId, string $challengeId): bool
{
    try {
        $stmt = $conn->prepare('DELETE FROM customer_phone_otps WHERE user_id = ? AND challenge_id = ?');
        if (!$stmt) return false;
        $stmt->bind_param('is', $userId, $challengeId);
        return $stmt->execute();
    } catch (Throwable $e) {
        error_log('OTP challenge cleanup failed.');
        return false;
    }
}

function otp_current_challenge_hex(mysqli $conn, int $userId): ?string
{
    try {
        $stmt = $conn->prepare('SELECT challenge_id FROM customer_phone_otps WHERE user_id = ? LIMIT 1');
        if (!$stmt) return null;
        $stmt->bind_param('i', $userId);
        if (!$stmt->execute()) return null;
        $row = $stmt->get_result()->fetch_assoc();
        return $row ? bin2hex((string)$row['challenge_id']) : null;
    } catch (Throwable $e) {
        error_log('OTP challenge lookup failed.');
        return null;
    }
}

function otp_challenge_remaining_seconds(mysqli $conn, int $userId, string $challengeHex): ?int
{
    $challengeId = ctype_xdigit($challengeHex) && strlen($challengeHex) === 32 ? hex2bin($challengeHex) : false;
    if (!is_string($challengeId)) return null;
    try {
        $stmt = $conn->prepare('SELECT GREATEST(0, TIMESTAMPDIFF(SECOND, UTC_TIMESTAMP(), expires_at)) AS remaining FROM customer_phone_otps WHERE user_id = ? AND challenge_id = ? LIMIT 1');
        if (!$stmt) return null;
        $stmt->bind_param('is', $userId, $challengeId);
        if (!$stmt->execute()) return null;
        $row = $stmt->get_result()->fetch_assoc();
        return $row ? (int)$row['remaining'] : null;
    } catch (Throwable $e) {
        error_log('OTP expiry lookup failed.');
        return null;
    }
}

function otp_verify_customer(mysqli $conn, int $userId, string $challengeHex, string $candidate, string $hmacKey): string
{
    $expectedChallenge = ctype_xdigit($challengeHex) && strlen($challengeHex) === 32 ? hex2bin($challengeHex) : false;
    if (!is_string($expectedChallenge) || !preg_match('/\A[0-9]{6}\z/', $candidate)) return 'invalid';
    $transactionStarted = false;
    try {
        if (!$conn->begin_transaction()) return 'unavailable';
        $transactionStarted = true;
        $accountStmt = $conn->prepare('SELECT u.phone, u.status, u.phone_verified_at, u.failed_otp_attempts, u.otp_locked_until, r.name AS role_name FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = ? FOR UPDATE');
        if (!$accountStmt) throw new RuntimeException('OTP verification account lookup could not be prepared.');
        $accountStmt->bind_param('i', $userId);
        if (!$accountStmt->execute()) throw new RuntimeException('OTP verification account lookup failed.');
        $account = $accountStmt->get_result()->fetch_assoc();
        if (!$account || strcasecmp((string)$account['role_name'], 'Customer') !== 0 || $account['status'] !== 'active') {
            $conn->rollback(); return 'unavailable';
        }
        if ($account['phone_verified_at'] !== null) { $conn->commit(); return 'already_verified'; }

        $lockedUntil = $account['otp_locked_until'] ?? null;
        if ($lockedUntil !== null && $lockedUntil !== '') {
            $lockAt = new DateTimeImmutable((string)$lockedUntil, new DateTimeZone('UTC'));
            $nowUtc = new DateTimeImmutable('now', new DateTimeZone('UTC'));
            if ($lockAt > $nowUtc) { $conn->rollback(); return 'locked'; }
            $reset = $conn->prepare('UPDATE users SET failed_otp_attempts = 0, otp_locked_until = NULL WHERE id = ? AND otp_locked_until IS NOT NULL AND otp_locked_until <= UTC_TIMESTAMP()');
            if (!$reset) throw new RuntimeException('Expired OTP lock reset could not be prepared.');
            $reset->bind_param('i', $userId);
            if (!$reset->execute()) throw new RuntimeException('Expired OTP lock reset failed.');
            $account['failed_otp_attempts'] = 0;
        }

        $otpStmt = $conn->prepare('SELECT challenge_id, phone_snapshot, otp_hash, attempts, (expires_at <= UTC_TIMESTAMP()) AS expired FROM customer_phone_otps WHERE user_id = ? FOR UPDATE');
        if (!$otpStmt) throw new RuntimeException('OTP verification challenge lookup could not be prepared.');
        $otpStmt->bind_param('i', $userId);
        if (!$otpStmt->execute()) throw new RuntimeException('OTP verification challenge lookup failed.');
        $challenge = $otpStmt->get_result()->fetch_assoc();
        if (!$challenge) { $conn->rollback(); return 'expired'; }
        if (!hash_equals(bin2hex((string)$challenge['challenge_id']), $challengeHex)) { $conn->rollback(); return 'replaced'; }
        if ((int)$challenge['expired'] === 1) { $conn->rollback(); return 'expired'; }
        if ((int)$challenge['attempts'] >= 5) { $conn->rollback(); return 'attempts_exhausted'; }
        if (!is_string($account['phone']) || $account['phone'] !== $challenge['phone_snapshot']) {
            $delete = $conn->prepare('DELETE FROM customer_phone_otps WHERE user_id = ? AND challenge_id = ?');
            if (!$delete) throw new RuntimeException('Stale OTP cleanup could not be prepared.');
            $delete->bind_param('is', $userId, $expectedChallenge);
            if (!$delete->execute()) throw new RuntimeException('Stale OTP cleanup failed.');
            if (!$conn->commit()) throw new RuntimeException('Stale OTP cleanup could not commit.');
            $transactionStarted = false;
            return 'phone_changed';
        }

        $computed = otp_digest($userId, (string)$account['phone'], (string)$challenge['challenge_id'], $candidate, $hmacKey);
        if (!hash_equals((string)$challenge['otp_hash'], $computed)) {
            $failures = (int)($account['failed_otp_attempts'] ?? 0) + 1;
            if ($failures >= 2) {
                $deleteChallenge = $conn->prepare('DELETE FROM customer_phone_otps WHERE user_id = ?');
                if (!$deleteChallenge) throw new RuntimeException('OTP lock challenge cleanup could not be prepared.');
                $deleteChallenge->bind_param('i', $userId);
                if (!$deleteChallenge->execute()) throw new RuntimeException('OTP lock challenge cleanup failed.');

                $lockUpdate = $conn->prepare('UPDATE users SET failed_otp_attempts = ?, otp_locked_until = DATE_ADD(UTC_TIMESTAMP(), INTERVAL 24 HOUR) WHERE id = ?');
                if (!$lockUpdate) throw new RuntimeException('OTP lock update could not be prepared.');
                $lockUpdate->bind_param('ii', $failures, $userId);
                if (!$lockUpdate->execute()) throw new RuntimeException('OTP lock update failed.');
                if (!$conn->commit()) throw new RuntimeException('OTP lock transaction could not commit.');
                $transactionStarted = false;
                return 'locked';
            }

            $failureUpdate = $conn->prepare('UPDATE users SET failed_otp_attempts = ?, otp_locked_until = NULL WHERE id = ?');
            if (!$failureUpdate) throw new RuntimeException('OTP failure counter update could not be prepared.');
            $failureUpdate->bind_param('ii', $failures, $userId);
            if (!$failureUpdate->execute()) throw new RuntimeException('OTP failure counter update failed.');

            $attemptStmt = $conn->prepare('UPDATE customer_phone_otps SET attempts = attempts + 1 WHERE user_id = ? AND challenge_id = ? AND attempts < 5');
            if (!$attemptStmt) throw new RuntimeException('OTP attempt update could not be prepared.');
            $attemptStmt->bind_param('is', $userId, $expectedChallenge);
            if (!$attemptStmt->execute() || $attemptStmt->affected_rows !== 1) throw new RuntimeException('OTP attempt update failed.');
            if (!$conn->commit()) throw new RuntimeException('OTP attempt transaction could not commit.');
            $transactionStarted = false;
            return 'invalid';
        }

        $verifyStmt = $conn->prepare('UPDATE users SET phone_verified_at = UTC_TIMESTAMP(), failed_otp_attempts = 0, otp_locked_until = NULL WHERE id = ? AND phone_verified_at IS NULL');
        if (!$verifyStmt) throw new RuntimeException('OTP verification update could not be prepared.');
        $verifyStmt->bind_param('i', $userId);
        if (!$verifyStmt->execute() || $verifyStmt->affected_rows !== 1) throw new RuntimeException('OTP verification update failed.');
        $deleteStmt = $conn->prepare('DELETE FROM customer_phone_otps WHERE user_id = ? AND challenge_id = ?');
        if (!$deleteStmt) throw new RuntimeException('OTP challenge deletion could not be prepared.');
        $deleteStmt->bind_param('is', $userId, $expectedChallenge);
        if (!$deleteStmt->execute() || $deleteStmt->affected_rows !== 1) throw new RuntimeException('OTP challenge deletion failed.');
        if (!$conn->commit()) throw new RuntimeException('OTP verification transaction could not commit.');
        $transactionStarted = false;
        return 'verified';
    } catch (Throwable $e) {
        if ($transactionStarted) $conn->rollback();
        error_log('OTP verification transaction failed.');
        return 'unavailable';
    }
}

function otp_clear_temporary_session(): void
{
    unset($_SESSION['otp_pending_user_id'], $_SESSION['otp_pending_challenge_id']);
}
