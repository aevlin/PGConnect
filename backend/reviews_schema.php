<?php
// backend/reviews_schema.php
// Ensure reviews table exists.

function ensure_reviews_schema(PDO $pdo) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS reviews (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        pg_id INT NOT NULL,
        rating INT NOT NULL,
        comment TEXT DEFAULT NULL,
        owner_response TEXT DEFAULT NULL,
        edit_count INT DEFAULT 0,
        updated_at DATETIME DEFAULT NULL,
        is_hidden TINYINT(1) DEFAULT 0,
        moderated_by INT DEFAULT NULL,
        moderated_at DATETIME DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_pg (pg_id),
        INDEX idx_user (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $cols = $pdo->query("SHOW COLUMNS FROM reviews")->fetchAll(PDO::FETCH_COLUMN);
    $cols = array_map('strtolower', $cols);
    if (!in_array('owner_response', $cols, true)) $pdo->exec("ALTER TABLE reviews ADD COLUMN owner_response TEXT DEFAULT NULL");
    if (!in_array('edit_count', $cols, true)) $pdo->exec("ALTER TABLE reviews ADD COLUMN edit_count INT DEFAULT 0");
    if (!in_array('updated_at', $cols, true)) $pdo->exec("ALTER TABLE reviews ADD COLUMN updated_at DATETIME DEFAULT NULL");
    if (!in_array('is_hidden', $cols, true)) $pdo->exec("ALTER TABLE reviews ADD COLUMN is_hidden TINYINT(1) DEFAULT 0");
    if (!in_array('moderated_by', $cols, true)) $pdo->exec("ALTER TABLE reviews ADD COLUMN moderated_by INT DEFAULT NULL");
    if (!in_array('moderated_at', $cols, true)) $pdo->exec("ALTER TABLE reviews ADD COLUMN moderated_at DATETIME DEFAULT NULL");
}
