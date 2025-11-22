<?php
session_start();
require_once '../../config/database.php';
require_once '../../functions/auth_check.php';

// --- OPTIMASI PATH & BASE URL ---
// Tentukan base path untuk navigasi yang benar (naik dua level dari /admin/orders/detail.php)
$base_path = rtrim(str_replace('\\','/', dirname(dirname(dirname($_SERVER['SCRIPT_NAME'])))), '/');
if ($base_path === '' || $base_path === '.') {
    $base_path = '/';
} else {
    $base_path = $base_path . '/';
}
$admin_base = $base_path . 'admin/';

// Panggil fungsi proteksi! Hanya Admin yang boleh akses
requireLogin('admin', $base_path . 'login.php');

$admin_name = $_SESSION['username'] ?? 'Admin';

$order_id = (int)($_GET['id'] ?? 0);
if ($order_id === 0) {
    header("Location: index.php");
    exit();
}

// 1. Ambil data pesanan utama
$stmt_order = $conn->prepare("SELECT o.*, u.username FROM orders o JOIN users u ON o.user_id = u.id WHERE o.id = ?");
$stmt_order->bind_param("i", $order_id);
$stmt_order->execute();
$order = $stmt_order->get_result()->fetch_assoc();
$stmt_order->close();

if (!$order) {
    header("Location: index.php?error=" . urlencode("Pesanan tidak ditemukan."));
    exit();
}

// 2. Ambil item-item pesanan
$stmt_items = $conn->prepare("SELECT oi.*, p.name FROM order_items oi JOIN products p ON oi.product_id = p.id WHERE oi.order_id = ?");
$stmt_items->bind_param("i", $order_id);
$stmt_items->execute();
$items_result = $stmt_items->get_result();
$stmt_items->close();

// Definisikan kelas status untuk Badge Bootstrap
$status_classes = [
    'pending' => 'warning',
    'processing' => 'primary',
    'shipped' => 'info',
    'completed' => 'success',
    'cancelled' => 'danger'
];
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Detail Pesanan #<?php echo $order_id; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css"> 
    <style>
        :root {
            --primary-color: #6F4E37; /* Coklat Kopi */
        }
        .admin-navbar {
            background-color: var(--primary-color) !important;
        }
        .info-header {
            background-color: #f8f9fa;
        }
    </style>
</head>
<body>
    <header>
        <nav class="navbar navbar-expand-lg navbar-dark admin-navbar shadow">
            <div class="container-fluid">
                <a class="navbar-brand fw-bold" href="<?= $admin_base; ?>dashboard.php">☕ Admin Panel</a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#adminNav" aria-controls="adminNav" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="adminNav">
                    <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                        <li class="nav-item"><a class="nav-link" href="<?= $admin_base; ?>dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a></li>
                        <li class="nav-item"><a class="nav-link" href="<?= $admin_base; ?>products/index.php"><i class="bi bi-box"></i> Produk</a></li>
                        <li class="nav-item"><a class="nav-link" href="<?= $admin_base; ?>categories/index.php"><i class="bi bi-tags"></i> Kategori</a></li>
                        <li class="nav-item"><a class="nav-link active" aria-current="page" href="index.php"><i class="bi bi-cart-check"></i> Pesanan</a></li>
                    </ul>
                    
                    <ul class="navbar-nav ms-auto">
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="bi bi-person-circle"></i> <?php echo htmlspecialchars($admin_name); ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                                <li><a class="dropdown-item text-danger" href="<?= $base_path; ?>logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a></li>
                            </ul>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>
    </header>

    <main class="container my-5">
        <h3 class="mb-4"><i class="bi bi-receipt"></i> Detail Pesanan #<?php echo $order['id']; ?></h3>
        
        <a href="index.php" class="btn btn-outline-secondary mb-4">
            <i class="bi bi-arrow-left"></i> Kembali ke Daftar Pesanan
        </a>

        <div class="row g-4">
            <div class="col-lg-5">
                <div class="card shadow-sm">
                    <div class="card-header info-header fw-bold">
                        Informasi Utama
                    </div>
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Pelanggan:
                            <span class="fw-bold"><?php echo htmlspecialchars($order['username']); ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Tanggal Pesan:
                            <span><?php echo date("d M Y H:i", strtotime($order['order_date'])); ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Status Saat Ini:
                            <?php 
                                $status_key = strtolower($order['order_status']);
                                $badge_class = $status_classes[$status_key] ?? 'secondary';
                            ?>
                            <span class="badge rounded-pill bg-<?php echo $badge_class; ?>">
                                <?php echo ucfirst($order['order_status']); ?>
                            </span>
                        </li>
                    </ul>
                </div>
            </div>

            <div class="col-lg-7">
                <div class="card bg-success text-white shadow-sm">
                    <div class="card-body">
                        <h4 class="card-title mb-2">Total Pembayaran</h4>
                        <h2 class="display-5 fw-bold">Rp <?php echo number_format($order['total_amount'], 0, ',', '.'); ?></h2>
                        <p class="card-text">Status: <?php echo ucfirst($order['order_status']); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <h4 class="mt-5 mb-3"><i class="bi bi-basket-fill"></i> Item Pesanan</h4>
        
        <?php if ($items_result->num_rows > 0): ?>
            <div class="table-responsive">
                <table class="table table-hover table-bordered align-middle">
                    <thead class="table-dark" style="background-color: var(--primary-color);">
                        <tr>
                            <th>Nama Produk</th>
                            <th class="text-end">Harga Satuan</th>
                            <th class="text-center">Jumlah</th>
                            <th class="text-end">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $subtotal_check = 0; while($item = $items_result->fetch_assoc()): ?>
                        <tr>
                            <td class="fw-bold"><?php echo htmlspecialchars($item['name']); ?></td>
                            <td class="text-end">Rp <?php echo number_format($item['price_at_order'], 0, ',', '.'); ?></td>
                            <td class="text-center"><?php echo $item['quantity']; ?></td>
                            <td class="text-end fw-bold">Rp <?php echo number_format($item['price_at_order'] * $item['quantity'], 0, ',', '.'); ?></td>
                        </tr>
                        <?php $subtotal_check += $item['price_at_order'] * $item['quantity']; endwhile; ?>
                        
                        <tr class="table-secondary">
                            <td colspan="3" class="text-end fw-bold">TOTAL ITEM</td>
                            <td class="text-end fw-bold fs-6">Rp <?php echo number_format($subtotal_check, 0, ',', '.'); ?></td>
                        </tr>
                        <?php if ($subtotal_check != $order['total_amount']): ?>
                            <tr class="table-danger">
                                <td colspan="4" class="text-center">
                                    <i class="bi bi-exclamation-triangle-fill"></i> Peringatan: Total Item tidak sesuai dengan Total Pesanan yang tersimpan!
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
             <div class="alert alert-warning">Tidak ada item yang ditemukan untuk pesanan ini.</div>
        <?php endif; ?>
    </main>

    <footer class="text-center py-3 mt-5 border-top">
        <p class="mb-0">&copy; 2024 Admin Coffee Store. Powered by Bootstrap 5.</p>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php
if (isset($conn)) {
    // Koneksi sudah ditutup di PHP logic sebelumnya, tapi sebagai keamanan:
    // $conn->close();
}
?>