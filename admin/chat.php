<?php
require_once '../backend/connect.php';
require_once '../backend/messages_schema.php';
require_once '../backend/auth.php';
require_role('admin');

ensure_chat_schema($pdo);

$adminId = (int)$_SESSION['user_id'];
$q = trim((string)($_GET['q'] ?? ''));
$ownerId = isset($_GET['owner_id']) ? (int)$_GET['owner_id'] : 0;
$activeId = isset($_GET['c']) ? (int)$_GET['c'] : 0;

if ($activeId > 0) {
    try {
        $markRead = $pdo->prepare("
            UPDATE messages m
            JOIN conversations c ON c.id = m.conversation_id
            SET m.is_read_admin = 1
            WHERE m.conversation_id = ?
              AND c.admin_id = ?
              AND COALESCE(c.conversation_type, 'tenant_owner') = 'admin_owner'
              AND m.sender_role = 'owner'
              AND COALESCE(m.is_read_admin, 0) = 0
        ");
        $markRead->execute([$activeId, $adminId]);
    } catch (Throwable $e) {}
}

$owners = [];
try {
    $sql = "SELECT u.id, u.name, u.email,
            c.id AS conversation_id,
            (SELECT COUNT(*)
             FROM messages m
             WHERE m.conversation_id = c.id
               AND m.sender_role = 'owner'
               AND COALESCE(m.is_read_admin, 0) = 0) AS unread_count
            FROM users u
            LEFT JOIN conversations c
              ON c.owner_id = u.id
             AND c.admin_id = :admin_id
             AND COALESCE(c.conversation_type, 'tenant_owner') = 'admin_owner'
            WHERE u.role = 'owner'";
    $params = [':admin_id' => $adminId];
    if ($q !== '') {
        $sql .= " AND (LOWER(u.name) LIKE :q_name OR LOWER(u.email) LIKE :q_email)";
        $like = '%' . strtolower($q) . '%';
        $params[':q_name'] = $like;
        $params[':q_email'] = $like;
    }
    $sql .= " ORDER BY u.name ASC, u.id DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $owners = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $owners = [];
}

if ($ownerId > 0 && $activeId <= 0) {
    $convStmt = $pdo->prepare("SELECT id
        FROM conversations
        WHERE owner_id = ? AND admin_id = ? AND COALESCE(conversation_type, 'tenant_owner') = 'admin_owner'
        LIMIT 1");
    $convStmt->execute([$ownerId, $adminId]);
    $activeId = (int)$convStmt->fetchColumn();
    if ($activeId <= 0) {
        $ins = $pdo->prepare("INSERT INTO conversations (user_id, owner_id, admin_id, pg_id, conversation_type)
                              VALUES (0, ?, ?, NULL, 'admin_owner')");
        $ins->execute([$ownerId, $adminId]);
        $activeId = (int)$pdo->lastInsertId();
    }
}

$activeConversation = null;
if ($activeId > 0) {
    $ac = $pdo->prepare("SELECT c.id, c.owner_id,
        COALESCE(o.name, 'Owner') AS owner_name,
        COALESCE(o.email, '') AS owner_email,
        COALESCE(o.phone, '') AS owner_phone
        FROM conversations c
        LEFT JOIN users o ON o.id = c.owner_id
        WHERE c.id = ? AND c.admin_id = ? AND COALESCE(c.conversation_type, 'tenant_owner') = 'admin_owner'
        LIMIT 1");
    $ac->execute([$activeId, $adminId]);
    $activeConversation = $ac->fetch(PDO::FETCH_ASSOC) ?: null;
    if (!$activeConversation) {
        $activeId = 0;
    }
}

$messages = [];
if ($activeId > 0) {
    $m = $pdo->prepare("SELECT * FROM messages WHERE conversation_id = ? ORDER BY created_at ASC, id ASC");
    $m->execute([$activeId]);
    $messages = $m->fetchAll(PDO::FETCH_ASSOC);
    foreach ($owners as &$owner) {
        if ((int)$owner['id'] === (int)($activeConversation['owner_id'] ?? 0)) {
            $owner['unread_count'] = 0;
            break;
        }
    }
    unset($owner);
}

require_once '../includes/header.php';
?>
<section class="section-shell">
  <div class="container py-4">
    <div class="row g-3">
      <div class="col-lg-4">
        <div class="card border-0 shadow-sm p-3">
          <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="h6 mb-0">Owners</h2>
            <span class="small text-muted">Admin-only chat</span>
          </div>
          <form method="GET" class="mb-3">
            <div class="input-group">
              <input type="text" name="q" class="form-control" value="<?php echo htmlspecialchars($q); ?>" placeholder="Search owner name or email">
              <button class="btn btn-primary">Search</button>
            </div>
          </form>
          <?php if (empty($owners)): ?>
            <p class="text-muted small mb-0">No owners found.</p>
          <?php else: ?>
            <div class="list-group">
              <?php foreach ($owners as $owner): ?>
                <?php
                  $ownerLink = 'chat.php?' . http_build_query([
                    'owner_id' => (int)$owner['id'],
                    'q' => $q,
                  ]);
                  $isActive = $activeConversation && (int)$activeConversation['owner_id'] === (int)$owner['id'];
                ?>
                <a class="list-group-item list-group-item-action <?php echo $isActive ? 'active' : ''; ?>" href="<?php echo htmlspecialchars($ownerLink); ?>">
                  <div class="fw-semibold"><?php echo htmlspecialchars($owner['name'] ?: 'Owner'); ?></div>
                  <div class="small d-flex justify-content-between">
                    <span><?php echo htmlspecialchars($owner['email'] ?: ''); ?></span>
                    <?php if (!empty($owner['unread_count'])): ?>
                      <span class="badge bg-danger"><?php echo (int)$owner['unread_count']; ?></span>
                    <?php endif; ?>
                  </div>
                </a>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>
      <div class="col-lg-8">
        <div class="card border-0 shadow-sm p-3 h-100">
          <div class="d-flex justify-content-between align-items-start mb-3">
            <div>
              <h2 class="h6 mb-1">Owner Messages</h2>
              <?php if ($activeConversation): ?>
                <div class="small text-muted">Owner: <?php echo htmlspecialchars($activeConversation['owner_name']); ?></div>
              <?php endif; ?>
            </div>
            <?php if ($activeConversation): ?>
              <div class="small text-end text-muted">
                <div><?php echo htmlspecialchars($activeConversation['owner_email']); ?></div>
                <div><?php echo htmlspecialchars($activeConversation['owner_phone']); ?></div>
              </div>
            <?php endif; ?>
          </div>

          <div class="alert alert-light border small">
            Tenant-owner chats are private. Admin can only message owners here.
          </div>

          <div class="border rounded p-3 mb-3" style="height:360px; overflow:auto;">
            <?php if (empty($messages)): ?>
              <p class="text-muted small mb-0">Select an owner to start or continue chat.</p>
            <?php else: ?>
              <?php foreach ($messages as $msg): ?>
                <div class="mb-2 <?php echo $msg['sender_role'] === 'admin' ? 'text-end' : 'text-start'; ?>">
                  <span class="badge <?php echo $msg['sender_role'] === 'admin' ? 'bg-primary' : 'bg-light text-dark'; ?>">
                    <?php echo htmlspecialchars(strtoupper($msg['sender_role']) . ': ' . $msg['message']); ?>
                  </span>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>

          <?php if ($activeId > 0): ?>
            <form method="POST" action="send-chat.php" class="row g-2">
              <input type="hidden" name="conversation_id" value="<?php echo (int)$activeId; ?>">
              <div class="col-md-10">
                <input type="text" name="message" class="form-control" placeholder="Message owner">
              </div>
              <div class="col-md-2">
                <button class="btn btn-primary w-100">Send</button>
              </div>
            </form>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</section>
<?php require_once '../includes/footer.php'; ?>
