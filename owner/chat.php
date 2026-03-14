<?php
require_once '../backend/connect.php';
require_once '../backend/messages_schema.php';
require_once '../backend/auth.php';
require_role('owner');
require_once '../includes/header.php';

ensure_chat_schema($pdo);
$ownerId = (int)$_SESSION['user_id'];

$conversations = [];
$activeId = isset($_GET['c']) ? (int)$_GET['c'] : 0;
try {
    $stmt = $pdo->prepare('SELECT c.id,
        COALESCE(p.pg_name, "PG") AS pg_name,
        COALESCE(u.name, "User") AS user_name,
        (SELECT COUNT(*) FROM messages m WHERE m.conversation_id = c.id AND m.sender_role = "user" AND m.is_read = 0) AS unread_count
        FROM conversations c
        LEFT JOIN pg_listings p ON p.id = c.pg_id
        LEFT JOIN users u ON u.id = c.user_id
        WHERE c.owner_id = ?
        ORDER BY c.created_at DESC');
    $stmt->execute([$ownerId]);
    $conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if ($activeId === 0 && !empty($conversations)) $activeId = (int)$conversations[0]['id'];
} catch (Throwable $e) {}

$messages = [];
if ($activeId) {
    $m = $pdo->prepare('SELECT * FROM messages WHERE conversation_id = ? ORDER BY created_at ASC');
    $m->execute([$activeId]);
    $messages = $m->fetchAll(PDO::FETCH_ASSOC);
    // mark messages as read for owner
    $pdo->prepare("UPDATE messages SET is_read = 1 WHERE conversation_id = ? AND recipient_role = 'owner'")->execute([$activeId]);
}
?>

<section class="section-shell">
  <div class="container py-4">
    <div class="row g-3">
      <div class="col-lg-4">
        <div class="card border-0 shadow-sm p-3">
          <h2 class="h6 mb-3">Chats</h2>
          <?php if (empty($conversations)): ?>
            <p class="text-muted small mb-0">No conversations yet.</p>
          <?php else: ?>
            <div class="list-group">
              <?php foreach ($conversations as $c): ?>
                <a class="list-group-item list-group-item-action <?php echo ($activeId == $c['id']) ? 'active' : ''; ?>" href="chat.php?c=<?php echo (int)$c['id']; ?>">
                  <div class="fw-semibold"><?php echo htmlspecialchars($c['pg_name']); ?></div>
                  <div class="small d-flex justify-content-between">
                    <span>User: <?php echo htmlspecialchars($c['user_name']); ?></span>
                    <?php if (!empty($c['unread_count'])): ?>
                      <span class="badge bg-danger"><?php echo (int)$c['unread_count']; ?></span>
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
          <h2 class="h6 mb-3">Messages</h2>
          <div class="border rounded p-3 mb-3" style="height:320px; overflow:auto;">
            <?php if (empty($messages)): ?>
              <p class="text-muted small mb-0">Select a chat to view messages.</p>
            <?php else: ?>
              <?php foreach ($messages as $msg): ?>
                <div class="mb-2 <?php echo $msg['sender_role'] === 'owner' ? 'text-end' : 'text-start'; ?>">
                  <span class="badge bg-light text-dark"><?php echo htmlspecialchars($msg['message']); ?></span>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
          <?php if ($activeId): ?>
          <form method="POST" action="send-chat.php">
            <input type="hidden" name="conversation_id" value="<?php echo (int)$activeId; ?>">
            <div class="input-group">
              <input type="text" name="message" class="form-control" placeholder="Type a message">
              <button class="btn btn-primary">Send</button>
            </div>
          </form>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</section>

<?php require_once '../includes/footer.php'; ?>
