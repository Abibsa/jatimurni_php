<?php
// Pastikan BASE_URL terdefinisi
require_once __DIR__ . '/../config/config.php';

// Deteksi level direktori
$current_path = $_SERVER['PHP_SELF'];
$is_subfolder = strpos($current_path, '/pages/') !== false;
$path_prefix = $is_subfolder ? '../' : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meubel Jati</title>
    
    <!-- Scripts -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r134/three.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/vanta@latest/dist/vanta.net.min.js"></script>
    
    <!-- CSS -->
    <link rel="stylesheet" href="<?php echo $path_prefix; ?>assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="<?php echo $path_prefix; ?>assets/css/products.css">
    <link rel="stylesheet" href="<?php echo $path_prefix; ?>assets/css/style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@sweetalert2/theme-dark@4/dark.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <!-- Navbar Toggle Button -->
    <button class="navbar-toggle" id="navbarToggle">
        <i class="fas fa-chevron-left"></i>
    </button>
    
    <!-- Navbar -->
    <nav class="navbar" id="navbar">
        <div class="container">
            <a href="<?php echo $path_prefix; ?>index.php" class="logo">
                <i class="fas fa-couch"></i>
                <span>Meubel Jati</span>
            </a>
            <ul class="nav-links">
                <li>
                    <a href="<?php echo $path_prefix; ?>index.php">
                        <i class="fas fa-home"></i>
                        <span>Home</span>
                    </a>
                </li>
                <li>
                    <a href="<?php echo $path_prefix; ?>pages/products.php">
                        <i class="fas fa-box"></i>
                        <span>Produk</span>
                    </a>
                </li>
                <li>
                    <a href="<?php echo $path_prefix; ?>pages/users.php">
                        <i class="fas fa-users"></i>
                        <span>Pengguna</span>
                    </a>
                </li>
                <li>
                    <a href="<?php echo $path_prefix; ?>pages/transactions.php">
                        <i class="fas fa-exchange-alt"></i>
                        <span>Transaksi</span>
                    </a>
                </li>
                <li>
                    <a href="<?php echo $path_prefix; ?>pages/payments.php">
                        <i class="fas fa-money-bill"></i>
                        <span>Pembayaran</span>
                    </a>
                </li>
                <li>
                    <a href="#" onclick="confirmLogout(event)">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>Log Out</span>
                    </a>
                </li>
            </ul>
        </div>
    </nav>

    <!-- Content Wrapper -->
    <div class="content-wrapper">
        <div class="main-content">

    <!-- Add JavaScript for toggle functionality -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const navbar = document.getElementById('navbar');
            const navbarToggle = document.getElementById('navbarToggle');
            const contentWrapper = document.querySelector('.content-wrapper');
            
            navbarToggle.addEventListener('click', function() {
                // Efek klik
                this.style.transform = 'scale(0.95)';
                setTimeout(() => {
                    this.style.transform = 'scale(1)';
                }, 100);

                navbar.classList.toggle('collapsed');
                contentWrapper.classList.toggle('expanded');
                
                // Update posisi tombol dan rotasi icon
                if (navbar.classList.contains('collapsed')) {
                    this.style.left = '80px';
                    this.querySelector('i').style.transform = 'rotate(180deg)';
                } else {
                    this.style.left = '260px';
                    this.querySelector('i').style.transform = 'rotate(0deg)';
                }
            });
        });
    </script>
    <script src="<?php echo $path_prefix; ?>assets/js/products.js" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
    function confirmLogout(event) {
        event.preventDefault();
        
        Swal.fire({
            title: 'Konfirmasi Logout',
            text: 'Apakah Anda yakin ingin keluar?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#e63946',
            cancelButtonColor: '#2a2a2a',
            confirmButtonText: 'Ya, Logout!',
            cancelButtonText: 'Batal',
            background: '#1a1a1a',
            color: '#ffffff',
            backdrop: `
                rgba(0,0,0,0.8)
                left top
                no-repeat
            `
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'Logging Out...',
                    text: 'Terima kasih telah menggunakan aplikasi kami',
                    icon: 'success',
                    showConfirmButton: false,
                    timer: 1500,
                    background: '#1a1a1a',
                    color: '#ffffff',
                    willClose: () => {
                        window.location.href = '<?php echo $path_prefix; ?>auth/logout.php';
                    }
                });
            }
        });
    }
    </script>
</body>
</html>
