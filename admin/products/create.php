<?php
session_start();
require_once '../../config/database.php';
require_once '../../functions/auth_check.php';

requireLogin('admin', '../../login.php');

$error = '';
$categories = [];

// Ambil semua kategori untuk dropdown
$cat_result = $conn->query("SELECT id, name FROM categories ORDER BY name ASC");
while ($row = $cat_result->fetch_assoc()) {
    $categories[] = $row;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // 1. Ambil dan sanitasi input
    $name = $conn->real_escape_string(trim($_POST['name']));
    $category_id = (int)$_POST['category_id'];
    $description = $conn->real_escape_string(trim($_POST['description']));
    $price = (float)$_POST['price'];
    $stock = (int)$_POST['stock'];
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $image_path = null;

    // 2. Proses upload gambar (Sangat Sederhana, perlu peningkatan!)
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $target_dir = "../../assets/images/products/";
        
        // Pastikan folder ada
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        
        $image_file_type = strtolower(pathinfo($_FILES["image"]["name"], PATHINFO_EXTENSION));
        $new_file_name = uniqid('prod_') . '.' . $image_file_type;
        $target_file = $target_dir . $new_file_name;

        // Pindahkan file yang diupload
        if (move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
            // Path yang akan disimpan di database (relatif dari root)
            $image_path = 'assets/images/products/' . $new_file_name;
        } else {
            $error = "Error saat mengupload file gambar.";
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
            $error = "Gagal menambahkan produk: " . $stmt->error;
        }

        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Admin - Tambah Produk</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body>
    <header>
        </header>

    <main class="container">
        <h3>Tambah Produk Baru</h3>
        <p><a href="index.php">← Kembali ke Daftar Produk</a></p>

        <?php if ($error): ?>
            <p style="color: red;"><?php echo $error; ?></p>
        <?php endif; ?>

        <form method="POST" action="create.php" enctype="multipart/form-data">
            <label for="name">Nama Produk:</label>
            <input type="text" id="name" name="name" required><br><br>

            <label for="category_id">Kategori:</label>
            <select id="category_id" name="category_id" required>
                <option value="">Pilih Kategori</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                <?php endforeach; ?>
            </select><br><br>

            <label for="description">Deskripsi:</label>
            <textarea id="description" name="description"></textarea><br><br>

            <label for="price">Harga (Rp):</label>
            <input type="number" id="price" name="price" step="1000" min="0" required><br><br>

            <label for="stock">Stok:</label>
            <input type="number" id="stock" name="stock" min="0" required><br><br>
            
            <label for="image">Gambar Produk:</label>
            <input type="file" id="image" name="image" accept="image/*"><br><br>
            
            <input type="checkbox" id="is_active" name="is_active" checked>
            <label for="is_active">Aktifkan Produk</label><br><br>

            <button type="submit">Simpan Produk</button>
        </form>
    </main>
    
    <footer>
        <p>&copy; 2024 Admin Coffee Store.</p>
    </footer>
</body>
</html>

<?php
$conn->close();
?>