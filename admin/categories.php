<?php
require "../config.php"; require_once "../includes/csrf.php";
require_once "../includes/interaction_ui.php";

if (empty($_SESSION["user_id"]) || strtolower($_SESSION["role"] ?? "") !== "admin") {
    header("Location: ../login.php");
    exit;
}
$auth_stmt = $conn->prepare("SELECT status FROM users WHERE id = ? LIMIT 1");
$auth_user_id = (int)$_SESSION["user_id"];
$auth_stmt->bind_param("i", $auth_user_id);
$auth_stmt->execute();
$auth_user = $auth_stmt->get_result()->fetch_assoc();
if (($auth_user["status"] ?? "active") === "suspended") {
    session_destroy();
    header("Location: ../login.php?err=suspended");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name = trim((string)($_POST["name"] ?? ""));
    if ($name === "") {
        $_SESSION["ui_notification"] = ["type" => "error", "message" => "Nama kategori wajib diisi."];
    } else {
        try {
            $stmt = $conn->prepare("INSERT INTO categories (name) VALUES (?)");
            $stmt->bind_param("s", $name);
            if (!$stmt->execute()) throw new RuntimeException("Category insert failed.");
            $_SESSION["ui_notification"] = ["type" => "success", "message" => "Kategori berhasil ditambahkan."];
        } catch (Throwable $e) {
            error_log("Admin category creation failed");
            $_SESSION["ui_notification"] = ["type" => "error", "message" => "Kategori belum berhasil ditambahkan. Silakan coba kembali."];
        }
    }
    header("Location: categories.php");
    exit;
}
$cats = $conn->query("SELECT * FROM categories");
include "header.php";
?>

<div class="mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
    <div>
        <h1 class="text-2xl font-extrabold text-white tracking-tight">Kategori Produk</h1>
        <p class="text-xs text-stone-400 mt-1">Klasifikasi dan grup menu untuk katalog Kopi Mahasiswa.</p>
    </div>
</div>

<form method="POST" data-loading class="bg-[#0e131d]/90 border border-stone-800/80 p-5 rounded-2xl shadow-xl mb-6 flex flex-col sm:flex-row gap-3">
    <?php if(function_exists("csrf_input")) echo csrf_input(); ?>
    <div class="relative flex-grow">
        <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 pointer-events-none text-stone-500">
            <i data-lucide="tag" class="w-4 h-4"></i>
        </span>
        <input type="text" name="name" placeholder="Nama Kategori Baru (misal: Manual Brew)" required
               class="w-full bg-[#131926] border border-stone-800 focus:border-amber-500 focus:ring-1 focus:ring-amber-500 rounded-xl pl-10 pr-4 py-2.5 text-sm text-stone-200 placeholder-stone-600 transition-all outline-none">
    </div>
    <button type="submit" class="bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-400 hover:to-amber-500 text-stone-950 font-bold px-6 py-2.5 rounded-xl text-sm transition-all shadow-md shadow-amber-950/40 flex items-center justify-center gap-1.5 whitespace-nowrap">
        <i data-lucide="plus" class="w-4 h-4"></i> Tambah Kategori
    </button>
</form>

<div class="bg-[#0e131d]/90 border border-stone-800/80 rounded-2xl overflow-hidden shadow-xl">
    <div class="p-4 border-b border-stone-800/80 flex items-center gap-2">
        <i data-lucide="list" class="w-4 h-4 text-amber-400"></i>
        <h2 class="text-xs font-bold text-white uppercase tracking-wider">Daftar Kategori Tersedia</h2>
    </div>
    <table class="w-full text-left text-xs">
        <thead class="bg-[#141b29] text-stone-400 uppercase tracking-wider text-[11px]">
            <tr>
                <th class="p-4 w-20">ID</th>
                <th class="p-4">Nama Kategori</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-stone-800/60">
            <?php while($c = $cats->fetch_assoc()): ?>
            <tr class="hover:bg-stone-800/30 transition-colors">
                <td class="p-4 font-mono text-amber-400 font-bold">#<?= $c['id'] ?></td>
                <td class="p-4 font-medium text-white"><?= htmlspecialchars($c['name']) ?></td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>

<?php include "footer.php"; ?>