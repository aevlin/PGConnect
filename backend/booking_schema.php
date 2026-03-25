<?php
// backend/booking_schema.php
// Ensure bookings table and columns exist for the booking workflow.

function ensure_bookings_schema(PDO $pdo) {
    // Create table if not exists (use VARCHAR status to avoid ENUM migration issues)
    $pdo->exec("CREATE TABLE IF NOT EXISTS bookings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        pg_id INT NOT NULL,
        contact_name VARCHAR(150) DEFAULT NULL,
        contact_phone VARCHAR(50) DEFAULT NULL,
        move_in_date DATE DEFAULT NULL,
        visit_requested TINYINT(1) DEFAULT 0,
        visit_status VARCHAR(20) DEFAULT NULL,
        visit_datetime DATETIME DEFAULT NULL,
        visit_note VARCHAR(255) DEFAULT NULL,
        message TEXT DEFAULT NULL,
        status VARCHAR(30) DEFAULT 'requested',
        payment_amount DECIMAL(10,2) DEFAULT NULL,
        payment_status VARCHAR(20) DEFAULT 'unpaid',
        payment_ref VARCHAR(120) DEFAULT NULL,
        paid_at DATETIME DEFAULT NULL,
        moved_out_at DATETIME DEFAULT NULL,
        owner_action_at DATETIME DEFAULT NULL,
        user_action_at DATETIME DEFAULT NULL,
        admin_notified_at DATETIME DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user (user_id),
        INDEX idx_pg (pg_id),
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Helper to add missing columns
    $cols = $pdo->query("SHOW COLUMNS FROM bookings")->fetchAll(PDO::FETCH_COLUMN);
    $cols = array_map('strtolower', $cols);
    $add = function($name, $def) use ($pdo, $cols) {
        if (!in_array(strtolower($name), $cols, true)) {
            $pdo->exec("ALTER TABLE bookings ADD COLUMN $name $def");
        }
    };

    $add('contact_name', "VARCHAR(150) DEFAULT NULL");
    $add('contact_phone', "VARCHAR(50) DEFAULT NULL");
    $add('move_in_date', "DATE DEFAULT NULL");
    $add('visit_requested', "TINYINT(1) DEFAULT 0");
    $add('visit_status', "VARCHAR(20) DEFAULT NULL");
    $add('visit_datetime', "DATETIME DEFAULT NULL");
    $add('visit_note', "VARCHAR(255) DEFAULT NULL");
    $add('message', "TEXT DEFAULT NULL");
    $add('status', "VARCHAR(30) DEFAULT 'requested'");
    $add('payment_amount', "DECIMAL(10,2) DEFAULT NULL");
    $add('payment_status', "VARCHAR(20) DEFAULT 'unpaid'");
    $add('payment_ref', "VARCHAR(120) DEFAULT NULL");
    $add('paid_at', "DATETIME DEFAULT NULL");
    $add('moved_out_at', "DATETIME DEFAULT NULL");
    $add('owner_action_at', "DATETIME DEFAULT NULL");
    $add('user_action_at', "DATETIME DEFAULT NULL");
    $add('admin_notified_at', "DATETIME DEFAULT NULL");

    // Ensure status is VARCHAR (upgrade from ENUM if needed)
    try {
        $pdo->exec("ALTER TABLE bookings MODIFY COLUMN status VARCHAR(30) DEFAULT 'requested'");
    } catch (Exception $e) {
        // ignore if not supported
    }

    // Fix foreign key to correct table (pg_listings)
    try {
        $dbName = $pdo->query('SELECT DATABASE()')->fetchColumn();
        if ($dbName) {
            $sql = "SELECT CONSTRAINT_NAME, REFERENCED_TABLE_NAME
                    FROM information_schema.KEY_COLUMN_USAGE
                    WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'bookings' AND COLUMN_NAME = 'pg_id'
                      AND REFERENCED_TABLE_NAME IS NOT NULL";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$dbName]);
            $fks = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($fks as $fk) {
                if (strtolower($fk['REFERENCED_TABLE_NAME']) !== 'pg_listings') {
                    $pdo->exec("ALTER TABLE bookings DROP FOREIGN KEY `{$fk['CONSTRAINT_NAME']}`");
                }
            }
        }
    } catch (Exception $e) {
        // ignore if information_schema not accessible
    }

    // Ensure correct foreign key exists (pg_id -> pg_listings.id)
    try {
        $pdo->exec("ALTER TABLE bookings
            ADD CONSTRAINT fk_bookings_pg_listings
            FOREIGN KEY (pg_id) REFERENCES pg_listings(id)
            ON DELETE CASCADE");
    } catch (Exception $e) {
        // ignore if already exists
    }
}
