<?php
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('log_errors', '1');
ob_start();
$render_cart_error = static function (): void {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    http_response_code(500);
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!DOCTYPE html><html lang="id"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Kendala — Kopi Mahasiswa</title><style>body{margin:0;background:#090c12;color:#e7e5e4;font:16px system-ui,sans-serif;display:grid;min-height:100vh;place-items:center}.card{max-width:460px;margin:24px;padding:32px;border:1px solid #292524;border-radius:18px;background:#0e131d;text-align:center}h1{color:#fbbf24;font-size:22px}p{color:#a8a29e;line-height:1.6}a{display:inline-block;margin-top:12px;color:#090c12;background:#f59e0b;padding:10px 18px;border-radius:10px;text-decoration:none;font-weight:700}</style></head><body><main class="card"><h1>Keranjang belum dapat dimuat</h1><p>Maaf, terjadi kendala saat memuat halaman. Silakan coba kembali.</p><a href="cart.php">Coba kembali</a></main></body></html>';
};
set_exception_handler(static function (Throwable $exception) use ($render_cart_error): void {
    error_log(sprintf(
        'Customer cart uncaught exception type=%s file=%s line=%d',
        get_class($exception),
        $exception->getFile(),
        $exception->getLine()
    ));
    $render_cart_error();
});
register_shutdown_function(static function () use ($render_cart_error): void {
    $error = error_get_last();
    if (!$error || !in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR], true)) {
        return;
    }

    error_log(sprintf(
        'Customer cart fatal error type=%d file=%s line=%d',
        $error['type'],
        $error['file'],
        $error['line']
    ));

    $render_cart_error();
});

require "../config.php";
require_once __DIR__ . "/../includes/csrf.php";
include "header.php";

$cart_items = [];
$total = 0;

if (!empty($_SESSION["cart"])) {
    $ids = implode(",", array_keys($_SESSION["cart"]));
    $result = $conn->query("SELECT * FROM products WHERE id IN ($ids)");

    while ($row = $result->fetch_assoc()) {
        $qty = $_SESSION["cart"][$row["id"]];
        $subtotal = $row["price"] * $qty;
        $total += $subtotal;

        $row["qty"] = $qty;
        $row["subtotal"] = $subtotal;
        $cart_items[] = $row;
    }
}
?>

<div class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
    <!-- Header Title -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-8">
        <div>
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-amber-500/10 border border-amber-500/20 flex items-center justify-center text-amber-400">
                    <i data-lucide="shopping-cart" class="w-5 h-5"></i>
                </div>
                <div>
                    <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-100 tracking-tight">Keranjang Belanja</h1>
                    <p class="text-xs sm:text-sm text-slate-400">Tinjau pesanan kopi Anda sebelum melanjutkan ke checkout</p>
                </div>
            </div>
        </div>
        <a href="menu.php" class="inline-flex items-center gap-2 text-xs font-semibold text-amber-400 hover:text-amber-300 bg-amber-500/10 hover:bg-amber-500/20 border border-amber-500/20 px-3.5 py-2 rounded-xl transition-all">
            <i data-lucide="plus" class="w-4 h-4"></i>
            Tambah Menu Lain
        </a>
    </div>

    <?php if (empty($cart_items)): ?>
        <!-- Empty Cart State -->
        <div class="trade-card rounded-2xl p-12 text-center max-w-lg mx-auto">
            <div class="w-20 h-20 rounded-2xl bg-[#131926] border border-slate-800 flex items-center justify-center mx-auto mb-4 text-slate-500">
                <i data-lucide="shopping-bag" class="w-10 h-10 stroke-1"></i>
            </div>
            <h3 class="text-lg font-bold text-slate-200 mb-2">Keranjang Anda Masih Kosong</h3>
            <p class="text-sm text-slate-400 mb-6">Nikmati berbagai pilihan racikan kopi segar dan camilan terbaik kami hari ini.</p>
            <a href="menu.php" class="inline-flex items-center gap-2 bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-400 hover:to-amber-500 text-slate-950 font-bold px-6 py-3 rounded-xl shadow-lg shadow-amber-500/20 transition-all text-sm">
                <i data-lucide="coffee" class="w-4 h-4"></i>
                Jelajahi Menu Kopi
            </a>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Cart Items Table (2 cols) -->
            <div class="lg:col-span-2 space-y-4">
                <div class="trade-card rounded-2xl overflow-hidden">
                    <div class="p-5 border-b border-slate-800/80 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <i data-lucide="list" class="w-4 h-4 text-amber-400"></i>
                            <h2 class="text-sm font-bold text-slate-200 uppercase tracking-wider">Daftar Item (<?= count($cart_items) ?>)</h2>
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-[#131926]/70 text-slate-400 uppercase text-[11px] font-bold tracking-wider border-b border-slate-800/80">
                                <tr>
                                    <th class="px-5 py-3.5">Produk</th>
                                    <th class="px-4 py-3.5">Harga</th>
                                    <th class="px-4 py-3.5">Jumlah</th>
                                    <th class="px-4 py-3.5">Subtotal</th>
                                    <th class="px-4 py-3.5 text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-800/60 text-slate-200">
                                <?php foreach ($cart_items as $item): ?>
                                    <?php
                                        $img = (!empty($item["image"]) && file_exists("../" . $item["image"])) ? "../" . $item["image"] : "";
                                    ?>
                                    <tr class="hover:bg-[#131926]/40 transition-colors">
                                        <td class="px-5 py-4">
                                            <div class="flex items-center gap-3">
                                                <div class="w-12 h-12 rounded-xl bg-[#131926] border border-slate-800 flex items-center justify-center overflow-hidden shrink-0">
                                                    <?php if ($img): ?>
                                                        <img src="<?= htmlspecialchars($img) ?>" alt="<?= htmlspecialchars($item["name"]) ?>" class="w-full h-full object-cover">
                                                    <?php else: ?>
                                                        <i data-lucide="coffee" class="w-5 h-5 text-slate-500"></i>
                                                    <?php endif; ?>
                                                </div>
                                                <div>
                                                    <a href="detail.php?id=<?= $item['id'] ?>" class="font-bold text-slate-100 hover:text-amber-400 transition-colors">
                                                        <?= htmlspecialchars($item["name"]) ?>
                                                    </a>
                                                    <div class="text-[11px] text-slate-500">Item ID: #<?= $item['id'] ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-4 py-4 whitespace-nowrap font-mono text-sm text-slate-300">
                                            Rp <?= number_format($item["price"], 0, ",", ".") ?>
                                        </td>
                                        <td class="px-4 py-4 whitespace-nowrap">
                                            <form action="cart_action.php" method="POST" data-loading class="flex items-center gap-1.5">
                                                <input type="hidden" name="action" value="update">
                                                <input type="hidden" name="product_id" value="<?= $item["id"] ?>">
                                                <input type="number" name="qty" value="<?= $item["qty"] ?>" min="1" class="w-16 bg-[#131926] border border-slate-700/70 text-slate-100 rounded-lg px-2.5 py-1 text-center font-mono text-xs focus:border-amber-500 focus:outline-none transition-colors">
                                                <button type="submit" class="p-1.5 text-xs text-amber-400 hover:text-amber-300 bg-amber-500/10 hover:bg-amber-500/20 rounded-lg border border-amber-500/20 transition-all" title="Perbarui Jumlah">
                                                    <i data-lucide="check" class="w-3.5 h-3.5"></i>
                                                </button>
                                            </form>
                                        </td>
                                        <td class="px-4 py-4 whitespace-nowrap font-mono font-bold text-amber-400 text-sm">
                                            Rp <?= number_format($item["subtotal"], 0, ",", ".") ?>
                                        </td>
                                        <td class="px-4 py-4 whitespace-nowrap text-right">
                                            <form action="cart_action.php" method="POST" data-loading data-confirm data-confirm-title="Hapus menu dari keranjang" data-confirm-message="Produk “{object}” akan dihapus dari keranjang aktif. Lanjutkan?" data-confirm-object="<?= htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') ?>" data-confirm-label="Hapus menu" data-confirm-danger="true" class="inline">
                                                <input type="hidden" name="action" value="remove">
                                                <input type="hidden" name="product_id" value="<?= $item["id"] ?>">
                                                <button type="submit" class="p-2 text-rose-400 hover:text-rose-300 hover:bg-rose-500/10 rounded-lg transition-colors" title="Hapus dari Keranjang">
                                                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Checkout Summary (1 col) -->
            <div class="lg:col-span-1">
                <div class="trade-card rounded-2xl p-6 sticky top-24">
                    <div class="flex items-center gap-2.5 pb-4 border-b border-slate-800/80 mb-5">
                        <div class="w-8 h-8 rounded-lg bg-amber-500/10 border border-amber-500/20 flex items-center justify-center text-amber-400">
                            <i data-lucide="receipt" class="w-4 h-4"></i>
                        </div>
                        <div>
                            <h2 class="text-base font-bold text-slate-100">Ringkasan Belanja</h2>
                            <p class="text-[11px] text-slate-400">Detail kalkulasi pesanan Anda</p>
                        </div>
                    </div>

                    <div class="space-y-3 mb-6">
                        <div class="flex justify-between text-xs text-slate-400">
                            <span>Total Item</span>
                            <span class="font-mono font-bold text-slate-200"><?= count($cart_items) ?> jenis produk</span>
                        </div>
                        <div class="flex justify-between text-xs text-slate-400">
                            <span>Total Kuantitas</span>
                            <span class="font-mono font-bold text-slate-200">
                                <?= array_sum(array_column($cart_items, 'qty')) ?> porsi
                            </span>
                        </div>
                        <div class="pt-3 border-t border-slate-800/80 flex items-baseline justify-between">
                            <span class="text-sm font-semibold text-slate-200">Total Pembayaran</span>
                            <span class="text-2xl font-extrabold font-mono text-amber-400 tracking-tight">
                                Rp <?= number_format($total, 0, ",", ".") ?>
                            </span>
                        </div>
                    </div>

                    <form action="checkout.php" method="POST" data-loading class="space-y-4">
                        <?= csrf_input() ?>
                        <div>
                            <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-2">Tipe Pesanan</label>
                            <select name="order_type" class="w-full bg-[#131926] border border-slate-700/70 rounded-xl px-3.5 py-2.5 text-xs text-slate-200 focus:border-amber-500 focus:outline-none transition-colors" required>
                                <option value="Dine In">Dine In (Makan di Tempat)</option>
                                <option value="Take Away">Take Away (Bungkus / Bawa Pulang)</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-2">Catatan Pesanan</label>
                            <textarea name="notes" rows="2" class="w-full bg-[#131926] border border-slate-700/70 rounded-xl px-3.5 py-2 text-xs text-slate-200 focus:border-amber-500 focus:outline-none transition-colors resize-none placeholder-slate-500" placeholder="Contoh: Less ice, gula aren terpisah, dsb."></textarea>
                        </div>

                        <button type="submit" class="w-full bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-400 hover:to-amber-500 text-slate-950 font-bold py-3.5 rounded-xl shadow-lg shadow-amber-500/20 transition-all flex items-center justify-center gap-2 text-sm">
                            <i data-lucide="check-circle" class="w-4 h-4"></i>
                            Checkout Sekarang
                        </button>
                    </form>

                    <div class="mt-4 pt-4 border-t border-slate-800/60 text-center">
                        <p class="text-[11px] text-slate-500 flex items-center justify-center gap-1.5">
                            <i data-lucide="shield-check" class="w-3.5 h-3.5 text-emerald-400"></i>
                            Transaksi aman & terverifikasi sistem kasir
                        </p>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include "footer.php"; ?>
