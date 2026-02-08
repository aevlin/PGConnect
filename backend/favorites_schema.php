<?php
// backend/favorites_schema.php
// Ensure favorites table exists.

function ensure_favorites_schema(PDO $pdo) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS favorites (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        pg_id INT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY ux_user_pg (user_id, pg_id),
        INDEX idx_user (user_id),
        INDEX idx_pg (pg_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
}

