<?php session_start(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Meubel Jati Murni</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="assets/css/login.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/vanta@latest/dist/vanta.net.min.js"></script>
</head>
<body>
    <div id="vanta-background"></div>
    <div class="login-container register-container">
        <div class="login-header">
            <i class="fas fa-user-plus"></i>
            <h1>Daftar Akun</h1>
            <p>Silakan lengkapi data diri Anda</p>
        </div>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="error-message">
                <?php 
                    echo $_SESSION['error'];
                    unset($_SESSION['error']);
                ?>
            </div>
        <?php endif; ?>

        <form action="auth/register_process.php" method="POST" id="registerForm">
            <div class="form-group">
                <i class="fas fa-user"></i>
                <input type="text" name="nama" placeholder="Nama Lengkap" required autocomplete="name">
            </div>
            <div class="form-group">
                <i class="fas fa-envelope"></i>
                <input type="email" name="email" placeholder="Email" required autocomplete="email">
            </div>
            <div class="form-group">
                <i class="fas fa-phone"></i>
                <input type="tel" name="nomer_telepon" placeholder="Nomor Telepon" required autocomplete="tel">
            </div>
            <div class="form-group">
                <i class="fas fa-map-marker-alt"></i>
                <input type="text" name="alamat" placeholder="Alamat" required autocomplete="address-line1">
            </div>
            <div class="form-group">
                <i class="fas fa-user-circle"></i>
                <input type="text" name="username" placeholder="Username" required autocomplete="username">
            </div>
            <div class="form-group">
                <i class="fas fa-lock" style="left: 15px;"></i>
                <input type="password" name="password" placeholder="Password" required>
            </div>
            <div class="form-group">
                <i class="fas fa-lock" style="left: 15px;"></i>
                <input type="password" name="confirm_password" placeholder="Konfirmasi Password" required>
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
            <button type="submit" class="login-button">
                <i class="fas fa-user-plus"></i> Daftar
            </button>
        </form>
        <div class="register-link">
            <p>Sudah punya akun? <a href="login.php">Login sekarang</a></p>
        </div>
    </div>

    <script src="assets/js/login.js"></script>
    <script src="assets/js/background-animation.js"></script>
</body>
</html> 