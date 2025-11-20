<?php
/**
 * Fungsi untuk mengenkripsi password sebelum disimpan ke database (Hashing)
 * @param string $password Password yang ingin di-hash
 * @return string Password yang sudah di-hash
 */
function hashPassword($password) {
    // PASSWORD_DEFAULT menggunakan algoritma hashing yang kuat dan akan otomatis diperbarui oleh PHP.
    return password_hash($password, PASSWORD_DEFAULT);
}

/**
 * Fungsi untuk memverifikasi password
 * @param string $password Input password dari user
 * @param string $hashedPassword Password hash yang tersimpan di database
 * @return bool True jika cocok, False jika tidak
 */
function verifyPassword($password, $hashedPassword) {
    return password_verify($password, $hashedPassword);
}

// Tambahkan fungsi-fungsi helper lainnya di sini di masa mendatang (misal: sanitasi input)

?>