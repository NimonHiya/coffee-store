<?php
session_start();
require_once 'config/database.php';
// Asumsikan 'functions/auth_check.php' berisi fungsi requireLogin()
require_once 'functions/auth_check.php';

// --- OPTIMASI PATH GAMBAR & BASE URL (untuk konsistensi navigasi) ---
$base_url = rtrim(str_replace('\\','/', dirname($_SERVER['SCRIPT_NAME'])), '/');
if ($base_url === '' || $base_url === '.') {
    $base_url = '';
} else {
    $base_url = $base_url . '/';
}

// PENTING: User harus login
requireLogin('user', 'login.php?redirect=order_detail.php&id=' . ($_GET['id'] ?? 0)); 

$user_id = $_SESSION['user_id'];
$order_id = (int)($_GET['id'] ?? 0);

if ($order_id === 0) {
    header("Location: {$base_url}user_orders.php");
    exit();
}

$order = false;
$items_result = false;

if (isset($conn)) {
    // 1. Ambil data pesanan utama, PASTIKAN HANYA MILIK USER INI
    $stmt_order = $conn->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ?");
    $stmt_order->bind_param("ii", $order_id, $user_id);
    $stmt_order->execute();
    $order = $stmt_order->get_result()->fetch_assoc();
    $stmt_order->close();

    if (!$order) {
        // Jika pesanan tidak ditemukan atau BUKAN milik user ini
        header("Location: {$base_url}user_orders.php?error=" . urlencode("Pesanan tidak ditemukan atau akses ditolak."));
        exit();
    }

    // 2. Ambil item-item pesanan
    $stmt_items = $conn->prepare("SELECT oi.*, p.name FROM order_items oi JOIN products p ON oi.product_id = p.id WHERE oi.order_id = ?");
    $stmt_items->bind_param("i", $order_id);
    $stmt_items->execute();
    $items_result = $stmt_items->get_result();
    
    // Koneksi akan ditutup di akhir file
} else {
    // Handle jika koneksi gagal
    $order = false;
    $message = "Kesalahan koneksi database.";
}

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Pesanan #<?php echo $order_id; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        :root {
            --primary-color: #6F4E37; /* Coklat Kopi */
        }
        .navbar { background-color: var(--primary-color) !important; }

        /* Gaya status yang sama dengan user_orders.php */
        .status-pending { background-color: #ffc107; color: #333; } /* Warning */
        .status-processing { background-color: #0d6efd; color: white; } /* Primary */
        .status-completed { background-color: #198754; color: white; } /* Success */
        .status-cancelled { background-color: #dc3545; color: white; } /* Danger */
    </style>
</head>
<body>
    <header>
        <nav class="navbar navbar-expand-lg navbar-dark shadow-sm" style="background-color: var(--primary-color);">
            <div class="container">
                <a class="navbar-brand text-white fw-bold h4 mb-0" href="<?= $base_url; ?>index.php">☕ Coffee Store</a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="mainNav">
                    <ul class="navbar-nav ms-auto">
                        <li class="nav-item"><a class="nav-link" href="<?= $base_url; ?>index.php">Home</a></li>
                        <li class="nav-item"><a class="nav-link" href="<?= $base_url; ?>products.php">Produk</a></li>
                        <li class="nav-item"><a class="nav-link" href="<?= $base_url; ?>cart.php">Keranjang</a></li>
                        <li class="nav-item"><a class="nav-link active" aria-current="page" href="<?= $base_url; ?>user_orders.php">Riwayat Pesanan</a></li> 
                        <li class="nav-item"><a class="nav-link" href="<?= $base_url; ?>logout.php">Logout (<?php echo htmlspecialchars($_SESSION['username']); ?>)</a></li>
                    </ul>
                </div>
            </div>
        </nav>
    </header>

    <main class="container my-5">
        
        <h3 class="mb-4">Detail Pesanan #<?php echo $order['id']; ?></h3>
        
        <a href="<?= $base_url; ?>user_orders.php" class="btn btn-sm btn-outline-secondary mb-4">
            <i class="bi bi-arrow-left"></i> Kembali ke Riwayat Pesanan
        </a>

        <div class="row mb-5">
            <div class="col-lg-6">
                <div class="card shadow-sm">
                    <div class="card-header fw-bold bg-light">
                        Informasi Pesanan
                    </div>
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Tanggal Pesan:
                            <span class="fw-bold"><?php echo date("d M Y H:i", strtotime($order['order_date'])); ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Status Saat Ini:
                            <?php 
                                $status_class = strtolower($order['order_status']);
                                $display_status = ucfirst($order['order_status']);
                            ?>
                            <span class="badge rounded-pill status-<?php echo $status_class; ?>">
                                <?php echo $display_status; ?>
                            </span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center bg-success text-white">
                            Total Pembayaran:
                            <span class="fw-bold fs-5">Rp <?php echo number_format($order['total_amount'], 0, ',', '.'); ?></span>
                        </li>
                    </ul>
                </div>
            </div>
            </div>

        <h4><i class="bi bi-list-task"></i> Item Pesanan</h4>
        <?php if ($items_result && $items_result->num_rows > 0): ?>
            <div class="table-responsive">
                <table class="table table-hover table-bordered align-middle">
                    <thead class="table-dark" style="background-color: var(--primary-color);">
                        <tr>
                            <th>Nama Produk</th>
                            <th>Harga Satuan</th>
                            <th>Jumlah</th>
                            <th>Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $subtotal_check = 0; while($item = $items_result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($item['name']); ?></td>
                            <td>Rp <?php echo number_format($item['price_at_order'], 0, ',', '.'); ?></td>
                            <td class="text-center"><?php echo $item['quantity']; ?></td>
                            <td class="fw-bold">Rp <?php echo number_format($item['price_at_order'] * $item['quantity'], 0, ',', '.'); ?></td>
                        </tr>
                        <?php $subtotal_check += $item['price_at_order'] * $item['quantity']; endwhile; ?>
                        <tr class="table-secondary">
                            <td colspan="3" class="text-end fw-bold">TOTAL ITEM</td>
                            <td class="fw-bold fs-6">Rp <?php echo number_format($subtotal_check, 0, ',', '.'); ?></td>
                        </tr>
                        <?php if ($subtotal_check != $order['total_amount']): ?>
                            <tr class="table-danger">
                                <td colspan="4" class="text-center">
                                    <i class="bi bi-exclamation-triangle-fill"></i> Peringatan: Total Item tidak sesuai dengan Total Pesanan ($order['total_amount'])!
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
             <div class="alert alert-warning">Tidak ada item yang ditemukan untuk pesanan ini.</div>
        <?php endif; ?>
    </main>
    
    <footer class="text-center py-3 mt-5 border-top">
        <p class="mb-0">&copy; 2024 Coffee Store. Powered by PHP Native.</p>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php
if (isset($conn)) {
    // Karena $stmt_items sudah ditutup jika dijalankan di PHP logic
    // Kita hanya perlu menutup koneksi
    $conn->close();
}
?>