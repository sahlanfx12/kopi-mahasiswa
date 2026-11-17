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
    $contact = trim((string)($_POST["contact_name"] ?? ""));
    $phone = trim((string)($_POST["phone"] ?? ""));
    if ($name === "" || $contact === "" || $phone === "") {
        $_SESSION["ui_notification"] = ["type" => "error", "message" => "Nama supplier, nama kontak, dan nomor telepon wajib diisi."];
    } else {
        try {
            $stmt = $conn->prepare("INSERT INTO suppliers (name, contact_name, phone) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $name, $contact, $phone);
            if (!$stmt->execute()) throw new RuntimeException("Supplier insert failed.");
            $_SESSION["ui_notification"] = ["type" => "success", "message" => "Mitra supplier berhasil ditambahkan."];
        } catch (Throwable $e) {
            error_log("Admin supplier creation failed");
            $_SESSION["ui_notification"] = ["type" => "error", "message" => "Mitra supplier belum berhasil ditambahkan. Silakan coba kembali."];
        }
    }
    header("Location: suppliers.php");
    exit;
}
$sups = $conn->query("SELECT * FROM suppliers ORDER BY id DESC");
include "header.php";
?>

<div class="mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
    <div>
        <h1 class="text-2xl font-extrabold text-white tracking-tight">Mitra Supplier</h1>
        <p class="text-xs text-stone-400 mt-1">Daftar pemasok bahan baku, biji kopi, dan logistik Kopi Mahasiswa.</p>
    </div>
</div>

<!-- Add Supplier Form -->
<form method="POST" data-loading class="bg-[#0e131d]/90 border border-stone-800/80 p-5 rounded-2xl shadow-xl mb-6 flex flex-col lg:flex-row gap-3">
    <?php if(function_exists("csrf_input")) echo csrf_input(); ?>
    <div class="relative flex-1">
        <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 pointer-events-none text-stone-500">
            <i data-lucide="building" class="w-4 h-4"></i>
        </span>
        <input type="text" name="name" placeholder="Nama Perusahaan / Supplier" required
               class="w-full bg-[#131926] border border-stone-800 focus:border-amber-500 focus:ring-1 focus:ring-amber-500 rounded-xl pl-10 pr-4 py-2.5 text-sm text-stone-200 placeholder-stone-600 transition-all outline-none">
    </div>
    <div class="relative flex-1">
        <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 pointer-events-none text-stone-500">
            <i data-lucide="user" class="w-4 h-4"></i>
        </span>
        <input type="text" name="contact_name" placeholder="Nama Contact Person" required
               class="w-full bg-[#131926] border border-stone-800 focus:border-amber-500 focus:ring-1 focus:ring-amber-500 rounded-xl pl-10 pr-4 py-2.5 text-sm text-stone-200 placeholder-stone-600 transition-all outline-none">
    </div>
    <div class="relative flex-1">
        <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 pointer-events-none text-stone-500">
            <i data-lucide="phone" class="w-4 h-4"></i>
        </span>
        <input type="text" name="phone" placeholder="Nomor Telepon / WA" required
               class="w-full bg-[#131926] border border-stone-800 focus:border-amber-500 focus:ring-1 focus:ring-amber-500 rounded-xl pl-10 pr-4 py-2.5 text-sm text-stone-200 placeholder-stone-600 transition-all outline-none">
    </div>
    <button type="submit" class="bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-400 hover:to-amber-500 text-stone-950 font-bold px-6 py-2.5 rounded-xl text-sm transition-all shadow-md shadow-amber-950/40 flex items-center justify-center gap-1.5 whitespace-nowrap">
        <i data-lucide="plus" class="w-4 h-4"></i> Tambah Mitra
    </button>
</form>

<!-- Suppliers Table -->
<div class="bg-[#0e131d]/90 border border-stone-800/80 rounded-2xl overflow-hidden shadow-xl">
    <div class="p-4 border-b border-stone-800/80 flex items-center justify-between">
        <div class="flex items-center gap-2">
            <i data-lucide="truck" class="w-4 h-4 text-amber-400"></i>
            <h2 class="text-xs font-bold text-white uppercase tracking-wider">Daftar Supplier Aktif</h2>
        </div>
        <span class="text-xs text-stone-500 font-mono"><?= $sups->num_rows ?> Mitra Terdaftar</span>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-left text-xs">
            <thead class="bg-[#141b29] text-stone-400 uppercase tracking-wider text-[11px]">
                <tr>
                    <th class="p-4">Nama Supplier</th>
                    <th class="p-4">Kontak Person</th>
                    <th class="p-4">Telepon / WhatsApp</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-800/60">
                <?php while($s = $sups->fetch_assoc()): ?>
                <tr class="hover:bg-stone-800/30 transition-colors">
                    <td class="p-4 font-bold text-white flex items-center gap-2">
                        <div class="w-8 h-8 rounded-lg bg-amber-500/10 text-amber-400 flex items-center justify-center">
                            <i data-lucide="building-2" class="w-4 h-4"></i>
                        </div>
                        <?= htmlspecialchars($s['name']) ?>
                    </td>
                    <td class="p-4 text-stone-300 font-medium"><?= htmlspecialchars($s['contact_name']) ?></td>
                    <td class="p-4 text-stone-400 font-mono"><?= htmlspecialchars($s['phone']) ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include "footer.php"; ?>