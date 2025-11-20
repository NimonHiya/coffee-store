<?php
session_start();
require_once 'config/database.php';
require_once 'functions/auth_check.php';

// PENTING: User harus login untuk melihat riwayat pesanannya sendiri.
requireLogin('user', 'login.php?redirect=user_orders.php'); 

$user_id = $_SESSION['user_id'];

// 1. Ambil semua pesanan milik user yang sedang login
$query = "SELECT 
            o.id, 
            o.total_amount, 
            o.order_status, 
            o.order_date
          FROM orders o
          WHERE o.user_id = ?
          ORDER BY o.order_date DESC";
          
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$orders_result = $stmt->get_result();

$message = isset($_GET['success']) ? htmlspecialchars($_GET['success']) : '';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Riwayat Pesanan Saya</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        /* Gaya status pesanan yang sama dengan Admin */
        .status-pending { color: orange; font-weight: bold; }
        .status-processing { color: blue; font-weight: bold; }
        .status-completed { color: green; font-weight: bold; }
        .status-cancelled { color: red; font-weight: bold; }
    </style>
</head>
<body>
    <header>
        <nav>
            <h1>☕ Coffee Store</h1>
            <ul>
                <li><a href="index.php">Home</a></li>
                <li><a href="products.php">Produk</a></li>
                <li><a href="cart.php">Keranjang</a></li>
                <li><a href="user_orders.php">Riwayat Pesanan</a></li> 
                <li><a href="logout.php">Logout (<?php echo htmlspecialchars($_SESSION['username']); ?>)</a></li>
            </ul>
        </nav>
    </header>

    <main class="container">
        <h2>Riwayat Pesanan Saya</h2>
        
        <?php if ($message): ?>
            <p style="color: green; font-weight: bold;"><?php echo $message; ?></p>
        <?php endif; ?>

        <?php if ($orders_result->num_rows > 0): ?>
            <table border="1" style="width: 100%; border-collapse: collapse; margin-top: 15px;">
                <thead>
                    <tr>
                        <th>ID Pesanan</th>
                        <th>Tanggal</th>
                        <th>Total</th>
                        <th>Status</th>
                        <th>Detail</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($order = $orders_result->fetch_assoc()): ?>
                    <tr>
                        <td>#<?php echo $order['id']; ?></td>
                        <td><?php echo date("d M Y H:i", strtotime($order['order_date'])); ?></td>
                        <td>Rp <?php echo number_format($order['total_amount'], 0, ',', '.'); ?></td>
                        <td class="status-<?php echo strtolower($order['order_status']); ?>">
                            <?php echo ucfirst($order['order_status']); ?>
                        </td>
                        <td>
                            <a href="order_detail.php?id=<?php echo $order['id']; ?>">Lihat Item</a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>Anda belum pernah melakukan pemesanan.</p>
        <?php endif; ?>
    </main>

    <footer>
        <p>&copy; 2024 Coffee Store. Powered by PHP Native.</p>
    </footer>
</body>
</html>

<?php
$stmt->close();
$conn->close();
?>