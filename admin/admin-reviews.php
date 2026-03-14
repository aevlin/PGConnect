<?php
require_once '../backend/auth.php';
require_role('admin');
require_once '../backend/connect.php';
require_once '../backend/reviews_schema.php';
ensure_reviews_schema($pdo);
require_once '../includes/header.php';

$adminId = (int)$_SESSION['user_id'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['review_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    if ($id > 0 && in_array($action, ['hide','unhide'], true)) {
        $hidden = $action === 'hide' ? 1 : 0;
        $u = $pdo->prepare('UPDATE reviews SET is_hidden = ?, moderated_by = ?, moderated_at = NOW() WHERE id = ?');
        $u->execute([$hidden, $adminId, $id]);
    }
    header('Location: admin-reviews.php');
    exit;
}

$rows = $pdo->query("SELECT r.*, p.pg_name, u.name AS user_name
                     FROM reviews r
                     JOIN pg_listings p ON p.id = r.pg_id
                     JOIN users u ON u.id = r.user_id
                     ORDER BY r.created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
?>
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h5 mb-0">Review Moderation</h1>
    <a href="admin-dashboard.php" class="btn btn-outline-secondary btn-sm">Back</a>
  </div>
  <?php if (empty($rows)): ?>
    <div class="alert alert-info">No reviews found.</div>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table table-sm bg-white shadow-sm">
        <thead class="table-light"><tr><th>ID</th><th>PG</th><th>User</th><th>Rating</th><th>Comment</th><th>Owner reply</th><th>Status</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($rows as $r): ?>
            <tr>
              <td><?php echo (int)$r['id']; ?></td>
              <td><?php echo htmlspecialchars($r['pg_name']); ?></td>
              <td><?php echo htmlspecialchars($r['user_name']); ?></td>
              <td><?php echo (int)$r['rating']; ?></td>
              <td><?php echo htmlspecialchars($r['comment'] ?? ''); ?></td>
              <td><?php echo htmlspecialchars($r['owner_response'] ?? ''); ?></td>
              <td><?php echo !empty($r['is_hidden']) ? 'Hidden' : 'Visible'; ?></td>
              <td>
                <form method="POST" class="d-inline">
                  <input type="hidden" name="review_id" value="<?php echo (int)$r['id']; ?>">
                  <?php if (!empty($r['is_hidden'])): ?>
                    <button class="btn btn-sm btn-outline-success" name="action" value="unhide">Unhide</button>
                  <?php else: ?>
                    <button class="btn btn-sm btn-outline-danger" name="action" value="hide">Hide</button>
                  <?php endif; ?>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
<?php require_once '../includes/footer.php'; ?>

