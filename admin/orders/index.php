<?php
session_start();
require_once '../../config/database.php';
require_once '../../functions/auth_check.php';

requireLogin('admin', '../../login.php');

$message = isset($_GET['success']) ? htmlspecialchars($_GET['success']) : '';
$error = '';

// Array untuk status pesanan yang valid
$valid_statuses = ['pending', 'processing', 'shipped', 'completed', 'cancelled'];

// --- A. Logika UPDATE Status Pesanan (POST Request) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'update_status') {
    $order_id = (int)($_POST['order_id'] ?? 0);
    $new_status = $conn->real_escape_string($_POST['status'] ?? '');

    if ($order_id > 0 && in_array($new_status, $valid_statuses)) {
        $stmt = $conn->prepare("UPDATE orders SET order_status = ? WHERE id = ?");
        $stmt->bind_param("si", $new_status, $order_id);
        
        if ($stmt->execute()) {
            header("Location: index.php?success=Status pesanan #{$order_id} berhasil diubah menjadi " . ucfirst($new_status));
            exit();
        } else {
            $error = "Gagal mengubah status pesanan: " . $stmt->error;
        }
        $stmt->close();
    } else {
        $error = "Data pesanan atau status tidak valid.";
    }
}

// --- B. Ambil Data Pesanan untuk Tampilan (READ) ---
// Kita ambil data pesanan beserta nama user yang memesan.
$query = "SELECT 
            o.id, 
            u.username, 
            o.total_amount, 
            o.order_status, 
            o.order_date
          FROM orders o
          JOIN users u ON o.user_id = u.id
          ORDER BY o.order_date DESC";
          
$orders_result = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Admin - Manajemen Pesanan</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        .status-pending { color: orange; font-weight: bold; }
        .status-completed { color: green; font-weight: bold; }
        .status-cancelled { color: red; font-weight: bold; }
    </style>
</head>
<body>
    <header>
        <nav>
            <p>⚙️ **Admin Panel** | Selamat datang, <?php echo htmlspecialchars($_SESSION['username']); ?></p>
            <ul>
                <li><a href="../dashboard.php">Dashboard</a></li>
                <li><a href="../products/index.php">Produk</a></li>
                <li><a href="../categories/index.php">Kategori</a></li>
                <li><a href="index.php">Pesanan</a></li>
                <li><a href="../../logout.php" style="color: red;">Logout</a></li>
            </ul>
        </nav>
    </header>

    <main class="container">
        <h3>Daftar Pesanan User</h3>
        
        <?php if ($message): ?>
            <p style="color: green; font-weight: bold;"><?php echo $message; ?></p>
        <?php endif; ?>
        <?php if ($error): ?>
            <p style="color: red; font-weight: bold;"><?php echo $error; ?></p>
        <?php endif; ?>

        <?php if ($orders_result->num_rows > 0): ?>
            <table border="1" style="width: 100%; border-collapse: collapse; margin-top: 15px;">
                <thead>
                    <tr>
                        <th>ID Pesanan</th>
                        <th>User</th>
                        <th>Tanggal</th>
                        <th>Total</th>
                        <th>Status</th>
                        <th>Aksi</th>
                        <th>Detail</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($order = $orders_result->fetch_assoc()): ?>
                    <tr>
                        <td>#<?php echo $order['id']; ?></td>
                        <td><?php echo htmlspecialchars($order['username']); ?></td>
                        <td><?php echo date("d M Y H:i", strtotime($order['order_date'])); ?></td>
                        <td>Rp <?php echo number_format($order['total_amount'], 0, ',', '.'); ?></td>
                        <td class="status-<?php echo strtolower($order['order_status']); ?>">
                            <?php echo ucfirst($order['order_status']); ?>
                        </td>
                        <td>
                            <form method="POST" action="index.php" style="display: inline;">
                                <input type="hidden" name="action" value="update_status">
                                <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                <select name="status" required>
                                    <?php foreach ($valid_statuses as $status): ?>
                                        <option value="<?php echo $status; ?>" 
                                            <?php echo ($status == $order['order_status']) ? 'selected' : ''; ?>>
                                            <?php echo ucfirst($status); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" style="padding: 3px 5px;">Ubah</button>
                            </form>
                        </td>
                        <td>
                            <a href="detail.php?id=<?php echo $order['id']; ?>">Lihat Item</a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>Belum ada pesanan yang masuk.</p>
        <?php endif; ?>
    </main>

    <footer>
        <p>&copy; 2024 Admin Coffee Store.</p>
    </footer>
</body>
</html>

<?php
$conn->close();
?>