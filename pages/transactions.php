<?php
session_start();
require_once '../config/database.php';

$db = new Database();

// Handle filter dan sorting
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'newest';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Proses semua POST request terlebih dahulu
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['delete_id'])) {
            // Validasi ID
            $deleteId = trim($_POST['delete_id']);
            if (empty($deleteId)) {
                throw new Exception('ID transaksi tidak valid');
            }

            // Pastikan format ID sesuai (t001, t002, dst)
            if (!preg_match('/^t\d{3}$/', $deleteId)) {
                $deleteId = 't' . str_pad(substr($deleteId, 1), 3, '0', STR_PAD_LEFT);
            }

            // Hapus transaksi
            $deleteResult = $db->getCollection('transactions')->deleteOne([
                '_id' => $deleteId
            ]);

            if ($deleteResult->getDeletedCount() > 0) {
                $_SESSION['notification'] = [
                    'type' => 'success',
                    'message' => '🗑️ Transaksi berhasil dihapus!'
                ];
            } else {
                throw new Exception('Gagal menghapus transaksi');
            }
        } else {
            // Handle create/update
            // Generate ID baru jika tidak ada ID
            if (!isset($_POST['id']) || empty($_POST['id'])) {
                // Cari transaksi dengan ID terbesar
                $lastTransaction = $db->getCollection('transactions')->findOne(
                    [], 
                    ['sort' => ['_id' => -1]]
                );

                if ($lastTransaction) {
                    if (preg_match('/^t(\d{3})$/', $lastTransaction->_id, $matches)) {
                        $nextNumber = intval($matches[1]) + 1;
                    } else {
                        $nextNumber = 1;
                    }
                } else {
                    $nextNumber = 1;
                }

                $data['_id'] = 't' . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);
                
                // Pastikan ID baru unik
                while ($db->getCollection('transactions')->findOne(['_id' => $data['_id']])) {
                    $nextNumber++;
                    $data['_id'] = 't' . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);
                }
            }

            // Set data transaksi lainnya
            $data['userId'] = $_POST['userId'];
            $data['status'] = $_POST['status'];
            $data['orderDate'] = $_POST['orderDate'];
            $data['shippingAddress'] = $_POST['shippingAddress'];
            $data['products'] = [];

            // Proses data produk
            if (isset($_POST['productId']) && is_array($_POST['productId'])) {
                for ($i = 0; $i < count($_POST['productId']); $i++) {
                    if (!empty($_POST['productId'][$i])) {
                        $productData = [
                            'productId' => $_POST['productId'][$i],
                            'jumlah_product' => isset($_POST['jumlah_product'][$i]) ? (int)$_POST['jumlah_product'][$i] : 1,
                            'price' => isset($_POST['price'][$i]) ? (float)$_POST['price'][$i] : 0
                        ];
                        $data['products'][] = $productData;
                    }
                }
            }

            // Hitung total amount
            $data['totalAmount'] = array_reduce($data['products'], function($carry, $item) {
                return $carry + ($item['price'] * $item['jumlah_product']);
            }, 0);

            // Update atau Create berdasarkan ada tidaknya ID
            if (isset($_POST['id']) && !empty($_POST['id'])) {
                // Update
                $timezone = new DateTimeZone('Asia/Jakarta');
                $orderDate = new DateTime($_POST['orderDate'], $timezone);
                $utcDate = clone $orderDate;
                $utcDate->setTimezone(new DateTimeZone('UTC'));
                $data['orderDate'] = new MongoDB\BSON\UTCDateTime($utcDate->getTimestamp() * 1000);
                
                $result = $db->getCollection('transactions')->updateOne(
                    ['_id' => $_POST['id']],
                    ['$set' => $data]
                );
                
                if ($result->getModifiedCount() > 0) {
                    $_SESSION['notification'] = [
                        'type' => 'success',
                        'message' => '📝 Data berhasil diperbarui!'
                    ];
                } else {
                    throw new Exception('Gagal memperbarui transaksi');
                }
            } else {
                // Create
                $timezone = new DateTimeZone('Asia/Jakarta');
                $orderDate = new DateTime($_POST['orderDate'], $timezone);
                $utcDate = clone $orderDate;
                $utcDate->setTimezone(new DateTimeZone('UTC'));
                $data['orderDate'] = new MongoDB\BSON\UTCDateTime($utcDate->getTimestamp() * 1000);
                
                $result = $db->getCollection('transactions')->insertOne($data);
                
                if ($result->getInsertedCount() > 0) {
                    $_SESSION['notification'] = [
                        'type' => 'success',
                        'message' => '✅ Data berhasil ditambahkan!'
                    ];
                } else {
                    throw new Exception('Gagal menambahkan transaksi baru');
                }
            }
        }
    } catch (Exception $e) {
        error_log('Error: ' . $e->getMessage());
        $_SESSION['notification'] = [
            'type' => 'error',
            'message' => '❌ Error: ' . $e->getMessage()
        ];
        header('Location: transactions.php');
        exit;
    }
}

// Buat filter query
$filter = [];
if ($status_filter !== '') {
    $filter['status'] = $status_filter;
}
if ($search) {
    $filter['$or'] = [
        ['_id' => ['$regex' => $search, '$options' => 'i']],
        ['userId' => ['$regex' => $search, '$options' => 'i']]
    ];
}

// Buat sort options
$sort_options = [];
switch($sort) {
    case 'oldest':
        $sort_options = ['_id' => 1];
        break;
    case 'highest':
        $sort_options = ['totalAmount' => -1];
        break;
    case 'lowest':
        $sort_options = ['totalAmount' => 1];
        break;
    default: // newest
        $sort_options = ['_id' => -1];
}

$transactions = $db->getCollection('transactions')
    ->find($filter, ['sort' => $sort_options])
    ->toArray();

// Setelah semua logika, baru include header dan tampilkan HTML
require_once '../templates/header.php';
?>

<!-- Header Section -->
<div class="transactions-wrapper">
    <div class="page-header">
        <div class="header-content">
            <div class="header-title">
                <h1><i class="fas fa-shopping-cart"></i> Transaksi</h1>
                <p>Kelola semua transaksi</p>
            </div>
            <button type="button" class="btn-add" onclick="showAddTransactionModal()">
                <i class="fas fa-plus"></i> Tambah Transaksi
            </button>
        </div>
    </div>

    <!-- Notification System -->
    <?php if (isset($_SESSION['notification'])): ?>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const Toast = Swal.mixin({
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3000,
                timerProgressBar: true,
                background: '<?php echo $_SESSION['notification']['type'] === 'success' ? '#1a472a' : '#472a2a'; ?>',
                color: '#ffffff',
                customClass: {
                    popup: 'colored-toast'
                },
                didOpen: (toast) => {
                    toast.addEventListener('mouseenter', Swal.stopTimer)
                    toast.addEventListener('mouseleave', Swal.resumeTimer)
                }
            });

            Toast.fire({
                icon: '<?php echo $_SESSION['notification']['type']; ?>',
                title: '<?php echo $_SESSION['notification']['message']; ?>'
            });
        });
        </script>
        <?php unset($_SESSION['notification']); ?>
    <?php endif; ?>

    <!-- Filter dan Search Section -->
    <div class="transactions-controls">
        <div class="search-box">
            <input type="text" 
                   id="searchInput" 
                   placeholder="Cari transaksi..." 
                   value="<?php echo htmlspecialchars($search); ?>">
            <button id="searchButton">
                <i class="fas fa-search"></i>
            </button>
        </div>

        <div class="filter-group">
            <select id="statusFilter" onchange="filterTransactions()">
                <option value="">🔍 semua status</option>
                <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>⏳ pending</option>
                <option value="processing" <?php echo $status_filter == 'processing' ? 'selected' : ''; ?>>⚙️ processing</option>
                <option value="shipped" <?php echo $status_filter == 'shipped' ? 'selected' : ''; ?>>🚚 shipped</option>
                <option value="delivered" <?php echo $status_filter == 'delivered' ? 'selected' : ''; ?>>✅ delivered</option>
                <option value="cancelled" <?php echo $status_filter == 'cancelled' ? 'selected' : ''; ?>>❌ cancelled</option>
            </select>

            <select id="sortSelect" onchange="filterTransactions()">
                <option value="newest" <?php echo $sort == 'newest' ? 'selected' : ''; ?>>📅 Terbaru</option>
                <option value="oldest" <?php echo $sort == 'oldest' ? 'selected' : ''; ?>>📅 Terlama</option>
                <option value="highest" <?php echo $sort == 'highest' ? 'selected' : ''; ?>>💰 Harga Tertinggi</option>
                <option value="lowest" <?php echo $sort == 'lowest' ? 'selected' : ''; ?>>💰 Harga Terendah</option>
            </select>
        </div>
    </div>

    <!-- Transactions Summary Cards -->
    <div class="summary-cards">
        <?php
        $total_transactions = count($transactions);
        $total_revenue = array_reduce($transactions, function($carry, $item) {
            return $carry + $item->totalAmount;
        }, 0);
        $pending_orders = count(array_filter($transactions, function($t) {
            return strtolower($t->status) === 'pending';
        }));
        ?>
        
        <div class="summary-card">
            <div class="card-icon">
                <i class="fas fa-chart-line"></i>
            </div>
            <div class="card-info">
                <h3>Total Transaksi</h3>
                <p><?php echo $total_transactions; ?></p>
            </div>
        </div>

        <div class="summary-card">
            <div class="card-icon">
                <i class="fas fa-money-bill-wave"></i>
            </div>
            <div class="card-info">
                <h3>Total Pendapatan</h3>
                <p>Rp <?php echo number_format($total_revenue, 0, ',', '.'); ?></p>
            </div>
        </div>

        <div class="summary-card">
            <div class="card-icon">
                <i class="fas fa-clock"></i>
            </div>
            <div class="card-info">
                <h3>Pesanan Pending</h3>
                <p><?php echo $pending_orders; ?></p>
            </div>
        </div>
    </div>

    <!-- Existing Table -->
    <div class="table-container">
        <table class='transaction-table'>
            <tr>
                <th>ID</th>
                <th>User ID</th>
                <th>Products</th>
                <th>Total Amount</th>
                <th>Status</th>
                <th>Order Date</th>
                <th>Shipping Address</th>
                <th>Actions</th>
            </tr>

            <?php
            foreach ($transactions as $transaction) {
                // Konversi objek transaksi ke array untuk JSON
                $transactionData = [
                    '_id' => 't' . str_pad(substr($transaction->_id, 1), 3, '0', STR_PAD_LEFT),
                    'userId' => $transaction->userId,
                    'status' => $transaction->status,
                    'orderDate' => $transaction->orderDate->toDateTime()->setTimezone(new DateTimeZone('Asia/Jakarta'))->format('Y-m-d\TH:i'),
                    'shippingAddress' => $transaction->shippingAddress,
                    'products' => array_map(function($p) {
                        return [
                            'productId' => $p->productId,
                            'jumlah_product' => (int)($p->jumlah_product ?? 1),
                            'price' => (float)$p->price
                        ];
                    }, iterator_to_array($transaction->products)),
                    'totalAmount' => (float)$transaction->totalAmount
                ];

                $products = array_map(function($product) {
                    $quantity = isset($product->quantity) ? $product->quantity : 
                               (isset($product->jumlah_product) ? $product->jumlah_product : 1);
                               
                    return "<li>Product ID: " . $product->productId . 
                           "<br>Jumlah: " . $quantity . 
                           "<br>Harga: Rp " . number_format($product->price, 0, ',', '.');
                }, $transaction->products->getArrayCopy());
                
                $productsList = "<ul class='product-list'>" . implode('</li>', $products) . "</li></ul>";
                $statusClass = "status-" . strtolower($transaction->status);

                echo "<tr>
                    <td>{$transaction->_id}</td>
                    <td>{$transaction->userId}</td>
                    <td>{$productsList}</td>
                    <td>Rp " . number_format($transaction->totalAmount, 0, ',', '.') . "</td>
                    <td class='{$statusClass}'>{$transaction->status}</td>
                    <td>" . $transaction->orderDate->toDateTime()->setTimezone(new DateTimeZone('Asia/Jakarta'))->format('d/m/Y H:i:s') . "</td>
                    <td>{$transaction->shippingAddress}</td>
                    <td class='action-buttons'>
                        <button type='button' class='btn-edit' onclick='editTransaction(" . json_encode($transactionData) . ")'>
                            <i class='fas fa-edit'></i>
                        </button>
                        <form method='POST' style='display:inline;'>
                            <input type='hidden' name='delete_id' value='{$transaction->_id}'>
                            <button type='submit' class='btn-delete' onclick='return deleteTransaction(this.form)'>
                                <i class='fas fa-trash'></i>
                            </button>
                        </form>
                    </td>
                </tr>";
            }
            ?>
        </table>
    </div>
</div>

<style>
/* Container Styles */
.transactions-wrapper {
    padding: 20px;
    max-width: 1400px;
    margin: 0 auto;
    min-height: 100vh;
}

/* Header Styles */
.page-header {
    margin-bottom: 30px;
    padding: 20px;
    background: var(--secondary-black);
    border-radius: 10px;
}

.header-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.header-title {
    flex: 1;
}

.header-title h1 {
    color: var(--primary-white);
    font-size: 24px;
    display: flex;
    align-items: center;
    gap: 10px;
    margin: 0;
}

.header-title p {
    color: #888;
    margin: 5px 0 0 0;
}

.btn-add {
    padding: 12px 24px;
    height: fit-content;
    display: flex;
    align-items: center;
    gap: 8px;
    font-weight: 500;
    background: var(--primary-red);
    color: white;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-add:hover {
    background: var(--secondary-red);
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
}

.btn-add i {
    font-size: 0.9rem;
}

/* Controls Styles */
.transactions-controls {
    display: flex;
    gap: 20px;
    margin-bottom: 30px;
    padding: 20px;
    background: var(--secondary-black);
    border-radius: 10px;
}

.search-box {
    flex: 1;
    max-width: 400px;
    display: flex;
}

.search-box input {
    flex: 1;
    padding: 10px 15px;
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 8px 0 0 8px;
    background: rgba(255,255,255,0.05);
    color: var(--primary-white);
}

.search-box button {
    padding: 10px 20px;
    background: var(--primary-red);
    border: none;
    border-radius: 0 8px 8px 0;
    color: white;
    cursor: pointer;
}

.filter-group {
    position: relative;
    display: flex;
    gap: 15px;
}

.filter-group select {
    padding: 10px 35px 10px 15px;
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 8px;
    background-color: var(--secondary-black); /* Mengubah warna background default */
    color: #ffffff;
    cursor: pointer;
    font-size: 0.9rem;
    transition: all 0.3s ease;
    -webkit-appearance: none;
    -moz-appearance: none;
    appearance: none;
    min-width: 160px;
    background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='white' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
    background-repeat: no-repeat;
    background-position: right 10px center;
    background-size: 1em;
}

/* Hover state */
.filter-group select:hover {
    border-color: var(--primary-blue);
    background-color: rgba(255, 255, 255, 0.1); /* Warna saat hover */
}

/* Focus state */
.filter-group select:focus {
    border-color: var(--primary-blue);
    background-color: rgba(255, 255, 255, 0.15); /* Warna saat focus/klik */
    box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.2);
    outline: none;
}

/* Selected option state */
.filter-group select option {
    background-color: var(--secondary-black); /* Warna background untuk options */
    color: #ffffff;
    padding: 10px;
}

/* Hover pada options */
.filter-group select option:hover {
    background-color: var(--primary-blue);
}

.filter-group::before {
    display: none;
}

/* Summary Cards */
.summary-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.summary-card {
    background: var(--secondary-black);
    padding: 20px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    gap: 15px;
}

.card-icon {
    width: 50px;
    height: 50px;
    background: rgba(230, 57, 70, 0.1);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--primary-red);
    font-size: 1.5rem;
}

.card-info h3 {
    margin: 0;
    font-size: 0.9rem;
    color: #888;
}

.card-info p {
    margin: 5px 0 0;
    font-size: 1.2rem;
    color: var(--primary-white);
    font-weight: bold;
}

/* Table Container */
.table-container {
    background: var(--secondary-black);
    border-radius: 10px;
    padding: 20px;
    overflow-x: auto;
}

/* Responsive Design */
@media (max-width: 768px) {
    .transactions-controls {
        flex-direction: column;
    }

    .search-box {
        max-width: 100%;
    }

    .filter-group {
        flex-direction: column;
    }

    .filter-group select {
        width: 100%;
    }
}

/* Modal Styling */
.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.7);
    z-index: 1000;
    overflow-y: auto;
    padding: 20px;
}

.modal-content {
    background: var(--secondary-black);
    border-radius: 15px;
    max-width: 800px;
    margin: 30px auto;
    padding: 30px;
    position: relative;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
}

/* Modal Header */
.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
    padding-bottom: 20px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.modal-header h2 {
    color: var(--primary-white);
    font-size: 1.5rem;
    display: flex;
    align-items: center;
    gap: 12px;
    margin: 0;
}

.modal-header h2 i {
    color: var(--primary-red);
}

/* Form Layout */
.form-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 25px;
    margin-bottom: 30px;
}

/* Form Groups */
.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 10px;
    color: var(--primary-white);
    font-weight: 500;
    font-size: 0.95rem;
}

.form-group input,
.form-group select,
.form-group textarea {
    width: 100%;
    padding: 12px 15px;
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 8px;
    background: rgba(255, 255, 255, 0.05);
    color: var(--primary-white);
    font-size: 0.95rem;
    transition: all 0.3s ease;
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
    border-color: var(--primary-blue);
    box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.1);
    outline: none;
}

.form-group textarea {
    height: 120px;
    resize: vertical;
}

/* Products Section */
.products-section {
    background: rgba(255, 255, 255, 0.03);
    border-radius: 12px;
    padding: 25px;
    margin-bottom: 30px;
}

.products-section h3 {
    color: var(--primary-white);
    font-size: 1.1rem;
    margin: 0 0 20px 0;
    display: flex;
    align-items: center;
    gap: 10px;
}

.products-section h3 i {
    color: var(--primary-red);
}

/* Product Entry */
.product-entry {
    display: grid;
    grid-template-columns: 2fr 1fr 1.5fr auto;
    gap: 15px;
    align-items: start;
    background: rgba(255, 255, 255, 0.02);
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 15px;
    border: 1px solid rgba(255, 255, 255, 0.05);
    transition: all 0.3s ease;
}

.product-entry:hover {
    background: rgba(255, 255, 255, 0.04);
    border-color: rgba(255, 255, 255, 0.1);
}

.product-entry .form-group {
    margin-bottom: 0;
}

/* Add Product Button */
.btn-add-product {
    width: 100%;
    padding: 15px;
    background: linear-gradient(45deg, var(--primary-blue), #0056b3);
    color: white;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 500;
    font-size: 0.95rem;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    transition: all 0.3s ease;
    box-shadow: 0 4px 15px rgba(0, 123, 255, 0.2);
}

.btn-add-product:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(0, 123, 255, 0.3);
}

.btn-add-product:active {
    transform: translateY(0);
}

/* Remove Product Button */
.btn-remove {
    background: var(--primary-red);
    color: white;
    border: none;
    border-radius: 8px;
    width: 42px;
    height: 42px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-remove:hover {
    background: var(--secondary-red);
    transform: scale(1.05);
}

.btn-remove i {
    font-size: 1rem;
}

/* Form Actions */
.form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 15px;
    margin-top: 30px;
    padding-top: 25px;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
}

.btn-cancel,
.btn-submit {
    padding: 12px 25px;
    border-radius: 8px;
    font-weight: 500;
    font-size: 0.95rem;
    display: flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-cancel {
    background: rgba(255, 255, 255, 0.05);
    color: var(--primary-white);
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.btn-submit {
    background: var(--primary-blue);
    color: white;
    border: none;
    box-shadow: 0 4px 15px rgba(0, 123, 255, 0.2);
}

.btn-cancel:hover,
.btn-submit:hover {
    transform: translateY(-2px);
}

.btn-submit:hover {
    background: var(--secondary-blue);
    box-shadow: 0 6px 20px rgba(0, 123, 255, 0.3);
}

/* Close Button */
.close-btn {
    background: rgba(255, 255, 255, 0.05);
    border: none;
    border-radius: 8px;
    width: 36px;
    height: 36px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s ease;
    color: var(--primary-white);
}

.close-btn:hover {
    background: var(--primary-red);
    transform: rotate(90deg);
}

/* Responsive Design */
@media (max-width: 768px) {
    .modal {
        padding: 10px;
    }

    .modal-content {
        padding: 20px;
        margin: 15px auto;
    }

    .form-grid {
        grid-template-columns: 1fr;
        gap: 15px;
    }

    .product-entry {
        grid-template-columns: 1fr;
        gap: 10px;
        padding: 15px;
    }

    .form-actions {
        flex-direction: column-reverse;
    }

    .btn-cancel,
    .btn-submit {
        width: 100%;
        justify-content: center;
    }
}

/* Animations */
@keyframes modalFadeIn {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.modal-content {
    animation: modalFadeIn 0.3s ease-out;
}

/* Input Placeholders */
.form-group input::placeholder,
.form-group textarea::placeholder {
    color: rgba(255, 255, 255, 0.3);
}

/* Custom Scrollbar */
.modal::-webkit-scrollbar {
    width: 8px;
}

.modal::-webkit-scrollbar-track {
    background: rgba(255, 255, 255, 0.05);
}

.modal::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.1);
    border-radius: 4px;
}

.modal::-webkit-scrollbar-thumb:hover {
    background: rgba(255, 255, 255, 0.2);
}

/* Action Buttons */
.action-buttons {
    display: flex;
    gap: 8px;
    justify-content: center;
}

.btn-edit {
    background: var(--primary-blue);
    color: white;
    border: none;
    border-radius: 8px;
    width: 35px;
    height: 35px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-edit:hover {
    background: var(--secondary-blue);
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0, 123, 255, 0.2);
}

.btn-edit:active {
    transform: translateY(0);
}

.btn-edit i {
    font-size: 0.9rem;
}

.btn-delete {
    background: var(--primary-red);
    color: white;
    border: none;
    border-radius: 8px;
    width: 35px;
    height: 35px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-delete:hover {
    background: var(--secondary-red);
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(220, 53, 69, 0.2);
}

.btn-delete:active {
    transform: translateY(0);
}

.btn-delete i {
    font-size: 0.9rem;
}

/* Responsive Design */
@media (max-width: 768px) {
    .action-buttons {
        flex-direction: row;
        gap: 5px;
    }
    
    .btn-edit,
    .btn-delete {
        width: 35px;
        height: 35px;
    }
}

/* Styling untuk dialog konfirmasi SweetAlert2 */
.swal2-popup {
    border-radius: 15px !important;
}

.swal2-title {
    color: #ffffff !important;
}

.swal2-html-container {
    color: #cccccc !important;
}

.btn-danger {
    background: var(--primary-red) !important;
    border-color: var(--primary-red) !important;
}

.btn-danger:hover {
    background: var(--secondary-red) !important;
    border-color: var(--secondary-red) !important;
}

.btn-secondary {
    background: #6c757d !important;
    border-color: #6c757d !important;
}

.btn-secondary:hover {
    background: #5a6268 !important;
    border-color: #545b62 !important;
}

.btn-add-product {
    width: 100%;
    padding: 14px;
    background: linear-gradient(45deg, var(--primary-blue), #0056b3);
    color: white;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 500;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    margin-top: 15px;
    transition: all 0.3s ease;
    box-shadow: 0 4px 15px rgba(0,123,255,0.2);
}

.btn-add-product:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(0,123,255,0.3);
}

.btn-add-product:active {
    transform: translateY(0);
}

.btn-remove {
    background: var(--primary-red);
    color: white;
    border: none;
    border-radius: 8px;
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-remove:hover {
    background: var(--secondary-red);
    transform: scale(1.1);
}

.btn-remove i {
    font-size: 1rem;
}

/* Form Actions */
.form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    margin-top: 25px;
    padding-top: 20px;
    border-top: 1px solid rgba(255,255,255,0.1);
}

.btn-cancel,
.btn-submit {
    padding: 12px 24px;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 8px;
    transition: all 0.3s ease;
}

.btn-cancel {
    background: rgba(255,255,255,0.05);
    color: var(--primary-white);
}

.btn-submit {
    background: linear-gradient(45deg, var(--primary-blue), #0056b3);
    color: white;
    box-shadow: 0 4px 15px rgba(0,123,255,0.2);
}

.btn-cancel:hover,
.btn-submit:hover {
    transform: translateY(-2px);
}

.btn-submit:hover {
    box-shadow: 0 6px 20px rgba(0,123,255,0.3);
}

/* Animations */
@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes slideIn {
    from { transform: translateY(-20px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}

/* Responsive Design */
@media (max-width: 768px) {
    .form-row {
        grid-template-columns: 1fr;
    }
    
    .product-entry {
        grid-template-columns: 1fr;
        gap: 10px;
    }
    
    .form-actions {
        flex-direction: column;
    }
    
    .btn-cancel,
    .btn-submit {
        width: 100%;
        justify-content: center;
    }
    
    .modal-content {
        margin: 15px;
    }
}

.btn.btn-primary {
  background-color: #007bff; /* Example background color */
  color: #fff; /* Example text color */
  border: none; /* Remove default border */
  padding: 10px 20px; /* Add some padding */
  cursor: pointer; /* Change cursor to pointer on hover */
}

.btn.btn-primary:hover {
  background-color: #0069d9; /* Darken background on hover */
}

.btn.btn-primary i.fas.fa-save {
  margin-right: 5px; /* Add some space between icon and text */
}

.btn.btn-secondary {
  background-color: #007bff; /* Example background color */
  color: #fff; /* Example text color */
  border: none; /* Remove default border */
  padding: 10px 20px; /* Add some padding */
  cursor: pointer; /* Change cursor to pointer on hover */
}

.btn.btn-secondary:hover {
  background-color: #0069d9; /* Darken background on hover */
}

.btn.btn-primary i.fas.fa-times {
  margin-right: 5px; /* Add some space between icon and text */
}


/* Close Button Styling */
.close-btn {
    position: relative;
    width: 32px;
    height: 32px;
    background: rgba(255, 255, 255, 0.05);
    border: none;
    border-radius: 8px;
    color: #888;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
    overflow: hidden;
}

.close-icon {
    font-size: 24px;
    font-weight: bold;
    line-height: 1;
    transition: all 0.3s ease;
}

.close-btn::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: var(--primary-red);
    opacity: 0;
    transition: all 0.3s ease;
    transform: scale(0.8);
    border-radius: 8px;
}

.close-btn:hover {
    color: white;
    transform: rotate(90deg);
}

.close-btn:hover::before {
    opacity: 1;
    transform: scale(1);
}

.close-btn:hover .close-icon {
    transform: scale(1.1);
}

.close-btn:active {
    transform: rotate(90deg) scale(0.95);
}

/* Styling untuk ID Transaksi */
.transaction-id-display {
    padding: 10px 20px;
    color: #888;
    font-size: 0.9rem;
    border-bottom: 1px solid rgba(255,255,255,0.1);
    background: rgba(255,255,255,0.03);
}

.input-group input {
    width: 100%;
    padding: 8px 12px 8px 35px;
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 5px;
    background: var(--secondary-black);
    color: var(--primary-white);
    cursor: text;
}

.input-group input:focus {
    outline: none;
    border-color: var(--primary-blue);
}

/* Notifikasi Styling */
.colored-toast.swal2-icon-success {
    background: #1a472a !important;
}

.colored-toast.swal2-icon-error {
    background: #472a2a !important;
}

.colored-toast {
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15) !important;
}

.colored-toast .swal2-title {
    color: white;
    font-size: 16px !important;
    padding: 8px 12px !important;
}

.colored-toast .swal2-close {
    color: white;
}

.colored-toast .swal2-html-container {
    color: rgba(255,255,255,0.8);
}

.colored-toast .swal2-timer-progress-bar {
    background: rgba(255,255,255,0.3);
}

/* Table Styles */
.transaction-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 20px;
    background: var(--secondary-black);
    border-radius: 10px;
    overflow: hidden;
}

.transaction-table th {
    background: rgba(255,255,255,0.05);
    padding: 15px;
    text-align: left;
    font-weight: 500;
    color: var(--primary-white);
}

.transaction-table td {
    padding: 15px;
    border-bottom: 1px solid rgba(255,255,255,0.05);
    color: var(--primary-white);
}

/* Status Colors */
.status-pending {
    background: #ffa726;
    color: #fff;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 0.85em;
    font-weight: 500;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.status-processing {
    background: #42a5f5;
    color: #fff;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 0.85em;
    font-weight: 500;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.status-shipped {
    background: #66bb6a;
    color: #fff;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 0.85em;
    font-weight: 500;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.status-delivered {
    background: #4caf50;
    color: #fff;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 0.85em;
    font-weight: 500;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.status-cancelled {
    background: #ef5350;
    color: #fff;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 0.85em;
    font-weight: 500;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

/* Tambahkan icon untuk setiap status */
.status-pending::before {
    content: '\f017';
    font-family: "Font Awesome 5 Free";
    font-weight: 900;
}

.status-processing::before {
    content: '\f110';
    font-family: "Font Awesome 5 Free";
    font-weight: 900;
}

.status-shipped::before {
    content: '\f48b';
    font-family: "Font Awesome 5 Free";
    font-weight: 900;
}

.status-delivered::before {
    content: '\f058';
    font-family: "Font Awesome 5 Free";
    font-weight: 900;
}

.status-cancelled::before {
    content: '\f057';
    font-family: "Font Awesome 5 Free";
    font-weight: 900;
}

/* Hover effect untuk filter */
.filter-group select:hover {
    border-color: var(--primary-blue);
    background-color: rgba(255, 255, 255, 0.08);
}

.filter-group select:focus {
    border-color: var(--primary-blue);
    box-shadow: 0 0 0 3px rgba(0,123,255,0.1);
    outline: none;
}

/* Responsive design */
@media (max-width: 768px) {
    .filter-group {
        flex-direction: column;
    }
    
    .filter-group select {
        width: 100%;
    }
}

/* Status badges dalam tabel */
.status-pending {
    background: #ffa726;
    color: #fff;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 0.85em;
    font-weight: 500;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.status-processing {
    background: #42a5f5;
    color: #fff;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 0.85em;
    font-weight: 500;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.status-shipped {
    background: #66bb6a;
    color: #fff;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 0.85em;
    font-weight: 500;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.status-delivered {
    background: #4caf50;
    color: #fff;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 0.85em;
    font-weight: 500;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.status-cancelled {
    background: #ef5350;
    color: #fff;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 0.85em;
    font-weight: 500;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

/* Tambahkan icon untuk setiap status */
.status-pending::before {
    content: '\f017';
    font-family: "Font Awesome 5 Free";
    font-weight: 900;
}

.status-processing::before {
    content: '\f110';
    font-family: "Font Awesome 5 Free";
    font-weight: 900;
}

.status-shipped::before {
    content: '\f48b';
    font-family: "Font Awesome 5 Free";
    font-weight: 900;
}

.status-delivered::before {
    content: '\f058';
    font-family: "Font Awesome 5 Free";
    font-weight: 900;
}

.status-cancelled::before {
    content: '\f057';
    font-family: "Font Awesome 5 Free";
    font-weight: 900;
}

/* Hover effect untuk filter */
.filter-group select:hover {
    border-color: var(--primary-blue);
    background-color: rgba(255, 255, 255, 0.08);
}

.filter-group select:focus {
    border-color: var(--primary-blue);
    box-shadow: 0 0 0 3px rgba(0,123,255,0.1);
    outline: none;
}

/* Responsive design */
@media (max-width: 768px) {
    .filter-group {
        flex-direction: column;
    }
    
    .filter-group select {
        width: 100%;
    }
}

/* Product List in Table */
.product-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.product-list li {
    padding: 5px 0;
    border-bottom: 1px solid rgba(255,255,255,0.05);
}

.product-list li:last-child {
    border-bottom: none;
}

/* Action Buttons */
.action-buttons {
    display: flex;
    gap: 8px;
}

.btn-edit, .btn-delete {
    padding: 8px;
    border: none;
    border-radius: 5px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-edit {
    background: var(--primary-blue);
    color: white;
}

.btn-delete {
    background: var(--primary-red);
    color: white;
}

.btn-edit:hover, .btn-delete:hover {
    transform: translateY(-2px);
    box-shadow: 0 2px 5px rgba(0,0,0,0.2);
}

/* Table Container */
.table-container {
    margin-top: 20px;
    background: var(--secondary-black);
    border-radius: 10px;
    padding: 20px;
    overflow-x: auto;
}

/* Responsive Table */
@media (max-width: 768px) {
    .transaction-table {
        display: block;
        overflow-x: auto;
        white-space: nowrap;
    }
    
    .action-buttons {
        flex-direction: column;
        gap: 5px;
    }
    
    .btn-edit, .btn-delete {
        width: 100%;
    }
}

/* Hover Effects */
.transaction-table tr:hover {
    background: rgba(255,255,255,0.02);
}

.btn-edit:active, .btn-delete:active {
    transform: translateY(0);
}

/* Empty State */
.no-data {
    text-align: center;
    padding: 40px;
    color: #666;
}

/* Loading State */
.loading {
    text-align: center;
    padding: 40px;
    color: #666;
}

.loading i {
    animation: spin 1s infinite linear;
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

/* Styling untuk filter dan sort select */
.filter-controls {
    display: flex;
    gap: 15px;
}

.filter-button {
    padding: 8px 16px;
    border-radius: 8px;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.1);
    color: var(--primary-white);
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 8px;
    transition: all 0.3s ease;
}

.filter-button i {
    font-size: 14px;
}

/* Dropdown styling */
.filter-dropdown {
    position: absolute;
    top: 100%;
    left: 0;
    background: var(--secondary-black);
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
    min-width: 200px;
    z-index: 1000;
    border: 1px solid rgba(255, 255, 255, 0.1);
    overflow: hidden;
}

.filter-option {
    padding: 10px 16px;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 8px;
    color: var(--primary-white);
    transition: all 0.3s ease;
}

/* Status colors */
.filter-option[data-value="Pending"] {
    color: #ffa726;
}

.filter-option[data-value="Processing"] {
    color: #42a5f5;
}

.filter-option[data-value="Shipped"] {
    color: #66bb6a;
}

.filter-option[data-value="Delivered"] {
    color: #4caf50;
}

.filter-option[data-value="Cancelled"] {
    color: #ef5350;
}

/* Sort options */
.filter-option[data-value="newest"],
.filter-option[data-value="oldest"] {
    color: #9575cd;
}

.filter-option[data-value="highest"],
.filter-option[data-value="lowest"] {
    color: #4db6ac;
}

.filter-option:hover {
    background: rgba(255, 255, 255, 0.05);
}

/* Active state */
.filter-option.active {
    background: rgba(255, 255, 255, 0.1);
    font-weight: 500;
}

/* Update HTML structure */
</style>

<?php require_once '../templates/footer.php'; ?>

<!-- Modal (tambahkan di akhir file, sebelum closing div terakhir) -->
<div id="transactionModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="modalTitle">
                <i class="fas fa-plus-circle"></i>
                <span>Tambah Transaksi Baru</span>
            </h2>
            <button type="button" class="close-btn" onclick="closeModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <form id="transactionForm" method="POST">
            <input type="hidden" id="transactionId" name="id">
            
            <div class="form-grid">
                <div class="form-group">
                    <label><i class="fas fa-user"></i> User ID</label>
                    <input type="text" name="userId" required>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-info-circle"></i> Status</label>
                    <select name="status" required>
                        <option value="pending">⏳ Pending</option>
                        <option value="processing">⚙️ Processing</option>
                        <option value="shipped">🚚 Shipped</option>
                        <option value="delivered">✅ Delivered</option>
                        <option value="cancelled">❌ Cancelled</option>
                    </select>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-calendar"></i> Tanggal Order</label>
                    <input type="datetime-local" name="orderDate" required>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-map-marker-alt"></i> Alamat Pengiriman</label>
                    <textarea name="shippingAddress" required></textarea>
                </div>
            </div>

            <div class="products-section">
                <h3><i class="fas fa-box"></i> Produk</h3>
                <div id="productsContainer"></div>
                <button type="button" class="btn-add-product" onclick="addProduct()">
                    <i class="fas fa-plus"></i> Tambah Produk
                </button>
            </div>

            <div class="form-actions">
                <button type="button" class="btn-cancel" onclick="closeModal()">
                    <i class="fas fa-times"></i> Batal
                </button>
                <button type="submit" class="btn-submit">
                    <i class="fas fa-save"></i> Simpan
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Tambahkan script di akhir file, sebelum closing body tag -->
<script>
    // Fungsi untuk menampilkan modal tambah transaksi
    function showAddTransactionModal() {
        try {
            const modal = document.getElementById('transactionModal');
            const form = document.getElementById('transactionForm');
            const modalTitle = document.getElementById('modalTitle');
            
            if (!modal || !form || !modalTitle) {
                throw new Error('Required elements not found');
            }

            // Reset form
            form.reset();
            document.getElementById('transactionId').value = '';
            
            // Update modal title
            modalTitle.innerHTML = `
                <i class="fas fa-plus-circle"></i>
                <span>Tambah Transaksi Baru</span>
            `;

            // Set tanggal default ke waktu sekarang dengan timezone Asia/Jakarta
            const now = new Date();
            const jakartaTime = new Date(now.getTime() + (7 * 60 * 60 * 1000)); // Tambah 7 jam untuk WIB
            form.querySelector('input[name="orderDate"]').value = jakartaTime.toISOString().slice(0, 16);
            
            // Reset products container dan tambah satu product entry kosong
            const productsContainer = document.getElementById('productsContainer');
            productsContainer.innerHTML = '';
            addProductEntry();
            
            // Tampilkan modal
            modal.style.display = 'block';

        } catch (error) {
            console.error('Error in showAddTransactionModal:', error);
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Terjadi kesalahan saat membuka form: ' + error.message,
                background: '#333',
                color: '#fff'
            });
        }
    }

    // Fungsi untuk mengedit transaksi
    function editTransaction(transaction) {
        try {
            const modal = document.getElementById('transactionModal');
            const form = document.getElementById('transactionForm');
            const modalTitle = document.getElementById('modalTitle');
            
            if (!modal || !form || !modalTitle) {
                throw new Error('Required elements not found');
            }

            // Reset form
            form.reset();
            
            // Update modal title
            modalTitle.innerHTML = `
                <i class="fas fa-edit"></i>
                <span>Edit Transaksi ${transaction._id}</span>
            `;

            // Set nilai-nilai form
            document.getElementById('transactionId').value = transaction._id;
            form.querySelector('input[name="userId"]').value = transaction.userId;
            form.querySelector('select[name="status"]').value = transaction.status.toLowerCase();
            form.querySelector('textarea[name="shippingAddress"]').value = transaction.shippingAddress;

            // Perbaikan handling tanggal dengan timezone
            let orderDate;
            
            // Cek format tanggal dari database
            if (transaction.orderDate.$date) {
                // Jika format MongoDB ISODate
                orderDate = new Date(transaction.orderDate.$date);
            } else if (typeof transaction.orderDate === 'string') {
                // Jika format string
                orderDate = new Date(transaction.orderDate);
            } else {
                // Jika format timestamp atau lainnya
                orderDate = new Date(transaction.orderDate);
            }

            // Format tanggal untuk input datetime-local
            const formattedDate = orderDate.toISOString().slice(0, 16);
            form.querySelector('input[name="orderDate"]').value = formattedDate;

            // Handle products
            const productsContainer = document.getElementById('productsContainer');
            productsContainer.innerHTML = '';

            if (Array.isArray(transaction.products) && transaction.products.length > 0) {
                transaction.products.forEach(product => addProductEntry(product));
            } else {
                addProductEntry();
            }

            // Tampilkan modal
            modal.style.display = 'block';

        } catch (error) {
            console.error('Error in editTransaction:', error);
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Terjadi kesalahan saat mengedit transaksi: ' + error.message,
                background: '#333',
                color: '#fff'
            });
        }
    }

    // Fungsi untuk update tampilan tanggal
    function updateDateDisplay(input) {
        const date = new Date(input.value);
        if (!isNaN(date.getTime())) {
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            const hours = String(date.getHours()).padStart(2, '0');
            const minutes = String(date.getMinutes()).padStart(2, '0');

            // Format tampilan: MM/DD/YYYY HH:MM
            const displayFormat = `${month}/${day}/${year} ${hours}:${minutes}`;
            input.setAttribute('data-display-format', displayFormat);
        }
    }

    // Event listener untuk perubahan input tanggal
    document.addEventListener('DOMContentLoaded', function() {
        const dateInput = document.querySelector('input[name="orderDate"]');
        if (dateInput) {
            // Style untuk menampilkan format custom
            const style = document.createElement('style');
            style.textContent = `
                input[type="datetime-local"] {
                    position: relative;
                }
                input[type="datetime-local"]::-webkit-calendar-picker-indicator {
                    background: transparent;
                    bottom: 0;
                    color: transparent;
                    cursor: pointer;
                    height: auto;
                    left: 0;
                    position: absolute;
                    right: 0;
                    top: 0;
                    width: auto;
                }
            `;
            document.head.appendChild(style);

            // Event listener untuk perubahan nilai
            dateInput.addEventListener('change', function() {
                updateDateDisplay(this);
            });

            // Event listener untuk fokus
            dateInput.addEventListener('focus', function() {
                this.type = 'datetime-local';
            });

            // Event listener untuk blur
            dateInput.addEventListener('blur', function() {
                const displayFormat = this.getAttribute('data-display-format');
                if (displayFormat) {
                    this.type = 'text';
                    this.value = displayFormat;
                }
            });
        }
    });

    // Fungsi untuk menambah product entry (digunakan untuk tambah dan edit)
    function addProductEntry(productData = null) {
        const container = document.getElementById('productsContainer');
        if (!container) return;
        
        const entry = document.createElement('div');
        entry.className = 'product-entry';
        
        const productId = productData?.productId || '';
        const quantity = productData?.jumlah_product || 1;
        const price = productData?.price || '';
        
        entry.innerHTML = `
            <div class="form-group">
                <input type="text" 
                       name="productId[]" 
                       value="${productId}" 
                       placeholder="Product ID" 
                       class="product-input"
                       required>
            </div>
            <div class="form-group">
                <input type="number" 
                       name="jumlah_product[]" 
                       value="${quantity}" 
                       placeholder="Jumlah" 
                       min="1" 
                       class="quantity-input"
                       required>
            </div>
            <div class="form-group">
                <input type="number" 
                       name="price[]" 
                       value="${price}" 
                       placeholder="Harga" 
                       min="0" 
                       class="price-input"
                       required>
            </div>
            <button type="button" class="btn-remove" onclick="removeProductEntry(this)">
                <i class="fas fa-trash"></i>
            </button>
        `;
        container.appendChild(entry);
    }

    // Fungsi untuk menghapus product entry
    function removeProductEntry(button) {
        const entry = button.closest('.product-entry');
        const container = entry.parentElement;
        
        if (container.children.length <= 1) {
            // Jika ini product terakhir, tambah entry kosong baru
            addProductEntry();
        }
        
        entry.remove();
    }

    // Event listener untuk tombol "Tambah Produk"
    document.querySelector('.btn-add-product')?.addEventListener('click', () => {
        addProductEntry();
    });

    // Event listener untuk form submission
    document.getElementById('transactionForm')?.addEventListener('submit', function(e) {
        e.preventDefault();
        
        if (!this.checkValidity()) {
            e.stopPropagation();
            return;
        }
        
        Swal.fire({
            title: 'Konfirmasi',
            text: 'Apakah Anda yakin ingin menyimpan perubahan?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Ya, Simpan',
            cancelButtonText: 'Batal',
            background: '#333',
            color: '#fff'
        }).then((result) => {
            if (result.isConfirmed) {
                this.submit();
            }
        });
    });

    // Fungsi untuk menutup modal (existing)
    function closeModal() {
        const modal = document.getElementById('transactionModal');
        if (modal) {
            modal.style.display = 'none';
        }
    }

    // Event listeners
    document.addEventListener('DOMContentLoaded', function() {
        // Event listener untuk tombol close modal
        const closeButtons = document.querySelectorAll('.close-btn, .btn-cancel');
        closeButtons.forEach(button => {
            button.addEventListener('click', closeModal);
        });

        // Event listener untuk klik di luar modal
        window.addEventListener('click', function(event) {
            const modal = document.getElementById('transactionModal');
            if (event.target === modal) {
                closeModal();
            }
        });
    });

    // Fungsi untuk filter dan sort transaksi
    function filterTransactions() {
        const statusFilter = document.getElementById('statusFilter').value;
        const sortSelect = document.getElementById('sortSelect').value;
        const searchQuery = document.getElementById('searchInput')?.value || '';

        // Buat URL dengan parameter yang diperlukan
        let url = new URL(window.location.href);
        let params = new URLSearchParams(url.search);

        // Update parameter
        if (statusFilter) {
            params.set('status', statusFilter);
        } else {
            params.delete('status');
        }

        if (sortSelect) {
            params.set('sort', sortSelect);
        } else {
            params.delete('sort');
        }

        if (searchQuery) {
            params.set('search', searchQuery);
        } else {
            params.delete('search');
        }

        // Tampilkan loading state
        Swal.fire({
            title: 'Loading...',
            text: 'Sedang memproses permintaan',
            allowOutsideClick: false,
            allowEscapeKey: false,
            allowEnterKey: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        // Redirect ke URL baru dengan parameter yang diupdate
        window.location.href = `${url.pathname}?${params.toString()}`;
    }

    // Fungsi untuk handle pencarian
    function handleSearch(e) {
        e.preventDefault();
        filterTransactions();
    }

    // Event listener untuk form pencarian
    document.querySelector('.search-box')?.addEventListener('submit', handleSearch);

    // Event listener untuk reset filter
    function resetFilters() {
        // Reset semua filter ke default
        document.getElementById('statusFilter').value = '';
        document.getElementById('sortSelect').value = 'newest';
        document.getElementById('searchInput').value = '';
        
        // Trigger filter
        filterTransactions();
    }

    // Tambahkan tombol reset jika diperlukan
    const resetButton = document.createElement('button');
    resetButton.type = 'button';
    resetButton.className = 'btn-reset';
    resetButton.innerHTML = '<i class="fas fa-undo"></i> Reset Filter';
    resetButton.onclick = resetFilters;

    // Tambahkan ke dalam filter-group
    document.querySelector('.filter-group').appendChild(resetButton);

    // CSS untuk tombol reset
    const style = document.createElement('style');
    style.textContent = `
        .btn-reset {
            padding: 10px 15px;
            background: var(--primary-red);
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }

        .btn-reset:hover {
            background: var(--secondary-red);
            transform: translateY(-2px);
        }

        .btn-reset i {
            font-size: 0.9rem;
        }

        @media (max-width: 768px) {
            .btn-reset {
                width: 100%;
                justify-content: center;
                margin-top: 10px;
            }
        }
    `;
    document.head.appendChild(style);

    // Tambahkan indikator filter aktif
    function updateFilterIndicators() {
        const statusFilter = document.getElementById('statusFilter');
        const sortSelect = document.getElementById('sortSelect');

        if (statusFilter.value) {
            statusFilter.classList.add('filter-active');
        } else {
            statusFilter.classList.remove('filter-active');
        }

        if (sortSelect.value !== 'newest') {
            sortSelect.classList.add('filter-active');
        } else {
            sortSelect.classList.remove('filter-active');
        }
    }

    // CSS untuk indikator filter aktif
    const filterStyle = document.createElement('style');
    filterStyle.textContent = `
        .filter-active {
            border-color: var(--primary-blue) !important;
            box-shadow: 0 0 0 2px rgba(0,123,255,0.1);
        }

        select {
            transition: all 0.3s ease;
        }
    `;
    document.head.appendChild(filterStyle);

    // Panggil updateFilterIndicators saat halaman dimuat
    document.addEventListener('DOMContentLoaded', updateFilterIndicators);

    // Update indicators saat filter berubah
    document.getElementById('statusFilter').addEventListener('change', updateFilterIndicators);
    document.getElementById('sortSelect').addEventListener('change', updateFilterIndicators);
</script>


