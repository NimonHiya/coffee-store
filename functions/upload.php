<?php

/**
 * Fungsi untuk menangani proses upload gambar produk.
 * * @param array $fileArray Array $_FILES['nama_field'] dari form.
 * @return string|false Mengembalikan path relatif (mis. 'assets/images/products/namafile.jpg') jika sukses, atau FALSE jika gagal.
 */
function uploadProductImage(array $fileArray) {
    
    // Periksa apakah file diupload tanpa error
    if ($fileArray['error'] !== UPLOAD_ERR_OK) {
        // Error code 4 (UPLOAD_ERR_NO_FILE) berarti tidak ada file yang dipilih,
        // jadi kita asumsikan tidak ada update gambar.
        if ($fileArray['error'] === UPLOAD_ERR_NO_FILE) {
            return false;
        }
        // Jika error lain, log error dan return false
        error_log("Upload Error: " . $fileArray['error']);
        return false;
    }
    
    // Tentukan direktori tujuan relatif dari root web (dari file PHP saat ini, harus mundur 2x)
    // File ini ada di functions/, jadi kita perlu mundur 1x ke root proyek.
    $server_root = dirname(dirname(__FILE__)); // Ini akan mengarah ke folder coffee-store/
    $target_dir_relative = "assets/images/products/";
    $target_dir_full = $server_root . "/" . $target_dir_relative; 
    
    // Pastikan folder tujuan ada
    if (!is_dir($target_dir_full)) {
        if (!mkdir($target_dir_full, 0777, true)) {
            error_log("Failed to create directory: " . $target_dir_full);
            return false; // Gagal membuat folder
        }
    }

    // Buat nama file yang unik untuk menghindari tabrakan
    $image_file_type = strtolower(pathinfo($fileArray["name"], PATHINFO_EXTENSION));
    $new_file_name = 'prod_' . uniqid() . '.' . $image_file_type;
    $target_file = $target_dir_full . $new_file_name;

    // Pindahkan file dari lokasi sementara ke lokasi tujuan
    if (move_uploaded_file($fileArray["tmp_name"], $target_file)) {
        // Sukses! Kembalikan path yang akan disimpan di database (relatif dari root web)
        return $target_dir_relative . $new_file_name;
    } else {
        error_log("Failed to move uploaded file to: " . $target_file);
        return false; // Gagal memindahkan file
    }
}

?>