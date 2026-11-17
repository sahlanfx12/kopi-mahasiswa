<?php
require "../config.php";
require_once "../includes/csrf.php";
require_once "../includes/interaction_ui.php";
require_once "../includes/notification_events.php";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (empty($_SESSION["user_id"]) || strtolower($_SESSION["role"] ?? "") !== "kasir") {
        header("Location: ../login.php");
        exit;
    }

    $user_id = (int)$_SESSION["user_id"];
    $account_stmt = $conn->prepare("SELECT status FROM users WHERE id = ? LIMIT 1");
    $account_stmt->bind_param("i", $user_id);
    $account_stmt->execute();
    $account = $account_stmt->get_result()->fetch_assoc();
    if (!$account || ($account["status"] ?? "") !== "active") {
        session_destroy();
        header("Location: ../login.php?err=suspended");
        exit;
    }

    $order_id = filter_var($_POST["order_id"] ?? null, FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]]);
    $status = strtoupper(trim((string)($_POST["status"] ?? "")));
    $allowed_statuses = ["PENDING", "CONFIRMED", "PREPARING", "READY", "COMPLETED", "CANCELLED"];

    if ($order_id === false || !in_array($status, $allowed_statuses, true)) {
        $_SESSION["ui_notification"] = ["type" => "error", "message" => "Perubahan status tidak valid. Periksa kembali pesanan dan status yang dipilih."];
    } else {
        $transaction_started = false;
        try {
            if (!$conn->begin_transaction()) throw new RuntimeException("Order status transaction could not start.");
            $transaction_started = true;
            $current_stmt = $conn->prepare("SELECT o.status, o.user_id, r.name AS user_role FROM orders o JOIN users u ON u.id = o.user_id JOIN roles r ON r.id = u.role_id WHERE o.id = ? FOR UPDATE");
            if (!$current_stmt) throw new RuntimeException("Order status lookup could not be prepared.");
            $current_stmt->bind_param("i", $order_id);
            if (!$current_stmt->execute()) throw new RuntimeException("Order status lookup failed.");
            $current_order = $current_stmt->get_result()->fetch_assoc();
            if (!$current_order) throw new RuntimeException("Order not found.");
            $old_status = (string)$current_order['status'];
            $customer_id = (int)$current_order['user_id'];
            if ($old_status === $status) {
                if (!$conn->commit()) throw new RuntimeException("Order status transaction could not commit.");
                $transaction_started = false;
                $_SESSION["ui_notification"] = ["type" => "info", "message" => "Pesanan sudah menggunakan status tersebut."];
            } else {
                $update_stmt = $conn->prepare("UPDATE orders SET status = ? WHERE id = ? AND status = ?");
                if (!$update_stmt) throw new RuntimeException("Order status update could not be prepared.");
                $update_stmt->bind_param("sis", $status, $order_id, $old_status);
                if (!$update_stmt->execute() || $update_stmt->affected_rows !== 1) throw new RuntimeException("Order status update failed.");

                $notification = null;
                if ($status === 'PREPARING') $notification = ['Pesanan mulai diproses', 'Pesanan #' . str_pad((string)$order_id, 5, '0', STR_PAD_LEFT) . ' mulai disiapkan.', 'info'];
                elseif ($status === 'COMPLETED') $notification = ['Pesanan selesai', 'Pesanan #' . str_pad((string)$order_id, 5, '0', STR_PAD_LEFT) . ' telah selesai.', 'success'];
                elseif ($status === 'CANCELLED') $notification = ['Pesanan dibatalkan', 'Pesanan #' . str_pad((string)$order_id, 5, '0', STR_PAD_LEFT) . ' telah dibatalkan.', 'warning'];
                if ($notification !== null && strcasecmp((string)$current_order['user_role'], 'Customer') === 0 && !notification_event_send($conn, $customer_id, $notification[0], $notification[1], $notification[2])) {
                    throw new RuntimeException("Order notification insert failed.");
                }
                if (!$conn->commit()) throw new RuntimeException("Order status transaction could not commit.");
                $transaction_started = false;
                $_SESSION["ui_notification"] = [
                    "type" => "success",
                    "message" => "Status pesanan #" . str_pad((string)$order_id, 5, "0", STR_PAD_LEFT) . " berhasil diperbarui menjadi " . app_status_label($status) . "."
                ];
            }
        } catch (Throwable $e) {
            if ($transaction_started) $conn->rollback();
            error_log("Kasir order status update failed");
            $_SESSION["ui_notification"] = ["type" => "error", "message" => "Status pesanan belum berhasil diperbarui. Silakan coba kembali."];
        }
    }

    header("Location: transactions.php");
    exit;
}

include "header.php";
$orders = $conn->query("SELECT o.*, u.name as customer_name FROM orders o JOIN users u ON o.user_id = u.id ORDER BY o.id DESC LIMIT 50");
?>

<div class="mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
    <div>
        <h1 class="text-2xl font-extrabold text-white tracking-tight">Riwayat &amp; Antrean Pesanan Kasir</h1>
        <p class="text-xs text-stone-400 mt-1">Audit transaksi, perbarui status dapur/penyajian, dan cetak ulang struk.</p>
    </div>
    <a href="pos.php" class="bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-400 hover:to-amber-500 text-stone-950 font-bold px-4 py-2 rounded-xl text-xs transition-all shadow-md shadow-amber-950/40 flex items-center gap-1.5 self-start sm:self-auto">
        <i data-lucide="plus" class="w-4 h-4"></i> Transaksi Baru
    </a>
</div>

<div class="bg-[#0e131d]/90 border border-stone-800/80 rounded-2xl overflow-hidden shadow-xl">
    <div class="p-4 border-b border-stone-800/80 flex items-center justify-between">
        <div class="flex items-center gap-2">
            <i data-lucide="receipt" class="w-4 h-4 text-amber-400"></i>
            <h2 class="text-xs font-bold text-white uppercase tracking-wider">Daftar Transaksi Terkini</h2>
        </div>
        <span class="text-xs text-stone-500 font-mono">50 Pesanan Terakhir</span>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-left text-xs">
            <thead class="bg-[#141b29] text-stone-400 uppercase tracking-wider text-[11px]">
                <tr>
                    <th class="p-4">ID Order</th>
                    <th class="p-4">Pelanggan</th>
                    <th class="p-4">Tipe</th>
                    <th class="p-4">Total Nilai</th>
                    <th class="p-4">Status</th>
                    <th class="p-4">Perbarui Status</th>
                    <th class="p-4 text-center">Struk</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-800/60">
                <?php while($o = $orders->fetch_assoc()):
                    $status_badge = match($o['status']) {
                        'PENDING' => 'bg-amber-500/10 text-amber-400 border border-amber-500/30',
                        'CONFIRMED' => 'bg-cyan-500/10 text-cyan-400 border border-cyan-500/30',
                        'PREPARING' => 'bg-purple-500/10 text-purple-400 border border-purple-500/30',
                        'READY' => 'bg-indigo-500/10 text-indigo-400 border border-indigo-500/30',
                        'COMPLETED' => 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/30',
                        'CANCELLED' => 'bg-rose-500/10 text-rose-400 border border-rose-500/30',
                        default => 'bg-stone-800 text-stone-400'
                    };
                ?>
                <tr class="hover:bg-stone-800/30 transition-colors">
                    <td class="p-4 font-mono font-bold text-amber-400">#<?= str_pad($o['id'], 5, '0', STR_PAD_LEFT) ?></td>
                    <td class="p-4 font-semibold text-white"><?= htmlspecialchars($o['customer_name']) ?></td>
                    <td class="p-4 text-stone-400"><?= htmlspecialchars($o['order_type'] ?? '-') ?></td>
                    <td class="p-4 font-mono font-bold text-stone-200">Rp <?= number_format($o['total_amount'], 0, ',', '.') ?></td>
                    <td class="p-4">
                        <span class="px-2.5 py-1 rounded-full text-[10px] font-bold tracking-wide <?= $status_badge ?>">
                            <?= htmlspecialchars(app_status_label($o['status'])) ?>
                        </span>
                    </td>
                    <td class="p-4">
                        <form method="POST" class="flex items-center gap-2" data-loading data-confirm data-confirm-title="Perbarui status pesanan" data-confirm-message="Yakin ingin mengubah pesanan #{object} menjadi {status}?" data-confirm-object="<?= str_pad((string)$o['id'], 5, '0', STR_PAD_LEFT) ?>" data-confirm-label="Perbarui status" <?= $o['status'] === 'CANCELLED' ? 'data-confirm-danger="true"' : '' ?>>
                            <?php if(function_exists("csrf_input")) echo csrf_input(); ?>
                            <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
                            <select name="status" data-confirm-status class="bg-[#131926] border border-stone-800 text-stone-200 text-xs rounded-lg px-2.5 py-1.5 outline-none focus:border-amber-500 font-semibold">
                                <option value="PENDING" <?= $o['status']=='PENDING'?'selected':'' ?>><?= htmlspecialchars(app_status_label('PENDING')) ?></option>
                                <option value="CONFIRMED" <?= $o['status']=='CONFIRMED'?'selected':'' ?>><?= htmlspecialchars(app_status_label('CONFIRMED')) ?></option>
                                <option value="PREPARING" <?= $o['status']=='PREPARING'?'selected':'' ?>><?= htmlspecialchars(app_status_label('PREPARING')) ?></option>
                                <option value="READY" <?= $o['status']=='READY'?'selected':'' ?>><?= htmlspecialchars(app_status_label('READY')) ?></option>
                                <option value="COMPLETED" <?= $o['status']=='COMPLETED'?'selected':'' ?>><?= htmlspecialchars(app_status_label('COMPLETED')) ?></option>
                                <option value="CANCELLED" <?= $o['status']=='CANCELLED'?'selected':'' ?>><?= htmlspecialchars(app_status_label('CANCELLED')) ?></option>
                            </select>
                            <button type="submit" class="bg-[#1a2233] hover:bg-[#232d42] text-stone-200 border border-stone-700 px-3 py-1.5 rounded-lg text-xs font-semibold transition-all">
                                Perbarui
                            </button>
                        </form>
                    </td>
                    <td class="p-4 text-center">
                        <a href="print.php?id=<?= $o['id'] ?>" target="_blank" class="bg-stone-800 hover:bg-stone-700 text-stone-300 p-2 rounded-lg text-xs inline-flex items-center justify-center transition-all" title="Cetak Struk">
                            <i data-lucide="printer" class="w-3.5 h-3.5"></i>
                        </a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include "footer.php"; ?>
