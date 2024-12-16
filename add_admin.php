<?php
require_once 'config/database.php';

$db = new Database();
$users = $db->getCollection('users');

// Data admin
$adminData = [
    'nama' => 'Abib',
    'email' => 'azizabib22@gmail.com',
    'alamat' => 'Jl. Kedung - Jepara, Tegalsambi, Kec. Tahunan, Kabupaten Jepara, Jawa Tengah',
    'nomer_telepon' => '085878612964',
    'role' => 'admin',
    'account' => [[
        'username' => 'abib',
        'password' => password_hash('abib123', PASSWORD_DEFAULT)
    ]]
];

// Cek apakah admin sudah ada
$existingAdmin = $users->findOne(['email' => 'abib@admin.com']);

if (!$existingAdmin) {
    $users->insertOne($adminData);
    echo "Admin berhasil ditambahkan\n";
    echo "Username: abib\n";
    echo "Password: abib123\n";
} else {
    echo "Admin sudah ada\n";
}
?> 