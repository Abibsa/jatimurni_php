<?php
require_once '../config/database.php';
session_start();

// Cek login
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error'] = 'Silakan login terlebih dahulu';
    header('Location: ../login.php');
    exit;
}

// Cek parameter ID
if (!isset($_GET['id'])) {
    header('Location: ../index.php');
    exit;
}

$db = new Database();
$transactionId = $_GET['id'];

// Perbaikan fungsi format tanggal untuk MongoDB UTCDateTime
function formatTanggal($mongoDate) {
    if ($mongoDate instanceof MongoDB\BSON\UTCDateTime) {
        // Konversi MongoDB UTCDateTime ke DateTime PHP
        $dateTime = $mongoDate->toDateTime();
        
        // Set timezone ke Asia/Jakarta
        $dateTime->setTimezone(new DateTimeZone('Asia/Jakarta'));
        
        // Format tanggal ke format Indonesia
        return $dateTime->format('d/m/Y H:i:s');
    }
    
    // Fallback jika format tanggal tidak sesuai
    return 'Format tanggal tidak valid';
}

try {
    // Ambil data transaksi
    $transaction = $db->getCollection('transactions')->findOne(['_id' => $transactionId]);
    if (!$transaction) {
        throw new Exception('Data transaksi tidak ditemukan');
    }

    // Ambil data pembayaran
    $payment = $db->getCollection('payments')->findOne(['transactionId' => $transactionId]);
    if (!$payment) {
        throw new Exception('Data pembayaran tidak ditemukan');
    }

} catch (Exception $e) {
    $_SESSION['error'] = $e->getMessage();
    header('Location: ../index.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Konfirmasi Pesanan - Meubel Jati Murni</title>
    
    <!-- Font -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary: #ff0000;
            --bg: #010101;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--bg);
            color: #fff;
            line-height: 1.6;
        }

        .container {
            max-width: 800px;
            margin: 2rem auto;
            padding: 2rem;
        }

        .confirmation-box {
            background: rgba(255, 255, 255, 0.05);
            padding: 2rem;
            border-radius: 8px;
            margin-bottom: 2rem;
        }

        .success-icon {
            text-align: center;
            font-size: 4rem;
            color: #4CAF50;
            margin-bottom: 1rem;
        }

        .order-details {
            margin-top: 2rem;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .btn {
            display: inline-block;
            padding: 0.8rem 1.5rem;
            background-color: var(--primary);
            color: #fff;
            text-decoration: none;
            border-radius: 5px;
            margin-top: 1rem;
        }

        .btn:hover {
            background-color: #cc0000;
        }

        .payment-info {
            margin-top: 2rem;
            padding: 1rem;
            background: rgba(255, 255, 255, 0.08);
            border-radius: 5px;
        }

        /* Tambahkan style untuk status badge */
        .status-badge {
            display: inline-block;
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-weight: 500;
            text-transform: uppercase;
            font-size: 0.875rem;
            margin: 1rem 0;
        }

        .status-badge.pending {
            background: rgba(255, 193, 7, 0.1);
            color: #ffc107;
        }

        .status-badge.processing {
            background: rgba(33, 150, 243, 0.1);
            color: #2196f3;
        }

        .status-badge.completed {
            background: rgba(76, 175, 80, 0.1);
            color: #4caf50;
        }

        /* Style untuk daftar produk */
        .products-list {
            margin: 1.5rem 0;
            padding: 1rem;
            background: rgba(255, 255, 255, 0.03);
            border-radius: 8px;
        }

        .product-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .product-item:last-child {
            border-bottom: none;
        }

        .product-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .product-name {
            font-weight: 500;
        }

        .product-quantity {
            color: rgba(255, 255, 255, 0.7);
        }

        /* Style untuk ringkasan pembayaran */
        .payment-summary {
            margin: 1.5rem 0;
            padding: 1.5rem;
            background: rgba(255, 255, 255, 0.03);
            border-radius: 8px;
        }

        .payment-summary h4 {
            margin-bottom: 1rem;
            color: rgba(255, 255, 255, 0.9);
            font-size: 1.1rem;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 0.75rem 0;
            color: rgba(255, 255, 255, 0.8);
        }

        .summary-row:not(:last-child) {
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .summary-row.total {
            border-top: 2px solid rgba(255, 255, 255, 0.1);
            margin-top: 0.5rem;
            padding-top: 1rem;
            font-weight: 600;
            font-size: 1.1rem;
            color: #fff;
        }

        /* Style untuk informasi pengiriman */
        .shipping-info {
            margin: 1.5rem 0;
            padding: 1.5rem;
            background: rgba(255, 255, 255, 0.03);
            border-radius: 8px;
        }

        .shipping-info h4 {
            margin-bottom: 1rem;
            color: rgba(255, 255, 255, 0.9);
            font-size: 1.1rem;
        }

        .shipping-info .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 0.75rem 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .shipping-info .detail-row:last-child {
            border-bottom: none;
        }

        .shipping-info .detail-row span:first-child {
            color: rgba(255, 255, 255, 0.7);
        }

        .shipping-info .detail-row span:last-child {
            color: #fff;
            text-align: right;
            max-width: 60%;
        }

        /* Style untuk tombol aksi */
        .action-buttons {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.8rem 1.5rem;
            border-radius: 8px;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: #cc0000;
            transform: translateY(-2px);
        }

        .btn-secondary {
            background: rgba(255, 255, 255, 0.1);
            color: white;
        }

        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateY(-2px);
        }

        /* Animasi untuk success icon */
        .success-icon {
            animation: scaleIn 0.5s ease-out;
        }

        @keyframes scaleIn {
            0% {
                transform: scale(0);
                opacity: 0;
            }
            60% {
                transform: scale(1.2);
            }
            100% {
                transform: scale(1);
                opacity: 1;
            }
        }

        /* Responsive design */
        @media (max-width: 768px) {
            .action-buttons {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="confirmation-box">
            <div class="success-icon">✓</div>
            <h2 style="text-align: center;">Pesanan Berhasil Dibuat!</h2>
            
            <div class="order-details">
                <h3>Detail Pesanan</h3>
                
                <!-- Tambahkan bagian status dengan badge -->
                <div class="status-badge <?php echo strtolower($transaction['status']); ?>">
                    <?php echo htmlspecialchars($transaction['status']); ?>
                </div>
                
                <div class="detail-row">
                    <span>ID Transaksi:</span>
                    <span><?php echo htmlspecialchars($transaction['_id']); ?></span>
                </div>
                <div class="detail-row">
                    <span>Tanggal Pesanan:</span>
                    <span><?php echo formatTanggal($transaction['orderDate']); ?></span>
                </div>
                
                <!-- Tambahkan detail produk -->
                <div class="products-list">
                    <h4>Produk yang Dipesan</h4>
                    <?php foreach ($transaction['products'] as $product): ?>
                        <div class="product-item">
                            <div class="product-info">
                                <span class="product-name"><?php echo htmlspecialchars($product['name']); ?></span>
                                <span class="product-quantity">x<?php echo $product['quantity']; ?></span>
                            </div>
                            <span class="product-price">
                                Rp <?php echo number_format($product['price'] * $product['quantity'], 0, ',', '.'); ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Tambahkan ringkasan pembayaran -->
                <div class="payment-summary">
                    <h4>Ringkasan Pembayaran</h4>
                    <div class="summary-row">
                        <span>Subtotal Produk:</span>
                        <span>Rp <?php echo number_format($transaction['subtotal'], 0, ',', '.'); ?></span>
                    </div>
                    <div class="summary-row">
                        <span>Ongkos Kirim (<?php echo ucfirst($transaction['shippingCity']); ?>):</span>
                        <span>Rp <?php echo number_format($transaction['shippingCost'], 0, ',', '.'); ?></span>
                    </div>
                    <div class="summary-row total">
                        <span>Total Pembayaran:</span>
                        <span>Rp <?php echo number_format($transaction['totalAmount'], 0, ',', '.'); ?></span>
                    </div>
                </div>
                
                <!-- Tambahkan informasi pengiriman -->
                <div class="shipping-info">
                    <h4>Informasi Pengiriman</h4>
                    <div class="detail-row">
                        <span>Kota Tujuan:</span>
                        <span><?php echo ucfirst($transaction['shippingCity']); ?></span>
                    </div>
                    <div class="detail-row">
                        <span>Alamat Lengkap:</span>
                        <span><?php echo nl2br(htmlspecialchars($transaction['shippingAddress'])); ?></span>
                    </div>
                </div>
                
                <!-- Tambahkan tombol aksi -->
                <div class="action-buttons">
                    <a href="../customer/index.php" class="btn btn-primary">
                        <i class="fas fa-home"></i> Kembali ke Beranda
                    </a>
                    
                </div>
            </div>
        </div>
    </div>
</body>
</html> 