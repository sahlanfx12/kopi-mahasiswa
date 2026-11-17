<?php
session_start();
require "../config.php";
require_once "../includes/csrf.php";
require_once "../includes/notification_events.php";

if (!isset($_SESSION["user_id"]) || strtolower($_SESSION["role"]) !== "customer") {
    header("Location: ../login.php");
    exit;
}

if (empty($_SESSION["cart"])) {
    header("Location: menu.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user_id = (int)$_SESSION["user_id"];
    $order_type = in_array($_POST["order_type"] ?? '', ['Dine In', 'Take Away']) ? $_POST["order_type"] : 'Dine In';
    $notes = htmlspecialchars(strip_tags($_POST["notes"] ?? ""), ENT_QUOTES, 'UTF-8');
    // Re-escape for DB
    $order_type_safe = $conn->real_escape_string($order_type);
    $notes_safe = $conn->real_escape_string($notes);

    $transaction_started = false;
    try {
        if (!$conn->begin_transaction()) throw new Exception("Gagal memulai transaksi.");
        $transaction_started = true;

        $total = 0;
        $cart_items = [];

        // Validasi: cart tidak boleh kosong
        if (empty($_SESSION["cart"])) throw new Exception("Keranjang kosong!");

        $cart_ids = array_keys($_SESSION["cart"]);
        // Validasi semua ID adalah integer positif
        $safe_ids = array_filter(array_map('intval', $cart_ids), fn($id) => $id > 0);
        if (empty($safe_ids)) throw new Exception("Data keranjang tidak valid!");

        $ids_str = implode(",", $safe_ids);

        // Ambil harga dari DB (TIDAK percaya harga dari session/frontend)
        $result = $conn->query(
            "SELECT p.id, p.name, p.price, s.current_stock, s.minimum_stock, s.id as stock_id
             FROM products p
             LEFT JOIN stock s ON p.id = s.product_id
             WHERE p.id IN ($ids_str) AND p.is_available = 1
             FOR UPDATE"
        );
        if (!$result) throw new Exception("Gagal membaca produk dan stok.");

        // Map hasil ke array untuk validasi
        $db_products = [];
        while ($row = $result->fetch_assoc()) {
            $db_products[$row["id"]] = $row;
        }

        foreach ($safe_ids as $product_id) {
            if (!isset($db_products[$product_id])) {
                throw new Exception("Produk tidak tersedia, silakan refresh halaman.");
            }

            $qty = (int)$_SESSION["cart"][$product_id];
            if ($qty <= 0) throw new Exception("Jumlah produk tidak valid!");

            $prod = $db_products[$product_id];
            if ($prod["current_stock"] < $qty) {
                throw new Exception("Stok " . htmlspecialchars($prod["name"]) . " tidak mencukupi. Sisa: " . $prod["current_stock"]);
            }

            // Hitung subtotal berdasarkan harga DB
            $unit_price = (float)$prod["price"];
            $subtotal = $unit_price * $qty;
            $total += $subtotal;
            $cart_items[] = [
                "id" => $product_id,
                "name" => $prod["name"],
                "qty" => $qty,
                "price" => $unit_price,
                "subtotal" => $subtotal,
                "stock_id" => $prod["stock_id"]
            ];
        }

        // 1. Buat order
        $stmt_order = $conn->prepare(
            "INSERT INTO orders (user_id, total_amount, order_type, status, notes) VALUES (?, ?, ?, 'PENDING', ?)"
        );
        $stmt_order->bind_param("idss", $user_id, $total, $order_type_safe, $notes_safe);
        if (!$stmt_order->execute()) throw new Exception("Gagal menyimpan pesanan.");
        $order_id = $stmt_order->insert_id;

        // 2. Simpan order_items & update stock
        $stmt_item = $conn->prepare(
            "INSERT INTO order_items (order_id, product_id, quantity, price, subtotal) VALUES (?, ?, ?, ?, ?)"
        );
        $stmt_stock = $conn->prepare(
            "UPDATE stock SET current_stock = current_stock - ? WHERE id = ? AND current_stock >= ?"
        );
        $stmt_st_log = $conn->prepare(
            "INSERT INTO stock_transactions (stock_id, type, quantity, reference_type, reference_id, notes) VALUES (?, 'OUT', ?, 'ORDER', ?, 'Pembelian Customer')"
        );

        foreach ($cart_items as $item) {
            $stmt_item->bind_param("iiidd", $order_id, $item["id"], $item["qty"], $item["price"], $item["subtotal"]);
            if (!$stmt_item->execute()) throw new Exception("Gagal menyimpan item pesanan.");

            // Kurangi stok (dengan proteksi current_stock >= qty)
            $stmt_stock->bind_param("iii", $item["qty"], $item["stock_id"], $item["qty"]);
            if (!$stmt_stock->execute() || $stmt_stock->affected_rows !== 1) {
                throw new Exception("Stok " . htmlspecialchars($item["name"]) . " sudah habis saat proses checkout!");
            }

            // Catat stock transaction log
            $stmt_st_log->bind_param("iii", $item["stock_id"], $item["qty"], $order_id);
            if (!$stmt_st_log->execute()) throw new Exception("Gagal mencatat mutasi stok.");

            $old_stock = (int)$db_products[$item["id"]]["current_stock"];
            $minimum_stock = (int)($db_products[$item["id"]]["minimum_stock"] ?? 0);
            $new_stock = $old_stock - (int)$item["qty"];
            if (!notification_event_stock_change($conn, (string)$item["name"], $old_stock, $new_stock, $minimum_stock)) {
                throw new Exception("Gagal mencatat notifikasi stok.");
            }
        }

        if (!notification_event_send($conn, $user_id, 'Pesanan diterima', 'Pesanan Anda berhasil dibuat dan menunggu pemrosesan kasir.', 'info')) {
            throw new Exception("Gagal mencatat notifikasi pesanan.");
        }
        if (!notification_event_send_roles($conn, ['Admin', 'Kasir'], 'Pesanan Customer baru', 'Pesanan baru #' . str_pad((string)$order_id, 5, '0', STR_PAD_LEFT) . ' menunggu pemrosesan.', 'info')) {
            throw new Exception("Gagal mencatat notifikasi pesanan.");
        }

        if (!$conn->commit()) throw new Exception("Gagal menyelesaikan transaksi.");
        $transaction_started = false;
        unset($_SESSION["cart"]);
        $_SESSION["ui_notification"] = ["type" => "success", "message" => "Pesanan berhasil dibuat dan masuk ke antrean kasir."];
        header("Location: history.php");
        exit;

    } catch (Exception $e) {
        if ($transaction_started) $conn->rollback();
        error_log("Customer checkout failed");
        $_SESSION["ui_notification"] = ["type" => "error", "message" => "Pesanan belum berhasil dibuat. Periksa kembali ketersediaan menu dan coba lagi."];
        header("Location: cart.php");
        exit;
    }
}

header("Location: cart.php");
