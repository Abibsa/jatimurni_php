// Fungsi untuk memuat data cart dari localStorage
function loadCartItems() {
    const cartItems = JSON.parse(localStorage.getItem('cartItems') || '[]');
    const cartContainer = document.getElementById('cartItems');
    let subtotal = 0;
    
    cartContainer.innerHTML = '';
    
    cartItems.forEach(item => {
        const itemTotal = item.price * item.quantity;
        subtotal += itemTotal;
        
        cartContainer.innerHTML += `
            <div class="cart-item">
                <img src="${item.image}" alt="${item.name}">
                <div class="item-details">
                    <h4>${item.name}</h4>
                    <p>Jumlah: ${item.quantity}</p>
                </div>
                <div class="item-price">
                    Rp ${itemTotal.toLocaleString('id-ID')}
                </div>
            </div>
        `;
    });
    
    const shipping = 50000; // Ongkos kirim tetap
    const total = subtotal + shipping;
    
    document.getElementById('subtotal').textContent = `Rp ${subtotal.toLocaleString('id-ID')}`;
    document.getElementById('total').textContent = `Rp ${total.toLocaleString('id-ID')}`;
}

async function processCheckout() {
    const form = document.getElementById('checkoutForm');
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }
    
    const formData = new FormData(form);
    const cartItems = JSON.parse(localStorage.getItem('cartItems') || '[]');
    
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
    
    try {
        // Tampilkan loading
        Swal.fire({
            title: 'Memproses Pesanan',
            text: 'Mohon tunggu sebentar...',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        // Hitung total
        const subtotal = cartItems.reduce((sum, item) => {
            const price = typeof item.price === 'string' ? 
                parseFloat(item.price.replace(/[^\d]/g, '')) : 
                parseFloat(item.price);
            const quantity = item.qty || item.quantity || 1;
            return sum + (price * quantity);
        }, 0);
        const shippingCost = 50000;
        const totalAmount = subtotal + shippingCost;

        // Generate ID transaksi dan pembayaran
        const transactionId = formData.get('transactionId') || 't' + Date.now();
        const paymentId = 'p' + Date.now();

        // Data transaksi untuk MongoDB
        const transactionData = {
            _id: transactionId,
            userId: formData.get('userId'),
            status: 'pending',
            orderDate: new Date().toISOString(),
            shippingAddress: formData.get('shippingAddress'),
            products: cartItems.map(item => ({
                productId: formatProductId(item.productId),
                name: item.name,
                quantity: item.qty || item.quantity || 1,
                price: typeof item.price === 'string' ? 
                    parseInt(item.price.replace(/\D/g, '')) : 
                    parseInt(item.price),
                subtotal: (typeof item.price === 'string' ? 
                    parseInt(item.price.replace(/\D/g, '')) : 
                    parseInt(item.price)) * (item.qty || item.quantity || 1)
            })),
            totalAmount: parseInt(formData.get('total_amount'))
        };

        // Data pembayaran untuk MongoDB
        const paymentData = {
            _id: paymentId,
            transactionId: transactionId,
            amount: totalAmount,
            paymentMethod: formData.get('paymentMethod'),
            paymentDate: transactionData.orderDate,
            status: 'pending'
        };

        // Validasi data sebelum dikirim
        const requiredFields = {
            transaction: ['user_id', 'items', 'shipping_address', 'customer_info'],
            payment: ['payment_method']
        };

        // Cek field transaksi
        for (const field of requiredFields.transaction) {
            if (!transactionData[field]) {
                throw new Error(`Data transaksi tidak lengkap: ${field} kosong`);
            }
        }

        // Cek field pembayaran
        for (const field of requiredFields.payment) {
            if (!paymentData[field]) {
                throw new Error(`Data pembayaran tidak lengkap: ${field} kosong`);
            }
        }

        // Kirim data ke server
        const response = await fetch('process_checkout.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                transaction: transactionData,
                payment: paymentData
            })
        });

        const result = await response.json();

        if (!response.ok) {
            throw new Error(result.message || 'Terjadi kesalahan saat memproses checkout');
        }

        if (result.success) {
            // Tampilkan notifikasi sukses
            Swal.fire({
                icon: 'success',
                title: 'Pesanan Berhasil!',
                text: 'Pesanan Anda sedang diproses',
                background: '#1a1a1a',
                color: '#ffffff'
            }).then(() => {
                // Bersihkan cart dan redirect
                localStorage.removeItem('cartItems');
                window.location.href = 'order_confirmation.php?id=' + transactionId;
            });
        } else {
            throw new Error(result.message || 'Terjadi kesalahan saat memproses checkout');
        }
    } catch (error) {
        console.error('Error:', error);
        Swal.fire({
            icon: 'error',
            title: 'Gagal',
            text: error.message || 'Terjadi kesalahan saat memproses checkout',
            background: '#1a1a1a',
            color: '#ffffff'
        });
    }
}

// Load cart items when page loads
document.addEventListener('DOMContentLoaded', loadCartItems); 

// Tambahkan fungsi helper untuk memformat Product ID
function formatProductId(id) {
    // Hapus prefix 'p' jika ada
    const numericId = id.replace(/^p/, '');
    // Pad dengan 0 di depan hingga 3 digit
    const paddedId = numericId.padStart(3, '0');
    // Tambahkan prefix 'p' kembali
    return 'p' + paddedId;
}

async function validateStock() {
    for (const item of cartItems) {
        try {
            const formattedId = formatProductId(item.id);
            const response = await fetch(`get_product.php?id=${formattedId}`);
            if (!response.ok) {
                throw new Error('Gagal mengecek stok produk');
            }
            
            const data = await response.json();
            if (!data.success) {
                throw new Error(data.message);
            }
            
            const product = data.product;
            const itemQuantity = item.qty || item.quantity || 1;
            
            if (itemQuantity > product.stock) {
                throw new Error(`Stok ${product.name} tidak mencukupi. Tersedia: ${product.stock}`);
            }
        } catch (error) {
            throw new Error(error.message);
        }
    }
    return true;
}