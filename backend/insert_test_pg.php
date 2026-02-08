<?php
// backend/insert_test_pg.php
// Small helper to insert a single test PG (Koovapally example) for local testing.
// Usage (web): http://localhost/PGConnect/backend/insert_test_pg.php
// WARNING: For local dev only. Ensure you remove this file when done.

require_once 'connect.php';

try {
    // Find an owner account or create one
    $stmt = $pdo->prepare('SELECT id FROM users WHERE role = "owner" LIMIT 1');
    $stmt->execute();
    $owner = $stmt->fetchColumn();
    if (!$owner) {
        $pw = password_hash('owner123', PASSWORD_DEFAULT);
        $ins = $pdo->prepare('INSERT INTO users (name,email,password,role,created_at) VALUES (?, ?, ?, "owner", NOW())');
        $email = 'seed-owner+' . time() . '@pgconnect.test';
        $ins->execute(['Auto Owner', $email, $pw]);
        $owner = $pdo->lastInsertId();
    }

    // Check if a PG with this pg_code already exists
    $pg_code = 'GRM9+7P8';
    $check = $pdo->prepare('SELECT id FROM pg_listings WHERE pg_code = ? LIMIT 1');
    $check->execute([$pg_code]);
    if ($check->fetchColumn()) {
        echo "Test PG already exists (pg_code={$pg_code}).\n";
        exit;
    }

    $pg_name = 'Amal Jyothi College PG Test';
    $address = 'Amal Jyothi College of Engineering, Koovappally, Kanjirapally - Erumely Rd, Koovappally, Kerala 686518';
    $city = 'Koovappally';
    $location_area = 'Koovappally';
    $district = 'Kanjirapally';
    $state = 'Kerala';
    $rent = 7000;
    $capacity = 20;
    $available_beds = 10;
    $occupancy_type = 'co-ed';
    $occupancy_status = 'available';
    $sharing_type = 'single';
    $latitude = 9.6175; // approximate
    $longitude = 76.8521; // approximate
    $amenities = 'WiFi,Hot water,Meals';

    $ins = $pdo->prepare('INSERT INTO pg_listings (owner_id, pg_code, pg_name, district, state, location_area, city, address, occupancy_type, capacity, available_beds, occupancy_status, monthly_rent, amenities, sharing_type, latitude, longitude, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "approved", NOW())');
    $ins->execute([$owner, $pg_code, $pg_name, $district, $state, $location_area, $city, $address, $occupancy_type, $capacity, $available_beds, $occupancy_status, $rent, $amenities, $sharing_type, $latitude, $longitude]);

    $pgid = $pdo->lastInsertId();
    // add a placeholder image
    $img = $pdo->prepare('INSERT INTO pg_images (pg_id, image_path) VALUES (?, ?)');
    $img->execute([$pgid, 'https://picsum.photos/seed/' . $pgid . '/800/600']);

    echo "Inserted test PG id={$pgid}, pg_code={$pg_code}\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
