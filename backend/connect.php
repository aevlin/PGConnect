<?php
// backend/connect.php
require_once __DIR__ . '/bootstrap.php';

$host    = getenv('PGCONNECT_DB_HOST') ?: getenv('DB_HOST') ?: 'localhost';
$port    = getenv('PGCONNECT_DB_PORT') ?: getenv('DB_PORT') ?: '';
$db      = getenv('PGCONNECT_DB_NAME') ?: getenv('DB_NAME') ?: 'pgconnect';
$user    = getenv('PGCONNECT_DB_USER') ?: getenv('DB_USER') ?: 'root';
$pass    = getenv('PGCONNECT_DB_PASS') ?: getenv('DB_PASS') ?: '';
$charset = getenv('PGCONNECT_DB_CHARSET') ?: getenv('DB_CHARSET') ?: 'utf8mb4';

$dsn = "mysql:host={$host};dbname={$db};charset={$charset}";
if ($port !== '') {
    $dsn = "mysql:host={$host};port={$port};dbname={$db};charset={$charset}";
}

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    @error_log('PGConnect database connection failed: ' . $e->getMessage());
    die('Database connection failed. Check your deployment database settings.');
}
