<?php
// backend/messages_schema.php
// Ensure chat tables exist.

function ensure_chat_schema(PDO $pdo) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS conversations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        owner_id INT NOT NULL,
        pg_id INT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user (user_id),
        INDEX idx_owner (owner_id),
        INDEX idx_pg (pg_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $pdo->exec("CREATE TABLE IF NOT EXISTS messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        conversation_id INT NOT NULL,
        sender_id INT NOT NULL,
        sender_role VARCHAR(10) NOT NULL,
        recipient_role VARCHAR(10) DEFAULT NULL,
        message TEXT NOT NULL,
        is_read TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_conv (conversation_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Add missing columns if table already existed
    $cols = $pdo->query("SHOW COLUMNS FROM messages")->fetchAll(PDO::FETCH_COLUMN);
    $cols = array_map('strtolower', $cols);
    if (!in_array('recipient_role', $cols, true)) {
        $pdo->exec("ALTER TABLE messages ADD COLUMN recipient_role VARCHAR(10) DEFAULT NULL");
    }
    if (!in_array('is_read', $cols, true)) {
        $pdo->exec("ALTER TABLE messages ADD COLUMN is_read TINYINT(1) DEFAULT 0");
    }
}
