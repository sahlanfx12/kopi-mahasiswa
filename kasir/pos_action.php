<?php
session_start();
if (!isset($_SESSION["user_id"]) || strtolower($_SESSION["role"]) !== "kasir") exit;
require_once "../includes/csrf.php";
if (!isset($_SESSION["kasir_cart"])) $_SESSION["kasir_cart"] = [];
$action = $_POST["action"] ?? "";
$product_id = $_POST["product_id"] ?? 0;
if ($action === "add") $_SESSION["kasir_cart"][$product_id] = ($_SESSION["kasir_cart"][$product_id] ?? 0) + 1;
elseif ($action === "remove") unset($_SESSION["kasir_cart"][$product_id]);
header("Location: pos.php");
exit;
