<?php
// Selalu mulai session di awal halaman yang membutuhkannya
session_start();

require_once 'config/database.php';
require_once 'functions/helpers.php';

$message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // ... (Logika PHP Login Anda) ...
    $username_input = $conn->real_escape_string($_POST['username']);
    $password_input = $_POST['password'];

    $stmt = $conn->prepare("SELECT id, username, password, role FROM users WHERE username = ?");
    $stmt->bind_param("s", $username_input);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        $hashed_password_db = $user['password'];
        
        if (verifyPassword($password_input, $hashed_password_db)) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role']; 
            
            if ($_SESSION['role'] === 'admin') {
                header("Location: admin/dashboard.php");
                exit();
            } else {
                header("Location: index.php");
                exit();
            }
        } else {
            $message = "Username atau Password salah.";
        }
    } else {
        $message = "Username atau Password salah.";
    }

    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Login Coffee Store</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        /* Gaya khusus untuk menengahkan formulir login */
        .login-wrapper {
            display: flex;
            justify-content: center; /* Tengah horizontal */
            align-items: center; /* Tengah vertikal */
            min-height: calc(100vh - 120px); 
            padding: 20px;
        }
        .container {
            width: 100%;
            max-width: 450px; /* Batasi lebar container agar form terlihat baik */
            margin: auto; 
            /* Hapus text-align: center agar label form rata kiri */
        }
        .login-wrapper form {
            max-width: 400px; 
            width: 100%;
            margin: 0 auto; /* Menengahkan form di dalam container */
            padding: 30px;
        }
        .login-wrapper h2 {
            text-align: center; /* Menengahkan judul */
            margin-bottom: 20px;
        }
        /* Tambahan: Form input harus full width, ini sudah diatur di style.css */
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
                <li><a href="register.php">Daftar</a></li>
            </ul>
        </nav>
    </header>

    <div class="login-wrapper">
        <div class="container">
            <h2>Login ke Akun Anda</h2>
            
            <?php if ($message): ?>
                <p style="color: red; font-weight: bold; margin-bottom: 15px; text-align: center;"><?php echo $message; ?></p>
            <?php endif; ?>
            <?php if (isset($_GET['registered'])): ?>
                <p style="color: green; font-weight: bold; margin-bottom: 15px; text-align: center;">Pendaftaran berhasil! Silakan masuk.</p>
            <?php endif; ?>

            <form method="POST" action="login.php">
                <label for="username">Username:</label>
                <input type="text" id="username" name="username" required>
                
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" required>
                
                <button type="submit">Login</button>
            </form>
            <p style="text-align: center;">Belum punya akun? <a href="register.php">Daftar sekarang</a></p>
        </div>
    </div>
    
    <footer>
        <p>&copy; 2024 Coffee Store. Powered by PHP Native.</p>
    </footer>

</body>
</html>