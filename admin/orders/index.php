<?php
session_start();
require_once '../../config/database.php';
require_once '../../functions/auth_check.php';

// --- OPTIMASI PATH & BASE URL ---
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
$message = isset($_GET['success']) ? htmlspecialchars($_GET['success']) : '';
$error = '';

// Array untuk status pesanan yang valid
$valid_statuses = ['pending', 'processing', 'shipped', 'completed', 'cancelled'];

// --- A. Logika UPDATE Status Pesanan (POST Request) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'update_status' && isset($conn)) {
    $order_id = (int)($_POST['order_id'] ?? 0);
    $new_status = $conn->real_escape_string($_POST['status'] ?? '');

    if ($order_id > 0 && in_array($new_status, $valid_statuses)) {
        
        // 1. Cek Status Pesanan Saat Ini sebelum Update
        $stmt_check = $conn->prepare("SELECT order_status FROM orders WHERE id = ?");
        $stmt_check->bind_param("i", $order_id);
        $stmt_check->execute();
        $current_order_status = $stmt_check->get_result()->fetch_column();
        $stmt_check->close();

        // Cek apakah status lama sudah Completed atau Cancelled
        if (in_array($current_order_status, ['completed', 'cancelled'])) {
            $error = "Gagal mengubah status: Pesanan #{$order_id} sudah berstatus **" . ucfirst($current_order_status) . "** dan tidak dapat diubah lagi.";
        } else {
            // 2. Lakukan Update jika valid
            $stmt = $conn->prepare("UPDATE orders SET order_status = ? WHERE id = ?");
            $stmt->bind_param("si", $new_status, $order_id);
            
            if ($stmt->execute()) {
                header("Location: index.php?success=" . urlencode("Status pesanan #{$order_id} berhasil diubah menjadi " . ucfirst($new_status)));
                exit();
            } else {
                $error = "Gagal mengubah status pesanan: " . $stmt->error;
            }
            $stmt->close();
        }
    } else {
        $error = "Data pesanan atau status tidak valid.";
    }
}

// --- B. Ambil Data Pesanan untuk Tampilan (READ) ---
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Manajemen Pesanan</title>
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
        /* Gaya status menggunakan Badge Bootstrap */
        .status-pending { background-color: #ffc107; color: #333; } /* Warning */
        .status-processing { background-color: #0d6efd; color: white; } /* Primary */
        .status-shipped { background-color: #0dcaf0; color: #333; } /* Info */
        .status-completed { background-color: #198754; color: white; } /* Success */
        .status-cancelled { background-color: #dc3545; color: white; } /* Danger */
        
        /* Gaya tambahan untuk baris yang tidak dapat diubah */
        .order-locked {
            opacity: 0.7;
            background-color: #f1f1f1;
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
        <h3 class="mb-4"><i class="bi bi-list-check"></i> Daftar Pesanan User</h3>
        
        <?php if ($message): ?>
            <?php 
                // Gantikan '**' dengan <strong> untuk pesan sukses
                $display_message = str_replace(['**'], ['<strong>', '</strong>'], $message);
            ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i> **Sukses!** <?php echo $display_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <?php 
                // Gantikan '**' dengan <strong> untuk pesan error
                $display_error = str_replace(['**'], ['<strong>', '</strong>'], $error);
            ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i> **Error:** <?php echo $display_error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if ($orders_result && $orders_result->num_rows > 0): ?>
            <div class="table-responsive">
                <table class="table table-hover table-bordered align-middle">
                    <thead class="table-dark" style="background-color: var(--primary-color);">
                        <tr>
                            <th class="text-center">ID Pesanan</th>
                            <th>User</th>
                            <th>Tanggal</th>
                            <th class="text-end">Total</th>
                            <th class="text-center" style="width: 15%;">Status Saat Ini</th>
                            <th class="text-center" style="width: 25%;">Ubah Status</th>
                            <th>Detail</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($order = $orders_result->fetch_assoc()): 
                            $status_class = strtolower($order['order_status']);
                            $display_status = ucfirst($order['order_status']);
                            $is_locked = in_array($status_class, ['completed', 'cancelled']);
                        ?>
                        <tr class="<?= $is_locked ? 'order-locked' : ''; ?>">
                            <td class="text-center fw-bold">#<?php echo $order['id']; ?></td>
                            <td><?php echo htmlspecialchars($order['username']); ?></td>
                            <td><?php echo date("d M Y H:i", strtotime($order['order_date'])); ?></td>
                            <td class="text-end fw-bold">Rp <?php echo number_format($order['total_amount'], 0, ',', '.'); ?></td>
                            <td class="text-center">
                                <span class="badge rounded-pill status-<?php echo $status_class; ?>">
                                    <?php echo $display_status; ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($is_locked): ?>
                                    <span class="text-muted"><i class="bi bi-lock-fill"></i> Tidak Dapat Diubah</span>
                                <?php else: ?>
                                    <form method="POST" action="index.php" class="d-flex align-items-center justify-content-center">
                                        <input type="hidden" name="action" value="update_status">
                                        <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                        
                                        <select name="status" class="form-select form-select-sm me-2" style="width: auto;" required>
                                            <?php foreach ($valid_statuses as $status): ?>
                                                <option value="<?php echo $status; ?>" 
                                                    <?php echo ($status == $order['order_status']) ? 'selected' : ''; ?>>
                                                    <?php echo ucfirst($status); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="submit" class="btn btn-sm btn-primary">
                                            <i class="bi bi-arrow-repeat"></i> Ubah
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <a href="detail.php?id=<?php echo $order['id']; ?>" class="btn btn-sm btn-outline-info">
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
                <i class="bi bi-info-circle"></i> Belum ada pesanan yang masuk.
            </div>
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
    $conn->close();
}
?>