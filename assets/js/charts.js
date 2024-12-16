document.addEventListener('DOMContentLoaded', function() {
    // Konfigurasi warna yang lebih modern
    const colors = {
        primary: 'rgba(230, 57, 70, 0.8)',
        secondary: 'rgba(74, 144, 226, 0.8)',
        accent: 'rgba(80, 200, 120, 0.8)',
        background: 'rgba(255, 255, 255, 0.1)',
        text: '#ffffff'
    };

    // Penjualan Bulanan - Line Chart dengan gradient
    const monthlyCtx = document.getElementById('monthlyChart').getContext('2d');
    const gradient = monthlyCtx.createLinearGradient(0, 0, 0, 400);
    gradient.addColorStop(0, 'rgba(230, 57, 70, 0.5)');
    gradient.addColorStop(1, 'rgba(230, 57, 70, 0)');

    new Chart(monthlyCtx, {
        type: 'line',
        data: {
            labels: ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Ags', 'Sep', 'Okt', 'Nov', 'Des'],
            datasets: [{
                label: 'Penjualan 2024',
                data: [1200, 1900, 1500, 2200, 1800, 2400, 2100, 2800, 2300, 2600, 3000, 3200],
                borderColor: colors.primary,
                backgroundColor: gradient,
                tension: 0.4,
                fill: true,
                pointBackgroundColor: colors.primary,
                pointBorderColor: '#fff',
                pointHoverRadius: 8,
                pointHoverBorderWidth: 2
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    labels: {
                        color: colors.text
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        color: colors.background
                    },
                    ticks: {
                        color: colors.text,
                        callback: value => `Rp ${value.toLocaleString('id-ID')}`
                    }
                },
                x: {
                    grid: {
                        color: colors.background
                    },
                    ticks: {
                        color: colors.text
                    }
                }
            }
        }
    });

    // Produk Terlaris - Horizontal Bar Chart
    new Chart(document.getElementById('topProductsChart'), {
        type: 'bar',
        data: {
            labels: ['Kursi Cafe', 'Meja Cafe', 'Kursi Tamu', 'Meja Makan', 'Lemari'],
            datasets: [{
                data: [150, 120, 100, 80, 60],
                backgroundColor: [
                    colors.primary,
                    colors.secondary,
                    colors.accent,
                    'rgba(255, 193, 7, 0.8)',
                    'rgba(156, 39, 176, 0.8)'
                ],
                borderRadius: 8
            }]
        },
        options: {
            indexAxis: 'y',
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                x: {
                    grid: {
                        color: colors.background
                    },
                    ticks: {
                        color: colors.text
                    }
                },
                y: {
                    grid: {
                        display: false
                    },
                    ticks: {
                        color: colors.text
                    }
                }
            }
        }
    });

    // Perbandingan Penjualan Tahunan - Bar Chart dengan efek 3D
    new Chart(document.getElementById('yearlyComparisonChart'), {
        type: 'bar',
        data: {
            labels: ['2021', '2022', '2023', '2024'],
            datasets: [{
                label: 'Total Penjualan',
                data: [15000, 25000, 35000, 45000],
                backgroundColor: colors.secondary,
                borderRadius: 8,
                borderSkipped: false
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        color: colors.background
                    },
                    ticks: {
                        color: colors.text,
                        callback: value => `Rp ${value.toLocaleString('id-ID')}`
                    }
                },
                x: {
                    grid: {
                        display: false
                    },
                    ticks: {
                        color: colors.text
                    }
                }
            }
        }
    });

    // Status Pembayaran - Doughnut Chart dengan efek modern
    new Chart(document.getElementById('paymentStatusChart'), {
        type: 'doughnut',
        data: {
            labels: ['Lunas', 'Pending', 'Cicilan', 'Dibatalkan'],
            datasets: [{
                data: [65, 20, 10, 5],
                backgroundColor: [
                    colors.accent,
                    colors.secondary,
                    colors.primary,
                    'rgba(255, 99, 132, 0.8)'
                ],
                borderWidth: 0,
                borderRadius: 5,
                offset: 10
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        color: colors.text,
                        padding: 20,
                        usePointStyle: true,
                        pointStyle: 'circle'
                    }
                }
            },
            cutout: '70%'
        }
    });
}); 