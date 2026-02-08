<?php
// backend/getPgs.php
header('Content-Type: application/json');

require_once 'connect.php';
require_once __DIR__ . '/config.php';

$logPath = __DIR__ . '/getPgs.log';

try {
    // Optional nearby filter: lat, lng in decimal degrees, radius in kilometers
    $lat = isset($_GET['lat']) ? $_GET['lat'] : null;
    $lng = isset($_GET['lng']) ? $_GET['lng'] : null;
    $radius = isset($_GET['radius']) ? (float)$_GET['radius'] : 5.0; // default 5 km

    // Validate lat/lng if provided
    if ($lat !== null) {
        if (!is_numeric($lat)) {
            echo json_encode(['error' => 'invalid_lat']);
            exit;
        }
        $lat = (float)$lat;
    }
    if ($lng !== null) {
        if (!is_numeric($lng)) {
            echo json_encode(['error' => 'invalid_lng']);
            exit;
        }
        $lng = (float)$lng;
    }

    $statusWhere = (defined('SHOW_PENDING_LISTINGS') && SHOW_PENDING_LISTINGS) ? "IN ('approved','pending')" : "= 'approved'";

    if ($lat !== null && $lng !== null) {
        // Haversine formula to compute distance in km (Earth radius ~6371 km)
        $sql = 'SELECT id, pg_name, city, address AS location, monthly_rent AS rent, capacity, sharing_type,
                       latitude, longitude,
                       (SELECT image_path FROM pg_images WHERE pg_id = pg_listings.id ORDER BY id LIMIT 1) AS cover_image,
                       (6371 * acos(
                           cos(radians(:lat)) * cos(radians(latitude)) * cos(radians(longitude) - radians(:lng)) +
                           sin(radians(:lat)) * sin(radians(latitude))
                       )) AS distance
                FROM pg_listings
                WHERE status $statusWhere AND latitude IS NOT NULL AND longitude IS NOT NULL
                HAVING distance <= :radius
                ORDER BY distance ASC
                LIMIT 500';

        $stmt = $pdo->prepare($sql);
        $stmt->execute([':lat' => $lat, ':lng' => $lng, ':radius' => $radius]);
        $pgs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($pgs)) {
            @file_put_contents($logPath, date('Y-m-d H:i:s') . " No nearby PGs for {$lat},{$lng} radius={$radius}\n", FILE_APPEND);
        }
    } else {
        $stmt = $pdo->prepare(
            'SELECT id, pg_name, city, address AS location, monthly_rent AS rent, capacity, sharing_type, latitude, longitude,
                    (SELECT image_path FROM pg_images WHERE pg_id = pg_listings.id ORDER BY id LIMIT 1) AS cover_image
             FROM pg_listings
             WHERE status $statusWhere AND latitude IS NOT NULL AND longitude IS NOT NULL
             ORDER BY created_at DESC
             LIMIT 1000'
        );
        $stmt->execute();
        $pgs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ensure numeric lat/lng in output
    foreach ($pgs as &$p) {
        $p['latitude'] = isset($p['latitude']) ? (float)$p['latitude'] : null;
        $p['longitude'] = isset($p['longitude']) ? (float)$p['longitude'] : null;
        if (isset($p['distance'])) $p['distance'] = (float)$p['distance'];
    }

    echo json_encode($pgs);
} catch (Exception $e) {
    $msg = $e->getMessage();
    @file_put_contents($logPath, date('Y-m-d H:i:s') . " ERROR: " . $msg . "\n", FILE_APPEND);

    // Increment error counter for this message
    $counts = [];
    if (is_readable(ERROR_COUNTER_FILE)) {
        $raw = @file_get_contents(ERROR_COUNTER_FILE);
        $counts = $raw ? json_decode($raw, true) : [];
        if (!is_array($counts)) $counts = [];
    }
    $now = time();
    // prune old entries
    foreach ($counts as $k => $arr) {
        if (!isset($arr['ts']) || ($now - $arr['ts']) > ERROR_ALERT_WINDOW) unset($counts[$k]);
    }
    $key = md5($msg);
    if (!isset($counts[$key])) $counts[$key] = ['count' => 0, 'ts' => $now, 'msg' => $msg];
    $counts[$key]['count']++;
    $counts[$key]['ts'] = $now;
    @file_put_contents(ERROR_COUNTER_FILE, json_encode($counts));

    // send alert when threshold exceeded
    if ($counts[$key]['count'] >= ERROR_ALERT_THRESHOLD) {
        $subject = "PGConnect: repeated getPgs errors";
        $body = "Error repeated {$counts[$key]['count']} times within window: \n" . $msg . "\nCheck $logPath";
        send_admin_alert($subject, $body);
        // reset counter for this error so we don't spam
        unset($counts[$key]);
        @file_put_contents(ERROR_COUNTER_FILE, json_encode($counts));
    }

    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}
