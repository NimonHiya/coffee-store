<?php
// Selalu mulai session di awal halaman yang membutuhkannya
session_start();

require_once 'config/database.php';
// Asumsikan 'functions/helpers.php' berisi fungsi hashPassword()
require_once 'functions/helpers.php';

// --- OPTIMASI PATH GAMBAR & BASE URL (untuk konsistensi navigasi) ---
$base_url = rtrim(str_replace('\\','/', dirname($_SERVER['SCRIPT_NAME'])), '/');
if ($base_url === '' || $base_url === '.') {
    $base_url = '';
} else {
    $base_url = $base_url . '/';
}

$message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Pastikan koneksi database tersedia
    if (isset($conn)) {
        // Sanitize input
        $username = trim($conn->real_escape_string($_POST['username']));
        $email = trim($conn->real_escape_string($_POST['email']));
        $password = $_POST['password']; // Password mentah
        
        // 1. Validasi input sederhana
        if (empty($username) || empty($email) || empty($password)) {
            $message = "Semua field harus diisi!";
        } 
        // 1b. Validasi Email Format (Opsional tapi disarankan)
        elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = "Format email tidak valid.";
        }
        else {
            // 2. Hash password (Asumsi fungsi hashPassword() ada di helpers.php)
            $hashed_password = hashPassword($password);
            $role = 'user'; // Default role selalu 'user' untuk pendaftaran publik

            // 3. Siapkan query INSERT menggunakan Prepared Statements
            $stmt = $conn->prepare("INSERT INTO users (username, password, email, role) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $username, $hashed_password, $email, $role);
            
            // 4. Jalankan query
            if ($stmt->execute()) {
                // Pendaftaran berhasil, redirect ke halaman login dengan pesan sukses
                header("Location: {$base_url}login.php?registered=true");
                exit();
            } else {
                // Cek jika error karena username/email sudah terdaftar (UNIQUE constraint)
                if ($conn->errno == 1062) { 
                    $message = "Username atau Email sudah terdaftar.";
                } else {
                    $message = "Error saat pendaftaran. Silakan coba lagi.";
                    // Detail error yang lebih teknis: "Error saat pendaftaran: " . $stmt->error;
                }
            }
            
            $stmt->close();
        }
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
    <title>Daftar Akun Baru | Coffee Store</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        :root {
            --primary-color: #6F4E37; /* Coklat Kopi */
        }
        .navbar { background-color: var(--primary-color) !important; }
        .register-wrapper {
            /* Kunci penengahan vertikal dan horizontal menggunakan Flexbox Bootstrap */
            display: flex;
            justify-content: center; /* Tengah horizontal */
            align-items: center; /* Tengah vertikal */
            min-height: calc(100vh - 100px); /* Ketinggian min layar dikurangi header/footer */
            padding: 20px 0;
        }
        .register-card {
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
                        <li class="nav-item"><a class="nav-link" href="<?= $base_url; ?>login.php">Login</a></li>
                    </ul>
                </div>
            </div>
        </nav>
    </header>

    <div class="register-wrapper">
        <div class="container">
            <div class="card register-card shadow-lg mx-auto">
                <div class="card-body p-5">
                    <h2 class="card-title text-center mb-4 fw-bold">Daftar Akun Baru</h2>
                    <hr class="mb-4">
                    
                    <?php if ($message): ?>
                        <div class="alert alert-danger text-center" role="alert">
                            <i class="bi bi-exclamation-triangle-fill"></i> <?php echo $message; ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="<?= $base_url; ?>register.php">
                        
                        <div class="mb-3">
                            <label for="username" class="form-label fw-bold">Username:</label>
                            <input type="text" id="username" name="username" class="form-control" required autocomplete="username" value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label for="email" class="form-label fw-bold">Email:</label>
                            <input type="email" id="email" name="email" class="form-control" required autocomplete="email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                        </div>

                        <div class="mb-4">
                            <label for="password" class="form-label fw-bold">Password:</label>
                            <input type="password" id="password" name="password" class="form-control" required autocomplete="new-password">
                            <div class="form-text">Password harus kuat dan unik.</div>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="bi bi-person-plus-fill"></i> Daftar Akun
                            </button>
                        </div>
                    </form>
                    
                    <p class="text-center mt-4">
                        Sudah punya akun? <a href="<?= $base_url; ?>login.php" class="text-decoration-none fw-bold">Login di sini</a>
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