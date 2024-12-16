<?php
require_once '../config/database.php';
session_start();

// Cek login
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error'] = 'Silakan login terlebih dahulu';
    header('Location: ../login.php');
    exit;
}

error_reporting(E_ALL);
ini_set('display_errors', 1);

$db = new Database();

function formatTanggalIndonesia() {
    $timezone = new DateTimeZone('Asia/Jakarta');
    $date = new DateTime('now', $timezone);
    return $date->format('Y-m-d H:i:s');
}

try {
    // Ambil data user dari MongoDB
    $userId = $_SESSION['user_id'];
    $user = $db->getCollection('users')->findOne(['_id' => $userId]);
    
    if (!$user) {
        throw new Exception('Data user tidak ditemukan');
    }
    
    // Auto-fill form data
    $userData = [
        'fullName' => $user['name'] ?? '',
        'email' => $user['email'] ?? '',
        'phone' => $user['phone'] ?? '',
        'address' => $user['address'] ?? ''
    ];
    
    // Ambil data cart dari session jika ada
    $cartItems = isset($_SESSION['cart_items']) ? $_SESSION['cart_items'] : [];
    
    // Generate transaction ID untuk form
    $lastTransaction = $db->getCollection('transactions')->findOne(
        [], 
        ['sort' => ['_id' => -1]]
    );
    
    $transactionId = 't001';
    if ($lastTransaction) {
        $lastId = (int) substr($lastTransaction['_id'], 1);
        $transactionId = 't' . str_pad($lastId + 1, 3, '0', STR_PAD_LEFT);
    }
    
    $lastPayment = $db->getCollection('payments')->findOne(
        [], 
        ['sort' => ['_id' => -1]]
    );
    
    $paymentId = 'p001';
    if ($lastPayment) {
        $lastId = (int) substr($lastPayment['_id'], 1);
        $paymentId = 'p' . str_pad($lastId + 1, 3, '0', STR_PAD_LEFT);
    }
    
} catch (Exception $e) {
    error_log('Error in checkout.php: ' . $e->getMessage());
    $_SESSION['error'] = 'Terjadi kesalahan, silakan login kembali';
    header('Location: ../login.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - Meubel Jati Murni</title>
    
    <!-- Font -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
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

        /* Adjust main container spacing */
        .checkout-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 1rem 2rem;
            display: grid;
            grid-template-columns: 60% 40%;
            gap: 2rem;
        }

        /* Adjust back button spacing */
        .back-nav {
            grid-column: 1 / -1;
            margin-bottom: 1rem;
        }

        /* Move form section up */
        .checkout-form-section {
            padding-right: 2rem;
            margin-top: -4rem;
        }

        /* Move summary section up */
        .order-summary {
            position: sticky;
            top: 1rem;
            height: fit-content;
            padding: 1.5rem;
            border-radius: 15px;
            background: rgba(255, 255, 255, 0.05);
            margin-top: -8rem;
        }

        /* Responsive adjustments */
        @media (max-width: 992px) {
            .checkout-container {
                grid-template-columns: 1fr;
                padding: 1rem;
            }

            .checkout-form-section {
                padding-right: 0;
            }

            .order-summary {
                position: relative;
                top: 0;
            }
        }

        /* Adjust form section spacing */
        .form-section {
            background: rgba(255, 255, 255, 0.05);
            padding: 1.5rem;
            border-radius: 15px;
            margin-bottom: 1rem;
        }

        /* Adjust heading spacing */
        h3 {
            margin-bottom: 1rem;
            font-size: 1.25rem;
        }

        /* Adjust form group spacing */
        .form-group {
            margin-bottom: 1rem;
        }

        /* Cart items styling */
        .cart-items {
            max-height: 400px;
            overflow-y: auto;
            padding-right: 1rem;
            margin: 1.5rem 0;
        }

        .cart-items::-webkit-scrollbar {
            width: 6px;
        }

        .cart-items::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.05);
        }

        .cart-items::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.2);
            border-radius: 3px;
        }

        /* Spacing dan alignment */
        .summary-row {
            padding: 1rem 0;
        }

        /* Back button positioning */
        .back-button {
            margin: 0;
            display: inline-flex;
        }

        .cart-item {
            display: grid;
            grid-template-columns: auto 1fr;
            gap: 1rem;
            padding: 1rem;
            background: rgba(255, 255, 255, 0.03);
            border-radius: 8px;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
        }

        .cart-item:hover {
            background: rgba(255, 255, 255, 0.05);
        }

        .cart-item img {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 8px;
        }

        /* Penyesuaian spacing untuk item details */
        .item-details {
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .item-header {
            margin-bottom: 0.5rem;
        }

        .item-header h4 {
            font-size: 1.1rem;
            font-weight: 500;
            margin-bottom: 0.25rem;
        }

        /* Penyesuaian controls wrapper */
        .controls-wrapper {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: auto;
        }

        /* Update quantity controls */
        .quantity-controls {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            background: rgba(255, 255, 255, 0.05);
            padding: 0.25rem;
            border-radius: 6px;
        }

        .qty-btn {
            width: 28px;
            height: 28px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--primary);
            border: none;
            border-radius: 4px;
            color: white;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .qty-btn:hover {
            background: #cc0000;
            transform: scale(1.05);
        }

        .qty-btn:active {
            transform: scale(0.95);
        }

        .quantity-display {
            min-width: 30px;
            text-align: center;
            font-weight: 500;
        }

        /* Update delete button */
        .delete-btn {
            background: rgba(255, 0, 0, 0.1);
            border: none;
            color: var(--primary);
            width: 36px;
            height: 36px;
            border-radius: 50%;
            cursor: pointer;
            position: relative;
            overflow: hidden;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .delete-btn i {
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        /* Efek hover dengan warna putih */
        .delete-btn:hover {
            background: rgba(255, 255, 255, 0.9);
            color: var(--primary);
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(255, 255, 255, 0.2);
        }

        /* Efek active/click dengan warna putih yang lebih gelap */
        .delete-btn:active {
            background: rgba(255, 255, 255, 0.7);
            transform: translateY(0);
            box-shadow: 0 2px 8px rgba(255, 255, 255, 0.1);
        }

        /* Efek ripple yang lebih kontras */
        .delete-btn::after {
            content: '';
            position: absolute;
            width: 100%;
            height: 100%;
            top: 0;
            left: 0;
            pointer-events: none;
            background-image: radial-gradient(circle, rgba(255, 0, 0, 0.2) 10%, transparent 10.01%);
            background-repeat: no-repeat;
            background-position: 50%;
            transform: scale(10, 10);
            opacity: 0;
            transition: transform 0.5s, opacity 1s;
        }

        .delete-btn:active::after {
            transform: scale(0, 0);
            opacity: 0.3;
            transition: 0s;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            margin: 1rem 0;
            color: #fff;
        }

        .total-row {
            font-size: 1.2rem;
            font-weight: 600;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            padding-top: 1rem;
            margin-top: 1rem;
        }

        .btn-checkout {
            width: 100%;
            padding: 1rem;
            background: var(--primary);
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            position: relative;
            overflow: hidden;
        }

        .btn-checkout:hover {
            background: #cc0000;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(255, 0, 0, 0.2);
        }

        .btn-checkout:active {
            transform: translateY(0);
        }

        .btn-checkout:disabled {
            background: #666;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .spinner {
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 1s ease-in-out infinite;
            margin-right: 8px;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Efek ripple saat diklik */
        .btn-checkout::after {
            content: '';
            position: absolute;
            width: 100%;
            height: 100%;
            top: 0;
            left: 0;
            pointer-events: none;
            background-image: radial-gradient(circle, #fff 10%, transparent 10.01%);
            background-repeat: no-repeat;
            background-position: 50%;
            transform: scale(10, 10);
            opacity: 0;
            transition: transform .5s, opacity 1s;
        }

        .btn-checkout:active::after {
            transform: scale(0, 0);
            opacity: .3;
            transition: 0s;
        }

        .order-form {
            background: rgba(255, 255, 255, 0.05);
            padding: 2rem;
            border-radius: 8px;
            margin-bottom: 2rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #fff;
        }

        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 0.8rem;
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 4px;
            background: rgba(255, 255, 255, 0.05);
            color: #fff;
            font-family: 'Poppins', sans-serif;
        }

        .form-group textarea {
            height: 100px;
            resize: vertical;
        }

        .form-group select option {
            background: #1a1a1a;
            color: #fff;
        }

        /* Tambahkan style untuk tombol kembali */
        .back-button {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 0.8rem 1.5rem;
            background: rgba(255, 255, 255, 0.1);
            color: #fff;
            border: none;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
            margin-bottom: 2rem;
        }

        .back-button:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateX(-5px);
        }

        .back-button i {
            font-size: 1.2rem;
        }

        .quantity-display {
            min-width: 30px;
            text-align: center;
        }

        .quantity-controls {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-top: 0.5rem;
        }

        .qty-btn {
            background: var(--primary);
            color: white;
            border: none;
            width: 25px;
            height: 25px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            transition: background-color 0.3s;
        }

        .qty-btn:hover {
            background: #cc0000;
        }

        .quantity-display {
            min-width: 30px;
            text-align: center;
        }

        /* Style untuk item details */
        .item-details {
            flex-grow: 1;
            padding: 0 1rem;
        }

        .item-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 0.5rem;
        }

        .item-header h4 {
            margin: 0;
            font-size: 1rem;
            color: #fff;
        }

        .item-price {
            color: #fff;
            font-weight: 500;
        }

        /* Style untuk controls wrapper */
        .controls-wrapper {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        /* Style untuk delete button */
        .delete-btn {
            background: none;
            border: none;
            color: #ff0000;
            cursor: pointer;
            padding: 0.5rem;
            transition: color 0.3s;
        }

        .delete-btn:hover {
            color: #cc0000;
        }

        /* Style untuk form input yang belum ada */
        .form-group input {
            width: 100%;
            padding: 0.8rem;
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 4px;
            background: rgba(255, 255, 255, 0.05);
            color: #fff;
            font-family: 'Poppins', sans-serif;
        }

        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--primary);
        }

        /* Style untuk disabled state */
        input:disabled,
        textarea:disabled,
        select:disabled {
            opacity: 0.7;
            cursor: not-allowed;
        }

        /* Style untuk error state */
        .form-group.error input,
        .form-group.error textarea,
        .form-group.error select {
            border-color: #ff0000;
        }

        .error-message {
            color: #ff0000;
            font-size: 0.875rem;
            margin-top: 0.25rem;
        }

        /* Style untuk informasi stok */
        .stock-info {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin: 0.5rem 0;
            font-size: 0.9rem;
            color: rgba(255, 255, 255, 0.7);
        }

        .stock-label {
            color: rgba(255, 255, 255, 0.5);
        }

        .stock-value {
            background: rgba(255, 255, 255, 0.1);
            padding: 0.2rem 0.5rem;
            border-radius: 4px;
            font-weight: 500;
        }

        /* Style untuk dialog konfirmasi SweetAlert2 yang lebih modern */
        .swal2-popup {
            background: #1a1a1a !important;
            border: 1px solid rgba(255, 255, 255, 0.1) !important;
            border-radius: 16px !important;
        }

        .swal2-title {
            color: #fff !important;
            font-size: 1.5rem !important;
        }

        .swal2-html-container {
            color: rgba(255, 255, 255, 0.8) !important;
        }

        .swal2-confirm {
            background: var(--primary) !important;
            border-radius: 8px !important;
            padding: 12px 24px !important;
            font-weight: 600 !important;
            transition: all 0.3s ease !important;
        }

        .swal2-confirm:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 4px 12px rgba(255, 0, 0, 0.2) !important;
        }

        .swal2-cancel {
            background: rgba(255, 255, 255, 0.1) !important;
            border-radius: 8px !important;
            padding: 12px 24px !important;
            color: #fff !important;
            font-weight: 600 !important;
            transition: all 0.3s ease !important;
        }

        .swal2-cancel:hover {
            background: rgba(255, 255, 255, 0.2) !important;
            transform: translateY(-2px) !important;
        }

        /* Style untuk select kota pengiriman */
        .form-group select#shippingCity {
            width: 100%;
            padding: 0.8rem;
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 4px;
            background: rgba(255, 255, 255, 0.05);
            color: #fff;
            font-family: 'Poppins', sans-serif;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .form-group select#shippingCity:hover {
            border-color: rgba(255, 255, 255, 0.2);
        }

        .form-group select#shippingCity:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 2px rgba(255, 0, 0, 0.1);
        }

        .form-group select#shippingCity option {
            background: #1a1a1a;
            color: #fff;
            padding: 0.8rem;
        }

        /* Styling untuk option yang dipilih */
        .form-group select#shippingCity option:checked {
            background: var(--primary);
            color: #fff;
        }

        /* Hover effect untuk options */
        .form-group select#shippingCity option:hover {
            background: rgba(255, 0, 0, 0.1);
        }
    </style>
</head>
<body>
    <div class="checkout-container">
        <!-- Back navigation -->
        <div class="back-nav">
            <a href="../customer/index.php" class="back-button">
                <i class="fas fa-arrow-left"></i>
                Kembali ke Beranda
            </a>
        </div>

        <!-- Form section -->
        <div class="checkout-form-section">
            <form id="checkoutForm" class="form-section">
                <h3>Detail Pengiriman</h3>
                <div class="form-group">
                    <label for="shippingAddress">Alamat Pengiriman</label>
                    <textarea name="shippingAddress" id="shippingAddress" required><?php echo htmlspecialchars($userData['address']); ?></textarea>
                </div>

                <div class="form-group">
                    <label for="shippingCity">Kota Pengiriman</label>
                    <select name="shippingCity" id="shippingCity" required onchange="updateShippingCost()">
                        <option value="">Pilih Kota</option>
                        <option value="jepara" data-cost="0">Jepara (Gratis)</option>
                        <option value="kudus" data-cost="50000">Kudus (Rp 50.000)</option>
                        <option value="pati" data-cost="75000">Pati (Rp 75.000)</option>
                        <option value="semarang" data-cost="100000">Semarang (Rp 100.000)</option>
                        <option value="jogja" data-cost="150000">Yogyakarta (Rp 150.000)</option>
                        <option value="solo" data-cost="175000">Solo (Rp 175.000)</option>
                        <option value="surabaya" data-cost="200000">Surabaya (Rp 200.000)</option>
                    </select>
                </div>

                <h3>Metode Pembayaran</h3>
                <div class="form-group">
                    <select name="paymentMethod" id="paymentMethod" required>
                        <option value="transfer">Transfer Bank</option>
                        <option value="cash">Cash on Delivery</option>
                    </select>
                </div>

                <input type="hidden" name="userId" value="<?php echo $_SESSION['user_id']; ?>">
                <input type="hidden" name="transactionId" value="<?php echo $transactionId; ?>">
                <input type="hidden" name="paymentId" value="<?php echo $paymentId; ?>">
                <input type="hidden" name="orderDate" value="<?php echo formatTanggalIndonesia(); ?>">
                <input type="hidden" name="paymentDate" value="<?php echo formatTanggalIndonesia(); ?>">
                <input type="hidden" name="subtotal" id="subtotal">
                <input type="hidden" name="shipping_cost" value="50000">
                <input type="hidden" name="total_amount" id="total_amount">
                <input type="hidden" name="status" value="processing">
                <input type="hidden" name="paymentStatus" value="pending">
            </form>
        </div>

        <!-- Summary section -->
        <div class="order-summary">
            <h3>Ringkasan Pesanan</h3>
            <div class="cart-items" id="cartItems">
                <!-- Items akan dimasukkan via JavaScript -->
            </div>
            
            <div class="summary-row">
                <span>Subtotal</span>
                <span id="subtotal-display">Rp 0</span>
            </div>
            
            <div class="summary-row">
                <span>Ongkos Kirim</span>
                <span id="shipping">Rp 50.000</span>
            </div>
            
            <div class="summary-row total-row">
                <span>Total</span>
                <span id="total">Rp 0</span>
            </div>
            
            <button class="btn-checkout" onclick="processCheckout()">
                Proses Pembayaran
            </button>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        // Deklarasikan variabel di scope global
        let cartItems = [];
        const shippingCost = 50000;

        // Fungsi format currency di scope global
        function formatCurrency(amount) {
            return new Intl.NumberFormat('id-ID', {
                style: 'currency',
                currency: 'IDR',
                minimumFractionDigits: 0,
                maximumFractionDigits: 0
            }).format(amount);
        }

        // Fungsi helper untuk memformat Product ID
        function formatProductId(id) {
            // Hapus prefix 'p' jika ada
            const numericId = id.replace(/^p/, '');
            // Pad dengan 0 di depan hingga 3 digit
            const paddedId = numericId.padStart(3, '0');
            // Tambahkan prefix 'p' kembali
            return 'p' + paddedId;
        }

        // Fungsi untuk debugging
        function renderCartItems() {
            const cartItemsContainer = document.getElementById('cartItems');
            cartItemsContainer.innerHTML = '';
            
            cartItems.forEach((item, index) => {
                const itemPrice = parseInt(item.price.replace(/\D/g, ''));
                const itemQuantity = item.qty || item.quantity || 1;
                const itemTotal = itemPrice * itemQuantity;
                
                // Tambahkan pengecekan untuk stok
                const stockDisplay = item.stock !== undefined ? item.stock : 'Memuat...';
                
                cartItemsContainer.innerHTML += `
                    <div class="cart-item">
                        <img src="${item.image}" alt="${item.name}">
                        <div class="item-details">
                            <div class="item-header">
                                <h4>${item.name}</h4>
                                <div class="item-price">${formatCurrency(itemPrice)}</div>
                            </div>
                            <div class="stock-info">
                                <span class="stock-label">Stok:</span>
                                <span class="stock-value">${stockDisplay}</span>
                            </div>
                            <div class="controls-wrapper">
                                <div class="quantity-controls">
                                    <button type="button" onclick="updateQuantity(${index}, -1)" class="qty-btn">
                                        <i class="fas fa-minus"></i>
                                    </button>
                                    <span class="quantity-display">${itemQuantity}</span>
                                    <button type="button" onclick="updateQuantity(${index}, 1)" class="qty-btn">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                </div>
                                <button type="button" onclick="removeItem(${index})" class="delete-btn">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                `;
            });
        }

        // Update fungsi removeItem dengan animasi yang lebih menarik
        function removeItem(index) {
            Swal.fire({
                title: 'Hapus Item?',
                text: "Item akan dihapus dari keranjang",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ff0000',
                cancelButtonColor: '#333333',
                confirmButtonText: 'Ya, hapus!',
                cancelButtonText: 'Batal',
                background: '#1a1a1a',
                color: '#ffffff',
                backdrop: `
                    rgba(0,0,0,0.8)
                    url("data:image/svg+xml,%3Csvg width='80' height='80' viewBox='0 0 24 24' xmlns='http://www.w3.org/2000/svg'%3E%3Cstyle%3E.spinner_P7sC%7Banimation:spinner_svv2 .8s linear infinite;animation-delay:-0.8s%7D.spinner_Km9P%7Banimation-delay:-0.65s%7D.spinner_c7oB%7Banimation-delay:-0.5s%7D@keyframes spinner_svv2%7B0%25,66.66%25%7Banimation-timing-function:cubic-bezier(0.4,0,0.2,1);y:13px;height:8px%7D33.33%25%7Banimation-timing-function:cubic-bezier(0.8,0,0.6,1);y:10px;height:14px%7D%7D%3C/style%3E%3Cpath d='M12 3V21' stroke='%23ff0000' stroke-linecap='round' stroke-width='2' class='spinner_P7sC'/%3E%3Cpath d='M16.24 7.75V16.25' stroke='%23ff0000' stroke-linecap='round' stroke-width='2' class='spinner_P7sC spinner_Km9P'/%3E%3Cpath d='M7.76 7.75V16.25' stroke='%23ff0000' stroke-linecap='round' stroke-width='2' class='spinner_P7sC spinner_c7oB'/%3E%3C/svg%3E")
                    center center no-repeat
                `,
                showClass: {
                    popup: 'animate__animated animate__fadeInDown'
                },
                hideClass: {
                    popup: 'animate__animated animate__fadeOutUp'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    // Animasi fade out untuk item yang dihapus
                    const itemElement = document.querySelectorAll('.cart-item')[index];
                    itemElement.style.transition = 'all 0.3s ease';
                    itemElement.style.opacity = '0';
                    itemElement.style.transform = 'translateX(20px)';
                    
                    setTimeout(() => {
                        cartItems.splice(index, 1);
                        localStorage.setItem('cartItems', JSON.stringify(cartItems));
                        renderCartItems();
                        updateSummary();
                        
                        Swal.fire({
                            icon: 'success',
                            title: 'Terhapus!',
                            text: 'Item telah dihapus dari keranjang',
                            background: '#1a1a1a',
                            color: '#ffffff',
                            showConfirmButton: false,
                            timer: 1500
                        });
                    }, 300);
                }
            });
        }

        // Tambahkan fungsi untuk update quantity
        function updateQuantity(index, change) {
            const item = cartItems[index];
            const newQuantity = (item.qty || item.quantity || 1) + change;
            
            // Tambahkan pengecekan stok sebelum update quantity
            if (newQuantity > item.stock) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Stok Tidak Mencukupi',
                    text: `Stok tersedia hanya ${item.stock} unit`,
                    background: '#1a1a1a',
                    color: '#ffffff',
                    confirmButtonColor: '#ff0000'
                });
                return;
            }
            
            // Mencegah quantity kurang dari 1
            if (newQuantity < 1) {
                // Langsung panggil removeItem tanpa konfirmasi tambahan
                removeItem(index);
                return;
            }
            
            // Update quantity jika validasi berhasil
            if (item.qty) {
                item.qty = newQuantity;
            } else {
                item.quantity = newQuantity;
            }
            
            // Update localStorage
            localStorage.setItem('cartItems', JSON.stringify(cartItems));
            
            // Re-render cart dan update summary
            renderCartItems();
            updateSummary();
        }

        // Fungsi calculate subtotal di scope global
        function calculateSubtotal() {
            return cartItems.reduce((total, item) => {
                const price = parseInt(item.price.replace(/\D/g, ''));
                const itemQuantity = item.qty || item.quantity || 1;
                return total + (price * itemQuantity);
            }, 0);
        }

        // Fungsi update summary di scope global
        function updateSummary() {
            const subtotal = calculateSubtotal();
            const shippingCost = parseInt(document.querySelector('input[name="shipping_cost"]').value) || 0;
            const total = subtotal + shippingCost;
            
            document.getElementById('subtotal-display').textContent = formatCurrency(subtotal);
            document.getElementById('shipping').textContent = formatCurrency(shippingCost);
            document.getElementById('total').textContent = formatCurrency(total);
            
            document.querySelector('input[name="subtotal"]').value = subtotal;
            document.querySelector('input[name="total_amount"]').value = total;
        }

        // Tambahkan fungsi untuk mengambil data stok
        async function fetchStockData() {
            for (let i = 0; i < cartItems.length; i++) {
                const item = cartItems[i];
                try {
                    const response = await fetch(`get_product.php?id=${item.productId}`);
                    const data = await response.json();
                    
                    if (data.success && data.product) {
                        cartItems[i].stock = data.product.stock;
                    }
                } catch (error) {
                    console.error('Error fetching stock:', error);
                    cartItems[i].stock = 'Error';
                }
            }
            // Re-render setelah mendapatkan data stok
            renderCartItems();
            localStorage.setItem('cartItems', JSON.stringify(cartItems));
        }

        // Update event listener DOMContentLoaded
        document.addEventListener('DOMContentLoaded', function() {
            cartItems = JSON.parse(localStorage.getItem('cartItems') || '[]');
            renderCartItems();
            updateSummary();
            fetchStockData(); // Tambahkan pemanggilan fungsi ini
        });

        // Tambahkan fungsi untuk mengatur loading state
        function setLoadingState(isLoading) {
            const button = document.querySelector('.btn-checkout');
            if (isLoading) {
                button.disabled = true;
                button.innerHTML = `
                    <span class="spinner"></span>
                    Memproses Pembayaran...
                `;
            } else {
                button.disabled = false;
                button.innerHTML = 'Proses Pembayaran';
            }
        }

        // Validasi stok sebelum checkout
        async function validateStock() {
            for (const item of cartItems) {
                try {
                    const response = await fetch(`get_product.php?id=${item.productId}`);
                    if (!response.ok) {
                        throw new Error('Gagal mengecek stok produk');
                    }
                    
                    const data = await response.json();
                    if (!data.success) {
                        throw new Error(data.message);
                    }
                    
                    const product = data.product;
                    const itemQuantity = item.qty || item.quantity || 1;
                    
                    if (itemQuantity > product.stock) {
                        throw new Error(`Stok ${product.name} tidak mencukupi. Tersedia: ${product.stock}`);
                    }
                } catch (error) {
                    throw new Error(error.message);
                }
            }
            return true;
        }

        // Update fungsi processCheckout
        async function processCheckout() {
            try {
                const shippingCity = document.getElementById('shippingCity').value;
                if (!shippingCity) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Pilih Kota',
                        text: 'Silakan pilih kota tujuan pengiriman',
                        background: '#1a1a1a',
                        color: '#ffffff'
                    });
                    return;
                }
                
                setLoadingState(true);
                
                const form = document.getElementById('checkoutForm');
                if (!form.checkValidity()) {
                    form.reportValidity();
                    setLoadingState(false);
                    return;
                }

                // Hitung subtotal dan shipping cost
                const subtotal = calculateSubtotal();
                const shippingCost = parseInt(document.querySelector('input[name="shipping_cost"]').value) || 0;
                const totalAmount = subtotal + shippingCost;

                // Format data transaksi
                const transactionData = {
                    _id: form.querySelector('input[name="transactionId"]').value,
                    userId: form.querySelector('input[name="userId"]').value,
                    status: 'pending',
                    orderDate: new Date().toISOString(),
                    shippingAddress: form.querySelector('textarea[name="shippingAddress"]').value,
                    shippingCity: shippingCity,
                    shippingCost: shippingCost,
                    subtotal: subtotal,
                    totalAmount: totalAmount,
                    products: cartItems.map(item => ({
                        productId: formatProductId(item.productId),
                        name: item.name,
                        quantity: item.qty || item.quantity || 1,
                        price: typeof item.price === 'string' ? 
                            parseInt(item.price.replace(/\D/g, '')) : 
                            parseInt(item.price),
                        subtotal: (typeof item.price === 'string' ? 
                            parseInt(item.price.replace(/\D/g, '')) : 
                            parseInt(item.price)) * (item.qty || item.quantity || 1)
                    }))
                };

                // Format data pembayaran
                const paymentData = {
                    _id: form.querySelector('input[name="paymentId"]').value,
                    transactionId: transactionData._id,
                    amount: totalAmount,
                    paymentMethod: form.querySelector('select[name="paymentMethod"]').value,
                    paymentDate: new Date().toISOString(),
                    status: 'pending'
                };

                // Kirim data ke server
                const response = await fetch('process_checkout.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        transaction: transactionData,
                        payment: paymentData
                    })
                });

                const result = await response.json();

                if (!result.success) {
                    throw new Error(result.message || 'Terjadi kesalahan saat memproses checkout');
                }

                // Sukses
                Swal.fire({
                    icon: 'success',
                    title: 'Checkout Berhasil',
                    text: 'Pesanan Anda sedang diproses',
                    background: '#1a1a1a',
                    color: '#ffffff'
                }).then(() => {
                    localStorage.removeItem('cartItems');
                    window.location.href = 'order_confirmation.php?id=' + result.transactionId;
                });

            } catch (error) {
                console.error('Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Gagal Memproses Checkout',
                    text: error.message,
                    background: '#1a1a1a',
                    color: '#ffffff'
                });
            } finally {
                setLoadingState(false);
            }
        }

        function updateShippingCost() {
            const shippingSelect = document.getElementById('shippingCity');
            const selectedOption = shippingSelect.options[shippingSelect.selectedIndex];
            const shippingCost = parseInt(selectedOption.dataset.cost) || 0;
            
            // Update tampilan ongkos kirim
            document.getElementById('shipping').textContent = formatCurrency(shippingCost);
            
            // Update input hidden untuk ongkos kirim
            document.querySelector('input[name="shipping_cost"]').value = shippingCost;
            
            // Update total
            updateSummary();
        }
    </script>
</body>
</html> 