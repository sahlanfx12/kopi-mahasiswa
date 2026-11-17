<?php
require "../config.php";
include "header.php";

$filter = isset($_GET['filter']) ? $conn->real_escape_string($_GET['filter']) : 'all';
$query = "SELECT st.*, p.name as product_name FROM stock_transactions st JOIN stock s ON st.stock_id = s.id JOIN products p ON s.product_id = p.id";
if ($filter !== 'all') {
    $query .= " WHERE st.type = '$filter'";
}
$query .= " ORDER BY st.created_at DESC LIMIT 100";
$transactions = $conn->query($query);
?>

<div class="mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
    <div>
        <h1 class="text-2xl font-extrabold text-white tracking-tight">Mutasi &amp; Riwayat Stok</h1>
        <p class="text-xs text-stone-400 mt-1">Audit pergerakan barang masuk dan keluar inventori.</p>
    </div>

    <form method="GET" class="flex items-center gap-2">
        <select name="filter" class="bg-[#131926] border border-stone-800 text-stone-300 text-xs rounded-xl px-3 py-2 outline-none focus:border-amber-500">
            <option value="all" <?= $filter=='all'?'selected':'' ?>>Semua Alur Mutasi</option>
            <option value="IN" <?= $filter=='IN'?'selected':'' ?>><?= htmlspecialchars(app_status_label('IN')) ?></option>
            <option value="OUT" <?= $filter=='OUT'?'selected':'' ?>><?= htmlspecialchars(app_status_label('OUT')) ?></option>
        </select>
        <button type="submit" class="bg-[#1a2233] hover:bg-[#232d42] text-stone-200 border border-stone-700 px-4 py-2 rounded-xl text-xs font-semibold transition-all">
            Filter
        </button>
    </form>
</div>

<div class="bg-[#0e131d]/90 border border-stone-800/80 rounded-2xl overflow-hidden shadow-xl">
    <div class="p-4 border-b border-stone-800/80 flex items-center justify-between">
        <div class="flex items-center gap-2">
            <i data-lucide="arrow-left-right" class="w-4 h-4 text-amber-400"></i>
            <h2 class="text-xs font-bold text-white uppercase tracking-wider">Log Pergerakan Inventori</h2>
        </div>
        <span class="text-xs text-stone-500 font-mono">100 Log Terakhir</span>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-left text-xs">
            <thead class="bg-[#141b29] text-stone-400 uppercase tracking-wider text-[11px]">
                <tr>
                    <th class="px-5 py-3">Waktu</th>
                    <th class="px-5 py-3">Nama Produk</th>
                    <th class="px-5 py-3 text-center">Tipe</th>
                    <th class="px-5 py-3 text-center">Kuantitas</th>
                    <th class="px-5 py-3">Referensi</th>
                    <th class="px-5 py-3">Keterangan</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-800/60">
                <?php while($t = $transactions->fetch_assoc()):
                    $is_in = ($t['type'] === 'IN');
                    $badge_class = $is_in
                        ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/30'
                        : 'bg-rose-500/10 text-rose-400 border border-rose-500/30';
                ?>
                <tr class="hover:bg-stone-800/30 transition-colors">
                    <td class="px-5 py-3.5 text-stone-400 font-mono text-[11px]"><?= date("d M Y H:i:s", strtotime($t['created_at'])) ?></td>
                    <td class="px-5 py-3.5 font-bold text-white"><?= htmlspecialchars($t['product_name']) ?></td>
                    <td class="px-5 py-3.5 text-center">
                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold tracking-wide <?= $badge_class ?>">
                            <?= $is_in ? '+ ' . htmlspecialchars(app_status_label('IN')) : '− ' . htmlspecialchars(app_status_label('OUT')) ?>
                        </span>
                    </td>
                    <td class="px-5 py-3.5 text-center font-mono font-bold text-sm <?= $is_in ? 'text-emerald-400' : 'text-rose-400' ?>">
                        <?= ($is_in ? '+' : '-') . $t['quantity'] ?>
                    </td>
                    <td class="px-5 py-3.5 text-stone-400 font-mono text-[11px]">
                        <?= htmlspecialchars($t['reference_type']) ?> #<?= $t['reference_id'] ?>
                    </td>
                    <td class="px-5 py-3.5 text-stone-400 text-xs"><?= htmlspecialchars($t['notes'] ?? '-') ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include "footer.php"; ?>