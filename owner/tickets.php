<?php
require_once '../backend/auth.php';
require_role('owner');
require_once '../backend/connect.php';
require_once '../backend/feature_schema.php';
require_once '../backend/notify.php';
ensure_feature_schema($pdo);
require_once '../includes/header.php';

$ownerId = (int)$_SESSION['user_id'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['ticket_id'] ?? 0);
    $status = trim($_POST['status'] ?? '');
    $note = trim($_POST['owner_note'] ?? '');
    if ($id > 0 && in_array($status, ['open','in_progress','resolved','closed'], true)) {
        $u = $pdo->prepare('UPDATE service_tickets SET status = ?, owner_note = ?, updated_at = NOW() WHERE id = ? AND owner_id = ?');
        $u->execute([$status, $note, $id, $ownerId]);
        $s = $pdo->prepare('SELECT user_id FROM service_tickets WHERE id = ? LIMIT 1');
        $s->execute([$id]);
        $uid = (int)$s->fetchColumn();
        if ($uid > 0) notify_user($pdo, $uid, 'user', 'Ticket updated', 'Owner updated your service ticket.', '/PGConnect/user/tickets.php');
    }
    header('Location: tickets.php');
    exit;
}

$stmt = $pdo->prepare('SELECT t.*, p.pg_name, u.name as user_name FROM service_tickets t JOIN pg_listings p ON p.id=t.pg_id JOIN users u ON u.id=t.user_id WHERE t.owner_id = ? ORDER BY t.updated_at DESC');
$stmt->execute([$ownerId]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<div class="container py-4">
  <h1 class="h5 mb-3">Service Tickets</h1>
  <?php if (empty($rows)): ?>
    <div class="alert alert-info">No tickets for your PGs.</div>
  <?php else: ?>
    <?php foreach ($rows as $r): ?>
      <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
          <div class="d-flex justify-content-between">
            <div>
              <div class="fw-semibold"><?php echo htmlspecialchars($r['title']); ?> <span class="small text-muted">(<?php echo htmlspecialchars($r['category']); ?>)</span></div>
              <div class="small text-muted">PG: <?php echo htmlspecialchars($r['pg_name']); ?> · User: <?php echo htmlspecialchars($r['user_name']); ?></div>
              <div class="small mt-1"><?php echo htmlspecialchars($r['description'] ?? ''); ?></div>
            </div>
            <span class="badge bg-light text-dark border"><?php echo htmlspecialchars($r['status']); ?></span>
          </div>
          <form method="POST" class="row g-2 mt-2">
            <input type="hidden" name="ticket_id" value="<?php echo (int)$r['id']; ?>">
            <div class="col-md-3">
              <select name="status" class="form-select form-select-sm">
                <?php foreach (['open','in_progress','resolved','closed'] as $s): ?>
                  <option value="<?php echo $s; ?>" <?php echo $r['status'] === $s ? 'selected' : ''; ?>><?php echo $s; ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-7">
              <input type="text" name="owner_note" class="form-control form-control-sm" value="<?php echo htmlspecialchars($r['owner_note'] ?? ''); ?>" placeholder="Owner note/update">
            </div>
            <div class="col-md-2">
              <button class="btn btn-sm btn-primary w-100">Update</button>
            </div>
          </form>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>
<?php require_once '../includes/footer.php'; ?>

