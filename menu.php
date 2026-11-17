<?php include "components/header.php"; ?>

<!-- PRODUCT HEADER -->
<section class="bg-gradient-to-r from-stone-900 to-amber-950 text-white py-12">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <h1 class="text-3xl font-extrabold mb-2">Menu Kami 🍵</h1>
        <p class="text-amber-200 text-sm">Pilih yang terbaik, nikmati setiap tegukan</p>
    </div>
</section>

<?php
require_once "config.php";

$search = isset($_GET["search"]) ? $conn->real_escape_string(strip_tags($_GET["search"])) : "";
$category_filter = isset($_GET["category"]) ? (int)$_GET["category"] : 0;
$sort = isset($_GET["sort"]) ? $_GET["sort"] : "";

$query = "SELECT p.*, c.name as category_name, s.current_stock
          FROM products p
          LEFT JOIN categories c ON p.category_id = c.id
          LEFT JOIN stock s ON p.id = s.product_id
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
    <!-- Filter Form -->
    <form method="GET" class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4 mb-8 flex flex-wrap gap-3 items-center">
        <div class="relative flex-grow min-w-[200px]">
            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">🔍</span>
            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>"
                   placeholder="Cari kopi atau makanan..."
                   class="border border-gray-200 rounded-xl pl-9 pr-4 py-2.5 text-sm w-full focus:outline-none focus:ring-2 focus:ring-amber-400">
        </div>

        <select name="category" class="border border-gray-200 rounded-xl px-4 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-amber-400 min-w-[160px]">
            <option value="0">Semua Kategori</option>
            <?php while($cat = $categories->fetch_assoc()): ?>
                <option value="<?= $cat["id"] ?>" <?= $category_filter == $cat["id"] ? "selected" : "" ?>>
                    <?= htmlspecialchars($cat["name"]) ?>
                </option>
            <?php endwhile; ?>
        </select>

        <select name="sort" class="border border-gray-200 rounded-xl px-4 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-amber-400 min-w-[180px]">
            <option value="">Terbaru</option>
            <option value="price_asc" <?= $sort == "price_asc" ? "selected" : "" ?>>Harga: Rendah ke Tinggi</option>
            <option value="price_desc" <?= $sort == "price_desc" ? "selected" : "" ?>>Harga: Tinggi ke Rendah</option>
        </select>

        <button type="submit" class="bg-amber-700 text-white px-6 py-2.5 rounded-xl text-sm font-medium hover:bg-amber-800 transition-colors">
            Filter
        </button>

        <?php if ($search || $category_filter || $sort): ?>
        <a href="menu.php" class="text-gray-500 text-sm hover:text-gray-700 px-3 py-2.5 rounded-xl hover:bg-gray-100 transition-colors">
            Reset
        </a>
        <?php endif; ?>
    </form>

    <!-- Product Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
        <?php if($products && $products->num_rows > 0): ?>
            <?php while($p = $products->fetch_assoc()):
                $stok = (int)($p["current_stock"] ?? 0);
                $habis = $stok <= 0;
            ?>
            <div class="bg-white rounded-2xl overflow-hidden shadow-sm border border-gray-100 hover:shadow-lg transition-all duration-300 flex flex-col <?= $habis ? 'opacity-70' : 'hover:-translate-y-1' ?>">
                <!-- Image Area dengan SVG fallback -->
                <div class="h-48 relative overflow-hidden bg-gradient-to-br from-amber-50 to-amber-100 flex items-center justify-center">
                    <?php if ($p["image"] && file_exists("../" . $p["image"])): ?>
                        <img src="../<?= htmlspecialchars($p["image"]) ?>" alt="<?= htmlspecialchars($p["name"]) ?>" class="object-cover w-full h-full">
                    <?php else: ?>
                        <!-- SVG Placeholder berdasarkan kategori -->
                        <?php
                        $cat_id = $p["category_id"] ?? 1;
                        $emoji = '☕';
                        if ($cat_id == 4) $emoji = '🥐';
                        elseif ($cat_id == 5) $emoji = '🫘';
                        elseif ($cat_id == 3) $emoji = '🍵';
                        ?>
                        <div class="flex flex-col items-center justify-center h-full w-full">
                            <span class="text-5xl"><?= $emoji ?></span>
                            <span class="text-xs text-amber-600 mt-2 font-medium"><?= htmlspecialchars($p["category_name"]) ?></span>
                        </div>
                    <?php endif; ?>

                    <!-- Status Badge -->
                    <?php if ($habis): ?>
                        <div class="absolute inset-0 bg-black/50 flex items-center justify-center">
                            <span class="bg-red-600 text-white text-sm font-bold px-4 py-2 rounded-full">Stok Habis</span>
                        </div>
                    <?php elseif ($stok <= 10): ?>
                        <div class="absolute top-3 right-3">
                            <span class="bg-orange-500 text-white text-xs font-bold px-2 py-1 rounded-full">Sisa <?= $stok ?></span>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Product Info -->
                <div class="p-4 flex-grow flex flex-col">
                    <span class="text-xs font-semibold text-amber-600 uppercase tracking-wider mb-1"><?= htmlspecialchars($p["category_name"]) ?></span>
                    <h3 class="text-base font-bold text-gray-900 mb-1"><?= htmlspecialchars($p["name"]) ?></h3>
                    <p class="text-xs text-gray-400 flex-grow line-clamp-2 mb-3"><?= htmlspecialchars($p["description"]) ?></p>

                    <div class="flex items-center justify-between mt-auto">
                        <span class="text-lg font-extrabold text-gray-900">Rp <?= number_format($p["price"], 0, ',', '.') ?></span>
                    </div>

                    <?php if (!$habis): ?>
                    <form action="cart_action.php" method="POST" class="mt-3">
                        <input type="hidden" name="action" value="add">
                        <input type="hidden" name="product_id" value="<?= $p["id"] ?>">
                        <button type="submit" class="w-full bg-amber-700 text-white py-2.5 rounded-xl hover:bg-amber-800 transition-colors font-semibold text-sm flex items-center justify-center gap-2">
                            🛒 Tambah ke Cart
                        </button>
                    </form>
                    <?php else: ?>
                    <button disabled class="mt-3 w-full bg-gray-200 text-gray-400 py-2.5 rounded-xl font-semibold text-sm cursor-not-allowed">
                        Stok Habis
                    </button>
                    <?php endif; ?>
                </div>
            </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="col-span-full text-center py-20">
                <div class="text-5xl mb-4">🔍</div>
                <h3 class="text-lg font-semibold text-gray-700 mb-2">Produk tidak ditemukan</h3>
                <p class="text-gray-400 text-sm mb-4">Coba dengan kata kunci atau filter yang berbeda</p>
                <a href="menu.php" class="text-amber-700 hover:underline text-sm font-medium">Reset filter →</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include "components/footer.php"; ?>
