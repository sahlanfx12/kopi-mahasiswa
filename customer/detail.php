<?php
require "../config.php";
include "header.php";

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$stmt = $conn->prepare("SELECT p.*, c.name as category_name, s.current_stock
                        FROM products p
                        LEFT JOIN categories c ON p.category_id = c.id
                        LEFT JOIN stock s ON p.id = s.product_id
                        WHERE p.id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();

if (!$product) {
    echo "<div class='max-w-7xl mx-auto py-16 px-4 text-center'>
            <div class='trade-card max-w-md mx-auto p-8 rounded-2xl'>
                <div class='w-16 h-16 rounded-2xl bg-amber-500/10 border border-amber-500/20 flex items-center justify-center mx-auto mb-4 text-amber-400'>
                    <i data-lucide='coffee' class='w-8 h-8'></i>
                </div>
                <h1 class='text-xl font-bold text-slate-100 mb-2'>Produk Tidak Ditemukan</h1>
                <p class='text-sm text-slate-400 mb-6'>Menu yang Anda cari mungkin telah dihapus atau belum tersedia.</p>
                <a href='menu.php' class='inline-flex items-center gap-2 bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-400 hover:to-amber-500 text-slate-950 font-bold px-6 py-2.5 rounded-xl text-sm transition-all'>
                    <i data-lucide='arrow-left' class='w-4 h-4'></i> Kembali ke Menu
                </a>
            </div>
          </div>";
    include "footer.php";
    exit;
}

$img = "";
if (!empty($product["image"])) {
    if (file_exists("../uploads/products/" . $product["image"])) {
        $img = "../uploads/products/" . $product["image"];
    } elseif (file_exists("../" . $product["image"])) {
        $img = "../" . $product["image"];
    }
}
$stock_qty = (int)($product['current_stock'] ?? 0);
?>

<div class="max-w-6xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
    <!-- Breadcrumb / Back Link -->
    <div class="mb-6">
        <a href="menu.php" class="inline-flex items-center gap-2 text-xs font-semibold text-slate-400 hover:text-amber-400 transition-colors">
            <i data-lucide="arrow-left" class="w-4 h-4"></i>
            Kembali ke Katalog Menu
        </a>
    </div>

    <!-- Product Detail Card -->
    <div class="trade-card rounded-2xl overflow-hidden border border-slate-800/80">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-0">
            <!-- Left: Product Image -->
            <div class="relative bg-[#131926] min-h-[360px] md:min-h-[460px] flex items-center justify-center p-8 border-b md:border-b-0 md:border-r border-slate-800/80">
                <?php if ($img): ?>
                    <img src="<?= htmlspecialchars($img) ?>" alt="<?= htmlspecialchars($product["name"]) ?>" class="w-full h-full max-h-[420px] object-cover rounded-xl shadow-2xl">
                <?php else: ?>
                    <div class="flex flex-col items-center justify-center text-slate-600">
                        <div class="w-24 h-24 rounded-2xl bg-[#0e131d] border border-slate-800 flex items-center justify-center mb-3">
                            <i data-lucide="coffee" class="w-12 h-12 stroke-1 text-slate-500"></i>
                        </div>
                        <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">Foto Tidak Tersedia</span>
                    </div>
                <?php endif; ?>

                <!-- Category Pill Overlay -->
                <div class="absolute top-4 left-4">
                    <span class="badge-glow bg-slate-950/80 backdrop-blur-md text-amber-400 border border-amber-500/30 px-3 py-1 rounded-full text-[11px] font-bold uppercase tracking-wider">
                        <?= htmlspecialchars($product['category_name'] ?? 'Menu Kopi') ?>
                    </span>
                </div>
            </div>

            <!-- Right: Product Info & Order Form -->
            <div class="p-6 sm:p-8 flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between gap-4 mb-2">
                        <span class="text-xs font-mono text-slate-500">Product Code: #<?= str_pad($product['id'], 4, '0', STR_PAD_LEFT) ?></span>
                        <?php if ($stock_qty > 0): ?>
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-bold bg-emerald-500/10 text-emerald-400 border border-emerald-500/30">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                                Tersedia (<?= $stock_qty ?> porsi)
                            </span>
                        <?php else: ?>
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-bold bg-rose-500/10 text-rose-400 border border-rose-500/30">
                                <span class="w-1.5 h-1.5 rounded-full bg-rose-400"></span>
                                Stok Habis
                            </span>
                        <?php endif; ?>
                    </div>

                    <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-100 tracking-tight mb-3">
                        <?= htmlspecialchars($product['name']) ?>
                    </h1>

                    <div class="text-3xl font-extrabold font-mono text-amber-400 mb-6 tracking-tight">
                        Rp <?= number_format($product['price'], 0, ',', '.') ?>
                    </div>

                    <!-- Description Box -->
                    <div class="bg-[#131926]/70 border border-slate-800/80 rounded-xl p-4 mb-6">
                        <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-2 flex items-center gap-1.5">
                            <i data-lucide="info" class="w-3.5 h-3.5 text-amber-400"></i>
                            Deskripsi Produk
                        </h3>
                        <p class="text-xs sm:text-sm text-slate-300 leading-relaxed">
                            <?= nl2br(htmlspecialchars($product['description'] ?? 'Racikan kopi istimewa dengan bahan biji kopi pilihan kualitas terbaik yang disajikan spesial untuk menemani aktivitas harian Anda.')) ?>
                        </p>
                    </div>

                    <!-- Highlights Feature Tags -->
                    <div class="grid grid-cols-2 gap-3 mb-6">
                        <div class="p-3 rounded-xl bg-[#131926]/40 border border-slate-800/60 flex items-center gap-2.5">
                            <i data-lucide="sparkles" class="w-4 h-4 text-amber-400 shrink-0"></i>
                            <div class="text-[11px] text-slate-300 font-medium">Biji Kopi Pilihan</div>
                        </div>
                        <div class="p-3 rounded-xl bg-[#131926]/40 border border-slate-800/60 flex items-center gap-2.5">
                            <i data-lucide="clock" class="w-4 h-4 text-emerald-400 shrink-0"></i>
                            <div class="text-[11px] text-slate-300 font-medium">Freshly Brewed</div>
                        </div>
                    </div>
                </div>

                <!-- Action Form -->
                <div class="pt-6 border-t border-slate-800/80">
                    <?php if ($stock_qty > 0): ?>
                        <form method="POST" action="cart_action.php" data-loading class="space-y-4">
                            <?php if (file_exists("../includes/csrf.php")) { require_once "../includes/csrf.php"; echo csrf_input(); } ?>
                            <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                            <input type="hidden" name="action" value="add">

                            <div class="flex items-center gap-4">
                                <div class="w-32">
                                    <label class="block text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-1.5">Kuantitas</label>
                                    <input type="number" name="qty" value="1" min="1" max="<?= $stock_qty ?>" class="w-full bg-[#131926] border border-slate-700/70 text-slate-100 rounded-xl px-3 py-2.5 text-center font-mono text-sm focus:border-amber-500 focus:outline-none transition-colors">
                                </div>
                                <div class="flex-1">
                                    <label class="block text-[11px] font-bold text-transparent mb-1.5 select-none">Order</label>
                                    <button type="submit" class="w-full bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-400 hover:to-amber-500 text-slate-950 font-bold py-2.5 px-4 rounded-xl shadow-lg shadow-amber-500/20 transition-all flex items-center justify-center gap-2 text-sm">
                                        <i data-lucide="shopping-bag" class="w-4 h-4"></i>
                                        Tambah ke Keranjang
                                    </button>
                                </div>
                            </div>
                        </form>
                    <?php else: ?>
                        <div class="p-4 rounded-xl bg-slate-900 border border-slate-800 text-center">
                            <p class="text-xs text-rose-400 font-semibold mb-1">Menu ini sedang habis</p>
                            <p class="text-[11px] text-slate-500">Silakan pilih menu kopi favorit lainnya yang tersedia.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include "footer.php"; ?>
