<?php
require "config.php";
require_once "includes/csrf.php";
require_once "includes/otp_service.php";

$valid_roles = ['admin', 'customer', 'kasir', 'supplier', 'owner'];

// Redirect hanya jika sesi valid dan role terdaftar.
if (isset($_SESSION["user_id"])) {
    $user_id = filter_var($_SESSION["user_id"] ?? null, FILTER_VALIDATE_INT);
    $stored_role = strtolower(trim((string)($_SESSION["role"] ?? '')));

    if ($user_id !== false && in_array($stored_role, $valid_roles, true) && isset($_SESSION["role_id"])) {
        $stmt = $conn->prepare(
            "SELECT LOWER(r.name) AS role_name FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = ? LIMIT 1"
        );

        if ($stmt) {
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($row && isset($row['role_name']) && in_array((string)$row['role_name'], $valid_roles, true) && (string)$row['role_name'] === $stored_role) {
                header("Location: {$stored_role}/");
                exit;
            }
        }
    }

    unset($_SESSION["user_id"], $_SESSION["name"], $_SESSION["role"], $_SESSION["role_id"]);
}

$error = "";
$otp_notice = (string)($_SESSION['otp_login_notice'] ?? '');
unset($_SESSION['otp_login_notice']);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $conn->real_escape_string(strip_tags(trim($_POST["email"] ?? "")));
    $password = $_POST["password"] ?? "";

    if (empty($email) || empty($password)) {
        $error = "Email dan password wajib diisi!";
    } else {
        $stmt = $conn->prepare(
            "SELECT u.id, u.name, u.password, u.status, u.role_id, u.phone_verified_at, r.name as role_name
             FROM users u
             JOIN roles r ON u.role_id = r.id
             WHERE u.email = ?"
        );
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            if (password_verify($password, $user["password"])) {
                // Cek status akun — tolak jika suspended
                if (($user["status"] ?? "active") === "suspended") {
                    $error = "Akun Anda sedang ditangguhkan. Hubungi Manager Operasional untuk informasi lebih lanjut.";
                } elseif (strcasecmp((string)$user["role_name"], "Customer") === 0 && $user["phone_verified_at"] === null) {
                    $lockStatus = otp_customer_lock_status($conn, (int)$user['id']);
                    if (!empty($lockStatus['unavailable'])) {
                        $error = "Layanan verifikasi sedang tidak tersedia. Silakan coba lagi.";
                    } elseif (!empty($lockStatus['locked'])) {
                        $unlockAt = new DateTimeImmutable((string)$lockStatus['locked_until'], new DateTimeZone('UTC'));
                        $error = 'Akun Customer terkunci karena terlalu banyak kesalahan OTP. Coba kembali setelah ' . $unlockAt->format('Y-m-d H:i:s') . ' UTC.';
                    } else {
                        session_regenerate_id(true);
                        $_SESSION['otp_pending_user_id'] = (int)$user['id'];
                        $_SESSION['otp_pending_challenge_id'] = otp_current_challenge_hex($conn, (int)$user['id']);
                        header("Location: customer/verify_otp.php");
                        exit;
                    }
                } else {
                    $db_role_name = trim((string)($user["role_name"] ?? ""));
                    $normalized_role = strtolower($db_role_name);

                    if (!in_array($normalized_role, ['admin', 'customer', 'kasir', 'supplier', 'owner'], true)) {
                        $error = "Akses pengguna tidak valid.";
                    } else {
                        session_regenerate_id(true); // Cegah session fixation
                        $_SESSION["user_id"] = (int)$user["id"];
                        $_SESSION["name"]    = $user["name"];
                        $_SESSION["role"]    = $normalized_role;
                        $_SESSION["role_id"] = (int)$user["role_id"];

                        // Update last_active untuk presence system
                        $conn->query("UPDATE users SET last_active = NOW() WHERE id = " . (int)$user["id"]);

                        header("Location: {$normalized_role}/");
                        exit;
                    }
                }
            } else {
                $error = "Password salah!";
            }
        } else {
            $error = "Email tidak ditemukan!";
        }
    }
}

// Jika ada error pada POST, pastikan lampu langsung menyala agar pengguna tidak tertahan
$initially_lit = !empty($error);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Masuk — Kopi Mahasiswa</title>
    <?php require_once "includes/theme_head.php"; ?>
    <style>
        /* Smooth cinematic transitions for lamp & pull-cord */
        .lamp-transition {
            transition: all 0.75s cubic-bezier(0.16, 1, 0.3, 1);
        }

        /* Bulb Glow Animation */
        @keyframes filamentGlow {
            0%, 100% { opacity: 0.95; filter: drop-shadow(0 0 12px #f59e0b); }
            50% { opacity: 1; filter: drop-shadow(0 0 22px #fbbf24); }
        }

        .filament-active {
            animation: filamentGlow 2.5s ease-in-out infinite;
        }

        /* Pull Cord Spring Rebound */
        .cord-spring {
            transition: transform 0.45s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        /* Subtle, elegant micro-sway when lamp is off */
        @keyframes gentleSway {
            0%, 100% { transform: rotate(0deg); }
            35% { transform: rotate(0.9deg); }
            75% { transform: rotate(-0.6deg); }
        }

        .lamp-off .cord-sway-idle {
            animation: gentleSway 6.5s ease-in-out infinite;
            transform-origin: top center;
        }

        /* Cinematic soft radial glow with ultra-smooth diffusion (no harsh edges) */
        #lamp-glow-cone {
            background: radial-gradient(circle at 35% 25%,
                rgba(245, 158, 11, 0.28) 0%,
                rgba(217, 119, 6, 0.16) 28%,
                rgba(180, 83, 9, 0.08) 50%,
                rgba(120, 53, 15, 0.02) 70%,
                transparent 85%);
            filter: blur(40px);
        }

        /* Card reveal state (Cinematic: opacity, blur, subtle scale) */
        #login-card-container {
            transition: opacity 0.7s cubic-bezier(0.16, 1, 0.3, 1),
                        transform 0.7s cubic-bezier(0.16, 1, 0.3, 1),
                        filter 0.7s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .lamp-off #login-card-container {
            opacity: 0;
            transform: scale(0.95) translateY(10px);
            filter: blur(8px);
            pointer-events: none;
        }

        .lamp-on #login-card-container {
            opacity: 1;
            transform: scale(1) translateY(0);
            filter: blur(0);
            pointer-events: auto;
        }

        /* Reduced motion support */
        @media (prefers-reduced-motion: reduce) {
            #login-card-container, .lamp-transition, .cord-spring, .cord-sway-idle, .filament-active {
                transition: none !important;
                animation: none !important;
            }
        }
    </style>
    <!-- Fallback jika JS dinonaktifkan di browser -->
    <noscript>
        <style>
            #login-card-container {
                opacity: 1 !important;
                transform: none !important;
                filter: none !important;
                pointer-events: auto !important;
            }
            #pull-cord-wrapper {
                display: none !important;
            }
            #lamp-glow-cone {
                opacity: 0.9 !important;
            }
        </style>
    </noscript>
</head>
<body id="page-body" class="min-h-screen bg-[#090c12] text-stone-200 flex flex-col justify-between p-4 antialiased selection:bg-amber-500/20 selection:text-amber-300 relative overflow-x-hidden <?= $initially_lit ? 'lamp-on' : 'lamp-off' ?>">

    <!-- Theme Toggle (Fixed Top Right) -->
    <div class="fixed top-5 right-5 z-40">
        <?php require_once "includes/theme_toggle.php"; ?>
    </div>

    <!-- Room Dark Atmosphere Overlay (Fades out when lamp turns on) -->
    <div id="room-ambient-dark" class="fixed inset-0 bg-[#07090e]/85 z-0 lamp-transition <?= $initially_lit ? 'opacity-20 pointer-events-none' : 'opacity-100' ?>"></div>

    <!-- Cinematic Warm Amber Radial Glow Bloom (Spreads smoothly without hard circles) -->
    <div id="lamp-glow-cone" class="fixed -top-40 -left-40 sm:-top-32 sm:-left-28 w-[650px] h-[650px] sm:w-[950px] sm:h-[950px] lg:w-[1250px] lg:h-[1250px] rounded-full pointer-events-none z-0 lamp-transition <?= $initially_lit ? 'opacity-100' : 'opacity-0' ?>"></div>

    <!-- Secondary Soft Ambient Bloom (Bottom Right) -->
    <div class="fixed -bottom-40 -right-40 w-96 h-96 bg-amber-700/10 rounded-full blur-[140px] pointer-events-none z-0"></div>

    <!-- ========================================================================= -->
    <!-- CINEMATIC STAGE: LAMP ON LEFT, LOGIN CARD ON RIGHT (DESKTOP) -->
    <!-- ========================================================================= -->
    <div class="w-full max-w-6xl mx-auto flex-1 flex flex-col lg:flex-row items-center justify-between gap-8 lg:gap-16 py-6 sm:py-10 px-4 sm:px-6 relative z-10">

        <!-- ===================================================================== -->
        <!-- LEFT COLUMN: VINTAGE PENDANT LAMP & WHITE PULL-CORD APPARATUS -->
        <!-- ===================================================================== -->
        <div class="w-full lg:w-5/12 flex flex-col items-center justify-start relative pt-2 lg:pt-0">
            <div id="pull-cord-wrapper" class="relative select-none flex flex-col items-center">
                <!-- Vintage Industrial Pendant Lamp Fixture SVG -->
                <div class="relative flex flex-col items-center">
                    <svg class="w-28 h-40 sm:w-36 sm:h-52 overflow-visible drop-shadow-[0_12px_28px_rgba(0,0,0,0.85)]" viewBox="0 0 120 160" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <!-- Ceiling Mount Plate -->
                        <path d="M46 0H74V8C74 10.2091 72.2091 12 70 12H50C47.7909 12 46 10.2091 46 8V0Z" fill="#1e293b" stroke="#334155" stroke-width="1"/>

                        <!-- Heavy Black Power Cable -->
                        <line x1="60" y1="12" x2="60" y2="48" stroke="#0f172a" stroke-width="3.5" stroke-linecap="round"/>

                        <!-- Metallic Socket Cap & Brass Collar -->
                        <path d="M52 48H68V58H52V48Z" fill="#334155" stroke="#475569" stroke-width="1"/>
                        <path d="M46 58H74L71 66H49L46 58Z" fill="#d97706" opacity="0.95"/>

                        <!-- Conical Matte Black Industrial Shade -->
                        <path d="M18 104L49 66H71L102 104H18Z" fill="#0e131d" stroke="#334155" stroke-width="1.5"/>
                        <!-- Inner Shade Warm Amber Reflection -->
                        <ellipse cx="60" cy="104" rx="42" ry="8.5" fill="#131926" stroke="#f59e0b" stroke-width="1.5" stroke-opacity="0.75"/>

                        <!-- Edison Glass Bulb -->
                        <path d="M50 107C50 101.477 54.4772 97 60 97C65.5228 97 70 101.477 70 107C70 112.523 66 118 64 121C62 124 62 127 60 127C58 127 58 124 56 121C54 118 50 112.523 50 107Z"
                              id="bulb-glass"
                              class="lamp-transition"
                              fill="<?= $initially_lit ? 'rgba(251, 191, 36, 0.35)' : 'rgba(30, 41, 59, 0.4)' ?>"
                              stroke="<?= $initially_lit ? '#fbbf24' : '#475569' ?>"
                              stroke-width="1.2"/>

                        <!-- Glowing Incandescent Filament Inside Bulb -->
                        <path id="bulb-filament"
                              class="lamp-transition <?= $initially_lit ? 'filament-active' : '' ?>"
                              d="M57 108L59 103L60 108L61 103L63 108"
                              stroke="<?= $initially_lit ? '#fef08a' : '#64748b' ?>"
                              stroke-width="1.8"
                              stroke-linecap="round"
                              stroke-linejoin="round"/>

                        <!-- Radiant Point Halo Effect (Visible when lit) -->
                        <circle id="bulb-core-glow" cx="60" cy="108" r="18" fill="url(#coreGlowGradient)"
                                class="lamp-transition <?= $initially_lit ? 'opacity-100' : 'opacity-0' ?>"/>

                        <defs>
                            <radialGradient id="coreGlowGradient" cx="0" cy="0" r="1" gradientUnits="userSpaceOnUse" gradientTransform="translate(60 108) rotate(90) scale(20)">
                                <stop stop-color="#fbbf24" stop-opacity="0.95"/>
                                <stop offset="0.55" stop-color="#f59e0b" stop-opacity="0.45"/>
                                <stop offset="1" stop-color="#d97706" stop-opacity="0"/>
                            </radialGradient>
                        </defs>
                    </svg>
                </div>

                <!-- The Hanging White Braided Pull-Cord & Metallic Handle -->
                <div class="cord-sway-idle origin-top">
                    <div id="cord-assembly" class="flex flex-col items-center cord-spring cursor-grab active:cursor-grabbing -mt-10 sm:-mt-8"
                         role="button"
                         tabindex="0"
                         aria-label="Tarik tali atau tekan Spasi/Enter untuk menyalakan lampu"
                         title="Tarik atau tekan untuk menyalakan lampu">

                        <!-- Braided Cord Wire (white, visible against dark background) -->
                        <div id="cord-wire" class="w-[2.5px] h-36 sm:h-48 bg-gradient-to-b from-stone-400 via-stone-200 to-white shadow-[0_0_8px_rgba(255,255,255,0.45)] rounded-full origin-top"></div>

                        <!-- Solid White Bell / Bead Handle with Amber Brass Accent -->
                        <div id="cord-handle" class="relative -mt-0.5 group">
                            <!-- Subtle Glow Ring when off (Purely Visual Cue) -->
                            <div id="cord-pulse-ring" class="absolute -inset-1.5 rounded-full bg-amber-400/20 blur-[4px] animate-pulse <?= $initially_lit ? 'hidden' : '' ?>"></div>

                            <!-- Solid Teardrop Pull Bead -->
                            <div class="w-4 h-7 sm:w-4.5 sm:h-8 rounded-full bg-gradient-to-b from-white via-stone-100 to-amber-200 border border-stone-300 shadow-[0_4px_14px_rgba(0,0,0,0.6)] flex items-center justify-center transition-transform group-hover:scale-110">
                                <!-- Subtle metallic ring details -->
                                <div class="w-2.5 h-[1.5px] bg-amber-600/70 rounded-full mb-1"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ===================================================================== -->
        <!-- RIGHT COLUMN: LOGIN CARD CONTAINER (REVEALED IN AMBER GLOW) -->
        <!-- ===================================================================== -->
        <div class="w-full lg:w-6/12 max-w-md mx-auto flex flex-col justify-center">
            <div id="login-card-container">
                <!-- Brand Header -->
                <div class="text-center mb-6">
                    <div class="inline-flex w-14 h-14 rounded-2xl bg-gradient-to-br from-amber-500 to-amber-700 items-center justify-center text-stone-950 shadow-xl shadow-amber-950/50 mb-3 border border-amber-400/30">
                        <i data-lucide="coffee" class="w-8 h-8"></i>
                    </div>
                    <h1 class="text-2xl font-extrabold text-white tracking-tight">Kopi Mahasiswa</h1>
                    <p class="text-xs text-stone-400 mt-1">Terminal Masuk Terpusat Operasional &amp; Pelanggan</p>
                </div>

                <!-- Main Card -->
                <div class="trade-card bg-[#0e131d]/90 backdrop-blur-xl rounded-2xl border border-stone-800/90 shadow-2xl overflow-hidden p-7">
                    <?php if ($otp_notice !== ''): ?>
                    <div class="bg-emerald-500/10 border border-emerald-500/30 text-emerald-300 px-4 py-3 rounded-xl text-xs mb-5">
                        <?= htmlspecialchars($otp_notice, ENT_QUOTES, 'UTF-8') ?>
                    </div>
                    <?php endif; ?>
                    <?php if ($error): ?>
                    <div class="bg-rose-500/10 border border-rose-500/30 text-rose-300 px-4 py-3 rounded-xl text-xs mb-5 flex items-start gap-2.5">
                        <i data-lucide="alert-circle" class="w-4 h-4 text-rose-400 flex-shrink-0 mt-0.5"></i>
                        <div class="leading-relaxed"><?= htmlspecialchars($error) ?></div>
                    </div>
                    <?php endif; ?>

                    <form action="" method="POST" class="space-y-4">
                        <?php if (function_exists("csrf_input")) echo csrf_input(); ?>

                        <div>
                            <label class="block text-xs font-semibold text-stone-300 uppercase tracking-wider mb-1.5">Alamat Email</label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 pointer-events-none text-stone-500">
                                    <i data-lucide="mail" class="w-4 h-4"></i>
                                </span>
                                <input id="input-email" name="email" type="email" required autocomplete="email"
                                       value="<?= htmlspecialchars($_POST["email"] ?? "") ?>"
                                       class="w-full bg-[#131926] border border-stone-800 focus:border-amber-500 focus:ring-1 focus:ring-amber-500 rounded-xl pl-10 pr-4 py-2.5 text-sm text-stone-200 placeholder-stone-600 transition-all outline-none"
                                       placeholder="nama@email.com">
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-stone-300 uppercase tracking-wider mb-1.5">Password</label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 pointer-events-none text-stone-500">
                                    <i data-lucide="lock" class="w-4 h-4"></i>
                                </span>
                                <input name="password" type="password" required autocomplete="current-password"
                                       class="w-full bg-[#131926] border border-stone-800 focus:border-amber-500 focus:ring-1 focus:ring-amber-500 rounded-xl pl-10 pr-4 py-2.5 text-sm text-stone-200 placeholder-stone-600 transition-all outline-none"
                                       placeholder="••••••••">
                            </div>
                        </div>

                        <button type="submit"
                                class="w-full bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-400 hover:to-amber-500 text-stone-950 font-bold py-2.5 rounded-xl shadow-lg shadow-amber-950/40 hover:shadow-amber-500/20 transition-all transform active:scale-[0.98] mt-2 flex items-center justify-center gap-2 text-sm">
                            <i data-lucide="log-in" class="w-4 h-4"></i> Masuk Sekarang
                        </button>
                    </form>

                    <div class="mt-6 pt-5 border-t border-stone-800/80 text-center">
                        <p class="text-xs text-stone-400">
                            Belum memiliki akun pelanggan?
                            <a href="register.php" class="text-amber-400 font-semibold hover:text-amber-300 hover:underline ml-1">Daftar Sekarang</a>
                        </p>
                        <div class="mt-3">
                            <a href="index.php" class="inline-flex items-center gap-1.5 text-xs text-stone-500 hover:text-stone-300 transition-colors">
                                <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i> Kembali ke Beranda
                            </a>
                        </div>
                    </div>
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

    <!-- ========================================================================= -->
    <!-- INTERACTIVE SCRIPT: PULL-CORD PHYSICS, MOUSE/TOUCH & KEYBOARD -->
    <!-- ========================================================================= -->
    <script>
        (function() {
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }

            const pageBody = document.getElementById('page-body');
            const cordAssembly = document.getElementById('cord-assembly');
            const roomAmbient = document.getElementById('room-ambient-dark');
            const lampGlow = document.getElementById('lamp-glow-cone');
            const bulbGlass = document.getElementById('bulb-glass');
            const bulbFilament = document.getElementById('bulb-filament');
            const bulbGlow = document.getElementById('bulb-core-glow');
            const pulseRing = document.getElementById('cord-pulse-ring');
            const emailInput = document.getElementById('input-email');

            let isLit = <?= $initially_lit ? 'true' : 'false' ?>;
            let isDragging = false;
            let startY = 0;
            let currentTranslateY = 0;
            const PULL_THRESHOLD = 36; // Jarak tarikan (px) untuk memicu saklar
            const MAX_PULL = 64; // Batas maksimal tarikan fisik

            function setLampState(lit) {
                isLit = lit;
                if (isLit) {
                    pageBody.classList.remove('lamp-off');
                    pageBody.classList.add('lamp-on');

                    if (roomAmbient) roomAmbient.classList.add('opacity-20', 'pointer-events-none');
                    if (lampGlow) {
                        lampGlow.classList.remove('opacity-0');
                        lampGlow.classList.add('opacity-100');
                    }

                    if (bulbGlass) {
                        bulbGlass.setAttribute('fill', 'rgba(251, 191, 36, 0.35)');
                        bulbGlass.setAttribute('stroke', '#fbbf24');
                    }
                    if (bulbFilament) {
                        bulbFilament.classList.add('filament-active');
                        bulbFilament.setAttribute('stroke', '#fef08a');
                    }
                    if (bulbGlow) bulbGlow.classList.replace('opacity-0', 'opacity-100');
                    if (pulseRing) pulseRing.classList.add('hidden');

                    // Auto-focus email input setelah transisi kartu selesai
                    setTimeout(() => {
                        if (emailInput && document.activeElement !== emailInput) {
                            emailInput.focus();
                        }
                    }, 480);
                } else {
                    pageBody.classList.remove('lamp-on');
                    pageBody.classList.add('lamp-off');

                    if (roomAmbient) roomAmbient.classList.remove('opacity-20', 'pointer-events-none');
                    if (lampGlow) {
                        lampGlow.classList.remove('opacity-100');
                        lampGlow.classList.add('opacity-0');
                    }

                    if (bulbGlass) {
                        bulbGlass.setAttribute('fill', 'rgba(30, 41, 59, 0.4)');
                        bulbGlass.setAttribute('stroke', '#475569');
                    }
                    if (bulbFilament) {
                        bulbFilament.classList.remove('filament-active');
                        bulbFilament.setAttribute('stroke', '#64748b');
                    }
                    if (bulbGlow) bulbGlow.classList.replace('opacity-100', 'opacity-0');
                    if (pulseRing) pulseRing.classList.remove('hidden');
                }
            }

            // Trigger Spring Action & Switch
            function triggerSwitch() {
                cordAssembly.style.transform = 'translateY(18px)';
                setTimeout(() => {
                    cordAssembly.style.transform = 'translateY(0px)';
                    setLampState(!isLit);
                }, 120);
            }

            // Mouse Drag Interaction
            cordAssembly.addEventListener('mousedown', function(e) {
                if (e.button !== 0) return; // Hanya klik kiri
                isDragging = true;
                startY = e.clientY;
                cordAssembly.classList.remove('cord-spring');
                e.preventDefault();
            });

            window.addEventListener('mousemove', function(e) {
                if (!isDragging) return;
                const deltaY = e.clientY - startY;
                if (deltaY > 0) {
                    currentTranslateY = Math.min(deltaY * 0.7, MAX_PULL);
                    cordAssembly.style.transform = `translateY(${currentTranslateY}px)`;
                }
            });

            window.addEventListener('mouseup', function(e) {
                if (!isDragging) return;
                isDragging = false;
                cordAssembly.classList.add('cord-spring');

                if (currentTranslateY >= PULL_THRESHOLD) {
                    cordAssembly.style.transform = 'translateY(0px)';
                    currentTranslateY = 0;
                    setLampState(!isLit);
                } else if (currentTranslateY < 5) {
                    // Click langsung pada tali
                    triggerSwitch();
                } else {
                    cordAssembly.style.transform = 'translateY(0px)';
                    currentTranslateY = 0;
                }
            });

            // Touch Drag Interaction (Mobile / Tablet)
            cordAssembly.addEventListener('touchstart', function(e) {
                if (e.touches.length !== 1) return;
                isDragging = true;
                startY = e.touches[0].clientY;
                cordAssembly.classList.remove('cord-spring');
            }, { passive: true });

            window.addEventListener('touchmove', function(e) {
                if (!isDragging) return;
                const deltaY = e.touches[0].clientY - startY;
                if (deltaY > 0) {
                    currentTranslateY = Math.min(deltaY * 0.7, MAX_PULL);
                    cordAssembly.style.transform = `translateY(${currentTranslateY}px)`;
                }
            }, { passive: true });

            window.addEventListener('touchend', function(e) {
                if (!isDragging) return;
                isDragging = false;
                cordAssembly.classList.add('cord-spring');

                if (currentTranslateY >= PULL_THRESHOLD) {
                    cordAssembly.style.transform = 'translateY(0px)';
                    currentTranslateY = 0;
                    setLampState(!isLit);
                } else if (currentTranslateY < 6) {
                    // Tap langsung pada tali
                    triggerSwitch();
                } else {
                    cordAssembly.style.transform = 'translateY(0px)';
                    currentTranslateY = 0;
                }
            });

            // Keyboard Access (Space / Enter)
            cordAssembly.addEventListener('keydown', function(e) {
                if (e.key === ' ' || e.key === 'Enter') {
                    e.preventDefault();
                    triggerSwitch();
                }
            });
        })();
    </script>
</body>
</html>
