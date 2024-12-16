// Inisialisasi array untuk menyimpan item keranjang
let cartItems = JSON.parse(localStorage.getItem('cartItems') || '[]');

function addToCart(nama, harga, gambar, productId) {
    event.preventDefault();
    
    const existingItemIndex = cartItems.findIndex(item => item.productId === productId);
    
    if (existingItemIndex !== -1) {
        showNotification('Produk sudah ada di keranjang');
    } else {
        const cartItem = {
            productId: productId, // Pastikan menggunakan productId bukan id
            name: nama,
            price: harga,
            image: gambar,
            quantity: 1
        };
        cartItems.push(cartItem);
        
        localStorage.setItem('cartItems', JSON.stringify(cartItems));
        renderCart();
        
        showNotification(`${nama} berhasil ditambahkan ke keranjang`);
    }
}
// Tambahkan fungsi untuk generate ID cart
function generateCartId() {
    // Ambil cartItems dari localStorage
    const existingItems = JSON.parse(localStorage.getItem('cartItems') || '[]');
    
    if (existingItems.length === 0) {
        return 'c001';
    }
    
    // Cari ID terakhir
    const lastId = existingItems[existingItems.length - 1].id;
    const lastNumber = parseInt(lastId.substring(1));
    
    // Generate ID baru
    const newNumber = lastNumber + 1;
    return `c${String(newNumber).padStart(3, '0')}`;
}

function calculateTotal() {
    return cartItems.reduce((total, item) => {
        const price = parseInt(item.price.replace(/\D/g, ''));
        return total + (price * item.quantity);
    }, 0);
}

function renderCart() {
    const cartItemsWrapper = document.querySelector('.cart-items-wrapper');
    
    if (cartItems.length === 0) {
        cartItemsWrapper.innerHTML = `
            <div class="empty-cart">
                <i data-feather="shopping-bag"></i>
                <p>Keranjang belanja kosong</p>
            </div>
        `;
    } else {
        cartItemsWrapper.innerHTML = cartItems.map((item, index) => `
            <div class="cart-item" data-product-id="${item.id}">
                <img src="${item.image}" alt="${item.name}" class="cart-item-image">
                <div class="item-detail">
                    <h3>${item.name}</h3>
                    <div class="item-price">${item.price}</div>
                    <div class="item-actions">
                        <button type="button" class="remove-btn" onclick="removeFromCart(${index})">
                            <i data-feather="trash-2"></i>
                        </button>
                    </div>
                </div>
            </div>
        `).join('');
    }
    
    // Update total
    const total = cartItems.reduce((sum, item) => {
        const price = parseInt(item.price.replace(/\D/g, ''));
        return sum + price;
    }, 0);
    
    document.querySelector('.total-amount').textContent = `Rp ${numberFormat(total)}`;
    
    // Re-init Feather icons
    feather.replace();
}

// Tambahkan event listener untuk tombol shopping cart
document.querySelector('#shopping-cart-button').addEventListener('click', function(e) {
    e.preventDefault();
    const shoppingCart = document.querySelector('.shopping-cart');
    shoppingCart.classList.toggle('active');
});

// Tambahkan event listener untuk tombol close cart
document.querySelector('#close-cart').addEventListener('click', function(e) {
    e.preventDefault();
    const shoppingCart = document.querySelector('.shopping-cart');
    shoppingCart.classList.remove('active');
});

// Perbaikan event listener untuk klik di luar cart
document.addEventListener('click', function(e) {
    const shoppingCart = document.querySelector('.shopping-cart');
    const cartButton = document.querySelector('#shopping-cart-button');
    const closeButton = document.querySelector('#close-cart');
    
    // Cek apakah yang diklik adalah tombol aksi di dalam cart
    const isActionButton = e.target.closest('.qty-btn') || 
                          e.target.closest('.remove-btn') ||
                          e.target.closest('.checkout-btn');
    
    // Cek apakah klik di dalam cart
    const isClickInsideCart = shoppingCart.contains(e.target);
    
    // Cek apakah klik pada tombol cart atau tombol close
    const isClickOnCartButton = cartButton.contains(e.target);
    const isClickOnCloseButton = closeButton.contains(e.target);
    
    // Tutup cart jika klik di luar cart DAN bukan tombol cart DAN bukan tombol aksi
    if (!isClickInsideCart && !isClickOnCartButton && !isActionButton) {
        shoppingCart.classList.remove('active');
    }
    
    // Tutup cart jika klik tombol close
    if (isClickOnCloseButton) {
        shoppingCart.classList.remove('active');
    }
});

async function checkout() {
    if (cartItems.length === 0) {
        Swal.fire({
            icon: 'error',
            title: 'Keranjang Kosong',
            text: 'Silakan tambahkan produk ke keranjang terlebih dahulu',
            background: '#1a1a1a',
            color: '#ffffff'
        });
        return;
    }
    window.location.href = 'checkout.php';
}

// Fungsi untuk pencarian produk
function searchProducts(query) {
    if (!query) return [];
    
    return allProducts.filter(product => 
        product.name.toLowerCase().includes(query.toLowerCase()) ||
        product.description.toLowerCase().includes(query.toLowerCase()) ||
        product.category.toLowerCase().includes(query.toLowerCase())
    );
}

// Event listener untuk tombol search
document.querySelector('#search-button').addEventListener('click', function(e) {
    e.preventDefault();
    const searchForm = document.querySelector('.search-form');
    searchForm.classList.toggle('active');
    
    // Focus pada input search ketika form dibuka
    if (searchForm.classList.contains('active')) {
        document.querySelector('.search-input').focus();
    }
});

// Update event listener untuk input search
document.querySelector('.search-input').addEventListener('input', async function(e) {
    const query = e.target.value.trim();
    const loadingEl = document.querySelector('.search-loading');
    const resultsContainer = document.querySelector('.results-container');
    
    // Reset dan sembunyikan hasil sebelumnya
    resultsContainer.style.display = 'none';
    
    // Jika query kosong, sembunyikan loading dan hasil
    if (!query) {
        loadingEl.classList.remove('active');
        resultsContainer.style.display = 'block';
        displaySearchResults([]);
        return;
    }
    
    // Tampilkan loading
    loadingEl.classList.add('active');
    
    try {
        // Kirim request ke API pencarian
        const response = await fetch(`search_products.php?query=${encodeURIComponent(query)}`);
        const results = await response.json();
        
        // Sembunyikan loading
        loadingEl.classList.remove('active');
        resultsContainer.style.display = 'block';
        
        // Tampilkan hasil
        displaySearchResults(results);
    } catch (error) {
        console.error('Error:', error);
        loadingEl.classList.remove('active');
        resultsContainer.innerHTML = '<p class="no-results">Terjadi kesalahan saat mencari produk</p>';
    }
});

// Fungsi untuk menampilkan hasil pencarian
function displaySearchResults(results) {
    const resultsContainer = document.querySelector('.results-container');
    
    if (!results || results.length === 0) {
        resultsContainer.innerHTML = '<p class="no-results">Tidak ada produk yang ditemukan</p>';
        return;
    }
    
    resultsContainer.innerHTML = results.map(product => `
        <div class="search-result-item">
            <div class="result-image">
                <img src="../${product.img_url}" alt="${product.name}" 
                     onerror="this.src='assets/images/default-product.jpg'">
            </div>
            <div class="result-info">
                <span class="result-category">${product.category}</span>
                <h4>${product.name}</h4>
                <div class="result-price">Rp ${numberFormat(product.price)}</div>
                <div class="result-actions">
                    <button onclick="addToCart('${product.name}', 'Rp ${numberFormat(product.price)}', '../${product.img_url}', '${product._id}')">
                        <i data-feather="shopping-cart"></i>
                    </button>
                </div>
            </div>
        </div>
    `).join('');
    
    // Re-initialize Feather icons
    feather.replace();
}

// Helper function untuk format angka
function numberFormat(number) {
    return new Intl.NumberFormat('id-ID').format(number);
}

// Tutup search form ketika klik di luar
document.addEventListener('click', function(e) {
    const searchForm = document.querySelector('.search-form');
    const searchButton = document.querySelector('#search-button');
    
    if (!searchForm.contains(e.target) && !searchButton.contains(e.target)) {
        searchForm.classList.remove('active');
    }
});

// Tambahkan fungsi untuk menangani modal box
function handleDetailButtons() {
    const detailButtons = document.querySelectorAll('.detail-btn, .item-detail-button');
    const modal = document.getElementById('item-detail-modal');
    const closeIcon = modal.querySelector('.close-icon');
    
    detailButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Ambil data dari atribut data-
            const nama = this.getAttribute('data-nama');
            const harga = this.getAttribute('data-harga');
            const gambar = this.getAttribute('data-gambar');
            
            // Update konten modal
            modal.querySelector('.product-title').textContent = nama;
            modal.querySelector('.current').textContent = harga;
            modal.querySelector('#modal-product-image').src = gambar;
            
            // Tampilkan modal
            modal.style.display = 'block';
            document.body.style.overflow = 'hidden'; // Prevent scrolling
        });
    });
    
    // Tutup modal saat klik close icon
    closeIcon.addEventListener('click', function(e) {
        e.preventDefault();
        modal.style.display = 'none';
        document.body.style.overflow = 'auto'; // Enable scrolling
    });
    
    // Tutup modal saat klik di luar modal
    window.addEventListener('click', function(e) {
        if (e.target === modal) {
            modal.style.display = 'none';
            document.body.style.overflow = 'auto';
        }
    });
}

// Fungsi baru untuk menambahkan item dengan quantity
function addToCartWithQuantity(nama, harga, gambar, quantity) {
    const existingItem = cartItems.find(item => item.name === nama);
    
    if (existingItem) {
        existingItem.quantity += quantity;
    } else {
        cartItems.push({
            name: nama,
            price: harga,
            image: gambar,
            quantity: quantity
        });
    }
    
    renderCart();
    showNotification(`${quantity} ${nama} berhasil ditambahkan ke keranjang`);
}

// Panggil fungsi setelah DOM loaded
document.addEventListener('DOMContentLoaded', function() {
    handleDetailButtons();
    
    // Re-initialize setelah feather icons di-replace
    feather.replace();
    
    // Handle gambar yang gagal dimuat
    document.querySelectorAll('img').forEach(img => {
        img.onerror = function() {
            // Ganti dengan gambar default jika gambar tidak ditemukan
            if (!this.src.includes('default-product.jpg')) {
                this.src = 'assets/images/default-product.jpg';
            }
        }
    });
});

// Tambahkan fungsi untuk menampilkan notifikasi
function showNotification(message) {
    // Hapus notifikasi yang sudah ada (jika ada)
    const existingNotification = document.querySelector('.notification');
    if (existingNotification) {
        existingNotification.remove();
    }
    
    // Buat elemen notifikasi baru
    const notification = document.createElement('div');
    notification.className = 'notification';
    notification.innerHTML = `
        <i data-feather="check-circle"></i>
        <span>${message}</span>
    `;
    
    // Tambahkan ke body
    document.body.appendChild(notification);
    
    // Replace feather icons
    feather.replace();
    
    // Hapus notifikasi setelah 3 detik
    setTimeout(() => {
        notification.remove();
    }, 3000);
}

// Fungsi untuk menangani klik tombol "Beli Sekarang"
function beliSekarang(element) {
    event.preventDefault();
    
    // Ambil data produk dari atribut data-
    const nama = element.getAttribute('data-nama');
    const harga = element.getAttribute('data-harga');
    
    // Format pesan WhatsApp
    let message = `Halo, saya tertarik dengan produk ini:%0A%0A`;
    message += `Nama Produk: ${nama}%0A`;
    message += `Harga: ${harga}%0A%0A`;
    message += `Mohon informasi lebih lanjut.`;
    
    // Nomor WhatsApp tujuan (ganti dengan nomor WhatsApp bisnis Anda)
    const phoneNumber = '6285878612964';
    
    // Redirect ke WhatsApp
    window.location.href = `https://wa.me/${phoneNumber}?text=${message}`;
}

// Fungsi untuk mengecek elemen dalam viewport
function isInViewport(element) {
    const rect = element.getBoundingClientRect();
    return (
        rect.top <= (window.innerHeight || document.documentElement.clientHeight) * 0.8
    );
}

// Fungsi untuk menambahkan class show
function showElements() {
    // Animasi untuk sections
    document.querySelectorAll('section').forEach(section => {
        if (isInViewport(section)) {
            section.classList.add('show');
        }
    });

    // Animasi untuk cards
    document.querySelectorAll('.produk-card, .product-card').forEach(card => {
        if (isInViewport(card)) {
            setTimeout(() => {
                card.classList.add('show');
            }, 200 * Array.from(card.parentNode.children).indexOf(card));
        }
    });

    // Animasi untuk content
    document.querySelectorAll('.content, .about, .produk, .contact').forEach(content => {
        if (isInViewport(content)) {
            content.classList.add('show');
        }
    });

    // Animasi untuk images
    document.querySelectorAll('.about-img, .produk-card-img').forEach(img => {
        if (isInViewport(img)) {
            img.classList.add('show');
        }
    });
}

// Event listeners
document.addEventListener('DOMContentLoaded', showElements);
document.addEventListener('scroll', showElements);
window.addEventListener('resize', showElements);

// Smooth scroll untuk link navigasi
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        e.preventDefault();
        const targetId = this.getAttribute('href');
        if (targetId === '#') return;
        
        const targetElement = document.querySelector(targetId);
        if (targetElement) {
            targetElement.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        }
    });
});

// FAQ Toggle - Perbaikan implementasi
const faqs = document.querySelectorAll('.faq');

faqs.forEach(faq => {
    const toggleBtn = faq.querySelector('.faq-toggle');
    
    if (toggleBtn) {
        toggleBtn.addEventListener('click', (e) => {
            e.preventDefault(); // Mencegah default action
            e.stopPropagation(); // Mencegah event bubbling
            
            // Menutup FAQ lain yang sedang terbuka
            faqs.forEach(item => {
                if (item !== faq && item.classList.contains('active')) {
                    item.classList.remove('active');
                }
            });
            
            // Toggle FAQ yang diklik
            faq.classList.toggle('active');
            
            // Update icon
            const icon = toggleBtn.querySelector('i');
            if (icon) {
                if (faq.classList.contains('active')) {
                    icon.setAttribute('data-feather', 'chevron-up');
                } else {
                    icon.setAttribute('data-feather', 'chevron-down');
                }
                feather.replace(); // Re-render icons
            }
        });
    }
});

// Definisi variabel untuk hamburger menu
const hamburger = document.querySelector('#hamburger-menu');
const navbarNav = document.querySelector('.navbar-nav');

// Toggle class active untuk hamburger menu
hamburger.onclick = (e) => {
  navbarNav.classList.toggle('active');
  e.preventDefault();
};

// Klik di luar sidebar untuk menghilangkan nav
document.addEventListener('click', function(e) {
  if(!hamburger.contains(e.target) && !navbarNav.contains(e.target)) {
    navbarNav.classList.remove('active');
  }
});

// Tutup navbar saat link di klik
document.querySelectorAll('.navbar-nav a').forEach(link => {
  link.addEventListener('click', () => {
    navbarNav.classList.remove('active');
  });
});

// Fungsi untuk mengambil data username
async function getUserData() {
  try {
    const response = await fetch('get_user_data.php');
    const data = await response.json();
    
    if (data.success) {
      document.getElementById('user-name').textContent = data.username;
    } else {
      window.location.href = '../login.php';
    }
  } catch (error) {
    console.error('Error:', error);
    window.location.href = '../login.php';
  }
}

// Panggil fungsi getUserData saat halaman dimuat
document.addEventListener('DOMContentLoaded', function() {
  getUserData();
});

// Tambahkan event listener untuk tombol logout
document.getElementById('logout-button').addEventListener('click', function(e) {
  e.preventDefault();
  
  // Tampilkan konfirmasi menggunakan SweetAlert2
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
    color: '#ffffff'
  }).then((result) => {
    if (result.isConfirmed) {
      // Redirect ke halaman logout
      window.location.href = '../auth/logout.php';
    }
  });
});

// Tambahkan event listener untuk dropdown akun
document.getElementById('account-button').addEventListener('click', function(e) {
  e.preventDefault();
  document.querySelector('.account-dropdown').classList.toggle('active');
});

// Tutup dropdown saat klik di luar
document.addEventListener('click', function(e) {
  if (!e.target.closest('.user-account')) {
    document.querySelector('.account-dropdown').classList.remove('active');
  }
});

// Fungsi untuk menambah item ke cart
async function addToCartWithQuantity(productId, quantity = 1) {
    try {
        const response = await fetch(`get_product.php?id=${productId}`);
        const data = await response.json();
        
        if (!data.success) {
            throw new Error(data.message);
        }
        
        const product = data.product;
        let cartItems = JSON.parse(localStorage.getItem('cartItems') || '[]');
        
        const existingItemIndex = cartItems.findIndex(item => item.id === product._id);
        
        if (existingItemIndex > -1) {
            // Update quantity jika produk sudah ada
            const newQuantity = cartItems[existingItemIndex].quantity + quantity;
            if (newQuantity > product.stock) {
                showNotification('Stok tidak mencukupi');
                return;
            }
            cartItems[existingItemIndex].quantity = newQuantity;
        } else {
            // Tambah produk baru ke cart
            cartItems.push({
                id: product._id,
                name: product.name,
                price: product.price,
                image: product.img_url,
                quantity: quantity,
                stock: product.stock
            });
        }
        
        localStorage.setItem('cartItems', JSON.stringify(cartItems));
        renderCart();
        showNotification(`${product.name} berhasil ditambahkan ke keranjang`);
        
    } catch (error) {
        console.error('Error:', error);
        showNotification('Gagal menambahkan produk ke keranjang');
    }
}

// Fungsi untuk update quantity
function updateQuantity(productId, change) {
    try {
        let cartItems = JSON.parse(localStorage.getItem('cartItems') || '[]');
        const itemIndex = cartItems.findIndex(item => item._id === productId);
        
        if (itemIndex > -1) {
            const newQuantity = cartItems[itemIndex].quantity + change;
            
            // Cek batas minimum dan maksimum quantity
            if (newQuantity > 0 && newQuantity <= cartItems[itemIndex].stock) {
                cartItems[itemIndex].quantity = newQuantity;
                localStorage.setItem('cartItems', JSON.stringify(cartItems));
                renderCart();
                
                // Update total
                updateTotal();
            } else if (newQuantity <= 0) {
                // Konfirmasi penghapusan item
                Swal.fire({
                    title: 'Hapus Produk',
                    text: 'Apakah Anda yakin ingin menghapus produk ini?',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#3085d6',
                    confirmButtonText: 'Ya, Hapus!',
                    cancelButtonText: 'Batal',
                    background: '#1a1a1a',
                    color: '#ffffff'
                }).then((result) => {
                    if (result.isConfirmed) {
                        removeFromCart(productId);
                    }
                });
            } else {
                // Tampilkan pesan jika melebihi stok
                Swal.fire({
                    icon: 'error',
                    title: 'Stok Tidak Cukup',
                    text: 'Jumlah melebihi stok yang tersedia',
                    background: '#1a1a1a',
                    color: '#ffffff'
                });
            }
        }
    } catch (error) {
        console.error('Error updating quantity:', error);
        Swal.fire({
            icon: 'error',
            title: 'Gagal',
            text: 'Gagal mengupdate jumlah produk',
            background: '#1a1a1a',
            color: '#ffffff'
        });
    }
}

// Fungsi untuk update total
function updateTotal() {
    try {
        const cartItems = JSON.parse(localStorage.getItem('cartItems') || '[]');
        const total = cartItems.reduce((sum, item) => {
            // Pastikan price dan quantity adalah number yang valid
            const price = Number(item.price) || 0;
            const quantity = Number(item.quantity) || 0;
            return sum + (price * quantity);
        }, 0);
        
        const totalElement = document.querySelector('.total-amount');
        if (totalElement) {
            totalElement.textContent = `Rp ${numberFormat(total)}`;
        }
    } catch (error) {
        console.error('Error updating total:', error);
    }
}

// Fungsi untuk render cart
function renderCart() {
    const cartItemsWrapper = document.querySelector('.cart-items-wrapper');
    
    if (cartItems.length === 0) {
        cartItemsWrapper.innerHTML = `
            <div class="empty-cart">
                <i data-feather="shopping-bag"></i>
                <p>Keranjang belanja kosong</p>
            </div>
        `;
    } else {
        cartItemsWrapper.innerHTML = cartItems.map((item, index) => `
            <div class="cart-item" data-product-id="${item.id}">
                <img src="${item.image}" alt="${item.name}" class="cart-item-image">
                <div class="item-detail">
                    <h3>${item.name}</h3>
                    <div class="item-price">${item.price}</div>
                    <div class="item-actions">
                        <button type="button" class="remove-btn" onclick="removeFromCart(${index})">
                            <i data-feather="trash-2"></i>
                        </button>
                    </div>
                </div>
            </div>
        `).join('');
    }
    
    // Update total
    const total = cartItems.reduce((sum, item) => {
        const price = parseInt(item.price.replace(/\D/g, ''));
        return sum + price;
    }, 0);
    
    document.querySelector('.total-amount').textContent = `Rp ${numberFormat(total)}`;
    
    // Re-init Feather icons
    feather.replace();
}

// Fungsi untuk format angka
function numberFormat(number) {
    // Pastikan input adalah number dan valid
    const validNumber = Number(number) || 0;
    return new Intl.NumberFormat('id-ID').format(validNumber);
}

// Panggil renderCart saat halaman dimuat
document.addEventListener('DOMContentLoaded', function() {
    renderCart();
});

function removeFromCart(index) {
    // Hapus item berdasarkan index
    cartItems.splice(index, 1);
    
    // Simpan ke localStorage
    localStorage.setItem('cartItems', JSON.stringify(cartItems));
    
    // Update tampilan keranjang
    renderCart();
    
    // Tampilkan notifikasi
    showNotification('Item berhasil dihapus dari keranjang');
}

// Tambahkan event listener untuk memuat ulang keranjang saat halaman dimuat
document.addEventListener('DOMContentLoaded', function() {
    // Muat ulang data keranjang dari localStorage
    cartItems = JSON.parse(localStorage.getItem('cartItems') || '[]');
    
    // Pastikan setiap item memiliki quantity
    cartItems = cartItems.map(item => ({
        ...item,
        quantity: item.quantity || 1
    }));
    
    // Simpan kembali ke localStorage
    localStorage.setItem('cartItems', JSON.stringify(cartItems));
    
    // Render cart
    renderCart();
    
    // Tambahkan event listener untuk tombol quantity
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('qty-btn')) {
            const index = parseInt(e.target.closest('.cart-item').dataset.index);
            const change = e.target.classList.contains('plus') ? 1 : -1;
            updateQuantity(index, change);
        }
    });
});

// Event listener untuk tombol detail produk
document.querySelectorAll('.detail-btn').forEach(btn => {
    btn.addEventListener('click', async (e) => {
        e.preventDefault();
        const productId = e.target.closest('.detail-btn').dataset.productId;
        
        try {
            // Ambil data produk dari MongoDB
            const response = await fetch(`get_product.php?id=${productId}`);
            const result = await response.json();
            
            if (result.success) {
                const product = result.product;
                
                // Update modal dengan data produk
                document.querySelector('#modal-product-image').src = product.img_url;
                document.querySelector('.product-title').textContent = product.name;
                document.querySelector('.product-category').textContent = product.category;
                document.querySelector('.current').textContent = `Rp ${numberFormat(product.price)}`;
                document.querySelector('.rating-count').textContent = `(Stok: ${product.stock})`;
                
                // Reset quantity input
                document.querySelector('.qty-input').value = 1;
                
                // Tambahkan product ID ke tombol "Tambah ke Keranjang"
                const addToCartBtn = document.querySelector('.add-to-cart');
                addToCartBtn.dataset.productId = product._id;
                
                // Tampilkan modal
                document.getElementById('item-detail-modal').style.display = 'flex';
                
                // Re-init Feather icons
                feather.replace();
            }
        } catch (error) {
            console.error('Error:', error);
            showNotification('Gagal memuat detail produk');
        }
    });
});

// Event listener untuk tombol close modal
document.querySelector('.close-icon').addEventListener('click', (e) => {
    e.preventDefault();
    document.getElementById('item-detail-modal').style.display = 'none';
});

// Tambahkan fungsi untuk mengirim pesan ke WhatsApp
function kirimPesan() {
    event.preventDefault();
    
    // Ambil nilai dari form
    const nama = document.getElementById('nama').value;
    const email = document.getElementById('email').value;
    const phone = document.getElementById('phone').value;
    const message = document.getElementById('message').value;
    
    // Validasi form
    if (!nama || !email || !phone || !message) {
        Swal.fire({
            icon: 'error',
            title: 'Form Tidak Lengkap',
            text: 'Mohon lengkapi semua field yang diperlukan',
            background: '#1a1a1a',
            color: '#ffffff'
        });
        return;
    }
    
    // Format pesan WhatsApp
    let waMessage = `Halo, saya ingin bertanya:%0A%0A`;
    waMessage += `Nama: ${nama}%0A`;
    waMessage += `Email: ${email}%0A`;
    waMessage += `No. HP: ${phone}%0A`;
    waMessage += `Pesan:%0A${message}`;
    
    // Nomor WhatsApp tujuan (ganti dengan nomor WhatsApp bisnis Anda)
    const phoneNumber = '6285878612964';
    
    // Redirect ke WhatsApp
    window.location.href = `https://wa.me/${phoneNumber}?text=${waMessage}`;
}



