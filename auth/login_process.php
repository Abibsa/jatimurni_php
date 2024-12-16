<?php
session_start();
require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];
    $role = $_POST['role'];
    
    $db = new Database();
    $users = $db->getCollection('users');
    
    // Cari user berdasarkan username dan role
    $user = $users->findOne([
        'account' => [
            '$elemMatch' => [
                'username' => $username
            ]
        ],
        'role' => $role
    ]);

    if ($user) {
        // Verifikasi password
        $account = $user['account'][0];
        if (password_verify($password, $account['password'])) {
            $_SESSION['user_id'] = (string)$user['_id'];
            $_SESSION['username'] = $username;
            $_SESSION['role'] = $role;
            
            if (isset($_POST['remember'])) {
                setcookie('user_login', $username, time() + (86400 * 30), "/");
            }
            
            // Arahkan berdasarkan role
            if ($role === 'admin') {
                header('Location: ../index.php');
            } else if ($role === 'customer') {
                header('Location: ../customer/index.php');
            }
            exit;
        }
    }
    
    $_SESSION['error'] = 'Username atau password salah!';
    header('Location: ../login.php');
    exit;
}
?> 