<?php
session_start();
require_once 'config/database.php';

// Pastikan ID produk ada di URL
if (!isset($_GET['id']) || empty($_GET['id'])) {
    // Jika tidak ada ID, arahkan kembali ke daftar produk
    header("Location: products.php");
    exit();
}

$product_id = (int)$_GET['id'];
$product = null;

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
    header("Location: products.php?error=Produk tidak ditemukan.");
    exit();
}

$product = $result->fetch_assoc();
$stmt->close();
$conn->close();

$current_stock = $product['stock'];
$is_available = $current_stock > 0;
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Produk: <?php echo htmlspecialchars($product['name']); ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .product-container {
            display: flex;
            gap: 30px;
            margin-top: 20px;
        }
        .product-image img {
            max-width: 400px;
            height: auto;
            border: 1px solid #ccc;
        }
        .product-info {
            flex: 1;
        }
        .stock-status {
            font-weight: bold;
            margin-top: 10px;
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
                    <li><a href="user_orders.php">Riwayat</a></li>
                    <li><a href="logout.php">Logout</a></li>
                <?php else: ?>
                    <li><a href="login.php">Login</a></li>
                <?php endif; ?>
            </ul>
        </nav>
    </header>

    <main class="container">
        <p><a href="products.php">← Kembali ke Daftar Produk</a></p>
        
        <div class="product-container">
            <div class="product-image">
                <img src="<?php echo htmlspecialchars($product['image_path'] ? $product['image_path'] : 'assets/images/default.jpg'); ?>" 
                     alt="<?php echo htmlspecialchars($product['name']); ?>">
            </div>

            <div class="product-info">
                <h2><?php echo htmlspecialchars($product['name']); ?></h2>
                <p>Kategori: <strong><?php echo htmlspecialchars($product['category_name'] ?: 'Tidak Berkategori'); ?></strong></p>
                
                <h3>Rp <?php echo number_format($product['price'], 0, ',', '.'); ?></h3>
                
                <p><?php echo nl2br(htmlspecialchars($product['description'])); ?></p>
                
                <div class="stock-status" style="color: <?php echo $is_available ? 'green' : 'red'; ?>;">
                    <?php echo $is_available ? '✅ Stok Tersedia' : '❌ Stok Habis'; ?> 
                    (Tersisa: <?php echo $current_stock; ?>)
                </div>

                <?php if ($is_available): ?>
                    <form method="POST" action="cart.php" style="margin-top: 20px;">
                        <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                        <input type="hidden" name="action" value="add">
                        
                        <label for="quantity">Jumlah:</label>
                        <input type="number" name="quantity" id="quantity" value="1" min="1" max="<?php echo $current_stock; ?>" style="width: 60px;" required>
                        
                        <button type="submit" style="padding: 10px 20px; background-color: #8B4513; color: white; border: none; cursor: pointer;">
                            Tambah ke Keranjang
                        </button>
                    </form>
                <?php else: ?>
                    <button disabled style="padding: 10px 20px; background-color: #ccc;">Stok Habis</button>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <footer>
        <p>&copy; 2024 Coffee Store. Powered by PHP Native.</p>
    </footer>
</body>
</html>