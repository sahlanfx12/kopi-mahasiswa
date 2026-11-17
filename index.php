<?php include "components/header.php"; ?>

<!-- HERO SECTION -->
<section class="relative bg-gradient-to-b from-[#090c12] via-[#0f141f] to-[#090c12] text-white overflow-hidden py-16 sm:py-24 border-b border-stone-800/80">
    <!-- Ambient glow decorative elements -->
    <div class="absolute inset-0 pointer-events-none overflow-hidden">
        <div class="absolute top-10 left-1/4 w-96 h-96 bg-amber-500/10 rounded-full blur-[130px]"></div>
        <div class="absolute bottom-10 right-1/4 w-[30rem] h-[30rem] bg-amber-700/10 rounded-full blur-[150px]"></div>
    </div>

    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 items-center">
            <div class="lg:col-span-7">
                <div class="inline-flex items-center gap-2 bg-amber-500/10 text-amber-300 text-xs font-semibold px-3 py-1.5 rounded-full mb-6 border border-amber-500/30">
                    <span class="w-2 h-2 rounded-full bg-amber-400 animate-pulse"></span>
                    <span>Platform Coffee Hub Kampus Terpadu</span>
                </div>
                <h1 class="text-4xl sm:text-5xl lg:text-6xl font-extrabold tracking-tight leading-[1.1] mb-6 text-white">
                    Teman Ngopi,<br>
                    <span class="bg-gradient-to-r from-amber-400 via-amber-300 to-amber-500 bg-clip-text text-transparent">Partner Produktif.</span>
                </h1>
                <p class="text-base sm:text-lg text-stone-400 mb-8 leading-relaxed max-w-xl">
                    Nikmati racikan kopi premium dari biji pilihan terbaik petani lokal. Harga bersahabat untuk mahasiswa, suasana nyaman buat tugas, dan sistem pemesanan online cepat tanpa antre.
                </p>

                <div class="flex flex-wrap items-center gap-4">
                    <a href="customer/menu.php" class="bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-400 hover:to-amber-500 text-stone-950 font-bold px-7 py-3.5 rounded-xl transition-all transform hover:scale-[1.02] active:scale-[0.98] shadow-lg shadow-amber-950/50 flex items-center gap-2">
                        <i data-lucide="coffee" class="w-5 h-5"></i> Lihat Katalog Menu
                    </a>
                    <a href="register.php" class="bg-[#141b29] hover:bg-[#1a2233] text-stone-200 hover:text-white font-semibold px-7 py-3.5 rounded-xl transition-all border border-stone-800 hover:border-stone-700 flex items-center gap-2">
                        <i data-lucide="sparkles" class="w-5 h-5 text-amber-400"></i> Buat Akun
                    </a>
                </div>

                <!-- Quick Trading-Style Metric Row -->
                <div class="grid grid-cols-3 gap-4 mt-12 pt-8 border-t border-stone-800/80 max-w-lg">
                    <div class="bg-[#0e131d]/60 border border-stone-800/60 rounded-xl p-3">
                        <div class="text-2xl font-bold font-mono text-amber-400">10+</div>
                        <div class="text-xs text-stone-400 mt-0.5">Varian Menu</div>
                    </div>
                    <div class="bg-[#0e131d]/60 border border-stone-800/60 rounded-xl p-3">
                        <div class="text-2xl font-bold font-mono text-amber-400">15K</div>
                        <div class="text-xs text-stone-400 mt-0.5">Mulai Harga</div>
                    </div>
                    <div class="bg-[#0e131d]/60 border border-stone-800/60 rounded-xl p-3">
                        <div class="text-2xl font-bold font-mono text-emerald-400 flex items-center gap-1">
                            <i data-lucide="star" class="w-4 h-4 fill-emerald-400 text-emerald-400"></i> 4.9
                        </div>
                        <div class="text-xs text-stone-400 mt-0.5">Rating Kepuasan</div>
                    </div>
                </div>
            </div>

            <!-- Visual Card (Bybit style depth) -->
            <div class="lg:col-span-5 hidden lg:flex justify-center">
                <div class="relative w-full max-w-md">
                    <div class="bg-[#0e131d]/90 border border-stone-800 rounded-3xl p-7 shadow-2xl shadow-black/80 backdrop-blur-xl relative overflow-hidden">
                        <div class="flex items-center justify-between pb-5 border-b border-stone-800/80 mb-6">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-xl bg-amber-500/10 border border-amber-500/20 flex items-center justify-center text-amber-400">
                                    <i data-lucide="flame" class="w-5 h-5"></i>
                                </div>
                                <div>
                                    <h4 class="font-bold text-white text-sm">Signature Blend</h4>
                                    <span class="text-[11px] text-stone-400 font-mono">ARABICA &bull; ROBUSTA</span>
                                </div>
                            </div>
                            <span class="text-xs font-mono font-bold text-emerald-400 bg-emerald-500/10 border border-emerald-500/20 px-2.5 py-1 rounded-full">
                                READY STOCK
                            </span>
                        </div>

                        <!-- Visual Icon Representation -->
                        <div class="h-44 rounded-2xl bg-gradient-to-br from-[#141b29] to-[#0e131d] border border-stone-800/80 flex items-center justify-center relative mb-6">
                            <div class="w-24 h-24 rounded-full bg-amber-500/10 border border-amber-500/20 flex items-center justify-center text-amber-400 shadow-inner">
                                <i data-lucide="coffee" class="w-12 h-12"></i>
                            </div>
                            <div class="absolute bottom-3 left-3 bg-[#090c12]/90 border border-stone-800 rounded-lg px-2.5 py-1 text-[11px] text-stone-300">
                                Fresh Brewed
                            </div>
                        </div>

                        <!-- Mini Trade/Order Bar -->
                        <div class="space-y-2.5 text-xs">
                            <div class="flex justify-between text-stone-400">
                                <span>Kategori Unggulan</span>
                                <span class="text-stone-200 font-medium">Espresso Based</span>
                            </div>
                            <div class="flex justify-between text-stone-400">
                                <span>Waktu Penyajian</span>
                                <span class="text-emerald-400 font-mono font-semibold">~3 - 5 Menit</span>
                            </div>
                        </div>

                        <div class="mt-6 pt-5 border-t border-stone-800/80 flex items-center justify-between">
                            <div>
                                <span class="text-[10px] text-stone-500 block uppercase">Harga Mahasiswa</span>
                                <span class="text-xl font-bold font-mono text-white">Rp 18.000</span>
                            </div>
                            <a href="customer/menu.php" class="bg-amber-500 hover:bg-amber-400 text-stone-950 text-xs font-bold px-4 py-2 rounded-lg transition-all">
                                Order Sekarang
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- FEATURE SECTION -->
<section class="py-20 bg-[#0c0f17] border-b border-stone-800/80">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-14">
            <span class="text-xs uppercase tracking-widest text-amber-400 font-bold">Kelebihan Utama</span>
            <h2 class="text-3xl font-extrabold text-white sm:text-4xl mt-2 tracking-tight">Kenapa Pilih Kopi Mahasiswa?</h2>
            <p class="mt-2 text-sm text-stone-400 max-w-lg mx-auto">Dirancang khusus untuk mendukung kebutuhan akademis dan kenyamanan nongkrong kamu.</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            <div class="bg-[#0e131d]/80 rounded-2xl p-6 border border-stone-800/80 hover:border-amber-500/30 transition-all duration-300 group">
                <div class="w-12 h-12 rounded-xl bg-amber-500/10 border border-amber-500/20 text-amber-400 flex items-center justify-center mb-5 group-hover:scale-110 transition-transform">
                    <i data-lucide="coffee" class="w-6 h-6"></i>
                </div>
                <h3 class="text-base font-bold text-white mb-2">Biji Kopi Pilihan</h3>
                <p class="text-xs text-stone-400 leading-relaxed">Racikan biji kopi Nusantara berkualitas, disangrai presisi untuk menghasilkan aroma autentik setiap cangkir.</p>
            </div>

            <div class="bg-[#0e131d]/80 rounded-2xl p-6 border border-stone-800/80 hover:border-amber-500/30 transition-all duration-300 group">
                <div class="w-12 h-12 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 flex items-center justify-center mb-5 group-hover:scale-110 transition-transform">
                    <i data-lucide="wallet" class="w-6 h-6"></i>
                </div>
                <h3 class="text-base font-bold text-white mb-2">Ramah Kantong</h3>
                <p class="text-xs text-stone-400 leading-relaxed">Mulai Rp 15.000. Kualitas kafe premium dengan harga yang pas untuk anggaran bulanan mahasiswa.</p>
            </div>

            <div class="bg-[#0e131d]/80 rounded-2xl p-6 border border-stone-800/80 hover:border-amber-500/30 transition-all duration-300 group">
                <div class="w-12 h-12 rounded-xl bg-cyan-500/10 border border-cyan-500/20 text-cyan-400 flex items-center justify-center mb-5 group-hover:scale-110 transition-transform">
                    <i data-lucide="smartphone" class="w-6 h-6"></i>
                </div>
                <h3 class="text-base font-bold text-white mb-2">Pesan Cepat Online</h3>
                <p class="text-xs text-stone-400 leading-relaxed">Pesan via website dari kelas atau perpustakaan. Tinggal ambil saat siap tanpa buang waktu antre.</p>
            </div>

            <div class="bg-[#0e131d]/80 rounded-2xl p-6 border border-stone-800/80 hover:border-amber-500/30 transition-all duration-300 group">
                <div class="w-12 h-12 rounded-xl bg-purple-500/10 border border-purple-500/20 text-purple-400 flex items-center justify-center mb-5 group-hover:scale-110 transition-transform">
                    <i data-lucide="zap" class="w-6 h-6"></i>
                </div>
                <h3 class="text-base font-bold text-white mb-2">Layanan Kilat</h3>
                <p class="text-xs text-stone-400 leading-relaxed">Didukung sistem POS dan alur operasional kasir cepat, pesanan langsung diproses barista handal.</p>
            </div>
        </div>
    </div>
</section>

<!-- PRODUCT HIGHLIGHT SECTION -->
<section class="py-20 bg-[#090c12]">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-end mb-10 gap-4">
            <div>
                <span class="text-xs uppercase tracking-widest text-amber-400 font-bold">Menu Pilihan</span>
                <h2 class="text-3xl font-extrabold text-white mt-1 tracking-tight">Katalog Menu Favorit</h2>
                <p class="mt-1 text-sm text-stone-400">Minuman dan kudapan yang paling sering dipesan oleh mahasiswa.</p>
            </div>
            <a href="customer/menu.php" class="text-amber-400 font-semibold hover:text-amber-300 text-sm flex items-center gap-1.5 transition-colors">
                Lihat semua menu <i data-lucide="arrow-right" class="w-4 h-4"></i>
            </a>
        </div>

        <?php
        require_once "config.php";
        $featured = $conn->query(
            "SELECT p.*, c.name as cat_name FROM products p
             JOIN categories c ON p.category_id = c.id
             WHERE p.is_available = 1 LIMIT 4"
        );
        ?>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
            <?php if ($featured && $featured->num_rows > 0): ?>
                <?php
                $i = 0;
                while ($p = $featured->fetch_assoc()):
                    $i++;
                ?>
                <div class="bg-[#0e131d]/90 rounded-2xl overflow-hidden border border-stone-800/80 hover:border-amber-500/40 transition-all duration-300 hover:-translate-y-1 group flex flex-col justify-between">
                    <div>
                        <!-- Product Image Area -->
                        <div class="h-44 bg-[#141b29] flex items-center justify-center relative overflow-hidden">
                            <?php if (!empty($p['image']) && file_exists("uploads/products/" . $p['image'])): ?>
                                <img src="uploads/products/<?= htmlspecialchars($p['image']) ?>" alt="<?= htmlspecialchars($p['name']) ?>" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
                            <?php else: ?>
                                <div class="w-16 h-16 rounded-2xl bg-amber-500/10 border border-amber-500/20 text-amber-400 flex items-center justify-center">
                                    <i data-lucide="coffee" class="w-8 h-8"></i>
                                </div>
                            <?php endif; ?>
                            <div class="absolute top-3 left-3 bg-[#090c12]/90 border border-stone-800 text-amber-400 text-[11px] font-bold px-2.5 py-1 rounded-full flex items-center gap-1">
                                <i data-lucide="flame" class="w-3 h-3 text-amber-400"></i> Favorit #<?= $i ?>
                            </div>
                        </div>
                        <div class="p-5">
                            <span class="text-[10px] font-bold text-amber-400 uppercase tracking-wider block mb-1"><?= htmlspecialchars($p["cat_name"]) ?></span>
                            <h3 class="text-base font-bold text-white mb-1.5 group-hover:text-amber-300 transition-colors"><?= htmlspecialchars($p["name"]) ?></h3>
                            <p class="text-xs text-stone-400 line-clamp-2 leading-relaxed mb-4"><?= htmlspecialchars($p["description"] ?? "") ?></p>
                        </div>
                    </div>
                    <div class="p-5 pt-0 border-t border-stone-800/60 mt-auto flex items-center justify-between">
                        <div>
                            <span class="text-[10px] text-stone-500 block">Harga</span>
                            <span class="text-base font-bold font-mono text-white">Rp <?= number_format($p["price"], 0, ',', '.') ?></span>
                        </div>
                        <a href="customer/menu.php" class="bg-amber-500 hover:bg-amber-400 text-stone-950 text-xs font-bold px-3.5 py-2 rounded-lg transition-all flex items-center gap-1">
                            Pesan
                        </a>
                    </div>
                </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="col-span-4 text-center py-16 bg-[#0e131d]/60 rounded-2xl border border-stone-800">
                    <i data-lucide="coffee" class="w-12 h-12 text-stone-600 mx-auto mb-3"></i>
                    <p class="text-stone-400 text-sm">Menu akan segera hadir. Pastikan database sudah terisi.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- CTA SECTION -->
<section class="py-16 bg-gradient-to-r from-amber-950/60 via-[#141b29] to-amber-950/60 border-t border-stone-800/80">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h2 class="text-3xl font-extrabold text-white sm:text-4xl mb-4 tracking-tight">
            Siap Menikmati Kopi Berkualitas? ☕
        </h2>
        <p class="text-sm sm:text-base text-stone-300 mb-8 max-w-xl mx-auto">
            Daftarkan akun sekarang, pesan racikan kopi favoritmu, dan nikmati waktu produktif bersama Kopi Mahasiswa.
        </p>
        <div class="flex flex-wrap gap-4 justify-center">
            <a href="register.php" class="bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-400 hover:to-amber-500 text-stone-950 font-bold px-8 py-3 rounded-xl transition-all shadow-lg shadow-amber-950/50">
                Daftar &amp; Pesan Sekarang
            </a>
            <a href="login.php" class="bg-[#1a2233] text-stone-200 hover:text-white font-semibold px-8 py-3 rounded-xl transition-all border border-stone-700 hover:border-stone-600">
                Masuk ke Akun
            </a>
        </div>
    </div>
</section>

<?php include "components/footer.php"; ?>
