# Kopi Mahasiswa — UI/UX Design Handoff

Dokumentasi ini merangkum desain antarmuka berdasarkan pembacaan halaman yang sudah ada pada proyek Kopi Mahasiswa. Proyek asli tidak diubah; seluruh deliverable berada di folder ini.

## Isi folder

- `01-design-system.svg` — warna, tipografi, grid, radius, dan status yang mengikuti tema aplikasi.
- `02-ui-components.svg` — contoh navigasi, kartu metrik, tombol, input, badge status, dan tabel.
- `03-admin-dashboard.svg` — ringkasan operasional Admin.
- `04-customer-dashboard.svg` — dashboard pelanggan dan pintasan ke katalog/riwayat.
- `05-kasir-dashboard.svg` — ringkasan kasir dan pintasan POS/transaksi.
- `06-supplier-dashboard.svg` — profil supplier, ringkasan PO, alur status, dan tabel pesanan.
- `07-owner-dashboard.svg` — ringkasan bisnis, insight, produk terlaris, stok menipis, dan transaksi terbaru.
- `08-prototype-flow.svg` — peta alur navigasi antarmuka berdasarkan rute yang ditemukan.

## Impor SVG ke Figma

1. Buka Figma dan pilih atau buat file desain.
2. Pilih **File → Place image…** (atau seret file SVG dari File Explorer ke kanvas).
3. Pilih salah satu berkas `.svg`. Setiap berkas berisi satu artboard dengan ukuran yang tercantum di SVG.
4. Ulangi untuk artboard lain. Alternatifnya, buka SVG di browser, salin vektor, lalu tempel di Figma.
5. Untuk mengubah elemen, klik kanan hasil impor lalu pilih **Ungroup** bila tersedia. Struktur SVG tetap berupa vektor; hasil impor bukan komponen Figma yang sudah terhubung.
6. Untuk membuat prototype interaktif, susun frame hasil impor dan tambahkan konektor di tab **Prototype** mengikuti peta `08-prototype-flow.svg`.

## Rute dan batas cakupan

Halaman utama yang divisualkan:

- **Admin:** `admin/index.php` — metrik produk/pesanan/pengguna, pending order, stok menipis, pintasan pengelolaan, dan transaksi terbaru.
- **Customer:** `customer/index.php` — sambutan, katalog menu, serta riwayat/lacak pesanan.
- **Kasir:** `kasir/index.php` — penjualan hari ini, volume transaksi, order menunggu, POS, dan riwayat transaksi.
- **Supplier:** `supplier/index.php` — profil supplier, total PO per status, panduan status, dan tabel PO.
- **Owner:** `owner/index.php` — omzet, transaksi selesai, penjualan harian, stok, insight bisnis, produk terlaris, dan transaksi terbaru.

Alur lanjutan yang didokumentasikan hanya mencakup halaman yang ditemukan di source: katalog → keranjang → checkout → riwayat (Customer); dashboard → POS → proses pembayaran/struk → riwayat transaksi (Kasir); dashboard → PO → konfirmasi pengiriman (Supplier); dashboard → halaman operasional terkait (Admin); serta ringkasan → laporan penjualan (Owner). Login dan keluar ditampilkan sebagai batas sesi umum.

## Catatan desain

- Tampilan mengikuti tema gelap yang terlihat pada halaman proyek: latar `#090C12`, permukaan `#0E131D`/`#141B29`, aksen amber, dan teks bernuansa stone.
- Angka contoh adalah data ilustratif untuk mockup, bukan salinan data operasional atau data pengguna.
- Dashboard pada SVG bersifat representasi visual statis. Nilai, tabel, status, dan tombol tidak terhubung ke aplikasi.
- Fitur yang tidak tampak pada halaman sumber tidak ditambahkan sebagai alur desain. Halaman lain disebut di peta hanya bila tautan atau implementasinya ditemukan.

## Pemeriksaan

Seluruh SVG menggunakan XML SVG mandiri dengan `viewBox`, elemen vektor dasar, dan teks; tidak bergantung pada gambar eksternal atau font/icon package. Periksa file `.svg` saat impor bila font fallback di komputer Figma berbeda.
