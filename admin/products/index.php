<?php
session_start();
require_once '../../config/database.php';
require_once '../../functions/auth_check.php';

// Pastikan hanya admin yang bisa mengakses halaman ini
requireLogin('admin', '../../login.php');

// 1. Ambil data produk
$query = "SELECT 
            p.id, 
            p.name, 
            c.name AS category_name, 
            p.price, 
            p.stock,
            p.is_active
          FROM products p
          LEFT JOIN categories c ON p.category_id = c.id
          ORDER BY p.id DESC";
          
$result = $conn->query($query);

// Cek apakah ada pesan sukses dari halaman lain (misalnya setelah create/update)
$message = isset($_GET['success']) ? htmlspecialchars($_GET['success']) : '';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Admin - Daftar Produk</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body>
    <header>
        <nav>
            <p>⚙️ **Admin Panel** | Selamat datang, <?php echo htmlspecialchars($_SESSION['username']); ?></p>
            <ul>
                <li><a href="../dashboard.php">Dashboard</a></li>
                <li><a href="index.php">Produk</a></li>
                <li><a href="../categories/index.php">Kategori</a></li>
                <li><a href="../../logout.php" style="color: red;">Logout</a></li>
            </ul>
        </nav>
    </header>

    <main class="container">
        <h3>Daftar Produk</h3>
        
        <?php if ($message): ?>
            <p style="color: green; font-weight: bold;"><?php echo $message; ?></p>
        <?php endif; ?>

        <p><a href="create.php" style="background-color: green; color: white; padding: 10px; text-decoration: none;">+ Tambah Produk Baru</a></p>

        <?php if ($result->num_rows > 0): ?>
            <table border="1" style="width: 100%; border-collapse: collapse; margin-top: 15px;">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nama Produk</th>
                        <th>Kategori</th>
                        <th>Harga</th>
                        <th>Stok</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo $row['id']; ?></td>
                        <td><?php echo htmlspecialchars($row['name']); ?></td>
                        <td><?php echo htmlspecialchars($row['category_name'] ?: 'Tidak Berkategori'); ?></td>
                        <td>Rp <?php echo number_format($row['price'], 0, ',', '.'); ?></td>
                        <td><?php echo $row['stock']; ?></td>
                        <td>
                            <span style="color: <?php echo $row['is_active'] ? 'green' : 'red'; ?>;">
                                <?php echo $row['is_active'] ? 'Aktif' : 'Tidak Aktif'; ?>
                            </span>
                        </td>
                        <td>
                            <a href="edit.php?id=<?php echo $row['id']; ?>">Edit</a> | 
                            <a href="delete.php?id=<?php echo $row['id']; ?>" 
                               onclick="return confirm('Yakin ingin menghapus produk <?php echo htmlspecialchars($row['name']); ?>?')" 
                               style="color: red;">Hapus</a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>Belum ada produk terdaftar.</p>
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