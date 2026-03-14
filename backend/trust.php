<?php

function pg_trust_score(PDO $pdo, $pgId) {
    $pgId = (int)$pgId;
    if ($pgId <= 0) return 0;
    $score = 0;
    try {
        $stmt = $pdo->prepare("SELECT p.owner_id, p.created_at, u.owner_verification_status, u.phone, u.profile_photo
                               FROM pg_listings p
                               JOIN users u ON u.id = p.owner_id
                               WHERE p.id = ? LIMIT 1");
        $stmt->execute([$pgId]);
        $r = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$r) return 0;

        if (($r['owner_verification_status'] ?? '') === 'verified') $score += 40;
        if (!empty($r['phone']) && !empty($r['profile_photo'])) $score += 20;
        if (!empty($r['created_at']) && strtotime($r['created_at']) >= strtotime('-180 days')) $score += 20;

        $rt = $pdo->prepare("SELECT AVG(TIMESTAMPDIFF(HOUR, created_at, owner_action_at)) FROM bookings WHERE pg_id = ? AND owner_action_at IS NOT NULL");
        $rt->execute([$pgId]);
        $avgHours = (float)$rt->fetchColumn();
        if ($avgHours > 0 && $avgHours <= 24) $score += 20;
        elseif ($avgHours > 24 && $avgHours <= 48) $score += 10;
    } catch (Throwable $e) {
        return max(0, min(100, $score));
    }
    return max(0, min(100, (int)$score));
}

