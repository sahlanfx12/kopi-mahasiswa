<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kopi Mahasiswa — Premium Campus Coffee & Hub</title>
    <?php require_once __DIR__ . '/../includes/theme_head.php'; ?>
</head>
<body class="bg-[#090c12] text-stone-200 flex flex-col min-h-screen antialiased selection:bg-amber-500/20 selection:text-amber-300">
    <!-- Navbar -->
    <nav class="sticky top-0 z-50 bg-[#0e131d]/90 backdrop-blur-xl border-b border-stone-800/80">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <div class="flex items-center gap-3">
                    <a href="index.php" class="flex items-center gap-2.5 group">
                        <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-amber-500 to-amber-700 flex items-center justify-center text-stone-950 shadow-md shadow-amber-950/40 group-hover:scale-105 transition-transform">
                            <i data-lucide="coffee" class="w-5 h-5"></i>
                        </div>
                        <div>
                            <span class="font-extrabold text-lg tracking-tight text-white group-hover:text-amber-400 transition-colors">Kopi Mahasiswa</span>
                            <span class="hidden sm:inline-block ml-2 text-[10px] tracking-widest uppercase px-2 py-0.5 rounded-full bg-amber-500/10 text-amber-400 border border-amber-500/20 font-bold">Coffee Hub</span>
                        </div>
                    </a>
                </div>

                <div class="hidden md:flex items-center space-x-1 sm:space-x-2">
                    <a href="index.php" class="text-stone-300 hover:text-white hover:bg-stone-800/50 px-3.5 py-2 rounded-lg text-sm font-medium transition-all flex items-center gap-1.5">
                        <i data-lucide="home" class="w-4 h-4 text-stone-400"></i> Beranda
                    </a>
                    <a href="menu.php" class="text-stone-300 hover:text-white hover:bg-stone-800/50 px-3.5 py-2 rounded-lg text-sm font-medium transition-all flex items-center gap-1.5">
                        <i data-lucide="utensils" class="w-4 h-4 text-stone-400"></i> Menu
                    </a>
                    <a href="login.php" class="text-stone-300 hover:text-amber-400 hover:bg-stone-800/50 px-3.5 py-2 rounded-lg text-sm font-medium transition-all flex items-center gap-1.5">
                        <i data-lucide="log-in" class="w-4 h-4 text-stone-400"></i> Masuk
                    </a>
                    <a href="register.php" class="bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-400 hover:to-amber-500 text-stone-950 font-bold px-4 py-2 rounded-lg text-sm shadow-lg shadow-amber-950/40 hover:shadow-amber-500/20 transition-all transform active:scale-95 flex items-center gap-1.5">
                        <i data-lucide="user-plus" class="w-4 h-4"></i> Daftar Gratis
                    </a>
                    <div class="pl-2">
                        <?php require_once __DIR__ . '/../includes/theme_toggle.php'; ?>
                    </div>
                </div>

                <!-- Mobile menu button -->
                <div class="md:hidden flex items-center gap-2">
                    <?php require_once __DIR__ . '/../includes/theme_toggle.php'; ?>
                    <a href="login.php" class="text-xs bg-amber-500/10 border border-amber-500/30 text-amber-400 font-semibold px-3 py-1.5 rounded-lg">Login</a>
                    <a href="register.php" class="text-xs bg-amber-500 text-stone-950 font-bold px-3 py-1.5 rounded-lg">Daftar</a>
                </div>
            </div>
        </div>
    </nav>
    <main class="flex-grow">
