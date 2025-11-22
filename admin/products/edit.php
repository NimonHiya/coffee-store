<?php
// 1. PENTING: Selalu mulai session di halaman yang butuh data user
session_start();

// 2. Sertakan koneksi database (jika dibutuhkan) dan fungsi otentikasi
require_once '../../config/database.php';
require_once '../../functions/auth_check.php';

// --- OPTIMASI PATH & BASE URL ---
// Tentukan base path untuk navigasi yang benar (naik dua level dari /admin/products/edit.php)
$base_path = rtrim(str_replace('\\','/', dirname(dirname(dirname($_SERVER['SCRIPT_NAME'])))), '/');
if ($base_path === '' || $base_path === '.') {
    $base_path = '/';
} else {
    $base_path = $base_path . '/';
}
$admin_base = $base_path . 'admin/';

// 3. Panggil fungsi proteksi! Hanya Admin yang boleh akses
requireLogin('admin', $base_path . 'login.php');

$error = '';
$categories = [];
$product = null;

// Ambil nama admin untuk navigasi
$admin_name = $_SESSION['username'] ?? 'Admin';

// Pastikan ID produk ada di URL
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: index.php");
    exit();
}

$product_id = (int)$_GET['id'];

// --- BAGIAN 1: Mengambil Data LAMA dan Kategori ---
if (isset($conn)) {
    // Ambil data produk yang akan diedit
    $stmt = $conn->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        header("Location: index.php?error=" . urlencode("Produk tidak ditemukan."));
        exit();
    }
    $product = $result->fetch_assoc();
    $stmt->close();

    $current_image_path = $product['image_path'] ?? ''; 

    // Ambil semua kategori untuk dropdown
    $cat_result = $conn->query("SELECT id, name FROM categories ORDER BY name ASC");
    if ($cat_result) {
        while ($row = $cat_result->fetch_assoc()) {
            $categories[] = $row;
        }
    }
} else {
    $error = "Kesalahan koneksi database.";
}


// --- BAGIAN 2: Memproses Form UPDATE (POST Request) ---
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($conn)) {
    // Ambil dan sanitasi input
    $name = $conn->real_escape_string(trim($_POST['name'] ?? ''));
    $category_id = (int)($_POST['category_id'] ?? 0);
    $description = $conn->real_escape_string(trim($_POST['description'] ?? ''));
    $price = (float)($_POST['price'] ?? 0);
    $stock = (int)($_POST['stock'] ?? 0);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    // PENTING: Validasi Server-Side
    if (empty($name)) {
        $error = "Nama Produk wajib diisi dan tidak boleh kosong.";
    }

    // Default path adalah path yang sudah ada
    $updated_image_path = $current_image_path; 

    // 1. Proses upload gambar BARU (jika ada)
    if (!$error && isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $target_dir = "../../assets/images/products/";
        
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true); 
        }

        $image_file_type = strtolower(pathinfo($_FILES["image"]["name"], PATHINFO_EXTENSION));
        $new_file_name = uniqid('prod_') . '.' . $image_file_type;
        $target_file = $target_dir . $new_file_name;

        if (move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
            $new_image_path = 'assets/images/products/' . $new_file_name;
            
            // Hapus gambar lama jika berhasil upload baru
            if (!empty($product['image_path']) && file_exists('../../' . $product['image_path'])) {
                @unlink('../../' . $product['image_path']); // Gunakan @ untuk suppress warning jika gagal
            }
            $updated_image_path = $new_image_path; // Update path yang akan disimpan
        } else {
            $error = "Error saat mengupload file gambar. Cek izin folder.";
        }
    }
    
    // 2. Jalankan query UPDATE
    if (!$error) {
        $stmt_update = $conn->prepare("UPDATE products SET name=?, category_id=?, description=?, price=?, stock=?, is_active=?, image_path=? WHERE id=?");
        
        // Tipe param: s (name), i (cat_id), s (desc), d (price), i (stock), i (active), s (path), i (id)
        $stmt_update->bind_param("sisdissi", $name, $category_id, $description, $price, $stock, $is_active, $updated_image_path, $product_id);

        if ($stmt_update->execute()) {
            // Berhasil, redirect ke halaman daftar produk
            header("Location: index.php?success=Produk **" . urlencode($name) . "** berhasil diperbarui!");
            exit();
        } else {
            $error = "Gagal memperbarui produk: " . $stmt_update->error;
        }

        $stmt_update->close();
    }
    
    // Ambil ulang data produk jika terjadi error agar form terisi data terbaru
    if ($error) {
        $stmt_reload = $conn->prepare("SELECT * FROM products WHERE id = ?");
        $stmt_reload->bind_param("i", $product_id);
        $stmt_reload->execute();
        // Update variabel $product dengan data dari POST (jika gagal) atau dari DB (jika error DB)
        $product = array_merge($product, $_POST); 
        // Lebih aman, ambil dari DB lalu timpa dengan nilai POST jika ada error form
        $reloaded_product = $stmt_reload->get_result()->fetch_assoc();
        if ($reloaded_product) {
             $product = array_merge($reloaded_product, $_POST);
        }
        $stmt_reload->close();
        
        // Update path untuk tampilan form
        if (!empty($updated_image_path) && !isset($_FILES['image'])) {
             // Jika ada error form tapi tidak ada upload baru, gunakan path lama
             $current_image_path = $updated_image_path;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Edit Produk</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css"> 
    <style>
        :root {
            --primary-color: #6F4E37; /* Coklat Kopi */
        }
        .admin-navbar {
            background-color: var(--primary-color) !important;
        }
        .form-container {
            max-width: 700px;
            margin: 0 auto;
        }
        .current-image-preview {
            max-width: 150px;
            height: auto;
            border-radius: 8px;
            border: 1px solid #ddd;
        }
    </style>
</head>
<body>
    <header>
        <nav class="navbar navbar-expand-lg navbar-dark admin-navbar shadow">
            <div class="container-fluid">
                <a class="navbar-brand fw-bold" href="<?= $admin_base; ?>dashboard.php">☕ Admin Panel</a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#adminNav" aria-controls="adminNav" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="adminNav">
                    <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                        <li class="nav-item"><a class="nav-link" href="<?= $admin_base; ?>dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a></li>
                        <li class="nav-item"><a class="nav-link active" aria-current="page" href="index.php"><i class="bi bi-box"></i> Produk</a></li>
                        <li class="nav-item"><a class="nav-link" href="<?= $admin_base; ?>categories/index.php"><i class="bi bi-tags"></i> Kategori</a></li>
                        <li class="nav-item"><a class="nav-link" href="<?= $admin_base; ?>orders/index.php"><i class="bi bi-cart-check"></i> Pesanan</a></li>
                    </ul>
                    
                    <ul class="navbar-nav ms-auto">
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="bi bi-person-circle"></i> <?php echo htmlspecialchars($admin_name); ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                                <li><a class="dropdown-item text-danger" href="<?= $base_path; ?>logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a></li>
                            </ul>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>
    </header>

    <main class="container my-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3 class="mb-0"><i class="bi bi-pencil-square"></i> Edit Produk: <?php echo htmlspecialchars($product['name']); ?></h3>
            <a href="index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Kembali ke Daftar Produk</a>
        </div>

        <div class="card shadow-lg form-container">
            <div class="card-body p-4 p-md-5">

                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i> **Gagal:** <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <form method="POST" action="edit.php?id=<?php echo $product_id; ?>" enctype="multipart/form-data">
                    
                    <div class="mb-3">
                        <label for="name" class="form-label fw-bold">Nama Produk:</label>
                        <input type="text" id="name" name="name" class="form-control" value="<?php echo htmlspecialchars($product['name']); ?>" required>
                    </div>

                    <div class="mb-3">
                        <label for="category_id" class="form-label fw-bold">Kategori:</label>
                        <select id="category_id" name="category_id" class="form-select" required>
                            <option value="">Pilih Kategori</option>
                            <?php 
                            $selected_cat = $product['category_id'] ?? null;
                            foreach ($categories as $cat): 
                                $selected = ($selected_cat == $cat['id']) ? 'selected' : '';
                            ?>
                                <option value="<?php echo $cat['id']; ?>" <?= $selected; ?>><?php echo htmlspecialchars($cat['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label fw-bold">Deskripsi:</label>
                        <textarea id="description" name="description" class="form-control" rows="4"><?php echo htmlspecialchars($product['description']); ?></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="price" class="form-label fw-bold">Harga (Rp):</label>
                            <input type="number" id="price" name="price" class="form-control" step="1000" min="0" value="<?php echo htmlspecialchars($product['price']); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="stock" class="form-label fw-bold">Stok:</label>
                            <input type="number" id="stock" name="stock" class="form-control" min="0" value="<?php echo htmlspecialchars($product['stock']); ?>" required>
                        </div>
                    </div>
                    
                    <hr>

                    <div class="mb-4">
                        <label class="form-label fw-bold">Gambar Saat Ini:</label>
                        <?php if (!empty($product['image_path'])): ?>
                            <?php 
                                $image_display_path = '../../' . htmlspecialchars($product['image_path']); 
                                $cache_buster = '?t=' . time(); 
                            ?>
                            <div class="d-flex align-items-center">
                                <img src="<?php echo $image_display_path . $cache_buster; ?>" alt="Gambar Produk" class="current-image-preview me-3">
                                <small class="text-muted">File: <?php echo htmlspecialchars($product['image_path']); ?></small>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-secondary py-2">Tidak ada gambar produk saat ini.</div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-4">
                        <label for="image" class="form-label fw-bold">Ganti Gambar:</label>
                        <input type="file" id="image" name="image" class="form-control" accept="image/*">
                        <div class="form-text">Pilih file baru untuk mengganti gambar saat ini.</div>
                    </div>
                    
                    <div class="form-check mb-4">
                        <input type="checkbox" id="is_active" name="is_active" class="form-check-input" <?php echo $product['is_active'] ? 'checked' : ''; ?>>
                        <label for="is_active" class="form-check-label fw-bold">Aktifkan Produk (Tampilkan di Toko)</label>
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-warning btn-lg">
                            <i class="bi bi-arrow-repeat"></i> Perbarui Produk
                        </button>
                    </div>
                </form>
            </div>
        </div>

    </main>
    
    <footer class="text-center py-3 mt-5 border-top">
        <p class="mb-0">&copy; 2024 Admin Coffee Store. Powered by Bootstrap 5.</p>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php
if (isset($conn)) {
    $conn->close();
}
?>