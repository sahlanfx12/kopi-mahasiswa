<?php include "header.php"; ?>

<div class="max-w-7xl mx-auto py-10 px-4 sm:px-6 lg:px-8">
    <!-- Welcome Banner -->
    <div class="bg-[#0e131d]/90 border border-stone-800/80 rounded-3xl p-6 sm:p-10 shadow-2xl relative overflow-hidden mb-8">
        <div class="relative z-10 max-w-2xl">
            <div class="inline-flex items-center gap-2 bg-amber-500/10 text-amber-400 text-xs font-semibold px-3 py-1 rounded-full mb-4 border border-amber-500/20">
                <i data-lucide="sparkles" class="w-3.5 h-3.5"></i>
                <span>Selamat Datang Kembali</span>
            </div>
            <h1 class="text-3xl sm:text-4xl font-extrabold text-white tracking-tight mb-3">
                Halo, <?= htmlspecialchars($_SESSION["name"] ?? 'Pelanggan') ?>! ☕
            </h1>
            <p class="text-sm sm:text-base text-stone-400 leading-relaxed mb-6">
                Ingin kopi racikan barista hari ini? Pesan online sekarang tanpa antre, lacak status racikan, dan ambil langsung saat siap dinikmati.
            </p>
            <div class="flex flex-wrap gap-3">
                <a href="menu.php" class="bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-400 hover:to-amber-500 text-stone-950 font-bold px-6 py-3 rounded-xl text-xs transition-all shadow-lg shadow-amber-950/50 flex items-center gap-2">
                    <i data-lucide="coffee" class="w-4 h-4"></i> Buka Katalog Menu
                </a>
                <a href="history.php" class="bg-[#141b29] hover:bg-[#1a2233] text-stone-200 border border-stone-800 px-6 py-3 rounded-xl text-xs font-semibold transition-all flex items-center gap-2">
                    <i data-lucide="clock" class="w-4 h-4 text-amber-400"></i> Riwayat Pesanan
                </a>
            </div>
        </div>
    </div>

    <!-- Quick Action Cards (Bybit style trading surfaces) -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <a href="menu.php" class="bg-[#0e131d]/90 hover:bg-[#141b29] border border-stone-800/80 hover:border-amber-500/40 p-7 rounded-2xl transition-all shadow-xl flex items-start gap-5 group">
            <div class="w-14 h-14 rounded-2xl bg-amber-500/10 border border-amber-500/20 text-amber-400 flex items-center justify-center group-hover:scale-105 transition-transform flex-shrink-0">
                <i data-lucide="utensils" class="w-7 h-7"></i>
            </div>
            <div>
                <h3 class="text-lg font-bold text-white group-hover:text-amber-400 transition-colors">Katalog Menu &amp; Racikan</h3>
                <p class="text-xs text-stone-400 mt-1 leading-relaxed">
                    Jelajahi espresso, manual brew, signature latte, dan kudapan pendamping belajar dengan harga khusus mahasiswa.
                </p>
                <span class="inline-flex items-center gap-1 text-xs text-amber-400 font-semibold mt-4 group-hover:translate-x-1 transition-transform">
                    Pesan Sekarang <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
                </span>
            </div>
        </a>

        <a href="history.php" class="bg-[#0e131d]/90 hover:bg-[#141b29] border border-stone-800/80 hover:border-blue-500/40 p-7 rounded-2xl transition-all shadow-xl flex items-start gap-5 group">
            <div class="w-14 h-14 rounded-2xl bg-blue-500/10 border border-blue-500/20 text-blue-400 flex items-center justify-center group-hover:scale-105 transition-transform flex-shrink-0">
                <i data-lucide="receipt" class="w-7 h-7"></i>
            </div>
            <div>
                <h3 class="text-lg font-bold text-white group-hover:text-blue-400 transition-colors">Lacak Pesanan Saya</h3>
                <p class="text-xs text-stone-400 mt-1 leading-relaxed">
                    Pantau progres pesanan kamu mulai dari Sedang Disiapkan, Siap Disajikan, hingga selesai diambil di outlet.
                </p>
                <span class="inline-flex items-center gap-1 text-xs text-blue-400 font-semibold mt-4 group-hover:translate-x-1 transition-transform">
                    Lihat Histori <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
                </span>
            </div>
        </a>
    </div>
</div>

<?php include "footer.php"; ?>
