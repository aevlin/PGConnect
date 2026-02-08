<?php
session_start();
require_once 'connect.php';
require_once __DIR__ . '/config.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'owner') {
    header('Location: ../backend/login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['csvfile'])) {
    header('Location: ../owner/bulk-upload.php?error=No+file+uploaded');
    exit;
}

$owner_id = $_SESSION['user_id'];
$file = $_FILES['csvfile'];

if ($file['error'] !== UPLOAD_ERR_OK) {
    header('Location: ../owner/bulk-upload.php?error=File+upload+error');
    exit;
}

$handle = fopen($file['tmp_name'], 'r');
if (!$handle) {
    header('Location: ../owner/bulk-upload.php?error=Cannot+open+file');
    exit;
}

$header = fgetcsv($handle);
$required = ['pg_code','pg_name','district','state','location_area','city','address','monthly_rent','capacity','available_beds','occupancy_type','occupancy_status','sharing_type','latitude','longitude','amenities'];
if (!$header) {
    header('Location: ../owner/bulk-upload.php?error=Empty+CSV');
    exit;
}

$header = array_map('trim', $header);
$lower = array_map('strtolower', $header);

// map header indices
$map = [];
foreach ($required as $col) {
    $idx = array_search($col, $lower);
    if ($idx === false) {
        header('Location: ../owner/bulk-upload.php?error=CSV+missing+column+' . urlencode($col));
        exit;
    }
    $map[$col] = $idx;
}

$inserted = 0;
$skipped = 0;
$errors = [];

$pdo->beginTransaction();
$newStatus = (defined('AUTO_APPROVE_LISTINGS') && AUTO_APPROVE_LISTINGS) ? 'approved' : 'pending';
$stmt = $pdo->prepare("INSERT INTO pg_listings (owner_id, pg_code, pg_name, district, state, location_area, city, address, occupancy_type, capacity, available_beds, occupancy_status, monthly_rent, amenities, sharing_type, latitude, longitude, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");

$rowNo = 1;
while (($row = fgetcsv($handle)) !== false) {
    $rowNo++;
    // basic row length check
    if (count($row) < count($header)) {
        $skipped++;
        $errors[] = "Row $rowNo: insufficient columns";
        continue;
    }

    $pg_code = trim($row[$map['pg_code']]);
    $pg_name = trim($row[$map['pg_name']]);
    $district = trim($row[$map['district']]);
    $state = trim($row[$map['state']]);
    $location_area = trim($row[$map['location_area']]);
    $city = trim($row[$map['city']]);
    $address = trim($row[$map['address']]);
    $monthly_rent = filter_var($row[$map['monthly_rent']], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    $capacity = filter_var($row[$map['capacity']], FILTER_SANITIZE_NUMBER_INT);
    $available_beds = filter_var($row[$map['available_beds']], FILTER_SANITIZE_NUMBER_INT);
    $occupancy_type = strtolower(trim($row[$map['occupancy_type']]));
    $occupancy_status = strtolower(trim($row[$map['occupancy_status']]));
    $sharing_type = strtolower(trim($row[$map['sharing_type']]));
    $latitude = is_numeric($row[$map['latitude']]) ? (float)$row[$map['latitude']] : null;
    $longitude = is_numeric($row[$map['longitude']]) ? (float)$row[$map['longitude']] : null;
    $amenities = trim($row[$map['amenities']] ?? '');

    // validate required fields
    if ($pg_code === '' || $pg_name === '' || $district === '' || $state === '' || $location_area === '' || $city === '' || $address === '' || $monthly_rent === '' || $capacity === '' || $available_beds === '' || $occupancy_type === '' || $occupancy_status === '') {
        $skipped++;
        $errors[] = "Row $rowNo: missing required fields";
        continue;
    }

    if (!in_array($sharing_type, ['single','double','triple'])) {
        $skipped++;
        $errors[] = "Row $rowNo: invalid sharing_type ($sharing_type)";
        continue;
    }

    $monthly_rent = (float)$monthly_rent;
    $capacity = (int)$capacity;

    try {
    $ok = $stmt->execute([$owner_id, $pg_code, $pg_name, $district, $state, $location_area, $city, $address, $occupancy_type, $capacity, $available_beds, $occupancy_status, $monthly_rent, $amenities, $sharing_type, $latitude, $longitude, $newStatus]);
        if ($ok) {
            $inserted++;
        } else {
            $skipped++;
            $errors[] = "Row $rowNo: DB insert failed";
        }
    } catch (Exception $e) {
        $skipped++;
        $errors[] = "Row $rowNo: exception - " . $e->getMessage();
    }

    // safety limit
    if ($rowNo >= 2000) break;
}

$pdo->commit();
fclose($handle);

$msg = "Inserted=$inserted; Skipped=$skipped";
if (!empty($errors)) {
    $msg .= '; Errors=' . urlencode(implode(' | ', array_slice($errors,0,5)));
}

header('Location: ../owner/bulk-upload.php?msg=' . urlencode($msg));
exit;
