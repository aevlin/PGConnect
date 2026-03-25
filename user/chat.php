<?php
require_once '../backend/connect.php';
require_once '../backend/messages_schema.php';
require_once '../backend/auth.php';
require_role('user');
require_once '../includes/header.php';

ensure_chat_schema($pdo);
$userId = (int)$_SESSION['user_id'];
$flashSuccess = $_SESSION['chat_flash_success'] ?? '';
$flashError = $_SESSION['chat_flash_error'] ?? '';
unset($_SESSION['chat_flash_success'], $_SESSION['chat_flash_error']);

$conversations = [];
$activeId = isset($_GET['c']) ? (int)$_GET['c'] : 0;
$activeConversation = null;

if ($activeId > 0) {
    try {
        $markRead = $pdo->prepare("
            UPDATE messages m
            JOIN conversations c ON c.id = m.conversation_id
            SET m.is_read = 1
            WHERE m.conversation_id = ?
              AND c.user_id = ?
              AND m.recipient_role = 'user'
              AND m.is_read = 0
        ");
        $markRead->execute([$activeId, $userId]);
    } catch (Throwable $e) {}
}

try {
    $stmt = $pdo->prepare('SELECT c.id, c.pg_id,
        COALESCE(p.pg_name, "PG") AS pg_name,
        COALESCE(u.name, "Owner") AS owner_name,
        u.phone AS owner_phone,
        u.email AS owner_email,
        (SELECT COUNT(*) FROM messages m WHERE m.conversation_id = c.id AND m.sender_role = "owner" AND m.is_read = 0) AS unread_count
        FROM conversations c
        LEFT JOIN pg_listings p ON p.id = c.pg_id
        LEFT JOIN users u ON u.id = c.owner_id
        WHERE c.user_id = ?
        ORDER BY c.created_at DESC');
    $stmt->execute([$userId]);
    $conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if ($activeId === 0 && !empty($conversations)) $activeId = (int)$conversations[0]['id'];
} catch (Throwable $e) {}

$messages = [];
if ($activeId) {
    foreach ($conversations as $conversationRow) {
        if ((int)$conversationRow['id'] === $activeId) {
            $activeConversation = $conversationRow;
            $activeConversation['unread_count'] = 0;
            break;
        }
    }
    foreach ($conversations as &$conversationRow) {
        if ((int)$conversationRow['id'] === $activeId) {
            $conversationRow['unread_count'] = 0;
            break;
        }
    }
    unset($conversationRow);
    $m = $pdo->prepare('SELECT * FROM messages WHERE conversation_id = ? ORDER BY created_at ASC');
    $m->execute([$activeId]);
    $messages = $m->fetchAll(PDO::FETCH_ASSOC);
}

function user_chat_timeline_html($booking) {
    if (!$booking) {
        return '<div class="small text-muted">No booking started from this chat yet.</div>';
    }
    $status = (string)($booking['status'] ?? '');
    $steps = [
        'Requested' => in_array($status, ['requested','owner_approved','approved','user_confirmed','payment_pending','paid','left'], true),
        'Approved' => in_array($status, ['owner_approved','approved','user_confirmed','payment_pending','paid','left'], true),
        'Payment' => in_array($status, ['payment_pending','paid','left'], true),
        'Staying' => in_array($status, ['paid','left'], true),
        'Left' => in_array($status, ['left'], true),
    ];
    $html = '<div class="small text-muted mb-2">Booking #' . (int)$booking['id'] . ' · status: <strong>' . htmlspecialchars($status) . '</strong></div><div class="d-flex gap-1 flex-wrap">';
    foreach ($steps as $name => $done) {
        $html .= $done
            ? '<span class="badge bg-success-subtle text-success border">' . htmlspecialchars($name) . '</span>'
            : '<span class="badge bg-light text-muted border">' . htmlspecialchars($name) . '</span>';
    }
    $html .= '</div>';
    return $html;
}
?>

<section class="section-shell">
  <div class="container py-4">
    <div class="row g-3">
      <div class="col-lg-4">
        <div class="card border-0 shadow-sm p-3">
          <h2 class="h6 mb-3">Chats</h2>
          <?php if ($flashSuccess !== ''): ?><div class="alert alert-success py-2"><?php echo htmlspecialchars($flashSuccess); ?></div><?php endif; ?>
          <?php if ($flashError !== ''): ?><div class="alert alert-danger py-2"><?php echo htmlspecialchars($flashError); ?></div><?php endif; ?>
          <?php if (empty($conversations)): ?>
            <p class="text-muted small mb-0">No conversations yet.</p>
          <?php else: ?>
            <div class="list-group">
              <?php foreach ($conversations as $c): ?>
                <a class="list-group-item list-group-item-action <?php echo ($activeId == $c['id']) ? 'active' : ''; ?>" href="chat.php?c=<?php echo (int)$c['id']; ?>">
                  <div class="fw-semibold"><?php echo htmlspecialchars($c['pg_name']); ?></div>
                  <div class="small d-flex justify-content-between">
                    <span>Owner: <?php echo htmlspecialchars($c['owner_name']); ?></span>
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
          <div class="d-flex justify-content-between align-items-start mb-3">
            <div>
              <h2 class="h6 mb-1">Messages</h2>
              <?php if ($activeConversation): ?>
                <div class="small text-muted">
                  <?php echo htmlspecialchars($activeConversation['pg_name']); ?> · Owner: <?php echo htmlspecialchars($activeConversation['owner_name']); ?>
                </div>
              <?php endif; ?>
            </div>
            <?php if ($activeConversation): ?>
              <div class="small text-end text-muted">
                <div><?php echo htmlspecialchars($activeConversation['owner_email'] ?? ''); ?></div>
                <div><?php echo htmlspecialchars($activeConversation['owner_phone'] ?? ''); ?></div>
              </div>
            <?php endif; ?>
          </div>
          <div id="chatBookingTimeline" class="border rounded p-2 mb-3 bg-white">
            <?php echo user_chat_timeline_html(null); ?>
          </div>
          <div id="chatMessagesBox" class="border rounded p-3 mb-3 bg-light" style="height:380px; overflow:auto;">
            <?php if (empty($messages)): ?>
              <p class="text-muted small mb-0">Select a chat to view messages.</p>
            <?php else: ?>
              <?php foreach ($messages as $msg): ?>
                <?php
                  $metadata = [];
                  if (!empty($msg['metadata_json'])) {
                      $metadata = json_decode((string)$msg['metadata_json'], true) ?: [];
                  }
                  $isMine = $msg['sender_role'] === 'user';
                ?>
                <div class="mb-2 <?php echo $isMine ? 'text-end' : 'text-start'; ?>">
                  <div class="d-inline-block p-2 rounded-3 <?php echo $isMine ? 'bg-primary text-white' : 'bg-white border'; ?>" style="max-width:82%;">
                    <div><?php echo nl2br(htmlspecialchars($msg['message'])); ?></div>
                    <?php if (($msg['action_key'] ?? '') === 'room_readiness' && !$isMine): ?>
                      <div class="mt-2 d-flex gap-2 flex-wrap justify-content-end">
                        <form method="POST" action="chat-action.php" class="m-0">
                          <input type="hidden" name="conversation_id" value="<?php echo (int)$activeId; ?>">
                          <input type="hidden" name="action" value="ready_yes">
                          <button class="btn btn-sm btn-success">Yes, I’m ready</button>
                        </form>
                        <form method="POST" action="chat-action.php" class="m-0">
                          <input type="hidden" name="conversation_id" value="<?php echo (int)$activeId; ?>">
                          <input type="hidden" name="action" value="ready_no">
                          <button class="btn btn-sm btn-outline-secondary">Not yet</button>
                        </form>
                      </div>
                    <?php endif; ?>
                    <?php if (($msg['action_key'] ?? '') === 'booking_created' && !$isMine): ?>
                      <div class="mt-2">
                        <a class="btn btn-sm btn-warning" href="<?php echo BASE_URL; ?>/user/booking-request.php">Open booking</a>
                      </div>
                    <?php endif; ?>
                    <div class="small mt-1 <?php echo $isMine ? 'text-white-50' : 'text-muted'; ?>">
                      <?php echo htmlspecialchars($msg['created_at']); ?>
                    </div>
                  </div>
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
<?php if ($activeId): ?>
<script>
(function(){
  const convId = <?php echo (int)$activeId; ?>;
  const box = document.getElementById('chatMessagesBox');
  const timeline = document.getElementById('chatBookingTimeline');
  if (!box || !timeline) return;

  function esc(str) {
    return String(str || '').replace(/[&<>"']/g, function(c){
      return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];
    });
  }

  function timelineHtml(booking) {
    if (!booking) return '<div class="small text-muted">No booking started from this chat yet.</div>';
    const status = String(booking.status || '');
    const steps = [
      ['Requested', ['requested','owner_approved','approved','user_confirmed','payment_pending','paid','left']],
      ['Approved', ['owner_approved','approved','user_confirmed','payment_pending','paid','left']],
      ['Payment', ['payment_pending','paid','left']],
      ['Staying', ['paid','left']],
      ['Left', ['left']]
    ];
    let html = '<div class="small text-muted mb-2">Booking #' + Number(booking.id) + ' · status: <strong>' + esc(status) + '</strong></div><div class="d-flex gap-1 flex-wrap">';
    steps.forEach(function(step){
      const done = step[1].includes(status);
      html += done
        ? '<span class="badge bg-success-subtle text-success border">' + esc(step[0]) + '</span>'
        : '<span class="badge bg-light text-muted border">' + esc(step[0]) + '</span>';
    });
    html += '</div>';
    return html;
  }

  function renderMessages(messages) {
    if (!Array.isArray(messages) || !messages.length) {
      box.innerHTML = '<p class="text-muted small mb-0">Select a chat to view messages.</p>';
      return;
    }
    box.innerHTML = messages.map(function(msg){
      const isMine = msg.sender_role === 'user';
      const bubbleClass = isMine ? 'bg-primary text-white' : 'bg-white border';
      const timeClass = isMine ? 'text-white-50' : 'text-muted';
      let actions = '';
      if ((msg.action_key || '') === 'room_readiness' && !isMine) {
        actions = '<div class="mt-2 d-flex gap-2 flex-wrap justify-content-end">' +
          '<form method="POST" action="chat-action.php" class="m-0">' +
          '<input type="hidden" name="conversation_id" value="' + convId + '">' +
          '<input type="hidden" name="action" value="ready_yes">' +
          '<button class="btn btn-sm btn-success">Yes, I\\'m ready</button></form>' +
          '<form method="POST" action="chat-action.php" class="m-0">' +
          '<input type="hidden" name="conversation_id" value="' + convId + '">' +
          '<input type="hidden" name="action" value="ready_no">' +
          '<button class="btn btn-sm btn-outline-secondary">Not yet</button></form></div>';
      }
      if ((msg.action_key || '') === 'booking_created' && !isMine) {
        actions += '<div class="mt-2"><a class="btn btn-sm btn-warning" href="<?php echo BASE_URL; ?>/user/booking-request.php">Open booking</a></div>';
      }
      return '<div class="mb-2 ' + (isMine ? 'text-end' : 'text-start') + '">' +
        '<div class="d-inline-block p-2 rounded-3 ' + bubbleClass + '" style="max-width:82%;">' +
        '<div>' + esc(msg.message).replace(/\\n/g, '<br>') + '</div>' +
        actions +
        '<div class="small mt-1 ' + timeClass + '">' + esc(msg.created_at) + '</div>' +
        '</div></div>';
    }).join('');
    box.scrollTop = box.scrollHeight;
  }

  function poll() {
    fetch('<?php echo BASE_URL; ?>/backend/chat_poll.php?conversation_id=' + encodeURIComponent(convId), { cache: 'no-store' })
      .then(r => r.json())
      .then(function(data){
        if (!data || !data.ok) return;
        renderMessages(data.messages || []);
        timeline.innerHTML = timelineHtml(data.booking || null);
      })
      .catch(function(){});
  }

  poll();
  setInterval(poll, 5000);
})();
</script>
<?php endif; ?>
