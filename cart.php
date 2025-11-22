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
        
        if (isset($conn)) {
            $stmt = $conn->prepare("SELECT id, name, price, stock, image_path FROM products WHERE id = ? AND is_active = 1");
            $stmt->bind_param("i", $product_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $product = $result->fetch_assoc();
            $stmt->close();
        } else {
             $product = false;
        }

        if ($product) {
            $is_found = false;
            foreach ($_SESSION['cart'] as $key => &$item) {
                if ($item['product_id'] == $product_id) {
                    $new_quantity = $item['quantity'] + $quantity;
                    $item['quantity'] = min($new_quantity, $product['stock']); 
                    
                    if ($new_quantity > $product['stock']) {
                         $message = "Gagal menambahkan: Stok produk **" . htmlspecialchars($item['name']) . "** hanya tersisa " . $product['stock'] . ". Jumlah Anda disesuaikan.";
                    } else {
                         $message = "Jumlah **" . htmlspecialchars($item['name']) . "** diperbarui di keranjang.";
                    }
                    $is_found = true;
                    break;
                }
            }
            
            if (!$is_found && $quantity > 0) {
                $quantity = min($quantity, $product['stock']);
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
                if (isset($conn)) {
                    $stock_query = $conn->prepare("SELECT stock FROM products WHERE id = ?");
                    $stock_query->bind_param("i", $product_id);
                    $stock_query->execute();
                    $stock_result = $stock_query->get_result()->fetch_assoc();
                    $stock = $stock_result['stock'];
                    $stock_query->close();
                } else {
                    $stock = 999; 
                }

                if ($new_quantity > 0 && $new_quantity <= $stock) {
                    $item['quantity'] = $new_quantity;
                    $message = "Jumlah produk **" . htmlspecialchars($item['name']) . "** berhasil diubah.";
                } elseif ($new_quantity > $stock) {
                    // Kuantitas disesuaikan ke stok maksimal dan pesan peringatan muncul
                    $message = "Gagal: Stok produk **" . htmlspecialchars($item['name']) . "** hanya tersisa " . $stock . ". Jumlah disesuaikan.";
                    $item['quantity'] = $stock; // Set ke maksimum stok
                } elseif ($new_quantity <= 0) {
                    $action = 'remove';
                }
                break;
            }
        }
    }
    
    // c. Aksi Hapus Produk (dari cart.php atau jika kuantitas di set <= 0)
    if ($action === 'remove' && $product_id > 0) {
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
    if (isset($conn)) {
        $conn->close();
    }
    header("Location: cart.php?msg=" . urlencode($message));
    exit();
}

// Ambil pesan dari URL setelah redirect
$alert_type = 'success'; // Default ke success

if (isset($_GET['msg'])) {
    $message = htmlspecialchars(urldecode($_GET['msg']));
    
    // --- LOGIKA PENENTUAN ALERT TYPE ---
    if (stripos($message, 'gagal') !== false || stripos($message, 'tidak mencukupi') !== false || stripos($message, 'disesuaikan') !== false) {
        $alert_type = 'warning'; 
    } elseif (stripos($message, 'dihapus') !== false) {
        $alert_type = 'danger';
    }
}

// --- 2. Perhitungan Total Keranjang (Untuk Tampilan) ---
// Kita perlu mengambil stok terbaru dari DB untuk setiap item agar batasan MAX di input HTML akurat.
// Ini perlu dilakukan agar input MAX selalu benar, meskipun item sudah ada di session.
$updated_cart_for_display = [];
$total_keranjang = 0;

if (!empty($_SESSION['cart']) && isset($conn)) {
    // Ambil semua ID produk dalam keranjang
    $product_ids = array_column($_SESSION['cart'], 'product_id');
    $placeholders = implode(',', array_fill(0, count($product_ids), '?'));
    
    // Prepare statement secara dinamis
    $types = str_repeat('i', count($product_ids));
    $stmt_stock = $conn->prepare("SELECT id, stock FROM products WHERE id IN ($placeholders)");
    
    // Bind parameter array
    $stmt_stock->bind_param($types, ...$product_ids);
    $stmt_stock->execute();
    $stock_result = $stmt_stock->get_result();
    $db_stocks = $stock_result->fetch_all(MYSQLI_ASSOC);
    $stmt_stock->close();

    // Ubah array stok menjadi associative array berdasarkan ID untuk pencarian cepat
    $stock_map = array_column($db_stocks, 'stock', 'id');

    // Update keranjang untuk display (terutama max stock)
    foreach ($_SESSION['cart'] as $item) {
        $current_stock = $stock_map[$item['product_id']] ?? 0;
        
        // Sesuaikan kuantitas di session jika tiba-tiba stok berkurang drastis
        if ($item['quantity'] > $current_stock) {
            $item['quantity'] = $current_stock;
        }

        $item['max_stock'] = $current_stock;
        $updated_cart_for_display[] = $item;
        $total_keranjang += $item['price'] * $item['quantity'];
    }
    $_SESSION['cart'] = $updated_cart_for_display; // Simpan kembali jika ada penyesuaian
}


// Ambil status login untuk navigasi
$is_logged_in = isset($_SESSION['user_id']);
$username = $is_logged_in ? htmlspecialchars($_SESSION['username']) : '';

// Tutup koneksi yang dibuka di atas jika belum tertutup (seharusnya sudah tertutup di POST, tapi sebagai fallback)
if (isset($conn)) {
    // Di sini seharusnya sudah tidak ada koneksi jika POST berhasil
    // Jika tidak ada POST, koneksi dibuka di atas untuk cek stok, tutup sekarang.
    // Jika ada POST, koneksi sudah ditutup di POST.
    // Mari kita asumsikan jika koneksi masih terbuka, kita tutup. (Jika Anda menggunakan objek $conn yang sama dari require_once)
    // $conn->close(); // Biarkan ini tergantung pada implementasi database.php Anda, namun aman untuk tidak menutup jika koneksi hanya dibuka sekali per request.
}

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Keranjang Belanja</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        /* Gaya Kustom untuk Keranjang */
        :root {
            --primary-color: #6F4E37; /* Coklat Kopi */
            --secondary-color: #F7F3F0; /* Creamy White */
            --accent: #A0522D; /* Sienna/Darker Brown */
        }
        .navbar { background-color: var(--primary-color) !important; }
        .cart-item { border: none; border-bottom: 1px solid #eee; padding: 20px 0; }
        .cart-item img { 
            width: 80px; 
            height: 80px; 
            object-fit: cover; 
            border-radius: 6px;
        }
        .cart-details h5 { color: var(--accent); margin-bottom: 5px; }
        .cart-total { 
            background-color: var(--secondary-color);
            border: 1px solid #ddd;
            font-size: 1.4em; 
        }
        .checkout-button {
            background-color: var(--primary-color); 
            border-color: var(--primary-color);
        }
        .checkout-button:hover {
            background-color: var(--accent);
            border-color: var(--accent);
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
                        <li class="nav-item"><a class="nav-link active" aria-current="page" href="<?= $base_url; ?>cart.php">Keranjang</a></li>
                        <?php if ($is_logged_in): ?>
                            <li class="nav-item"><a class="nav-link" href="<?= $base_url; ?>user_orders.php">Pesanan Saya</a></li>
                            <li class="nav-item"><a class="nav-link" href="<?= $base_url; ?>logout.php">Logout (<?php echo $username; ?>)</a></li>
                        <?php else: ?>
                            <li class="nav-item"><a class="nav-link" href="<?= $base_url; ?>login.php">Login</a></li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </nav>
    </header>
    
    <main class="container my-5">
        <h2 class="mb-4">🛒 Keranjang Belanja Anda</h2>

        <?php if ($message): ?>
            <div class="alert alert-<?= $alert_type; ?> alert-dismissible fade show" role="alert">
                <?php 
                    if ($alert_type == 'warning') { echo '<i class="bi bi-exclamation-triangle-fill me-2"></i>'; }
                    elseif ($alert_type == 'danger') { echo '<i class="bi bi-x-circle-fill me-2"></i>'; }
                    else { echo '<i class="bi bi-check-circle-fill me-2"></i>'; }
                    
                    // Gantikan '**' dengan <strong>
                    echo str_replace(['**'], ['<strong>', '</strong>'], $message); 
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <a href="<?= $base_url; ?>products.php" class="btn btn-outline-secondary mb-4"><i class="bi bi-arrow-left"></i> Lanjutkan Belanja</a>

        <?php if (count($_SESSION['cart']) > 0): ?>
            <div class="card shadow-sm mb-4">
                <div class="card-body p-0">
                    <div class="list-group list-group-flush cart-list">
                        <?php foreach ($_SESSION['cart'] as $item): 
                             // Pastikan max_stock ada
                             $max_stock = $item['max_stock'] ?? 1;
                        ?>
                            <div class="list-group-item cart-item">
                                <div class="row align-items-center g-3">
                                    
                                    <div class="col-6 col-md-5 d-flex align-items-center">
                                        <img src="<?php echo $base_url . htmlspecialchars($item['image_path'] ? $item['image_path'] : 'assets/images/default.jpg'); ?>" 
                                             alt="<?php echo htmlspecialchars($item['name']); ?>" 
                                             class="img-fluid me-3">
                                        <div class="cart-details">
                                            <h5 class="fw-bold mb-1"><?php echo htmlspecialchars($item['name']); ?></h5>
                                            <small class="text-muted">Harga Satuan: Rp <?php echo number_format($item['price'], 0, ',', '.'); ?></small>
                                        </div>
                                    </div>

                                    <div class="col-3 col-md-3 text-center">
                                        <form method="POST" action="<?= $base_url; ?>cart.php" id="form-update-<?= $item['product_id']; ?>" class="d-flex align-items-center justify-content-center">
                                            <input type="hidden" name="action" value="update">
                                            <input type="hidden" name="product_id" value="<?php echo $item['product_id']; ?>">
                                            
                                            <input type="number" name="quantity" 
                                                    value="<?php echo $item['quantity']; ?>" min="1" 
                                                    max="<?= $max_stock; ?>"
                                                    onchange="document.getElementById('form-update-<?= $item['product_id']; ?>').submit();"
                                                    class="form-control form-control-sm text-center" style="width: 70px;" required>
                                            
                                            </form>
                                    </div>
                                    
                                    <div class="col-3 col-md-4 text-end">
                                        <p class="mb-1 fw-bold text-danger">
                                            Rp <?php echo number_format($item['price'] * $item['quantity'], 0, ',', '.'); ?>
                                        </p>
                                        <form method="POST" action="<?= $base_url; ?>cart.php" class="d-inline">
                                            <input type="hidden" name="action" value="remove">
                                            <input type="hidden" name="product_id" value="<?php echo $item['product_id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Hapus Item">
                                                <i class="bi bi-trash"></i> Hapus
                                            </button>
                                        </form>
                                    </div>
                                    
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="row justify-content-end">
                <div class="col-md-5 col-lg-4">
                    <div class="p-3 cart-total rounded shadow-sm">
                        <h5 class="mb-2 fw-normal">Subtotal Belanja:</h5>
                        <p class="mb-0 fs-3 text-dark">
                            Rp **<?php echo number_format($total_keranjang, 0, ',', '.'); ?>**
                        </p>
                    </div>
                    
                    <div class="d-grid mt-3">
                        <?php if ($is_logged_in): ?>
                            <a href="<?= $base_url; ?>checkout.php" class="btn btn-lg checkout-button shadow-sm">
                                Lanjut ke Checkout <i class="bi bi-arrow-right"></i>
                            </a>
                        <?php else: ?>
                            <a href="<?= $base_url; ?>login.php?redirect=checkout.php" class="btn btn-lg btn-warning shadow-sm">
                                <i class="bi bi-person-fill"></i> Login untuk Checkout
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

        <?php else: ?>
            <div class="alert alert-info text-center mt-4" role="alert">
                <i class="bi bi-info-circle"></i> Keranjang Anda kosong. Segera tambahkan produk favorit Anda!
            </div>
        <?php endif; ?>

    </main>

    <footer class="text-center py-3 mt-5 border-top">
        <p class="mb-0">&copy; 2024 Coffee Store. Powered by PHP Native.</p>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>