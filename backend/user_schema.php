<?php
// backend/user_schema.php
// Ensure user profile and owner document columns exist.

function ensure_user_profile_schema(PDO $pdo) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        email VARCHAR(150) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        role ENUM('user','owner','admin') NOT NULL DEFAULT 'user',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $cols = $pdo->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_COLUMN);
    $cols = array_map('strtolower', $cols);
    $add = function($name, $def) use ($pdo, $cols) {
        if (!in_array(strtolower($name), $cols, true)) {
            $pdo->exec("ALTER TABLE users ADD COLUMN $name $def");
        }
    };

    $add('phone', "VARCHAR(50) DEFAULT NULL");
    $add('address', "VARCHAR(255) DEFAULT NULL");
    $add('dob', "DATE DEFAULT NULL");
    $add('profile_photo', "VARCHAR(255) DEFAULT NULL");
    $add('owner_aadhaar', "VARCHAR(255) DEFAULT NULL");
    $add('owner_permit', "VARCHAR(255) DEFAULT NULL");
    $add('owner_verification_status', "VARCHAR(20) DEFAULT 'pending'");
}

