<?php
require "../config.php";
include "header.php";

$statuses = ['PENDING', 'CONFIRMED', 'PREPARING', 'READY', 'COMPLETED', 'CANCELLED'];
$status_classes = [
    'PENDING' => 'bg-amber-500/10 text-amber-400 border-amber-500/30',
    'CONFIRMED' => 'bg-cyan-500/10 text-cyan-400 border-cyan-500/30',
    'PREPARING' => 'bg-purple-500/10 text-purple-400 border-purple-500/30',
    'READY' => 'bg-indigo-500/10 text-indigo-400 border-indigo-500/30',
    'COMPLETED' => 'bg-emerald-500/10 text-emerald-400 border-emerald-500/30',
    'CANCELLED' => 'bg-rose-500/10 text-rose-400 border-rose-500/30',
];

$order_id_input = $_GET['id'] ?? null;
$order_id = is_string($order_id_input)
    ? filter_var($order_id_input, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
    : false;
$detail_order = null;
$items = null;
$payments = null;

if ($order_id !== false && $order_id !== null) {
    $detail_stmt = $conn->prepare(
        "SELECT o.*, u.name AS customer_name, u.email AS customer_email, u.phone AS customer_phone
         FROM orders o
         LEFT JOIN users u ON u.id = o.user_id
         WHERE o.id = ?"
    );
    $detail_stmt->bind_param("i", $order_id);
    $detail_stmt->execute();
    $detail_order = $detail_stmt->get_result()->fetch_assoc();

    if ($detail_order) {
        $items_stmt = $conn->prepare(
            "SELECT oi.quantity, oi.price, oi.subtotal, p.name AS product_name
             FROM order_items oi
             LEFT JOIN products p ON p.id = oi.product_id
             WHERE oi.order_id = ?
             ORDER BY oi.id"
        );
        $items_stmt->bind_param("i", $order_id);
        $items_stmt->execute();
        $items = $items_stmt->get_result();

        $payments_stmt = $conn->prepare(
            "SELECT amount_paid, change_amount, payment_method, created_at
             FROM payments
             WHERE order_id = ?
             ORDER BY created_at, id"
        );
        $payments_stmt->bind_param("i", $order_id);
        $payments_stmt->execute();
        $payments = $payments_stmt->get_result();
    } else {
        http_response_code(404);
    }
}

$is_detail = $order_id !== false && $order_id !== null;
$search_input = $_GET['q'] ?? '';
$search = is_string($search_input) ? trim($search_input) : '';
$search_id = preg_replace('/^#/', '', $search);
$status_input = $_GET['status'] ?? '';
$status = is_string($status_input) && in_array($status_input, $statuses, true) ? $status_input : '';
$page_input_raw = $_GET['page'] ?? '1';
$page_input = is_string($page_input_raw)
    ? filter_var($page_input_raw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
    : false;
$page = $page_input === false ? 1 : $page_input;
$page_size = 20;
$search_term = '%' . $search . '%';
$search_id_term = '%' . $search_id . '%';

if (!$is_detail) {
    $filter_sql = " WHERE (? = '' OR CAST(o.id AS CHAR) LIKE ? OR LPAD(o.id, 5, '0') LIKE ? OR u.name LIKE ? OR u.email LIKE ?)
                    AND (? = '' OR o.status = ?)";
    $count_stmt = $conn->prepare(
        "SELECT COUNT(*) AS total
         FROM orders o
         LEFT JOIN users u ON u.id = o.user_id" . $filter_sql
    );
    $count_stmt->bind_param(
        "sssssss",
        $search,
        $search_id_term,
        $search_id_term,
        $search_term,
        $search_term,
        $status,
        $status
    );
    $count_stmt->execute();
    $total_orders = (int)$count_stmt->get_result()->fetch_assoc()['total'];
    $total_pages = max(1, (int)ceil($total_orders / $page_size));
    $page = min($page, $total_pages);
    $offset = ($page - 1) * $page_size;

    $orders_stmt = $conn->prepare(
        "SELECT o.id, o.total_amount, o.status, o.created_at, o.order_type, u.name AS customer_name
         FROM orders o
         LEFT JOIN users u ON u.id = o.user_id" . $filter_sql . "
         ORDER BY o.created_at DESC, o.id DESC
         LIMIT ? OFFSET ?"
    );
    $orders_stmt->bind_param(
        "sssssssii",
        $search,
        $search_id_term,
        $search_id_term,
        $search_term,
        $search_term,
        $status,
        $status,
        $page_size,
        $offset
    );
    $orders_stmt->execute();
    $orders = $orders_stmt->get_result();

    $query_params = [];
    if ($search !== '') {
        $query_params['q'] = $search;
    }
    if ($status !== '') {
        $query_params['status'] = $status;
    }
    $pagination_query = http_build_query($query_params);
    $pagination_suffix = $pagination_query !== '' ? '&' . $pagination_query : '';
}

if ($is_detail && !$detail_order):
?>
    <div class="bg-[#0e131d]/90 border border-stone-800/80 rounded-2xl p-8 text-center">
        <h1 class="text-xl font-bold text-white">Pesanan tidak ditemukan</h1>
        <p class="text-sm text-stone-400 mt-2">Nomor pesanan tersebut tidak tersedia di database.</p>
        <a href="orders.php" class="inline-flex mt-5 px-4 py-2 rounded-lg bg-amber-500/10 text-amber-400 border border-amber-500/30 text-sm font-semibold">Kembali ke daftar pesanan</a>
    </div>
<?php elseif ($is_detail): ?>
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
        <div>
            <a href="orders.php" class="text-xs text-amber-400 hover:text-amber-300">&larr; Kembali ke daftar pesanan</a>
            <h1 class="text-2xl font-extrabold text-white mt-2">Detail Pesanan #<?= str_pad((string)$detail_order['id'], 5, '0', STR_PAD_LEFT) ?></h1>
        </div>
        <span class="inline-flex w-fit px-3 py-1.5 rounded-full border text-xs font-bold <?= $status_classes[$detail_order['status']] ?? 'bg-stone-800 text-stone-300 border-stone-700' ?>">
            <?= htmlspecialchars(app_status_label($detail_order['status']), ENT_QUOTES, 'UTF-8') ?>
        </span>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
        <section class="lg:col-span-2 bg-[#0e131d]/90 border border-stone-800/80 rounded-2xl overflow-hidden">
            <div class="px-5 py-4 border-b border-stone-800/80">
                <h2 class="text-sm font-bold text-white uppercase tracking-wider">Produk yang dipesan</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs min-w-[560px]">
                    <thead class="bg-[#141b29] text-stone-400 uppercase tracking-wider text-[11px]">
                        <tr>
                            <th class="px-5 py-3">Produk</th>
                            <th class="px-5 py-3 text-right">Harga</th>
                            <th class="px-5 py-3 text-center">Jumlah</th>
                            <th class="px-5 py-3 text-right">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-800/60">
                        <?php if ($items->num_rows > 0): ?>
                            <?php while ($item = $items->fetch_assoc()): ?>
                                <tr>
                                    <td class="px-5 py-3.5 font-semibold text-white"><?= htmlspecialchars($item['product_name'] ?? 'Produk tidak tersedia', ENT_QUOTES, 'UTF-8') ?></td>
                                    <td class="px-5 py-3.5 text-right font-mono text-stone-300">Rp <?= number_format((float)$item['price'], 0, ',', '.') ?></td>
                                    <td class="px-5 py-3.5 text-center font-mono text-amber-400"><?= (int)$item['quantity'] ?></td>
                                    <td class="px-5 py-3.5 text-right font-mono text-white">Rp <?= number_format((float)$item['subtotal'], 0, ',', '.') ?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="4" class="px-5 py-8 text-center text-stone-500">Tidak ada item pesanan.</td></tr>
                        <?php endif; ?>
                    </tbody>
                    <tfoot class="bg-[#141b29]">
                        <tr>
                            <th colspan="3" class="px-5 py-4 text-right text-stone-400 uppercase">Total pembayaran</th>
                            <th class="px-5 py-4 text-right font-mono text-amber-400">Rp <?= number_format((float)$detail_order['total_amount'], 0, ',', '.') ?></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </section>

        <div class="space-y-5">
            <section class="bg-[#0e131d]/90 border border-stone-800/80 rounded-2xl p-5">
                <h2 class="text-xs font-bold text-stone-400 uppercase tracking-wider mb-4">Informasi pelanggan</h2>
                <p class="text-base font-bold text-white"><?= htmlspecialchars($detail_order['customer_name'] ?? 'Pelanggan tidak tersedia', ENT_QUOTES, 'UTF-8') ?></p>
                <p class="text-xs text-stone-400 mt-2"><?= htmlspecialchars($detail_order['customer_email'] ?? '-', ENT_QUOTES, 'UTF-8') ?></p>
                <p class="text-xs text-stone-400 mt-1"><?= htmlspecialchars($detail_order['customer_phone'] ?? '-', ENT_QUOTES, 'UTF-8') ?></p>
            </section>

            <section class="bg-[#0e131d]/90 border border-stone-800/80 rounded-2xl p-5">
                <h2 class="text-xs font-bold text-stone-400 uppercase tracking-wider mb-4">Informasi pesanan</h2>
                <dl class="space-y-3 text-xs">
                    <div class="flex justify-between gap-3"><dt class="text-stone-500">Nomor</dt><dd class="font-mono text-amber-400">#<?= str_pad((string)$detail_order['id'], 5, '0', STR_PAD_LEFT) ?></dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-stone-500">Tanggal</dt><dd class="text-right text-stone-200"><?= htmlspecialchars(date('d M Y, H:i', strtotime($detail_order['created_at'])), ENT_QUOTES, 'UTF-8') ?></dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-stone-500">Tipe</dt><dd class="text-stone-200"><?= htmlspecialchars($detail_order['order_type'] ?? '-', ENT_QUOTES, 'UTF-8') ?></dd></div>
                    <?php if (!empty($detail_order['notes'])): ?>
                        <div class="border-t border-stone-800 pt-3"><dt class="text-stone-500 mb-1">Catatan</dt><dd class="text-stone-200"><?= nl2br(htmlspecialchars($detail_order['notes'], ENT_QUOTES, 'UTF-8')) ?></dd></div>
                    <?php endif; ?>
                </dl>
            </section>

            <section class="bg-[#0e131d]/90 border border-stone-800/80 rounded-2xl p-5">
                <h2 class="text-xs font-bold text-stone-400 uppercase tracking-wider mb-3">Data pembayaran</h2>
                <?php if ($payments->num_rows > 0): ?>
                    <div class="space-y-3">
                        <?php while ($payment = $payments->fetch_assoc()): ?>
                            <div class="border-t border-stone-800 pt-3 first:border-0 first:pt-0">
                                <div class="flex justify-between gap-3 text-xs">
                                    <span class="text-stone-400"><?= htmlspecialchars($payment['payment_method'] ?? '-', ENT_QUOTES, 'UTF-8') ?></span>
                                    <span class="font-mono text-white">Rp <?= number_format((float)$payment['amount_paid'], 0, ',', '.') ?></span>
                                </div>
                                <p class="text-[11px] text-stone-500 mt-1">Kembalian: Rp <?= number_format((float)$payment['change_amount'], 0, ',', '.') ?> &middot; <?= htmlspecialchars(date('d M Y, H:i', strtotime($payment['created_at'])), ENT_QUOTES, 'UTF-8') ?></p>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <p class="text-xs text-stone-500">Belum ada data pembayaran yang tercatat.</p>
                <?php endif; ?>
                <p class="text-[10px] text-stone-600 mt-3">Skema database tidak menyimpan status pembayaran.</p>
            </section>
        </div>
    </div>
<?php else: ?>
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-6">
        <div>
            <h1 class="text-2xl font-extrabold text-white">Pesanan</h1>
            <p class="text-xs text-stone-400 mt-1"><?= number_format($total_orders) ?> pesanan ditemukan</p>
        </div>
    </div>

    <form method="get" action="orders.php" class="bg-[#0e131d]/90 border border-stone-800/80 rounded-2xl p-4 mb-5 flex flex-col sm:flex-row gap-3">
        <label class="sr-only" for="order-search">Cari pesanan</label>
        <input id="order-search" type="search" name="q" value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>" placeholder="Cari nomor pesanan, nama, atau email pelanggan" class="flex-1 min-w-0 bg-[#131926] border border-stone-700 focus:border-amber-500 rounded-xl px-4 py-2.5 text-sm text-stone-200 outline-none">
        <label class="sr-only" for="order-status">Filter status</label>
        <select id="order-status" name="status" class="bg-[#131926] border border-stone-700 focus:border-amber-500 rounded-xl px-3 py-2.5 text-sm text-stone-200 outline-none">
            <option value="">Semua status</option>
            <?php foreach ($statuses as $order_status): ?>
                <option value="<?= htmlspecialchars($order_status, ENT_QUOTES, 'UTF-8') ?>" <?= $status === $order_status ? 'selected' : '' ?>><?= htmlspecialchars(app_status_label($order_status), ENT_QUOTES, 'UTF-8') ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="bg-amber-500 hover:bg-amber-400 text-stone-950 px-5 py-2.5 rounded-xl text-sm font-bold">Cari</button>
        <?php if ($search !== '' || $status !== ''): ?>
            <a href="orders.php" class="px-4 py-2.5 rounded-xl border border-stone-700 text-stone-300 hover:bg-stone-800 text-sm text-center">Reset</a>
        <?php endif; ?>
    </form>

    <div class="bg-[#0e131d]/90 border border-stone-800/80 rounded-2xl overflow-hidden shadow-xl">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs min-w-[760px]">
                <thead class="bg-[#141b29] text-stone-400 uppercase tracking-wider text-[11px]">
                    <tr>
                        <th class="px-5 py-3">Nomor pesanan</th>
                        <th class="px-5 py-3">Pelanggan</th>
                        <th class="px-5 py-3">Tanggal</th>
                        <th class="px-5 py-3 text-right">Total pembayaran</th>
                        <th class="px-5 py-3">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-800/60">
                    <?php if ($orders->num_rows > 0): ?>
                        <?php while ($order = $orders->fetch_assoc()): ?>
                            <tr class="hover:bg-stone-800/30 transition-colors">
                                <td class="px-5 py-3.5 font-mono font-bold"><a class="text-amber-400 hover:text-amber-300" href="orders.php?id=<?= (int)$order['id'] ?>">#<?= str_pad((string)$order['id'], 5, '0', STR_PAD_LEFT) ?></a></td>
                                <td class="px-5 py-3.5 font-medium text-white"><?= htmlspecialchars($order['customer_name'] ?? 'Pelanggan tidak tersedia', ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="px-5 py-3.5 text-stone-400"><?= htmlspecialchars(date('d M Y, H:i', strtotime($order['created_at'])), ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="px-5 py-3.5 text-right font-mono font-bold text-stone-200">Rp <?= number_format((float)$order['total_amount'], 0, ',', '.') ?></td>
                                <td class="px-5 py-3.5"><span class="inline-flex px-2.5 py-1 rounded-full border text-[10px] font-bold <?= $status_classes[$order['status']] ?? 'bg-stone-800 text-stone-300 border-stone-700' ?>"><?= htmlspecialchars(app_status_label($order['status']), ENT_QUOTES, 'UTF-8') ?></span></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="5" class="text-center py-12 text-stone-500">Tidak ada pesanan yang cocok dengan pencarian.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ($total_orders > 0): ?>
            <div class="px-5 py-4 border-t border-stone-800/80 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs">
                <span class="text-stone-500">Menampilkan <?= number_format($offset + 1) ?>–<?= number_format(min($offset + $page_size, $total_orders)) ?> dari <?= number_format($total_orders) ?></span>
                <nav aria-label="Navigasi halaman pesanan" class="flex items-center gap-2">
                    <?php if ($page > 1): ?><a href="?page=<?= $page - 1 ?><?= htmlspecialchars($pagination_suffix, ENT_QUOTES, 'UTF-8') ?>" class="px-3 py-2 rounded-lg border border-stone-700 text-stone-300 hover:bg-stone-800">Sebelumnya</a><?php endif; ?>
                    <span class="text-stone-400">Halaman <?= number_format($page) ?> dari <?= number_format($total_pages) ?></span>
                    <?php if ($page < $total_pages): ?><a href="?page=<?= $page + 1 ?><?= htmlspecialchars($pagination_suffix, ENT_QUOTES, 'UTF-8') ?>" class="px-3 py-2 rounded-lg border border-stone-700 text-stone-300 hover:bg-stone-800">Berikutnya</a><?php endif; ?>
                </nav>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php include "footer.php"; ?>
