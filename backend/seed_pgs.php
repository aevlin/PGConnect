<?php
// backend/seed_pgs.php
// Usage (CLI): php backend/seed_pgs.php --count=50 --center=12.9716,77.5946 --radius_km=2
// Usage (web):  http://localhost/PGConnect/backend/seed_pgs.php?count=50&center=12.97,77.59&radius_km=2

require_once 'connect.php';

// Helper to parse args for CLI
function get_arg($name, $default = null) {
    global $argc, $argv;
    if (php_sapi_name() === 'cli') {
        foreach ($argv as $arg) {
            if (strpos($arg, "--$name=") === 0) {
                return substr($arg, strlen("--$name="));
            }
        }
        return $default;
    } else {
        return isset($_GET[$name]) ? $_GET[$name] : $default;
    }
}

$count = (int)get_arg('count', 50);
$centerStr = get_arg('center', ''); // format: lat,lng
$radius_km = (float)get_arg('radius_km', 2);

if ($count <= 0) $count = 50;

$center = null;
if (!empty($centerStr)) {
    $parts = preg_split('/[,\s]+/', trim($centerStr));
    if (count($parts) === 2 && is_numeric($parts[0]) && is_numeric($parts[1])) {
        $center = [(float)$parts[0], (float)$parts[1]];
    }
}

// Find or create an owner account to attach listings
try {
    $stmt = $pdo->prepare('SELECT id FROM users WHERE role = "owner" LIMIT 1');
    $stmt->execute();
    $owner = $stmt->fetchColumn();
    if (!$owner) {
        $pw = password_hash('owner123', PASSWORD_DEFAULT);
        $ins = $pdo->prepare('INSERT INTO users (name,email,password,role,created_at) VALUES (?, ?, ?, "owner", NOW())');
        $email = 'seed-owner+' . time() . '@pgconnect.test';
        $ins->execute(['Auto Owner', $email, $pw]);
        $owner = $pdo->lastInsertId();
        echo "Created owner user: $email (id=$owner)\n";
    } else {
        echo "Using existing owner id=$owner\n";
    }
} catch (Exception $e) {
    echo "Error finding/creating owner: " . $e->getMessage() . "\n";
    exit(1);
}

// Cities to spread listings across India (name and approximate lat/lng)
$cities = [
    ['Mumbai', 19.0760, 72.8777],
    ['Bengaluru', 12.9716, 77.5946],
    ['Delhi', 28.6139, 77.2090],
    ['Kochi', 9.9312, 76.2673],
    ['Hyderabad', 17.3850, 78.4867],
    ['Pune', 18.5204, 73.8567],
    ['Chennai', 13.0827, 80.2707],
    ['Kolkata', 22.5726, 88.3639],
    ['Ahmedabad', 23.0225, 72.5714],
    ['Gurugram', 28.4595, 77.0266]
];

function random_point_around($lat, $lng, $radius_km) {
    // random distance and bearing
    $randDist = sqrt(mt_rand() / mt_getrandmax()) * $radius_km; // uniform in circle
    $randDistMeters = $randDist * 1000.0;
    $bearing = mt_rand() / mt_getrandmax() * 2 * M_PI;

    // Earth's radius in meters
    $R = 6371000;
    $lat1 = deg2rad($lat);
    $lon1 = deg2rad($lng);
    $lat2 = asin(sin($lat1) * cos($randDistMeters/$R) + cos($lat1) * sin($randDistMeters/$R) * cos($bearing));
    $lon2 = $lon1 + atan2(sin($bearing) * sin($randDistMeters/$R) * cos($lat1), cos($randDistMeters/$R) - sin($lat1) * sin($lat2));
    return [rad2deg($lat2), rad2deg($lon2)];
}

$insertPg = $pdo->prepare('INSERT INTO pg_listings (owner_id, pg_code, pg_name, district, state, location_area, city, address, occupancy_type, capacity, available_beds, occupancy_status, monthly_rent, amenities, sharing_type, latitude, longitude, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())');
$insertImg = $pdo->prepare('INSERT INTO pg_images (pg_id, image_path) VALUES (?, ?)');

$inserted = 0;
try {
    $pdo->beginTransaction();
    for ($i = 0; $i < $count; $i++) {
        // choose base city: either random city or around provided center
        if ($center) {
            $pt = random_point_around($center[0], $center[1], $radius_km);
            $cityName = 'Nearby';
        } else {
            $c = $cities[array_rand($cities)];
            // random offset within ~5 km
            $pt = random_point_around($c[1], $c[2], rand(1,7));
            $cityName = $c[0];
        }

        $lat = $pt[0];
        $lng = $pt[1];

    $code = 'PG' . strtoupper(substr(md5(uniqid('', true)), 0, 6));
    $name = "Seed PG " . strtoupper(substr(md5(uniqid('', true)), 0, 6));
    $address = rand(10,999) . " Main Rd, Area " . rand(1,50);
    $rent = rand(4000,25000);
    $capacity = rand(4,40);
    $available = rand(0, $capacity);
    $occupancy = ['boys','girls','co-ed'][array_rand(['boys','girls','co-ed'])];
    $status = ['available','filling_fast','full'][array_rand(['available','filling_fast','full'])];
    $sharing = ['single','double','triple'][array_rand(['single','double','triple'])];
    $district = $cityName . ' Dist';
    $state = 'State ' . substr($cityName, 0, 3);
    $location_area = 'Area ' . rand(1,50);
    $amenities = 'WiFi,Hot water,Food';

    $insertPg->execute([$owner, $code, $name, $district, $state, $location_area, $cityName, $address, $occupancy, $capacity, $available, $status, $rent, $amenities, $sharing, $lat, $lng, 'approved']);
        $pgid = $pdo->lastInsertId();

        // assign a placeholder remote image (picsum)
        $imgUrl = "https://picsum.photos/seed/" . ($pgid + rand(1,9999)) . "/800/600";
        $insertImg->execute([$pgid, $imgUrl]);

        $inserted++;
    }
    $pdo->commit();
    echo "Inserted $inserted PGs.\n";
} catch (Exception $e) {
    $pdo->rollBack();
    echo "Error inserting PGs: " . $e->getMessage() . "\n";
    exit(1);
}

if (php_sapi_name() !== 'cli') {
    echo "<p>Inserted $inserted PGs.</p>";
    echo "<p><a href=\"../index.php\">Back to index</a></p>";
}

return 0;
