<?php 
session_start(); // Memulai sesi

// Memeriksa apakah pengguna sudah login
if (!isset($_SESSION['username'])) {
    header("Location: login.php"); // Arahkan ke halaman login
    exit(); // Pastikan tidak ada kode lain yang dieksekusi setelah pengalihan
}
?>

<?php require_once 'templates/header.php'; ?>

<!-- Hero Section dengan Animasi -->
<div id="vanta-bg" class="hero-section">
    <div class="hero-content">
        <h1>Meubel Jati Murni</h1>
        <p>Kualitas Premium untuk Rumah Anda</p>
        <a href="https://abibsa.github.io/Meubel-Jati-Murni/" id="explore-btn" class="button-glow">Jelajahi Koleksi</a>
    </div>
</div>

<!-- statistic section -->
<section class="statistics-section">
    <h2>Statistik Penjualan</h2>
    <div class="stats-grid">
        <div class="stat-card">
            <i class="fas fa-shopping-cart"></i>
            <h3>Total Penjualan</h3>
            <p class="stat-number">1,234</p>
            <p class="stat-label">Transaksi</p>
        </div>
        <div class="stat-card">
            <i class="fas fa-users"></i>
            <h3>Pelanggan Aktif</h3>
            <p class="stat-number">856</p>
            <p class="stat-label">Pelanggan</p>
        </div>
        <div class="stat-card">
            <i class="fas fa-box"></i>
            <h3>Produk Terjual</h3>
            <p class="stat-number">2,567</p>
            <p class="stat-label">Unit</p>
        </div>
        <div class="stat-card">
            <i class="fas fa-money-bill-wave"></i>
            <h3>Pendapatan</h3>
            <p class="stat-number">Rp 987,5 Jt</p>
            <p class="stat-label">Total</p>
        </div>
    </div>
</section>

<!-- analytics section -->
<section class="analytics-section">
    <h2>Analisis Penjualan</h2>
    <div class="charts-grid">
        <div class="chart-card">
            <h3>Penjualan Bulanan</h3>
            <canvas id="monthlyChart"></canvas>
        </div>
        <div class="chart-card">
            <h3>Produk Terlaris</h3>
            <canvas id="topProductsChart"></canvas>
        </div>
        <div class="chart-card">
            <h3>Perbandingan Penjualan Tahunan</h3>
            <canvas id="yearlyComparisonChart"></canvas>
        </div>
        <div class="chart-card">
            <h3>Status Pembayaran</h3>
            <canvas id="paymentStatusChart"></canvas>
        </div>
    </div>
</section>

<!-- menu section -->
<section id="menu-section" class="menu-section">
    <h2>Menu Utama</h2>
    <div class="menu-grid">
        <div class="menu-card" onclick="navigateTo('pages/products.php')">
            <h3>Produk</h3>
            <p>Lihat berbagai furnitur jati kami</p>
        </div>
        <div class="menu-card" onclick="navigateTo('pages/users.php')">
            <h3>Pengguna</h3>
            <p>Data pelanggan dan operator</p>
        </div>
        <div class="menu-card" onclick="navigateTo('pages/transactions.php')">
            <h3>Transaksi</h3>
            <p>Riwayat pembelian pelanggan</p>
        </div>
        <div class="menu-card" onclick="navigateTo('pages/payments.php')">
            <h3>Pembayaran</h3>
            <p>Detail pembayaran</p>
        </div>
    </div>
</section>

<?php require_once 'templates/footer.php'; ?>

<!-- Tambahkan script untuk animasi -->
<script>
VANTA.NET({
    el: "#vanta-bg",
    mouseControls: true,
    touchControls: true,
    gyroControls: false,
    minHeight: 200.00,
    minWidth: 200.00,
    scale: 1.00,
    scaleMobile: 1.00,
    color: 0x3f51b5,
    backgroundColor: 0x1d1d1d,
    points: 15.00,
    maxDistance: 25.00,
    spacing: 17.00
})
</script>

<!-- Tambahkan sebelum closing body tag -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Data Penjualan Bulanan
const monthlyCtx = document.getElementById('monthlyChart').getContext('2d');
new Chart(monthlyCtx, {
    type: 'line',
    data: {
        labels: ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Ags', 'Sep', 'Okt', 'Nov', 'Des'],
        datasets: [{
            label: 'Penjualan 2024',
            data: [65, 59, 80, 81, 56, 55, 40, 45, 60, 75, 85, 90],
            borderColor: '#3f51b5',
            tension: 0.1
        }]
    }
});

// Data Produk Terlaris
const topProductsCtx = document.getElementById('topProductsChart').getContext('2d');
new Chart(topProductsCtx, {
    type: 'bar',
    data: {
        labels: ['Meja Makan', 'Kursi', 'Lemari', 'Dipan', 'Bufet'],
        datasets: [{
            label: 'Unit Terjual',
            data: [300, 250, 200, 150, 100],
            backgroundColor: '#3f51b5'
        }]
    }
});

// Data Perbandingan Tahunan
const yearlyCtx = document.getElementById('yearlyComparisonChart').getContext('2d');
new Chart(yearlyCtx, {
    type: 'bar',
    data: {
        labels: ['2022', '2023', '2024'],
        datasets: [{
            label: 'Total Penjualan (Juta Rupiah)',
            data: [750, 850, 987],
            backgroundColor: '#3f51b5'
        }]
    }
});

// Data Status Pembayaran
const paymentCtx = document.getElementById('paymentStatusChart').getContext('2d');
new Chart(paymentCtx, {
    type: 'doughnut',
    data: {
        labels: ['Lunas', 'Pending', 'Gagal'],
        datasets: [{
            data: [70, 20, 10],
            backgroundColor: ['#4CAF50', '#FFC107', '#F44336']
        }]
    }
});
</script>
