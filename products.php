<?php
// Selalu mulai session di awal file PHP
session_start();
require_once 'config/database.php';

// --- OPTIMASI PATH GAMBAR (Mengulang logika dari index.php) ---
$base_url = rtrim(str_replace('\\','/', dirname($_SERVER['SCRIPT_NAME'])), '/');
if ($base_url === '' || $base_url === '.') {
    $base_url = '';
} else {
    $base_url = $base_url . '/';
}

// --- PHP Logic untuk Filter Kategori ---
$filter_category_id = isset($_GET['cat']) ? intval($_GET['cat']) : null;

$where_clause = "WHERE p.is_active = 1";
if ($filter_category_id) {
    if (isset($conn)) {
        $where_clause .= " AND p.category_id = " . $filter_category_id;
    }
}

$products = [];
$categories = [];

if (isset($conn)) {
    // Ambil produk
    $query = "SELECT p.id, p.name, p.description, p.price, p.image_path, p.stock,
              c.name AS category_name
              FROM products p
              LEFT JOIN categories c ON p.category_id = c.id
              $where_clause
              ORDER BY p.id DESC";

    $products_result = $conn->query($query);
    if ($products_result) {
        $products = $products_result->fetch_all(MYSQLI_ASSOC);
    }

    // Ambil kategori
    $categories_result = $conn->query("SELECT id, name FROM categories ORDER BY name ASC");
    if ($categories_result) {
        $categories = $categories_result->fetch_all(MYSQLI_ASSOC);
    }
    
    $conn->close();
}

// Menentukan nama kategori yang sedang aktif untuk judul halaman
$current_category_name = "Semua Produk";
if ($filter_category_id) {
    foreach ($categories as $cat) {
        if ($cat['id'] == $filter_category_id) {
            $current_category_name = htmlspecialchars($cat['name']);
            break;
        }
    }
}

// --- LOGIKA PESAN NOTIFIKASI BARU ---
$notification_message = '';
$notification_type = 'success'; // Default type
if (isset($_GET['msg'])) {
    $notification_message = htmlspecialchars(urldecode($_GET['msg']));
    
    // Tentukan tipe notifikasi berdasarkan isi pesan (sederhana)
    if (stripos($notification_message, 'gagal') !== false || stripos($notification_message, 'tidak mencukupi') !== false) {
        $notification_type = 'danger';
    } elseif (stripos($notification_message, 'diperbarui') !== false) {
         $notification_type = 'info';
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Menu Produk Kami | <?= $current_category_name; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        /* Gaya kustom untuk menyesuaikan tampilan Bootstrap agar mirip dengan desain e-commerce kopi */
        :root {
            --primary-color: #6F4E37; /* Coklat Kopi */
            --secondary-color: #A0522D; /* Sienna */
        }
        .navbar {
            background-color: var(--primary-color) !important;
        }
        .btn-primary, .btn-primary:hover {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        /* ... Gaya product-card lainnya (dipertahankan) ... */
        .product-card {
            border: 1px solid #eee;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            transition: transform 0.3s;
        }
        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 12px rgba(0, 0, 0, 0.1);
        }
        .product-card img {
            width: 100%;
            height: 180px; /* Tinggi gambar konsisten */
            object-fit: cover;
        }
        .product-card-body {
            padding: 15px;
            text-align: center;
        }
        .product-card h4 {
            font-size: 1.25rem;
            margin-bottom: 5px;
            height: 50px; 
            overflow: hidden;
        }
        .product-card .price {
            font-size: 1.4rem;
            color: var(--primary-color);
            font-weight: bold;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <header>
        <nav class="navbar navbar-expand-lg navbar-dark shadow-sm">
            <div class="container">
                <a class="navbar-brand text-white fw-bold h4 mb-0" href="<?= $base_url; ?>index.php">☕ Coffee Store</a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="mainNav">
                    <ul class="navbar-nav ms-auto">
                        <li class="nav-item"><a class="nav-link" href="<?= $base_url; ?>index.php">Home</a></li>
                        <li class="nav-item"><a class="nav-link active" aria-current="page" href="<?= $base_url; ?>products.php">Produk</a></li>
                        <li class="nav-item"><a class="nav-link" href="<?= $base_url; ?>cart.php">Keranjang</a></li>

                        <?php if (isset($_SESSION['user_id'])): ?>
                            <li class="nav-item"><a class="nav-link" href="<?= $base_url; ?>user_orders.php">Pesanan Saya</a></li>
                            <li class="nav-item"><a class="nav-link" href="<?= $base_url; ?>logout.php">Logout (<?= htmlspecialchars($_SESSION['username']); ?>)</a></li>
                        <?php else: ?>
                            <li class="nav-item"><a class="nav-link" href="<?= $base_url; ?>login.php">Login</a></li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </nav>
    </header>

    <main class="container my-5">
        
        <?php if ($notification_message): ?>
            <div class="alert alert-<?= $notification_type; ?> alert-dismissible fade show" role="alert">
                <i class="bi bi-info-circle-fill me-2"></i> 
                <?= str_replace(['**'], [''], $notification_message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <h2 class="mb-4">Menu Kopi dan Lainnya - <?= $current_category_name; ?></h2>

        <div class="category-filter mb-4 d-flex flex-wrap align-items-center">
            <span class="me-3 fw-bold">Filter Kategori:</span> 
            <a href="<?= $base_url; ?>products.php" class="btn btn-sm <?= !$filter_category_id ? 'btn-dark active' : 'btn-outline-secondary' ?> rounded-pill me-2 mb-2">Semua</a>
            
            <?php foreach ($categories as $cat): ?>
                <a href="<?= $base_url; ?>products.php?cat=<?= $cat['id']; ?>"
                   class="btn btn-sm rounded-pill me-2 mb-2 
                          <?= ($filter_category_id == $cat['id']) ? 'btn-dark active' : 'btn-outline-secondary' ?>">
                    <?= htmlspecialchars($cat['name']); ?>
                </a>
            <?php endforeach; ?>
        </div>
        
        <hr class="mb-4">

        <?php if (count($products) > 0): ?>
            <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 g-4">
                <?php foreach ($products as $product): ?>
                    <div class="col">
                        <div class="product-card h-100 d-flex flex-column">
                            <?php 
                                $image_src = $base_url . htmlspecialchars($product['image_path'] ?: 'assets/images/default.jpg');
                            ?>
                            <img src="<?= $image_src; ?>" class="card-img-top" alt="<?= htmlspecialchars($product['name']); ?>">

                            <div class="product-card-body d-flex flex-column flex-grow-1">
                                <h4 class="fw-bold"><?= htmlspecialchars($product['name']); ?></h4>
                                <p class="badge bg-secondary text-white"><?= htmlspecialchars($product['category_name'] ?: 'Lain-lain'); ?></p>

                                <p class="price">Rp <?= number_format($product['price'], 0, ',', '.'); ?></p>
                                
                                <div class="mt-auto pt-2">
                                    <a href="<?= $base_url; ?>product_detail.php?id=<?= $product['id']; ?>" class="btn btn-outline-info btn-sm mb-2 w-100">
                                        Lihat Detail
                                    </a>

                                    <form method="POST" action="<?= $base_url; ?>cart.php" class="d-flex align-items-center justify-content-between">
                                        <input type="hidden" name="product_id" value="<?= $product['id']; ?>">
                                        <input type="hidden" name="action" value="add">
                                        
                                        <div class="quantity-control me-2">
                                            <label for="qty_<?= $product['id']; ?>" class="form-label d-block text-start mb-0" style="font-size: 0.8em; font-weight: bold;">Jml:</label>
                                            <input type="number" name="quantity" id="qty_<?= $product['id']; ?>" value="1" min="1" max="<?= (int)$product['stock']; ?>" class="form-control form-control-sm text-center">
                                        </div>

                                        <?php if ((int)$product['stock'] > 0): ?>
                                            <button type="submit" class="btn btn-primary btn-sm flex-grow-1">
                                                <i class="bi bi-cart-plus"></i> Tambah
                                            </button>
                                        <?php else: ?>
                                            <button type="button" class="btn btn-danger btn-sm flex-grow-1" disabled>
                                                Stok Habis
                                            </button>
                                        <?php endif; ?>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="alert alert-warning text-center">
                Tidak ada produk <?= $filter_category_id ? "dalam kategori ini" : "tersedia" ?>.
            </p>
        <?php endif; ?>

    </main>

    <footer class="text-center py-3 mt-5 border-top">
        <p class="mb-0">&copy; 2024 Coffee Store. Powered by PHP Native.</p>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>