<?php
require_once '../config/database.php';

header('Content-Type: application/json');
$db = new Database();
$collection = $db->getCollection('products');

// Fungsi untuk handle upload gambar
function handleImageUpload($file) {
    $targetDir = "../assets/images/products/";
    if (!file_exists($targetDir)) {
        mkdir($targetDir, 0777, true);
    }

    $fileName = time() . '_' . basename($file["name"]);
    $targetFile = $targetDir . $fileName;
    $imageFileType = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));
    
    // Validasi file
    $allowedTypes = array('jpg', 'jpeg', 'png', 'gif');
    if (!in_array($imageFileType, $allowedTypes)) {
        throw new Exception('Hanya file JPG, JPEG, PNG & GIF yang diizinkan.');
    }
    
    if ($file["size"] > 5000000) { // 5MB max
        throw new Exception('File terlalu besar (maksimal 5MB).');
    }
    
    if (move_uploaded_file($file["tmp_name"], $targetFile)) {
        return BASE_URL . "/assets/images/products/" . $fileName;
    }
    
    throw new Exception('Gagal mengupload file.');
}

try {
    switch($_SERVER['REQUEST_METHOD']) {
        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);
            
            // Validasi data
            if (empty($data['name']) || empty($data['category']) || 
                empty($data['price']) || !isset($data['stock'])) {
                throw new Exception('Semua field harus diisi!');
            }

            $imageUrl = null;
            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $imageUrl = handleImageUpload($_FILES['image']);
            }

            $result = $collection->insertOne([
                'name' => $data['name'],
                'category' => $data['category'],
                'price' => (int)$data['price'],
                'stock' => (int)$data['stock'],
                'description' => $data['description'],
                'image_url' => $imageUrl,
                'created_at' => new MongoDB\BSON\UTCDateTime()
            ]);

            echo json_encode([
                'success' => true,
                'message' => 'Produk berhasil ditambahkan',
                'id' => (string)$result->getInsertedId()
            ]);
            break;

        case 'PUT':
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (empty($data['id'])) {
                throw new Exception('ID produk tidak ditemukan!');
            }

            $result = $collection->updateOne(
                ['_id' => new MongoDB\BSON\ObjectId($data['id'])],
                ['$set' => [
                    'name' => $data['name'],
                    'category' => $data['category'],
                    'price' => (int)$data['price'],
                    'stock' => (int)$data['stock'],
                    'description' => $data['description'],
                    'image_url' => $data['image_url'] ?? '',
                    'updated_at' => new MongoDB\BSON\UTCDateTime()
                ]]
            );

            echo json_encode([
                'success' => true,
                'message' => 'Produk berhasil diupdate'
            ]);
            break;

        default:
            throw new Exception('Method tidak diizinkan!');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?> 