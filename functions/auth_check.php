<?php
// Pastikan session sudah dimulai sebelum memanggil file ini
// Di halaman yang memanggil file ini, harus ada session_start();

/**
 * Fungsi untuk memeriksa status login dan role pengguna.
 * @param string $required_role Role yang dibutuhkan ('admin' atau 'user').
 * @return bool True jika role cocok dan sudah login, False jika tidak.
 */
function checkAuthentication($required_role = 'user') {
    // Cek apakah session sudah dimulai (opsional, tapi baik untuk kejelasan)
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }

    // 1. Cek apakah user sudah login
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
        return false;
    }

    // 2. Cek apakah role sesuai
    if ($required_role === 'admin' && $_SESSION['role'] !== 'admin') {
        return false;
    }
    
    return true;
}

/**
 * Fungsi untuk memproteksi halaman. Jika user tidak terautentikasi,
 * akan di-redirect ke halaman login.
 * @param string $required_role Role yang dibutuhkan ('admin' atau 'user').
 * @param string $redirect_page Halaman yang dituju jika gagal (default login.php).
 */
function requireLogin($required_role = 'user', $redirect_page = '/login.php') {
    if (!checkAuthentication($required_role)) {
        // Jika Admin akses gagal, pesan error khusus bisa ditambahkan.
        if ($required_role === 'admin') {
            // Arahkan ke halaman utama dengan pesan "Akses ditolak"
            header("Location: /index.php?error=access_denied");
        } else {
            // Arahkan ke halaman login
            header("Location: " . $redirect_page);
        }
        exit();
    }
}
?>