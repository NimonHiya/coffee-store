<?php
session_start();
require_once '../../config/database.php';
require_once '../../functions/auth_check.php';

// Hanya admin yang boleh akses
requireLogin('admin', '../../login.php');

$error = '';
$categories = [];
$product = null;

// Ambil nama admin untuk navbar
$admin_name = $_SESSION['username'] ?? 'Admin';

// Pastikan ID produk ada
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: index.php");
    exit();
}

$product_id = (int)$_GET['id'];

// --- Ambil data produk ---
$stmt = $conn->prepare("SELECT * FROM products WHERE id = ?");
$stmt->bind_param("i", $product_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    header("Location: index.php?error=Produk tidak ditemukan.");
    exit();
}
$product = $result->fetch_assoc();
$stmt->close();

// --- Ambil semua kategori ---
$cat_result = $conn->query("SELECT id, name FROM categories ORDER BY name ASC");
while ($row = $cat_result->fetch_assoc()) {
    $categories[] = $row;
}

// --- Proses Form UPDATE ---
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name = $conn->real_escape_string(trim($_POST['name']));
    $category_id = (int)$_POST['category_id'];
    $description = $conn->real_escape_string(trim($_POST['description']));
    $price = (float)$_POST['price'];
    $stock = (int)$_POST['stock'];
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $current_image_path = $product['image_path'];

    // --- Upload gambar baru jika ada ---
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $target_dir = "../../assets/images/products/";
        if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);

        $ext = strtolower(pathinfo($_FILES["image"]["name"], PATHINFO_EXTENSION));
        $new_file_name = uniqid('prod_') . '.' . $ext;
        $target_file = $target_dir . $new_file_name;

        if (move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
            // Simpan path relatif (tanpa leading slash) sehingga konsisten di seluruh aplikasi
            $new_image_path = 'assets/images/products/' . $new_file_name;
            // Hapus file lama jika diperlukan (dinyalakan jika Anda ingin auto-delete)
            if ($current_image_path && file_exists('../../' . $current_image_path)) {
                // unlink('../../' . $current_image_path);
            }
            $current_image_path = $new_image_path;
        } else {
            $error = "Gagal mengupload gambar. Periksa izin folder dan ukuran file.";
        }
    }

    // --- UPDATE DATABASE ---
    if (!$error) {
    $stmt_update = $conn->prepare("UPDATE products SET name=?, category_id=?, description=?, price=?, stock=?, is_active=?, image_path=? WHERE id=?");
    // Tipe param: name(s), category_id(i), description(s), price(d), stock(i), is_active(i), image_path(s), id(i)
    $stmt_update->bind_param("sisdissi", $name, $category_id, $description, $price, $stock, $is_active, $current_image_path, $product_id);

        if ($stmt_update->execute()) {
            header("Location: index.php?success=Produk " . urlencode($name) . " berhasil diperbarui!");
            exit();
        } else {
            $error = "Gagal memperbarui produk: " . $stmt_update->error;
        }
        $stmt_update->close();
    }

    // Reload produk jika ada error
    if ($error) {
        $stmt_reload = $conn->prepare("SELECT * FROM products WHERE id = ?");
        $stmt_reload->bind_param("i", $product_id);
        $stmt_reload->execute();
        $product = $stmt_reload->get_result()->fetch_assoc();
        $stmt_reload->close();
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Admin - Edit Produk</title>
<link rel="stylesheet" href="../../assets/css/style.css">
<style>
/* ===== NAVBAR ===== */
header {
    background: #8B4513;
    padding: 12px 0;
}
header nav {
    width: 90%;
    max-width: 1200px;
    margin: auto;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
header nav h2 {color:#fff; margin:0;}
header nav ul {list-style:none; display:flex; gap:15px; margin:0; padding:0;}
header nav ul li a {color:#fff; text-decoration:none; padding:6px 12px; border-radius:6px;}
header nav ul li.logout a {background:#e74c3c;}
header nav ul li.logout a:hover {background:#c0392b;}
header nav ul li a:hover {background: rgba(255,255,255,0.2);}

/* ===== FORM CARD ===== */
.edit-card {
    background:#fff;
    padding:25px 30px;
    border-radius:10px;
    max-width:650px;
    margin:25px auto;
    box-shadow:0 4px 12px rgba(0,0,0,0.1);
}
.edit-card label {display:block; font-weight:bold; margin-bottom:6px;}
.edit-card input[type="text"], 
.edit-card input[type="number"], 
.edit-card select, 
.edit-card textarea {width:100%; padding:10px; border-radius:6px; border:1px solid #ccc; margin-bottom:18px;}
.edit-card textarea {height:120px; resize:vertical;}
.edit-card input[type="file"] {margin-top:8px; margin-bottom:20px;}
.edit-card .checkbox-container {display:flex; align-items:center; gap:8px; margin-bottom:20px;}
.edit-card .current-image img {border-radius:8px; border:1px solid #ddd; box-shadow:0 2px 5px rgba(0,0,0,0.1);}
.edit-card button {padding:12px; background:#4CAF50; border:none; color:#fff; font-weight:bold; font-size:16px; cursor:pointer; width:100%; border-radius:6px;}
.edit-card button:hover {background:#43a047;}
.back-link {display:inline-block; margin-bottom:15px; text-decoration:none; color:#555;}
.back-link:hover {text-decoration:underline;}
main h3 {text-align:center; margin-bottom:10px;}
</style>
</head>
<body>

<header>
<nav>
    <h2>⚙️ Dashboard Admin</h2>
    <ul>
        <li>Halo, <?php echo htmlspecialchars($admin_name); ?></li>
        <li><a href="../dashboard.php">Dashboard</a></li>
        <li><a href="index.php">Produk</a></li>
        <li><a href="../categories/index.php">Kategori</a></li>
        <li><a href="../orders/index.php">Pesanan</a></li>
        <li class="logout"><a href="../../logout.php">Logout</a></li>
    </ul>
</nav>
</header>

<main class="container">
<h3>Edit Produk: <?php echo htmlspecialchars($product['name']); ?></h3>
<a class="back-link" href="index.php">← Kembali ke Daftar Produk</a>

<?php if($error): ?>
<p style="color:red;text-align:center;"><?php echo $error; ?></p>
<?php endif; ?>

<div class="edit-card">
<form method="POST" action="edit.php?id=<?php echo $product_id; ?>" enctype="multipart/form-data">

<label for="name">Nama Produk:</label>
<input type="text" id="name" name="name" value="<?php echo htmlspecialchars($product['name']); ?>" required>

<label for="category_id">Kategori:</label>
<select id="category_id" name="category_id" required>
<option value="">Pilih Kategori</option>
<?php foreach($categories as $cat): ?>
<option value="<?php echo $cat['id']; ?>" <?php echo ($cat['id']==$product['category_id'])?'selected':''; ?>><?php echo htmlspecialchars($cat['name']); ?></option>
<?php endforeach; ?>
</select>

<label for="description">Deskripsi:</label>
<textarea id="description" name="description"><?php echo htmlspecialchars($product['description']); ?></textarea>

<label for="price">Harga (Rp):</label>
<input type="number" id="price" name="price" step="1000" min="0" value="<?php echo htmlspecialchars($product['price']); ?>" required>

<label for="stock">Stok:</label>
<input type="number" id="stock" name="stock" min="0" value="<?php echo htmlspecialchars($product['stock']); ?>" required>

<div class="current-image">
    <label>Gambar Saat Ini:</label>
    <?php if($product['image_path']): ?>
        <?php
            // Dari lokasi admin/products/edit.php, 2 level ke atas ke root proyek
            $img_src = '../../' . ltrim($product['image_path'], '/');
        ?>
        <img src="<?php echo htmlspecialchars($img_src); ?>?t=<?php echo time(); ?>" style="max-width:150px;">
    <?php else: ?>
        <p>Tidak ada gambar.</p>
    <?php endif; ?>
</div>

<label for="image">Ganti Gambar:</label>
<input type="file" id="image" name="image" accept="image/*">

<div class="checkbox-container">
<input type="checkbox" id="is_active" name="is_active" <?php echo $product['is_active']?'checked':''; ?>>
<label for="is_active" style="margin:0;">Aktifkan Produk</label>
</div>

<button type="submit">Perbarui Produk</button>
</form>
</div>
</main>

<footer>
<p style="text-align:center;">&copy; 2024 Admin Coffee Store</p>
</footer>

</body>
</html>
