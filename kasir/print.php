<?php
if (session_status() == PHP_SESSION_NONE) session_start();
if (!isset($_SESSION["user_id"]) || strtolower($_SESSION["role"]) !== "kasir") exit;
require "../config.php";
$id = (int)$_GET['id'];
$order = $conn->query("SELECT o.*, u.name as cashier_name FROM orders o LEFT JOIN users u ON o.user_id = u.id WHERE o.id = $id")->fetch_assoc();
if(!$order) die("Transaksi tidak valid");
$payment = $conn->query("SELECT * FROM payments WHERE order_id = $id")->fetch_assoc();
$items = $conn->query("SELECT oi.*, p.name FROM order_items oi JOIN products p ON oi.product_id = p.id WHERE oi.order_id = $id");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Struk #<?= $id ?></title>
    <style>
        body { font-family: monospace; width: 300px; margin: 0 auto; color: #000; }
        .center { text-align: center; }
        .right { text-align: right; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { padding: 4px 0; border-bottom: 1px dashed #ccc; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body onload="window.print()">
    <div class="no-print" style="margin-bottom: 20px; text-align: center;">
        <button onclick="window.print()" style="padding:10px; background:#d97706; color:#fff; border:none; cursor:pointer;">Cetak Ulang</button>
        <button onclick="window.close()" style="padding:10px; background:#ccc; border:none; cursor:pointer;">Tutup</button>
    </div>
    <div class="center">
        <h2>KOPI MAHASISWA</h2>
        <p>Jl. Pendidikan No 123<br>Telp: 0812-3456-7890</p>
        <p>--------------------------------</p>
        <p>No: #<?= str_pad($id, 5, '0', STR_PAD_LEFT) ?><br>Waktu: <?= date("d/m/Y H:i", strtotime($order['created_at'])) ?><br>Kasir: <?= htmlspecialchars($order['cashier_name']) ?></p>
        <p>--------------------------------</p>
    </div>
    <table>
        <?php while($item = $items->fetch_assoc()): ?>
        <tr>
            <td colspan="3"><?= htmlspecialchars($item['name']) ?></td>
        </tr>
        <tr>
            <td><?= $item['quantity'] ?> x</td>
            <td><?= number_format($item['price'],0,',','.') ?></td>
            <td class="right"><?= number_format($item['subtotal'],0,',','.') ?></td>
        </tr>
        <?php endwhile; ?>
    </table>
    <div style="margin-top:10px;">
        <p class="right"><strong>Total: Rp <?= number_format($order['total_amount'],0,',','.') ?></strong></p>
        <p class="right">Tunai: Rp <?= number_format($payment['amount_paid'] ?? 0,0,',','.') ?></p>
        <p class="right">Kembali: Rp <?= number_format($payment['change_amount'] ?? 0,0,',','.') ?></p>
    </div>
    <div class="center" style="margin-top:20px;">
        <p>Terima Kasih!<br>Selamat Menikmati Kopi Anda</p>
        <p style="font-size: 10px; color: #777; margin-top: 15px;">Built by Sahlan</p>
    </div>
</body>
</html>