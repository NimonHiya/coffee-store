<?php
session_start();
require_once '../../config/database.php';
require_once '../../functions/auth_check.php';

// Proteksi: Hanya Admin yang boleh akses
requireLogin('admin', '../../login.php');

// Pastikan ID produk ada di URL
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: index.php?error=ID produk tidak valid.");
    exit();
}

$product_id = (int)$_GET['id'];

// 1. Ambil nama produk, path gambar, dan STOK saat ini
$stmt_select = $conn->prepare("SELECT name, image_path, stock FROM products WHERE id = ?");
$stmt_select->bind_param("i", $product_id);
$stmt_select->execute();
$result_select = $stmt_select->get_result();

if ($result_select->num_rows === 0) {
    header("Location: index.php?error=Produk tidak ditemukan.");
    exit();
}

$product_info = $result_select->fetch_assoc();
$product_name = $product_info['name'];
$image_to_delete = $product_info['image_path'];
$current_stock = $product_info['stock']; // Ambil nilai stok
$stmt_select->close();

// =========================================================
// ✅ CHECK 1: CEK RIWAYAT PESANAN (FOREIGN KEY CONSTRAINT)
// =========================================================
$stmt_check_orders = $conn->prepare("SELECT 1 FROM order_items WHERE product_id = ? LIMIT 1");
$stmt_check_orders->bind_param("i", $product_id);
$stmt_check_orders->execute();
$result_check_orders = $stmt_check_orders->get_result();

if ($result_check_orders->num_rows > 0) {
    // Produk sudah ada dalam riwayat pesanan (ON DELETE RESTRICT)
    $error_msg = "Produk **" . htmlspecialchars($product_name) . "** tidak dapat dihapus karena sudah tercatat dalam riwayat pesanan (Foreign Key Constraint).";
    $stmt_check_orders->close();
    $conn->close();
    header("Location: index.php?error=" . urlencode($error_msg));
    exit();
}
$stmt_check_orders->close();

// =========================================================
// ✅ CHECK 2: CEK STOK (INVENTARIS)
// =========================================================
if ($current_stock > 0) {
    // Produk memiliki stok > 0
    $error_msg = "Produk **" . htmlspecialchars($product_name) . "** tidak dapat dihapus karena masih ada **$current_stock unit stok** di inventaris. Harap set stok menjadi 0 terlebih dahulu atau nonaktifkan produk.";
    $conn->close();
    header("Location: index.php?error=" . urlencode($error_msg));
    exit();
}

// =========================================================
// 2. Jalankan query DELETE (Jika kedua pengecekan lolos)
// =========================================================
$stmt_delete = $conn->prepare("DELETE FROM products WHERE id = ?");
$stmt_delete->bind_param("i", $product_id);

if ($stmt_delete->execute()) {
    
    // Opsional: Hapus file gambar fisik dari server
    if ($image_to_delete && file_exists('../../' . $image_to_delete)) {
        // PENTING: Aktifkan unlink() untuk menghapus file fisik
        // unlink('../../' . $image_to_delete);
    }

    // Redirect dengan pesan sukses
    header("Location: index.php?success=Produk **" . urlencode($product_name) . "** berhasil dihapus.");
    exit();
} else {
    // Redirect dengan pesan error
    header("Location: index.php?error=Gagal menghapus produk: " . urlencode($stmt_delete->error));
    exit();
}

$stmt_delete->close();
$conn->close();

?>