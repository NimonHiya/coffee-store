<?php
// Selalu mulai session di awal halaman yang membutuhkannya
session_start(); 

require_once 'config/database.php';
// Asumsikan 'functions/auth_check.php' berisi fungsi requireLogin()
require_once 'functions/auth_check.php'; 

// --- OPTIMASI PATH GAMBAR & BASE URL (untuk konsistensi navigasi) ---
$base_url = rtrim(str_replace('\\','/', dirname($_SERVER['SCRIPT_NAME'])), '/');
if ($base_url === '' || $base_url === '.') {
    $base_url = '';
} else {
    $base_url = $base_url . '/';
}

// PENTING: User harus login untuk melihat riwayat pesanannya sendiri.
// Asumsikan fungsi requireLogin() ada dan berfungsi
requireLogin('user', 'login.php?redirect=user_orders.php'); 

$user_id = $_SESSION['user_id'];

// 1. Ambil semua pesanan milik user yang sedang login
$orders_result = false;
$message = '';

if (isset($conn)) {
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

    // Pastikan menutup statement dan koneksi
    $stmt->close();
    $conn->close();
}


$message = isset($_GET['success']) ? htmlspecialchars($_GET['success']) : '';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Pesanan Saya</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        :root {
            --primary-color: #6F4E37; /* Coklat Kopi */
        }
        .navbar { background-color: var(--primary-color) !important; }

        /* Gaya status menggunakan Badge Bootstrap */
        .status-pending { background-color: #ffc107; color: #333; } /* Warning */
        .status-processing { background-color: #0d6efd; color: white; } /* Primary */
        .status-completed { background-color: #198754; color: white; } /* Success */
        .status-cancelled { background-color: #dc3545; color: white; } /* Danger */
    </style>
</head>
<body>
    <header>
        <nav class="navbar navbar-expand-lg navbar-dark shadow-sm" style="background-color: var(--primary-color);">
            <div class="container">
                <a class="navbar-brand text-white fw-bold h4 mb-0" href="<?= $base_url; ?>index.php">☕ Coffee Store</a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="mainNav">
                    <ul class="navbar-nav ms-auto">
                        <li class="nav-item"><a class="nav-link" href="<?= $base_url; ?>index.php">Home</a></li>
                        <li class="nav-item"><a class="nav-link" href="<?= $base_url; ?>products.php">Produk</a></li>
                        <li class="nav-item"><a class="nav-link" href="<?= $base_url; ?>cart.php">Keranjang</a></li>
                        <li class="nav-item"><a class="nav-link active" aria-current="page" href="<?= $base_url; ?>user_orders.php">Riwayat Pesanan</a></li> 
                        <li class="nav-item"><a class="nav-link" href="<?= $base_url; ?>logout.php">Logout (<?php echo htmlspecialchars($_SESSION['username']); ?>)</a></li>
                    </ul>
                </div>
            </div>
        </nav>
    </header>

    <main class="container my-5">
        <h2 class="mb-4">📜 Riwayat Pesanan Saya</h2>
        
        <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle-fill"></i> **Sukses!** <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if ($orders_result && $orders_result->num_rows > 0): ?>
            <div class="table-responsive">
                <table class="table table-hover table-bordered align-middle">
                    <thead class="table-dark" style="background-color: var(--primary-color);">
                        <tr>
                            <th>ID Pesanan</th>
                            <th>Tanggal</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($order = $orders_result->fetch_assoc()): ?>
                        <tr>
                            <td class="fw-bold text-center">#<?php echo $order['id']; ?></td>
                            <td><?php echo date("d M Y H:i", strtotime($order['order_date'])); ?></td>
                            <td>Rp **<?php echo number_format($order['total_amount'], 0, ',', '.'); ?>**</td>
                            <td>
                                <?php 
                                    $status_class = strtolower($order['order_status']);
                                    $display_status = ucfirst($order['order_status']);
                                ?>
                                <span class="badge rounded-pill status-<?php echo $status_class; ?>">
                                    <?php echo $display_status; ?>
                                </span>
                            </td>
                            <td>
                                <a href="<?= $base_url; ?>order_detail.php?id=<?php echo $order['id']; ?>" class="btn btn-sm btn-outline-info">
                                    <i class="bi bi-eye"></i> Lihat Item
                                </a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="alert alert-info text-center mt-4">
                <i class="bi bi-info-circle"></i> Anda belum memiliki riwayat pemesanan. Mulai belanja sekarang!
            </div>
            <div class="text-center">
                 <a href="<?= $base_url; ?>products.php" class="btn btn-primary mt-3">Lihat Produk</a>
            </div>
        <?php endif; ?>
    </main>

    <footer class="text-center py-3 mt-5 border-top">
        <p class="mb-0">&copy; 2024 Coffee Store. Powered by PHP Native.</p>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>