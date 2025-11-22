<?php
// Selalu mulai session di awal halaman yang membutuhkannya
session_start();

require_once 'config/database.php';
// Asumsikan 'functions/helpers.php' berisi fungsi verifyPassword()
require_once 'functions/helpers.php'; 

// --- OPTIMASI PATH GAMBAR & BASE URL (untuk konsistensi navigasi) ---
$base_url = rtrim(str_replace('\\','/', dirname($_SERVER['SCRIPT_NAME'])), '/');
if ($base_url === '' || $base_url === '.') {
    $base_url = '';
} else {
    $base_url = $base_url . '/';
}

$message = '';
// Ambil tujuan redirect, default ke index.php
$redirect_to = isset($_GET['redirect']) ? htmlspecialchars($_GET['redirect']) : 'index.php'; 

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Pastikan koneksi database tersedia
    if (isset($conn)) {
        // Logika PHP Login
        $username_input = $conn->real_escape_string($_POST['username']);
        $password_input = $_POST['password'];

        // Ambil kembali nilai redirect yang mungkin disembunyikan di form
        $redirect_post = $_POST['redirect_to'] ?? 'index.php';
        $redirect_url = filter_var($redirect_post, FILTER_SANITIZE_URL);

        // Amankan dan jalankan query
        $stmt = $conn->prepare("SELECT id, username, password, role FROM users WHERE username = ?");
        $stmt->bind_param("s", $username_input);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            $hashed_password_db = $user['password'];
            
            // Verifikasi password (asumsi fungsi verifyPassword() ada di helpers.php)
            if (verifyPassword($password_input, $hashed_password_db)) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role']; 
                
                // Tentukan halaman pengalihan
                if ($_SESSION['role'] === 'admin') {
                    header("Location: {$base_url}admin/dashboard.php");
                } else {
                    // Redirect ke halaman yang diminta sebelumnya
                    header("Location: {$base_url}{$redirect_url}");
                }
                exit();
            } else {
                $message = "Username atau Password salah.";
            }
        } else {
            $message = "Username atau Password salah.";
        }

        $stmt->close();
        $conn->close();
    } else {
        $message = "Kesalahan koneksi database.";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Coffee Store</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        :root {
            --primary-color: #6F4E37; /* Coklat Kopi */
        }
        .navbar { 
            background-color: var(--primary-color) !important; 
        }
        .login-wrapper {
            /* Kunci penengahan vertikal dan horizontal menggunakan Flexbox Bootstrap */
            display: flex;
            justify-content: center; /* Tengah horizontal */
            align-items: center; /* Tengah vertikal */
            /* Pastikan wrapper mengambil ketinggian penuh dari viewport */
            min-height: calc(100vh - 100px); 
            padding: 20px 0;
        }
        .login-card {
            max-width: 450px; /* Batasi lebar card */
            width: 100%;
        }
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        .btn-primary:hover {
            background-color: #553A2D;
            border-color: #553A2D;
        }
    </style>
</head>
<body>
    <header>
        <nav class="navbar navbar-expand-lg navbar-dark shadow-sm" style="background-color: var(--primary-color);">
            <div class="container">
                <a class="navbar-brand text-white fw-bold h4 mb-0" href="<?= $base_url; ?>index.php">☕ Coffee Store</a>
                <div class="collapse navbar-collapse" id="mainNav">
                    <ul class="navbar-nav ms-auto">
                        <li class="nav-item"><a class="nav-link" href="<?= $base_url; ?>index.php">Home</a></li>
                        <li class="nav-item"><a class="nav-link" href="<?= $base_url; ?>products.php">Produk</a></li>
                        <li class="nav-item"><a class="nav-link" href="<?= $base_url; ?>cart.php">Keranjang</a></li>
                        <li class="nav-item"><a class="nav-link" href="<?= $base_url; ?>register.php">Daftar</a></li>
                    </ul>
                </div>
            </div>
        </nav>
    </header>

    <div class="login-wrapper">
        <div class="container">
            <div class="card login-card shadow-lg mx-auto"> <div class="card-body p-5">
                    <h2 class="card-title text-center mb-4 fw-bold">Masuk ke Akun Anda</h2>
                    <hr class="mb-4">
                    
                    <?php if ($message): ?>
                        <div class="alert alert-danger text-center" role="alert">
                            <i class="bi bi-exclamation-triangle-fill"></i> <?php echo $message; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (isset($_GET['registered'])): ?>
                        <div class="alert alert-success text-center" role="alert">
                            <i class="bi bi-check-circle-fill"></i> Pendaftaran berhasil! Silakan masuk.
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="<?= $base_url; ?>login.php">
                        <input type="hidden" name="redirect_to" value="<?= $redirect_to; ?>">
                        
                        <div class="mb-3">
                            <label for="username" class="form-label fw-bold">Username:</label>
                            <input type="text" id="username" name="username" class="form-control" required autocomplete="username">
                        </div>
                        
                        <div class="mb-4">
                            <label for="password" class="form-label fw-bold">Password:</label>
                            <input type="password" id="password" name="password" class="form-control" required autocomplete="current-password">
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="bi bi-box-arrow-in-right"></i> Login
                            </button>
                        </div>
                    </form>
                    
                    <p class="text-center mt-4">
                        Belum punya akun? <a href="<?= $base_url; ?>register.php" class="text-decoration-none fw-bold">Daftar sekarang</a>
                    </p>
                </div>
            </div>
        </div>
    </div>
    
    <footer class="text-center py-3 mt-5 border-top">
        <p class="mb-0">&copy; 2024 Coffee Store. Powered by PHP Native.</p>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>