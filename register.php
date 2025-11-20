<?php
// Selalu mulai session di awal halaman yang membutuhkannya
session_start(); // Tambahkan session_start() di sini

require_once 'config/database.php';
require_once 'functions/helpers.php';
// require_once 'includes/header.php'; // Nantinya kita akan gunakan ini

$message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $conn->real_escape_string($_POST['username']);
    $email = $conn->real_escape_string($_POST['email']);
    $password = $_POST['password']; // Password mentah
    
    // 1. Validasi input sederhana
    if (empty($username) || empty($email) || empty($password)) {
        $message = "Semua field harus diisi!";
    } else {
        // 2. Hash password
        $hashed_password = hashPassword($password);
        $role = 'user'; // Default role selalu 'user' untuk pendaftaran publik

        // 3. Siapkan query INSERT
        $stmt = $conn->prepare("INSERT INTO users (username, password, email, role) VALUES (?, ?, ?, ?)");
        // 's' menandakan string, kita punya 4 parameter string
        $stmt->bind_param("ssss", $username, $hashed_password, $email, $role);
        
        // 4. Jalankan query
        if ($stmt->execute()) {
            // Pendaftaran berhasil, redirect ke halaman login
            header("Location: login.php?registered=true");
            exit();
        } else {
            // Cek jika error karena username/email sudah terdaftar (UNIQUE constraint)
            if ($conn->errno == 1062) { 
                 $message = "Username atau Email sudah terdaftar.";
            } else {
                 $message = "Error saat pendaftaran: " . $stmt->error;
            }
        }
        
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Registrasi User</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        /* Gaya khusus untuk menengahkan formulir registrasi */
        .register-wrapper {
            display: flex;
            justify-content: center; /* Tengah horizontal */
            align-items: center; /* Tengah vertikal */
            min-height: calc(100vh - 120px); /* Memenuhi tinggi sisa layar */
            padding: 20px;
        }
        .register-wrapper .container {
            width: 100%;
            max-width: 450px; /* Batasi lebar container */
            margin: auto; 
        }
        .register-wrapper form {
            max-width: 400px; 
            width: 100%;
            margin: 0 auto; /* Menengahkan form di dalam container */
            padding: 30px;
        }
        .register-wrapper h2 {
            text-align: center; 
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <header>
        <nav>
            <h1>☕ Coffee Store</h1>
            <ul>
                <li><a href="index.php">Home</a></li>
                <li><a href="products.php">Produk</a></li>
                <li><a href="cart.php">Keranjang</a></li>
                <li><a href="login.php">Login</a></li>
            </ul>
        </nav>
    </header>

    <div class="register-wrapper">
        <div class="container">
            <h2>Daftar Akun Baru</h2>
            
            <?php if ($message): ?>
                <p style="color: red; font-weight: bold; margin-bottom: 15px; text-align: center;"><?php echo $message; ?></p>
            <?php endif; ?>

            <form method="POST" action="register.php">
                <label for="username">Username:</label>
                <input type="text" id="username" name="username" required>
                
                <label for="email">Email:</label>
                <input type="email" id="email" name="email" required>

                <label for="password">Password:</label>
                <input type="password" id="password" name="password" required>
                
                <button type="submit">Daftar</button>
            </form>
            <p style="text-align: center;">Sudah punya akun? <a href="login.php">Login di sini</a></p>
        </div>
    </div>
    
    <footer>
        <p>&copy; 2024 Coffee Store. Powered by PHP Native.</p>
    </footer>

</body>
</html>