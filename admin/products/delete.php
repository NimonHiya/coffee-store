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

// 1. Ambil nama produk untuk pesan konfirmasi/sukses
$stmt_select = $conn->prepare("SELECT name, image_path FROM products WHERE id = ?");
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
$stmt_select->close();

// 2. Jalankan query DELETE
$stmt_delete = $conn->prepare("DELETE FROM products WHERE id = ?");
$stmt_delete->bind_param("i", $product_id);

if ($stmt_delete->execute()) {
    // Opsional: Hapus file gambar fisik dari server
    if ($image_to_delete && file_exists('../../' . $image_to_delete)) {
        // unlink('../../' . $image_to_delete);
        // Uncomment jika ingin menghapus file fisik
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