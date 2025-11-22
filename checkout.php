<?php
session_start();
require_once 'config/database.php';
// Asumsikan 'functions/auth_check.php' berisi fungsi requireLogin()
require_once 'functions/auth_check.php';

// --- OPTIMASI PATH GAMBAR & BASE URL (untuk konsistensi navigasi) ---
$base_url = rtrim(str_replace('\\','/', dirname($_SERVER['SCRIPT_NAME'])), '/');
if ($base_url === '' || $base_url === '.') {
    $base_url = '';
} else {
    $base_url = $base_url . '/';
}

// PENTING: User harus login untuk checkout. Kita gunakan requireLogin dengan role 'user'.
// Asumsikan fungsi requireLogin() ada dan berfungsi
requireLogin('user', 'login.php?redirect=checkout.php'); 

$user_id = $_SESSION['user_id'];
$cart = $_SESSION['cart'] ?? [];
$total_amount = 0;
$checkout_success = false;
$error_message = '';
$order_id = 0; // Inisialisasi ID Pesanan

// 1. Cek apakah keranjang kosong
if (empty($cart)) {
    $error_message = "Keranjang belanja Anda kosong. Tidak dapat melanjutkan checkout.";
} 

// 2. Jika ada item di keranjang, hitung total dan proses POST request
if (!$error_message && $_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Kita gunakan Transaction
    if (isset($conn)) {
        $conn->begin_transaction();
        $success = true;

        try {
            // A. Validasi Stok Terakhir Sebelum Checkout
            $validated_cart = [];
            $total_amount = 0;
            
            foreach ($cart as $item) {
                $product_id = $item['product_id'];
                $quantity = $item['quantity'];

                // Cek stok terbaru dari database (menggunakan FOR UPDATE untuk lock baris selama transaksi)
                $stmt_stock = $conn->prepare("SELECT price, stock, name FROM products WHERE id = ? AND is_active = 1 FOR UPDATE");
                $stmt_stock->bind_param("i", $product_id);
                $stmt_stock->execute();
                $result_stock = $stmt_stock->get_result();
                $db_product = $result_stock->fetch_assoc();
                $stmt_stock->close();

                if (!$db_product || $db_product['stock'] < $quantity) {
                    // Gagal stok kurang atau produk tidak aktif
                    throw new Exception("Stok untuk produk **" . htmlspecialchars($item['name']) . "** tidak mencukupi atau produk tidak tersedia lagi. Silakan cek keranjang Anda.");
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
    } else {
        $error_message = "Koneksi database gagal.";
    }
}

// Jika belum POST (atau POST gagal), hitung total untuk tampilan ringkasan
if (!$checkout_success && !$error_message) {
    // Kita harus memastikan total_amount dihitung dari item di session cart (sebelum validasi)
    // jika kita hanya menampilkan ringkasan pra-checkout
    $temp_total = 0;
    foreach ($cart as $item) {
        $temp_total += $item['price'] * $item['quantity'];
    }
    $total_amount = $temp_total;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout Pesanan</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        :root {
            --primary-color: #6F4E37; /* Coklat Kopi */
            --accent-color: #A0522D; /* Sienna */
        }
        .navbar { background-color: var(--primary-color) !important; }
        
        .summary-box { 
            border: 1px solid #ddd; 
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
        }
        .total-final {
            background-color: var(--accent-color);
            color: white;
            border-radius: 0 0 8px 8px;
        }
        .btn-confirm {
            background-color: #198754; /* Green Success */
            border-color: #198754;
        }
        .btn-confirm:hover {
            background-color: #156d41;
            border-color: #156d41;
        }
    </style>
</head>
<body>
    <header>
        <nav class="navbar navbar-expand-lg navbar-dark shadow-sm" style="background-color: var(--primary-color);">
            <div class="container">
                <a class="navbar-brand text-white fw-bold h4 mb-0" href="<?= $base_url; ?>index.php">☕ Coffee Store</a>
                <div class="collapse navbar-collapse" id="mainNav">
                    <ul class="navbar-nav ms-auto">
                        <li class="nav-item"><a class="nav-link" href="<?= $base_url; ?>index.php">Home</a></li>
                        <li class="nav-item"><a class="nav-link" href="<?= $base_url; ?>products.php">Produk</a></li>
                        <li class="nav-item"><a class="nav-link active" aria-current="page" href="<?= $base_url; ?>cart.php">Keranjang</a></li>
                        <li class="nav-item"><a class="nav-link" href="<?= $base_url; ?>user_orders.php">Pesanan Saya</a></li> 
                        <li class="nav-item"><a class="nav-link" href="<?= $base_url; ?>logout.php">Logout (<?php echo htmlspecialchars($_SESSION['username'] ?? ''); ?>)</a></li>
                    </ul>
                </div>
            </div>
        </nav>
    </header>

    <main class="container my-5">
        <h2>Konfirmasi dan Checkout</h2>

        <?php if ($checkout_success): ?>
            <div class="alert alert-success p-4 mt-4" role="alert">
                <h3 class="alert-heading"><i class="bi bi-check-circle-fill"></i> 🎉 Checkout Berhasil!</h3>
                <p>Terima kasih, **<?php echo htmlspecialchars($_SESSION['username'] ?? 'Pelanggan'); ?>**! Pesanan Anda telah diterima.</p>
                <hr>
                <p class="mb-1">Nomor Pesanan Anda: **#<?php echo $order_id; ?>**</p>
                <p class="mb-0 fs-5">Total Pembayaran: **Rp <?php echo number_format($total_amount, 0, ',', '.'); ?>**</p>
                
                <div class="mt-4">
                    <a href="<?= $base_url; ?>user_orders.php" class="btn btn-success me-2">
                        <i class="bi bi-list-task"></i> Lihat Pesanan Saya
                    </a>
                    <a href="<?= $base_url; ?>index.php" class="btn btn-outline-success">
                        <i class="bi bi-house-door"></i> Kembali ke Home
                    </a>
                </div>
            </div>
        <?php else: ?>

            <?php if ($error_message): ?>
                <div class="alert alert-danger mt-4" role="alert">
                    <i class="bi bi-exclamation-triangle-fill"></i> **Gagal Checkout:** <?php echo $error_message; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($cart)): ?>
                <div class="row mt-4">
                    <div class="col-lg-6 mb-4">
                        <h3 class="mb-3"><i class="bi bi-receipt"></i> Ringkasan Pesanan</h3>
                        <div class="summary-box">
                            <ul class="list-group list-group-flush">
                                <?php foreach ($cart as $item): ?>
                                    <li class="list-group-item d-flex justify-content-between">
                                        <span><?php echo htmlspecialchars($item['name']); ?> 
                                              <small class="text-muted">(x<?php echo $item['quantity']; ?>)</small>
                                        </span>
                                        <span class="fw-bold">Rp <?php echo number_format($item['price'] * $item['quantity'], 0, ',', '.'); ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                            <div class="p-3 total-final d-flex justify-content-between">
                                <span class="fw-bold fs-5">TOTAL AKHIR:</span>
                                <span class="fw-bold fs-5">Rp <?php echo number_format($total_amount, 0, ',', '.'); ?></span>
                            </div>
                        </div>
                        <p class="mt-3"><a href="<?= $base_url; ?>cart.php" class="text-decoration-none">← Edit Keranjang</a></p>
                    </div>

                    <div class="col-lg-6 mb-4">
                        <h3 class="mb-3"><i class="bi bi-credit-card"></i> Detail Pembayaran</h3>
                        
                        <div class="card p-4">
                            <h4 class="mb-3">Metode Pembayaran</h4>
                            <p class="alert alert-info">
                                <i class="bi bi-cash-stack"></i> Kami hanya menerima **Pembayaran Tunai Saat Pengambilan (Cash on Delivery / COD)** untuk pesanan ini.
                            </p>

                            <h4 class="mt-4 mb-3">Konfirmasi</h4>
                            <form method="POST" action="<?= $base_url; ?>checkout.php">
                                <p>Dengan mengklik tombol di bawah, Anda mengonfirmasi pesanan ini dan setuju untuk membayar total **Rp <?php echo number_format($total_amount, 0, ',', '.'); ?>** saat Anda mengambil pesanan Anda.</p>
                                
                                <button type="submit" class="btn btn-lg btn-confirm w-100">
                                    <i class="bi bi-bag-check-fill"></i> Konfirmasi Pembelian
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

        <?php endif; ?>
    </main>

    <footer class="text-center py-3 mt-5 border-top">
        <p class="mb-0">&copy; 2024 Coffee Store. Powered by PHP Native.</p>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>