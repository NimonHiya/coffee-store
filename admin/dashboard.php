<?php
// 1. PENTING: Selalu mulai session di halaman yang butuh data user
session_start();

// 2. Sertakan koneksi database (jika dibutuhkan) dan fungsi otentikasi
require_once '../config/database.php';
require_once '../functions/auth_check.php';

// 3. Panggil fungsi proteksi! Hanya Admin yang boleh akses
requireLogin('admin', '../login.php');

$admin_name = $_SESSION['username'];

// --- 4. LOGIKA PENGAMBILAN DATA RINGKASAN (WIDGETS) ---
$data_summary = [
    'total_products' => 0,
    'total_orders' => 0,
    'pending_orders' => 0,
    'total_users' => 0
];

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
// -----------------------------------------------------------
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="../assets/css/style.css"> 
    <style>
        /* Gaya Khusus Dashboard */
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        .summary-card {
            background-color: var(--white); /* Asumsi variabel white dari style.css */
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            text-align: left;
        }
        .summary-card h4 {
            margin-top: 0;
            font-size: 1em;
            color: #777;
        }
        .summary-card .value {
            font-size: 2.5em;
            font-weight: bold;
            margin: 5px 0 0;
            color: var(--accent); /* Warna accent dari style.css */
        }
        /* Warna khusus untuk indikator penting */
        .summary-card.pending .value {
            color: #ff9800; /* Warna Orange */
        }
        .summary-card.users .value {
            color: #2196f3; /* Warna Biru */
        }
        /* NAVBAR ADMIN */
header {
    background: var(--primary-color, #8B4513);
    padding: 12px 0;
    box-shadow: 0 2px 8px rgba(0,0,0,0.15);
}

header nav {
    width: 90%;
    max-width: 1250px;
    margin: auto;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

header nav h2 {
    margin: 0;
    color: #fff;
    font-size: 1.6em;
    font-weight: bold;
}

header nav ul {
    list-style: none;
    display: flex;
    align-items: center;
    gap: 18px;
}

header nav ul li {
    color: #fff;
    font-size: 0.95em;
}

/* Link Menu */
header nav ul li a {
    color: #fff;
    text-decoration: none;
    padding: 6px 12px;
    border-radius: 6px;
    transition: 0.3s;
    font-weight: bold;
}

header nav ul li a:hover {
    background: rgba(255,255,255,0.2);
}

/* Welcome text styling */
header nav ul li.welcome {
    font-weight: bold;
    background: rgba(255,255,255,0.15);
    padding: 6px 12px;
    border-radius: 6px;
}

/* Logout Button */
header nav ul li.logout a {
    background: #e74c3c;
    color: white !important;
    padding: 6px 14px;
}

header nav ul li.logout a:hover {
    background: #c0392b;
}

    </style>
</head>
<body>
    <header>
        <nav>
            <h2>Dashboard</h2>
        <ul>
            <li class="welcome">👋 Selamat datang, <?= htmlspecialchars($admin_name); ?></li>
            <li><a href="dashboard.php">Dashboard</a></li>
            <li><a href="products/index.php">Manajemen Produk</a></li>
            <li><a href="categories/index.php">Manajemen Kategori</a></li>
            <li><a href="orders/index.php">Daftar Pesanan</a></li>
            <li class="logout"><a href="../logout.php">Logout</a></li>
        </ul>
        </nav>
    </header>

    <main class="container">
        <h3>Ringkasan Toko Hari Ini</h3>
        
        <div class="dashboard-grid">
            
            <div class="summary-card">
                <h4>Total Produk Terdaftar</h4>
                <p class="value"><?= $data_summary['total_products']; ?></p>
                <a href="products/index.php">Lihat Produk</a>
            </div>
            
            <div class="summary-card pending">
                <h4>Pesanan Menunggu (Pending)</h4>
                <p class="value"><?= $data_summary['pending_orders']; ?></p>
                <a href="orders/index.php?status=pending" style="color: #ff9800;">Lihat Pesanan</a>
            </div>

            <div class="summary-card">
                <h4>Total Pesanan (Semua Status)</h4>
                <p class="value"><?= $data_summary['total_orders']; ?></p>
                <a href="orders/index.php">Lihat Daftar</a>
            </div>

            <div class="summary-card users">
                <h4>Total Pengguna (Pelanggan)</h4>
                <p class="value"><?= $data_summary['total_users']; ?></p>
                <a href="#">Lihat Pengguna</a>
            </div>

        </div>

        <h3 style="margin-top: 40px;">Aksi Cepat</h3>
        <p>Gunakan navigasi di atas untuk mengelola data inti toko Anda.</p>
        
    </main>

    <footer>
        <p>&copy; 2024 Admin Coffee Store.</p>
    </footer>
</body>
</html>

<?php
// Tutup koneksi di akhir halaman
$conn->close();
?>