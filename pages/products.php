<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../config/database.php';

$db = new Database();
$collection = $db->getCollection('products');

// Handle notifications
$notification = [
    'show' => false,
    'message' => '',
    'type' => 'success'
];

if (isset($_SESSION['notification'])) {
    $notification = $_SESSION['notification'];
    unset($_SESSION['notification']);
}

// Proses form submission sebelum output HTML
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_id'])) {
        // Logika delete
        try {
            $deleteId = $_POST['delete_id'];
            
            // Dapatkan informasi produk sebelum dihapus
            $product = $collection->findOne(['_id' => $deleteId]);
            
            // Hapus gambar jika ada
            if (isset($product['img_url']) && !empty($product['img_url'])) {
                $imagePath = '../' . $product['img_url'];
                if (file_exists($imagePath)) {
                    unlink($imagePath);
                }
            }
            
            // Hapus produk dari database
            $result = $collection->deleteOne(['_id' => $deleteId]);
            
            if ($result->getDeletedCount() > 0) {
                $_SESSION['notification'] = [
                    'show' => true,
                    'message' => 'Produk berhasil dihapus!',
                    'type' => 'success'
                ];
            } else {
                throw new Exception('Gagal menghapus produk');
            }
            
        } catch (Exception $e) {
            $_SESSION['notification'] = [
                'show' => true,
                'message' => 'Error: ' . $e->getMessage(),
                'type' => 'error'
            ];
        }
        
        header('Location: products.php');
        exit;
    } else {
        // Logika create/update
        try {
            // Validasi input
            if (empty($_POST['name']) || empty($_POST['category']) || 
                !isset($_POST['price']) || !isset($_POST['stock'])) {
                throw new Exception("Semua field harus diisi");
            }

            // Persiapkan data produk
            $data = [
                'name' => trim($_POST['name']),
                'category' => trim($_POST['category']),
                'price' => (float)$_POST['price'],
                'stock' => (int)$_POST['stock'],
                'updated_at' => new MongoDB\BSON\UTCDateTime()
            ];

            // Jika ini produk baru, generate ID
            if (!isset($_POST['id']) || empty($_POST['id'])) {
                // Cari produk dengan ID terbesar
                $lastProduct = $collection->findOne(
                    [], 
                    ['sort' => ['_id' => -1]]
                );

                if ($lastProduct) {
                    // Jika ID terakhir sudah dalam format p\d{3}
                    if (preg_match('/^p(\d{3})$/', $lastProduct['_id'], $matches)) {
                        $nextNumber = intval($matches[1]) + 1;
                    } else {
                        // Jika masih menggunakan ObjectId, mulai dari 1
                        $nextNumber = 1;
                    }
                } else {
                    // Jika belum ada produk sama sekali
                    $nextNumber = 1;
                }

                // Format ID baru
                $newId = 'p' . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);
                
                // Pastikan ID baru unik
                while ($collection->findOne(['_id' => $newId])) {
                    $nextNumber++;
                    $newId = 'p' . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);
                }

                $data['_id'] = $newId;
            }

            // Handle upload gambar baru
            if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = '../assets/images/products/';
                
                // Buat direktori jika belum ada
                if (!file_exists($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }

                // Generate nama file unik
                $extension = pathinfo($_FILES['product_image']['name'], PATHINFO_EXTENSION);
                $fileName = uniqid() . '_' . time() . '.' . $extension;
                $targetPath = $uploadDir . $fileName;

                // Pindahkan file
                if (move_uploaded_file($_FILES['product_image']['tmp_name'], $targetPath)) {
                    // Jika ini update dan ada gambar lama, hapus gambar lama
                    if (isset($_POST['id']) && !empty($_POST['id'])) {
                        $oldProduct = $collection->findOne(['_id' => $_POST['id']]);
                        if (isset($oldProduct['img_url'])) {
                            $oldImagePath = '../' . $oldProduct['img_url'];
                            if (file_exists($oldImagePath)) {
                                unlink($oldImagePath);
                            }
                        }
                    }
                    
                    $data['img_url'] = 'assets/images/products/' . $fileName;
                }
            } else if (isset($_POST['id']) && !empty($_POST['id'])) {
                // Jika update tanpa upload gambar baru, pertahankan gambar lama
                $oldProduct = $collection->findOne(['_id' => $_POST['id']]);
                if (isset($oldProduct['img_url'])) {
                    $data['img_url'] = $oldProduct['img_url'];
                }
            }

            // Simpan ke database
            if (isset($_POST['id']) && !empty($_POST['id'])) {
                // Update existing product
                $result = $collection->replaceOne(
                    ['_id' => $_POST['id']],
                    $data
                );
                $message = "Produk berhasil diperbarui!";
            } else {
                // Insert new product
                $data['created_at'] = new MongoDB\BSON\UTCDateTime();
                $result = $collection->insertOne($data);
                $message = "Produk berhasil ditambahkan!";
            }

            $_SESSION['notification'] = [
                'show' => true,
                'message' => $message,
                'type' => 'success'
            ];
        } catch (Exception $e) {
            $_SESSION['notification'] = [
                'show' => true,
                'message' => "Error: " . $e->getMessage(),
                'type' => 'error'
            ];
        }
        
        header('Location: products.php');
        exit;
    }
}

// Setelah semua logika redirect, baru include header
require_once '../templates/header.php';

// Handle sorting and searching
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'name_asc';
$search = isset($_GET['search']) ? $_GET['search'] : '';

$options = [];

// Handle search filter
if ($search) {
    $options['filter'] = ['name' => ['$regex' => $search, '$options' => 'i']];
}

// Handle sorting
switch($sort) {
    case 'price_asc':
        $options['sort'] = ['price' => 1];
        break;
    case 'price_desc':
        $options['sort'] = ['price' => -1];
        break;
    case 'name_desc':
        $options['sort'] = ['name' => -1];
        break;
    default: // name_asc
        $options['sort'] = ['name' => 1];
}

?>

<!-- Tambahkan ini setelah semua HTML dan sebelum script -->
<?php if (isset($notification) && $notification['show']): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    Swal.fire({
        icon: '<?php echo $notification['type'] === 'success' ? 'success' : 'error'; ?>',
        title: '<?php echo $notification['type'] === 'success' ? 'Berhasil!' : 'Error!'; ?>',
        text: '<?php echo $notification['message']; ?>',
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
        background: '#1a1a1a',
        color: '#ffffff',
        customClass: {
            popup: 'swal-dark'
        }
    });
});
</script>
<?php endif; ?>

<!-- Modal Tambah/Edit Produk -->
<div id="productModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="modalTitle">
                <i class="fas fa-plus-circle"></i>
                <span>Tambah Produk Baru</span>
            </h2>
            <button type="button" class="close-btn" onclick="closeModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <form id="productForm" method="POST" enctype="multipart/form-data">
            <input type="hidden" id="productId" name="id">
            
            <div class="form-grid">
                <!-- Nama Produk -->
                <div class="form-group">
                    <label>
                        <i class="fas fa-box"></i>
                        Nama Produk
                    </label>
                    <input type="text" 
                           name="name" 
                           class="form-control" 
                           required 
                           placeholder="Masukkan nama produk">
                </div>

                <!-- Kategori -->
                <div class="form-group">
                    <label>
                        <i class="fas fa-tags"></i>
                        Kategori
                    </label>
                    <select name="category" class="form-control" required>
                        <option value="">Pilih kategori</option>
                        <option value="Kursi">Kursi</option>
                        <option value="Meja">Meja</option>
                        <option value="Lemari">Lemari</option>
                        <option value="Dipan">Dipan</option>
                        <option value="Bufet">Bufet</option>
                    </select>
                </div>

                <!-- Harga -->
                <div class="form-group">
                    <label>
                        <i class="fas fa-money-bill"></i>
                        Harga
                    </label>
                    <div class="input-group">
                        <span class="input-group-text">Rp</span>
                        <input type="number" 
                               name="price" 
                               class="form-control" 
                               required 
                               min="0" 
                               placeholder="0">
                    </div>
                </div>

                <!-- Stok -->
                <div class="form-group">
                    <label>
                        <i class="fas fa-cubes"></i>
                        Stok
                    </label>
                    <input type="number" 
                           name="stock" 
                           class="form-control" 
                           required 
                           min="0" 
                           placeholder="0">
                </div>
            </div>

            <!-- Upload Gambar -->
            <div class="form-group upload-group">
                <label>
                    <i class="fas fa-image"></i>
                    Gambar Produk
                </label>
                <div class="image-upload-container" id="dropZone">
                    <label for="productImage" class="upload-label">
                        <input type="file" 
                               name="product_image" 
                               id="productImage" 
                               class="file-input" 
                               accept="image/*" 
                               onchange="previewImage(this)">
                        <div class="image-upload-placeholder">
                            <i class="fas fa-cloud-upload-alt"></i>
                            <p>Klik atau seret gambar ke sini</p>
                            <span class="upload-hint">Format: JPG, PNG (Max. 5MB)</span>
                        </div>
                        <img id="imagePreview" src="" alt="" style="display: none;">
                    </label>
                </div>
            </div>

            <!-- Form Actions -->
            <div class="form-actions">
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

<!-- Modal Delete -->
<div id="deleteModal" class="modal">
    <div class="modal-content delete-modal">
        <div class="delete-header">
            <div class="delete-icon">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <h2>Konfirmasi Hapus</h2>
            <p>Apakah Anda yakin ingin menghapus produk ini?</p>
        </div>
        
        <div class="delete-body">
            <div class="product-preview">
                <div class="product-image">
                    <img id="deleteProductImage" src="" alt="Product Image">
                </div>
                <div class="product-info">
                    <h3 id="deleteProductName"></h3>
                    <span id="deleteProductCategory" class="category-badge"></span>
                    <p id="deleteProductPrice" class="price"></p>
                </div>
            </div>
        </div>

        <div class="delete-footer">
            <button type="button" class="btn btn-secondary" onclick="closeDeleteModal()">
                <i class="fas fa-times"></i> Batal
            </button>
            <form id="deleteForm" method="POST" style="margin: 0;">
                <input type="hidden" id="deleteProductId" name="delete_id">
                <button type="submit" class="btn btn-danger">
                    <i class="fas fa-trash"></i> Hapus Produk
                </button>
            </form>
        </div>
    </div>
</div>

<!-- Container Utama -->
<div class="products-wrapper">
    <!-- Header Section -->
    <div class="products-header">
        <div class="header-title">
            <h1><i class="fas fa-box"></i> Daftar Produk</h1>
            <p>Kelola semua produk meubel Anda</p>
        </div>
    </div>

    <!-- Search and Filter Section -->
    <div class="products-controls">
        <div class="search-box">
            <div class="search-input-wrapper">
                <input type="text" 
                       id="searchInput" 
                       placeholder="Cari produk..." 
                       value="<?php echo htmlspecialchars($search); ?>">
                <div class="search-loading" id="searchLoading">
                    <i class="fas fa-spinner fa-spin"></i>
                </div>
            </div>
            <button id="searchButton">
                <i class="fas fa-search"></i>
            </button>
        </div>
        
        <div class="controls-right">
            <select id="sortSelect" onchange="sortProducts(this.value)">
                <option value="name_asc" <?php echo $sort == 'name_asc' ? 'selected' : ''; ?>>Nama (A-Z)</option>
                <option value="name_desc" <?php echo $sort == 'name_desc' ? 'selected' : ''; ?>>Nama (Z-A)</option>
                <option value="price_asc" <?php echo $sort == 'price_asc' ? 'selected' : ''; ?>>Harga (Rendah-Tinggi)</option>
                <option value="price_desc" <?php echo $sort == 'price_desc' ? 'selected' : ''; ?>>Harga (Tinggi-Rendah)</option>
            </select>
            
            <button onclick="showAddProductModal()" class="btn btn-primary">
                <i class="fas fa-plus"></i> Tambah Produk
            </button>
        </div>
    </div>

    <!-- Products Grid -->
    <div class="products-grid">
        <?php 
        $products = $collection->find(
            $options['filter'] ?? [], 
            ['sort' => $options['sort'] ?? ['name' => 1]]
        )->toArray(); // Konversi cursor ke array

        if (empty($products)): ?>
            <div class="no-products">
                <i class="fas fa-box-open"></i>
                <p>Belum ada produk yang ditambahkan</p>
            </div>
        <?php else:
            foreach ($products as $product): ?>
                <div class="product-card">
                    <div class="product-image">
                        <?php if (isset($product['img_url']) && !empty($product['img_url'])): ?>
                            <img src="<?php echo '../' . htmlspecialchars($product['img_url']); ?>" 
                                 alt="<?php echo htmlspecialchars($product['name']); ?>">
                        <?php else: ?>
                            <div class="no-image">
                                <i class="fas fa-image"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="product-info">
                        <span class="product-id"><?php echo htmlspecialchars($product['_id']); ?></span>
                        <h3><?php echo htmlspecialchars($product['name']); ?></h3>
                        <span class="category-badge"><?php echo htmlspecialchars($product['category']); ?></span>
                        <p class="price">Rp <?php echo number_format($product['price'], 0, ',', '.'); ?></p>
                        <p class="stock <?php echo $product['stock'] > 0 ? 'in-stock' : 'out-stock'; ?>">
                            <i class="fas fa-cube"></i>
                            Stok: <?php echo $product['stock']; ?>
                        </p>
                    </div>
                    <div class="product-actions">
                        <button type="button" onclick='editProduct(<?php echo json_encode($product); ?>)' class="btn btn-edit">
                            <i class="fas fa-edit"></i> Edit
                        </button>
                        <button type="button" onclick='showDeleteModal(<?php echo json_encode($product); ?>)' class="btn btn-delete">
                            <i class="fas fa-trash"></i> Hapus
                        </button>
                    </div>
                </div>
            <?php endforeach; 
        endif; ?>
    </div>
</div>

<style>
/* Perbaikan warna untuk modal dan form elements */
:root {
    --modal-bg: #1a1a1a;
    --input-bg: rgba(255, 255, 255, 0.05);
    --input-border: rgba(255, 255, 255, 0.1);
    --input-focus: #e63946;
    --input-hover-bg: rgba(255, 255, 255, 0.08);
    --text-primary: #ffffff;
    --text-secondary: rgba(255, 255, 255, 0.7);
    --btn-primary: #e63946;
    --btn-primary-hover: #dc3545;
    --btn-secondary: rgba(255, 255, 255, 0.1);
    --btn-secondary-hover: rgba(255, 255, 255, 0.2);
}

.modal-content {
    background: var(--modal-bg);
    border: 1px solid rgba(255, 255, 255, 0.1);
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
}

.modal-header {
    background: var(--modal-bg);
    border-bottom: 1px solid var(--input-border);
}

.form-control {
    background: var(--input-bg);
    border: 1px solid var(--input-border);
    color: var(--text-primary);
}

.form-control:hover {
    background: var(--input-hover-bg);
}

.form-control:focus {
    border-color: var(--input-focus);
    background: var(--input-hover-bg);
    box-shadow: 0 0 0 2px rgba(230, 57, 70, 0.2);
}

.form-group label {
    color: var(--text-primary);
}

.input-group-text {
    background: var(--input-bg);
    border: 1px solid var(--input-border);
    color: var(--text-secondary);
}

select.form-control option {
    background: var(--modal-bg);
    color: var(--text-primary);
}

.image-upload-container {
    border: 2px dashed var(--input-border);
    background: var(--input-bg);
}

.image-upload-container:hover {
    border-color: var(--input-focus);
    background: rgba(230, 57, 70, 0.1);
}

.image-upload-placeholder {
    color: var(--text-secondary);
}

.image-upload-placeholder i {
    color: var(--text-secondary);
}

.upload-hint {
    color: var(--text-secondary);
}

/* Button styles */
.btn-primary {
    background: var(--btn-primary);
    color: white;
}

.btn-primary:hover {
    background: var(--btn-primary-hover);
}

.btn-secondary {
    background: var(--btn-secondary);
    color: var(--text-primary);
}

.btn-secondary:hover {
    background: var(--btn-secondary-hover);
}

.close-btn {
    color: var(--text-secondary);
}

.close-btn:hover {
    background: var(--btn-secondary-hover);
    color: var(--btn-primary);
}

/* Modal footer */
.modal-footer {
    border-top: 1px solid var(--input-border);
}

/* Scrollbar styling */
.modal-body::-webkit-scrollbar {
    width: 8px;
}

.modal-body::-webkit-scrollbar-track {
    background: var(--modal-bg);
}

.modal-body::-webkit-scrollbar-thumb {
    background: var(--btn-secondary);
    border-radius: 4px;
}

.modal-body::-webkit-scrollbar-thumb:hover {
    background: var(--btn-secondary-hover);
}

/* Form validation colors */
.form-control:invalid {
    border-color: var(--btn-primary);
}

.form-control:valid {
    border-color: #4CAF50;
}

/* Placeholder color */
.form-control::placeholder {
    color: var(--text-secondary);
    opacity: 0.7;
}

/* Focus styles for better accessibility */
.form-control:focus,
.btn:focus,
.close-btn:focus {
    outline: none;
    box-shadow: 0 0 0 2px rgba(230, 57, 70, 0.2);
}

/* Transition effects */
.form-control,
.btn,
.close-btn,
.image-upload-container {
    transition: all 0.3s ease;
}

/* Modal backdrop */
.modal {
    background: rgba(0, 0, 0, 0.8);
    backdrop-filter: blur(5px);
}

/* Reset dan Base Styles */
.products-wrapper {
    padding: 20px;
    max-width: 1400px;
    margin: 0 auto;
    min-height: 100vh;
    background: var(--primary-black);
}

/* Header Styles */
.products-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
    padding: 20px;
    background: var(--secondary-black);
    border-radius: 10px;
}

.header-title {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.header-title h1 {
    display: flex;
    align-items: center;
    gap: 10px;
    margin: 0;
    font-size: 1.5rem;
    color: var(--primary-white);
}

.header-title p {
    margin: 0;
    color: var(--text-secondary);
    font-size: 0.9rem;
}

/* Tombol Tambah Produk */
.products-header .btn-primary {
    background: var(--primary-red);
    color: white;
    padding: 6px 12px;
    font-size: 0.85rem;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    border-radius: 4px;
    height: 32px;
    white-space: nowrap;
    align-self: center;
}

.products-header .btn-primary i {
    font-size: 0.8rem;
}

/* Controls Section */
.products-controls {
    display: flex;
    justify-content: space-between;
    align-items: center;
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
    gap: 8px;
}

.search-input-wrapper {
    position: relative;
    flex: 1;
}

.search-input-wrapper input {
    width: 100%;
    padding: 8px 35px 8px 12px; /* Extra padding right for loading icon */
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 6px;
    background: rgba(255,255,255,0.05);
    color: var(--primary-white);
}

.search-loading {
    position: absolute;
    right: 10px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--text-secondary);
    display: none; /* Hidden by default */
}

.search-loading.active {
    display: block;
}

#searchButton {
    padding: 8px 12px;
    background: var(--primary-red);
    border: none;
    border-radius: 6px;
    color: white;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
}

#searchButton:hover {
    background: var(--secondary-red);
}

/* Loading animation */
@keyframes spin {
    0% { transform: translateY(-50%) rotate(0deg); }
    100% { transform: translateY(-50%) rotate(360deg); }
}

.search-loading i {
    animation: spin 1s linear infinite;
}

.controls-right {
    display: flex;
    align-items: center;
    gap: 15px;
}

#sortSelect {
    padding: 8px 12px;
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 6px;
    background: rgba(255,255,255,0.05);
    color: var(--primary-white);
    cursor: pointer;
}

.btn-primary {
    background: var(--primary-red);
    color: white;
    padding: 6px 12px;
    font-size: 0.85rem;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    border-radius: 4px;
    height: 32px;
    white-space: nowrap;
}

.btn-primary i {
    font-size: 0.8rem;
}

.filter-box select {
    padding: 10px 15px;
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 8px;
    background: rgba(255,255,255,0.05);
    color: var(--primary-white);
    cursor: pointer;
    min-width: 200px;
}

/* Products Grid */
.products-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 20px;
    padding: 20px;
    background: var(--secondary-black);
    border-radius: 10px;
}

/* Product Card */
.product-card {
    background: var(--primary-black);
    border-radius: 10px;
    overflow: hidden;
    transition: transform 0.3s ease;
    border: 1px solid rgba(255,255,255,0.1);
}

.product-card:hover {
    transform: translateY(-5px);
}

.product-image {
    height: 200px;
    overflow: hidden;
    position: relative;
}

.product-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.no-image {
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(0,0,0,0.2);
    color: #666;
}

.product-info {
    padding: 15px;
}

.product-info h3 {
    margin: 0 0 10px 0;
    color: var(--primary-white);
    font-size: 1.1rem;
}

.category-badge {
    display: inline-block;
    padding: 4px 8px;
    background: var(--primary-red);
    color: white;
    border-radius: 4px;
    font-size: 0.8rem;
}

.price {
    font-size: 1.2rem;
    color: var(--primary-white);
    font-weight: bold;
    margin: 10px 0;
}

.stock {
    display: flex;
    align-items: center;
    gap: 5px;
    font-size: 0.9rem;
}

.in-stock { color: #4CAF50; }
.out-stock { color: #f44336; }

.product-actions {
    display: flex;
    gap: 10px;
    padding: 15px;
    border-top: 1px solid rgba(255,255,255,0.1);
}

/* Buttons */
.btn {
    padding: 8px 15px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 5px;
}

.btn-primary {
    background: var(--primary-red);
    color: white;
}

.btn-edit {
    background: #2196F3;
    color: white;
    flex: 1;
}

.btn-delete {
    width: 100%;
    background: var(--primary-red);
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 8px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}

.btn-delete:hover {
    background: #dc3545;
}

.btn-delete:active {
    transform: translateY(0);
}

.btn-delete i {
    font-size: 1rem;
}

/* Responsive Design */
@media (max-width: 768px) {
    .products-wrapper {
        padding: 10px;
    }

    .products-header {
        flex-direction: column;
        text-align: center;
        gap: 15px;
    }

    .products-controls {
        flex-direction: column;
    }

    .search-box {
        max-width: 100%;
    }

    .filter-box select {
        width: 100%;
    }

    .products-grid {
        grid-template-columns: 1fr;
    }
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
    background: rgba(0, 0, 0, 0.7);
    backdrop-filter: blur(5px);
}

.modal-content {
    background: var(--secondary-black);
    margin: 3% auto;
    width: 90%;
    max-width: 600px;
    border-radius: 12px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.3);
    max-height: 90vh;
    overflow-y: auto;
}

/* Form Styles */
.form-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 20px;
    padding: 20px;
}

.form-group {
    margin-bottom: 15px;
}

.upload-group {
    padding: 0 20px;
}

.form-group label {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 8px;
    color: var(--primary-white);
    font-weight: 500;
}

.form-control {
    width: 100%;
    padding: 10px;
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 6px;
    background: rgba(255,255,255,0.05);
    color: var(--primary-white);
    transition: all 0.3s ease;
}

.form-control:focus {
    border-color: var(--primary-red);
    outline: none;
    box-shadow: 0 0 0 2px rgba(230, 57, 70, 0.2);
}

/* Modal Header */
.modal-header {
    padding: 20px;
    border-bottom: 1px solid rgba(255,255,255,0.1);
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: var(--secondary-black);
    border-radius: 12px 12px 0 0;
}

.modal-header h2 {
    margin: 0;
    color: var(--primary-white);
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 1.5rem;
}

.modal-header h2 i {
    color: var(--primary-red);
}

/* Button Styles */
.form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    padding: 20px;
    border-top: 1px solid rgba(255,255,255,0.1);
}

/* Responsive Design */
@media (max-width: 768px) {
    .modal-content {
        margin: 5% auto;
        width: 95%;
    }

    .form-grid {
        grid-template-columns: 1fr;
    }
}

/* Image Upload Styles */
.image-upload-container {
    position: relative;
    min-height: 200px;
    border: 2px dashed rgba(255,255,255,0.2);
    border-radius: 8px;
    overflow: hidden;
    margin: 10px 0;
    transition: all 0.3s ease;
    background: rgba(255,255,255,0.05);
    cursor: pointer;
}

.image-upload-container:hover {
    border-color: var(--primary-red);
    background: rgba(230, 57, 70, 0.1);
}

.upload-label {
    display: block;
    width: 100%;
    height: 100%;
    cursor: pointer;
}

.file-input {
    position: absolute;
    width: 1px;
    height: 1px;
    padding: 0;
    margin: -1px;
    overflow: hidden;
    clip: rect(0,0,0,0);
    border: 0;
}

.image-upload-placeholder {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    color: var(--primary-white);
    padding: 20px;
    text-align: center;
    transition: all 0.3s ease;
}

.image-upload-placeholder i {
    font-size: 3rem;
    margin-bottom: 15px;
    color: var(--primary-red);
}

.image-upload-placeholder p {
    font-size: 1.1rem;
    margin: 10px 0;
}

.upload-hint {
    font-size: 0.85rem;
    color: rgba(255,255,255,0.6);
    margin-top: 8px;
}

#imagePreview {
    width: 100%;
    height: 200px;
    object-fit: contain;
    background: rgba(0,0,0,0.2);
    padding: 10px;
}

.dragover {
    border-color: var(--primary-red);
    background: rgba(230, 57, 70, 0.2);
}

/* Animasi untuk drag and drop */
@keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.02); }
    100% { transform: scale(1); }
}

.dragover {
    animation: pulse 1s infinite;
}

/* Button Styles */
.form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    padding: 20px;
    border-top: 1px solid rgba(255,255,255,0.1);
}

.btn {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 20px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 500;
    transition: all 0.3s ease;
}

.btn-primary {
    background: var(--primary-red);
    color: white;
    padding: 8px 16px;
    font-size: 0.9rem;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    border-radius: 6px;
    width: auto;
    min-width: fit-content;
}

.btn-primary:hover {
    background: var(--secondary-red);
    transform: translateY(-2px);
}

.btn-secondary {
    background: rgba(255,255,255,0.1);
    color: white;
}

.btn-secondary:hover {
    background: rgba(255,255,255,0.2);
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
        margin: 10% auto;
        width: 95%;
    }

    .form-grid {
        grid-template-columns: 1fr;
    }
}

/* Perbaikan style untuk tombol close */
.close-btn {
    background: none;
    border: none;
    color: var(--primary-white);
    font-size: 1.5rem;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s ease;
}

.close-btn:hover {
    background: rgba(255, 255, 255, 0.1);
    color: var(--primary-red);
    transform: rotate(90deg);
}

/* Styling untuk tombol aksi */
.product-actions {
    display: flex;
    gap: 10px;
    padding: 15px;
    border-top: 1px solid rgba(255,255,255,0.1);
}

.btn {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 10px 15px;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 500;
    transition: all 0.3s ease;
    flex: 1;
}

.btn i {
    font-size: 1rem;
}

.btn-edit {
    background: #2196F3;
    color: white;
}

.btn-edit:hover {
    background: #1976D2;
    transform: translateY(-2px);
}

.btn-delete {
    background: var(--primary-red);
    color: white;
}

.btn-delete:hover {
    background: #dc3545;
    transform: translateY(-2px);
}

/* SweetAlert Dark Theme */
.swal-dark {
    background: #1a1a1a !important;
    border: 1px solid rgba(255,255,255,0.1) !important;
}

.swal-title {
    color: #ffffff !important;
}

.swal-text {
    color: rgba(255,255,255,0.8) !important;
}

.swal-confirm {
    background: var(--primary-red) !important;
    color: white !important;
    border: none !important;
    box-shadow: none !important;
    border-radius: 8px !important;
    padding: 12px 24px !important;
    font-weight: 500 !important;
}

.swal-cancel {
    background: rgba(255,255,255,0.1) !important;
    color: white !important;
    border: none !important;
    box-shadow: none !important;
    border-radius: 8px !important;
    padding: 12px 24px !important;
    font-weight: 500 !important;
}

.swal-confirm:hover, .swal-cancel:hover {
    transform: translateY(-2px);
}

/* Animation */
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}

.swal2-popup {
    animation: fadeIn 0.3s ease-out;
}

/* Responsive Design */
@media (max-width: 768px) {
    .product-actions {
        flex-direction: column;
    }
    
    .btn {
        width: 100%;
    }
}

/* SweetAlert Dark Theme */
.swal2-popup.swal-dark {
    background: #1a1a1a;
    color: #ffffff;
    border: 1px solid rgba(255,255,255,0.1);
}

.swal2-title, .swal2-content {
    color: #ffffff !important;
}

.swal2-icon.swal2-warning {
    border-color: var(--primary-red) !important;
    color: var(--primary-red) !important;
}

/* Modal Delete Styles */
.delete-modal {
    max-width: 450px !important;
    padding: 0 !important;
    border-radius: 15px !important;
    overflow: hidden;
}

.delete-header {
    background: #fff;
    padding: 1.5rem;
    text-align: center;
    border-bottom: 1px solid #eee;
}

.delete-icon {
    width: 70px;
    height: 70px;
    margin: 0 auto 1rem;
    background: #fff3f3;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.delete-icon i {
    font-size: 2rem;
    color: #dc3545;
    animation: warningPulse 1.5s infinite;
}

@keyframes warningPulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.2); }
    100% { transform: scale(1); }
}

.delete-header h2 {
    color: #2d3436;
    margin: 0 0 0.5rem;
    font-size: 1.5rem;
}

.delete-header p {
    color: #636e72;
    margin: 0;
    font-size: 1rem;
}

.delete-body {
    padding: 1.5rem;
    background: #f8f9fa;
}

.product-preview {
    background: white;
    border-radius: 10px;
    padding: 1rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    box-shadow: 0 2px 5px rgba(0,0,0,0.05);
}

.product-preview .product-image {
    width: 80px;
    height: 80px;
    border-radius: 8px;
    overflow: hidden;
    flex-shrink: 0;
}

.product-preview .product-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.product-preview .product-info {
    flex: 1;
}

.product-preview h3 {
    margin: 0 0 0.5rem;
    font-size: 1.1rem;
    color: #2d3436;
}

.category-badge {
    display: inline-block;
    padding: 0.25rem 0.75rem;
    background: #e9ecef;
    color: #495057;
    border-radius: 15px;
    font-size: 0.875rem;
}

.price {
    margin: 0.5rem 0 0;
    color: #e63946;
    font-weight: bold;
}

.delete-footer {
    padding: 1rem 1.5rem;
    display: flex;
    justify-content: flex-end;
    gap: 1rem;
    background: #fff;
    border-top: 1px solid #eee;
}

.btn {
    padding: 0.5rem 1rem;
    border-radius: 8px;
    border: none;
    cursor: pointer;
    font-size: 0.9rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    transition: all 0.3s ease;
}

.btn-secondary {
    background: #e9ecef;
    color: #495057;
}

.btn-danger {
    background: #dc3545;
    color: white;
}

.btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
}

.btn-secondary:hover {
    background: #dee2e6;
}

.btn-danger:hover {
    background: #c82333;
}

/* Tambahkan style untuk pesan "tidak ada produk" */
.no-products {
    grid-column: 1 / -1;
    text-align: center;
    padding: 50px 20px;
    color: var(--text-secondary);
}

.no-products i {
    font-size: 48px;
    margin-bottom: 20px;
    color: var(--text-secondary);
}

.no-products p {
    font-size: 18px;
    margin: 0;
}

/* Khusus untuk tombol di header */
.products-header .btn-primary {
    margin-left: auto;
    background: var(--primary-red);
    color: white;
    padding: 6px 12px;
    font-size: 0.85rem;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    border-radius: 4px;
    width: auto;
    white-space: nowrap;
    min-height: 32px;
}

.products-header .btn-primary i {
    font-size: 0.8rem;
}

.products-header .btn-primary:hover {
    background: var(--secondary-red);
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Form handling
    const productForm = document.getElementById('productForm');
    if (productForm) {
        productForm.addEventListener('submit', function(e) {
            if (!validateForm()) {
                e.preventDefault();
                return;
            }
            
            const submitButton = this.querySelector('button[type="submit"]');
            const isEdit = document.getElementById('productId').value !== '';
            
            submitButton.disabled = true;
            submitButton.innerHTML = `<i class="fas fa-spinner fa-spin"></i> ${isEdit ? 'Memperbarui' : 'Menyimpan'}...`;
        });
    }

    // Search handling
    const searchInput = document.getElementById('searchInput');
    const searchButton = document.getElementById('searchButton');
    if (searchInput && searchButton) {
        searchButton.addEventListener('click', function() {
            window.location.href = `products.php?search=${encodeURIComponent(searchInput.value)}`;
        });

        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                searchButton.click();
            }
        });
    }

    // Sort handling
    const sortSelect = document.getElementById('sortSelect');
    if (sortSelect) {
        sortSelect.addEventListener('change', function() {
            window.location.href = `products.php?sort=${this.value}`;
        });
    }
});

// Fungsi untuk menampilkan modal tambah produk
function showAddProductModal() {
    resetForm();
    document.getElementById('modalTitle').innerHTML = `
        <i class="fas fa-plus-circle"></i>
        <span>Tambah Produk Baru</span>
    `;
    document.getElementById('productModal').style.display = 'block';
}

// Fungsi untuk menampilkan modal edit produk
function editProduct(product) {
    const form = document.getElementById('productForm');
    if (!form) return;

    document.getElementById('modalTitle').innerHTML = `
        <i class="fas fa-edit"></i>
        <span>Edit Produk: ${escapeHtml(product.name)}</span>
    `;
    
    form.querySelector('#productId').value = product._id;
    form.querySelector('input[name="name"]').value = decodeHtml(product.name || '');
    form.querySelector('select[name="category"]').value = product.category || '';
    form.querySelector('input[name="price"]').value = product.price || 0;
    form.querySelector('input[name="stock"]').value = product.stock || 0;
    
    const preview = document.getElementById('imagePreview');
    const placeholder = document.querySelector('.image-upload-placeholder');
    
    if (product.img_url) {
        preview.src = '../' + product.img_url;
        preview.style.display = 'block';
        placeholder.style.display = 'none';
    } else {
        preview.src = '';
        preview.style.display = 'none';
        placeholder.style.display = 'flex';
    }
    
    document.getElementById('productModal').style.display = 'block';
}

// Fungsi untuk menampilkan modal hapus
function showDeleteModal(product) {
    const modal = document.getElementById('deleteModal');
    if (!modal) return;

    document.getElementById('deleteProductId').value = product._id;
    document.getElementById('deleteProductName').textContent = product.name;
    document.getElementById('deleteProductCategory').textContent = product.category;
    document.getElementById('deleteProductPrice').textContent = 
        'Rp ' + product.price.toLocaleString('id-ID');
    
    if (product.img_url) {
        document.getElementById('deleteProductImage').src = '../' + product.img_url;
    }
    
    modal.style.display = 'block';
}

// Fungsi helper
function escapeHtml(unsafe) {
    return unsafe
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

function decodeHtml(html) {
    const txt = document.createElement("textarea");
    txt.innerHTML = html;
    return txt.value;
}

function resetForm() {
    const form = document.getElementById('productForm');
    if (!form) return;

    form.reset();
    document.getElementById('productId').value = '';
    const preview = document.getElementById('imagePreview');
    const placeholder = document.querySelector('.image-upload-placeholder');
    
    if (preview && placeholder) {
        preview.style.display = 'none';
        preview.src = '';
        placeholder.style.display = 'flex';
    }
}

function closeModal() {
    document.getElementById('productModal').style.display = 'none';
    resetForm();
}

function closeDeleteModal() {
    document.getElementById('deleteModal').style.display = 'none';
}

// Preview image handling
function previewImage(input) {
    const preview = document.getElementById('imagePreview');
    const placeholder = document.querySelector('.image-upload-placeholder');
    
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        
        reader.onload = function(e) {
            preview.src = e.target.result;
            preview.style.display = 'block';
            placeholder.style.display = 'none';
        };
        
        reader.readAsDataURL(input.files[0]);
    }
}

// Form validation
function validateForm() {
    const form = document.getElementById('productForm');
    if (!form) return false;

    const name = form.querySelector('input[name="name"]').value.trim();
    const category = form.querySelector('select[name="category"]').value;
    const price = form.querySelector('input[name="price"]').value;
    const stock = form.querySelector('input[name="stock"]').value;
    
    if (!name) {
        showError('Nama produk harus diisi!');
        return false;
    }
    
    if (!category) {
        showError('Kategori harus dipilih!');
        return false;
    }
    
    if (!price || isNaN(price) || price <= 0) {
        showError('Harga harus berupa angka positif!');
        return false;
    }
    
    if (!stock || isNaN(stock) || stock < 0) {
        showError('Stok harus berupa angka non-negatif!');
        return false;
    }
    
    return true;
}

function showError(message) {
    Swal.fire({
        icon: 'error',
        title: 'Error!',
        text: message,
        background: '#1a1a1a',
        color: '#ffffff',
        customClass: {
            popup: 'swal-dark'
        }
    });
}
</script>

<?php require_once '../templates/footer.php'; ?>

