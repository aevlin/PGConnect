<?php
// backend/migrate_add_uploaded_at.php
// Adds uploaded_at column to pg_images if it does not exist.
require_once 'connect.php';
try {
    // Check columns
    $stmt = $pdo->prepare("SHOW COLUMNS FROM pg_images LIKE 'uploaded_at'");
    $stmt->execute();
    $exists = $stmt->fetch();
    if ($exists) {
        echo "Column uploaded_at already exists in pg_images.\n";
        exit;
    }
    // Add column
    $pdo->exec("ALTER TABLE pg_images ADD COLUMN uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
    echo "Added uploaded_at column to pg_images.\n";
} catch (Exception $e) {
    echo "Migration error: " . $e->getMessage();
}
