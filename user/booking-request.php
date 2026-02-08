<?php
require_once '../includes/header.php';
require_once '../backend/connect.php';
require_once '../backend/booking_schema.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../backend/login.php'); exit;
}

ensure_bookings_schema($pdo);

$userId = (int)$_SESSION['user_id'];
$bookings = [];
$queryError = '';
try {
    $stmt = $pdo->prepare('SELECT b.*, p.pg_name, p.city, p.address, u.name AS owner_name
                           FROM bookings b
                           JOIN pg_listings p ON p.id = b.pg_id
                           JOIN users u ON u.id = p.owner_id
                           WHERE b.user_id = ?
                           ORDER BY b.created_at DESC');
    $stmt->execute([$userId]);
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $queryError = (defined('DEV_MODE') && DEV_MODE) ? $e->getMessage() : 'Failed to load booking requests.';
}
?>

<div class="container py-5">
  <h1 class="h4 mb-3">Booking Requests</h1>
  <?php if ($queryError): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($queryError); ?></div>
  <?php endif; ?>

  <?php if (empty($bookings)): ?>
    <div class="alert alert-info">No booking requests yet. When you request a booking, it will appear here.</div>
  <?php else: ?>
    <div class="list-group">
      <?php foreach ($bookings as $b): ?>
        <div class="list-group-item">
          <div class="d-flex justify-content-between align-items-start">
            <div>
              <strong><?php echo htmlspecialchars($b['pg_name']); ?></strong>
              <div class="small text-muted"><?php echo htmlspecialchars($b['address'] ?? ''); ?>, <?php echo htmlspecialchars($b['city'] ?? ''); ?></div>
              <div class="small text-muted">Owner: <?php echo htmlspecialchars($b['owner_name']); ?></div>
            </div>
            <div class="text-end">
              <div class="small">Status: <strong><?php echo htmlspecialchars($b['status']); ?></strong></div>
              <div class="mt-2">
                <?php if ($b['status'] === 'owner_approved'): ?>
                  <a class="btn btn-sm btn-success" href="user-booking-action.php?id=<?php echo (int)$b['id']; ?>&action=confirm">Confirm</a>
                  <a class="btn btn-sm btn-outline-danger" href="user-booking-action.php?id=<?php echo (int)$b['id']; ?>&action=cancel">Cancel</a>
                <?php elseif ($b['status'] === 'requested'): ?>
                  <a class="btn btn-sm btn-outline-danger" href="user-booking-action.php?id=<?php echo (int)$b['id']; ?>&action=cancel">Cancel</a>
                <?php else: ?>
                  <span class="badge bg-secondary">No action</span>
                <?php endif; ?>
              </div>
            </div>
          </div>
          <?php if (!empty($b['message'])): ?>
            <div class="mt-2"><em><?php echo nl2br(htmlspecialchars($b['message'])); ?></em></div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>
