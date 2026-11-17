<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/otp_service.php';

$error = '';
$notice = (string)($_SESSION['otp_verify_notice'] ?? '');
unset($_SESSION['otp_verify_notice']);
$userId = (int)($_SESSION['otp_pending_user_id'] ?? 0);
$challengeHex = (string)($_SESSION['otp_pending_challenge_id'] ?? '');
$otpLockStatus = ['locked' => false, 'unavailable' => false, 'retry_after' => 0, 'locked_until' => null];
header('Cache-Control: no-store');

if ($userId <= 0) {
    header('Location: ../login.php');
    exit;
}

try {
    $accountStmt = $conn->prepare("SELECT u.phone_verified_at, u.status, r.name AS role_name FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = ? LIMIT 1");
    if (!$accountStmt) throw new RuntimeException('Account lookup unavailable.');
    $accountStmt->bind_param('i', $userId);
    if (!$accountStmt->execute()) throw new RuntimeException('Account lookup unavailable.');
    $account = $accountStmt->get_result()->fetch_assoc();
    if (!$account || strcasecmp((string)$account['role_name'], 'Customer') !== 0 || $account['status'] !== 'active') {
        otp_clear_temporary_session();
        header('Location: ../login.php');
        exit;
    }
    if ($account['phone_verified_at'] !== null) {
        otp_clear_temporary_session();
        $_SESSION['otp_login_notice'] = 'Nomor WhatsApp sudah diverifikasi. Silakan masuk.';
        header('Location: ../login.php');
        exit;
    }
    $otpLockStatus = otp_customer_lock_status($conn, $userId);
    if (!empty($otpLockStatus['unavailable'])) {
        $error = 'Status penguncian OTP tidak dapat diperiksa. Silakan coba lagi nanti.';
    } elseif (!empty($otpLockStatus['locked'])) {
        $error = 'Akun Customer terkunci karena dua kali memasukkan OTP yang salah.';
    }
} catch (Throwable $e) {
    error_log('OTP verification page account lookup failed.');
    $error = 'Layanan verifikasi sedang tidak tersedia. Silakan coba lagi.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $error === '') {
    $action = (string)($_POST['action'] ?? 'verify');
    if ($action === 'resend') {
        $config = otp_environment_config();
        if ($config === null) {
            $error = 'Pengiriman kode sedang tidak tersedia. Silakan coba lagi nanti.';
        } else {
            $issued = otp_issue_customer($conn, $userId, $config['hmac_key']);
            if (empty($issued['ok'])) {
                $error = match ($issued['reason'] ?? '') {
                    'cooldown' => 'Tunggu ' . (int)($issued['retry_after'] ?? 60) . ' detik sebelum mengirim kode lagi.',
                    'hourly_limit' => 'Batas pengiriman kode per jam tercapai. Coba lagi nanti.',
                    'locked' => 'Akun Customer terkunci karena dua kali memasukkan OTP yang salah.',
                    default => 'Kode tidak dapat dikirim saat ini. Silakan coba lagi nanti.',
                };
                if (($issued['reason'] ?? '') === 'locked') $otpLockStatus = otp_customer_lock_status($conn, $userId);
            } else {
                $_SESSION['otp_pending_challenge_id'] = $issued['challenge_hex'];
                if (otp_send_fonnte($config['fonnte_api_key'], $issued['phone'], $issued['otp'])) {
                    $_SESSION['otp_verify_notice'] = 'Jika WhatsApp menerima pesan, masukkan kode terbaru. Kode sebelumnya sudah tidak berlaku.';
                } else {
                    otp_delete_matching_challenge($conn, $userId, $issued['challenge_id']);
                    $_SESSION['otp_verify_notice'] = 'Kode belum dapat dikirim. Batas pengiriman tetap dihitung; coba lagi setelah cooldown.';
                }
                header('Location: verify_otp.php');
                exit;
            }
        }
    } else {
        $config = otp_environment_config();
        if ($config === null) {
            $error = 'Verifikasi sedang tidak tersedia. Silakan coba lagi nanti.';
        } else {
            $result = otp_verify_customer($conn, $userId, $challengeHex, trim((string)($_POST['otp'] ?? '')), $config['hmac_key']);
            if ($result === 'verified' || $result === 'already_verified') {
                otp_clear_temporary_session();
                $_SESSION['otp_login_notice'] = 'Verifikasi berhasil. Silakan masuk dengan akun Anda.';
                header('Location: ../login.php');
                exit;
            }
            $error = match ($result) {
                'invalid' => 'Kode tidak cocok. Tersisa 1 kesempatan.',
                'locked' => 'Akun Customer terkunci karena dua kali memasukkan OTP yang salah.',
                'expired' => 'Kode kedaluwarsa. Kirim kode baru untuk melanjutkan.',
                'replaced' => 'Kode ini sudah diganti. Gunakan kode terbaru.',
                'phone_changed' => 'Nomor akun berubah. Minta kode baru.',
                default => 'Verifikasi tidak dapat diproses saat ini. Silakan coba lagi.',
            };
            if ($result === 'locked') $otpLockStatus = otp_customer_lock_status($conn, $userId);
            if (in_array($result, ['expired', 'replaced', 'phone_changed'], true)) {
                $_SESSION['otp_pending_challenge_id'] = otp_current_challenge_hex($conn, $userId) ?? '';
                $challengeHex = (string)$_SESSION['otp_pending_challenge_id'];
            }
        }
    }
}

$remaining = $challengeHex !== '' ? otp_challenge_remaining_seconds($conn, $userId, $challengeHex) : null;
$csrf = function_exists('csrf_input') ? csrf_input() : '';
$lockUnlockAt = null;
if (!empty($otpLockStatus['locked_until'])) {
    try { $lockUnlockAt = (new DateTimeImmutable((string)$otpLockStatus['locked_until'], new DateTimeZone('UTC')))->format('Y-m-d H:i:s'); } catch (Throwable $e) { $lockUnlockAt = null; }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verifikasi WhatsApp — Kopi Mahasiswa</title>
    <?php require_once __DIR__ . '/../includes/theme_head.php'; ?>
</head>
<body class="min-h-screen bg-[#090c12] text-stone-200 flex items-center justify-center p-4">
    <main class="w-full max-w-md rounded-2xl border border-stone-800 bg-[#0e131d] p-7 shadow-2xl">
        <div class="mb-6 text-center">
            <h1 class="text-2xl font-bold text-white">Verifikasi WhatsApp</h1>
            <p class="mt-2 text-sm text-stone-400">Masukkan kode 6 digit untuk mengaktifkan akun Customer.</p>
        </div>
        <?php if ($notice !== ''): ?><div class="mb-4 rounded-xl border border-emerald-500/30 bg-emerald-500/10 p-3 text-sm text-emerald-300"><?= htmlspecialchars($notice, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
        <?php if ($error !== ''): ?><div class="mb-4 rounded-xl border border-rose-500/30 bg-rose-500/10 p-3 text-sm text-rose-300"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
        <?php if (!empty($otpLockStatus['locked'])): ?>
            <div class="mb-4 rounded-xl border border-amber-500/30 bg-amber-500/10 p-4 text-center text-sm text-amber-200">
                <div class="font-semibold">Verifikasi dan permintaan kode baru dikunci selama 24 jam.</div>
                <?php if ($lockUnlockAt !== null): ?><div class="mt-2 text-xs">Akses dibuka pada <?= htmlspecialchars($lockUnlockAt, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?> UTC.</div><?php endif; ?>
            </div>
        <?php elseif ($remaining !== null && $remaining > 0): ?>
            <p class="mb-4 text-center text-xs text-stone-400">Kode berlaku <span id="otp-countdown"><?= (int)$remaining ?></span> detik.</p>
        <?php elseif ($remaining === null || $remaining <= 0): ?>
            <p class="mb-4 text-center text-xs text-stone-400">Kode aktif tidak tersedia atau sudah kedaluwarsa. Minta kode baru.</p>
        <?php endif; ?>
        <?php if (empty($otpLockStatus['locked']) && empty($otpLockStatus['unavailable'])): ?>
        <form method="post" class="space-y-4">
            <?= $csrf ?>
            <input type="hidden" name="action" value="verify">
            <label for="otp" class="block text-xs font-semibold uppercase tracking-wider text-stone-300">Kode OTP</label>
            <input id="otp" name="otp" type="text" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required class="w-full rounded-xl border border-stone-700 bg-[#131926] px-4 py-3 text-center text-xl tracking-[0.5em] text-white outline-none focus:border-amber-500" aria-label="Kode verifikasi 6 digit">
            <button type="submit" class="w-full rounded-xl bg-amber-500 px-4 py-3 font-bold text-stone-950 hover:bg-amber-400">Verifikasi</button>
        </form>
        <form method="post" class="mt-3">
            <?= $csrf ?>
            <input type="hidden" name="action" value="resend">
            <button type="submit" class="w-full rounded-xl border border-stone-700 px-4 py-3 text-sm font-semibold text-stone-200 hover:border-amber-500">Kirim ulang kode</button>
        </form>
        <?php endif; ?>
        <p class="mt-5 text-center text-xs text-stone-500"><a href="../login.php" class="text-amber-400 hover:underline">Kembali ke halaman masuk</a></p>
    </main>
    <?php if (empty($otpLockStatus['locked']) && $remaining !== null && $remaining > 0): ?>
    <script>
        let seconds = <?= (int)$remaining ?>;
        const countdown = document.getElementById('otp-countdown');
        const timer = window.setInterval(() => {
            seconds = Math.max(0, seconds - 1);
            countdown.textContent = String(seconds);
            if (seconds === 0) window.clearInterval(timer);
        }, 1000);
    </script>
    <?php endif; ?>
</body>
</html>
