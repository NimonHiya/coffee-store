<?php
// Selalu mulai session di awal file PHP
session_start();

// Asumsikan file 'config/database.php' berisi kode untuk koneksi ke database ($conn)
require_once 'config/database.php';
// require_once 'functions/helpers.php'; // Biarkan ini dalam komentar jika belum ada

// --- OPTIMASI PATH GAMBAR ---
// Bangun base URL dinamis
$base_url = rtrim(str_replace('\\','/', dirname($_SERVER['SCRIPT_NAME'])), '/');
if ($base_url === '' || $base_url === '.') {
    $base_url = '';
} else {
    // Tambahkan slash di akhir jika $base_url tidak kosong
    $base_url = $base_url . '/';
}

// Ambil 4 produk terbaru yang aktif untuk ditampilkan sebagai Produk Unggulan
$featured_products = [];
// Pastikan koneksi ($conn) berhasil sebelum melakukan query
if (isset($conn)) {
    // Tambahkan kondisi ORDER BY created_at DESC untuk mendapatkan yang terbaru
    $query = "SELECT id, name, price, image_path, description, stock FROM products WHERE is_active = 1 ORDER BY created_at DESC LIMIT 4";
    $result = $conn->query($query);

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $featured_products[] = $row;
        }
    }
    $conn->close(); // Tutup koneksi setelah selesai query
}

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Coffee Store Kami</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css"> 
    <style>
        /* CSS dasar untuk demonstrasi jika style.css belum tersedia */
        :root {
            --primary-color: #6F4E37; /* Coklat Kopi */
        }
        .hero {
            background-color: #F8F8F8;
            padding: 60px 0;
            text-align: center;
            margin-bottom: 30px;
        }
        .product-card {
            border: 1px solid #ddd;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        .product-card img {
            max-width: 100%;
            height: auto;
            border-radius: 4px;
            margin-bottom: 15px;
            max-height: 150px; /* Batasi tinggi gambar */
            object-fit: cover; /* Pastikan gambar memenuhi area */
        }
        .product-card h4 {
            font-size: 1.25rem;
            margin-bottom: 5px;
            height: 50px; /* Jaga tinggi yang konsisten untuk judul */
            overflow: hidden;
        }
        .product-card .price {
            font-size: 1.5rem;
            color: var(--primary-color);
            font-weight: bold;
            margin-top: 10px;
        }
        .product-card .meta {
             font-size: 0.9rem;
             color: #6c757d;
        }
    </style>
</head>
<body>
    <header>
        <nav class="navbar navbar-expand-lg" style="background-color: var(--primary-color);">
            <div class="container">
                <a class="navbar-brand text-white fw-bold h4 mb-0" href="<?php echo $base_url; ?>index.php">☕ Coffee Store</a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon" style="filter: invert(1);"></span>
                </button>
                <div class="collapse navbar-collapse" id="mainNav">
                    <ul class="navbar-nav ms-auto">
                        <li class="nav-item"><a class="nav-link text-white" href="<?php echo $base_url; ?>index.php">Home</a></li>
                        <li class="nav-item"><a class="nav-link text-white" href="<?php echo $base_url; ?>products.php">Produk</a></li>
                        <li class="nav-item"><a class="nav-link text-white" href="<?php echo $base_url; ?>cart.php">Keranjang</a></li>
                        <?php if (isset($_SESSION['user_id'])): ?>
                            <li class="nav-item"><a class="nav-link text-white" href="<?php echo $base_url; ?>user_orders.php">Pesanan Saya</a></li>
                            <li class="nav-item"><a class="nav-link text-white" href="<?php echo $base_url; ?>logout.php">Logout</a></li>
                        <?php else: ?>
                            <li class="nav-item"><a class="nav-link text-white" href="<?php echo $base_url; ?>login.php">Login</a></li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </nav>
    </header>

    <main>
        <div class="hero">
            <div class="container">
                <h2>Selamat Datang di Coffee Store</h2>
                <p>Nikmati biji kopi pilihan terbaik dan pastry lezat, disiapkan dengan penuh cinta.</p>
                <a href="<?php echo $base_url; ?>products.php" class="btn btn-primary" style="background-color: var(--primary-color); border-color: var(--primary-color);">Lihat Semua Menu</a>
            </div>
        </div>

        <div class="container featured-products">
            <h3 class="mb-4">✨ Produk Unggulan Kami</h3>

            <?php if (!empty($featured_products)): ?>
                <div class="row">
                    <?php foreach ($featured_products as $product): ?>
                        <div class="col-sm-6 col-md-3 mb-4">
                            <div class="product-card h-100">
                                <?php 
                                    // Path gambar di-handle dengan baik di awal file
                                    $image_path = !empty($product['image_path']) ? $product['image_path'] : 'assets/images/placeholder.jpg';
                                    // Gabungkan base URL dengan image path
                                    $image_src = $base_url . $image_path;
                                ?>
                                <a href="<?php echo $base_url; ?>product_detail.php?id=<?php echo (int)$product['id']; ?>" class="text-decoration-none text-dark">
                                    <img src="<?php echo htmlspecialchars($image_src); ?>"
                                        alt="<?php echo htmlspecialchars($product['name']); ?>" 
                                        class="img-fluid">
                                    
                                    <h4 class="fw-bold"><?php echo htmlspecialchars($product['name']); ?></h4>
                                </a>
                                <p class="meta">Stok: <?php echo (int)$product['stock']; ?></p>
                                <p class="price">Rp <?php echo number_format($product['price'], 0, ',', '.'); ?></p>
                                
                                <a href="<?php echo $base_url; ?>add_to_cart.php?id=<?php echo (int)$product['id']; ?>" class="btn btn-sm btn-success w-100 mt-2">
                                    <i class="bi bi-cart-plus"></i> Tambah ke Keranjang
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="alert alert-info">Saat ini belum ada produk unggulan untuk ditampilkan.</p>
            <?php endif; ?>
        </div>
    </main>

    <footer class="text-center py-3 mt-5" style="background-color: #333; color: white;">
        <p class="mb-0">&copy; 2024 Coffee Store. Powered by PHP Native.</p>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>