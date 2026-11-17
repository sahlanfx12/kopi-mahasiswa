<?php
require "../config.php";
include "header.php";

$user_id = (int)$_SESSION["user_id"];
$stmt = $conn->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY id DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$orders = $stmt->get_result();
?>

<div class="max-w-6xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
    <!-- Page Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-8">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-amber-500/10 border border-amber-500/20 flex items-center justify-center text-amber-400">
                <i data-lucide="receipt" class="w-5 h-5"></i>
            </div>
            <div>
                <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-100 tracking-tight">Riwayat Pesanan</h1>
                <p class="text-xs sm:text-sm text-slate-400">Pantau proses peracikan kopi dan rekam jejak pesanan Anda</p>
            </div>
        </div>
        <a href="menu.php" class="inline-flex items-center gap-2 text-xs font-semibold text-amber-400 hover:text-amber-300 bg-amber-500/10 hover:bg-amber-500/20 border border-amber-500/20 px-4 py-2.5 rounded-xl transition-all">
            <i data-lucide="coffee" class="w-4 h-4"></i>
            Pesan Menu Baru
        </a>
    </div>

    <!-- Orders List -->
    <?php if ($orders && $orders->num_rows > 0): ?>
        <div class="space-y-5">
            <?php while ($order = $orders->fetch_assoc()): ?>
                <?php
                    $status = strtoupper($order["status"] ?? "PENDING");

                    // Status Badge Styling & Labels
                    switch ($status) {
                        case "PENDING":
                            $badge_class = "bg-amber-500/10 text-amber-400 border-amber-500/30";
                            $status_icon = "clock";
                            $status_label = app_status_label($status);
                            break;
                        case "CONFIRMED":
                            $badge_class = "bg-sky-500/10 text-sky-400 border-sky-500/30";
                            $status_icon = "check-circle-2";
                            $status_label = app_status_label($status);
                            break;
                        case "PREPARING":
                            $badge_class = "bg-purple-500/10 text-purple-400 border-purple-500/30";
                            $status_icon = "flame";
                            $status_label = app_status_label($status);
                            break;
                        case "READY":
                            $badge_class = "bg-emerald-500/10 text-emerald-400 border-emerald-500/30";
                            $status_icon = "bell";
                            $status_label = app_status_label($status);
                            break;
                        case "COMPLETED":
                            $badge_class = "bg-slate-800 text-slate-300 border-slate-700";
                            $status_icon = "check";
                            $status_label = app_status_label($status);
                            break;
                        case "CANCELLED":
                            $badge_class = "bg-rose-500/10 text-rose-400 border-rose-500/30";
                            $status_icon = "x-circle";
                            $status_label = app_status_label($status);
                            break;
                        default:
                            $badge_class = "bg-slate-800 text-slate-400 border-slate-700";
                            $status_icon = "help-circle";
                            $status_label = app_status_label($status);
                            break;
                    }
                ?>
                <div class="trade-card rounded-2xl border border-slate-800/80 p-5 sm:p-6 hover:border-slate-700/80 transition-all">
                    <!-- Order Card Header -->
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-4 border-b border-slate-800/70">
                        <div class="flex flex-wrap items-center gap-2.5">
                            <span class="font-mono font-bold text-base text-amber-400">
                                #<?= str_pad($order["id"], 5, "0", STR_PAD_LEFT) ?>
                            </span>
                            <span class="text-slate-600">&bull;</span>
                            <span class="inline-flex items-center gap-1.5 text-xs text-slate-400">
                                <i data-lucide="calendar" class="w-3.5 h-3.5 text-slate-500"></i>
                                <?= date("d M Y, H:i", strtotime($order["created_at"])) ?> WIB
                            </span>
                            <span class="text-slate-600">&bull;</span>
                            <span class="inline-flex items-center gap-1 text-[11px] font-semibold px-2.5 py-0.5 rounded-full bg-[#131926] text-slate-300 border border-slate-700/60">
                                <i data-lucide="<?= $order["order_type"] === 'Take Away' ? 'shopping-bag' : 'coffee' ?>" class="w-3 h-3 text-amber-400"></i>
                                <?= htmlspecialchars($order["order_type"] ?? 'Dine In') ?>
                            </span>
                        </div>

                        <!-- Status Badge -->
                        <div>
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold border <?= $badge_class ?>">
                                <i data-lucide="<?= $status_icon ?>" class="w-3.5 h-3.5"></i>
                                <?= htmlspecialchars($status_label) ?>
                            </span>
                        </div>
                    </div>

                    <!-- Items Detail -->
                    <div class="py-4">
                        <div class="bg-[#131926]/60 rounded-xl p-4 border border-slate-800/60">
                            <h4 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-3 flex items-center gap-1.5">
                                <i data-lucide="coffee" class="w-3.5 h-3.5 text-amber-400"></i>
                                Rincian Menu Dipesan
                            </h4>
                            <div class="space-y-2">
                                <?php
                                    $order_id = (int)$order["id"];
                                    $stmt_items = $conn->prepare("SELECT oi.*, p.name FROM order_items oi JOIN products p ON oi.product_id = p.id WHERE oi.order_id = ?");
                                    $stmt_items->bind_param("i", $order_id);
                                    $stmt_items->execute();
                                    $items = $stmt_items->get_result();

                                    while ($item = $items->fetch_assoc()):
                                ?>
                                    <div class="flex items-center justify-between text-xs sm:text-sm">
                                        <div class="flex items-center gap-2.5">
                                            <span class="font-mono font-bold text-amber-400 bg-amber-500/10 border border-amber-500/20 px-2 py-0.5 rounded text-xs">
                                                <?= (int)$item["quantity"] ?>x
                                            </span>
                                            <span class="font-medium text-slate-200"><?= htmlspecialchars($item["name"]) ?></span>
                                        </div>
                                        <div class="font-mono text-slate-400 text-xs">
                                            Rp <?= number_format($item["subtotal"], 0, ",", ".") ?>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            </div>

                            <?php if (!empty($order["notes"])): ?>
                                <div class="mt-3 pt-3 border-t border-slate-800/80 flex items-start gap-2 text-xs text-slate-400">
                                    <i data-lucide="message-square" class="w-3.5 h-3.5 text-amber-400 shrink-0 mt-0.5"></i>
                                    <span><strong class="text-slate-300">Catatan:</strong> <?= htmlspecialchars($order["notes"]) ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Order Card Footer -->
                    <div class="pt-3 border-t border-slate-800/70 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                        <div class="text-xs text-slate-500 flex items-center gap-1.5">
                            <?php if ($status === "READY"): ?>
                                <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                                <span class="text-emerald-400 font-semibold">Silakan ambil pesanan Anda di Pick-Up Bar!</span>
                            <?php elseif ($status === "PREPARING"): ?>
                                <span class="w-2 h-2 rounded-full bg-purple-400 animate-pulse"></span>
                                <span class="text-purple-300">Barista kami sedang meracik pesanan terbaik untuk Anda.</span>
                            <?php elseif ($status === "CONFIRMED"): ?>
                                <span class="text-sky-400 font-semibold">Pesanan telah masuk antrean dapur.</span>
                            <?php else: ?>
                                <span>Status pembayaran: Lunas / Kasir Verifikasi</span>
                            <?php endif; ?>
                        </div>

                        <div class="flex items-baseline justify-between sm:justify-end gap-3">
                            <span class="text-xs text-slate-400">Total Pembayaran:</span>
                            <span class="text-lg sm:text-xl font-extrabold font-mono text-amber-400">
                                Rp <?= number_format($order["total_amount"], 0, ",", ".") ?>
                            </span>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    <?php else: ?>
        <!-- Empty State -->
        <div class="trade-card rounded-2xl p-12 text-center max-w-lg mx-auto">
            <div class="w-20 h-20 rounded-2xl bg-[#131926] border border-slate-800 flex items-center justify-center mx-auto mb-4 text-slate-500">
                <i data-lucide="clipboard-list" class="w-10 h-10 stroke-1"></i>
            </div>
            <h3 class="text-lg font-bold text-slate-200 mb-2">Belum Ada Riwayat Pesanan</h3>
            <p class="text-sm text-slate-400 mb-6">Anda belum memiliki transaksi pesanan kopi. Pesan kopi favorit Anda sekarang untuk mulai menikmati!</p>
            <a href="menu.php" class="inline-flex items-center gap-2 bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-400 hover:to-amber-500 text-slate-950 font-bold px-6 py-3 rounded-xl shadow-lg shadow-amber-500/20 transition-all text-sm">
                <i data-lucide="coffee" class="w-4 h-4"></i>
                Pesan Kopi Sekarang
            </a>
        </div>
    <?php endif; ?>
</div>

<?php include "footer.php"; ?>
