<?php
require_once '../config/database.php';
session_start();

// Prevent form resubmission
if (isset($_SESSION['last_delete_id']) && isset($_POST['delete_id'])) {
    if ($_SESSION['last_delete_id'] === $_POST['delete_id']) {
        header("Location: users.php");
        exit();
    }
}

if (isset($_POST['delete_id'])) {
    $_SESSION['last_delete_id'] = $_POST['delete_id'];
}

$db = new Database();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
    // Generate user ID dengan format u001, u002, dst
    $lastUser = $db->getCollection('users')->find([], ['sort' => ['_id' => -1], 'limit' => 1])->toArray();
    $nextId = 'u001';
    if (!empty($lastUser)) {
        $lastId = $lastUser[0]['_id'];
        $numericPart = intval(substr($lastId, 1)) + 1;
        $nextId = 'u' . str_pad($numericPart, 3, '0', STR_PAD_LEFT);
    }

    // Validasi input
    if (empty($_POST['email']) || empty($_POST['nama']) || empty($_POST['username']) || 
        empty($_POST['password']) || empty($_POST['alamat']) || empty($_POST['nomer_telepon'])) {
        $_SESSION['error'] = "Semua field harus diisi";
        header("Location: users.php");
        exit();
    }

    // Validasi password
    if (strlen($_POST['password']) < 6) {
        $_SESSION['error'] = "Password harus minimal 6 karakter";
        header("Location: users.php");
        exit();
    }

    $hashedPassword = password_hash($_POST['password'], PASSWORD_DEFAULT);

    // Validasi nomor telepon (hanya angka)
    if (!is_numeric($_POST['nomer_telepon'])) {
        $_SESSION['error'] = "Nomor telepon harus berupa angka";
        header("Location: users.php");
        exit();
    }

    $newUser = [
        '_id' => $nextId,
        'email' => $_POST['email'],
        'nama' => $_POST['nama'],
        'account' => [[
            'username' => $_POST['username'],
            'password' => $hashedPassword
        ]],
        'alamat' => $_POST['alamat'],
        'nomer_telepon' => $_POST['nomer_telepon'],
        'role' => isset($_POST['role']) ? $_POST['role'] : 'customer' // Default ke customer
    ];

    try {
        // Cek apakah email atau username sudah ada
        $existingUser = $db->getCollection('users')->findOne([
            '$or' => [
                ['email' => $_POST['email']],
                ['account.username' => $_POST['username']]
            ]
        ]);

        if ($existingUser) {
            $_SESSION['error'] = "Email atau username sudah digunakan";
            header("Location: users.php");
            exit();
        }

        $db->getCollection('users')->insertOne($newUser);
        $_SESSION['success'] = "User berhasil ditambahkan";
    } catch (Exception $e) {
        $_SESSION['error'] = "Error: " . $e->getMessage();
    }
    header("Location: users.php");
    exit();
}

// Tambahkan fungsi untuk handle edit user
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_user'])) {
    try {
        $userId = $_POST['user_id'];
        $currentUser = $db->getCollection('users')->findOne(['_id' => $userId]);

        // Validasi user ditemukan
        if (!$currentUser) {
            $_SESSION['error'] = "User tidak ditemukan";
            header("Location: users.php");
            exit();
        }

        // Cek jika mengubah role admin terakhir
        if ($currentUser['role'] === 'admin' && $_POST['role'] === 'customer') {
            $adminCount = $db->getCollection('users')->countDocuments(['role' => 'admin']);
            if ($adminCount <= 1) {
                $_SESSION['error'] = "Tidak dapat mengubah role admin terakhir!";
                header("Location: users.php");
                exit();
            }
        }

        // Siapkan data update
        $updateData = [
            'email' => $_POST['email'],
            'nama' => $_POST['nama'],
            'alamat' => $_POST['alamat'],
            'nomer_telepon' => $_POST['nomer_telepon'],
            'role' => $_POST['role']
        ];

        // Update password jika diisi
        if (!empty($_POST['password'])) {
            if (strlen($_POST['password']) < 6) {
                $_SESSION['error'] = "Password baru harus minimal 6 karakter";
                header("Location: users.php");
                exit();
            }
            $updateData['account.0.password'] = password_hash($_POST['password'], PASSWORD_DEFAULT);
        }

        // Lakukan update
        $result = $db->getCollection('users')->updateOne(
            ['_id' => $userId],
            ['$set' => $updateData]
        );

        if ($result->getModifiedCount() > 0) {
            $_SESSION['success'] = "User berhasil diperbarui";
        } else {
            $_SESSION['error'] = "Tidak ada perubahan data";
        }

    } catch (Exception $e) {
        $_SESSION['error'] = "Error: " . $e->getMessage();
    }
    
    header("Location: users.php");
    exit();
}

// Handle delete user
if (isset($_POST['delete_user'])) {
    try {
        $userId = $_POST['user_id'];
        
        // Cek apakah user yang akan dihapus ada
        $user = $db->getCollection('users')->findOne(['_id' => $userId]);
        
        if (!$user) {
            $_SESSION['error'] = "User tidak ditemukan";
            header("Location: users.php");
            exit();
        }
        
        // Cek apakah user yang akan dihapus adalah admin
        if ($user['role'] === 'admin') {
            // Hitung jumlah admin yang ada
            $adminCount = $db->getCollection('users')->countDocuments(['role' => 'admin']);
            
            if ($adminCount <= 1) {
                $_SESSION['error'] = "Tidak dapat menghapus admin terakhir! Sistem membutuhkan minimal satu admin.";
                header("Location: users.php");
                exit();
            }
        }
        
        $deleteResult = $db->getCollection('users')->deleteOne(['_id' => $userId]);
        
        if ($deleteResult->getDeletedCount() > 0) {
            $_SESSION['success'] = "User berhasil dihapus";
            
            // Tampilkan notifikasi sukses
            echo "<script>
                Swal.fire({
                    icon: 'success',
                    title: 'Berhasil!',
                    text: 'User berhasil dihapus',
                    background: '#2d2d2d',
                    color: '#fff',
                    confirmButtonColor: '#28a745',
                    timer: 2000,
                    timerProgressBar: true
                });
            </script>";
        } else {
            $_SESSION['error'] = "Gagal menghapus user";
        }
        
        header("Location: users.php");
        exit();
    } catch (Exception $e) {
        $_SESSION['error'] = "Error: " . $e->getMessage();
        header("Location: users.php");
        exit();
    }
}

require_once '../templates/header.php';

// Tampilkan notifikasi dengan SweetAlert2
if (isset($_SESSION['success'])) {
    echo "<script>
        Swal.fire({
            icon: 'success',
            title: 'Berhasil!',
            text: '{$_SESSION['success']}',
            showConfirmButton: false,
            timer: 2000,
            timerProgressBar: true,
            toast: true,
            position: 'top-end',
            background: '#a5dc86',
            color: '#fff',
            iconColor: '#fff'
        });
    </script>";
    unset($_SESSION['success']);
}

if (isset($_SESSION['error'])) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Oops...',
            text: '{$_SESSION['error']}',
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true,
            toast: true,
            position: 'top-end',
            background: '#f27474',
            color: '#fff',
            iconColor: '#fff'
        });
    </script>";
    unset($_SESSION['error']);
}

$users = $db->getCollection('users')
    ->find([], ['sort' => ['_id' => 1]])
    ->toArray();

// Debug: cek jumlah user sebelum ditampilkan
error_log("Total users found: " . count($users));

// Ganti bagian header container dengan yang baru
echo "<div class='transactions-wrapper'>
    <div class='page-header'>
        <div class='header-content'>
            <div class='header-title'>
                <h1><i class='fas fa-users'></i> Users Management</h1>
                <p>Kelola data pengguna sistem</p>
            </div>
            <div class='header-actions'>
                <button class='btn-add' onclick='openModal()'><i class='fas fa-plus'></i> Tambah User</button>
            </div>
        </div>
    </div>";

// Ubah tampilan tabel untuk menambahkan style dan icon yang lebih baik
echo "<table class='data-table'>
<thead>
    <tr>
        <th>ID</th>
        <th>Email</th>
        <th>Nama</th>
        <th>Username</th>
        <th>Alamat</th>
        <th>No. Telepon</th>
        <th>Role</th>
        <th>Aksi</th>
    </tr>
</thead>
<tbody>";

foreach ($users as $user) {
    $username = isset($user['account'][0]['username']) ? $user['account'][0]['username'] : 'N/A';
    $email = isset($user['email']) ? htmlspecialchars($user['email']) : '';
    $nama = isset($user['nama']) ? htmlspecialchars($user['nama']) : '';
    $alamat = isset($user['alamat']) ? htmlspecialchars($user['alamat']) : '';
    $nomer_telepon = isset($user['nomer_telepon']) ? $user['nomer_telepon'] : 0;
    $role = isset($user['role']) ? $user['role'] : 'customer';
    
    echo "<tr>
        <td>{$user['_id']}</td>
        <td>{$email}</td>
        <td>{$nama}</td>
        <td>{$username}</td>
        <td>{$alamat}</td>
        <td>{$nomer_telepon}</td>
        <td><span class='badge badge-" . ($role === 'admin' ? 'admin' : 'customer') . "'>{$role}</span></td>
        <td class='actions'>
            <button class='btn-action btn-warning' onclick='openEditModal(\"" . 
                htmlspecialchars($user['_id']) . "\", \"" . 
                htmlspecialchars($user['email']) . "\", \"" . 
                htmlspecialchars($user['nama']) . "\", \"" . 
                htmlspecialchars($username) . "\", \"" . 
                htmlspecialchars($user['alamat']) . "\", \"" . 
                htmlspecialchars($user['nomer_telepon']) . "\", \"" . 
                htmlspecialchars($user['role']) . 
                "\")'><i class='fas fa-edit'></i></button>
            <form method='POST' style='display: inline;' onsubmit='event.preventDefault(); confirmDelete(this);'>
                <input type='hidden' name='delete_user' value='1'>
                <input type='hidden' name='user_id' value='{$user['_id']}'>
                <button type='submit' class='btn-action btn-danger'><i class='fas fa-trash'></i></button>
            </form>
        </td>
    </tr>";
}

echo "</tbody></table>";

// Perbarui modal form untuk Add User
?>
<div id="addModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <div class="header-icon">
                <i class="fas fa-user-plus"></i>
            </div>
            <div class="header-text">
                <h2>Add New User</h2>
                <p>Tambahkan pengguna baru ke sistem</p>
            </div>
            <span class="close" onclick="closeAddModal()">&times;</span>
        </div>

        <form method="POST" class="modal-form">
            <input type="hidden" name="add_user" value="1">
            
            <div class="form-section">
                <h3><i class="fas fa-info-circle"></i> Informasi Dasar</h3>
                <div class="form-row">
                    <div class="form-group">
                        <label><i class="fas fa-envelope"></i> Email</label>
                        <div class="input-group">
                            <input type="email" name="email" placeholder="Masukkan email" required>
                            <span class="input-icon"><i class="fas fa-envelope"></i></span>
                        </div>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-user"></i> Nama Lengkap</label>
                        <div class="input-group">
                            <input type="text" name="nama" placeholder="Masukkan nama lengkap" required>
                            <span class="input-icon"><i class="fas fa-user"></i></span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h3><i class="fas fa-lock"></i> Informasi Akun</h3>
                <div class="form-row">
                    <div class="form-group">
                        <label><i class="fas fa-user-circle"></i> Username</label>
                        <div class="input-group">
                            <input type="text" name="username" placeholder="Masukkan username" required>
                            <span class="input-icon"><i class="fas fa-user-circle"></i></span>
                        </div>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-key"></i> Password</label>
                        <div class="input-group">
                            <input type="password" name="password" id="password" placeholder="Masukkan password" required>
                            <span class="input-icon toggle-password" onclick="togglePassword('password')">
                                <i class="fas fa-eye-slash"></i>
                            </span>
                        </div>
                        <small class="input-hint">Minimal 6 karakter</small>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h3><i class="fas fa-address-card"></i> Informasi Kontak</h3>
                <div class="form-row">
                    <div class="form-group">
                        <label><i class="fas fa-map-marker-alt"></i> Alamat</label>
                        <div class="input-group">
                            <input type="text" name="alamat" placeholder="Masukkan alamat lengkap" required>
                            <span class="input-icon"><i class="fas fa-map-marker-alt"></i></span>
                        </div>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-phone"></i> Nomor Telepon</label>
                        <div class="input-group">
                            <input type="number" name="nomer_telepon" placeholder="Masukkan nomor telepon" required>
                            <span class="input-icon"><i class="fas fa-phone"></i></span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h3><i class="fas fa-user-tag"></i> Role Pengguna</h3>
                <div class="form-group">
                    <div class="role-selector">
                        <label class="role-option">
                            <input type="radio" name="role" value="customer" checked>
                            <span class="role-box">
                                <i class="fas fa-user"></i>
                                <span>Customer</span>
                            </span>
                        </label>
                        <label class="role-option">
                            <input type="radio" name="role" value="admin">
                            <span class="role-box">
                                <i class="fas fa-user-shield"></i>
                                <span>Admin</span>
                            </span>
                        </label>
                    </div>
                </div>
            </div>

            <div class="form-actions">
                <button type="button" class="btn-cancel" onclick="closeAddModal()">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="submit" class="btn-submit">
                    <i class="fas fa-save"></i> Save User
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Edit User -->
<div id="editModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <div class="header-icon">
                <i class="fas fa-user-edit"></i>
            </div>
            <div class="header-text">
                <h2>Edit User</h2>
                <p>Perbarui informasi pengguna</p>
            </div>
            <span class="close" onclick="closeEditModal()">&times;</span>
        </div>

        <form method="POST" id="editUserForm" class="modal-form">
            <input type="hidden" name="edit_user" value="1">
            <input type="hidden" name="user_id" id="edit_user_id">

            <div class="form-section">
                <h3><i class="fas fa-info-circle"></i> Informasi Dasar</h3>
                <div class="form-row">
                    <div class="form-group">
                        <label><i class="fas fa-envelope"></i> Email</label>
                        <div class="input-group">
                            <input type="email" name="email" id="edit_email" required>
                            <span class="input-icon"><i class="fas fa-envelope"></i></span>
                        </div>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-user"></i> Nama Lengkap</label>
                        <div class="input-group">
                            <input type="text" name="nama" id="edit_nama" required>
                            <span class="input-icon"><i class="fas fa-user"></i></span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h3><i class="fas fa-lock"></i> Informasi Akun</h3>
                <div class="form-row">
                    <div class="form-group">
                        <label><i class="fas fa-user-circle"></i> Username</label>
                        <div class="input-group">
                            <input type="text" name="username" id="edit_username" readonly>
                            <span class="input-icon"><i class="fas fa-user-circle"></i></span>
                        </div>
                        <small class="input-hint">Username tidak dapat diubah</small>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-key"></i> Password Baru</label>
                        <div class="input-group">
                            <input type="password" name="password" id="edit_password">
                            <span class="input-icon toggle-password" onclick="togglePassword('edit_password')">
                                <i class="fas fa-eye-slash"></i>
                            </span>
                        </div>
                        <small class="input-hint">Kosongkan jika tidak ingin mengubah password</small>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h3><i class="fas fa-address-card"></i> Informasi Kontak</h3>
                <div class="form-row">
                    <div class="form-group">
                        <label><i class="fas fa-map-marker-alt"></i> Alamat</label>
                        <div class="input-group">
                            <input type="text" name="alamat" id="edit_alamat" required>
                            <span class="input-icon"><i class="fas fa-map-marker-alt"></i></span>
                        </div>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-phone"></i> Nomor Telepon</label>
                        <div class="input-group">
                            <input type="number" name="nomer_telepon" id="edit_nomer_telepon" required>
                            <span class="input-icon"><i class="fas fa-phone"></i></span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h3><i class="fas fa-user-tag"></i> Role Pengguna</h3>
                <div class="form-group">
                    <div class="role-selector">
                        <label class="role-option">
                            <input type="radio" name="role" value="customer" id="edit_role_customer">
                            <span class="role-box">
                                <i class="fas fa-user"></i>
                                <span>Customer</span>
                            </span>
                        </label>
                        <label class="role-option">
                            <input type="radio" name="role" value="admin" id="edit_role_admin">
                            <span class="role-box">
                                <i class="fas fa-user-shield"></i>
                                <span>Admin</span>
                            </span>
                        </label>
                    </div>
                </div>
            </div>

            <div class="form-actions">
                <button type="button" class="btn-cancel" onclick="closeEditModal()">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="submit" class="btn-submit">
                    <i class="fas fa-save"></i> Update User
                </button>
            </div>
        </form>
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
    background-color: var(--primary-blue);
    color: white;
    padding: 12px 24px;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-size: 14px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 8px;
    transition: all 0.3s ease;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
}

.btn-add:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 12px rgba(0,0,0,0.15);
    background-color: var(--secondary-blue);
}

.btn-add:active {
    transform: translateY(0);
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
}

.modal {
    display: none;
    position: fixed;
    z-index: 1;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.4);
    overflow-y: auto;
}

.modal-content {
    background-color: #fefefe;
    margin: 2% auto;
    padding: 30px;
    border: none;
    border-radius: 10px;
    width: 90%;
    max-width: 500px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    position: relative;
    max-height: 90vh;
    overflow-y: auto;
}

.modal h2 {
    color: #333;
    text-align: center;
    margin-bottom: 30px;
    font-size: 24px;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    color: #555;
    font-weight: 500;
}

.form-group input, .form-group select {
    width: 100%;
    padding: 12px;
    border: 1px solid #ddd;
    border-radius: 6px;
    font-size: 14px;
    transition: border-color 0.3s ease;
    box-sizing: border-box;
}

.form-group input:focus, .form-group select:focus {
    outline: none;
    border-color: #4CAF50;
    box-shadow: 0 0 0 2px rgba(76, 175, 80, 0.1);
}

.form-group input::placeholder {
    color: #999;
}

.submit-btn {
    width: 100%;
    background-color: #4CAF50;
    color: white;
    padding: 14px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-size: 16px;
    font-weight: 600;
    margin-top: 10px;
    transition: all 0.3s ease;
}

.submit-btn:hover {
    background-color: #45a049;
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.submit-btn:active {
    transform: translateY(0);
}

.close {
    position: absolute;
    right: 20px;
    top: 15px;
    color: #666;
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
    transition: color 0.3s ease;
}

.close:hover {
    color: #333;
}

.alert {
    padding: 15px;
    margin-bottom: 20px;
    border: 1px solid transparent;
    border-radius: 4px;
}

.alert-success {
    color: #155724;
    background-color: #d4edda;
    border-color: #c3e6cb;
}

.alert-danger {
    color: #721c24;
    background-color: #f8d7da;
    border-color: #f5c6cb;
}

/* Tambahkan style untuk tombol aksi */
.btn-edit, .btn-delete {
    padding: 5px 10px;
    margin: 0 2px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
}

.btn-edit {
    background-color: #ffc107;
    color: #000;
}

.btn-delete {
    background-color: #dc3545;
    color: #fff;
}

.btn-edit:hover {
    background-color: #e0a800;
}

.btn-delete:hover {
    background-color: #c82333;
}

/* Tambahkan style ini ke dalam tag <style> yang sudah ada */
.swal2-popup.swal2-toast {
    padding: 0.75rem;
    font-size: 0.875rem;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    border-radius: 8px;
}

.swal2-popup.swal2-toast .swal2-title {
    margin: 0;
    padding: 0;
    font-size: 1rem;
    font-weight: 600;
}

.swal2-popup.swal2-toast .swal2-icon {
    margin: 0 0.5rem 0 0;
    width: 1.5rem;
    height: 1.5rem;
}

.swal2-popup.swal2-toast .swal2-icon .swal2-icon-content {
    font-size: 1rem;
}

.swal2-popup.swal2-toast .swal2-timer-progress-bar {
    background: rgba(255, 255, 255, 0.3);
}

/* Animasi untuk notifikasi */
@keyframes slideInRight {
    from {
        transform: translateX(100%);
    }
    to {
        transform: translateX(0);
    }
}

@keyframes slideOutRight {
    from {
        transform: translateX(0);
    }
    to {
        transform: translateX(100%);
    }
}

.swal2-popup.swal2-toast.swal2-show {
    animation: slideInRight 0.3s ease-out;
}

.swal2-popup.swal2-toast.swal2-hide {
    animation: slideOutRight 0.3s ease-in;
}

/* Tambahkan style untuk validasi form */
.error {
    border-color: #dc3545 !important;
    box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25) !important;
}

/* Style untuk loading state */
.swal2-popup.swal2-modal-custom {
    background-color: #1a1a1a;
    color: #ffffff;
}

.swal2-confirm-button-custom {
    background-color: #dc3545 !important;
}

.swal2-cancel-button-custom {
    background-color: #6c757d !important;
}

/* Style untuk form edit */
.text-muted {
    color: #6c757d;
    font-size: 0.875em;
    margin-top: 0.25rem;
}

.form-group input[readonly] {
    background-color: #e9ecef;
    cursor: not-allowed;
}

/* Animasi loading */
.swal2-loading {
    margin: 1.5em auto;
}

/* Responsive design untuk modal */
@media (max-width: 768px) {
    .modal-content {
        width: 95%;
        margin: 20px auto;
        padding: 15px;
    }
    
    .form-group input,
    .form-group select {
        font-size: 16px; /* Mencegah zoom pada iOS */
    }
}

/* Tambahkan style untuk animasi loading dan konfirmasi delete */
.swal2-popup {
    font-size: 0.9rem !important;
}

.swal2-title {
    font-size: 1.3rem !important;
}

.swal2-confirm, .swal2-cancel {
    font-size: 0.9rem !important;
    padding: 0.5rem 1.5rem !important;
}

.swal2-popup.swal2-modal-custom {
    background-color: var(--secondary-black);
    color: var(--primary-white);
}

.swal2-popup.swal2-modal-custom .swal2-title {
    color: var(--primary-white);
}

.swal2-popup.swal2-modal-custom .swal2-content {
    color: var(--primary-white);
}

.swal2-popup.swal2-modal-custom .swal2-confirm {
    background-color: var(--danger-color) !important;
    color: var(--primary-white);
}

.swal2-popup.swal2-modal-custom .swal2-cancel {
    background-color: var(--secondary-gray) !important;
    color: var(--primary-white);
}

/* Animasi loading */
.swal2-loading {
    margin: 1.5em auto;
}

.swal2-loading .swal2-loader {
    border-color: var(--primary-blue) transparent var(--primary-blue) transparent;
}

/* Responsive design */
@media (max-width: 768px) {
    .swal2-popup {
        font-size: 0.8rem !important;
        padding: 1rem !important;
    }
    
    .swal2-title {
        font-size: 1.1rem !important;
    }
    
    .swal2-confirm, .swal2-cancel {
        font-size: 0.8rem !important;
        padding: 0.4rem 1.2rem !important;
    }
}

/* Table Styles */
.data-table {
    width: 100%;
    border-collapse: collapse;
    margin: 20px 0;
    background: var(--secondary-black);
    border-radius: 10px;
    overflow: hidden;
    box-shadow: 0 5px 15px rgba(0,0,0,0.2);
}

.data-table thead {
    background: var(--primary-black);
}

.data-table th {
    padding: 15px;
    text-align: left;
    color: var(--primary-white);
    font-weight: 600;
    text-transform: uppercase;
    font-size: 0.9em;
}

.data-table td {
    padding: 12px 15px;
    border-bottom: 1px solid rgba(255,255,255,0.1);
    color: var(--primary-white);
}

.data-table tbody tr:hover {
    background: rgba(255,255,255,0.05);
}

/* Badge Styles */
.badge {
    padding: 5px 10px;
    border-radius: 15px;
    font-size: 0.8em;
    font-weight: 600;
    text-transform: uppercase;
}

.badge-admin {
    background: var(--primary-red);
    color: white;
}

.badge-customer {
    background: var(--primary-blue);
    color: white;
}

/* Button Styles */
.actions {
    display: flex;
    gap: 8px;
    justify-content: flex-start;
    align-items: center;
}

.btn-action {
    padding: 8px;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 35px;
    height: 35px;
}

.btn-edit {
    background: var(--warning-color);
    color: var(--primary-black);
}

.btn-delete {
    background: var(--danger-color);
    color: var(--primary-white);
}

.btn-action:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.2);
}

.btn-edit:hover {
    background: #ffc107;
}

.btn-delete:hover {
    background: #dc3545;
}

/* Add Button Style */
.btn-add {
    background: linear-gradient(45deg, var(--primary-blue), var(--secondary-blue));
    color: white;
    padding: 12px 24px;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-size: 14px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 8px;
    transition: all 0.3s ease;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
}

.btn-add:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 12px rgba(0,0,0,0.15);
}

/* Responsive Table */
@media screen and (max-width: 768px) {
    .data-table {
        display: block;
        overflow-x: auto;
        white-space: nowrap;
    }
    
    .actions {
        min-width: 100px;
    }
}

/* Custom Variables */
:root {
    --warning-color: #ffc107;
    --danger-color: #dc3545;
    --primary-blue: #007bff;
    --secondary-blue: #0056b3;
}

/* Action Buttons */
.actions {
    display: flex;
    gap: 8px;
    justify-content: center;
}

.btn-action {
    width: 35px;
    height: 35px;
    border: none;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-warning {
    background-color: #ffc107;
    color: #000;
}

.btn-danger {
    background-color: #dc3545;
    color: #fff;
}

.btn-warning:hover {
    background-color: #e0a800;
    transform: translateY(-2px);
    box-shadow: 0 2px 5px rgba(0,0,0,0.2);
}

.btn-danger:hover {
    background-color: #c82333;
    transform: translateY(-2px);
    box-shadow: 0 2px 5px rgba(0,0,0,0.2);
}

/* Badge Styles */
.badge {
    padding: 5px 10px;
    border-radius: 15px;
    font-size: 0.8em;
    font-weight: 600;
    text-transform: uppercase;
}

.badge-admin {
    background: #dc3545;
    color: white;
}

.badge-customer {
    background: #007bff;
    color: white;
}

/* Add Button Style */
.btn-add {
    background: linear-gradient(45deg, #007bff, #0056b3);
    color: white;
    padding: 12px 24px;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-size: 14px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 8px;
    transition: all 0.3s ease;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
}

.btn-add:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 12px rgba(0,0,0,0.15);
}

/* Modal Styles */
.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
    animation: fadeIn 0.3s ease;
}

.modal-content {
    background-color: #2d2d2d;
    margin: 5% auto;
    padding: 20px;
    border-radius: 10px;
    width: 90%;
    max-width: 500px;
    position: relative;
    animation: slideIn 0.3s ease;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes slideIn {
    from { transform: translateY(-100px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}

/* Modal Styles */
.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-bottom: 20px;
    border-bottom: 1px solid #3a3a3a;
    margin-bottom: 20px;
}

.modal-header h2 {
    color: var(--primary-white);
    font-size: 1.5rem;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 10px;
}

.modal-header h2 i {
    color: var(--primary-blue);
}

.modal-form {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.form-group label {
    color: var(--primary-white);
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 8px;
}

.form-group label i {
    color: var(--primary-blue);
    width: 16px;
}

.form-group input,
.form-group select {
    padding: 12px;
    border: 1px solid #3a3a3a;
    border-radius: 8px;
    background: var(--secondary-black);
    color: var(--primary-white);
    font-size: 14px;
    transition: all 0.3s ease;
}

.form-group input:focus,
.form-group select:focus {
    border-color: var(--primary-blue);
    outline: none;
    box-shadow: 0 0 0 2px rgba(0, 123, 255, 0.25);
}

.password-input {
    position: relative;
    display: flex;
    align-items: center;
}

.password-input input {
    width: 100%;
    padding-right: 40px;
}

.toggle-password {
    position: absolute;
    right: 12px;
    color: #6c757d;
    cursor: pointer;
    transition: color 0.3s ease;
}

.toggle-password:hover {
    color: var(--primary-white);
}

.text-muted {
    color: #6c757d;
    font-size: 12px;
    margin-top: 4px;
}

.form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid #3a3a3a;
}

.btn-cancel,
.btn-submit {
    padding: 12px 24px;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 8px;
}

.btn-cancel {
    background: transparent;
    color: var(--primary-white);
    border: 1px solid #3a3a3a;
}

.btn-submit {
    background: var(--primary-blue);
    color: white;
}

.btn-cancel:hover {
    background: rgba(255, 255, 255, 0.1);
}

.btn-submit:hover {
    background: var(--secondary-blue);
    transform: translateY(-2px);
}

@media (max-width: 768px) {
    .form-row {
        grid-template-columns: 1fr;
    }
}

/* Modal Base Styles */
.modal-content {
    background: var(--secondary-black);
    color: var(--primary-white);
    border-radius: 15px;
    max-width: 800px;
    width: 95%;
    margin: 20px auto;
    padding: 0;
    box-shadow: 0 10px 25px rgba(0,0,0,0.5);
}

/* Modal Header */
.modal-header {
    background: linear-gradient(135deg, var(--primary-blue) 0%, var(--secondary-blue) 100%);
    padding: 25px;
    border-radius: 15px 15px 0 0;
    display: flex;
    align-items: center;
    gap: 20px;
    position: relative;
}

.header-icon {
    background: rgba(255,255,255,0.2);
    width: 50px;
    height: 50px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.header-icon i {
    font-size: 24px;
    color: white;
}

.header-text {
    flex: 1;
}

.header-text h2 {
    margin: 0;
    font-size: 24px;
    color: white;
}

.header-text p {
    margin: 5px 0 0;
    opacity: 0.8;
    font-size: 14px;
    color: white;
}

.close {
    position: absolute;
    right: 20px;
    top: 20px;
    color: white;
    opacity: 0.8;
    font-size: 28px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.close:hover {
    opacity: 1;
    transform: rotate(90deg);
}

/* Form Sections */
.modal-form {
    padding: 25px;
}

.form-section {
    margin-bottom: 30px;
    background: rgba(255,255,255,0.03);
    padding: 20px;
    border-radius: 12px;
}

.form-section h3 {
    margin: 0 0 20px;
    font-size: 18px;
    color: var(--primary-blue);
    display: flex;
    align-items: center;
    gap: 10px;
}

.form-section h3 i {
    opacity: 0.8;
}

/* Form Groups */
.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-bottom: 20px;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    color: var(--primary-white);
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 8px;
}

.form-group label i {
    color: var(--primary-blue);
    width: 16px;
}

/* Input Groups */
.input-group {
    position: relative;
}

.input-group input,
.input-group select {
    width: 100%;
    padding: 12px 40px 12px 15px;
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 8px;
    background: rgba(255,255,255,0.05);
    color: var(--primary-white);
    font-size: 14px;
    transition: all 0.3s ease;
}

.input-group input:focus,
.input-group select:focus {
    border-color: var(--primary-blue);
    background: rgba(255,255,255,0.08);
    outline: none;
    box-shadow: 0 0 0 3px rgba(0,123,255,0.1);
}

.input-icon {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: rgba(255,255,255,0.4);
    cursor: pointer;
    transition: all 0.3s ease;
}

.input-icon:hover {
    color: var(--primary-blue);
}

.input-hint {
    display: block;
    margin-top: 5px;
    font-size: 12px;
    color: rgba(255,255,255,0.5);
}

/* Role Selector */
.role-selector {
    display: flex;
    gap: 20px;
    margin-top: 10px;
}

.role-option {
    flex: 1;
    cursor: pointer;
}

.role-option input[type="radio"] {
    display: none;
}

.role-box {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 20px;
    background: rgba(255,255,255,0.05);
    border: 2px solid rgba(255,255,255,0.1);
    border-radius: 12px;
    transition: all 0.3s ease;
}

.role-box i {
    font-size: 24px;
    margin-bottom: 10px;
    color: rgba(255,255,255,0.6);
}

.role-box span {
    font-size: 14px;
    font-weight: 500;
}

.role-option input[type="radio"]:checked + .role-box {
    background: rgba(0,123,255,0.1);
    border-color: var(--primary-blue);
}

.role-option input[type="radio"]:checked + .role-box i {
    color: var(--primary-blue);
}

/* Form Actions */
.form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 15px;
    margin-top: 30px;
    padding-top: 20px;
    border-top: 1px solid rgba(255,255,255,0.1);
}

.btn-cancel,
.btn-submit {
    padding: 12px 24px;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 8px;
    transition: all 0.3s ease;
}

.btn-cancel {
    background: transparent;
    color: var(--primary-white);
    border: 1px solid rgba(255,255,255,0.2);
}

.btn-submit {
    background: var(--primary-blue);
    color: white;
}

.btn-cancel:hover {
    background: rgba(255,255,255,0.1);
    border-color: rgba(255,255,255,0.3);
}

.btn-submit:hover {
    background: var(--secondary-blue);
    transform: translateY(-2px);
}

/* Responsive Design */
@media (max-width: 768px) {
    .form-row {
        grid-template-columns: 1fr;
    }
    
    .role-selector {
        flex-direction: column;
    }
    
    .modal-content {
        margin: 10px;
        width: calc(100% - 20px);
    }
}

/* Animation */
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
</style>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
// Konfigurasi default untuk SweetAlert2 Toast
const Toast = Swal.mixin({
    toast: true,
    position: 'top-end',
    showConfirmButton: false,
    timer: 3000,
    timerProgressBar: true,
    didOpen: (toast) => {
        toast.addEventListener('mouseenter', Swal.stopTimer)
        toast.addEventListener('mouseleave', Swal.resumeTimer)
    }
});

// Fungsi untuk menampilkan notifikasi sukses
function showSuccessToast(message) {
    Toast.fire({
        icon: 'success',
        title: message,
        background: '#a5dc86',
        color: '#fff',
        iconColor: '#fff'
    });
}

// Fungsi untuk menampilkan notifikasi error
function showErrorToast(message) {
    Toast.fire({
        icon: 'error',
        title: message,
        background: '#f27474',
        color: '#fff',
        iconColor: '#fff'
    });
}

// Fungsi untuk modal Add
function openModal() {
    document.getElementById('addModal').style.display = "block";
}

function closeAddModal() {
    document.getElementById('addModal').style.display = "none";
}

// Fungsi untuk modal Edit
function openEditModal(id, email, nama, username, alamat, telepon, role) {
    document.getElementById('edit_user_id').value = id;
    document.getElementById('edit_email').value = email;
    document.getElementById('edit_nama').value = nama;
    document.getElementById('edit_username').value = username;
    document.getElementById('edit_alamat').value = alamat;
    document.getElementById('edit_nomer_telepon').value = telepon;
    
    // Set radio button role
    if (role === 'admin') {
        document.getElementById('edit_role_admin').checked = true;
    } else {
        document.getElementById('edit_role_customer').checked = true;
    }
    
    document.getElementById('editModal').style.display = 'block';
}

function closeEditModal() {
    document.getElementById('editModal').style.display = "none";
}

// Fungsi untuk konfirmasi delete
function confirmDelete(form) {
    const userId = form.querySelector('input[name="user_id"]').value;
    
    Swal.fire({
        title: 'Konfirmasi Hapus',
        text: 'Apakah Anda yakin ingin menghapus user ini?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Ya, Hapus!',
        cancelButtonText: 'Batal',
        background: '#2d2d2d',
        color: '#fff'
    }).then((result) => {
        if (result.isConfirmed) {
            // Tampilkan loading
            Swal.fire({
                title: 'Menghapus User...',
                allowOutsideClick: false,
                showConfirmButton: false,
                willOpen: () => {
                    Swal.showLoading();
                }
            });
            
            // Submit form
            form.submit();
        }
    });
}

// Fungsi untuk menutup modal
function closeModal() {
    document.getElementById('addModal').style.display = 'none';
    document.getElementById('editModal').style.display = 'none';
}

// Menutup modal saat klik di luar modal
window.onclick = function(event) {
    if (event.target.className === 'modal') {
        closeModal();
    }
}

// Tambahkan fungsi validasi di bagian script
function validateForm(form) {
    let password = form.querySelector('input[name="password"]');
    
    // Validasi password untuk form tambah user
    if (form.id !== 'editUserForm' && password.value.length < 6) {
        showErrorToast('Password harus minimal 6 karakter!');
        password.classList.add('error');
        return false;
    }
    
    // Validasi password untuk form edit (hanya jika diisi)
    if (form.id === 'editUserForm' && password.value !== '' && password.value.length < 6) {
        showErrorToast('Password baru harus minimal 6 karakter!');
        password.classList.add('error');
        return false;
    }
    
    return true;
}

// Update event listener untuk form
document.addEventListener('DOMContentLoaded', function() {
    const addForm = document.querySelector('#addModal form');
    const editForm = document.getElementById('editUserForm');
    
    addForm.addEventListener('submit', function(e) {
        if (!validateForm(this)) {
            e.preventDefault();
        }
    });
    
    editForm.addEventListener('submit', function(e) {
        if (!validateForm(this)) {
            e.preventDefault();
        }
    });
    
    // Reset form dan hapus class error saat modal ditutup
    document.querySelectorAll('.close').forEach(function(closeBtn) {
        closeBtn.addEventListener('click', function() {
            addForm.reset();
            editForm.reset();
            document.querySelectorAll('.error').forEach(function(el) {
                el.classList.remove('error');
            });
        });
    });
});

function togglePassword(inputId) {
    const input = document.getElementById(inputId);
    const icon = input.nextElementSibling;
    
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    } else {
        input.type = 'password';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    }
}
</script>

<?php require_once '../templates/footer.php'; ?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
