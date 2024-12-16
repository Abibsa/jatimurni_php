<?php
session_start();
require_once '../config/database.php';

// Set timezone default untuk seluruh aplikasi
date_default_timezone_set('Asia/Jakarta');

function formatDateTime($date, $format = 'FULL') {
    if (!$date) return '';
    
    // Jika input adalah MongoDB UTCDateTime
    if ($date instanceof MongoDB\BSON\UTCDateTime) {
        $dateTime = $date->toDateTime();
    } 
    // Jika input adalah string
    else if (is_string($date)) {
        try {
            $dateTime = new DateTime($date);
        } catch (Exception $e) {
            return 'Format tanggal tidak valid';
        }
    }
    // Jika input tidak valid
    else {
        return 'Format tanggal tidak valid';
    }
    
    // Set timezone ke Asia/Jakarta
    $dateTime->setTimezone(new DateTimeZone('Asia/Jakarta'));
    
    // Format berdasarkan parameter
    switch ($format) {
        case 'DATE_ONLY':
            return $dateTime->format('d/m/Y');
        case 'TIME_ONLY':
            return $dateTime->format('H:i:s');
        case 'SHORT':
            return $dateTime->format('d/m/Y H:i');
        case 'FULL':
        default:
            return $dateTime->format('d/m/Y H:i:s');
    }
}

// Untuk create/update data dengan timezone yang benar
function createMongoDateTime($dateString = null) {
    $timezone = new DateTimeZone('Asia/Jakarta');
    
    if ($dateString) {
        // Jika ada input tanggal
        $date = new DateTime($dateString, $timezone);
    } else {
        // Jika tidak ada input, gunakan waktu sekarang
        $date = new DateTime('now', $timezone);
    }
    
    return new MongoDB\BSON\UTCDateTime($date->getTimestamp() * 1000);
}

$db = new Database();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['action']) && $_POST['action'] === 'delete') {
            try {
                $result = $db->getCollection('payments')->deleteOne(['_id' => $_POST['id']]);
                
                if ($result->getDeletedCount() > 0) {
                    $_SESSION['notification'] = [
                        'type' => 'success',
                        'message' => '🗑️ Pembayaran berhasil dihapus!'
                    ];
                } else {
                    throw new Exception('Gagal menghapus pembayaran');
                }
            } catch (Exception $e) {
                $_SESSION['notification'] = [
                    'type' => 'error',
                    'message' => '❌ Error: ' . $e->getMessage()
                ];
            }
            
            header('Location: payments.php');
            exit;
        } 
        else if (isset($_POST['action']) && $_POST['action'] === 'edit') {
            $dateInput = new DateTime($_POST['paymentDate'], new DateTimeZone('Asia/Jakarta'));
            $timestamp = $dateInput->getTimestamp();
            
            $updateData = [
                '_id' => $_POST['id'],
                'transactionId' => $_POST['transactionId'],
                'amount' => (int)str_replace('.', '', $_POST['amount']),
                'paymentMethod' => strtolower($_POST['paymentMethod']),
                'paymentDate' => createMongoDateTime($_POST['paymentDate']),
                'status' => strtolower($_POST['status'])
            ];

            $result = $db->getCollection('payments')->replaceOne(
                ['_id' => $_POST['id']],
                $updateData
            );

            if ($result->getModifiedCount() > 0) {
                $_SESSION['notification'] = [
                    'type' => 'success',
                    'message' => 'Data pembayaran berhasil diperbarui!'
                ];
            } else {
                throw new Exception('Gagal memperbarui pembayaran');
            }
        }
        else {
            // Handle create dengan ID otomatis
            // Cari pembayaran dengan ID terbesar
            $lastPayment = $db->getCollection('payments')->findOne(
                [], 
                ['sort' => ['_id' => -1]]
            );

            if ($lastPayment) {
                // Jika ID terakhir sudah dalam format p\d{3}
                if (preg_match('/^p(\d{3})$/', $lastPayment->_id, $matches)) {
                    $nextNumber = intval($matches[1]) + 1;
                } else {
                    // Jika masih menggunakan format lain, mulai dari 1
                    $nextNumber = 1;
                }
            } else {
                // Jika belum ada pembayaran sama sekali
                $nextNumber = 1;
            }

            // Format ID baru
            $newId = 'p' . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);
            
            // Pastikan ID baru unik
            while ($db->getCollection('payments')->findOne(['_id' => $newId])) {
                $nextNumber++;
                $newId = 'p' . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);
            }

            // Handle create dengan timezone yang benar
            $now = new DateTime('now', new DateTimeZone('Asia/Jakarta'));
            $timestamp = $now->getTimestamp();
            
            $paymentData = [
                '_id' => $newId,
                'transactionId' => $_POST['transactionId'],
                'amount' => (int)str_replace('.', '', $_POST['amount']),
                'paymentMethod' => strtolower($_POST['paymentMethod']),
                'paymentDate' => createMongoDateTime($_POST['paymentDate']),
                'status' => strtolower($_POST['status'])
            ];

            $result = $db->getCollection('payments')->insertOne($paymentData);
            
            if ($result->getInsertedCount() > 0) {
                $_SESSION['notification'] = [
                    'type' => 'success',
                    'message' => 'Pembayaran baru berhasil ditambahkan!'
                ];
            } else {
                throw new Exception('Gagal menambahkan pembayaran');
            }
        }
        
        header('Location: payments.php');
        exit;
    } catch (Exception $e) {
        $_SESSION['notification'] = [
            'type' => 'error',
            'message' => 'Error: ' . $e->getMessage()
        ];
        header('Location: payments.php');
        exit;
    }
}

// Ambil data payments untuk ditampilkan
$payments = $db->getCollection('payments')->find();

// Setelah semua logika PHP, baru include header
require_once '../templates/header.php';

// Tambahkan SweetAlert2
echo '
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<link href="https://cdn.jsdelivr.net/npm/@sweetalert2/theme-dark@4/dark.css" rel="stylesheet">
';

// Notification System
if (isset($_SESSION['notification'])): ?>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        console.log('Notifikasi akan ditampilkan:', <?php echo json_encode($_SESSION['notification']); ?>); // Debugging
        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true,
            background: '<?php echo $_SESSION['notification']['type'] === 'success' ? '#1a472a' : '#472a2a'; ?>',
            color: '#ffffff',
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

<!-- Header Section -->
<div class="payments-wrapper">
    <div class="page-header">
        <div class="header-content">
            <div class="header-title">
                <h1><i class="fas fa-money-bill-wave"></i> Daftar Pembayaran</h1>
                <p>Kelola semua data pembayaran</p>
            </div>
            <button class="btn btn-primary btn-add" onclick="showAddModal()">
                <i class="fas fa-plus"></i> Tambah Pembayaran
            </button>
        </div>
    </div>

    <!-- Sisa kode table dll -->
    <div class="table-container">
        <table class="payment-table">
            <tr>
                <th>ID</th>
                <th>Transaction ID</th>
                <th>Amount</th>
                <th>Payment Method</th>
                <th>Payment Date</th>
                <th>Status</th>
                <th>Aksi</th>
            </tr>
            <?php foreach ($payments as $payment): ?>
                <?php 
                // Debug: tampilkan struktur data payment
                // error_log('Payment data: ' . print_r($payment, true));
                ?>
                <tr>
                    <td><?php echo $payment['_id']; ?></td>
                    <td><?php echo $payment['transactionId']; ?></td>
                    <td>Rp <?php echo number_format($payment['amount'], 0, ',', '.'); ?></td>
                    <td><?php echo $payment['paymentMethod']; ?></td>
                    <td><?php 
                        $paymentDate = $payment['paymentDate'] instanceof MongoDB\BSON\UTCDateTime 
                            ? formatDateTime($payment['paymentDate'])
                            : formatDateTime($payment['paymentDate']); 
                        echo $paymentDate;
                    ?></td>
                    <td class="status-<?php echo strtolower($payment['status']); ?>">
                        <?php echo $payment['status']; ?>
                    </td>
                    <td class="action-buttons">
                        <button type="button" class="btn-edit" onclick='showEditModal(<?php 
                            // Persiapkan data payment untuk dikirim ke JavaScript
                            $paymentData = [
                                "_id" => $payment["_id"],
                                "transactionId" => $payment["transactionId"],
                                "amount" => $payment["amount"],
                                "paymentMethod" => $payment["paymentMethod"],
                                "paymentDate" => $payment["paymentDate"]->toDateTime()->format('Y-m-d\TH:i'),
                                "status" => $payment["status"]
                            ];
                            echo json_encode($paymentData, JSON_HEX_APOS | JSON_HEX_QUOT); 
                        ?>)'>
                            <i class="fas fa-edit"></i>
                        </button>
                        <button type="button" class="btn-delete" onclick="confirmDelete('<?php echo $payment->_id; ?>')">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>
</div>

<!-- Modal Tambah/Edit Pembayaran -->
<div id="paymentModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="modalTitle">
                <i class="fas fa-plus-circle"></i>
                <span>Tambah Pembayaran Baru</span>
            </h2>
            <button type="button" class="close-btn" onclick="closeModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <form id="paymentForm" method="POST">
            <input type="hidden" id="paymentId" name="id">
            
            <div class="modal-body">
                <!-- Transaction ID -->
                <div class="form-group">
                    <label>Transaction ID</label>
                    <div class="input-group">
                        <i class="fas fa-shopping-cart"></i>
                        <input type="text" 
                               id="transactionId" 
                               name="transactionId" 
                               required 
                               pattern="t\d{3}"
                               placeholder="Format: t001">
                    </div>
                </div>

                <!-- Amount -->
                <div class="form-group">
                    <label>Jumlah Pembayaran</label>
                    <div class="input-group">
                        <i class="fas fa-money-bill"></i>
                        <input type="text" 
                               id="amount" 
                               name="amount" 
                               required 
                               placeholder="0">
                    </div>
                </div>

                <!-- Payment Method -->
                <div class="form-group">
                    <label>Metode Pembayaran</label>
                    <div class="input-group">
                        <i class="fas fa-credit-card"></i>
                        <select id="paymentMethod" name="paymentMethod" required>
                            <option value="">Pilih metode pembayaran</option>
                            <option value="cash">Cash</option>
                            <option value="transfer">Transfer Bank</option>
                            <option value="ewallet">E-Wallet</option>
                        </select>
                    </div>
                </div>

                <!-- Payment Date -->
                <div class="form-group">
                    <label>Tanggal Pembayaran</label>
                    <div class="input-group">
                        <i class="fas fa-calendar"></i>
                        <input type="datetime-local" 
                               id="paymentDate" 
                               name="paymentDate" 
                               required>
                    </div>
                </div>

                <!-- Status -->
                <div class="form-group">
                    <label>Status</label>
                    <div class="input-group">
                        <i class="fas fa-info-circle"></i>
                        <select id="status" name="status" required>
                            <option value="">Pilih status</option>
                            <option value="pending">Pending</option>
                            <option value="success">Success</option>
                            <option value="failed">Failed</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal()">
                    <i class="fas fa-times"></i> Batal
                </button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Simpan
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Fungsi untuk menampilkan modal tambah pembayaran
function showAddModal() {
    resetForm();
    document.getElementById('modalTitle').innerHTML = `
        <i class="fas fa-plus-circle"></i>
        <span>Tambah Pembayaran Baru</span>
    `;
    document.getElementById('paymentModal').style.display = 'block';
}

// Fungsi untuk menampilkan modal edit pembayaran
function showEditModal(payment) {
    const form = document.getElementById('paymentForm');
    if (!form) return;

    // Mengubah judul modal
    document.getElementById('modalTitle').innerHTML = `
        <i class="fas fa-edit"></i>
        <span>Edit Pembayaran: ${payment._id}</span>
    `;
    
    // Menambahkan input hidden untuk action
    let actionInput = form.querySelector('input[name="action"]');
    if (!actionInput) {
        actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        form.appendChild(actionInput);
    }
    actionInput.value = 'edit';
    
    // Mengisi form dengan data yang ada
    document.getElementById('paymentId').value = payment._id;
    document.getElementById('transactionId').value = payment.transactionId;
    document.getElementById('amount').value = new Intl.NumberFormat('id-ID').format(payment.amount);
    document.getElementById('paymentMethod').value = payment.paymentMethod;
    document.getElementById('paymentDate').value = payment.paymentDate;
    document.getElementById('status').value = payment.status;
    
    // Menampilkan modal
    document.getElementById('paymentModal').style.display = 'block';
}

// Fungsi untuk mereset form
function resetForm() {
    const form = document.getElementById('paymentForm');
    if (!form) return;

    form.reset();
    document.getElementById('paymentId').value = '';
    
    // Hapus input action jika ada
    const actionInput = form.querySelector('input[name="action"]');
    if (actionInput) actionInput.remove();
}

// Fungsi untuk menutup modal
function closeModal() {
    document.getElementById('paymentModal').style.display = 'none';
    resetForm();
}

// Format input jumlah pembayaran
document.getElementById('amount').addEventListener('input', function(e) {
    let value = this.value.replace(/\D/g, '');
    if (value === '') return;
    this.value = new Intl.NumberFormat('id-ID').format(value);
});

// Validasi form sebelum submit
document.getElementById('paymentForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    // Validasi format Transaction ID
    const transactionId = document.getElementById('transactionId').value;
    
    if (!transactionId.match(/^t\d{3}$/)) {
        Swal.fire({
            icon: 'error',
            title: 'Format Transaction ID salah',
            text: 'Format yang benar: t001, t002, dst',
            background: '#472a2a',
            color: '#ffffff'
        });
        return;
    }
    
    this.submit();
});

// Fungsi untuk menampilkan konfirmasi hapus
function confirmDelete(paymentId) {
    Swal.fire({
        title: 'Konfirmasi Hapus',
        text: 'Apakah Anda yakin ingin menghapus pembayaran ini?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef5350',
        cancelButtonColor: '#333',
        confirmButtonText: 'Ya, Hapus!',
        cancelButtonText: 'Batal',
        background: '#1a1a1a',
        color: '#ffffff'
    }).then((result) => {
        if (result.isConfirmed) {
            // Buat form untuk submit
            const form = document.createElement('form');
            form.method = 'POST';
            form.style.display = 'none';

            // Tambahkan input untuk ID dan action
            const idInput = document.createElement('input');
            idInput.type = 'hidden';
            idInput.name = 'id';
            idInput.value = paymentId;

            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'delete';

            // Tambahkan input ke form
            form.appendChild(idInput);
            form.appendChild(actionInput);

            // Tambahkan form ke document dan submit
            document.body.appendChild(form);
            form.submit();
        }
    });
}
</script>

<style>
/* Container Styles */
.payments-wrapper {
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

/* Controls Section */
.payments-controls{
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

/* Filter Group */
.filter-group {
    display: flex;
    gap: 15px;
}

.filter-group select {
    padding: 10px 15px;
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 8px;
    background: rgba(0,0,0,0.7);
    color: var(--primary-white);
    cursor: pointer;
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
    background: rgba(230,57,70,0.1);
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

/* Table Styles */
.table-container {
    background: var(--secondary-black);
    border-radius: 10px;
    padding: 20px;
    overflow-x: auto;
}

.payment-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 20px;
}

.payment-table th {
    background: rgba(255,255,255,0.05);
    padding: 15px;
    text-align: left;
    font-weight: 500;
    color: var(--primary-white);
}

.payment-table td {
    padding: 15px;
    border-bottom: 1px solid rgba(255,255,255,0.05);
    color: var(--primary-white);
}

/* Status Colors */
.status-pending {
    background: #ffa726;
    color: #fff;
    padding: 5px 10px;
    border-radius: 5px;
    font-size: 0.9em;
}

.status-success {
    background: #66bb6a;
    color: #fff;
    padding: 5px 10px;
    border-radius: 5px;
    font-size: 0.9em;
}

.status-failed {
    background: #ef5350;
    color: #fff;
    padding: 5px 10px;
    border-radius: 5px;
    font-size: 0.9em;
}

/* Responsive Design */
@media (max-width: 768px) {
    .payment-table {
        display: block;
        overflow-x: auto;
        white-space: nowrap;
    }
}

/* Modal Styles untuk Payments */
.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.7);
    z-index: 1000;
    animation: fadeIn 0.3s ease;
}

.modal-content {
    background: var(--secondary-black);
    border-radius: 15px;
    max-width: 600px; /* Lebih kecil dari transactions karena form pembayaran lebih sederhana */
    width: 90%;
    margin: 30px auto;
    position: relative;
    max-height: 85vh;
    overflow-y: auto;
    box-shadow: 0 5px 20px rgba(0,0,0,0.3);
    animation: slideIn 0.3s ease;
}

.modal-header {
    background: rgba(255,255,255,0.03);
    padding: 20px;
    border-radius: 15px 15px 0 0;
    border-bottom: 1px solid rgba(255,255,255,0.1);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h2 {
    color: var(--primary-white);
    font-size: 1.5rem;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 10px;
}

.modal-body {
    padding: 25px;
}

/* Form Styling */
.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    color: var(--primary-white);
    font-weight: 500;
}

.input-group {
    position: relative;
    display: flex;
    align-items: center;
}

.input-group i {
    position: absolute;
    left: 12px;
    color: #666;
    font-size: 1rem;
    transition: color 0.3s ease;
}

.input-group input,
.input-group select {
    width: 100%;
    padding: 10px 10px 10px 40px;
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 8px;
    background: rgba(255,255,255,0.05);
    color: var(--primary-white);
    font-size: 1rem;
    transition: all 0.3s ease;
}

.input-group input:focus,
.input-group select:focus {
    border-color: var(--primary-blue);
    box-shadow: 0 0 0 3px rgba(0,123,255,0.1);
    outline: none;
}

.input-group input:focus + i,
.input-group select:focus + i {
    color: var(--primary-blue);
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

/* Close Button */
.close-btn {
    position: relative;
    width: 32px;
    height: 32px;
    background: rgba(255,255,255,0.05);
    border: none;
    border-radius: 8px;
    color: #888;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
}

.close-btn:hover {
    color: white;
    transform: rotate(90deg);
    background: var(--primary-red);
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
    .modal-content {
        margin: 15px;
        width: calc(100% - 30px);
    }
    
    .form-actions {
        flex-direction: column;
    }
    
    .btn-cancel,
    .btn-submit {
        width: 100%;
        justify-content: center;
    }
}

/* Status Badge Styles */
.status-badge {
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 0.85em;
    font-weight: 500;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.status-pending {
    background: #ffa726;
    color: #fff;
}

.status-completed {
    background: #66bb6a;
    color: #fff;
}

.status-failed {
    background: #ef5350;
    color: #fff;
}

/* Amount Input Styling */
.amount-input-group {
    position: relative;
}

.amount-input-group::before {
    content: 'Rp';
    position: absolute;
    left: 40px;
    top: 50%;
    transform: translateY(-50%);
    color: #666;
    z-index: 1;
}

.amount-input-group input {
    padding-left: 65px !important;
}

/* Tambahkan style untuk notifikasi */
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

/* Styling untuk tombol aksi */
.action-buttons {
    display: flex;
    gap: 8px;
}

.btn-edit,
.btn-delete {
    padding: 8px;
    border: none;
    border-radius: 5px;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
}

.btn-edit {
    background: var(--primary-blue);
    color: white;
}

.btn-delete {
    background: var(--primary-red);
    color: white;
}

.btn-edit:hover,
.btn-delete:hover {
    transform: translateY(-2px);
    box-shadow: 0 2px 5px rgba(0,0,0,0.2);
}

.btn-edit:hover {
    background: var(--secondary-blue);
}

.btn-delete:hover {
    background: var(--secondary-red);
}

/* SweetAlert custom styling */
.swal2-popup {
    background: var(--secondary-black) !important;
    border: 1px solid rgba(255,255,255,0.1);
}

.swal2-title, 
.swal2-html-container {
    color: var(--primary-white) !important;
}

.swal2-confirm {
    background: var(--primary-red) !important;
}

.swal2-cancel {
    background: var(--secondary-black) !important;
    border: 1px solid rgba(255,255,255,0.1) !important;
}

/* Toast notification styling */
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

/* Modal Footer Styling */
.modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    padding: 20px;
    border-top: 1px solid rgba(255,255,255,0.1);
    background: rgba(255,255,255,0.03);
    border-radius: 0 0 15px 15px;
}

/* Button Styling */
.modal-footer .btn {
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

/* Secondary Button (Batal) */
.modal-footer .btn-secondary {
    background: rgba(255,255,255,0.05);
    color: var(--primary-white);
    border: 1px solid rgba(255,255,255,0.1);
}

.modal-footer .btn-secondary:hover {
    background: rgba(255,255,255,0.1);
    transform: translateY(-2px);
}

/* Primary Button (Simpan) */
.modal-footer .btn-primary {
    background: linear-gradient(45deg, var(--primary-blue), #0056b3);
    color: white;
    box-shadow: 0 4px 15px rgba(0,123,255,0.2);
}

.modal-footer .btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(0,123,255,0.3);
}

/* Active State */
.modal-footer .btn:active {
    transform: translateY(0);
}

/* Icon Styling */
.modal-footer .btn i {
    font-size: 0.9rem;
}

/* Responsive Design */
@media (max-width: 768px) {
    .modal-footer {
        flex-direction: column;
        gap: 10px;
    }

    .modal-footer .btn {
        width: 100%;
        justify-content: center;
    }
    
    .modal-footer .btn-secondary {
        order: 2;
    }
    
    .modal-footer .btn-primary {
        order: 1;
    }
}

/* Select dan Option Styling */
select {
    width: 100%;
    padding: 10px 15px;
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 8px;
    background: rgba(0, 0, 0, 0.7);
    color: #ffffff;
    cursor: pointer;
    font-size: 0.9rem;
    transition: all 0.3s ease;
    -webkit-appearance: none;
    -moz-appearance: none;
    appearance: none;
    background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='white' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
    background-repeat: no-repeat;
    background-position: right 10px center;
    background-size: 1em;
}

/* Option Styling */
select option {
    background: var(--secondary-black);
    color: #ffffff;
    padding: 12px;
}

/* Placeholder Option */
select option[value=""] {
    color: #666;
}

/* Payment Method Specific Colors */
select option[value="cash"] {
    color: #4CAF50; /* Hijau untuk Cash */
}

select option[value="transfer"] {
    color: #2196F3; /* Biru untuk Transfer Bank */
}

select option[value="ewallet"] {
    color: #FF9800; /* Orange untuk E-Wallet */
}

/* Hover State */
select:hover {
    border-color: var(--primary-blue);
    background-color: rgba(0, 0, 0, 0.8);
}

/* Focus State */
select:focus {
    border-color: var(--primary-blue);
    box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.2);
    outline: none;
}

/* Disabled State */
select:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

/* Styling untuk dropdown saat dibuka */
select:focus option:checked {
    background: linear-gradient(0deg, var(--primary-blue) 0%, var(--primary-blue) 100%);
    color: white;
}

/* Responsive Design */
@media (max-width: 768px) {
    select {
        font-size: 16px; /* Mencegah zoom pada iOS */
        padding: 12px 15px;
    }
}

/* Select Status Styling */
select[name="status"] {
    width: 100%;
    padding: 10px 15px;
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 8px;
    background: rgba(0, 0, 0, 0.7);
    color: #ffffff;
    cursor: pointer;
    font-size: 0.9rem;
    transition: all 0.3s ease;
    -webkit-appearance: none;
    -moz-appearance: none;
    appearance: none;
    background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='white' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
    background-repeat: no-repeat;
    background-position: right 10px center;
    background-size: 1em;
}

/* Option Container */
select[name="status"] option {
    background: var(--secondary-black);
    padding: 12px;
}

/* Placeholder Option */
select[name="status"] option[value=""] {
    color: #666;
}

/* Status-specific Colors */
select[name="status"] option[value="pending"] {
    color: #FFA726; /* Warna orange untuk Pending */
}

select[name="status"] option[value="success"] {
    color: #66BB6A; /* Warna hijau untuk Success */
}

select[name="status"] option[value="failed"] {
    color: #EF5350; /* Warna merah untuk Failed */
}

/* Hover State */
select[name="status"]:hover {
    border-color: var(--primary-blue);
    background-color: rgba(0, 0, 0, 0.8);
}

/* Focus State */
select[name="status"]:focus {
    border-color: var(--primary-blue);
    box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.2);
    outline: none;
}

/* Selected Option Styling */
select[name="status"]:focus option:checked {
    background: linear-gradient(0deg, var(--primary-blue) 0%, var(--primary-blue) 100%);
    color: white;
}

/* Disabled State */
select[name="status"]:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

/* Responsive Design */
@media (max-width: 768px) {
    select[name="status"] {
        font-size: 16px;
        padding: 12px 15px;
    }
}

/* Select Status Container */
.input-group select {
    width: 100%;
    padding: 12px 40px 12px 40px; /* Padding seimbang kiri-kanan */
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 8px;
    background: rgba(0, 0, 0, 0.7);
    color: #ffffff;
    cursor: pointer;
    font-size: 14px;
    font-weight: 500;
    letter-spacing: 0.3px;
    line-height: 1.5;
    text-transform: capitalize; /* Kapitalisasi huruf pertama */
    transition: all 0.3s ease;
    -webkit-appearance: none;
    -moz-appearance: none;
    appearance: none;
    background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='white' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
    background-repeat: no-repeat;
    background-position: right 10px center;
    background-size: 1em;
}

/* Status Options */
.input-group select option {
    padding: 12px 16px;
    font-size: 14px;
    font-weight: 500;
    line-height: 1.5;
    letter-spacing: 0.3px;
    text-transform: capitalize;
    background: var(--secondary-black);
}

/* Placeholder dengan style khusus */
.input-group select option[value=""] {
    color: #666;
    font-style: italic;
}

/* Status Colors dengan style yang lebih rapi */
.input-group select option[value="pending"] {
    color: #FFA726;
    font-weight: 500;
}

.input-group select option[value="success"] {
    color: #66BB6A;
    font-weight: 500;
}

.input-group select option[value="failed"] {
    color: #EF5350;
    font-weight: 500;
}

/* Selected Option Style */
.input-group select:not([value=""]) {
    font-weight: 500;
    color: #ffffff;
}
</style>

<?php require_once '../templates/footer.php'; ?>
