<?php
session_start();
require_once 'config/database.php';
require_once 'functions/auth_check.php';

requireLogin('user', 'login.php'); 

$user_id = $_SESSION['user_id'];
$order_id = (int)($_GET['id'] ?? 0);

if ($order_id === 0) {
    header("Location: user_orders.php");
    exit();
}

// 1. Ambil data pesanan utama, PASTIKAN HANYA MILIK USER INI
$stmt_order = $conn->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ?");
$stmt_order->bind_param("ii", $order_id, $user_id);
$stmt_order->execute();
$order = $stmt_order->get_result()->fetch_assoc();
$stmt_order->close();

if (!$order) {
    // Jika pesanan tidak ditemukan atau BUKAN milik user ini
    header("Location: user_orders.php?error=Pesanan tidak ditemukan atau akses ditolak.");
    exit();
}

// 2. Ambil item-item pesanan
$stmt_items = $conn->prepare("SELECT oi.*, p.name FROM order_items oi JOIN products p ON oi.product_id = p.id WHERE oi.order_id = ?");
$stmt_items->bind_param("i", $order_id);
$stmt_items->execute();
$items_result = $stmt_items->get_result();

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Detail Pesanan #<?php echo $order_id; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <header>...</header>

    <main class="container">
        <h3>Detail Pesanan #<?php echo $order['id']; ?></h3>
        <p><a href="user_orders.php">← Kembali ke Riwayat Pesanan</a></p>

        <div style="border: 1px solid #ccc; padding: 15px; margin-bottom: 20px;">
            <p><strong>Tanggal Pesan:</strong> <?php echo date("d M Y H:i", strtotime($order['order_date'])); ?></p>
            <p><strong>Status Saat Ini:</strong> <span class="status-<?php echo strtolower($order['order_status']); ?>"><?php echo ucfirst($order['order_status']); ?></span></p>
            <p><strong>Total Pembayaran:</strong> Rp <?php echo number_format($order['total_amount'], 0, ',', '.'); ?></p>
        </div>

        <h4>Item Pesanan</h4>
        <table border="1" style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr>
                    <th>Nama Produk</th>
                    <th>Harga Satuan</th>
                    <th>Jumlah</th>
                    <th>Subtotal</th>
                </tr>
            </thead>
            <tbody>
                <?php $subtotal_check = 0; while($item = $items_result->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars($item['name']); ?></td>
                    <td>Rp <?php echo number_format($item['price_at_order'], 0, ',', '.'); ?></td>
                    <td><?php echo $item['quantity']; ?></td>
                    <td>Rp <?php echo number_format($item['price_at_order'] * $item['quantity'], 0, ',', '.'); ?></td>
                </tr>
                <?php $subtotal_check += $item['price_at_order'] * $item['quantity']; endwhile; ?>
                <tr>
                    <td colspan="3" style="text-align: right; font-weight: bold;">TOTAL</td>
                    <td style="font-weight: bold;">Rp <?php echo number_format($subtotal_check, 0, ',', '.'); ?></td>
                </tr>
            </tbody>
        </table>
    </main>
    
    <footer>...</footer>
</body>
</html>

<?php
$stmt_items->close();
$conn->close();
?>