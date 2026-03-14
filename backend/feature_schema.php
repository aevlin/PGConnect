<?php
require_once __DIR__ . '/system_schema.php';

function ensure_feature_schema(PDO $pdo) {
    ensure_system_schema($pdo);

    $pdo->exec("CREATE TABLE IF NOT EXISTS availability_blocks (
        id INT AUTO_INCREMENT PRIMARY KEY,
        pg_id INT NOT NULL,
        block_date DATE NOT NULL,
        reason VARCHAR(180) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_pg_date (pg_id, block_date),
        INDEX idx_pg (pg_id),
        INDEX idx_date (block_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $pdo->exec("CREATE TABLE IF NOT EXISTS service_tickets (
        id INT AUTO_INCREMENT PRIMARY KEY,
        booking_id INT NOT NULL,
        pg_id INT NOT NULL,
        user_id INT NOT NULL,
        owner_id INT NOT NULL,
        category VARCHAR(40) NOT NULL,
        title VARCHAR(180) NOT NULL,
        description TEXT DEFAULT NULL,
        status VARCHAR(30) DEFAULT 'open',
        owner_note TEXT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_booking (booking_id),
        INDEX idx_pg (pg_id),
        INDEX idx_owner_status (owner_id, status),
        INDEX idx_user_status (user_id, status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $pdo->exec("CREATE TABLE IF NOT EXISTS saved_searches (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        city VARCHAR(120) DEFAULT NULL,
        min_rent INT DEFAULT NULL,
        max_rent INT DEFAULT NULL,
        sharing VARCHAR(20) DEFAULT NULL,
        is_active TINYINT(1) DEFAULT 1,
        last_match_count INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_user (user_id),
        INDEX idx_active (is_active)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
}

