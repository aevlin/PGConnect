<?php
// Assign all PG listings to a specific owner (admin only)
require_once __DIR__ . '/bootstrap.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'admin') {
  http_response_code(403);
  echo "Admin login required.";
  exit;
}

require_once __DIR__ . '/connect.php';

$email = 'aevlin@gmail.com';
$name = 'Aevlin';

try {
  // find owner by email
  $stmt = $pdo->prepare('SELECT id, role FROM users WHERE email = ? LIMIT 1');
  $stmt->execute([$email]);
  $u = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$u) {
    echo "Owner account not found for {$email}. Please sign up as owner first, then retry.";
    exit;
  }
  if ($u['role'] !== 'owner') {
    // promote to owner
    $pdo->prepare('UPDATE users SET role = ? WHERE id = ?')->execute(['owner', $u['id']]);
  }
  // update all listings
  $upd = $pdo->prepare('UPDATE pg_listings SET owner_id = ?');
  $upd->execute([$u['id']]);
  echo "All PGs now assigned to {$name} ({$email}).";
} catch (Throwable $e) {
  echo "Error: " . $e->getMessage();
}
?>
