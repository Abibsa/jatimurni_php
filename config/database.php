<?php
require_once __DIR__ . '/../vendor/autoload.php'; 

class Database {
    private $connection;

    public function __construct() {
        $this->connection = (new MongoDB\Client("mongodb://localhost:27017"))->selectDatabase('1399meubel');
    }

    public function getCollection($collectionName) {
        return $this->connection->selectCollection($collectionName);
    }

    public function updateUser($username, $email, $password) {
        // Contoh query untuk memperbarui pengguna
        $query = "UPDATE users SET email = :email, password = :password WHERE username = :username";
        $stmt = $this->conn->prepare($query);
        
        // Bind parameter
        $stmt->bindParam(':username', $username);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':password', password_hash($password, PASSWORD_DEFAULT)); // Hash password

        return $stmt->execute();
    }
}
?>

