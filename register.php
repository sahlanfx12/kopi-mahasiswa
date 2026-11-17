<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require "config.php"; require_once "includes/csrf.php";
require_once "includes/notification_events.php";
require_once "includes/otp_service.php";

// Redirect jika sudah login
if (isset($_SESSION["user_id"])) {
    $role = strtolower($_SESSION["role"]);
    header("Location: $role/");
    exit;
}

$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name     = trim(strip_tags($_POST["name"] ?? ""));
    $email    = trim(strip_tags($_POST["email"] ?? ""));
    $phoneInput = trim((string)($_POST["phone"] ?? ""));
    $phone    = otp_normalize_phone($phoneInput);
    $password = $_POST["password"] ?? "";
    $confirm  = $_POST["confirm_password"] ?? "";

    // Validasi
    if (empty($name) || empty($email) || empty($password) || $phoneInput === "") {
        $error = "Nama, email, nomor WhatsApp, dan password wajib diisi!";
    } elseif ($phone === null) {
        $error = "Format nomor WhatsApp tidak valid.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Format email tidak valid!";
    } elseif (strlen($password) < 8) {
        $error = "Password minimal 8 karakter!";
    } elseif ($password !== $confirm) {
        $error = "Konfirmasi password tidak cocok!";
    } elseif (($otpConfig = otp_environment_config()) === null) {
        $error = "Verifikasi WhatsApp sedang tidak tersedia. Silakan coba lagi nanti.";
    } else {
        // Cek duplikasi email menggunakan prepared statement
        $stmt_check = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt_check->bind_param("s", $email);
        $stmt_check->execute();
        $stmt_check->store_result();

        if ($stmt_check->num_rows > 0) {
            $error = "Email sudah terdaftar! Silakan gunakan email lain.";
        } else {
            $phone_check = $conn->prepare("SELECT id FROM users WHERE phone = ? LIMIT 1");
            if (!$phone_check) {
                $error = "Terjadi kesalahan. Silakan coba lagi.";
            } else {
                $phone_check->bind_param("s", $phone);
                $phone_check->execute();
                $phone_check->store_result();
            }
            if ($error === "" && $phone_check->num_rows > 0) {
                $error = "Nomor WhatsApp sudah digunakan.";
            }
        }

        if ($error === "") {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $role_id = 4; // Customer

            $transaction_started = false;
            $new_user_id = 0;
            $issued_challenge = null;
            try {
                if (!$conn->begin_transaction()) throw new RuntimeException('Registration transaction could not start.');
                $transaction_started = true;
                $stmt_insert = $conn->prepare(
                    "INSERT INTO users (role_id, name, email, phone, password) VALUES (?, ?, ?, ?, ?)"
                );
                if (!$stmt_insert) throw new RuntimeException('Registration insert could not be prepared.');
                $stmt_insert->bind_param("issss", $role_id, $name, $email, $phone, $hashed);
                if (!$stmt_insert->execute()) throw new RuntimeException('Registration insert failed.');
                $new_user_id = (int)$stmt_insert->insert_id;
                if ($new_user_id <= 0 || !notification_event_send($conn, $new_user_id, 'Registrasi berhasil', 'Akun Customer Anda berhasil dibuat.', 'success')) {
                    throw new RuntimeException('Registration notification failed.');
                }
                $issued_challenge = otp_issue_customer_in_transaction($conn, $new_user_id, $phone, $otpConfig['hmac_key']);
                if (!$conn->commit()) throw new RuntimeException('Registration transaction could not commit.');
                $transaction_started = false;
                session_regenerate_id(true);
                $_SESSION['otp_pending_user_id'] = $new_user_id;
                $_SESSION['otp_pending_challenge_id'] = $issued_challenge['challenge_hex'];
                if (otp_send_fonnte($otpConfig['fonnte_api_key'], $phone, $issued_challenge['otp'])) {
                    $_SESSION['otp_verify_notice'] = "Jika WhatsApp menerima pesan, masukkan kode verifikasi untuk mengaktifkan akun.";
                } else {
                    otp_delete_matching_challenge($conn, $new_user_id, $issued_challenge['challenge_id']);
                    $_SESSION['otp_verify_notice'] = "Kode belum dapat dikirim. Silakan gunakan kirim ulang.";
                }
                header("Location: customer/verify_otp.php");
                exit;
            } catch (Throwable $e) {
                if ($transaction_started) $conn->rollback();
                error_log('Customer registration transaction failed.');
                $error = "Terjadi kesalahan. Silakan coba lagi.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Akun — Kopi Mahasiswa</title>
    <?php require_once "includes/theme_head.php"; ?>
</head>
<body class="min-h-screen bg-[#090c12] text-stone-200 flex flex-col items-center justify-between p-4 antialiased selection:bg-amber-500/20 selection:text-amber-300 relative overflow-x-hidden">

    <!-- Theme Toggle (Top Right) -->
    <div class="fixed top-4 right-4 z-40">
        <?php require_once "includes/theme_toggle.php"; ?>
    </div>

    <!-- Ambient glow effects -->
    <div class="fixed inset-0 pointer-events-none overflow-hidden z-0">
        <div class="absolute -top-40 -right-40 w-96 h-96 bg-amber-500/10 rounded-full blur-[120px]"></div>
        <div class="absolute -bottom-40 -left-40 w-96 h-96 bg-amber-700/10 rounded-full blur-[120px]"></div>
    </div>

    <div class="w-full max-w-md my-auto relative z-10 pt-6 pb-4">
        <!-- Brand Header -->
        <div class="text-center mb-6">
            <div class="inline-flex w-14 h-14 rounded-2xl bg-gradient-to-br from-amber-500 to-amber-700 items-center justify-center text-stone-950 shadow-xl shadow-amber-950/50 mb-3 border border-amber-400/30">
                <i data-lucide="coffee" class="w-8 h-8"></i>
            </div>
            <h1 class="text-2xl font-extrabold text-white tracking-tight">Kopi Mahasiswa</h1>
            <p class="text-xs text-stone-400 mt-1">Registrasi Akun Baru Pelanggan</p>
        </div>

        <div class="bg-[#0e131d]/90 backdrop-blur-xl rounded-2xl border border-stone-800/90 shadow-2xl overflow-hidden p-7">
            <?php if ($error): ?>
            <div class="bg-rose-500/10 border border-rose-500/30 text-rose-300 px-4 py-3 rounded-xl text-xs mb-5 flex items-start gap-2.5">
                <i data-lucide="alert-circle" class="w-4 h-4 text-rose-400 flex-shrink-0 mt-0.5"></i>
                <div class="leading-relaxed"><?= htmlspecialchars($error) ?></div>
            </div>
            <?php endif; ?>

            <?php if ($success): ?>
            <div class="bg-emerald-500/10 border border-emerald-500/30 text-emerald-300 px-4 py-3 rounded-xl text-xs mb-5 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <i data-lucide="check-circle" class="w-4 h-4 text-emerald-400 flex-shrink-0"></i>
                    <span><?= htmlspecialchars($success) ?></span>
                </div>
                <a href="login.php" class="text-amber-400 font-bold hover:underline ml-2">Masuk &rarr;</a>
            </div>
            <?php endif; ?>

            <form action="" method="POST" data-loading class="space-y-4">
                <?php if(function_exists("csrf_input")) echo csrf_input(); ?>

                <div>
                    <label class="block text-xs font-semibold text-stone-300 uppercase tracking-wider mb-1.5">Nama Lengkap</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 pointer-events-none text-stone-500">
                            <i data-lucide="user" class="w-4 h-4"></i>
                        </span>
                        <input name="name" type="text" required autocomplete="name"
                               value="<?= htmlspecialchars($_POST["name"] ?? "") ?>"
                               class="w-full bg-[#131926] border border-stone-800 focus:border-amber-500 focus:ring-1 focus:ring-amber-500 rounded-xl pl-10 pr-4 py-2.5 text-sm text-stone-200 placeholder-stone-600 transition-all outline-none"
                               placeholder="Nama lengkap kamu">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-stone-300 uppercase tracking-wider mb-1.5">Email</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 pointer-events-none text-stone-500">
                            <i data-lucide="mail" class="w-4 h-4"></i>
                        </span>
                        <input name="email" type="email" required autocomplete="email"
                               value="<?= htmlspecialchars($_POST["email"] ?? "") ?>"
                               class="w-full bg-[#131926] border border-stone-800 focus:border-amber-500 focus:ring-1 focus:ring-amber-500 rounded-xl pl-10 pr-4 py-2.5 text-sm text-stone-200 placeholder-stone-600 transition-all outline-none"
                               placeholder="nama@email.com">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-stone-300 uppercase tracking-wider mb-1.5">Nomor Handphone</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 pointer-events-none text-stone-500">
                            <i data-lucide="phone" class="w-4 h-4"></i>
                        </span>
                        <input name="phone" type="tel" required autocomplete="tel"
                               value="<?= htmlspecialchars($_POST["phone"] ?? "") ?>"
                               class="w-full bg-[#131926] border border-stone-800 focus:border-amber-500 focus:ring-1 focus:ring-amber-500 rounded-xl pl-10 pr-4 py-2.5 text-sm text-stone-200 placeholder-stone-600 transition-all outline-none"
                               placeholder="08xxxxxxxxxx">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-stone-300 uppercase tracking-wider mb-1.5">
                        Password <span class="text-stone-500 lowercase font-normal">(min. 8 karakter)</span>
                    </label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 pointer-events-none text-stone-500">
                            <i data-lucide="lock" class="w-4 h-4"></i>
                        </span>
                        <input name="password" type="password" required autocomplete="new-password" minlength="8"
                               class="w-full bg-[#131926] border border-stone-800 focus:border-amber-500 focus:ring-1 focus:ring-amber-500 rounded-xl pl-10 pr-4 py-2.5 text-sm text-stone-200 placeholder-stone-600 transition-all outline-none"
                               placeholder="••••••••">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-stone-300 uppercase tracking-wider mb-1.5">Konfirmasi Password</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 pointer-events-none text-stone-500">
                            <i data-lucide="shield-check" class="w-4 h-4"></i>
                        </span>
                        <input name="confirm_password" type="password" required autocomplete="new-password" minlength="8"
                               class="w-full bg-[#131926] border border-stone-800 focus:border-amber-500 focus:ring-1 focus:ring-amber-500 rounded-xl pl-10 pr-4 py-2.5 text-sm text-stone-200 placeholder-stone-600 transition-all outline-none"
                               placeholder="••••••••">
                    </div>
                </div>

                <button type="submit"
                        class="w-full bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-400 hover:to-amber-500 text-stone-950 font-bold py-2.5 rounded-xl shadow-lg shadow-amber-950/40 hover:shadow-amber-500/20 transition-all transform active:scale-[0.98] mt-2 flex items-center justify-center gap-2 text-sm">
                    <i data-lucide="user-plus" class="w-4 h-4"></i> Daftar Sekarang
                </button>
            </form>

            <div class="mt-6 pt-5 border-t border-stone-800/80 text-center">
                <p class="text-xs text-stone-400">
                    Sudah punya akun?
                    <a href="login.php" class="text-amber-400 font-semibold hover:text-amber-300 hover:underline ml-1">Masuk di sini</a>
                </p>
                <div class="mt-3">
                    <a href="index.php" class="inline-flex items-center gap-1.5 text-xs text-stone-500 hover:text-stone-300 transition-colors">
                        <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i> Kembali ke Beranda
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Official Developer Watermark -->
    <footer class="w-full py-4 text-center relative z-20 footer-transparent">
        <div class="text-xs text-stone-400 font-medium tracking-wider app-watermark">
            Built by Sahlan
        </div>
    </footer>

    <script>
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
    </script>
</body>
</html>
