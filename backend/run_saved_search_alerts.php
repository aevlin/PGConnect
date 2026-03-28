<?php
header('Content-Type: application/json');
require_once 'connect.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/feature_schema.php';
require_once __DIR__ . '/notify.php';
ensure_session_started();

if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'user') {
    echo json_encode(['ok' => true, 'checked' => 0]);
    exit;
}

ensure_feature_schema($pdo);
$userId = (int)$_SESSION['user_id'];
$checked = 0;

try {
    $s = $pdo->prepare('SELECT * FROM saved_searches WHERE user_id = ? AND is_active = 1');
    $s->execute([$userId]);
    $searches = $s->fetchAll(PDO::FETCH_ASSOC);

    foreach ($searches as $ss) {
        $sql = "SELECT COUNT(*) FROM pg_listings p WHERE p.status = 'approved'";
        $params = [];
        if (!empty($ss['city'])) {
            $sql .= " AND LOWER(p.city) LIKE :city";
            $params[':city'] = '%' . strtolower($ss['city']) . '%';
        }
        if (!empty($ss['min_rent'])) {
            $sql .= " AND p.monthly_rent >= :min";
            $params[':min'] = (int)$ss['min_rent'];
        }
        if (!empty($ss['max_rent'])) {
            $sql .= " AND p.monthly_rent <= :max";
            $params[':max'] = (int)$ss['max_rent'];
        }
        if (!empty($ss['sharing'])) {
            $sql .= " AND p.sharing_type = :sharing";
            $params[':sharing'] = $ss['sharing'];
        }
        $c = $pdo->prepare($sql);
        $c->execute($params);
        $count = (int)$c->fetchColumn();
        if ($count > (int)$ss['last_match_count']) {
            notify_user($pdo, $userId, 'user', 'New PG match found', "Saved search has {$count} matching PGs.", base_url('user/search.php'));
        }
        $u = $pdo->prepare('UPDATE saved_searches SET last_match_count = ? WHERE id = ?');
        $u->execute([$count, (int)$ss['id']]);
        $checked++;
    }
} catch (Throwable $e) {}

echo json_encode(['ok' => true, 'checked' => $checked]);
