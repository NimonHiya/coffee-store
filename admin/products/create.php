<?php
// 1. PENTING: Selalu mulai session di halaman yang butuh data user
session_start();

// 2. Sertakan koneksi database (jika dibutuhkan) dan fungsi otentikasi
require_once '../../config/database.php';
require_once '../../functions/auth_check.php';
// require_once '../../functions/upload.php'; // Opsional: Sertakan fungsi upload jika Anda memindahkannya ke sana.

// --- OPTIMASI PATH & BASE URL ---
// Tentukan base path untuk navigasi yang benar (naik dua level dari /admin/products/create.php)
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
$admin_name = $_SESSION['username']; // Ambil nama admin untuk header

// Ambil semua kategori untuk dropdown
if (isset($conn)) {
    $cat_result = $conn->query("SELECT id, name FROM categories ORDER BY name ASC");
    if ($cat_result) {
        while ($row = $cat_result->fetch_assoc()) {
            $categories[] = $row;
        }
    }
}

// Simpan nilai POST sementara untuk mengisi ulang form jika terjadi error
$old_input = $_POST;

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($conn)) {
    // 1. Ambil dan sanitasi input
    $name = $conn->real_escape_string(trim($_POST['name'] ?? ''));
    $category_id = (int)($_POST['category_id'] ?? 0);
    $description = $conn->real_escape_string(trim($_POST['description'] ?? ''));
    $price = (float)($_POST['price'] ?? 0);
    $stock = (int)($_POST['stock'] ?? 0);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $image_path = null;

    // 2. Proses upload gambar
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $target_dir = "../../assets/images/products/"; // Path relatif dari file ini
        
        if (!is_dir($target_dir)) {
            if (!mkdir($target_dir, 0777, true)) {
                 $error = "Error: Gagal membuat direktori upload.";
            }
        }
        
        if (!$error) {
            $image_file_type = strtolower(pathinfo($_FILES["image"]["name"], PATHINFO_EXTENSION));
            $new_file_name = uniqid('prod_') . '.' . $image_file_type;
            $target_file = $target_dir . $new_file_name;

            if (move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
                // Path yang akan disimpan di database (relatif dari root e-commerce)
                $image_path = 'assets/images/products/' . $new_file_name;
            } else {
                $error = "Error saat memindahkan file gambar. Cek izin folder.";
            }
        }
    }

    if (!$error) {
        // 3. Masukkan data ke database menggunakan Prepared Statement
        $stmt = $conn->prepare("INSERT INTO products (name, category_id, description, price, stock, is_active, image_path) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sisdisi", $name, $category_id, $description, $price, $stock, $is_active, $image_path);

        if ($stmt->execute()) {
            // Berhasil, redirect ke halaman daftar produk
            header("Location: index.php?success=Produk **" . urlencode($name) . "** berhasil ditambahkan!");
            exit();
        } else {
            if ($conn->errno == 1062) { 
                $error = "Nama produk sudah terdaftar.";
            } else {
                $error = "Gagal menambahkan produk: " . $stmt->error;
            }
        }

        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Tambah Produk</title>
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
            <h3 class="mb-0"><i class="bi bi-box-arrow-in-up"></i> Tambah Produk Baru</h3>
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

                <form method="POST" action="create.php" enctype="multipart/form-data">
                    
                    <div class="mb-3">
                        <label for="name" class="form-label fw-bold">Nama Produk:</label>
                        <input type="text" id="name" name="name" class="form-control" value="<?php echo htmlspecialchars($old_input['name'] ?? ''); ?>" required>
                    </div>

                    <div class="mb-3">
                        <label for="category_id" class="form-label fw-bold">Kategori:</label>
                        <select id="category_id" name="category_id" class="form-select" required>
                            <option value="">Pilih Kategori</option>
                            <?php 
                            $selected_cat = $old_input['category_id'] ?? null;
                            foreach ($categories as $cat): 
                                $selected = ($selected_cat == $cat['id']) ? 'selected' : '';
                            ?>
                                <option value="<?php echo $cat['id']; ?>" <?= $selected; ?>><?php echo htmlspecialchars($cat['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label fw-bold">Deskripsi:</label>
                        <textarea id="description" name="description" class="form-control" rows="4"><?php echo htmlspecialchars($old_input['description'] ?? ''); ?></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="price" class="form-label fw-bold">Harga (Rp):</label>
                            <input type="number" id="price" name="price" class="form-control" step="1000" min="0" value="<?php echo htmlspecialchars($old_input['price'] ?? 0); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="stock" class="form-label fw-bold">Stok:</label>
                            <input type="number" id="stock" name="stock" class="form-control" min="0" value="<?php echo htmlspecialchars($old_input['stock'] ?? 0); ?>" required>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label for="image" class="form-label fw-bold">Gambar Produk:</label>
                        <input type="file" id="image" name="image" class="form-control" accept="image/*">
                        <div class="form-text">Maksimal ukuran file: 2MB.</div>
                    </div>
                    
                    <div class="form-check mb-4">
                        <?php 
                            $is_active_checked = !isset($old_input['is_active']) || ($old_input['is_active'] ?? 1); 
                        ?>
                        <input type="checkbox" id="is_active" name="is_active" class="form-check-input" <?= $is_active_checked ? 'checked' : ''; ?>>
                        <label for="is_active" class="form-check-label fw-bold">Aktifkan Produk (Tampilkan di Toko)</label>
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary btn-lg" style="background-color: var(--primary-color);">
                            <i class="bi bi-save"></i> Simpan Produk
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