<?php
require "../config.php";
require_once "../includes/csrf.php";
include "header.php";

if (!isset($_SESSION["kasir_cart"])) $_SESSION["kasir_cart"] = [];

$search = $_GET["search"] ?? "";
$query = "SELECT p.*, s.current_stock FROM products p LEFT JOIN stock s ON p.id = s.product_id WHERE p.is_available = 1";
if ($search) $query .= " AND p.name LIKE '%" . $conn->real_escape_string($search) . "%'";
$products = $conn->query($query);

$cart_items = [];
$total = 0;
if (!empty($_SESSION["kasir_cart"])) {
    $ids = implode(",", array_keys($_SESSION["kasir_cart"]));
    $res = $conn->query("SELECT p.id, p.name, p.price, s.current_stock FROM products p LEFT JOIN stock s ON p.id = s.product_id WHERE p.id IN ($ids)");
    while ($r = $res->fetch_assoc()) {
        $qty = $_SESSION["kasir_cart"][$r["id"]];
        $r["qty"] = $qty;
        $r["subtotal"] = $qty * $r["price"];
        $total += $r["subtotal"];
        $cart_items[] = $r;
    }
}
?>

<div class="flex flex-col lg:flex-row gap-5 min-h-[calc(100vh-10rem)]">
    <!-- Left: Product Catalog Selection (2/3 width) -->
    <div class="w-full lg:w-2/3 bg-[#0e131d]/90 border border-stone-800/80 p-5 rounded-2xl shadow-xl flex flex-col">
        <!-- Search bar -->
        <form method="GET" class="mb-5 flex gap-2">
            <div class="relative flex-grow">
                <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 pointer-events-none text-stone-500">
                    <i data-lucide="search" class="w-4 h-4"></i>
                </span>
                <input type="text" id="searchInput" onkeyup="filterProducts()" name="search" value="<?= htmlspecialchars($search) ?>"
                       placeholder="Cari kopi, kudapan, atau menu kasir..."
                       class="w-full bg-[#131926] border border-stone-800 focus:border-amber-500 focus:ring-1 focus:ring-amber-500 rounded-xl pl-10 pr-4 py-2.5 text-sm text-stone-200 placeholder-stone-600 transition-all outline-none">
            </div>
            <button type="submit" class="bg-[#1a2233] hover:bg-[#232d42] text-stone-200 border border-stone-700 px-5 py-2.5 rounded-xl text-xs font-semibold transition-all">
                Cari
            </button>
        </form>

        <!-- Product Grid -->
        <div id="productGrid" class="overflow-y-auto flex-grow grid grid-cols-2 sm:grid-cols-3 gap-3.5 content-start pr-1 max-h-[600px]">
            <?php while ($p = $products->fetch_assoc()):
                $stock = (int)$p["current_stock"];
                $is_empty = ($stock <= 0);
            ?>
            <div class="product-card bg-[#131926]/90 border border-stone-800/80 hover:border-amber-500/40 rounded-xl p-3 flex flex-col justify-between transition-all duration-200 hover:-translate-y-0.5">
                <div>
                    <?php if (!empty($p["image"]) && file_exists("../uploads/products/" . $p["image"])): ?>
                        <img src="../uploads/products/<?= htmlspecialchars($p["image"]) ?>" class="w-full h-28 object-cover rounded-lg mb-2.5 border border-stone-800/80" alt="Product Image">
                    <?php else: ?>
                        <div class="w-full h-28 bg-[#1a2233] border border-stone-800/80 flex items-center justify-center text-stone-500 rounded-lg mb-2.5 text-xs font-medium">
                            <i data-lucide="coffee" class="w-6 h-6 text-stone-600"></i>
                        </div>
                    <?php endif; ?>

                    <div class="product-name font-bold text-xs sm:text-sm text-white mb-1 line-clamp-1"><?= htmlspecialchars($p["name"]) ?></div>
                    <div class="flex items-center justify-between text-[11px] mb-2 font-mono">
                        <span class="text-stone-400">Stok: <strong class="<?= $is_empty ? 'text-rose-400' : 'text-emerald-400' ?>"><?= $stock ?></strong></span>
                        <span class="text-amber-400 font-bold">Rp <?= number_format($p["price"],0,',','.') ?></span>
                    </div>
                </div>

                <form action="pos_action.php" method="POST" data-loading>
                    <?php if(function_exists("csrf_input")) echo csrf_input(); ?>
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="product_id" value="<?= $p["id"] ?>">
                    <button type="submit"
                            class="w-full py-1.5 rounded-lg text-xs font-bold transition-all flex items-center justify-center gap-1.5 <?= $is_empty ? 'bg-stone-800 text-stone-600 cursor-not-allowed' : 'bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-400 hover:to-amber-500 text-stone-950 shadow-md shadow-amber-950/40 active:scale-95' ?>"
                            <?= $is_empty ? 'disabled' : '' ?>>
                        <i data-lucide="plus" class="w-3.5 h-3.5"></i> <?= $is_empty ? 'Habis' : 'Tambah' ?>
                    </button>
                </form>
            </div>
            <?php endwhile; ?>
        </div>
    </div>

    <!-- Right: Order Cart & Payment Terminal (1/3 width) -->
    <div class="w-full lg:w-1/3 bg-[#0e131d]/90 border border-stone-800/80 p-5 rounded-2xl shadow-xl flex flex-col justify-between">
        <div>
            <div class="flex items-center justify-between pb-3.5 border-b border-stone-800/80 mb-4">
                <div class="flex items-center gap-2">
                    <i data-lucide="shopping-cart" class="w-4 h-4 text-amber-400"></i>
                    <h2 class="font-bold text-sm text-white uppercase tracking-wider">Keranjang Kasir</h2>
                </div>
                <span class="text-xs font-mono text-stone-400"><?= count($cart_items) ?> Item</span>
            </div>

            <!-- Print Receipt Alert If Last Order Success -->
            <?php if(isset($_SESSION["last_order_id"])): ?>
                <div class="bg-emerald-500/10 border border-emerald-500/30 text-emerald-300 px-4 py-3 rounded-xl text-xs mb-4">
                    <div class="font-bold mb-1 flex items-center gap-1.5">
                        <i data-lucide="check-circle" class="w-4 h-4 text-emerald-400"></i> Struk Siap Dicetak
                    </div>
                    <a href="print.php?id=<?= $_SESSION["last_order_id"] ?>" target="_blank"
                       class="inline-flex items-center gap-1.5 mt-2 bg-gradient-to-r from-emerald-500 to-emerald-600 text-stone-950 font-bold py-1.5 px-3.5 rounded-lg text-xs shadow-md transition-all hover:scale-105">
                        <i data-lucide="printer" class="w-3.5 h-3.5"></i> Cetak Struk #<?= $_SESSION["last_order_id"] ?>
                    </a>
                </div>
            <?php unset($_SESSION["last_order_id"]); endif; ?>

            <!-- Item List -->
            <div class="overflow-y-auto max-h-[300px] divide-y divide-stone-800/60 pr-1 mb-4">
                <?php if (empty($cart_items)): ?>
                    <div class="py-12 text-center text-stone-500 text-xs">
                        <i data-lucide="shopping-bag" class="w-8 h-8 mx-auto mb-2 text-stone-600"></i>
                        Keranjang transaksi masih kosong.
                    </div>
                <?php else: ?>
                    <?php foreach($cart_items as $item): ?>
                    <div class="py-2.5 flex justify-between items-center text-xs">
                        <div class="flex-grow pr-2">
                            <div class="font-bold text-white"><?= htmlspecialchars($item["name"]) ?></div>
                            <div class="text-[11px] text-stone-400 font-mono">
                                Rp <?= number_format($item["price"],0,',','.') ?> &times; <?= $item["qty"] ?>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="font-mono font-bold text-amber-400">Rp <?= number_format($item["subtotal"],0,',','.') ?></span>
                            <form action="pos_action.php" method="POST" data-loading class="inline">
                                <?php if(function_exists("csrf_input")) echo csrf_input(); ?>
                                <input type="hidden" name="action" value="remove">
                                <input type="hidden" name="product_id" value="<?= $item["id"] ?>">
                                <button type="submit" class="text-rose-400 hover:text-rose-300 p-1 hover:bg-rose-500/10 rounded">
                                    <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Checkout / Total Bar -->
        <div class="pt-4 border-t border-stone-800/80">
            <div class="flex justify-between items-center mb-3">
                <span class="text-xs uppercase text-stone-400 tracking-wider">Total Tagihan</span>
                <span class="text-xl font-extrabold font-mono text-amber-400">Rp <?= number_format($total,0,',','.') ?></span>
            </div>

            <form action="checkout.php" method="POST" data-loading>
                <?php if(function_exists("csrf_input")) echo csrf_input(); ?>
                <div class="mb-3">
                    <label class="block text-[11px] font-semibold text-stone-300 uppercase tracking-wider mb-1">Uang Tunai Diterima (Rp)</label>
                    <input type="number" name="amount_paid"
                           class="w-full bg-[#131926] border border-stone-800 focus:border-amber-500 focus:ring-1 focus:ring-amber-500 rounded-xl px-3.5 py-2.5 text-sm font-mono text-white placeholder-stone-600 transition-all outline-none"
                           placeholder="<?= $total > 0 ? $total : '0' ?>"
                           required min="<?= $total ?>">
                </div>

                <button type="submit"
                        class="w-full py-3 rounded-xl font-extrabold text-sm transition-all flex items-center justify-center gap-2 shadow-lg shadow-amber-950/40 <?= empty($cart_items) ? 'bg-stone-800 text-stone-600 cursor-not-allowed' : 'bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-400 hover:to-amber-500 text-stone-950 transform active:scale-95' ?>"
                        <?= empty($cart_items) ? 'disabled' : '' ?>>
                    <i data-lucide="credit-card" class="w-4 h-4"></i> PROSES PEMBAYARAN
                </button>
            </form>
        </div>
    </div>
</div>

<script>
function filterProducts() {
    let input = document.getElementById('searchInput').value.toLowerCase();
    let cards = document.getElementsByClassName('product-card');
    for (let i = 0; i < cards.length; i++) {
        let name = cards[i].querySelector('.product-name').innerText.toLowerCase();
        if (name.includes(input)) {
            cards[i].style.display = '';
        } else {
            cards[i].style.display = 'none';
        }
    }
}
</script>

<?php include "footer.php"; ?>
