<?php
// backend/connect.php
$host    = 'localhost';
$db      = 'pgconnect';   // make sure this database exists in phpMyAdmin
$user    = 'root';        // default XAMPP user
$pass    = '';            // default XAMPP password (empty)
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    // In production you would log this instead of echoing
    die('Database connection failed');
}
