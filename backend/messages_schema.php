<?php
// backend/messages_schema.php
// Ensure chat tables exist.

function ensure_chat_schema(PDO $pdo) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS conversations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        owner_id INT NOT NULL,
        admin_id INT DEFAULT NULL,
        pg_id INT DEFAULT NULL,
        conversation_type VARCHAR(20) DEFAULT 'tenant_owner',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user (user_id),
        INDEX idx_owner (owner_id),
        INDEX idx_admin (admin_id),
        INDEX idx_type (conversation_type),
        INDEX idx_pg (pg_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $convCols = $pdo->query("SHOW COLUMNS FROM conversations")->fetchAll(PDO::FETCH_COLUMN);
    $convCols = array_map('strtolower', $convCols);
    if (!in_array('admin_id', $convCols, true)) {
        $pdo->exec("ALTER TABLE conversations ADD COLUMN admin_id INT DEFAULT NULL");
    }
    if (!in_array('conversation_type', $convCols, true)) {
        $pdo->exec("ALTER TABLE conversations ADD COLUMN conversation_type VARCHAR(20) DEFAULT 'tenant_owner'");
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        conversation_id INT NOT NULL,
        sender_id INT NOT NULL,
        sender_role VARCHAR(10) NOT NULL,
        recipient_role VARCHAR(10) DEFAULT NULL,
        message_type VARCHAR(30) DEFAULT 'text',
        action_key VARCHAR(50) DEFAULT NULL,
        metadata_json TEXT DEFAULT NULL,
        message TEXT NOT NULL,
        is_read TINYINT(1) DEFAULT 0,
        is_read_admin TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_conv (conversation_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Add missing columns if table already existed
    $cols = $pdo->query("SHOW COLUMNS FROM messages")->fetchAll(PDO::FETCH_COLUMN);
    $cols = array_map('strtolower', $cols);
    if (!in_array('recipient_role', $cols, true)) {
        $pdo->exec("ALTER TABLE messages ADD COLUMN recipient_role VARCHAR(10) DEFAULT NULL");
    }
    if (!in_array('message_type', $cols, true)) {
        $pdo->exec("ALTER TABLE messages ADD COLUMN message_type VARCHAR(30) DEFAULT 'text'");
    }
    if (!in_array('action_key', $cols, true)) {
        $pdo->exec("ALTER TABLE messages ADD COLUMN action_key VARCHAR(50) DEFAULT NULL");
    }
    if (!in_array('metadata_json', $cols, true)) {
        $pdo->exec("ALTER TABLE messages ADD COLUMN metadata_json TEXT DEFAULT NULL");
    }
    if (!in_array('is_read', $cols, true)) {
        $pdo->exec("ALTER TABLE messages ADD COLUMN is_read TINYINT(1) DEFAULT 0");
    }
    if (!in_array('is_read_admin', $cols, true)) {
        $pdo->exec("ALTER TABLE messages ADD COLUMN is_read_admin TINYINT(1) DEFAULT 0");
    }
}

function chat_insert_message(PDO $pdo, array $data) {
    ensure_chat_schema($pdo);
    $stmt = $pdo->prepare('
        INSERT INTO messages (
            conversation_id, sender_id, sender_role, recipient_role,
            message_type, action_key, metadata_json, message, is_read
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0)
    ');
    $stmt->execute([
        (int)$data['conversation_id'],
        (int)$data['sender_id'],
        (string)$data['sender_role'],
        $data['recipient_role'] ?? null,
        $data['message_type'] ?? 'text',
        $data['action_key'] ?? null,
        isset($data['metadata']) ? json_encode($data['metadata'], JSON_UNESCAPED_SLASHES) : null,
        (string)$data['message'],
    ]);
    return (int)$pdo->lastInsertId();
}
