<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Forbidden');
}

$host = "localhost";
$user = "root";
$pass = "";

// Buat koneksi ke server MySQL tanpa database terlebih dahulu
$conn = new mysqli($host, $user, $pass);
if ($conn->connect_error) {
    die("<div style='color:red; font-family:sans-serif; padding:20px;'>Koneksi MySQL gagal: " . $conn->connect_error . "<br><br>Pastikan Laragon sudah dijalankan dan MySQL service aktif.</div>");
}

// Baca file schema.sql
$sqlFile = __DIR__ . "/database/schema.sql";
if (!file_exists($sqlFile)) {
    die("File database/schema.sql tidak ditemukan!");
}

$sql = file_get_contents($sqlFile);

// Eksekusi multi query
$success = true;
$errors = [];

if ($conn->multi_query($sql)) {
    do {
        // Mengkonsumsi hasil agar tidak error pada query berikutnya
        if ($result = $conn->store_result()) {
            $result->free();
        }
    } while ($conn->next_result());
} else {
    $success = false;
    $errors[] = $conn->error;
}

// Cek apakah ada error yang tersisa
if ($conn->error) {
    $success = false;
    $errors[] = $conn->error;
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Migrasi Database - Kopi Mahasiswa</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-amber-50 min-h-screen flex items-center justify-center">
    <div class="max-w-lg w-full mx-4">
        <div class="bg-white rounded-2xl shadow-lg p-8 text-center">
            <div class="text-5xl mb-4">☕</div>
            <h1 class="text-2xl font-bold text-amber-800 mb-2">Kopi Mahasiswa</h1>
            <h2 class="text-lg font-semibold mb-4 <?= $success ? 'text-green-700' : 'text-red-700' ?>">
                <?= $success ? '✅ Migrasi Database Berhasil!' : '❌ Migrasi Gagal' ?>
            </h2>

            <?php if ($success): ?>
            <div class="bg-green-50 border border-green-200 rounded-lg p-4 text-left mb-6 text-sm text-green-800">
                <p class="font-semibold mb-2">Database <code>kopi_mahasiswa</code> berhasil dibuat dengan:</p>
                <ul class="list-disc list-inside space-y-1">
                    <li>13 Tabel (roles, users, products, orders, payments, stock, dll)</li>
                    <li>5 Role: Owner, Admin, Kasir, Customer, Supplier</li>
                    <li>5 Kategori produk</li>
                    <li>10 Produk dengan stok awal</li>
                    <li>1 Supplier demo</li>
                    <li>Tidak ada akun bawaan. Jalankan <code>php setup_owner.php</code> untuk membuat Owner pertama.</li>
                </ul>
            </div>

            <?php else: ?>
            <div class="bg-red-50 border border-red-200 rounded-lg p-4 text-left mb-6 text-sm text-red-800">
                <p class="font-semibold mb-2">Error:</p>
                <?php foreach ($errors as $err): ?>
                    <p class="font-mono text-xs"><?= htmlspecialchars($err) ?></p>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <div class="flex gap-3 justify-center">
                <a href="index.php" class="bg-amber-700 text-white px-6 py-2 rounded-full font-medium hover:bg-amber-800 transition-colors text-sm">
                    Ke Halaman Utama
                </a>
                <?php if (!$success): ?>
                <a href="migrate.php" class="bg-gray-200 text-gray-700 px-6 py-2 rounded-full font-medium hover:bg-gray-300 transition-colors text-sm">
                    Coba Lagi
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
