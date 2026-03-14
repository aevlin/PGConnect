<?php
require_once '../includes/header.php';
require_once '../backend/connect.php';
require_once '../backend/config.php';
require_once '../backend/messages_schema.php';
require_once '../backend/user_schema.php';
require_once '../backend/reviews_schema.php';
require_once '../backend/feature_schema.php';
require_once '../backend/trust.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) {
    die('No PG ID provided');
}

$statusWhere = (defined('SHOW_PENDING_LISTINGS') && SHOW_PENDING_LISTINGS) ? "IN ('approved','pending')" : "= 'approved'";
$stmt = $pdo->prepare(
    "SELECT p.*, 
            (SELECT image_path FROM pg_images WHERE pg_id = p.id ORDER BY id LIMIT 1) AS cover_image
     FROM pg_listings p
     WHERE p.id = ? AND p.status $statusWhere"
);
$stmt->execute([$id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    die('PG not found');
}

$statusLabel = ucfirst($row['status'] ?? 'pending');
$statusClass = ($row['status'] ?? '') === 'approved' ? 'bg-success' : 'bg-warning text-dark';

ensure_user_profile_schema($pdo);
ensure_chat_schema($pdo);
ensure_reviews_schema($pdo);
ensure_feature_schema($pdo);

// rating
$avgRating = null;
$ratingCount = 0;
try {
    $rs = $pdo->prepare('SELECT AVG(rating) as avg_r, COUNT(*) as cnt FROM reviews WHERE pg_id = ?');
    $rs->execute([$id]);
    $r = $rs->fetch(PDO::FETCH_ASSOC);
    $avgRating = $r && $r['avg_r'] ? round((float)$r['avg_r'], 1) : null;
    $ratingCount = $r ? (int)$r['cnt'] : 0;
} catch (Throwable $e) {}
if ($avgRating === null) $avgRating = pg_fallback_rating($id);
$trustScore = pg_trust_score($pdo, $id);

// owner contact
$ownerInfo = null;
try {
    $os = $pdo->prepare('SELECT u.id, u.name, u.email, u.phone FROM users u JOIN pg_listings p ON p.owner_id = u.id WHERE p.id = ? LIMIT 1');
    $os->execute([$id]);
    $ownerInfo = $os->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) { $ownerInfo = null; }

$isLoggedIn = isset($_SESSION['user_id']);
$isTenant = $isLoggedIn && (($_SESSION['user_role'] ?? '') === 'user');
$coordsAvailable = is_numeric($row['latitude'] ?? null) && is_numeric($row['longitude'] ?? null);

$userBookingStatus = '';
$userBookingMessage = '';
$bookingBlocked = false;
if ($isTenant) {
    try {
        require_once '../backend/booking_schema.php';
        ensure_bookings_schema($pdo);
        $bst = $pdo->prepare('SELECT status, moved_out_at FROM bookings WHERE user_id = ? AND pg_id = ? ORDER BY created_at DESC LIMIT 1');
        $bst->execute([$_SESSION['user_id'], $id]);
        $lastBooking = $bst->fetch(PDO::FETCH_ASSOC);
        if ($lastBooking) {
            $userBookingStatus = (string)($lastBooking['status'] ?? '');
            $movedOutAt = $lastBooking['moved_out_at'] ?? null;
            if ($userBookingStatus === 'paid' && empty($movedOutAt)) {
                $bookingBlocked = true;
                $userBookingMessage = 'You are already staying in this PG.';
            } elseif (in_array($userBookingStatus, ['requested', 'owner_approved', 'approved', 'user_confirmed', 'payment_pending'], true)) {
                $bookingBlocked = true;
                $userBookingMessage = 'You already booked this PG. Please manage your existing request.';
            }
        }
    } catch (Throwable $e) {
        $userBookingStatus = '';
        $userBookingMessage = '';
        $bookingBlocked = false;
    }
}
?>

<section class="section-shell">
  <div class="container py-5">
    <?php if (isset($_GET['review_prompt']) && $_GET['review_prompt'] == '1'): ?>
      <div class="alert alert-success mb-4">
        Stay marked as completed. Please add your review below.
      </div>
    <?php endif; ?>
    <?php if (!isset($_SESSION['user_id'])): ?>
      <div class="alert alert-warning mb-4">
        Please <a href="<?php echo BASE_URL; ?>/backend/login.php">login</a> or
        <a href="<?php echo BASE_URL; ?>/backend/signup.php">sign up</a> to contact owners or make booking requests.
      </div>
    <?php elseif (!$isTenant): ?>
      <div class="alert alert-info mb-4">
        Booking is available only for tenant user accounts.
      </div>
    <?php endif; ?>
    <a href="pg-listings.php" class="btn btn-outline-secondary mb-4">← Back to listings</a>

    <div class="row g-4">
        <!-- Main content -->
        <div class="col-lg-8">
            <div class="card shadow-sm border-0">
                <?php $fallbackImg = pg_fallback_image((int)$row['id']); ?>
                <img src="<?php echo htmlspecialchars($row['cover_image'] ? pg_image_url($row['cover_image'], $fallbackImg) : $fallbackImg); ?>"
                     class="card-img-top"
                     style="height: 400px; object-fit: cover;"
                     alt="PG photo">
                <div class="card-body">
                    <h1 class="h3 mb-3"><?php echo htmlspecialchars($row['pg_name']); ?></h1>
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <p class="mb-1"><strong>Location:</strong></p>
                            <p class="text-muted mb-0"><?php echo htmlspecialchars($row['address']); ?>, <?php echo htmlspecialchars($row['location_area']); ?>, <?php echo htmlspecialchars($row['city']); ?>, <?php echo htmlspecialchars($row['district']); ?>, <?php echo htmlspecialchars($row['state']); ?></p>
                            <p class="small text-muted mt-1">PG Code: <?php echo htmlspecialchars($row['pg_code']); ?></p>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-2"><span class="rating-badge">★ <?php echo htmlspecialchars($avgRating); ?></span> <span class="small text-muted">(<?php echo (int)$ratingCount; ?> ratings)</span></div>
                            <div class="mb-2"><span class="badge bg-info text-dark">Trust score: <?php echo (int)$trustScore; ?>/100</span></div>
                            <h2 class="h4 mb-1">₹<?php echo number_format($row['monthly_rent'], 0); ?>/month</h2>
                            <p class="mb-0">
                                <span class="badge bg-success"><?php echo htmlspecialchars($row['capacity']); ?> beds total</span>
                                <span class="badge bg-warning text-dark ms-2"><?php echo htmlspecialchars($row['available_beds']); ?> available</span>
                                <span class="badge bg-light text-dark ms-2"><?php echo htmlspecialchars(ucfirst($row['occupancy_type'])); ?></span>
                            </p>
                            <p class="mt-2"><strong>Status:</strong> <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $row['occupancy_status']))); ?></p>
                        </div>
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <h5>Features</h5>
                            <ul class="list-unstyled">
                                <?php
                                $features = [];
                                if (!empty($row['amenities'])) {
                                    $parts = array_filter(array_map('trim', explode(',', $row['amenities'])));
                                    foreach ($parts as $p) echo "<li>• " . htmlspecialchars($p) . "</li>";
                                } else {
                                    echo "<li>• WiFi</li><li>• 24/7 Water</li><li>• Power Backup</li><li>• Security</li><li>• Parking</li>";
                                }
                                ?>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h5>About</h5>
                            <p class="text-muted small">
                                Clean, comfortable PG with good amenities near IT hubs. 
                                Well maintained with regular cleaning and friendly staff.
                            </p>
                        </div>
                    </div>

                    <div class="card border-0 bg-light mb-3">
                        <div class="card-body">
                            <h6 class="mb-2">Map Location</h6>
                            <?php if ($coordsAvailable): ?>
                                <div id="pgDetailMap" style="height:260px;border-radius:12px;overflow:hidden;"></div>
                                <div class="small text-muted mt-2">
                                    Lat: <?php echo htmlspecialchars((string)$row['latitude']); ?>,
                                    Lng: <?php echo htmlspecialchars((string)$row['longitude']); ?>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-info mb-0">Location coordinates are not available for this PG.</div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="d-flex gap-3">
                        <button id="bookNowBtn" class="btn btn-success flex-grow-1"
                                <?php echo $isTenant && !$bookingBlocked ? '' : 'disabled'; ?>>
                            <?php echo $bookingBlocked ? 'Already Booked' : 'Book Now'; ?>
                        </button>
                        <button id="contactOwnerBtn" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#contactOwnerModal" <?php echo $isLoggedIn ? '' : 'disabled'; ?>>Contact Owner</button>
                        <button id="chatOwnerBtn" class="btn btn-primary" <?php echo $isLoggedIn ? '' : 'disabled'; ?>>Chat Owner</button>
                    </div>
                    <?php
                      $blockedDates = [];
                      try {
                        $bd = $pdo->prepare('SELECT block_date FROM availability_blocks WHERE pg_id = ? ORDER BY block_date ASC LIMIT 90');
                        $bd->execute([$id]);
                        $blockedDates = $bd->fetchAll(PDO::FETCH_COLUMN);
                      } catch (Throwable $e) {}
                    ?>
                    <?php if (!empty($blockedDates)): ?>
                      <div class="alert alert-warning mt-3 mb-0">
                        <strong>Unavailable dates:</strong>
                        <span class="small"><?php echo htmlspecialchars(implode(', ', array_slice($blockedDates, 0, 12))); ?><?php echo count($blockedDates) > 12 ? ' ...' : ''; ?></span>
                      </div>
                    <?php endif; ?>
                    <?php
                    // user booking status and duplicate-booking message
                    ?>
                    <?php if (!empty($userBookingStatus) || !empty($userBookingMessage)): ?>
                        <div class="alert alert-info mt-3 mb-0">
                            <?php if (!empty($userBookingMessage)): ?>
                                <?php echo htmlspecialchars($userBookingMessage); ?><br>
                            <?php endif; ?>
                            <?php if (!empty($userBookingStatus)): ?>
                                Your booking status: <strong><?php echo htmlspecialchars($userBookingStatus); ?></strong>.
                            <?php endif; ?>
                            <a href="booking-request.php">Manage</a>
                        </div>
                    <?php endif; ?>

                    <div class="mt-4">
                        <h5>Ratings & Reviews</h5>
                        <?php if (isset($_SESSION['user_id'])): ?>
                          <form id="reviewForm" class="mb-3">
                            <input type="hidden" name="pg_id" value="<?php echo (int)$id; ?>">
                            <div class="row g-2">
                              <div class="col-md-3">
                                <select name="rating" class="form-select" required>
                                  <option value="">Rating</option>
                                  <option value="5">5</option>
                                  <option value="4">4</option>
                                  <option value="3">3</option>
                                  <option value="2">2</option>
                                  <option value="1">1</option>
                                </select>
                              </div>
                              <div class="col-md-9">
                                <input type="text" name="comment" class="form-control" placeholder="Write a quick review (optional)">
                              </div>
                            </div>
                            <button class="btn btn-sm btn-outline-primary mt-2">Submit</button>
                            <div id="reviewAlert" class="small text-muted mt-1"></div>
                          </form>
                        <?php endif; ?>
                        <?php
                          $revRows = [];
                          try {
                              $rv = $pdo->prepare('SELECT r.rating, r.comment, r.owner_response, r.created_at, u.name FROM reviews r JOIN users u ON u.id = r.user_id WHERE r.pg_id = ? AND COALESCE(r.is_hidden,0) = 0 ORDER BY r.created_at DESC LIMIT 5');
                              $rv->execute([$id]);
                              $revRows = $rv->fetchAll(PDO::FETCH_ASSOC);
                          } catch (Throwable $e) {}
                        ?>
                        <?php if (empty($revRows)): ?>
                          <p class="text-muted small">No reviews yet.</p>
                        <?php else: ?>
                          <?php foreach ($revRows as $rv): ?>
                            <div class="border-bottom py-2">
                              <div class="small"><strong><?php echo htmlspecialchars($rv['name']); ?></strong> · ★ <?php echo (int)$rv['rating']; ?></div>
                              <div class="small text-muted"><?php echo htmlspecialchars($rv['comment'] ?? ''); ?></div>
                              <?php if (!empty($rv['owner_response'])): ?>
                                <div class="small mt-1"><span class="badge bg-light text-dark border">Owner reply</span> <?php echo htmlspecialchars($rv['owner_response']); ?></div>
                              <?php endif; ?>
                            </div>
                          <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="col-lg-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <h5>Quick Info</h5>
                    <ul class="list-unstyled mb-4">
                        <li><strong>Rent:</strong> ₹<?php echo number_format($row['monthly_rent'], 0); ?>/month</li>
                        <li><strong>Beds:</strong> <?php echo htmlspecialchars($row['capacity']); ?></li>
                        <li><strong>Sharing:</strong> <?php echo htmlspecialchars(ucfirst($row['sharing_type'])); ?></li>
                        <li><strong>Status:</strong> <span class="badge <?php echo $statusClass; ?>"><?php echo htmlspecialchars($statusLabel); ?></span></li>
                    </ul>

                    <h5 class="mb-3">Gallery</h5>
                    <div class="row g-2">
                        <?php
                        // fetch up to 6 images for gallery
                        $gstmt = $pdo->prepare('SELECT image_path FROM pg_images WHERE pg_id = ? ORDER BY id LIMIT 6');
                        $gstmt->execute([$id]);
                        $gimgs = $gstmt->fetchAll(PDO::FETCH_COLUMN);
                        if (empty($gimgs)) {
                            // fallback to cover_image or placeholder
                            $fallback = $row['cover_image'] ?: pg_fallback_image((int)$row['id']);
                            $gimgs = [$fallback];
                        }
                        foreach ($gimgs as $gimg) {
                            $gimg = pg_image_url($gimg);
                        ?>
                        <div class="col-4">
                            <img src="<?php echo htmlspecialchars($gimg); ?>" class="img-fluid rounded" alt="PG photo">
                        </div>
                        <?php } ?>
                    <div class="mt-3">
                        <?php $isFav = 0; if (isset($_SESSION['user_id'])) {
                            try {
                                $f = $pdo->prepare('SELECT id FROM favorites WHERE user_id = ? AND pg_id = ? LIMIT 1');
                                $f->execute([$_SESSION['user_id'], $id]);
                                $isFav = (bool)$f->fetchColumn();
                            } catch (Exception $e) {
                                $isFav = 0;
                            }
                        }
                        ?>
                        <button class="btn btn-outline-primary fav-btn" data-pg="<?php echo $id; ?>"><?php echo $isFav ? 'Unsave' : 'Save'; ?></button>
                    </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
  </div>
</section>

<?php require_once '../includes/footer.php'; ?>

<?php if ($coordsAvailable): ?>
<script>
(function(){
  const mapEl = document.getElementById('pgDetailMap');
  if (!mapEl || typeof L === 'undefined') return;
  const lat = <?php echo json_encode((float)$row['latitude']); ?>;
  const lng = <?php echo json_encode((float)$row['longitude']); ?>;
  const map = L.map(mapEl).setView([lat, lng], 15);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '© OpenStreetMap contributors'
  }).addTo(map);
  L.marker([lat, lng]).addTo(map).bindPopup('<?php echo addslashes(htmlspecialchars($row['pg_name'])); ?>').openPopup();
})();
</script>
<?php endif; ?>

<!-- Contact Owner Modal -->
<div class="modal fade" id="contactOwnerModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Contact Owner</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <?php if ($ownerInfo): ?>
          <p class="mb-1"><strong>Name:</strong> <?php echo htmlspecialchars($ownerInfo['name']); ?></p>
          <p class="mb-1"><strong>Email:</strong> <?php echo htmlspecialchars($ownerInfo['email']); ?></p>
          <p class="mb-3"><strong>Phone:</strong> <?php echo htmlspecialchars($ownerInfo['phone'] ?? ''); ?></p>
          <div class="mb-2">
            <label class="form-label">Message</label>
            <textarea id="ownerMessage" class="form-control" rows="3" placeholder="Ask about availability, facilities, etc."></textarea>
          </div>
          <div id="ownerMessageAlert" class="small text-muted"></div>
        <?php else: ?>
          <div class="alert alert-info mb-0">Owner contact not available.</div>
        <?php endif; ?>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        <?php if ($ownerInfo): ?>
          <button id="sendOwnerMessage" type="button" class="btn btn-primary">Send message</button>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- Booking Modal -->
<div class="modal fade" id="bookingModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Request Booking</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="bookingForm">
                    <input type="hidden" name="pg_id" value="<?php echo (int)$id; ?>">
                    <div class="mb-3">
                        <label class="form-label">Your name</label>
                        <input type="text" name="contact_name" class="form-control" value="<?php echo htmlspecialchars($_SESSION['user_name'] ?? ''); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Phone</label>
                        <input type="text" name="contact_phone" class="form-control" value="" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Join date</label>
                        <input type="date" name="move_in_date" id="moveInDateInput" class="form-control" required>
                    </div>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" value="1" id="visitRequested" name="visit_requested">
                        <label class="form-check-label" for="visitRequested">
                            Request a property visit appointment first
                        </label>
                    </div>
                    <div id="visitFields" style="display:none;">
                        <div class="mb-3">
                            <label class="form-label">Preferred visit date & time</label>
                            <input type="datetime-local" name="visit_datetime" id="visitDatetime" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Visit note (optional)</label>
                            <input type="text" name="visit_note" class="form-control" placeholder="Preferred time window or instructions">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Message (optional)</label>
                        <textarea name="message" class="form-control" rows="3" placeholder="Any specific requirements or move-in date"></textarea>
                    </div>
                </form>
                <div id="bookingAlert" class="mt-2"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button id="submitBooking" type="button" class="btn btn-primary">Send request</button>
            </div>
        </div>
    </div>
</div>

<script>
(function(){
    function initBooking(){
        const bookBtn = document.getElementById('bookNowBtn');
        const bookingModalEl = document.getElementById('bookingModal');
        const bookingModal = bookingModalEl ? new bootstrap.Modal(bookingModalEl) : null;
        const submitBtn = document.getElementById('submitBooking');
        const bookingForm = document.getElementById('bookingForm');
        const bookingAlert = document.getElementById('bookingAlert');
        const moveInDateInput = document.getElementById('moveInDateInput');
        const blockedDates = <?php echo json_encode(array_values($blockedDates ?? [])); ?>;
        const visitRequested = document.getElementById('visitRequested');
        const visitFields = document.getElementById('visitFields');
        const visitDatetime = document.getElementById('visitDatetime');

        if (!bookBtn) return;

        if (visitRequested && visitFields && visitDatetime) {
            visitRequested.addEventListener('change', function(){
                const show = !!visitRequested.checked;
                visitFields.style.display = show ? 'block' : 'none';
                visitDatetime.required = show;
            });
        }

        if (moveInDateInput && Array.isArray(blockedDates) && blockedDates.length) {
            moveInDateInput.addEventListener('change', function(){
                if (blockedDates.includes(moveInDateInput.value)) {
                    if (bookingAlert) bookingAlert.innerHTML = '<div class="alert alert-warning">Selected join date is unavailable. Please choose another date.</div>';
                    moveInDateInput.value = '';
                }
            });
        }

        bookBtn.addEventListener('click', function(){
            // if not logged in, redirect to login
            <?php if (!isset($_SESSION['user_id'])): ?>
                window.location.href = '<?php echo BASE_URL; ?>/backend/login.php';
                return;
            <?php else: ?>
                if (bookingAlert) bookingAlert.innerHTML = '';
                if (bookingModal) bookingModal.show();
            <?php endif; ?>
        });

        if (submitBtn) {
            submitBtn.addEventListener('click', function(){
                submitBtn.disabled = true;
                if (bookingAlert) bookingAlert.innerHTML = '<div class="text-muted">Sending request...</div>';
                const data = new FormData(bookingForm);
                fetch('<?php echo BASE_URL; ?>/backend/create_booking.php', { method: 'POST', body: data })
                    .then(async r => {
                        const text = await r.text();
                        let json = null;
                        try { json = JSON.parse(text); } catch (e) {}
                        return { okHttp: r.ok, json, text };
                    })
                    .then(j => {
                        if (j.json && j.json.ok) {
                            if (bookingAlert) bookingAlert.innerHTML = '<div class="alert alert-success">Booking request sent. Owner will be notified.</div>';
                            setTimeout(() => { if (bookingModal) bookingModal.hide(); }, 1200);
                        } else {
                            const msg = (j.json && j.json.message) ? j.json.message : (!j.okHttp ? ('HTTP ' + j.text) : 'Please check all required fields.');
                            if (bookingAlert) bookingAlert.innerHTML = '<div class="alert alert-danger">Failed to send request. ' + msg + '</div>';
                        }
                    }).catch(err => {
                        if (bookingAlert) bookingAlert.innerHTML = '<div class="alert alert-danger">Error sending request: ' + (err && err.message ? err.message : '') + '</div>';
                    }).finally(()=>{ submitBtn.disabled = false; });
            });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initBooking);
    } else {
        initBooking();
    }
})();
</script>

<script>
(function(){
  const form = document.getElementById('reviewForm');
  if (!form) return;
  form.addEventListener('submit', function(e){
    e.preventDefault();
    const data = new FormData(form);
    fetch('<?php echo BASE_URL; ?>/backend/submit_review.php', {
      method: 'POST',
      body: new URLSearchParams(data)
    }).then(r => r.json()).then(j => {
      const el = document.getElementById('reviewAlert');
      if (j && j.ok) {
        el.innerText = 'Review submitted. Refresh to see it.';
      } else {
        el.innerText = 'Failed to submit review.';
      }
    }).catch(()=> {
      const el = document.getElementById('reviewAlert');
      if (el) el.innerText = 'Failed to submit review.';
    });
  });
})();
</script>

<script>
(function(){
  const chatBtn = document.getElementById('chatOwnerBtn');
  if (!chatBtn) return;
  chatBtn.addEventListener('click', function(){
    <?php if (!isset($_SESSION['user_id'])): ?>
      window.location.href = '<?php echo BASE_URL; ?>/backend/login.php';
      return;
    <?php endif; ?>

    chatBtn.disabled = true;
    const body = 'pg_id=' + encodeURIComponent('<?php echo (int)$id; ?>');
    fetch('<?php echo BASE_URL; ?>/backend/open_chat.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body
    }).then(r => r.json()).then(j => {
      if (j && j.ok && j.redirect) {
        window.location.href = j.redirect;
      } else if (j && j.error === 'auth_required') {
        window.location.href = '<?php echo BASE_URL; ?>/backend/login.php';
      } else {
        alert('Unable to open chat right now.');
      }
    }).catch(() => {
      alert('Unable to open chat right now.');
    }).finally(() => { chatBtn.disabled = false; });
  });
})();
</script>

<script>
(function(){
  const sendBtn = document.getElementById('sendOwnerMessage');
  if (!sendBtn) return;
  sendBtn.addEventListener('click', function(){
    const msg = document.getElementById('ownerMessage').value.trim();
    if (!msg) {
      document.getElementById('ownerMessageAlert').innerText = 'Please enter a message.';
      return;
    }
    sendBtn.disabled = true;
    fetch('<?php echo BASE_URL; ?>/backend/send_message.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'pg_id=<?php echo (int)$id; ?>&message=' + encodeURIComponent(msg)
    }).then(r => r.json()).then(j => {
      document.getElementById('ownerMessageAlert').innerText = j.ok ? 'Message sent.' : 'Failed to send message.';
    }).catch(()=> {
      document.getElementById('ownerMessageAlert').innerText = 'Failed to send message.';
    }).finally(()=>{ sendBtn.disabled = false; });
  });
})();
</script>
