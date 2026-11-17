# Laporan Testing Kopi Mahasiswa — Tahap 3

## 1. Tujuan

Menambah bukti otomatis untuk helper yang benar-benar ada, pemeriksaan awal endpoint chatbot lima role, dan helper notifikasi berbasis DB. Laporan ini membedakan test yang dijalankan dari checklist manual dan alur yang belum mempunyai harness aman.

## 2. Lingkungan pengujian

| Komponen | Lingkungan aktual |
|---|---|
| OS | Windows lokal melalui Laragon |
| PHP CLI | 8.3.33 |
| Composer | 2.10.2 |
| PHPUnit | 10.5.67 |
| Database lokal | MySQL Laragon 8.4.3, `127.0.0.1:3306`, datadir `C:\laragon\data\mysql-8.4` |
| API eksternal | Tidak dipanggil |
| Database aplikasi utama | Tidak disentuh |

Dependency dikunci secara sah di `composer.lock`. CI memakai `composer install` agar menjalankan versi yang sama. Script `composer test` memakai `php vendor/bin/phpunit` agar berfungsi pada Windows dan Linux.

## 3. Hasil otomatis yang benar-benar dijalankan

Perintah:

```text
composer test
```

Hasil:

| Status | Jumlah |
|---|---:|
| PASS | 81 |
| FAIL | 0 |
| ERROR | 0 |
| SKIPPED | 0 |
| Total test | 81 |
| Assertions | 141 |

56 kasus lama tetap disertakan dan lulus. Suite Unit berisi 69 test dan 92 assertion (semua lulus). Suite Integration berisi 12 test dan 49 assertion (semua lulus): 10 test guard endpoint chatbot dan 2 test query Notification Center menggunakan database test.

Tidak ada klaim lulus untuk checkout, transaksi POS, perubahan stok, OTP DB, akses halaman berautentikasi, prompt/konteks yang dikirim ke provider, atau alur bisnis UI. Test chatbot yang lulus berhenti di guard metode/role sebelum memuat `config.php`, dan tidak memanggil provider.

## 4. Cakupan test yang tersedia

| Area | Test yang dijalankan | Hasil |
|---|---|---|
| Helper OTP lama | Normalisasi nomor, HMAC digest | Lulus; tidak menguji issue/verify DB, expiry, resend, limit, atau lock |
| Helper integrasi lama | Hint/masking secret, validasi secret, normalisasi nomor Fonnte | Lulus; tidak membuat request Fonnte |
| Helper notifikasi/status lama | Link aman, HTML escaping, label status | Lulus; belum menguji semua query/event database |
| AI service helper | Error dikenali secara eksak, sanitasi URL/credential, batas panjang, batas max token invalid | Lulus; tidak menghubungi provider |
| Enkripsi setting | Round-trip, purpose/AAD salah, ciphertext rusak | Lulus menggunakan key sintetis sementara |
| Endpoint AI Admin | GET ditolak; session role berbeda ditolak | Lulus |
| Endpoint AI Customer | GET ditolak; session role berbeda ditolak | Lulus |
| Endpoint AI Kasir | GET ditolak; session role berbeda ditolak | Lulus |
| Endpoint AI Supplier | GET ditolak; session role berbeda ditolak | Lulus |
| Endpoint AI Owner | GET ditolak; session role berbeda ditolak | Lulus |
| Notification center DB | Scope daftar/hitung dan tandai dibaca untuk dua ID sintetis | Lulus di `kopi_mahasiswa_test`; baris fixture di-rollback |

## 5. Test case per role

| Role | Otomatis lulus | Belum diuji otomatis |
|---|---|---|
| Admin | Endpoint AI menolak GET dan session role lain | Produk, kategori, stok, mutasi, supplier, PO, notifikasi DB, session Admin sah dan status akun, dashboard |
| Customer | Endpoint AI menolak GET dan session role lain | Katalog, keranjang, checkout/rollback, harga/stok, OTP end-to-end, riwayat, notifikasi, isolasi pesanan |
| Kasir | Endpoint AI menolak GET dan session role lain | POS, pembayaran, kembalian, transaksi/rollback, stok, status pesanan, struk, notifikasi |
| Supplier | Endpoint AI menolak GET dan session role lain | Isolasi PO, perubahan `ORDERED` → `SHIPPED`, notifikasi, akses portal |
| Owner | Endpoint AI menolak GET dan session role lain | Dashboard/KPI, laporan dan filter tanggal, cetak laporan, notifikasi |

Tes guard role salah menggunakan session sintetis tanpa DB dan berhenti sebelum aplikasi memuat koneksi DB. Tes tersebut tidak membuktikan akses positif untuk role sah atau pengecekan akun aktif/RBAC yang membutuhkan DB.

## 6. Test case chatbot lima role

Semua endpoint (`admin/ai_api.php`, `customer/ai_api.php`, `kasir/ai_api.php`, `supplier/ai_api.php`, `owner/ai_api.php`) diuji untuk method selain POST dan role session yang salah. Kedua skenario lulus untuk kelima endpoint.

Belum dijalankan: autentikasi role sah, status akun, izin RBAC, pembatasan history antar-user, isolasi konteks customer/supplier, validasi payload endpoint, persona/konteks yang benar-benar dikirim, respons JSON provider, timeout, fallback, maupun redaksi log saat respons provider nyata. Tidak ada kredensial yang dicetak dan semua test provider yang ada hanya menguji helper tanpa network.

## 7. Database test yang aman

Integration harness menerima hanya konfigurasi environment `KM_TEST_DB_*`, database tepat `kopi_mahasiswa_test`, dan host loopback. Ia memverifikasi `SELECT DATABASE()` sebelum operasi. Ia tidak memuat `config.php` aplikasi. Integration test membuat tabel notifikasi hanya jika belum ada dan memakai data sintetis di dalam transaksi yang di-rollback.

Schema `kopi_mahasiswa_test` dibuat pada MySQL Laragon yang teridentifikasi di port 3306. Akun `kopi_test`@`127.0.0.1` mendapat `USAGE` global bawaan dan `ALL PRIVILEGES` hanya untuk `kopi_mahasiswa_test`.*. Password testing dibuat acak di PowerShell, tidak dicetak atau disimpan ke repository; environment variable hanya berlaku selama proses test. Verifikasi sesudah test memilih schema `kopi_mahasiswa_test`, menemukan tabel `notifications`, dan mendapatkan 0 baris fixture tersisa.

Jangan memakai `config.php` atau menjalankan `database/schema.sql` mentah: file schema aplikasi secara eksplisit memilih `kopi_mahasiswa`. Test DB untuk test yang tersedia hanya memerlukan tabel notifikasi yang dibuat oleh test. Untuk integration bisnis lebih luas, sediakan schema test tersendiri dengan isi yang sudah diarahkan/ditinjau agar hanya memilih `kopi_mahasiswa_test`.

CI menjalankan MySQL 8.4 sebagai service sementara, membuat schema test dan akun khusus service, serta menghapus service bersama runner setelah job selesai. Credential di workflow adalah nilai dummy khusus service runner.

## 8. Checklist manual browser — belum dijalankan

Gunakan hanya akun sintetis dan lingkungan yang menunjuk ke DB test. Catat hasil per langkah; checklist ini bukan bukti lulus.

| ID | Role | Langkah | Hasil yang diharapkan | Status |
|---|---|---|---|---|
| MAN-ADM-01 | Admin | Login, buka dashboard, logout | Akses sesuai role dan logout menutup sesi | Belum dijalankan |
| MAN-ADM-02 | Admin | Tambah/edit/nonaktifkan produk dan kategori | Validasi, tampilan dan data DB test cocok | Belum dijalankan |
| MAN-ADM-03 | Admin | Catat stok masuk/keluar dan buka mutasi | Stok dan jejak mutasi konsisten; stok tidak negatif | Belum dijalankan |
| MAN-ADM-04 | Admin | Tambah supplier, buat PO, kirim/batalkan PO | Transisi sah dan notifikasi terkait tampil | Belum dijalankan |
| MAN-ADM-05 | Admin | Buka daftar/detail pesanan dan notifikasi | Data sesuai filter dan hanya dapat ditandai oleh pemilik | Belum dijalankan |
| MAN-ADM-06 | Admin | Chatbot: pertanyaan operasional, hapus history, payload kosong | Balasan/error dan history sesuai; provider hanya staging berizin | Belum dijalankan |
| MAN-CUS-01 | Customer | Login, navigasi katalog/detail, logout | Produk tersedia dan detail konsisten | Belum dijalankan |
| MAN-CUS-02 | Customer | Tambah/ubah/hapus keranjang lalu checkout | Total dari harga server; stok/order/item konsisten | Belum dijalankan |
| MAN-CUS-03 | Customer | Buka riwayat dan notifikasi | Hanya data akun sendiri terlihat; tandai dibaca bekerja | Belum dijalankan |
| MAN-CUS-04 | Customer | OTP valid, salah, kedaluwarsa, resend/limit | Status, masa berlaku dan batas percobaan sesuai aturan | Belum dijalankan |
| MAN-CUS-05 | Customer | Chatbot: katalog dan pertanyaan pesanan sendiri | Jawaban sesuai konteks akun; tidak menampilkan data akun lain | Belum dijalankan |
| MAN-KSR-01 | Kasir | Login, navigasi POS/transaksi, logout | Akses sesuai role | Belum dijalankan |
| MAN-KSR-02 | Kasir | POS dengan pembayaran pas, lebih, kurang | Total/kembalian tepat; pembayaran kurang ditolak | Belum dijalankan |
| MAN-KSR-03 | Kasir | Ubah status pesanan, buka/cetak ulang struk | Status dan struk sesuai transaksi | Belum dijalankan |
| MAN-KSR-04 | Kasir | Buka dan tandai notifikasi | Notifikasi akun sendiri diperbarui | Belum dijalankan |
| MAN-KSR-05 | Kasir | Chatbot: antrean dan produk tersedia | Jawaban sesuai data operasional; tidak membuat transaksi | Belum dijalankan |
| MAN-SUP-01 | Supplier | Login, navigasi portal dan logout | Hanya PO supplier terkait terlihat | Belum dijalankan |
| MAN-SUP-02 | Supplier | Konfirmasi pengiriman PO `ORDERED` | PO sendiri menjadi `SHIPPED`; PO lain tidak berubah | Belum dijalankan |
| MAN-SUP-03 | Supplier | Buka notifikasi dan chatbot | Data/notifikasi terbatas pada supplier terkait | Belum dijalankan |
| MAN-OWN-01 | Owner | Login dan periksa dashboard/KPI | KPI cocok dengan fixture transaksi completed | Belum dijalankan |
| MAN-OWN-02 | Owner | Filter dan cetak laporan penjualan | Rentang tanggal, agregat dan rincian konsisten | Belum dijalankan |
| MAN-OWN-03 | Owner | Buka notifikasi dan chatbot | Akses sesuai role; jawaban AI sesuai KPI/PO | Belum dijalankan |

## 9. CI dan validasi

- Workflow CI sekarang memasang dependency dari `composer.lock`, memverifikasi PHPUnit seri 10.5, dan menyalakan MySQL 8.4 disposable untuk integration test notifikasi.
- Workflow belum dijalankan pada GitHub dari sesi ini; status remote CI belum diketahui.
- `composer test` lulus lokal dengan PHPUnit 10.5.67: 81 test, 141 assertions, tanpa skip.
- PHP lint lulus untuk 89 file PHP proyek.
- `git diff --check` lulus.

## 10. Batasan dan kesimpulan

Pengujian checkout Customer, POS Kasir, inventory/PO Admin, OTP database, autentikasi role sah, KPI Owner, serta alur UI belum dijalankan. Endpoint bisnis memuat `config.php` secara langsung, sementara konfigurasi itu tidak dapat dioverride dengan environment; menjalankannya lokal tanpa harness bisa membuka koneksi DB yang salah. Oleh sebab itu bagian tersebut memerlukan harness/bootstrap test DB terisolasi yang diverifikasi sebelum dijalankan.

Chatbot tidak diubah. Semua request provider, Fonnte/WhatsApp, SMTP dan API eksternal tidak dilakukan. Database utama tidak disentuh. Hasil otomatis saat ini membuktikan 81 test pass, termasuk guard dasar kelima endpoint AI dan scope query Notification Center. Checkout Customer, POS Kasir, inventory/PO Admin, OTP database, autentikasi role sah, KPI Owner, serta checklist browser belum dijalankan.
