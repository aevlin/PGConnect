<?php
require_once '../backend/auth.php';
require_role('owner');
require_once '../backend/connect.php';
require_once '../backend/feature_schema.php';
ensure_feature_schema($pdo);
require_once '../includes/header.php';

$ownerId = (int)$_SESSION['user_id'];
$pgId = isset($_GET['pg_id']) ? (int)$_GET['pg_id'] : (int)($_POST['pg_id'] ?? 0);
if ($pgId <= 0) { header('Location: owner-pg-list.php'); exit; }

$own = $pdo->prepare('SELECT id, pg_name FROM pg_listings WHERE id = ? AND owner_id = ? LIMIT 1');
$own->execute([$pgId, $ownerId]);
$pg = $own->fetch(PDO::FETCH_ASSOC);
if (!$pg) { header('Location: owner-pg-list.php'); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $d = trim($_POST['block_date'] ?? '');
        $reason = trim($_POST['reason'] ?? '');
        if ($d !== '') {
            try {
                $ins = $pdo->prepare('INSERT IGNORE INTO availability_blocks (pg_id, block_date, reason) VALUES (?, ?, ?)');
                $ins->execute([$pgId, $d, $reason]);
            } catch (Throwable $e) {}
        }
    } elseif ($action === 'remove') {
        $bid = (int)($_POST['block_id'] ?? 0);
        if ($bid > 0) {
            $del = $pdo->prepare('DELETE FROM availability_blocks WHERE id = ? AND pg_id = ?');
            $del->execute([$bid, $pgId]);
        }
    }
    header('Location: owner-availability.php?pg_id=' . $pgId);
    exit;
}

$rows = [];
$s = $pdo->prepare('SELECT * FROM availability_blocks WHERE pg_id = ? ORDER BY block_date ASC');
$s->execute([$pgId]);
$rows = $s->fetchAll(PDO::FETCH_ASSOC);
?>
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h5 mb-0">Availability Calendar - <?php echo htmlspecialchars($pg['pg_name']); ?></h1>
    <a href="owner-pg-list.php" class="btn btn-outline-secondary btn-sm">Back</a>
  </div>
  <div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
      <form method="POST" class="row g-2 align-items-end">
        <input type="hidden" name="pg_id" value="<?php echo (int)$pgId; ?>">
        <input type="hidden" name="action" value="add">
        <div class="col-md-3">
          <label class="form-label">Block date</label>
          <input type="date" name="block_date" class="form-control" required>
        </div>
        <div class="col-md-6">
          <label class="form-label">Reason (optional)</label>
          <input type="text" name="reason" class="form-control" placeholder="Maintenance / full occupancy">
        </div>
        <div class="col-md-3">
          <button class="btn btn-primary w-100">Block date</button>
        </div>
      </form>
    </div>
  </div>

  <div class="card border-0 shadow-sm">
    <div class="card-body">
      <h2 class="h6">Blocked Dates</h2>
      <?php if (empty($rows)): ?>
        <p class="small text-muted mb-0">No blocked dates yet.</p>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table table-sm align-middle">
            <thead><tr><th>Date</th><th>Reason</th><th></th></tr></thead>
            <tbody>
              <?php foreach ($rows as $r): ?>
                <tr>
                  <td><?php echo htmlspecialchars($r['block_date']); ?></td>
                  <td><?php echo htmlspecialchars($r['reason'] ?? ''); ?></td>
                  <td class="text-end">
                    <form method="POST" class="d-inline">
                      <input type="hidden" name="pg_id" value="<?php echo (int)$pgId; ?>">
                      <input type="hidden" name="action" value="remove">
                      <input type="hidden" name="block_id" value="<?php echo (int)$r['id']; ?>">
                      <button class="btn btn-sm btn-outline-danger">Remove</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php require_once '../includes/footer.php'; ?>

