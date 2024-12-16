<?php
require_once '../config/database.php';

header('Content-Type: application/json');

try {
    $db = new Database();
    $collection = $db->getCollection('products');
    
    $query = isset($_GET['query']) ? trim($_GET['query']) : '';
    
    if (empty($query)) {
        echo json_encode([]);
        exit;
    }
    
    // Buat regex pattern untuk pencarian
    $regex = new MongoDB\BSON\Regex($query, 'i');
    
    // Cari produk berdasarkan nama atau kategori
    $products = $collection->find([
        '$or' => [
            ['name' => $regex],
            ['category' => $regex]
        ]
    ])->toArray();
    
    // Format hasil pencarian
    $results = array_map(function($product) {
        return [
            '_id' => $product['_id'],
            'name' => $product['name'],
            'category' => $product['category'],
            'price' => $product['price'],
            'img_url' => $product['img_url'],
            'stock' => $product['stock']
        ];
    }, $products);
    
    echo json_encode($results);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
} 