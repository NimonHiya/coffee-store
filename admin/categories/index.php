<?php
session_start();
require_once '../../config/database.php';
require_once '../../functions/auth_check.php';

requireLogin('admin', '../../login.php');

$error = '';
$message = isset($_GET['success']) ? htmlspecialchars($_GET['success']) : '';
$edit_mode = false;
$category_to_edit = null;

// --- A. Logika CREATE & UPDATE (Jika ada POST Request) ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
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
                header("Location: index.php?success=Kategori **" . urlencode($name) . "** berhasil diperbarui.");
                exit();
            } else {
                $error = "Gagal memperbarui kategori: " . $stmt->error;
            }
        } else {
            // Logika CREATE
            $stmt = $conn->prepare("INSERT INTO categories (name) VALUES (?)");
            $stmt->bind_param("s", $name);
            if ($stmt->execute()) {
                header("Location: index.php?success=Kategori **" . urlencode($name) . "** berhasil ditambahkan.");
                exit();
            } else {
                 if ($conn->errno == 1062) { // Error 1062 = Duplicate entry (UNIQUE constraint)
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
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $delete_id = (int)$_GET['id'];
    
    // Pastikan tidak ada produk yang terkait dengan kategori ini (atau kita hapus saja)
    // Berdasarkan SQL kita, produk akan diset NULL jika kategorinya dihapus (ON DELETE SET NULL)
    
    $stmt_delete = $conn->prepare("DELETE FROM categories WHERE id = ?");
    $stmt_delete->bind_param("i", $delete_id);
    
    if ($stmt_delete->execute()) {
        header("Location: index.php?success=Kategori berhasil dihapus.");
        exit();
    } else {
        $error = "Gagal menghapus kategori: " . $stmt_delete->error;
    }
    $stmt_delete->close();
}

// --- C. Logika EDIT Mode (Jika ada GET request dengan action=edit) ---
if (isset($_GET['action']) && $_GET['action'] == 'edit' && isset($_GET['id'])) {
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
$categories_result = $conn->query("SELECT id, name FROM categories ORDER BY name ASC");
$categories = $categories_result->fetch_all(MYSQLI_ASSOC);

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Admin - Manajemen Kategori</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body>
    <header>
        <nav>
            <p>⚙️ **Admin Panel** | Selamat datang, <?php echo htmlspecialchars($_SESSION['username']); ?></p>
            <ul>
                <li><a href="../dashboard.php">Dashboard</a></li>
                <li><a href="../products/index.php">Produk</a></li>
                <li><a href="index.php">Kategori</a></li>
                <li><a href="../../logout.php" style="color: red;">Logout</a></li>
            </ul>
        </nav>
    </header>

    <main class="container">
        <h3>Manajemen Kategori</h3>
        
        <?php if ($message): ?>
            <p style="color: green; font-weight: bold;"><?php echo $message; ?></p>
        <?php endif; ?>
        <?php if ($error): ?>
            <p style="color: red; font-weight: bold;"><?php echo $error; ?></p>
        <?php endif; ?>

        <h4><?php echo $edit_mode ? 'Edit Kategori' : 'Tambah Kategori Baru'; ?></h4>
        <form method="POST" action="index.php">
            <?php if ($edit_mode): ?>
                <input type="hidden" name="id" value="<?php echo $category_to_edit['id']; ?>">
            <?php endif; ?>
            
            <label for="name">Nama Kategori:</label>
            <input type="text" id="name" name="name" 
                   value="<?php echo $edit_mode ? htmlspecialchars($category_to_edit['name']) : ''; ?>" required>
            
            <button type="submit"><?php echo $edit_mode ? 'Update' : 'Simpan'; ?></button>
            <?php if ($edit_mode): ?>
                <a href="index.php">Batal Edit</a>
            <?php endif; ?>
        </form>

        <hr>

        <h4>Daftar Kategori Saat Ini</h4>
        <?php if (count($categories) > 0): ?>
            <table border="1" style="width: 50%; border-collapse: collapse; margin-top: 15px;">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nama Kategori</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($categories as $cat): ?>
                    <tr>
                        <td><?php echo $cat['id']; ?></td>
                        <td><?php echo htmlspecialchars($cat['name']); ?></td>
                        <td>
                            <a href="index.php?action=edit&id=<?php echo $cat['id']; ?>">Edit</a> | 
                            <a href="index.php?action=delete&id=<?php echo $cat['id']; ?>" 
                               onclick="return confirm('Yakin ingin menghapus kategori <?php echo htmlspecialchars($cat['name']); ?>? Tindakan ini akan menghapus kategori dari produk yang terkait.')" 
                               style="color: red;">Hapus</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>Belum ada kategori terdaftar.</p>
        <?php endif; ?>
    </main>

    <footer>
        <p>&copy; 2024 Admin Coffee Store.</p>
    </footer>
</body>
</html>

<?php
$conn->close();
?>