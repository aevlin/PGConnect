<?php
require_once '../backend/connect.php';
require_once '../backend/auth.php';
require_once '../backend/booking_schema.php';
require_once '../backend/reviews_schema.php';
require_once '../backend/system_schema.php';
require_once '../backend/notify.php';
require_once '../backend/audit.php';

require_role('user');
ensure_bookings_schema($pdo);
ensure_reviews_schema($pdo);
ensure_system_schema($pdo);

$bookingId = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : (int)($_POST['booking_id'] ?? 0);
if ($bookingId <= 0) {
    header('Location: booking-request.php'); exit;
}

$stmt = $pdo->prepare('SELECT b.*, p.pg_name, p.owner_id FROM bookings b JOIN pg_listings p ON p.id = b.pg_id WHERE b.id = ? AND b.user_id = ? LIMIT 1');
$stmt->execute([$bookingId, (int)$_SESSION['user_id']]);
$b = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$b || ($b['status'] ?? '') !== 'paid') {
    header('Location: booking-request.php'); exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rating = (int)($_POST['rating'] ?? 0);
    $comment = trim($_POST['comment'] ?? '');
    if ($rating < 1 || $rating > 5) {
        $error = 'Please provide a valid rating (1-5).';
    } else {
        try {
            $pdo->beginTransaction();
            $r = $pdo->prepare('INSERT INTO reviews (user_id, pg_id, rating, comment) VALUES (?, ?, ?, ?)');
            $r->execute([(int)$_SESSION['user_id'], (int)$b['pg_id'], $rating, $comment]);
            $u = $pdo->prepare('UPDATE bookings SET status = ?, moved_out_at = NOW(), user_action_at = NOW() WHERE id = ?');
            $u->execute(['left', $bookingId]);
            $pdo->commit();

            notify_user($pdo, (int)$b['owner_id'], 'owner', 'Tenant left PG', 'A tenant marked checkout and added a review.', base_url('owner/owner-bookings.php'));
            audit_log($pdo, 'user_checkout_with_review', 'booking', (int)$bookingId, 'rating=' . $rating);

            header('Location: booking-request.php');
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = 'Unable to complete checkout. Please try again.';
        }
    }
}
require_once '../includes/header.php';
?>
<div class="container py-5">
  <div class="row justify-content-center">
    <div class="col-lg-7">
      <div class="card border-0 shadow-sm">
        <div class="card-body p-4">
          <h1 class="h4 mb-1">Leave PG and Submit Review</h1>
          <p class="small text-muted mb-3">You are checking out from <?php echo htmlspecialchars($b['pg_name']); ?>.</p>
          <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
          <form method="POST">
            <input type="hidden" name="booking_id" value="<?php echo (int)$bookingId; ?>">
            <div class="mb-3">
              <label class="form-label">Rating *</label>
              <select name="rating" class="form-select" required>
                <option value="">Select rating</option>
                <option value="5">5 - Excellent</option>
                <option value="4">4 - Good</option>
                <option value="3">3 - Average</option>
                <option value="2">2 - Poor</option>
                <option value="1">1 - Very poor</option>
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label">Review (optional)</label>
              <textarea name="comment" rows="3" class="form-control" placeholder="Share your stay experience"></textarea>
            </div>
            <div class="d-flex gap-2">
              <a href="booking-request.php" class="btn btn-outline-secondary">Cancel</a>
              <button class="btn btn-danger">I Left and Submit Review</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>
<?php require_once '../includes/footer.php'; ?>
