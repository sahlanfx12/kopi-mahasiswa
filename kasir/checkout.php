<?php
session_start();
require "../config.php"; require_once "../includes/csrf.php";
require_once "../includes/notification_events.php";
if (!isset($_SESSION["user_id"]) || strtolower($_SESSION["role"]) !== "kasir") exit;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $transaction_started = false;
    try {
        if (empty($_SESSION["kasir_cart"]) || !is_array($_SESSION["kasir_cart"])) {
            throw new Exception("Keranjang transaksi kosong atau tidak valid!");
        }

        $cart = [];
        foreach ($_SESSION["kasir_cart"] as $raw_product_id => $raw_qty) {
            if (!is_scalar($raw_product_id) || !is_scalar($raw_qty)) {
                throw new Exception("Data keranjang tidak valid!");
            }
            $product_id = filter_var($raw_product_id, FILTER_VALIDATE_INT);
            $qty = filter_var($raw_qty, FILTER_VALIDATE_INT);
            if ($product_id === false || $product_id <= 0 || $qty === false || $qty <= 0) {
                throw new Exception("Data keranjang tidak valid!");
            }
            if (array_key_exists($product_id, $cart)) throw new Exception("Produk duplikat di keranjang.");
            $cart[$product_id] = $qty;
        }

        $raw_amount_paid = $_POST["amount_paid"] ?? null;
        if (!is_numeric($raw_amount_paid) || (float)$raw_amount_paid < 0) {
            throw new Exception("Nominal pembayaran tidak valid!");
        }
        $amount_paid = (float)$raw_amount_paid;
        $user_id = (int)$_SESSION["user_id"];

        if (!$conn->begin_transaction()) throw new Exception("Gagal memulai transaksi.");
        $transaction_started = true;

        $total = 0; $cart_items = [];
        $ids = implode(",", array_keys($cart));
        $result = $conn->query("SELECT p.id, p.price, p.name, s.current_stock, s.minimum_stock, s.id as stock_id FROM products p LEFT JOIN stock s ON p.id = s.product_id WHERE p.id IN ($ids) FOR UPDATE");
        if (!$result) throw new Exception("Gagal membaca produk dan stok.");

        while ($row = $result->fetch_assoc()) {
            $product_id = (int)$row["id"];
            if (!array_key_exists($product_id, $cart)) throw new Exception("Data produk keranjang tidak valid!");
            $qty = $cart[$product_id];
            if ($row["stock_id"] === null || $row["current_stock"] === null || (int)$row["current_stock"] < $qty) {
                throw new Exception("Stok {$row['name']} tidak cukup!");
            }
            $subtotal = $row["price"] * $qty;
            $total += $subtotal;
            $row["qty"] = $qty; $row["subtotal"] = $subtotal;
            $cart_items[] = $row;
        }

        if (count($cart_items) !== count($cart)) throw new Exception("Satu atau lebih produk tidak ditemukan atau tidak memiliki stok.");

        if ($amount_paid < $total) throw new Exception("Uang kurang!");
        $change_amount = $amount_paid - $total;

        $stmt_order = $conn->prepare("INSERT INTO orders (user_id, total_amount, order_type, status) VALUES (?, ?, 'Dine In', 'COMPLETED')");
        $stmt_order->bind_param("id", $user_id, $total);
        if (!$stmt_order->execute()) throw new Exception("Gagal menyimpan pesanan.");
        $order_id = $stmt_order->insert_id;

        $stmt_payment = $conn->prepare("INSERT INTO payments (order_id, amount_paid, change_amount, payment_method) VALUES (?, ?, ?, 'CASH')");
        $stmt_payment->bind_param("idd", $order_id, $amount_paid, $change_amount);
        if (!$stmt_payment->execute()) throw new Exception("Gagal menyimpan pembayaran.");

        $stmt_item = $conn->prepare("INSERT INTO order_items (order_id, product_id, quantity, price, subtotal) VALUES (?, ?, ?, ?, ?)");
        $stmt_stock = $conn->prepare("UPDATE stock SET current_stock = current_stock - ? WHERE id = ? AND current_stock >= ?");
        $stmt_st_log = $conn->prepare("INSERT INTO stock_transactions (stock_id, type, quantity, reference_type, reference_id, notes) VALUES (?, 'OUT', ?, 'ORDER', ?, 'Penjualan POS')");

        foreach ($cart_items as $item) {
            $stmt_item->bind_param("iiidd", $order_id, $item["id"], $item["qty"], $item["price"], $item["subtotal"]);
            if (!$stmt_item->execute()) throw new Exception("Gagal menyimpan item pesanan.");
            $stmt_stock->bind_param("iii", $item["qty"], $item["stock_id"], $item["qty"]);
            if (!$stmt_stock->execute() || $stmt_stock->affected_rows !== 1) {
                throw new Exception("Stok {$item['name']} tidak cukup atau gagal diperbarui.");
            }
            $stmt_st_log->bind_param("iii", $item["stock_id"], $item["qty"], $order_id);
            if (!$stmt_st_log->execute()) throw new Exception("Gagal mencatat mutasi stok.");
            $old_stock = (int)$item["current_stock"];
            $new_stock = $old_stock - (int)$item["qty"];
            if (!notification_event_stock_change($conn, (string)$item["name"], $old_stock, $new_stock, (int)($item["minimum_stock"] ?? 0))) {
                throw new Exception("Gagal mencatat notifikasi stok.");
            }
        }

        if (!$conn->commit()) throw new Exception("Gagal menyelesaikan transaksi.");
        $transaction_started = false;
        unset($_SESSION["kasir_cart"]);
        $_SESSION["ui_notification"] = ["type" => "success", "message" => "Transaksi berhasil. Kembalian: Rp " . number_format($change_amount,0,',','.')];
        $_SESSION["last_order_id"] = $order_id;
    } catch (Exception $e) {
        if ($transaction_started) $conn->rollback();
        error_log("Kasir checkout failed");
        $_SESSION["ui_notification"] = ["type" => "error", "message" => "Transaksi belum berhasil. Periksa stok dan nominal pembayaran, lalu coba kembali."];
    }
}
header("Location: pos.php");
?>
