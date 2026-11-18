# Kopi Mahasiswa

## Informasi Proyek

| Keterangan | Informasi |
|---|---|
| Nama | Muhammad Sahlan Habibi |
| NIM | 41257136 |
| Kelas | TI 1B |
| Semester | 3 |
| Mata kuliah | Rekayasa Perangkat Lunak |
| Tema proyek | Kopi Mahasiswa |
| Pengerjaan | Individu |
| Kelompok (jika diwajibkan) | Belum diketahui — isi sesuai arahan dosen |

## Deskripsi

Kopi Mahasiswa adalah aplikasi web untuk mendukung operasional kedai kopi. Aplikasi menyediakan alur kerja untuk pengelolaan produk dan stok, pemesanan pelanggan, transaksi kasir, pengadaan dari supplier, serta pemantauan dan pelaporan oleh owner.

### Lima Role

- **Admin:** mengelola produk, kategori, persediaan, supplier, purchase order, pesanan, dan notifikasi.
- **Customer:** melihat menu, mengelola keranjang, melakukan pemesanan, dan melihat riwayat pesanan.
- **Kasir:** memproses penjualan melalui POS, mengelola transaksi dan status pesanan, serta melihat atau mencetak struk.
- **Supplier:** melihat purchase order yang terkait dan memperbarui status pengiriman.
- **Owner:** memantau dashboard dan KPI serta melihat laporan penjualan.

### Chatbot AI

Aplikasi menyediakan chatbot AI untuk kelima role: Admin, Customer, Kasir, Supplier, dan Owner. Tiap role memiliki endpoint dan konteks bantuan yang disesuaikan dengan tugasnya. Koneksi chatbot memerlukan konfigurasi provider AI yang valid.

## Teknologi

- PHP Native 8.3
- MySQL dengan MySQLi
- Composer 2 untuk pengelolaan dependency
- PHPUnit 10.5 untuk automated test
- HTML, CSS, dan JavaScript untuk antarmuka web

## Instalasi dan Setup Lokal

### Persyaratan

- PHP 8.3 dengan ekstensi `mysqli`, `curl`, `openssl`, dan `mbstring`.
- MySQL yang kompatibel.
- Composer 2.

### Langkah Setup

1. Clone repository dan masuk ke folder proyek.
2. Pasang dependency yang versinya dikunci oleh `composer.lock`:

   ```sh
   composer install
   ```

3. Salin `config.example.php` menjadi `config.php`, lalu isi konfigurasi koneksi MySQL lokal. Schema aplikasi menggunakan database `kopi_mahasiswa`.
4. Dari terminal pada folder proyek, jalankan skrip migrasi CLI:

   ```sh
   php migrate.php
   php migrate_manager.php
   ```

5. Periksa perubahan yang diperlukan pada file di `database/migrations/` dan terapkan hanya migrasi yang belum ada pada database lokal.
6. Buat Owner pertama melalui terminal:

   ```sh
   php setup_owner.php
   ```

   Ikuti prompt untuk mengisi nama, email, dan password. Skrip ini hanya membuat Owner jika belum ada; tidak tersedia akun demo atau password default.
7. Jalankan aplikasi menggunakan web server PHP lokal, kemudian masuk dengan akun Owner yang dibuat.

`migrate.php`, `migrate_manager.php`, dan `setup_owner.php` adalah skrip CLI; jangan jadikan endpoint publik. Jangan commit `config.php`, password, API key, token, atau rahasia lain.

## Pengujian

Jalankan automated test dari root proyek:

```sh
composer test
```

Workflow GitHub Actions menggunakan PHP 8.3, Composer 2, PHPUnit 10.5, dan layanan MySQL sementara untuk integration test.

**Hasil CI untuk commit 552b777:** 81 PASS, 0 FAIL, 0 ERROR, 0 SKIPPED, 141 assertions.

[Lihat hasil GitHub Actions](https://github.com/sahlanfx12/kopi-mahasiswa/actions/runs/44108639683).

Cakupan automated test meliputi helper, guard metode dan role endpoint chatbot, serta query notifikasi pada database test. Hasil ini tidak menyatakan seluruh alur bisnis telah diuji end-to-end; alur UI seperti checkout, POS, dan proses bisnis lainnya perlu diperiksa terpisah.

## Video Presentasi Final

Satu video gabungan untuk presentasi final sesuai arahan dosen.

**URL video:** ((https://youtu.be/LELkGFb-1tI))
## Repository

[https://github.com/sahlanfx12/kopi-mahasiswa](https://github.com/sahlanfx12/kopi-mahasiswa)
