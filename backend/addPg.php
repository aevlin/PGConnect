<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'owner') {
    header('Location: ../backend/login.php');
    exit;
}

// include DB connection (PDO)
require_once 'connect.php'; // defines $pdo

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $owner_id     = $_SESSION['user_id'];
    $pg_name      = trim($_POST['pg_name'] ?? '');
    $city         = trim($_POST['city'] ?? '');
    $address      = trim($_POST['address'] ?? '');
    $pg_code      = trim($_POST['pg_code'] ?? '');
    $district     = trim($_POST['district'] ?? '');
    $state        = trim($_POST['state'] ?? '');
    $location_area= trim($_POST['location_area'] ?? '');
    $monthly_rent = $_POST['monthly_rent'] ?? '';
    $capacity     = $_POST['capacity'] ?? '';
    $available_beds = $_POST['available_beds'] ?? '';
    $occupancy_type = trim($_POST['occupancy_type'] ?? 'co-ed');
    $occupancy_status = trim($_POST['occupancy_status'] ?? 'available');
    $amenities    = trim($_POST['amenities'] ?? '');
    $sharing_type = trim($_POST['sharing_type'] ?? '');

    // basic required checks
    if ($pg_code === '' || $pg_name === '' || $district === '' || $state === '' || $location_area === '' || $city === '' || $address === '' || $monthly_rent === '' || $capacity === '' || $available_beds === '' || $occupancy_type === '' || $occupancy_status === '') {
    header('Location: ../owner/owner-add-pg.php?error=Please+fill+all+fields');
        exit;
    }

    // numeric validations
    if (!is_numeric($monthly_rent) || $monthly_rent < 0) {
    header('Location: ../owner/owner-add-pg.php?error=Invalid+rent+amount');
        exit;
    }

    if (!ctype_digit((string)$capacity) || (int)$capacity <= 0) {
    header('Location: ../owner/owner-add-pg.php?error=Invalid+capacity');
        exit;
    }

    // normalize values
    $monthly_rent = number_format((float)$monthly_rent, 2, '.', '');
    $capacity     = (int)$capacity;
    $available_beds = (int)$available_beds;

    // INSERT using PDO
    $sql = "INSERT INTO pg_listings
        (owner_id, pg_code, pg_name, district, state, location_area, city, address, occupancy_type, capacity, available_beds, occupancy_status, monthly_rent, amenities, sharing_type, status)
        VALUES (:owner_id, :pg_code, :pg_name, :district, :state, :location_area, :city, :address, :occupancy_type, :capacity, :available_beds, :occupancy_status, :monthly_rent, :amenities, :sharing_type, 'pending')";

    $stmt = $pdo->prepare($sql);
    $ok = $stmt->execute([
        ':owner_id' => $owner_id,
        ':pg_code' => $pg_code,
        ':pg_name' => $pg_name,
        ':district' => $district,
        ':state' => $state,
        ':location_area' => $location_area,
        ':city' => $city,
        ':address' => $address,
        ':occupancy_type' => $occupancy_type,
        ':capacity' => $capacity,
        ':available_beds' => $available_beds,
        ':occupancy_status' => $occupancy_status,
        ':monthly_rent' => $monthly_rent,
        ':amenities' => $amenities,
        ':sharing_type' => $sharing_type,
    ]);

    if ($ok) {
        header('Location: ../owner/owner-add-pg.php?success=1');
        exit;
    } else {
        header('Location: ../owner/owner-add-pg.php?error=Could+not+save+PG');
        exit;
    }
} else {
    header('Location: ../owner/owner-add-pg.php');
    exit;
}
