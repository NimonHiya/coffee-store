<?php
// 1. PENTING: Selalu mulai session di halaman yang butuh data user
session_start();

// 2. Sertakan koneksi database (jika dibutuhkan) dan fungsi otentikasi
require_once '../config/database.php';
require_once '../functions/auth_check.php';

// --- OPTIMASI PATH & BASE URL ---
$base_url = rtrim(str_replace('\\','/', dirname($_SERVER['SCRIPT_NAME'])), '/');
$base_url = rtrim(dirname($base_url), '/'); // Naik satu level dari /admin/dashboard.php ke /
if ($base_url === '' || $base_url === '.') {
    $base_url = '/';
} else {
    $base_url = $base_url . '/';
}

// 3. Panggil fungsi proteksi! Hanya Admin yang boleh akses
requireLogin('admin', "{$base_url}login.php");

$admin_name = $_SESSION['username'];

// --- 4. LOGIKA PENGAMBILAN DATA RINGKASAN (WIDGETS) ---
$data_summary = [
    'total_products' => 0,
    'total_orders' => 0,
    'pending_orders' => 0,
    'total_users' => 0
];

if (isset($conn)) {
    $queries = [
        'total_products' => "SELECT COUNT(id) FROM products",
        'total_orders' => "SELECT COUNT(id) FROM orders",
        'pending_orders' => "SELECT COUNT(id) FROM orders WHERE order_status = 'pending'",
        'total_users' => "SELECT COUNT(id) FROM users WHERE role = 'user'"
    ];

    foreach ($queries as $key => $sql) {
        $result = $conn->query($sql);
        if ($result) {
            $row = $result->fetch_row();
            $data_summary[$key] = $row[0];
        }
    }
}
// -----------------------------------------------------------
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../assets/css/style.css"> 
    <style>
        :root {
            --primary-color: #6F4E37; /* Coklat Kopi */
        }
        /* Navbar Admin */
        .admin-navbar {
            background-color: var(--primary-color) !important;
        }

        /* Summary Card Styling (Menyerupai Info Box AdminLTE) */
        .summary-card {
            color: white; /* Text default putih */
            border-radius: 8px;
            overflow: hidden;
            position: relative;
            min-height: 120px;
            padding: 15px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.15);
        }
        .summary-card .icon {
            position: absolute;
            top: 15px;
            right: 15px;
            font-size: 3.5rem;
            opacity: 0.3;
        }
        .summary-card h4 {
            font-size: 1.1rem;
            opacity: 0.9;
        }
        .summary-card .value {
            font-size: 2.8rem;
            font-weight: bold;
            line-height: 1;
        }
        .summary-card a {
            color: white;
            text-decoration: underline;
            font-weight: bold;
            display: block;
            margin-top: 10px;
        }
        .summary-card a:hover {
            color: #ddd;
        }

        /* Custom Colors (AdminLTE inspired) */
        .bg-info-custom { background-color: #17a2b8; } /* Biru Muda */
        .bg-success-custom { background-color: #28a745; } /* Hijau */
        .bg-warning-custom { background-color: #ffc107; color: #333 !important; } /* Kuning */
        .bg-danger-custom { background-color: #dc3545; } /* Merah */
        
        /* Aksi Cepat Card */
        .quick-actions-card {
            border: 1px solid #ddd;
            border-radius: 8px;
        }
    </style>
</head>
<body>
    <header>
        <nav class="navbar navbar-expand-lg navbar-dark admin-navbar shadow">
            <div class="container-fluid">
                <a class="navbar-brand fw-bold" href="#">☕ Admin Panel</a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#adminNav" aria-controls="adminNav" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="adminNav">
                    <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                        <li class="nav-item"><a class="nav-link active" href="dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a></li>
                        <li class="nav-item"><a class="nav-link" href="products/index.php"><i class="bi bi-box"></i> Produk</a></li>
                        <li class="nav-item"><a class="nav-link" href="categories/index.php"><i class="bi bi-tags"></i> Kategori</a></li>
                        <li class="nav-item"><a class="nav-link" href="orders/index.php"><i class="bi bi-cart-check"></i> Pesanan</a></li>
                    </ul>
                    
                    <ul class="navbar-nav ms-auto">
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="bi bi-person-circle"></i> <?= htmlspecialchars($admin_name); ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                                <li><a class="dropdown-item" href="profile.php"><i class="bi bi-gear"></i> Pengaturan Akun</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item text-danger" href="<?= $base_url; ?>logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a></li>
                            </ul>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>
    </header>

    <main class="container my-5">
        <h3 class="mb-4">Ringkasan Toko Hari Ini</h3>
        
        <div class="row g-4">
            
            <div class="col-lg-3 col-md-6">
                <div class="summary-card bg-info-custom">
                    <div class="icon"><i class="bi bi-cart-plus"></i></div>
                    <p class="value"><?= $data_summary['pending_orders']; ?></p>
                    <h4>Pesanan Menunggu (Pending)</h4>
                    <a href="orders/index.php?status=pending">More info <i class="bi bi-arrow-right-circle"></i></a>
                </div>
            </div>

            <div class="col-lg-3 col-md-6">
                <div class="summary-card bg-success-custom">
                    <div class="icon"><i class="bi bi-boxes"></i></div>
                    <p class="value"><?= $data_summary['total_products']; ?></p>
                    <h4>Total Produk Terdaftar</h4>
                    <a href="products/index.php">More info <i class="bi bi-arrow-right-circle"></i></a>
                </div>
            </div>

            <div class="col-lg-3 col-md-6">
                <div class="summary-card bg-warning-custom">
                    <div class="icon"><i class="bi bi-person-check"></i></div>
                    <p class="value"><?= $data_summary['total_users']; ?></p>
                    <h4>Total Pengguna (Pelanggan)</h4>
                    <a href="#">More info <i class="bi bi-arrow-right-circle"></i></a>
                </div>
            </div>

            <div class="col-lg-3 col-md-6">
                <div class="summary-card bg-danger-custom">
                    <div class="icon"><i class="bi bi-receipt"></i></div>
                    <p class="value"><?= $data_summary['total_orders']; ?></p>
                    <h4>Total Pesanan (Semua Status)</h4>
                    <a href="orders/index.php">More info <i class="bi bi-arrow-right-circle"></i></a>
                </div>
            </div>

        </div> 
        
        <h3 class="mt-5 mb-4">Analisis Penjualan</h3>
        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card shadow-sm p-4" style="min-height: 400px;">
                    <h5 class="card-title fw-bold">Grafik Penjualan Bulanan</h5>
                    <p class="text-muted">Placeholder untuk Chart.js/Flot Chart</p>
                                    </div>
            </div>
            <div class="col-lg-4">
                <div class="card shadow-sm p-4" style="min-height: 400px;">
                    <h5 class="card-title fw-bold">Distribusi Geografis</h5>
                    <p class="text-muted">Placeholder untuk peta dunia interaktif</p>
                                     </div>
            </div>
        </div>
        
    </main>

    <footer class="text-center py-3 mt-5 border-top">
        <p class="mb-0">&copy; 2024 Admin Coffee Store. Powered by Bootstrap 5.</p>
    </footer>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php
// Tutup koneksi di akhir halaman
if (isset($conn)) {
    $conn->close();
}
?>