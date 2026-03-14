<?php
require_once '../backend/auth.php';
require_role('admin');
require_once '../backend/connect.php';
require_once '../backend/feature_schema.php';
ensure_feature_schema($pdo);
require_once '../includes/header.php';

$rows = $pdo->query('SELECT t.*, p.pg_name, u.name user_name, o.name owner_name
                     FROM service_tickets t
                     JOIN pg_listings p ON p.id=t.pg_id
                     JOIN users u ON u.id=t.user_id
                     JOIN users o ON o.id=t.owner_id
                     ORDER BY t.updated_at DESC')->fetchAll(PDO::FETCH_ASSOC);
?>
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h5 mb-0">Tickets Monitor</h1>
    <a href="admin-dashboard.php" class="btn btn-outline-secondary btn-sm">Back</a>
  </div>
  <div class="table-responsive">
    <table class="table table-sm bg-white shadow-sm">
      <thead class="table-light"><tr><th>ID</th><th>PG</th><th>User</th><th>Owner</th><th>Category</th><th>Title</th><th>Status</th><th>Updated</th></tr></thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><?php echo (int)$r['id']; ?></td>
          <td><?php echo htmlspecialchars($r['pg_name']); ?></td>
          <td><?php echo htmlspecialchars($r['user_name']); ?></td>
          <td><?php echo htmlspecialchars($r['owner_name']); ?></td>
          <td><?php echo htmlspecialchars($r['category']); ?></td>
          <td><?php echo htmlspecialchars($r['title']); ?></td>
          <td><?php echo htmlspecialchars($r['status']); ?></td>
          <td><?php echo htmlspecialchars($r['updated_at']); ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require_once '../includes/footer.php'; ?>

