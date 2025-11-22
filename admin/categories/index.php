<?php
session_start();
require_once '../../config/database.php';
require_once '../../functions/auth_check.php';

// --- OPTIMASI PATH & BASE URL ---
// Tentukan base path untuk navigasi yang benar (naik dua level dari /admin/categories/index.php)
$base_path = rtrim(str_replace('\\','/', dirname(dirname(dirname($_SERVER['SCRIPT_NAME'])))), '/');
if ($base_path === '' || $base_path === '.') {
    $base_path = '/';
} else {
    $base_path = $base_path . '/';
}
$admin_base = $base_path . 'admin/';

// Panggil fungsi proteksi! Hanya Admin yang boleh akses
requireLogin('admin', $base_path . 'login.php');

$error = '';
$message = isset($_GET['success']) ? htmlspecialchars($_GET['success']) : '';
$edit_mode = false;
$category_to_edit = null;
$admin_name = $_SESSION['username'] ?? 'Admin'; // Ambil nama admin untuk header

// --- A. Logika CREATE & UPDATE (Jika ada POST Request) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($conn)) {
    $name = $conn->real_escape_string(trim($_POST['name']));
    $category_id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

    if (empty($name)) {
        $error = "Nama kategori tidak boleh kosong.";
    } else {
        if ($category_id > 0) {
            // Logika UPDATE
            $stmt = $conn->prepare("UPDATE categories SET name = ? WHERE id = ?");
            $stmt->bind_param("si", $name, $category_id);
            if ($stmt->execute()) {
                header("Location: index.php?success=" . urlencode("Kategori **{$name}** berhasil diperbarui."));
                exit();
            } else {
                if ($conn->errno == 1062) {
                    $error = "Gagal memperbarui: Nama kategori **" . htmlspecialchars($name) . "** sudah ada.";
                } else {
                    $error = "Gagal memperbarui kategori: " . $stmt->error;
                }
            }
        } else {
            // Logika CREATE
            $stmt = $conn->prepare("INSERT INTO categories (name) VALUES (?)");
            $stmt->bind_param("s", $name);
            if ($stmt->execute()) {
                header("Location: index.php?success=" . urlencode("Kategori **{$name}** berhasil ditambahkan."));
                exit();
            } else {
                if ($conn->errno == 1062) { // Error 1062 = Duplicate entry
                    $error = "Kategori **" . htmlspecialchars($name) . "** sudah ada.";
                } else {
                    $error = "Gagal menambahkan kategori: " . $stmt->error;
                }
            }
        }
        $stmt->close();
    }
}

// --- B. Logika DELETE (Jika ada GET request dengan action=delete) ---
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id']) && isset($conn)) {
    $delete_id = (int)$_GET['id'];
    
    // Asumsi: products.category_id diset NULL jika kategori dihapus (melalui foreign key ON DELETE SET NULL)
    $stmt_delete = $conn->prepare("DELETE FROM categories WHERE id = ?");
    $stmt_delete->bind_param("i", $delete_id);
    
    if ($stmt_delete->execute()) {
        header("Location: index.php?success=" . urlencode("Kategori berhasil dihapus. Produk terkait diset 'Tidak Berkategori'."));
        exit();
    } else {
        $error = "Gagal menghapus kategori: " . $stmt_delete->error;
    }
    $stmt_delete->close();
}

// --- C. Logika EDIT Mode (Jika ada GET request dengan action=edit) ---
if (isset($_GET['action']) && $_GET['action'] == 'edit' && isset($_GET['id']) && isset($conn)) {
    $edit_mode = true;
    $edit_id = (int)$_GET['id'];
    $stmt_edit = $conn->prepare("SELECT id, name FROM categories WHERE id = ?");
    $stmt_edit->bind_param("i", $edit_id);
    $stmt_edit->execute();
    $result_edit = $stmt_edit->get_result();
    
    if ($result_edit->num_rows === 1) {
        $category_to_edit = $result_edit->fetch_assoc();
    } else {
        $error = "Kategori tidak ditemukan untuk diedit.";
        $edit_mode = false;
    }
    $stmt_edit->close();
}

// --- D. Ambil Data Kategori untuk Tampilan (READ) ---
$categories = [];
if (isset($conn)) {
    $categories_result = $conn->query("SELECT id, name FROM categories ORDER BY name ASC");
    if ($categories_result) {
        $categories = $categories_result->fetch_all(MYSQLI_ASSOC);
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Manajemen Kategori</title>
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
                        <li class="nav-item"><a class="nav-link" href="<?= $admin_base; ?>products/index.php"><i class="bi bi-box"></i> Produk</a></li>
                        <li class="nav-item"><a class="nav-link active" aria-current="page" href="index.php"><i class="bi bi-tags"></i> Kategori</a></li>
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
        <h3 class="mb-4"><i class="bi bi-tags-fill"></i> Manajemen Kategori</h3>

        <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i> <?php echo str_replace(['**'], ['<strong>', '</strong>'], $message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i> **Error:** <?php echo str_replace(['**'], ['<strong>', '</strong>'], $error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="row g-4">
            <div class="col-lg-4">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white fw-bold">
                        <i class="bi bi-pencil-square"></i> <?php echo $edit_mode ? 'Edit Kategori #' . $category_to_edit['id'] : 'Tambah Kategori Baru'; ?>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="index.php">
                            <?php if ($edit_mode): ?>
                                <input type="hidden" name="id" value="<?php echo $category_to_edit['id']; ?>">
                            <?php endif; ?>
                            
                            <div class="mb-3">
                                <label for="name" class="form-label fw-bold">Nama Kategori:</label>
                                <input type="text" id="name" name="name" class="form-control" 
                                       value="<?php echo $edit_mode ? htmlspecialchars($category_to_edit['name']) : htmlspecialchars($_POST['name'] ?? ''); ?>" required>
                            </div>
                            
                            <div class="d-grid mt-3">
                                <button type="submit" class="btn btn-<?= $edit_mode ? 'warning' : 'primary'; ?> mb-2">
                                    <i class="bi bi-<?= $edit_mode ? 'arrow-repeat' : 'plus-circle'; ?>"></i> <?php echo $edit_mode ? 'Update Kategori' : 'Simpan Kategori'; ?>
                                </button>
                                <?php if ($edit_mode): ?>
                                    <a class="btn btn-outline-secondary" href="index.php">
                                        <i class="bi bi-x-circle"></i> Batal Edit
                                    </a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-8">
                <h4 class="mb-3">Daftar Kategori Saat Ini</h4>
                <?php if (count($categories) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover table-bordered align-middle">
                            <thead class="table-dark">
                                <tr>
                                    <th style="width: 10%;">ID</th>
                                    <th>Nama Kategori</th>
                                    <th style="width: 30%;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($categories as $cat): ?>
                                <tr>
                                    <td class="text-center"><?php echo $cat['id']; ?></td>
                                    <td class="fw-bold"><?php echo htmlspecialchars($cat['name']); ?></td>
                                    <td class="text-nowrap">
                                        <a class="btn btn-sm btn-warning me-2" href="index.php?action=edit&id=<?php echo $cat['id']; ?>" title="Edit">
                                            <i class="bi bi-pencil-square"></i> Edit
                                        </a>
                                        <a class="btn btn-sm btn-danger" href="index.php?action=delete&id=<?php echo $cat['id']; ?>" 
                                            onclick="return confirm('Yakin ingin menghapus kategori <?php echo htmlspecialchars($cat['name']); ?>? Produk terkait akan diset \'Tidak Berkategori\'.')" title="Hapus">
                                            <i class="bi bi-trash"></i> Hapus
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info text-center">
                        <i class="bi bi-info-circle"></i> Belum ada kategori terdaftar.
                    </div>
                <?php endif; ?>
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