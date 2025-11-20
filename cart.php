<?php
session_start();
require_once 'config/database.php';

// Pastikan array keranjang sudah ada di session
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

$message = '';
$total_keranjang = 0;

// --- 1. Logika Pemrosesan Keranjang (POST Request) ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $action = $_POST['action'] ?? '';
    $product_id = (int)($_POST['product_id'] ?? 0);
    
    // a. Aksi Tambah Produk (dari products.php atau product_detail.php)
    if ($action === 'add' && $product_id > 0) {
        $quantity = (int)($_POST['quantity'] ?? 1);
        
        // 1. Ambil data produk dari database (untuk harga dan nama terbaru)
        $stmt = $conn->prepare("SELECT id, name, price, stock, image_path FROM products WHERE id = ? AND is_active = 1");
        $stmt->bind_param("i", $product_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $product = $result->fetch_assoc();
        $stmt->close();

        if ($product) {
            $is_found = false;
            // 2. Cek apakah produk sudah ada di keranjang
            foreach ($_SESSION['cart'] as $key => &$item) {
                if ($item['product_id'] == $product_id) {
                    // Update jumlah (cek stok, pastikan tidak melebihi stok)
                    $new_quantity = $item['quantity'] + $quantity;
                    $item['quantity'] = min($new_quantity, $product['stock']); // Batasi sesuai stok
                    $message = "Jumlah **" . htmlspecialchars($item['name']) . "** diperbarui di keranjang.";
                    $is_found = true;
                    break;
                }
            }
            
            // 3. Jika produk belum ada, tambahkan sebagai item baru
            if (!$is_found && $quantity > 0) {
                $quantity = min($quantity, $product['stock']); // Batasi sesuai stok
                $_SESSION['cart'][] = [
                    'product_id' => $product['id'],
                    'name' => $product['name'],
                    'price' => (float)$product['price'], 
                    'quantity' => $quantity,
                    'image_path' => $product['image_path']
                ];
                $message = "Produk **" . htmlspecialchars($product['name']) . "** berhasil ditambahkan ke keranjang.";
            }
        }
    }
    
    // b. Aksi Ubah Jumlah Produk (dari cart.php)
    elseif ($action === 'update' && $product_id > 0) {
        $new_quantity = (int)($_POST['quantity'] ?? 1);
        
        foreach ($_SESSION['cart'] as $key => &$item) {
            if ($item['product_id'] == $product_id) {
                // Ambil stok (untuk validasi)
                $stock_query = $conn->prepare("SELECT stock FROM products WHERE id = ?");
                $stock_query->bind_param("i", $product_id);
                $stock_query->execute();
                $stock_result = $stock_query->get_result()->fetch_assoc();
                $stock = $stock_result['stock'];
                $stock_query->close();

                if ($new_quantity > 0 && $new_quantity <= $stock) {
                    $item['quantity'] = $new_quantity;
                    $message = "Jumlah produk **" . htmlspecialchars($item['name']) . "** berhasil diubah.";
                } elseif ($new_quantity > $stock) {
                    $message = "Gagal: Stok produk **" . htmlspecialchars($item['name']) . "** hanya tersisa " . $stock . ".";
                    $item['quantity'] = $stock; // Set ke maksimum stok
                }
                break;
            }
        }
    }
    
    // c. Aksi Hapus Produk (dari cart.php)
    elseif ($action === 'remove' && $product_id > 0) {
        foreach ($_SESSION['cart'] as $key => $item) {
            if ($item['product_id'] == $product_id) {
                $name_removed = $item['name'];
                unset($_SESSION['cart'][$key]); 
                $_SESSION['cart'] = array_values($_SESSION['cart']); 
                $message = "Produk **" . htmlspecialchars($name_removed) . "** telah dihapus dari keranjang.";
                break;
            }
        }
    }
    
    // Setelah semua aksi selesai, redirect ke halaman ini sendiri (PRG Pattern)
    header("Location: cart.php?msg=" . urlencode($message));
    exit();
}

// Ambil pesan dari URL setelah redirect
if (isset($_GET['msg'])) {
    $message = htmlspecialchars($_GET['msg']);
}

// --- 2. Perhitungan Total Keranjang (Untuk Tampilan) ---
foreach ($_SESSION['cart'] as $item) {
    $total_keranjang += $item['price'] * $item['quantity'];
}

// Ambil status login untuk navigasi
$is_logged_in = isset($_SESSION['user_id']);
$username = $is_logged_in ? htmlspecialchars($_SESSION['username']) : '';

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Keranjang Belanja</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        /* Gaya Keranjang Lebih Rapi */
        .cart-item { 
            display: flex; 
            align-items: center; 
            border: 1px solid #ddd; /* Tambah border untuk setiap item */
            border-radius: 8px;
            padding: 15px; 
            margin-bottom: 15px;
            background-color: var(--bg-white);
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        .cart-item img { 
            width: 90px; 
            height: 90px; 
            object-fit: cover; 
            margin-right: 20px; 
            border-radius: 6px;
        }
        .cart-details { 
            flex-grow: 1; 
        }
        .cart-details h4 {
            color: var(--accent);
            margin: 0 0 5px 0;
        }
        .cart-actions { 
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .cart-actions form {
            /* Reset gaya form default agar tidak merusak tata letak di dalam cart-actions */
            background: none;
            padding: 0;
            margin: 0;
            box-shadow: none;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .cart-actions form label {
            font-weight: normal;
            margin-bottom: 0;
        }
        .cart-actions form button {
            padding: 6px 12px;
            font-size: 0.9em;
        }
        .cart-actions form button[style*="color: red"] {
            background-color: #f44336;
            border: none !important;
        }
        .cart-actions form button[style*="color: red"]:hover {
            background-color: #d32f2f;
        }
        .cart-total { 
            background-color: var(--secondary-color);
            padding: 20px;
            border-radius: 8px;
            margin-top: 30px;
            text-align: right; 
            font-size: 1.5em; 
            font-weight: bold; 
            color: var(--accent);
        }
        .checkout-button {
            display: inline-block;
            background-color: var(--primary-color); 
            color: white; 
            padding: 15px 30px; 
            text-decoration: none; 
            font-size: 1.1em;
            border-radius: 8px;
            transition: background-color 0.3s;
        }
        .checkout-button:hover {
            background-color: var(--accent);
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
                <?php if ($is_logged_in): ?>
                    <li><a href="user_orders.php">Pesanan Saya</a></li>
                    <li><a href="logout.php">Logout (<?php echo $username; ?>)</a></li>
                <?php else: ?>
                    <li><a href="login.php">Login</a></li>
                <?php endif; ?>
            </ul>
        </nav>
    </header>

    <main class="container">
        <h2>Keranjang Belanja Anda</h2>

        <?php if ($message): ?>
            <p style="color: green; font-weight: bold; background-color: #e6ffe6; padding: 10px; border-radius: 4px;"><?php echo $message; ?></p>
        <?php endif; ?>
        
        <a href="products.php">← Lanjutkan Belanja</a>

        <?php if (count($_SESSION['cart']) > 0): ?>
            <div class="cart-list" style="margin-top: 20px;">
                <?php foreach ($_SESSION['cart'] as $item): ?>
                    <div class="cart-item">
                        <img src="<?php echo htmlspecialchars($item['image_path'] ? $item['image_path'] : 'assets/images/default.jpg'); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>">
                        
                        <div class="cart-details">
                            <h4><?php echo htmlspecialchars($item['name']); ?></h4>
                            <p>Harga Satuan: Rp <?php echo number_format($item['price'], 0, ',', '.'); ?></p>
                            <p>Subtotal: Rp <?php echo number_format($item['price'] * $item['quantity'], 0, ',', '.'); ?></p>
                        </div>

                        <div class="cart-actions">
                            <form method="POST" action="cart.php">
                                <input type="hidden" name="action" value="update">
                                <input type="hidden" name="product_id" value="<?php echo $item['product_id']; ?>">
                                <label for="qty_<?php echo $item['product_id']; ?>">Jml:</label>
                                <input type="number" name="quantity" id="qty_<?php echo $item['product_id']; ?>" 
                                        value="<?php echo $item['quantity']; ?>" min="1" style="width: 50px;" required>
                                <button type="submit">Update</button>
                            </form>
                            
                            <form method="POST" action="cart.php">
                                <input type="hidden" name="action" value="remove">
                                <input type="hidden" name="product_id" value="<?php echo $item['product_id']; ?>">
                                <button type="submit" style="color: white; background-color: #f44336;">Hapus</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="cart-total">
                Total Belanja: Rp <?php echo number_format($total_keranjang, 0, ',', '.'); ?>
            </div>
            
            <div style="margin-top: 30px; text-align: right;">
                <a href="checkout.php" class="checkout-button">Lanjut ke Checkout →</a>
            </div>

        <?php else: ?>
            <p style="margin-top: 20px;">Keranjang Anda kosong. Silakan tambahkan produk!</p>
        <?php endif; ?>

    </main>

    <footer>
        <p>&copy; 2024 Coffee Store. Powered by PHP Native.</p>
    </footer>
</body>
</html>