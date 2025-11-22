<?php
session_start();
require_once 'config/database.php';

// --- OPTIMASI PATH GAMBAR & BASE URL (untuk konsistensi navigasi) ---
$base_url = rtrim(str_replace('\\','/', dirname($_SERVER['SCRIPT_NAME'])), '/');
if ($base_url === '' || $base_url === '.') {
    $base_url = '';
} else {
    $base_url = $base_url . '/';
}

// Pastikan ID produk ada di URL
if (!isset($_GET['id']) || empty($_GET['id'])) {
    // Jika tidak ada ID, arahkan kembali ke daftar produk
    header("Location: {$base_url}products.php");
    exit();
}

$product_id = (int)$_GET['id'];
$product = null;

if (isset($conn)) {
    // 1. Ambil data produk berdasarkan ID
    $stmt = $conn->prepare("SELECT 
                p.id, 
                p.name, 
                p.description, 
                p.price, 
                p.stock,
                p.image_path,
                c.name AS category_name
              FROM products p
              LEFT JOIN categories c ON p.category_id = c.id
              WHERE p.id = ? AND p.is_active = 1");
              
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        // Jika produk tidak ditemukan atau tidak aktif
        header("Location: {$base_url}products.php?error=" . urlencode("Produk tidak ditemukan."));
        exit();
    }

    $product = $result->fetch_assoc();
    $stmt->close();
    $conn->close();
} else {
     header("Location: {$base_url}products.php?error=" . urlencode("Koneksi database gagal."));
     exit();
}


$current_stock = (int)$product['stock'];
$is_available = $current_stock > 0;
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Produk: <?php echo htmlspecialchars($product['name']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        :root {
            --primary-color: #6F4E37; /* Coklat Kopi */
        }
        .navbar { background-color: var(--primary-color) !important; }
        .product-image img {
            width: 100%;
            max-height: 450px; /* Batasi ketinggian */
            object-fit: cover;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .product-price {
            font-size: 2.5rem;
            color: var(--primary-color);
            font-weight: bold;
            margin: 15px 0;
        }
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
                        <?php if (isset($_SESSION['user_id'])): ?>
                            <li class="nav-item"><a class="nav-link" href="<?= $base_url; ?>user_orders.php">Riwayat</a></li>
                            <li class="nav-item"><a class="nav-link" href="<?= $base_url; ?>logout.php">Logout (<?php echo htmlspecialchars($_SESSION['username'] ?? ''); ?>)</a></li>
                        <?php else: ?>
                            <li class="nav-item"><a class="nav-link" href="<?= $base_url; ?>login.php">Login</a></li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </nav>
    </header>

    <main class="container my-5">
        <a href="<?= $base_url; ?>products.php" class="btn btn-sm btn-outline-secondary mb-4">
            <i class="bi bi-arrow-left"></i> Kembali ke Daftar Produk
        </a>

        <div class="row product-container g-4">
            
            <div class="col-md-5">
                <div class="product-image">
                    <?php
                        $image_src = $base_url . htmlspecialchars($product['image_path'] ? $product['image_path'] : 'assets/images/default.jpg');
                    ?>
                    <img src="<?php echo $image_src; ?>" 
                         alt="<?php echo htmlspecialchars($product['name']); ?>" 
                         class="img-fluid">
                </div>
            </div>

            <div class="col-md-7">
                <div class="product-info">
                    <h1 class="fw-bold mb-3"><?php echo htmlspecialchars($product['name']); ?></h1>
                    
                    <span class="badge bg-secondary mb-3">
                        <i class="bi bi-tag-fill"></i> <?php echo htmlspecialchars($product['category_name'] ?: 'Tidak Berkategori'); ?>
                    </span>
                    
                    <p class="product-price">
                        Rp <?php echo number_format($product['price'], 0, ',', '.'); ?>
                    </p>
                    
                    <h4 class="mt-4 mb-2">Deskripsi</h4>
                    <p class="text-muted"><?php echo nl2br(htmlspecialchars($product['description'])); ?></p>
                    
                    <hr class="my-4">

                    <div class="stock-status mb-4">
                        <?php if ($is_available): ?>
                            <span class="badge bg-success fs-6">
                                <i class="bi bi-check-circle-fill"></i> Stok Tersedia
                            </span> 
                            <small class="text-muted ms-2">(Tersisa: <?php echo $current_stock; ?>)</small>
                        <?php else: ?>
                            <span class="badge bg-danger fs-6">
                                <i class="bi bi-x-circle-fill"></i> Stok Habis
                            </span>
                        <?php endif; ?>
                    </div>

                    <?php if ($is_available): ?>
                        <form method="POST" action="<?= $base_url; ?>cart.php" class="d-flex align-items-center">
                            <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                            <input type="hidden" name="action" value="add">
                            
                            <div class="me-3">
                                <label for="quantity" class="form-label mb-1 fw-bold">Jumlah:</label>
                                <input type="number" name="quantity" id="quantity" value="1" min="1" max="<?php echo $current_stock; ?>" 
                                       class="form-control text-center" style="width: 80px;" required>
                            </div>
                            
                            <button type="submit" class="btn btn-primary btn-lg mt-3" style="background-color: var(--primary-color); border-color: var(--primary-color);">
                                <i class="bi bi-cart-plus-fill"></i> Tambah ke Keranjang
                            </button>
                        </form>
                    <?php else: ?>
                        <button disabled class="btn btn-danger btn-lg mt-3">
                            <i class="bi bi-slash-circle"></i> Stok Habis
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <footer class="text-center py-3 mt-5 border-top">
        <p class="mb-0">&copy; 2024 Coffee Store. Powered by PHP Native.</p>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>