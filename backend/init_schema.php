<?php
// backend/init_schema.php
// Run this locally to create schema tables expected by the app (pg_listings, pg_images)
require_once 'connect.php';

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS pg_listings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        owner_id INT NOT NULL,
        pg_code VARCHAR(64) DEFAULT NULL,
        pg_name VARCHAR(255) NOT NULL,
        district VARCHAR(100) DEFAULT NULL,
        state VARCHAR(100) DEFAULT NULL,
        location_area VARCHAR(255) DEFAULT NULL,
        address TEXT NOT NULL,
        city VARCHAR(100) NOT NULL,
        occupancy_type VARCHAR(20) DEFAULT 'co-ed',
        capacity INT DEFAULT 0,
        available_beds INT DEFAULT 0,
        occupancy_status ENUM('available','filling_fast','full') DEFAULT 'available',
        monthly_rent DECIMAL(10,2) DEFAULT 0,
        amenities TEXT DEFAULT NULL,
        sharing_type VARCHAR(50) DEFAULT NULL,
        latitude DECIMAL(10,8) DEFAULT NULL,
        longitude DECIMAL(11,8) DEFAULT NULL,
        status ENUM('pending','approved') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE,
        UNIQUE KEY unique_pg_code (pg_code)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Ensure columns exist for older installations (MySQL 8+ supports IF NOT EXISTS)
    $pdo->exec("ALTER TABLE pg_listings ADD COLUMN IF NOT EXISTS pg_code VARCHAR(64) DEFAULT NULL;" );
    $pdo->exec("ALTER TABLE pg_listings ADD COLUMN IF NOT EXISTS district VARCHAR(100) DEFAULT NULL;" );
    $pdo->exec("ALTER TABLE pg_listings ADD COLUMN IF NOT EXISTS state VARCHAR(100) DEFAULT NULL;" );
    $pdo->exec("ALTER TABLE pg_listings ADD COLUMN IF NOT EXISTS location_area VARCHAR(255) DEFAULT NULL;" );
    $pdo->exec("ALTER TABLE pg_listings ADD COLUMN IF NOT EXISTS occupancy_type VARCHAR(20) DEFAULT 'co-ed';" );
    $pdo->exec("ALTER TABLE pg_listings ADD COLUMN IF NOT EXISTS available_beds INT DEFAULT 0;" );
    $pdo->exec("ALTER TABLE pg_listings ADD COLUMN IF NOT EXISTS occupancy_status ENUM('available','filling_fast','full') DEFAULT 'available';" );
    $pdo->exec("ALTER TABLE pg_listings ADD COLUMN IF NOT EXISTS amenities TEXT DEFAULT NULL;" );


    $pdo->exec("CREATE TABLE IF NOT EXISTS pg_images (
        id INT AUTO_INCREMENT PRIMARY KEY,
        pg_id INT NOT NULL,
        image_path VARCHAR(255) NOT NULL,
        uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (pg_id) REFERENCES pg_listings(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    echo "pg_listings and pg_images tables created or already exist.\n";
} catch (PDOException $e) {
    echo "Error creating tables: " . $e->getMessage() . "\n";
}

echo "Done.\n";
