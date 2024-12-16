<?php
require_once '../config/database.php';
session_start();

header('Content-Type: application/json');

try {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data) {
        throw new Exception('Data tidak valid');
    }

    $db = new Database();
    
    // Simpan data transaksi
    $result = $db->getCollection('transactions')->insertOne([
        '_id' => $data['transaction']['_id'],
        'userId' => $data['transaction']['userId'],
        'status' => $data['transaction']['status'],
        'orderDate' => new MongoDB\BSON\UTCDateTime(strtotime($data['transaction']['orderDate']) * 1000),
        'shippingAddress' => $data['transaction']['shippingAddress'],
        'shippingCity' => $data['transaction']['shippingCity'],
        'shippingCost' => $data['transaction']['shippingCost'],
        'subtotal' => $data['transaction']['subtotal'],
        'totalAmount' => $data['transaction']['totalAmount'],
        'products' => $data['transaction']['products']
    ]);

    // Simpan data pembayaran
    $db->getCollection('payments')->insertOne([
        '_id' => $data['payment']['_id'],
        'transactionId' => $data['payment']['transactionId'],
        'amount' => $data['payment']['amount'],
        'paymentMethod' => $data['payment']['paymentMethod'],
        'paymentDate' => new MongoDB\BSON\UTCDateTime(strtotime($data['payment']['paymentDate']) * 1000),
        'status' => $data['payment']['status']
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Checkout berhasil',
        'transactionId' => $data['transaction']['_id']
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?> 