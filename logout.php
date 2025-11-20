<?php
// 1. Selalu mulai session terlebih dahulu
session_start();

// 2. Hapus semua variabel session
$_SESSION = array();

// 3. Jika menggunakan cookies session, hapus juga cookie session.
// Catatan: Ini akan menghancurkan session, bukan hanya variabel session.
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 4. Hancurkan session
session_destroy();

// 5. Redirect user kembali ke halaman login atau halaman utama
header("Location: login.php");
exit;
?>