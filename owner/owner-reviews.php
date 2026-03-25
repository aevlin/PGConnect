<?php
require_once '../backend/auth.php';
require_role('owner');
require_once '../backend/connect.php';
require_once '../backend/reviews_schema.php';
ensure_reviews_schema($pdo);

$ownerId = (int)$_SESSION['user_id'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reviewId = (int)($_POST['review_id'] ?? 0);
    $resp = trim($_POST['owner_response'] ?? '');
    if ($reviewId > 0) {
        $u = $pdo->prepare("UPDATE reviews r
                            JOIN pg_listings p ON p.id = r.pg_id
                            SET r.owner_response = ?
                            WHERE r.id = ? AND p.owner_id = ?");
        $u->execute([$resp, $reviewId, $ownerId]);
    }
    header('Location: owner-reviews.php');
    exit;
}

require_once '../includes/header.php';

$stmt = $pdo->prepare("SELECT r.*, p.pg_name, u.name AS user_name
                       FROM reviews r
                       JOIN pg_listings p ON p.id = r.pg_id
                       JOIN users u ON u.id = r.user_id
                       WHERE p.owner_id = ?
                       ORDER BY r.created_at DESC");
$stmt->execute([$ownerId]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<div class="container py-4">
  <h1 class="h5 mb-3">Reviews for Your PGs</h1>
  <?php if (empty($rows)): ?>
    <div class="alert alert-info">No reviews yet.</div>
  <?php else: ?>
    <?php foreach ($rows as $r): ?>
      <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
          <div class="d-flex justify-content-between">
            <div>
              <div class="fw-semibold"><?php echo htmlspecialchars($r['pg_name']); ?> · ★ <?php echo (int)$r['rating']; ?></div>
              <div class="small text-muted"><?php echo htmlspecialchars($r['user_name']); ?> · <?php echo htmlspecialchars($r['created_at']); ?></div>
              <div class="small mt-1"><?php echo htmlspecialchars($r['comment'] ?? ''); ?></div>
              <?php if (!empty($r['is_hidden'])): ?><div class="small text-danger mt-1">Hidden by admin</div><?php endif; ?>
            </div>
          </div>
          <form method="POST" class="mt-2">
            <input type="hidden" name="review_id" value="<?php echo (int)$r['id']; ?>">
            <div class="input-group">
              <input type="text" name="owner_response" class="form-control form-control-sm" value="<?php echo htmlspecialchars($r['owner_response'] ?? ''); ?>" placeholder="Reply to this review">
              <button class="btn btn-sm btn-primary">Save response</button>
            </div>
          </form>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>
<?php require_once '../includes/footer.php'; ?>
