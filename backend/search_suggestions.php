<?php
header('Content-Type: application/json');
require_once 'connect.php';
require_once __DIR__ . '/config.php';

try {
    $q = trim($_GET['q'] ?? '');
    if ($q === '') {
        echo json_encode(['ok' => true, 'suggestions' => []]);
        exit;
    }

    $like = "%{$q}%";
    $likeLower = mb_strtolower($like, 'UTF-8');
    $suggestions = [];
    $seen = [];

    $statusWhere = (defined('SHOW_PENDING_LISTINGS') && SHOW_PENDING_LISTINGS) ? "IN ('approved','pending')" : "= 'approved'";
    // 1) PG names and codes (prioritize) - case-insensitive
    $stmt = $pdo->prepare('SELECT id, pg_name, pg_code FROM pg_listings WHERE status ' . $statusWhere . ' AND (LOWER(pg_name) LIKE :q OR LOWER(pg_code) LIKE :q) LIMIT 8');
    $stmt->execute([':q' => $likeLower]);
    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $label = $r['pg_name'] ?: ($r['pg_code'] ?? '');
        if ($label && !isset($seen[$label])) {
            $seen[$label] = true;
            $suggestions[] = ['type' => 'pg', 'id' => (int)$r['id'], 'label' => $label];
        }
    }

    // 2) Cities and location areas (distinct)
    $stmt = $pdo->prepare('SELECT DISTINCT city, location_area FROM pg_listings WHERE status ' . $statusWhere . ' AND (LOWER(city) LIKE :q OR LOWER(location_area) LIKE :q) LIMIT 6');
    $stmt->execute([':q' => $likeLower]);
    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if (!empty($r['location_area'])) {
            $label = $r['location_area'];
            if (!isset($seen[$label])) { $seen[$label] = true; $suggestions[] = ['type' => 'area', 'label' => $label]; }
        }
        if (!empty($r['city'])) {
            $label = $r['city'];
            if (!isset($seen[$label])) { $seen[$label] = true; $suggestions[] = ['type' => 'city', 'label' => $label]; }
        }
    }

    // 3) Addresses / district / state (combine)
    $stmt = $pdo->prepare('SELECT DISTINCT address, city, district, state FROM pg_listings WHERE status ' . $statusWhere . ' AND (LOWER(address) LIKE :q OR LOWER(district) LIKE :q OR LOWER(state) LIKE :q) LIMIT 8');
    $stmt->execute([':q' => $likeLower]);
    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $parts = array_filter([($r['address'] ?? ''), ($r['city'] ?? ''), ($r['district'] ?? ''), ($r['state'] ?? '')]);
        $label = trim(implode(', ', $parts));
        if ($label !== '' && !isset($seen[$label])) { $seen[$label] = true; $suggestions[] = ['type' => 'address', 'label' => $label]; }
    }

    // trim to 12 suggestions
    $suggestions = array_slice($suggestions, 0, 12);

    echo json_encode(['ok' => true, 'suggestions' => $suggestions]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Server error']);
}
