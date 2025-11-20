<?php
session_start();
require_once 'config/database.php';

// --- PHP Logic untuk Filter Kategori ---
$filter_category_id = isset($_GET['cat']) ? (int)$_GET['cat'] : null;

$where_clause = "WHERE p.is_active = 1";
if ($filter_category_id) {
    $where_clause .= " AND p.category_id = " . $filter_category_id;
}

// Ambil produk
$query = "SELECT p.id, p.name, p.description, p.price, p.image_path, p.stock,
              c.name AS category_name
           FROM products p
           LEFT JOIN categories c ON p.category_id = c.id
           $where_clause
           ORDER BY p.id DESC";

$products_result = $conn->query($query);
$products = $products_result->fetch_all(MYSQLI_ASSOC);

// Ambil kategori
$categories_result = $conn->query("SELECT id, name FROM categories ORDER BY name ASC");
$categories = $categories_result->fetch_all(MYSQLI_ASSOC);

$conn->close();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Menu Produk Kami</title>
    <link rel="stylesheet" href="assets/css/style.css">
    
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
                <li><a href="logout.php">Logout (<?= htmlspecialchars($_SESSION['username']); ?>)</a></li>
            <?php else: ?>
                <li><a href="login.php">Login</a></li>
            <?php endif; ?>
        </ul>
    </nav>
</header>

<main class="container">
    <h2>Menu Kopi dan Lainnya</h2>

    <div class="category-filter">
        Filter: 
        <a href="products.php" class="<?= !$filter_category_id ? 'active' : '' ?>">Semua</a>
        
        <?php foreach ($categories as $cat): ?>
            <a href="products.php?cat=<?= $cat['id']; ?>"
               class="<?= ($filter_category_id == $cat['id']) ? 'active' : '' ?>">
                <?= htmlspecialchars($cat['name']); ?>
            </a>
        <?php endforeach; ?>
    </div>

    <?php if (count($products) > 0): ?>
        <div class="product-grid">
            <?php foreach ($products as $product): ?>
                <div class="product-card">
                    <img src="<?= htmlspecialchars($product['image_path'] ?: 'assets/images/default.jpg'); ?>" alt="<?= htmlspecialchars($product['name']); ?>">

                    <h4><?= htmlspecialchars($product['name']); ?></h4>

                    <p class="category"><?= htmlspecialchars($product['category_name'] ?: 'Lain-lain'); ?></p>

                    <p class="price">Rp <?= number_format($product['price'], 0, ',', '.'); ?></p>

                    <a href="product_detail.php?id=<?= $product['id']; ?>" class="detail-link">
                        Lihat Detail
                    </a>

                    <form method="POST" action="cart.php">
                        <input type="hidden" name="product_id" value="<?= $product['id']; ?>">
                        <input type="hidden" name="action" value="add">
                        
                        <div class="quantity-control">
                            <label for="qty_<?= $product['id']; ?>" style="font-size: 0.9em; font-weight: bold;">Jml:</label>
                            <input type="number" name="quantity" id="qty_<?= $product['id']; ?>" value="1" min="1" max="<?= $product['stock']; ?>">
                        </div>

                        <button type="submit">Tambah</button>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <p style="text-align:center;margin-top:30px;">
            Tidak ada produk <?= $filter_category_id ? "dalam kategori ini" : "tersedia" ?>.
        </p>
    <?php endif; ?>

</main>

<footer>
    <p>&copy; 2024 Coffee Store. Powered by PHP Native.</p>
</footer>

</body>
</html>