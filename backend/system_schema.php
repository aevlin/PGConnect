<?php
// backend/system_schema.php
// Shared schema upgrades for notifications, audits, and performance indexes.

function ensure_system_schema(PDO $pdo) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        user_role VARCHAR(20) NOT NULL,
        title VARCHAR(160) NOT NULL,
        message TEXT DEFAULT NULL,
        link VARCHAR(255) DEFAULT NULL,
        is_read TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user_role (user_id, user_role),
        INDEX idx_read (is_read),
        INDEX idx_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $pdo->exec("CREATE TABLE IF NOT EXISTS audit_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        actor_id INT DEFAULT NULL,
        actor_role VARCHAR(20) DEFAULT NULL,
        action VARCHAR(120) NOT NULL,
        target_type VARCHAR(80) DEFAULT NULL,
        target_id INT DEFAULT NULL,
        details TEXT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_actor (actor_id, actor_role),
        INDEX idx_action (action),
        INDEX idx_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Ensure favorites unique per user+pg (safe best-effort).
    try {
        $pdo->exec("ALTER TABLE favorites ADD UNIQUE KEY uq_user_pg (user_id, pg_id)");
    } catch (Throwable $e) {}

    // Bookings query indexes for timeline/filtering.
    try {
        $pdo->exec("ALTER TABLE bookings ADD INDEX idx_user_status_created (user_id, status, created_at)");
    } catch (Throwable $e) {}
    try {
        $pdo->exec("ALTER TABLE bookings ADD INDEX idx_pg_status_created (pg_id, status, created_at)");
    } catch (Throwable $e) {}
}

