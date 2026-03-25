<?php 
$page_title = $page_title ?? 'PGConnect';
// include global error handler to avoid blank white screens on fatal errors
@include_once __DIR__ . '/error_handler.php';
// Ensure session cookie is available site-wide
if (session_status() === PHP_SESSION_NONE) {
  // set cookie path to root so session cookie is sent on all pages
  @session_set_cookie_params(0, '/');
  session_start();
}

// Base URL for site (adjust if you deploy under a different path)
if (!defined('BASE_URL')) define('BASE_URL', '/PGConnect');

// Normalize image paths stored in DB (e.g., "uploads/...", "/uploads/...", absolute URLs)
function pg_image_url($path, $fallback = '') {
  $p = trim((string)$path);
  if ($p === '') return $fallback;
  if (preg_match('#^https?://#i', $p)) return $p;

  $rel = $p;
  if ($rel[0] === '/') $rel = ltrim($rel, '/');
  $baseRel = ltrim(BASE_URL, '/');
  if ($baseRel !== '' && strpos($rel, $baseRel) === 0) {
    $rel = ltrim(substr($rel, strlen($baseRel)), '/');
  }

  $root = dirname(__DIR__);
  $fs = $root . '/' . $rel;
  if ($fallback && !file_exists($fs)) return $fallback;
  return BASE_URL . '/' . ltrim($rel, '/');
}

// Deterministic fallback rating when no reviews exist
function pg_fallback_rating($pgId) {
  $seed = ($pgId * 9301 + 49297) % 233280;
  $r = 3.6 + ($seed / 233280) * 1.3; // 3.6 - 4.9
  return round($r, 1);
}

// Deterministic fallback images for PGs without uploads
function pg_fallback_image($pgId) {
  $imgs = [
    'https://images.pexels.com/photos/1457841/pexels-photo-1457841.jpeg',
    'https://images.pexels.com/photos/1643383/pexels-photo-1643383.jpeg',
    'https://images.pexels.com/photos/2121121/pexels-photo-2121121.jpeg',
    'https://images.pexels.com/photos/1454806/pexels-photo-1454806.jpeg',
    'https://images.pexels.com/photos/271618/pexels-photo-271618.jpeg',
    'https://images.pexels.com/photos/259588/pexels-photo-259588.jpeg'
  ];
  $idx = abs((int)$pgId) % count($imgs);
  return $imgs[$idx];
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title><?php echo htmlspecialchars($page_title); ?> – Find PGs Across India</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#2563eb">
  <link rel="manifest" href="<?php echo BASE_URL; ?>/manifest.webmanifest">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
  <style>
    /* YOUR EXACT CSS FROM index.html - ALL styles */
    :root { --app-nav-offset: 92px; }
    body { background: #f5f5f7; color: #0f172a; font-family: system-ui,-apple-system,"Segoe UI",sans-serif; padding-top: var(--app-nav-offset); }
    .navbar { background-color: rgba(255,255,255,0.9); backdrop-filter: blur(16px); box-shadow: 0 8px 24px rgba(15,23,42,0.04); }
    .navbar-brand span { font-weight: 700; letter-spacing: .08em; text-transform: uppercase; }
    .hero-wrap { position: relative; padding-top: 5.5rem; padding-bottom: 3.5rem; overflow: hidden; }
    .hero-bg { position: absolute; inset: 0; background: linear-gradient(135deg,#e0f2fe 0,#f9fafb 35%,#e5e7eb 70%,#eef2ff 100%); z-index: -2; }
    .hero-photo { border-radius: 32px; overflow: hidden; box-shadow: 0 32px 80px rgba(15,23,42,0.25); }
    .hero-photo img { width: 100%; height: 420px; object-fit: cover; }
    .hero-title { font-size: clamp(2.4rem,3.7vw,3.1rem); font-weight: 700; line-height: 1.02; }
    .hero-title span { background: linear-gradient(120deg,#2563eb,#22c55e); -webkit-background-clip: text; color: transparent; }
    .hero-subtitle { color: #6b7280; max-width: 30rem; }
    .eyebrow { display: inline-flex; align-items: center; gap: 8px; padding: 4px 10px; border-radius: 999px; background: #e0f2fe; color: #0369a1; font-size: .75rem; margin-bottom: .8rem; }
    .eyebrow span { width: 9px; height: 9px; border-radius: 999px; background: #22c55e; box-shadow: 0 0 0 4px rgba(34,197,94,0.25); }
    .search-card { position: absolute; left: 50%; bottom: -2.2rem; transform: translateX(-50%); width: min(840px, 92%); border-radius: 1.8rem; background: rgba(255,255,255,0.75); border: 1px solid rgba(209,213,219,0.6); backdrop-filter: blur(20px); box-shadow: 0 18px 40px rgba(15,23,42,0.14), 0 0 0 1px rgba(255,255,255,0.9) inset; padding: 1.1rem 1.4rem 1.2rem; }
    .pill-toggle button.active { background-color: #111827; color: #f9fafb; box-shadow: 0 12px 28px rgba(15,23,42,.35); }
    .pill-toggle button:not(.active) { color: #6b7280; }
    .form-control, .form-select { background-color: #f9fafb; border-color: #d1d5db; color: #111827; font-size: .9rem; }
    .form-control::placeholder { color:#9ca3af; }
    .form-control:focus, .form-select:focus { background-color: #ffffff; border-color: #2563eb; box-shadow: 0 0 0 .15rem rgba(37,99,235,.35); color:#111827; }
    .btn-gradient { background: linear-gradient(120deg,#22c55e,#2563eb); border: none; color: #fff; box-shadow: 0 14px 30px rgba(37,99,235,.35); }
    .hero-metrics span strong { display:block; font-size:.95rem; }
    .section-shell { margin-top: .75rem; padding-top: 2.5rem; padding-bottom: 3rem; background: #ffffff; border-radius: 2.4rem 2.4rem 0 0; box-shadow: 0 -6px 24px rgba(15,23,42,0.06); }
    .pg-card { background: #ffffff; border-radius: 1.3rem; overflow: hidden; border: 1px solid #e5e7eb; box-shadow: 0 14px 30px rgba(15,23,42,.08); transition: all 0.3s ease; }
    .pg-card:hover { transform: translateY(-8px); box-shadow: 0 24px 48px rgba(15,23,42,.15); }
    .pg-card img { object-fit: cover; height: 190px; }
    .fav-heart-btn { position:absolute; top:12px; right:12px; background:#fff; border:1px solid #e5e7eb; border-radius:999px; width:34px; height:34px; display:flex; align-items:center; justify-content:center; box-shadow:0 6px 16px rgba(15,23,42,.12); }
    .fav-heart-btn i { color:#ef4444; font-size:14px; }
    .fav-heart-btn.active { background:#fee2e2; border-color:#fecaca; }
    .role-tile { flex:1; cursor:pointer; }
    .role-tile input { display:none; }
    .role-tile .tile-body { display:flex; align-items:center; justify-content:center; flex-direction:column; gap:6px; height:90px; border:1px solid #e5e7eb; border-radius:14px; background:#f9fafb; font-weight:600; color:#111827; transition:all .2s ease; }
    .role-tile .tile-body i { font-size:18px; color:#2563eb; }
    .role-tile input:checked + .tile-body { border-color:#2563eb; background:#eff6ff; box-shadow:0 10px 24px rgba(37,99,235,.18); }
    .chat-link { display:inline-flex; align-items:center; gap:6px; }
    .rating-badge { font-size:.75rem; padding:.2rem .45rem; border-radius:999px; background:#fff7ed; color:#9a3412; border:1px solid #fed7aa; }
    .tag { font-size: .7rem; border-radius: 999px; padding: .2rem .6rem; background: #eff6ff; border: 1px solid #bfdbfe; color: #1d4ed8; }
    #pg-map { height: 260px; border-radius: 1.4rem; overflow: hidden; border: 1px solid #e5e7eb; box-shadow: 0 14px 30px rgba(15,23,42,.08); }
    footer { font-size: .8rem; color: #6b7280; background: #ffffff; border-top: 1px solid #e5e7eb; }
    @media (max-width: 991.98px) { :root { --app-nav-offset: 84px; } .hero-wrap { padding-bottom: 5.3rem; } .search-card { position: static; transform:none; margin-top:1.4rem; } .hero-photo img { height: 320px; } .hero-subtitle { max-width: 100%; } }
  </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-light fixed-top">
  <div class="container">
  <a class="navbar-brand d-flex align-items-center gap-2" href="<?php echo BASE_URL; ?>/index.php">
      <span class="badge rounded-circle bg-primary text-white fw-bold">PG</span>
      <span>PGCONNECT</span>
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="mainNav">
      <?php $hideNavForAuth = in_array(basename($_SERVER['PHP_SELF'] ?? ''), ['login.php', 'signup.php'], true); ?>
      <ul class="navbar-nav ms-auto mb-2 mb-lg-0 me-3">
        <?php if (!$hideNavForAuth && (!isset($_SESSION['user_role']) || $_SESSION['user_role'] === 'user')): ?>
          <li class="nav-item"><a class="nav-link" href="<?php echo BASE_URL; ?>/user/pg-listings.php">PGs</a></li>
        <?php endif; ?>
        <?php
          $isLoggedIn = isset($_SESSION['user_id']);
          if ($isLoggedIn) {
            if (($_SESSION['user_role'] ?? '') === 'owner') $chatPath = '/owner/chat.php';
            elseif (($_SESSION['user_role'] ?? '') === 'admin') $chatPath = '/admin/chat.php';
            else $chatPath = '/user/chat.php';
          } else {
            $chatPath = '/backend/login.php';
          }
        ?>
        <?php if (!$hideNavForAuth): ?>
          <li class="nav-item">
            <a class="nav-link chat-link" href="<?php echo BASE_URL . $chatPath; ?>">
              <i class="fa-solid fa-comment-dots"></i> Chat
              <?php if ($isLoggedIn): ?>
                <span class="badge bg-danger rounded-pill ms-1" id="chatCountBadge" style="display:none;">0</span>
              <?php endif; ?>
            </a>
          </li>
        <?php endif; ?>
        <?php if ($isLoggedIn): ?>
          <?php if (!$hideNavForAuth): ?>
            <li class="nav-item dropdown">
              <a class="nav-link position-relative" href="#" data-bs-toggle="dropdown" id="notificationBell">
                <i class="fa fa-bell"></i>
                <span class="badge bg-danger rounded-pill ms-1" id="notificationCountBadge" style="display:none;">0</span>
              </a>
              <ul class="dropdown-menu dropdown-menu-end p-0" style="min-width:320px;">
                <li class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom">
                  <strong class="small">Notifications</strong>
                  <button class="btn btn-link btn-sm p-0" id="markAllNotificationsRead">Mark all read</button>
                </li>
                <li id="notificationListWrap">
                  <div class="px-3 py-2 small text-muted">No notifications.</div>
                </li>
              </ul>
            </li>
          <?php endif; ?>
          <?php if (($_SESSION['user_role'] ?? '') === 'owner' && !$hideNavForAuth): ?>
            <li class="nav-item">
              <a class="nav-link position-relative" href="<?php echo BASE_URL; ?>/owner/owner-bookings.php">
                Booking Requests
                <span class="badge bg-danger rounded-pill ms-1" id="ownerBookingCountBadge" style="display:none;">0</span>
              </a>
            </li>
          <?php endif; ?>
          <?php if (($_SESSION['user_role'] ?? '') === 'user' && !$hideNavForAuth): ?>
            <li class="nav-item">
              <a class="nav-link" href="<?php echo BASE_URL; ?>/user/booking-request.php">Bookings</a>
            </li>
          <?php endif; ?>
          <li class="nav-item">
            <a class="nav-link position-relative" href="<?php echo BASE_URL; ?>/user/my-favorites.php">
              <i class="fa fa-heart"></i>
              <span class="badge bg-danger rounded-pill ms-1" id="favCountBadge">0</span>
            </a>
          </li>
          <?php if (($_SESSION['user_role'] ?? '') === 'user'): ?>
            <li class="nav-item">
              <a class="nav-link position-relative" href="<?php echo BASE_URL; ?>/user/compare.php">
                <i class="fa fa-clone"></i>
                <?php $cmpCount = isset($_SESSION['compare_pgs']) ? count($_SESSION['compare_pgs']) : 0; ?>
                <span class="badge bg-dark rounded-pill ms-1" id="compareCountBadge" data-count="<?php echo (int)$cmpCount; ?>">0</span>
              </a>
            </li>
          <?php endif; ?>
        <?php endif; ?>
        <?php if(isset($_SESSION['user_role'])): ?>
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown"><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'User'); ?></a>
            <ul class="dropdown-menu">
              <?php if($_SESSION['user_role'] == 'owner'): ?>
                <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/owner/owner-add-pg.php">Add New PG</a></li>
                <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/owner/bulk-upload.php">Bulk Upload PGs</a></li>
                <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/owner/owner-dashboard.php">Owner Dashboard</a></li>
                <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/owner/tickets.php">Service Tickets</a></li>
                <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/owner/owner-reviews.php">PG Reviews</a></li>
                <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/owner/owner-profile.php">My Profile</a></li>
              <?php elseif($_SESSION['user_role'] == 'admin'): ?>
                <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/admin/admin-dashboard.php">Admin Panel</a></li>
                <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/admin/admin-tickets.php">Tickets Monitor</a></li>
                <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/admin/admin-reviews.php">Review Moderation</a></li>
              <?php else: ?>
                <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/user/user-profile.php">User Dashboard</a></li>
                <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/user/tickets.php">Service Tickets</a></li>
                <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/user/saved-searches.php">Saved Searches</a></li>
                <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/user/user-profile-edit.php">My Profile</a></li>
              <?php endif; ?>
              <li><hr class="dropdown-divider"></li>
              <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/logout.php">Logout</a></li>
            </ul>
          </li>
        <?php endif; ?>
      </ul>
      <?php if(!isset($_SESSION['user_role'])): ?>
        <div class="d-flex gap-2">
          <a href="<?php echo BASE_URL; ?>/backend/login.php" class="btn btn-outline-secondary btn-sm px-3">Login</a>
          <a href="<?php echo BASE_URL; ?>/backend/signup.php" class="btn btn-primary btn-sm px-3">Sign Up</a>
        </div>
      <?php endif; ?>
    </div>
  </div>
</nav>
