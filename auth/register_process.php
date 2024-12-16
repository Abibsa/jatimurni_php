<?php
session_start();
require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nama = $_POST['nama'];
    $email = $_POST['email'];
    $nomer_telepon = $_POST['nomer_telepon'];
    $alamat = $_POST['alamat'];
    $username = $_POST['username'];
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $role = $_POST['role'];

    // Validasi password
    if ($password !== $confirm_password) {
        $_SESSION['error'] = "Password tidak cocok!";
        header('Location: ../register.php');
        exit;
    }

    try {
        $db = new Database();
        $users = $db->getCollection('users');

        // Tambahkan fungsi untuk generate ID
        $lastUser = $users->findOne(
            [],
            [
                'sort' => ['_id' => -1],
                'projection' => ['_id' => 1]
            ]
        );
        
        // Generate ID dengan format u001, u002, dst
        if ($lastUser) {
            $lastId = $lastUser['_id'];
            $numericPart = intval(substr($lastId, 1));
            $newId = 'u' . str_pad($numericPart + 1, 3, '0', STR_PAD_LEFT);
        } else {
            $newId = 'u001';
        }

        // Cek username sudah ada atau belum
        $existingUser = $users->findOne(['account.username' => $username]);
        if ($existingUser) {
            $_SESSION['error'] = "Username sudah digunakan!";
            header('Location: ../register.php');
            exit;
        }

        // Cek email sudah ada atau belum
        $existingEmail = $users->findOne(['email' => $email]);
        if ($existingEmail) {
            $_SESSION['error'] = "Email sudah terdaftar!";
            header('Location: ../register.php');
            exit;
        }

        // Hash password
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        // Data user baru dengan ID yang telah digenerate
        $newUser = [
            '_id' => $newId,
            'nama' => $nama,
            'email' => $email,
            'nomer_telepon' => $nomer_telepon,
            'alamat' => $alamat,
            'role' => $role,
            'account' => [[
                'username' => $username,
                'password' => $hashedPassword
            ]],
            'created_at' => new MongoDB\BSON\UTCDateTime(),
            'status' => 'active'
        ];

        // Insert ke database
        $result = $users->insertOne($newUser);

        if ($result->getInsertedCount() > 0) {
            $_SESSION['success'] = "Registrasi berhasil! Silakan login.";
            header('Location: ../login.php');
            exit;
        } else {
            throw new Exception("Gagal menyimpan data");
        }

    } catch (Exception $e) {
        $_SESSION['error'] = "Terjadi kesalahan: " . $e->getMessage();
        header('Location: ../register.php');
        exit;
    }
}
?> 