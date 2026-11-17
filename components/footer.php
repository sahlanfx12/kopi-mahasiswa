    </main>
    <footer class="border-t border-stone-800/80 bg-[#0a0d14] text-stone-400 mt-auto">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8 mb-8">
                <!-- Brand -->
                <div>
                    <div class="flex items-center gap-2.5 mb-3">
                        <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-amber-500 to-amber-700 flex items-center justify-center text-stone-950 font-bold">
                            <i data-lucide="coffee" class="w-4 h-4"></i>
                        </div>
                        <span class="font-extrabold text-white text-lg tracking-tight">Kopi Mahasiswa</span>
                    </div>
                    <p class="text-xs text-stone-400 leading-relaxed max-w-sm">
                        Platform coffee shop terintegrasi untuk mahasiswa. Kopi artisanal berkualitas tinggi, atmosfer produktif, dan sistem pemesanan modern.
                    </p>
                </div>
                <!-- Navigation -->
                <div>
                    <h4 class="text-white text-xs font-bold uppercase tracking-wider mb-3 text-stone-300">Navigasi Utama</h4>
                    <ul class="space-y-2 text-xs">
                        <li><a href="index.php" class="hover:text-amber-400 transition-colors flex items-center gap-1.5"><i data-lucide="chevron-right" class="w-3.5 h-3.5 text-stone-600"></i> Beranda</a></li>
                        <li><a href="menu.php" class="hover:text-amber-400 transition-colors flex items-center gap-1.5"><i data-lucide="chevron-right" class="w-3.5 h-3.5 text-stone-600"></i> Katalog Produk</a></li>
                        <li><a href="login.php" class="hover:text-amber-400 transition-colors flex items-center gap-1.5"><i data-lucide="chevron-right" class="w-3.5 h-3.5 text-stone-600"></i> Portal Masuk</a></li>
                        <li><a href="register.php" class="hover:text-amber-400 transition-colors flex items-center gap-1.5"><i data-lucide="chevron-right" class="w-3.5 h-3.5 text-stone-600"></i> Registrasi Akun</a></li>
                    </ul>
                </div>
                <!-- Info -->
                <div>
                    <h4 class="text-white text-xs font-bold uppercase tracking-wider mb-3 text-stone-300">Kontak & Operasional</h4>
                    <ul class="space-y-2 text-xs text-stone-400">
                        <li class="flex items-center gap-2"><i data-lucide="map-pin" class="w-4 h-4 text-amber-500 flex-shrink-0"></i> Gedung Kampus Utama, Lantai 1</li>
                        <li class="flex items-center gap-2"><i data-lucide="clock" class="w-4 h-4 text-amber-500 flex-shrink-0"></i> Senin &ndash; Jumat: 07.00 &ndash; 21.00 WIB</li>
                        <li class="flex items-center gap-2"><i data-lucide="phone" class="w-4 h-4 text-amber-500 flex-shrink-0"></i> 0812-3456-7890</li>
                        <li class="flex items-center gap-2"><i data-lucide="mail" class="w-4 h-4 text-amber-500 flex-shrink-0"></i> hello@kopimahasiswa.com</li>
                    </ul>
                </div>
            </div>

            <!-- Bottom Bar with exact Developer Watermark -->
            <div class="border-t border-stone-800/80 pt-6 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs">
                <div class="text-stone-500">
                    &copy; <?= date('Y') ?> All rights reserved.
                </div>
                <div class="text-xs text-stone-400 font-medium tracking-wider app-watermark">
                    Built by Sahlan
                </div>
            </div>
        </div>
    </footer>
    <script>
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
    </script>
</body>
</html>
