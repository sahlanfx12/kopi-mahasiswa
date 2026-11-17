<?php
session_start();

if (!isset($_SESSION["user_id"]) || strtolower($_SESSION["role"]) !== "customer") {
    header("Location: ../login.php");
    exit;
}

if (!isset($_SESSION["cart"])) {
    $_SESSION["cart"] = [];
}

$action = $_POST["action"] ?? "";
$product_id = filter_var($_POST["product_id"] ?? null, FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]]);
$qty = filter_var($_POST["qty"] ?? 1, FILTER_VALIDATE_INT);
$notice = ["type" => "error", "message" => "Perubahan keranjang tidak valid. Silakan coba kembali."];

if ($product_id !== false && $qty !== false && $qty > 0 && $action === "add") {
    if (isset($_SESSION["cart"][$product_id])) {
        $_SESSION["cart"][$product_id] += $qty;
    } else {
        $_SESSION["cart"][$product_id] = $qty;
    }
    $notice = ["type" => "success", "message" => "Menu berhasil ditambahkan ke keranjang."];
} elseif ($product_id !== false && $qty !== false && $action === "update") {
    if ($qty > 0) {
        $_SESSION["cart"][$product_id] = $qty;
        $notice = ["type" => "success", "message" => "Jumlah menu di keranjang berhasil diperbarui."];
    } else {
        unset($_SESSION["cart"][$product_id]);
        $notice = ["type" => "success", "message" => "Menu berhasil dihapus dari keranjang."];
    }
} elseif ($product_id !== false && $action === "remove") {
    unset($_SESSION["cart"][$product_id]);
    $notice = ["type" => "success", "message" => "Menu berhasil dihapus dari keranjang."];
}
$_SESSION["ui_notification"] = $notice;

// Kembalikan ke halaman sebelumnya atau cart
$referer = $_SERVER["HTTP_REFERER"] ?? "cart.php";
if (strpos($referer, "menu.php") !== false) {
    header("Location: menu.php");
} else {
    header("Location: cart.php");
}
exit;
?>
