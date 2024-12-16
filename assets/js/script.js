document.addEventListener('DOMContentLoaded', function() {
    console.log('Script loaded');
    
    if (typeof Chart === 'undefined') {
        console.error('Chart.js not loaded!');
        return;
    }
    
    const chartElements = {
        monthly: document.getElementById('monthlyChart'),
        topProducts: document.getElementById('topProductsChart'),
        yearlyComparison: document.getElementById('yearlyComparisonChart'),
        paymentStatus: document.getElementById('paymentStatusChart')
    };

    // Log status elemen
    Object.entries(chartElements).forEach(([key, element]) => {
        console.log(`${key} chart element:`, element ? 'Found' : 'Not found');
    });

    // Data untuk grafik penjualan bulanan
    const monthlyData = {
        labels: ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Ags', 'Sep', 'Okt', 'Nov', 'Des'],
        datasets: [{
            label: 'Penjualan 2024',
            data: [65, 59, 80, 81, 56, 55, 40, 45, 60, 75, 85, 90],
            borderColor: '#e63946',
            backgroundColor: 'rgba(230, 57, 70, 0.1)',
            tension: 0.3
        }]
    };

    // Data untuk grafik produk terlaris
    const topProductsData = {
        labels: ['Kursi Tamu', 'Meja Makan', 'Lemari Pakaian', 'Dipan', 'Bufet'],
        datasets: [{
            data: [300, 250, 200, 150, 100],
            backgroundColor: [
                '#e63946',
                '#ff4d5a',
                '#ff6b6b',
                '#ff8585',
                '#ffa5a5'
            ]
        }]
    };

    // Data untuk grafik perbandingan tahunan
    const yearlyData = {
        labels: ['2022', '2023', '2024'],
        datasets: [{
            label: 'Total Penjualan (Juta Rupiah)',
            data: [450, 520, 600],
            backgroundColor: '#e63946'
        }]
    };

    // Data untuk grafik status pembayaran
    const paymentStatusData = {
        labels: ['Lunas', 'Pending', 'Gagal'],
        datasets: [{
            data: [70, 20, 10],
            backgroundColor: [
                '#4CAF50',
                '#FFC107',
                '#e63946'
            ]
        }]
    };

    // Opsi global untuk semua grafik
    const globalOptions = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom',
                labels: {
                    color: '#ffffff',
                    font: {
                        size: 12
                    }
                }
            },
            title: {
                display: true,
                color: '#ffffff',
                font: {
                    size: 14
                }
            }
        }
    };

    // Inisialisasi grafik penjualan bulanan
    const monthlyChart = document.getElementById('monthlyChart');
    if (monthlyChart) {
        new Chart(monthlyChart, {
            type: 'line',
            data: monthlyData,
            options: {
                ...globalOptions,
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(255, 255, 255, 0.1)'
                        },
                        ticks: {
                            color: '#ffffff'
                        }
                    },
                    x: {
                        grid: {
                            color: 'rgba(255, 255, 255, 0.1)'
                        },
                        ticks: {
                            color: '#ffffff'
                        }
                    }
                }
            }
        });
    }

    // Inisialisasi grafik produk terlaris
    const topProductsChart = document.getElementById('topProductsChart');
    if (topProductsChart) {
        new Chart(topProductsChart, {
            type: 'pie',
            data: topProductsData,
            options: globalOptions
        });
    }

    // Inisialisasi grafik perbandingan tahunan
    const yearlyChart = document.getElementById('yearlyComparisonChart');
    if (yearlyChart) {
        new Chart(yearlyChart, {
            type: 'bar',
            data: yearlyData,
            options: {
                ...globalOptions,
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(255, 255, 255, 0.1)'
                        },
                        ticks: {
                            color: '#ffffff'
                        }
                    },
                    x: {
                        grid: {
                            color: 'rgba(255, 255, 255, 0.1)'
                        },
                        ticks: {
                            color: '#ffffff'
                        }
                    }
                }
            }
        });
    }

    // Inisialisasi grafik status pembayaran
    const paymentChart = document.getElementById('paymentStatusChart');
    if (paymentChart) {
        new Chart(paymentChart, {
            type: 'doughnut',
            data: paymentStatusData,
            options: {
                ...globalOptions,
                cutout: '60%'
            }
        });
    }

    // Update fungsi toggle navbar
    const navbar = document.querySelector('.navbar');
    const navbarToggle = document.createElement('button');
    const contentWrapper = document.querySelector('.content-wrapper');
    
    // Buat tombol toggle dengan efek yang lebih menarik
    navbarToggle.className = 'navbar-toggle';
    navbarToggle.innerHTML = `
        <i class="fas fa-chevron-left"></i>
    `;
    document.body.appendChild(navbarToggle);

    // Fungsi untuk toggle navbar dengan animasi yang lebih halus
    navbarToggle.addEventListener('click', function() {
        // Tambahkan efek klik
        this.style.transform = 'scale(0.95)';
        setTimeout(() => {
            this.style.transform = 'scale(1)';
        }, 100);

        navbar.classList.toggle('collapsed');
        contentWrapper.classList.toggle('expanded');
        this.classList.toggle('collapsed');
        
        // Update icon dengan animasi
        const icon = this.querySelector('i');
        if (navbar.classList.contains('collapsed')) {
            icon.style.transform = 'rotate(180deg)';
            this.style.left = '80px';
        } else {
            icon.style.transform = 'rotate(0deg)';
            this.style.left = '260px';
        }
    });

    // Tambahkan efek hover yang lebih halus
    navbarToggle.addEventListener('mouseover', function() {
        this.style.filter = 'brightness(1.2)';
    });

    navbarToggle.addEventListener('mouseout', function() {
        this.style.filter = 'brightness(1)';
    });
});
