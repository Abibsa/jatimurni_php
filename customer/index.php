<?php
require_once '../config/database.php';

$db = new Database();
$collection = $db->getCollection('products');

// Mengambil data produk dari MongoDB dan urutkan berdasarkan _id
$products = $collection->find([], ['sort' => ['_id' => 1]])->toArray();
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Meubel Jati Murni</title>

  <!-- Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,300;0,400;0,700;1,700&display=swap"
    rel="stylesheet">

  <!-- Feather Icons -->
  <script src="https://unpkg.com/feather-icons"></script>

  <!-- My Style -->
  <link rel="stylesheet" href="assets/css/style.css">

  <!-- SweetAlert2 -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>

<body>

  <!-- Navbar start -->
  <nav class="navbar">
    <a href="#" class="navbar-logo">Jati<span>Murni</span>.</a>

    <div class="navbar-nav">
      <a href="#home">Home</a>
      <a href="#about">Tentang Kami</a>
      <a href="#produk">Produk</a>
      <a href="#contact">Kontak</a>
      <a href="#faq">FAQ</a>
    </div>

    <div class="navbar-extra">
      <a href="#" id="search-button"><i data-feather="search"></i></a>
      <a href="#" id="shopping-cart-button"><i data-feather="shopping-cart"></i></a>
      <div class="user-account">
        <a href="#" id="account-button">
          <i data-feather="user"></i> 
          <span id="user-name">Loading...</span>
        </a>
        <div class="account-dropdown">
          <a href="#" id="logout-button">
            <i data-feather="log-out"></i> Logout
          </a>
        </div>
      </div>
      <a href="#" id="hamburger-menu"><i data-feather="menu"></i></a>
    </div>

    <div class="shopping-cart">
      <div class="cart-header">
        <h2>Keranjang Belanja</h2>
        <button class="close-cart" id="close-cart">
          <i data-feather="x"></i>
        </button>
      </div>
      <div class="cart-items-wrapper">
        <!-- Cart items akan di-render di sini -->
      </div>
      <div class="cart-total">
        <div class="total">
          Total: <span class="total-amount">Rp. 0</span>
        </div>
        <button class="checkout-btn" onclick="checkout()">
          <i data-feather="shopping-bag"></i>
          Checkout
        </button>
      </div>
    </div>

    <div class="search-form">
      <input type="search" class="search-input" placeholder="Cari produk...">
      <div class="search-loading">
        <div class="loading-spinner"></div>
        <p class="loading-text">Mencari produk...</p>
      </div>
      <div class="results-container">
        <!-- Hasil pencarian akan ditampilkan di sini -->
      </div>
    </div>

  </nav>
  <!-- Navbar end -->

  <!-- Hero Section start -->
  <section class="hero" id="home">
    <main class="content">
      <h1>Mari Pilih Produk <span>Kami</span></h1>
      <p>Designer furniture. Locally designed. Globally Crafted.</p>
      <a href="https://wa.me/" class="cta">Beli Sekarang</a>
    </main>
  </section>
  <!-- Hero Section end -->

  <!-- About Section start -->
  <section id="about" class="about">
    <h2><span>Tentang</span> Kami</h2> 

     <div class="row">
       <div class="about-img">
         <img src="assets/images/Tentang-Kami.jpeg" alt="Tentang Kami">
       </div>
       <div class="content">
        <h3>Kenapa Harus Memilih Produk kami?</h3>
        <img src="assets/images/Terpercaya.png">Tingkat Kepuasan Dan Kepercayaan Pembeli Kami Sangat Tinggi. Terbukti Meubel Jati Murni Mendapatkan Predikat Toko Mebel Online Bintang 5 Versi Google.
        <p></p>
        <img src="assets/images/Custom Order.png">Tim Kami Terdiri Dari Orang Yang Ahli Dalam Membuat, Membaca Desain Furniture & Merealisasikan Ke Dalam Media Kayu. Dengan Pengalaman Produksi Lebih Dari 20 Tahun.
        <p></p>
        <img src="assets/images/Garansi 2 Tahun.png">Semua Produk Yang Kami Jual Bergaransi Selama 2 Tahun. Inilah Komitmen Kami Demi Kepuasan Anda.
        <p></p>
        <img src="assets/images/Kayu Jati Terbaik.png">Kayu Adalah Bagian Terpenting Dalam Furniture. Kayu Jati TPK Perhutani Adalah Solusi Tepat Untuk Menghasilkan Produk Furniture Bermutu Tinggi.
        <p></p>
        </div>
       <div>
     </section>
    <!-- About Section end -->

    <!-- Produk Section start -->
    <section id="produk" class="produk">
      <div class="section-header">
        <h2><span>Produk</span> Kami</h2>
        <div class="header-line">
          <span></span>
          <i data-feather="package"></i>
          <span></span>
        </div>
        <div class="section-description">
          <p>Nikmati keindahan furniture eksklusif kami yang menghadirkan perpaduan sempurna 
            antara keindahan klasik Jepara dan sentuhan modern.</p>
          <p>Setiap karya dibuat dengan keahlian tangan terampil pengrajin Jepara berpengalaman, 
            menggunakan kayu jati berkualitas tinggi untuk memastikan keindahan yang abadi 
            di rumah Anda.</p>
        </div>
      </div>

      <div class="row">
        <?php
        // Koneksi ke MongoDB
        require_once '../config/database.php';
        $db = new Database();
        $collection = $db->getCollection('products');
        
        // Mengambil semua produk
        $products = $collection->find()->toArray();
        
        foreach ($products as $product): ?>
          <div class="produk-card">
            <div class="produk-badge">New Arrival</div>
            <div class="produk-img-container">
              <img src="<?php echo '../' . $product['img_url']; ?>" 
                   alt="<?php echo htmlspecialchars($product['name']); ?>" 
                   class="produk-card-img">
              <div class="produk-overlay">
                <div class="produk-icons">
                  <a href="#" class="cart-btn" 
                     onclick="addToCart(
                         '<?php echo htmlspecialchars($product['name']); ?>', 
                         'Rp. <?php echo number_format($product['price'], 0, ',', '.'); ?>', 
                         '<?php echo '../' . $product['img_url']; ?>', 
                         '<?php echo (string)$product['_id']; ?>')"
                     data-product-id="<?php echo (string)$product['_id']; ?>">
                    <i data-feather="shopping-cart"></i>
                  </a>
                  <a href="#" class="detail-btn" 
                     data-product-id="<?php echo (string)$product['_id']; ?>"
                     data-nama="<?php echo htmlspecialchars($product['name']); ?>" 
                     data-harga="Rp. <?php echo number_format($product['price'], 0, ',', '.'); ?>" 
                     data-gambar="<?php echo '../' . $product['img_url']; ?>"
                     data-stok="<?php echo $product['stock']; ?>"
                     data-kategori="<?php echo htmlspecialchars($product['category']); ?>">
                     <i data-feather="eye"></i>
                  </a>
                </div>
              </div>
            </div>
            <div class="produk-info">
              <h3 class="produk-card-title"><?php echo htmlspecialchars($product['name']); ?></h3>
              <div class="produk-rating">
                <?php for($i = 0; $i < 5; $i++): ?>
                  <i data-feather="star" class="star-full"></i>
                <?php endfor; ?>
                <span>(<?php echo $product['stock']; ?> tersedia)</span>
              </div>
              <p class="produk-description"><?php echo htmlspecialchars($product['category']); ?></p>
              <p class="produk-card-price">Rp. <?php echo number_format($product['price'], 0, ',', '.'); ?></p>
              <a href="#" class="produk-btn" onclick="beliSekarang(this)" 
                 data-nama="<?php echo htmlspecialchars($product['name']); ?>" 
                 data-harga="Rp. <?php echo number_format($product['price'], 0, ',', '.'); ?>">
                 Beli Sekarang
              </a>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </section>
    <!-- Produk Section end -->

    <!-- Contact Section start -->
    <section id="contact" class="contact">
      <h2><span>Kontak</span> Kami</h2>
      <p>Hubungi kami untuk konsultasi desain dan pemesanan furniture impian Anda. Tim kami siap membantu mewujudkan interior hunian yang elegan.</p>

      <div class="row">
        <div class="contact-info">
          <div class="info-item">
            <i data-feather="map-pin"></i>
            <div class="info-content">
              <h3>Lokasi Showroom</h3>
              <p>Jl. Raya Jepara-Kudus KM 10, Jepara, Jawa Tengah</p>
            </div>
          </div>
          
          <div class="info-item">
            <i data-feather="clock"></i>
            <div class="info-content">
              <h3>Jam Operasional</h3>
              <p>Senin - Sabtu: 08.00 - 17.00 WIB</p>
              <p>Minggu: 09.00 - 15.00 WIB</p>
            </div>
          </div>

          <div class="info-item">
            <i data-feather="phone"></i>
            <div class="info-content">
              <h3>Telepon / WhatsApp</h3>
              <p>+62 858-7861-2964</p>
            </div>
          </div>

          <iframe
            src="https://www.google.com/maps/embed?pb=!1m14!1m8!1m3!1d1981.65852729239!2d110.65589145767808!3d-6.607467903181635!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x2e711e51026a21a5%3A0x6f2841323e515b76!2sJati%20Murni!5e0!3m2!1sid!2sid!4v1698586793130!5m2!1sid!2sid"
            allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade" class="map">
          </iframe>
        </div>

        <form action="" class="contact-form" id="contactForm" onsubmit="kirimPesan()">
          <h3>Kirim Pesan</h3>
          <div class="input-group">
            <i data-feather="user"></i>
            <input type="text" id="nama" placeholder="Nama Lengkap" required>
          </div>
          <div class="input-group">
            <i data-feather="mail"></i>
            <input type="email" id="email" placeholder="Email" required>
          </div>
          <div class="input-group">
            <i data-feather="phone"></i>
            <input type="tel" id="phone" placeholder="Nomor WhatsApp" required>
          </div>
          <div class="input-group">
            <i data-feather="message-square"></i>
            <textarea id="message" placeholder="Pesan Anda" required></textarea>
          </div>
          <button type="submit" class="btn">
            <i data-feather="send"></i>
            <span>Kirim Pesan</span>
          </button>
        </form>
      </div>
    </section>
    <!-- Contact Section end -->

    <!-- FAQ Section start -->
    <section id="faq" class="faq-section">
      <h2><span>FAQ</span></h2>
      <p>Pertanyaan yang Sering Diajukan</p>
      
      <div class="faq-container">
        <div class="faq">
          <h3 class="faq-title">
            Bagaimana cara melakukan pemesanan?
          </h3>
          <p class="faq-text">
            Anda dapat melakukan pemesanan melalui WhatsApp kami atau mengunjungi showroom kami secara langsung. Tim kami akan membantu Anda memilih produk yang sesuai dengan kebutuhan.
          </p>
          <button class="faq-toggle">
            <i data-feather="chevron-down"></i>
          </button>
        </div>
        
        <div class="faq">
          <h3 class="faq-title">
            Berapa lama waktu pengerjaan?
          </h3>
          <p class="faq-text">
            Waktu pengerjaan bervariasi tergantung jenis produk, biasanya berkisar antara 2-4 minggu untuk produk custom.
          </p>
          <button class="faq-toggle">
            <i data-feather="chevron-down"></i>
          </button>
        </div>
        
        <div class="faq">
          <h3 class="faq-title">
            Apakah tersedia layanan pengiriman?
          </h3>
          <p class="faq-text">
            Ya, kami menyediakan layanan pengiriman ke seluruh Indonesia dengan biaya yang ditentukan berdasarkan lokasi pengiriman.
          </p>
          <button class="faq-toggle">
            <i data-feather="chevron-down"></i>
          </button>
        </div>
      </div>
    </section>
    <!-- FAQ Section end -->

    <!-- Footer start -->
    <footer>
      <div class="footer-container">
        <!-- Company Info -->
        <div class="footer-section">
          <h3>Jati<span>Murni</span></h3>
          <p>Menghadirkan furniture berkualitas premium dengan sentuhan klasik Jepara dan desain modern untuk melengkapi keindahan hunian Anda.</p>
          <div class="contact-info">
            <p><i data-feather="map-pin"></i> Jl. Raya Jepara-Kudus KM 10, Jepara</p>
            <p><i data-feather="phone"></i> +62 856-4019-9495</p>
            <p><i data-feather="mail"></i> jatimurni@gmail.com</p>
          </div>
        </div>

        <!-- Quick Links -->
        <div class="footer-section">
          <h4>Menu Utama</h4>
          <div class="links">
            <a href="#home"><i data-feather="chevron-right"></i> Home</a>
            <a href="#about"><i data-feather="chevron-right"></i> Tentang Kami</a>
            <a href="#produk"><i data-feather="chevron-right"></i> Produk</a>
            <a href="#contact"><i data-feather="chevron-right"></i> Kontak</a>
            <a href="#faq"><i data-feather="chevron-right"></i> FAQ</a>
          </div>
        </div>

        <!-- Social Media -->
        <div class="footer-section">
          <h4>Ikuti Kami</h4>
          <p>Dapatkan update terbaru tentang produk dan promo menarik</p>
          <div class="socials">
            <a href="https://www.instagram.com/furniture_jati_murni" class="social-btn instagram">
              <i data-feather="instagram"></i>
              <span>Instagram</span>
            </a>
            <a href="https://www.facebook.com/jatimurnijpr/" class="social-btn facebook">
              <i data-feather="facebook"></i>
              <span>Facebook</span>
            </a>
            <a href="https://wa.me/6285878612964" class="social-btn whatsapp">
              <i data-feather="message-circle"></i>
              <span>WhatsApp</span>
            </a>
          </div>
        </div>
      </div>

      <div class="footer-bottom">
        <div class="credit">
          <p>Created with <i data-feather="heart"></i> by <a href="">Muhammad Ashab Ibnu Abdul Aziz | 231240001399</a></p>
          <p>&copy; 2024 Jati Murni. All rights reserved.</p>
        </div>
      </div>
    </footer>
    <!-- Footer end -->

    <!-- Modal Box Item Detail -->
    <div class="modal" id="item-detail-modal">
      <div class="modal-container">
        <a href="#" class="close-icon"><i data-feather="x"></i></a>
        <div class="modal-content">
          <div class="modal-image">
            <img src="" alt="Product Image" id="modal-product-image">
          </div>
          <div class="product-content">
            <span class="product-category">Furniture</span>
            <h3 class="product-title"></h3>
            <div class="product-rating">
              <div class="stars">
                <i data-feather="star" class="star-full"></i>
                <i data-feather="star" class="star-full"></i>
                <i data-feather="star" class="star-full"></i>
                <i data-feather="star" class="star-full"></i>
                <i data-feather="star" class="star-full"></i>
              </div>
              <span class="rating-count"></span>
            </div>
            <div class="product-price">
              <div class="discount-badge">Hemat 40%</div>
              <div class="price">
                <span class="current"></span>
                <span class="original"></span>
              </div>
            </div>
            <div class="product-description">
              <p></p>
              <ul class="features">
                <li><i data-feather="check-circle"></i> Kayu Jati Berkualitas</li>
                <li><i data-feather="check-circle"></i> Finishing Premium</li>
                <li><i data-feather="check-circle"></i> Garansi 2 Tahun</li>
              </ul>
            </div>
          </div>
        </div>
      </div>
    </div>
    <!-- Modal Box Item Detail end -->

    <!-- Feather Icons -->
    <script>
      feather.replace();
    </script>

    <!-- My Javascript -->
    <script src="assets/js/main.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script>
      document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.produk-card-img').forEach(img => {
          img.onerror = function() {
            this.src = 'assets/images/default-product.jpg';
          }
        });
      });
    </script>
</body>

</html>