<?php
require_once '../config/database.php';

header('Content-Type: application/json');

try {
    $db = new Database();
    $collection = $db->getCollection('products');
    
    $query = isset($_GET['q']) ? trim($_GET['q']) : '';
    
    if (empty($query)) {
        echo json_encode([]);
        exit;
    }
    
    // Buat regex pattern untuk pencarian
    $regex = new MongoDB\BSON\Regex($query, 'i');
    
    // Pipeline aggregation dengan $lookup
    $pipeline = [
        [
            '$match' => [
                '$or' => [
                    ['name' => $regex],
                    ['category' => $regex]
                ]
            ]
        ],
        [
            // Lookup ke collection categories
            '$lookup' => [
                'from' => 'categories',
                'localField' => 'category_id', 
                'foreignField' => '_id',
                'as' => 'category_info'
            ]
        ],
        [
            // Lookup ke collection reviews
            '$lookup' => [
                'from' => 'reviews',
                'localField' => '_id',
                'foreignField' => 'product_id',
                'as' => 'reviews'
            ]
        ],
        [
            // Hitung rata-rata rating
            '$addFields' => [
                'average_rating' => [
                    '$avg' => '$reviews.rating'
                ],
                'review_count' => [
                    '$size' => '$reviews'
                ],
                'category_name' => [
                    '$first' => '$category_info.name'
                ]
            ]
        ],
        [
            // Project fields yang dibutuhkan
            '$project' => [
                'name' => 1,
                'price' => 1,
                'description' => 1,
                'img_url' => 1,
                'stock' => 1,
                'category_name' => 1,
                'average_rating' => 1,
                'review_count' => 1
            ]
        ]
    ];

    $products = $collection->aggregate($pipeline)->toArray();

    echo json_encode([
        'success' => true,
        'products' => $products
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} 