<?php
// backend/seed_users.php
// Run this locally to insert test accounts into the pgconnect database.
// Usage (from project root): php backend/seed_users.php

require_once 'connect.php';

$users = [
    [
        'name' => 'Admin',
        'email' => 'admin@pgconnect.in',
        'password' => 'admin123',
        'role' => 'admin'
    ],
    [
        'name' => 'Owner Test',
        'email' => 'owner@pgconnect.in',
        'password' => 'owner123',
        'role' => 'owner'
    ],
    [
        'name' => 'User Test',
        'email' => 'user@pgconnect.in',
        'password' => 'user123',
        'role' => 'user'
    ]
];

foreach ($users as $u) {
    try {
        // check exists
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$u['email']]);
        if ($stmt->fetch()) {
            echo "User already exists: {$u['email']}\n";
            continue;
        }

        $hash = password_hash($u['password'], PASSWORD_DEFAULT);
        $ins = $pdo->prepare('INSERT INTO users (name, email, password, role, created_at) VALUES (?, ?, ?, ?, NOW())');
        $ins->execute([$u['name'], $u['email'], $hash, $u['role']]);
        echo "Inserted {$u['email']} (password: {$u['password']})\n";
    } catch (Exception $e) {
        echo "Error inserting {$u['email']}: " . $e->getMessage() . "\n";
    }
}

echo "Done.\n";
