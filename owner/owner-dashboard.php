<?php
$page_title = 'Owner Dashboard';
require_once '../backend/connect.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if (!defined('BASE_URL')) define('BASE_URL', '/PGConnect');
if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'owner') {
    header('Location: ' . BASE_URL . '/backend/login.php');
    exit;
}

$ownerId = (int)($_SESSION['user_id'] ?? 0);
$q = trim((string)($_GET['q'] ?? ''));
$page = isset($_GET['page']) && (int)$_GET['page'] > 0 ? (int)$_GET['page'] : 1;
$perPage = 10;
require_once '../backend/user_schema.php';
ensure_user_profile_schema($pdo);

$todayAppointments = [];
$upcomingAppointments = [];
try {
    $apptSql = "SELECT b.id, b.visit_datetime, b.visit_status, b.contact_phone,
                       p.pg_name, u.name AS user_name
                FROM bookings b
                JOIN pg_listings p ON p.id = b.pg_id
                JOIN users u ON u.id = b.user_id
                WHERE p.owner_id = ?
                  AND b.visit_requested = 1
                  AND b.visit_datetime IS NOT NULL
                  AND COALESCE(b.visit_status, 'requested') IN ('requested', 'accepted', 'rescheduled')
                ORDER BY b.visit_datetime ASC";
    $apptStmt = $pdo->prepare($apptSql);
    $apptStmt->execute([$ownerId]);
    $allAppointments = $apptStmt->fetchAll(PDO::FETCH_ASSOC);
    $todayStart = strtotime(date('Y-m-d 00:00:00'));
    $tomorrowStart = strtotime(date('Y-m-d 00:00:00', strtotime('+1 day')));
    foreach ($allAppointments as $appt) {
        $visitTs = !empty($appt['visit_datetime']) ? strtotime((string)$appt['visit_datetime']) : false;
        if ($visitTs === false) continue;
        if ($visitTs >= $todayStart && $visitTs < $tomorrowStart) {
            $todayAppointments[] = $appt;
        } elseif ($visitTs >= $tomorrowStart) {
            $upcomingAppointments[] = $appt;
        }
    }
    $upcomingAppointments = array_slice($upcomingAppointments, 0, 6);
} catch (Throwable $e) {
    $todayAppointments = [];
    $upcomingAppointments = [];
}

require_once '../includes/header.php';
// owner verification status
$vstmt = $pdo->prepare('SELECT owner_verification_status FROM users WHERE id = ?');
$vstmt->execute([$ownerId]);
$verificationStatus = $vstmt->fetchColumn() ?: 'pending';
?>

<div class="container py-5">
    <div class="row">
        <div class="col-md-12">
            <h1 class="mb-4">Owner Dashboard</h1>
            <p>Welcome <?php echo htmlspecialchars($_SESSION['user_name'] ?? ''); ?>!</p>
            <?php if (!in_array(strtolower((string)$verificationStatus), ['verified', 'approved'], true)): ?>
              <div class="alert alert-warning">Your owner verification is <strong><?php echo htmlspecialchars($verificationStatus); ?></strong>. Upload documents in <a href="owner-profile.php">My Profile</a>.</div>
            <?php endif; ?>

            <div class="row g-3 mb-4">
                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h2 class="h5 mb-0">Today's appointments</h2>
                                <span class="badge bg-primary"><?php echo count($todayAppointments); ?></span>
                            </div>
                            <?php if (empty($todayAppointments)): ?>
                                <p class="small text-muted mb-0">No visits scheduled for today.</p>
                            <?php else: ?>
                                <div class="list-group list-group-flush">
                                    <?php foreach ($todayAppointments as $appt): ?>
                                        <div class="list-group-item px-0">
                                            <div class="fw-semibold"><?php echo htmlspecialchars($appt['pg_name']); ?></div>
                                            <div class="small text-muted">
                                                <?php echo htmlspecialchars($appt['user_name']); ?>
                                                <?php if (!empty($appt['contact_phone'])): ?> · <?php echo htmlspecialchars($appt['contact_phone']); ?><?php endif; ?>
                                            </div>
                                            <div class="small mt-1">
                                                <?php echo htmlspecialchars(date('d M Y, h:i A', strtotime((string)$appt['visit_datetime']))); ?>
                                                <span class="badge bg-light text-dark border ms-2"><?php echo htmlspecialchars(ucfirst((string)$appt['visit_status'])); ?></span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h2 class="h5 mb-0">Upcoming appointments</h2>
                                <span class="badge bg-warning text-dark"><?php echo count($upcomingAppointments); ?></span>
                            </div>
                            <?php if (empty($upcomingAppointments)): ?>
                                <p class="small text-muted mb-0">No upcoming visits scheduled.</p>
                            <?php else: ?>
                                <div class="list-group list-group-flush">
                                    <?php foreach ($upcomingAppointments as $appt): ?>
                                        <div class="list-group-item px-0">
                                            <div class="fw-semibold"><?php echo htmlspecialchars($appt['pg_name']); ?></div>
                                            <div class="small text-muted">
                                                <?php echo htmlspecialchars($appt['user_name']); ?>
                                                <?php if (!empty($appt['contact_phone'])): ?> · <?php echo htmlspecialchars($appt['contact_phone']); ?><?php endif; ?>
                                            </div>
                                            <div class="small mt-1">
                                                <?php echo htmlspecialchars(date('d M Y, h:i A', strtotime((string)$appt['visit_datetime']))); ?>
                                                <span class="badge bg-light text-dark border ms-2"><?php echo htmlspecialchars(ucfirst((string)$appt['visit_status'])); ?></span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Owner PGs -->
            <form method="GET" class="card border-0 shadow-sm mb-3">
                <div class="card-body d-flex gap-2 flex-wrap">
                    <input type="text" name="q" class="form-control" style="max-width:420px;" placeholder="Search your PGs by name, code, city, address" value="<?php echo htmlspecialchars($q); ?>">
                    <button class="btn btn-primary">Search</button>
                    <?php if ($q !== ''): ?>
                      <a href="owner-dashboard.php" class="btn btn-outline-secondary">Clear</a>
                    <?php endif; ?>
                </div>
            </form>

            <?php
            $sql = 'SELECT p.id, p.pg_name, p.pg_code, p.city, p.address, p.monthly_rent, p.capacity, p.available_beds, p.sharing_type, p.status, p.occupancy_status,
                           (SELECT image_path FROM pg_images WHERE pg_id = p.id ORDER BY id LIMIT 1) AS cover_image
                    FROM pg_listings p
                    WHERE owner_id = :oid';
            $params = [':oid' => $ownerId];
            if ($q !== '') {
                $sql .= ' AND (
                    LOWER(p.pg_name) LIKE :q_name
                    OR LOWER(p.pg_code) LIKE :q_code
                    OR LOWER(p.city) LIKE :q_city
                    OR LOWER(p.address) LIKE :q_address
                )';
                $like = '%' . strtolower($q) . '%';
                $params[':q_name'] = $like;
                $params[':q_code'] = $like;
                $params[':q_city'] = $like;
                $params[':q_address'] = $like;
            }
            $sql .= ' ORDER BY created_at DESC';
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $allMyPgs = $stmt->fetchAll();
            $totalPgs = count($allMyPgs);
            $totalPages = max(1, (int)ceil($totalPgs / $perPage));
            if ($page > $totalPages) $page = $totalPages;
            $offset = ($page - 1) * $perPage;
            $myPgs = array_slice($allMyPgs, $offset, $perPage);
            ?>

            <div class="row g-3 mb-4">
            <?php if (empty($myPgs)): ?>
                <div class="col-12">
                    <div class="alert alert-info"><?php echo $q !== '' ? 'No PG matched your search.' : 'You have not added any PGs yet.'; ?></div>
                </div>
            <?php else: ?>
                <?php foreach ($myPgs as $pg): ?>
                <div class="col-md-6 col-lg-4">
                    <article class="pg-card h-100 p-0">
                        <?php
                        $img = $pg['cover_image'] ?? '';
                        $fallback = pg_fallback_image((int)$pg['id']);
                        if (empty($img)) {
                            $img = $fallback;
                        } else {
                            $img = pg_image_url($img, $fallback);
                        }
                        ?>
                        <img src="<?php echo htmlspecialchars($img); ?>" class="w-100" style="height:180px;object-fit:cover;" alt="PG">
                        <div class="p-3">
                            <div class="d-flex justify-content-between align-items-center mb-1 small">
                                <span><?php echo htmlspecialchars($pg['pg_name']); ?></span>
                                <span class="text-warning fw-semibold">₹<?php echo number_format($pg['monthly_rent']); ?>/mo</span>
                            </div>
                            <p class="small text-muted mb-2"><?php echo htmlspecialchars($pg['address']); ?></p>
                            <div class="d-flex gap-2">
                                <span class="tag"><?php echo htmlspecialchars(ucfirst($pg['sharing_type'])); ?></span>
                                <span class="tag"><?php echo (int)$pg['capacity']; ?> beds</span>
                                <span class="tag"><?php echo (int)($pg['available_beds'] ?? 0); ?> available</span>
                                <span class="tag"><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', (string)($pg['occupancy_status'] ?? 'available')))); ?></span>
                                <span class="tag"><?php echo htmlspecialchars(ucfirst((string)($pg['status'] ?? 'pending'))); ?></span>
                            </div>
                            <div class="mt-3 d-flex gap-2">
                                <a href="owner-pg-list.php" class="btn btn-sm btn-outline-primary">Manage</a>
                                <a href="owner-add-pg.php?id=<?php echo (int)$pg['id']; ?>" class="btn btn-sm btn-gradient">Edit</a>
                            </div>
                            <div class="mt-3">
                                <form method="POST" action="owner-pg-action.php" class="d-flex gap-2 flex-wrap align-items-center">
                                    <input type="hidden" name="pg_id" value="<?php echo (int)$pg['id']; ?>">
                                    <input type="hidden" name="q" value="<?php echo htmlspecialchars($q); ?>">
                                    <?php if (($pg['status'] ?? '') === 'paused'): ?>
                                      <button class="btn btn-sm btn-outline-success" name="action" value="resume" type="submit">Resume</button>
                                    <?php else: ?>
                                      <button class="btn btn-sm btn-outline-secondary" name="action" value="pause" type="submit">Pause</button>
                                    <?php endif; ?>
                                    <button class="btn btn-sm btn-outline-danger" name="action" value="mark_full" type="submit">Mark full</button>
                                    <div class="input-group input-group-sm" style="max-width: 170px;">
                                      <span class="input-group-text">Beds</span>
                                      <input type="number" min="0" max="<?php echo (int)$pg['capacity']; ?>" name="available_beds" class="form-control" value="<?php echo (int)($pg['available_beds'] ?? 0); ?>">
                                      <button class="btn btn-outline-primary" name="action" value="beds" type="submit">Update</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </article>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
            </div>

            <?php if (!empty($allMyPgs) && $totalPages > 1): ?>
              <?php $queryBase = ['q' => $q]; ?>
              <nav aria-label="Owner PG pagination" class="mb-4">
                <ul class="pagination flex-wrap">
                  <?php $prevQuery = $queryBase; $prevQuery['page'] = max(1, $page - 1); ?>
                  <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                    <a class="page-link" href="owner-dashboard.php?<?php echo htmlspecialchars(http_build_query($prevQuery)); ?>">Previous</a>
                  </li>
                  <?php for ($p = max(1, $page - 2); $p <= min($totalPages, $page + 2); $p++): ?>
                    <?php $pageQuery = $queryBase; $pageQuery['page'] = $p; ?>
                    <li class="page-item <?php echo $p === $page ? 'active' : ''; ?>">
                      <a class="page-link" href="owner-dashboard.php?<?php echo htmlspecialchars(http_build_query($pageQuery)); ?>"><?php echo (int)$p; ?></a>
                    </li>
                  <?php endfor; ?>
                  <?php $nextQuery = $queryBase; $nextQuery['page'] = min($totalPages, $page + 1); ?>
                  <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                    <a class="page-link" href="owner-dashboard.php?<?php echo htmlspecialchars(http_build_query($nextQuery)); ?>">Next</a>
                  </li>
                </ul>
                <div class="small text-muted">Showing <?php echo count($myPgs); ?> of <?php echo (int)$totalPgs; ?> PGs · Page <?php echo (int)$page; ?> of <?php echo (int)$totalPages; ?></div>
              </nav>
            <?php endif; ?>

            <?php
                // count pending booking requests for this owner's PGs
                try {
                    $bc = $pdo->prepare('SELECT COUNT(b.id) FROM bookings b JOIN pg_listings p ON p.id = b.pg_id WHERE p.owner_id = ? AND b.status = ?');
                    $bc->execute([$ownerId, 'requested']);
                    $bookingCount = (int)$bc->fetchColumn();
                } catch (Throwable $e) {
                    $bookingCount = 0;
                }
            ?>
            <div class="mt-3">
                <a href="owner-add-pg.php" class="btn btn-success btn-lg">+ Add New PG</a>
                <a href="owner-bookings.php" class="btn btn-outline-primary btn-lg ms-2">View Bookings <?php if ($bookingCount) echo '<span class="badge bg-danger ms-2">' . $bookingCount . '</span>'; ?></a>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
