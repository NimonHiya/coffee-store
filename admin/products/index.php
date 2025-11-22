<?php
// 1. PENTING: Selalu mulai session di halaman yang butuh data user
session_start();

// 2. Sertakan koneksi database (jika dibutuhkan) dan fungsi otentikasi
// Sesuaikan path ke file konfigurasi
require_once '../../config/database.php';
require_once '../../functions/auth_check.php';

// --- OPTIMASI PATH & BASE URL ---
// Tentukan base path untuk navigasi yang benar (naik dua level dari /admin/products/index.php)
$base_path = rtrim(str_replace('\\','/', dirname(dirname(dirname($_SERVER['SCRIPT_NAME'])))), '/');
if ($base_path === '' || $base_path === '.') {
    $base_path = '/';
} else {
    $base_path = $base_path . '/';
}
$admin_base = $base_path . 'admin/';


// 3. Panggil fungsi proteksi! Hanya Admin yang boleh akses
requireLogin('admin', $base_path . 'login.php');

// 4. Ambil data produk
$query = "SELECT 
             p.id, 
             p.name, 
             c.name AS category_name, 
             p.price, 
             p.stock,
             p.is_active
           FROM products p
           LEFT JOIN categories c ON p.category_id = c.id
           ORDER BY p.id DESC";
             
$result = $conn->query($query);

// Cek apakah ada pesan sukses (dari create/edit)
$message = isset($_GET['success']) ? htmlspecialchars($_GET['success']) : '';
// Cek apakah ada pesan error (dari delete.php)
$error = isset($_GET['error']) ? htmlspecialchars($_GET['error']) : '';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Daftar Produk</title>
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
        .btn-add {
            background-color: #28a745; /* Hijau */
            border-color: #28a745;
        }
        .btn-add:hover {
            background-color: #1e7e34;
            border-color: #1e7e34;
        }
        .btn-edit {
            color: #ffc107;
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
                        <li class="nav-item"><a class="nav-link active" aria-current="page" href="index.php"><i class="bi bi-box"></i> Produk</a></li>
                        <li class="nav-item"><a class="nav-link" href="<?= $admin_base; ?>categories/index.php"><i class="bi bi-tags"></i> Kategori</a></li>
                        <li class="nav-item"><a class="nav-link" href="<?= $admin_base; ?>orders/index.php"><i class="bi bi-cart-check"></i> Pesanan</a></li>
                    </ul>
                    
                    <ul class="navbar-nav ms-auto">
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="bi bi-person-circle"></i> <?php echo htmlspecialchars($_SESSION['username']); ?>
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
        <h3 class="mb-4"><i class="bi bi-box-seam"></i> Daftar Produk</h3>
        
        <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i> **Sukses!** <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-x-octagon-fill me-2"></i> **Gagal Hapus:** <?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="d-flex justify-content-end mb-4">
            <a href="create.php" class="btn btn-add btn-lg text-white shadow-sm">
                <i class="bi bi-plus-circle-fill"></i> Tambah Produk Baru
            </a>
        </div>

        <?php if ($result->num_rows > 0): ?>
            <div class="table-responsive">
                <table class="table table-hover table-bordered align-middle">
                    <thead class="table-dark" style="background-color: var(--primary-color);">
                        <tr>
                            <th class="text-center">ID</th>
                            <th>Nama Produk</th>
                            <th>Kategori</th>
                            <th class="text-end">Harga</th>
                            <th class="text-center">Stok</th>
                            <th class="text-center">Status</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td class="text-center"><?php echo $row['id']; ?></td>
                            <td class="fw-bold"><?php echo htmlspecialchars($row['name']); ?></td>
                            <td><?php echo htmlspecialchars($row['category_name'] ?: 'Tidak Berkategori'); ?></td>
                            <td class="text-end">Rp <?php echo number_format($row['price'], 0, ',', '.'); ?></td>
                            <td class="text-center fw-bold"><?php echo $row['stock']; ?></td>
                            <td class="text-center">
                                <?php if ($row['is_active']): ?>
                                    <span class="badge bg-success"><i class="bi bi-check-circle-fill"></i> Aktif</span>
                                <?php else: ?>
                                    <span class="badge bg-danger"><i class="bi bi-x-circle-fill"></i> Tidak Aktif</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center text-nowrap">
                                <a href="edit.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-outline-warning btn-edit" title="Edit">
                                    <i class="bi bi-pencil-square"></i>
                                </a>
                                <a href="delete.php?id=<?php echo $row['id']; ?>" 
                                   class="btn btn-sm btn-outline-danger" title="Hapus"
                                   onclick="return confirm('Yakin ingin menghapus produk <?php echo htmlspecialchars($row['name']); ?>? Tindakan ini tidak dapat dilakukan jika produk sudah ada di pesanan.')">
                                    <i class="bi bi-trash"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="alert alert-info text-center mt-4">
                <i class="bi bi-info-circle"></i> Belum ada produk terdaftar. Mulai dengan menambahkan produk baru!
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