<?php
require_once '../config/database.php';

header('Content-Type: application/json');

try {
    if (!isset($_GET['id'])) {
        throw new Exception('ID produk tidak ditemukan');
    }

    $db = new Database();
    $product = $db->getCollection('products')->findOne(['_id' => $_GET['id']]);
    
    if (!$product) {
        throw new Exception('Produk tidak ditemukan');
    }

    echo json_encode([
        'success' => true,
        'product' => $product,
        'price' => $product['price']
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?> 