<?php
session_start();
require_once 'config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];
    
    $db = new Database();
    $users = $db->getCollection('users');
    
    try {
        // Cari user berdasarkan username
        $user = $users->findOne([
            'account' => [
                '$elemMatch' => [
                    'username' => $username
                ]
            ],
            'role' => 'customer'
        ]);

        if ($user) {
            // Cek password
            foreach ($user['account'] as $account) {
                if ($account['username'] === $username && password_verify($password, $account['password'])) {
                    // Set session dengan data lengkap
                    $_SESSION['logged_in'] = true;
                    $_SESSION['user_id'] = (string)$user['_id'];
                    $_SESSION['username'] = $username;
                    $_SESSION['role'] = 'customer';
                    $_SESSION['name'] = $user['name'] ?? '';
                    $_SESSION['email'] = $user['email'] ?? '';
                    $_SESSION['phone'] = $user['phone'] ?? '';
                    $_SESSION['address'] = $user['address'] ?? '';
                    
                    // Redirect ke halaman customer
                    header('Location: customer/index.php');
                    exit();
                }
            }
        }
        
        $_SESSION['error'] = 'Username atau password salah';
        header('Location: login.php');
        exit();
        
    } catch (Exception $e) {
        $_SESSION['error'] = 'Terjadi kesalahan saat login';
        header('Location: login.php');
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Meubel Jati Murni</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="assets/css/login.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/vanta@latest/dist/vanta.net.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
    <div id="hero-animation"></div>
    <div id="vanta-background"></div>
    <div class="login-container">
        <div class="login-header">
            <i class="fas fa-couch"></i>
            <h1>Meubel Jati Murni</h1>
            <p>Silakan login untuk melanjutkan</p>
        </div>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="error-message">
                <?php 
                    echo $_SESSION['error'];
                    unset($_SESSION['error']);
                ?>
            </div>
        <?php endif; ?>

        <form action="auth/login_process.php" method="POST" id="loginForm">
            <div class="form-group">
                <i class="fas fa-user"></i>
                <input type="text" name="username" placeholder="Username" required>
            </div>
            <div class="form-group">
                <i class="fas fa-lock"></i>
                <input type="password" name="password" placeholder="Password" required>
            </div>
            <div class="form-group">
                <label for="role">Role:</label>
                <div class="role-options">
                    <div class="role-option">
                        <input type="radio" id="customer" name="role" value="customer" required>
                        <label for="customer">
                            <div class="card">
                                <h3>Customer</h3>
                                <p>Untuk pengguna biasa yang ingin berbelanja.</p>
                            </div>
                        </label>
                    </div>
                    <div class="role-option">
                        <input type="radio" id="admin" name="role" value="admin" required>
                        <label for="admin">
                            <div class="card">
                                <h3>Admin</h3>
                                <p>Untuk pengelola yang mengatur sistem.</p>
                            </div>
                        </label>
                    </div>
                </div>
            </div>
            <div class="remember-forgot">
                <label class="remember-me">
                    <input type="checkbox" name="remember">
                    <span>Ingat saya</span>
                </label>
                <a href="forgot_password.php" class="forgot-password">Lupa password?</a>
            </div>
            <button type="submit" class="login-button">
                <i class="fas fa-sign-in-alt"></i> Login
            </button>
        </form>
        <div class="register-link">
            <p>Belum punya akun? <a href="register.php">Daftar sekarang</a></p>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
    <script src="assets/js/login.js"></script>
    <script src="assets/js/background-animation.js"></script>

    <?php if(isset($_COOKIE['logout_message'])): ?>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        Swal.fire({
            title: 'Logout Berhasil!',
            text: '<?php echo $_COOKIE['logout_message']; ?>',
            icon: '<?php echo $_COOKIE['logout_status']; ?>',
            timer: 2000,
            showConfirmButton: false,
            background: '#1a1a1a',
            color: '#ffffff',
            toast: true,
            position: 'top-end'
        });
    });
    </script>
    <?php 
        // Hapus cookie setelah ditampilkan
        setcookie('logout_message', '', time() - 3600, '/');
        setcookie('logout_status', '', time() - 3600, '/');
    endif; 
    ?>
</body>
</html> 