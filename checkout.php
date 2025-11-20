<?php
session_start();
require_once 'config/database.php';
require_once 'functions/auth_check.php';

// PENTING: User harus login untuk checkout. Kita gunakan requireLogin dengan role 'user'.
requireLogin('user', 'login.php?redirect=checkout.php'); 

$user_id = $_SESSION['user_id'];
$cart = $_SESSION['cart'] ?? [];
$total_amount = 0;
$checkout_success = false;
$error_message = '';

// 1. Cek apakah keranjang kosong
if (empty($cart)) {
    $error_message = "Keranjang belanja Anda kosong. Tidak dapat melanjutkan checkout.";
    // Kita tetap biarkan user melihat pesan error, tapi proses tidak dilanjutkan
} 

// 2. Jika ada item di keranjang, hitung total dan proses POST request
if (!$error_message && $_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Kita gunakan Transaction untuk memastikan semua query (INSERT dan UPDATE)
    // berhasil atau tidak sama sekali (ACID principle).
    $conn->begin_transaction();
    $success = true;

    try {
        // A. Validasi Stok Terakhir Sebelum Checkout
        $validated_cart = [];
        $total_amount = 0;
        
        foreach ($cart as $item) {
            $product_id = $item['product_id'];
            $quantity = $item['quantity'];

            // Cek stok terbaru dari database
            $stmt_stock = $conn->prepare("SELECT price, stock, name FROM products WHERE id = ? AND is_active = 1");
            $stmt_stock->bind_param("i", $product_id);
            $stmt_stock->execute();
            $result_stock = $stmt_stock->get_result();
            $db_product = $result_stock->fetch_assoc();
            $stmt_stock->close();

            if (!$db_product || $db_product['stock'] < $quantity) {
                // Gagal stok kurang atau produk tidak aktif
                throw new Exception("Stok untuk produk **" . htmlspecialchars($item['name']) . "** tidak mencukupi atau produk tidak tersedia lagi.");
            }
            
            // Simpan data final yang divalidasi
            $validated_cart[] = [
                'product_id' => $product_id,
                'name' => $db_product['name'],
                'price' => (float)$db_product['price'],
                'quantity' => $quantity
            ];
            $total_amount += (float)$db_product['price'] * $quantity;
        }

        // B. Simpan Data Pesanan ke Tabel 'orders'
        // Status default 'pending'
        $order_status = 'pending';
        $stmt_order = $conn->prepare("INSERT INTO orders (user_id, total_amount, order_status) VALUES (?, ?, ?)");
        $stmt_order->bind_param("ids", $user_id, $total_amount, $order_status);
        $stmt_order->execute();
        $order_id = $conn->insert_id; // Ambil ID pesanan yang baru dibuat
        $stmt_order->close();

        // C. Simpan Detail Pesanan ke Tabel 'order_items' dan Kurangi Stok
        $stmt_item = $conn->prepare("INSERT INTO order_items (order_id, product_id, quantity, price_at_order) VALUES (?, ?, ?, ?)");
        $stmt_stock_update = $conn->prepare("UPDATE products SET stock = stock - ? WHERE id = ?");

        foreach ($validated_cart as $item) {
            // 1. Insert ke order_items
            $price_at_order = $item['price'];
            $stmt_item->bind_param("iidi", $order_id, $item['product_id'], $item['quantity'], $price_at_order);
            $stmt_item->execute();

            // 2. Kurangi Stok
            $stmt_stock_update->bind_param("ii", $item['quantity'], $item['product_id']);
            $stmt_stock_update->execute();
        }
        $stmt_item->close();
        $stmt_stock_update->close();

        // Jika semua berhasil: Commit Transaction dan bersihkan keranjang
        $conn->commit();
        $_SESSION['cart'] = []; // Kosongkan keranjang
        $checkout_success = true;

    } catch (Exception $e) {
        // Jika ada kesalahan (termasuk stok), Rollback Transaction
        $conn->rollback();
        $error_message = $e->getMessage();
        $success = false;
    }
}

// Jika belum POST, hitung total untuk tampilan
if (!$checkout_success && !$error_message) {
     foreach ($cart as $item) {
        $total_amount += $item['price'] * $item['quantity'];
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Checkout Pesanan</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .summary-box { border: 1px solid #ccc; padding: 20px; margin-top: 20px; }
        .summary-row { display: flex; justify-content: space-between; margin-bottom: 5px; }
    </style>
</head>
<body>
    <header>...</header>

    <main class="container">
        <h2>Konfirmasi dan Checkout</h2>

        <?php if ($checkout_success): ?>
            <div style="background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; padding: 15px; margin-top: 20px;">
                <h3>🎉 Checkout Berhasil!</h3>
                <p>Terima kasih atas pesanan Anda. Nomor Pesanan Anda: **#<?php echo $order_id; ?>**</p>
                <p>Total Pembayaran: **Rp <?php echo number_format($total_amount, 0, ',', '.'); ?>**</p>
                <p>Kami akan memproses pesanan Anda. Silakan cek detail pesanan Anda nanti (fitur ini akan kita buat).</p>
                <a href="index.php">Kembali ke Home</a>
            </div>
        <?php else: ?>

            <?php if ($error_message): ?>
                <p style="color: red; background-color: #f8d7da; border: 1px solid #f5c6cb; padding: 10px;"><?php echo $error_message; ?></p>
            <?php endif; ?>

            <?php if (!empty($cart)): ?>
                <h3>Ringkasan Pesanan</h3>
                <div class="summary-box">
                    <?php foreach ($cart as $item): ?>
                        <div class="summary-row">
                            <span><?php echo htmlspecialchars($item['name']); ?> (x<?php echo $item['quantity']; ?>)</span>
                            <span>Rp <?php echo number_format($item['price'] * $item['quantity'], 0, ',', '.'); ?></span>
                        </div>
                    <?php endforeach; ?>
                    <hr>
                    <div class="summary-row" style="font-weight: bold; font-size: 1.1em;">
                        <span>TOTAL AKHIR:</span>
                        <span>Rp <?php echo number_format($total_amount, 0, ',', '.'); ?></span>
                    </div>
                </div>

                <h4 style="margin-top: 30px;">Metode Pembayaran</h4>
                <p>Kami hanya menerima pembayaran tunai saat pengambilan (Cash on Delivery / COD) untuk saat ini.</p>
                
                <form method="POST" action="checkout.php" style="margin-top: 20px;">
                    <button type="submit" style="background-color: green; color: white; padding: 15px 30px; border: none; font-size: 1.2em; cursor: pointer;">
                        Konfirmasi Pembelian (Bayar Saat Ambil)
                    </button>
                </form>
                
                <p style="margin-top: 15px;"><a href="cart.php">← Kembali ke Keranjang</a></p>
            <?php endif; ?>

        <?php endif; ?>
    </main>

    <footer>...</footer>
</body>
</html>

<?php
$conn->close();
?>