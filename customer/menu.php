<?php
require "../config.php";
include "header.php";

$search = isset($_GET["search"]) ? $conn->real_escape_string($_GET["search"]) : "";
$category_filter = isset($_GET["category"]) ? (int)$_GET["category"] : 0;
$sort = isset($_GET["sort"]) ? $_GET["sort"] : "";

$query = "SELECT p.*, c.name as category_name
          FROM products p
          LEFT JOIN categories c ON p.category_id = c.id
          WHERE p.is_available = 1";

if ($search) {
    $query .= " AND p.name LIKE '%$search%'";
}
if ($category_filter > 0) {
    $query .= " AND p.category_id = $category_filter";
}

if ($sort === "price_asc") {
    $query .= " ORDER BY p.price ASC";
} elseif ($sort === "price_desc") {
    $query .= " ORDER BY p.price DESC";
} else {
    $query .= " ORDER BY p.id DESC";
}

$products = $conn->query($query);
$categories = $conn->query("SELECT * FROM categories ORDER BY name ASC");
?>

<div class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
    <!-- Header & Filter Bar -->
    <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center mb-8 gap-4">
        <div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-white tracking-tight">Katalog Menu Kopi &amp; Kudapan</h1>
            <p class="text-xs text-stone-400 mt-1">Pilih minuman favorit kamu, racik sesuai selera, dan masukkan ke keranjang.</p>
        </div>

        <form method="GET" class="flex flex-wrap gap-2 w-full lg:w-auto">
            <div class="relative flex-grow sm:flex-grow-0">
                <span class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-stone-500">
                    <i data-lucide="search" class="w-3.5 h-3.5"></i>
                </span>
                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Cari kopi..."
                       class="bg-[#131926] border border-stone-800 focus:border-amber-500 rounded-xl pl-9 pr-3 py-2 text-xs text-stone-200 placeholder-stone-600 outline-none w-full sm:w-44">
            </div>

            <select name="category" class="bg-[#131926] border border-stone-800 focus:border-amber-500 rounded-xl px-3 py-2 text-xs text-stone-200 outline-none">
                <option value="0">Semua Kategori</option>
                <?php while($cat = $categories->fetch_assoc()): ?>
                    <option value="<?= $cat["id"] ?>" <?= $category_filter == $cat["id"] ? "selected" : "" ?>>
                        <?= htmlspecialchars($cat["name"]) ?>
                    </option>
                <?php endwhile; ?>
            </select>

            <select name="sort" class="bg-[#131926] border border-stone-800 focus:border-amber-500 rounded-xl px-3 py-2 text-xs text-stone-200 outline-none">
                <option value="">Terbaru</option>
                <option value="price_asc" <?= $sort == "price_asc" ? "selected" : "" ?>>Harga: Terendah</option>
                <option value="price_desc" <?= $sort == "price_desc" ? "selected" : "" ?>>Harga: Tertinggi</option>
            </select>

            <button type="submit" class="bg-[#1a2233] hover:bg-[#232d42] text-stone-200 border border-stone-700 px-4 py-2 rounded-xl text-xs font-semibold transition-all">
                Filter
            </button>
        </form>
    </div>

    <!-- Product Grid (Bybit Trading Style) -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
        <?php if($products->num_rows > 0): ?>
            <?php while($p = $products->fetch_assoc()):
                $imgPath = (!empty($p["image"]) && file_exists("../uploads/products/" . $p["image"])) ? "../uploads/products/" . htmlspecialchars($p["image"]) : "";
            ?>
            <div class="bg-[#0e131d]/90 rounded-2xl border border-stone-800/80 hover:border-amber-500/40 overflow-hidden flex flex-col justify-between shadow-xl transition-all duration-300 hover:-translate-y-1 group">
                <div>
                    <!-- Product Image Container -->
                    <div class="h-48 bg-[#141b29] flex items-center justify-center relative overflow-hidden">
                        <?php if ($imgPath): ?>
                            <a href="detail.php?id=<?= $p["id"] ?>" class="w-full h-full block">
                                <img src="<?= $imgPath ?>" class="object-cover w-full h-full group-hover:scale-105 transition-transform duration-300" alt="<?= htmlspecialchars($p["name"]) ?>">
                            </a>
                        <?php else: ?>
                            <a href="detail.php?id=<?= $p["id"] ?>" class="w-full h-full flex items-center justify-center text-stone-500">
                                <i data-lucide="coffee" class="w-10 h-10 text-stone-600"></i>
                            </a>
                        <?php endif; ?>

                        <div class="absolute top-3 left-3 bg-[#090c12]/90 border border-stone-800 text-amber-400 text-[10px] font-bold px-2.5 py-0.5 rounded-full uppercase tracking-wider">
                            <?= htmlspecialchars($p["category_name"] ?? "Kopi") ?>
                        </div>
                    </div>

                    <!-- Product Info -->
                    <div class="p-5">
                        <h3 class="text-base font-bold text-white group-hover:text-amber-300 transition-colors">
                            <a href="detail.php?id=<?= $p["id"] ?>"><?= htmlspecialchars($p["name"]) ?></a>
                        </h3>
                        <p class="text-xs text-stone-400 mt-1 line-clamp-2 leading-relaxed">
                            <?= htmlspecialchars($p["description"] ?? "") ?>
                        </p>
                    </div>
                </div>

                <div class="p-5 pt-0">
                    <div class="flex items-center justify-between mb-3 pt-3 border-t border-stone-800/60">
                        <span class="text-[11px] text-stone-500 uppercase">Harga</span>
                        <span class="font-bold font-mono text-base text-amber-400">Rp <?= number_format($p["price"], 0, ',', '.') ?></span>
                    </div>

                    <form action="cart_action.php" method="POST" data-loading>
                        <?php if(function_exists("csrf_input")) echo csrf_input(); ?>
                        <input type="hidden" name="action" value="add">
                        <input type="hidden" name="product_id" value="<?= $p["id"] ?>">
                        <button type="submit" class="w-full bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-400 hover:to-amber-500 text-stone-950 py-2.5 rounded-xl font-bold text-xs transition-all shadow-md shadow-amber-950/40 flex items-center justify-center gap-1.5 active:scale-95">
                            <i data-lucide="plus" class="w-4 h-4"></i> Tambah ke Cart
                        </button>
                    </form>
                </div>
            </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="col-span-full text-center py-16 bg-[#0e131d]/60 rounded-2xl border border-stone-800 text-stone-500">
                <i data-lucide="search-x" class="w-12 h-12 mx-auto mb-3 text-stone-600"></i>
                <p class="text-sm font-medium text-white">Produk tidak ditemukan.</p>
                <p class="text-xs text-stone-400 mt-1">Coba kata kunci lain atau pilih semua kategori.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include "footer.php"; ?>
