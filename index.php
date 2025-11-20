<?php
// Selalu mulai session di awal file PHP
session_start();

require_once 'config/database.php';
// require_once 'functions/helpers.php'; 

// --- OPTIMASI PATH GAMBAR ---
// Bangun base URL dinamis (Kode Anda sudah baik, kita gunakan hasil akhirnya)
$base_url = rtrim(str_replace('\\','/', dirname($_SERVER['SCRIPT_NAME'])), '/');
if ($base_url === '' || $base_url === '.') {
    $base_url = '';
} else {
    $base_url = $base_url . '/';
}

// Ambil 4 produk terbaru yang aktif untuk ditampilkan sebagai Produk Unggulan
$featured_products = [];
$query = "SELECT id, name, price, image_path, description, stock FROM products WHERE is_active = 1 ORDER BY created_at DESC LIMIT 4";
$result = $conn->query($query);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $featured_products[] = $row;
    }
}
$conn->close(); // Tutup koneksi setelah selesai query
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Coffee Store Kami</title>
    <link rel="stylesheet" href="assets/css/style.css"> 
    
    <style>
        /* Variabel Warna (diambil dari style.css) */
        :root {
            --primary-color: #8B4513;
            --secondary-color: #D2B48C;
            --accent-color: #4A2800;
            --bg-white: #FFFFFF;
            --bg-light: #F7F7F7;
            --text-color: #333;
        }

        /* Gaya Hero Section (Unik untuk Index) */
        .hero {
            background-color: var(--secondary-color); /* Warna Cream */
            padding: 50px 0;
            text-align: center;
            color: var(--accent-color);
            margin-bottom: 30px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .hero h2 {
            font-size: 2.5em;
            color: var(--accent-color);
        }
        .hero p {
            font-size: 1.2em;
            margin-top: 10px;
        }
        
        /* GAYA PRODUK CARD - MENGGUNAKAN CLASS DARI products.php */
        .featured-products {
            text-align: center;
        }
        
        /* Tambahkan gaya khusus untuk tombol di hero section */
        .hero a.button-primary {
            display: inline-block; 
            margin-top: 20px; 
            padding: 10px 25px; 
            background-color: var(--primary-color); 
            color: white; 
            border-radius: 4px; 
            font-weight: bold;
            transition: background-color 0.3s;
        }
        .hero a.button-primary:hover {
            background-color: var(--accent-color);
        }
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
                <?php if (isset($_SESSION['user_id'])): ?>
                    <li><a href="user_orders.php">Pesanan Saya</a></li>
                    <li><a href="logout.php">Logout</a></li>
                <?php else: ?>
                    <li><a href="login.php">Login</a></li>
                <?php endif; ?>
            </ul>
        </nav>
    </header>

    <main>
        <div class="hero">
            <h2>Selamat Datang di Coffee Store</h2>
            <p>Nikmati biji kopi pilihan terbaik dan *pastry* lezat, disiapkan dengan penuh cinta.</p>
            <a href="products.php" class="button-primary">Lihat Semua Menu</a>
        </div>

        <div class="container featured-products">
            <h3>✨ Produk Unggulan Kami</h3>

            <?php if (!empty($featured_products)): ?>
                <div class="product-grid">
                    <?php foreach ($featured_products as $product): ?>
                        <div class="product-card">
                            <?php 
                                // Ambil path dari DB. Asumsikan path disimpan relatif (assets/...)
                                $image_path = $product['image_path'] ?: 'assets/images/default.jpg';
                                $image_src = $base_url . $image_path;
                            ?>
                            
                            <img src="<?php echo htmlspecialchars($image_src); ?>"
                                alt="<?php echo htmlspecialchars($product['name']); ?>">
                            
                            <h4><?php echo htmlspecialchars($product['name']); ?></h4>
                            <p class="price">Rp <?php echo number_format($product['price'], 0, ',', '.'); ?></p>
                            
                            <a href="product_detail.php?id=<?php echo $product['id']; ?>" class="detail-link">Lihat Detail</a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p>Saat ini belum ada produk unggulan untuk ditampilkan.</p>
            <?php endif; ?>
        </div>
    </main>

    <footer>
        <p>&copy; 2024 Coffee Store. Powered by PHP Native.</p>
    </footer>

</body>
</html>