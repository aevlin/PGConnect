<?php
require_once __DIR__ . '/backend/bootstrap.php';
if (session_status() === PHP_SESSION_NONE) {
  @session_set_cookie_params(0, '/');
  session_start();
}
require_once __DIR__ . '/backend/connect.php';
require_once __DIR__ . '/backend/config.php';

function pg_fallback_image($pgId) {
  $imgs = [
    'https://images.pexels.com/photos/1457841/pexels-photo-1457841.jpeg',
    'https://images.pexels.com/photos/1643383/pexels-photo-1643383.jpeg',
    'https://images.pexels.com/photos/2121121/pexels-photo-2121121.jpeg',
    'https://images.pexels.com/photos/1454806/pexels-photo-1454806.jpeg',
    'https://images.pexels.com/photos/271618/pexels-photo-271618.jpeg',
    'https://images.pexels.com/photos/259588/pexels-photo-259588.jpeg'
  ];
  return $imgs[abs((int)$pgId) % count($imgs)];
}

$statusWhere = (defined('SHOW_PENDING_LISTINGS') && SHOW_PENDING_LISTINGS) ? "IN ('approved','pending')" : "= 'approved'";
$bestPgs = [];
try {
  $stmt = $pdo->prepare("SELECT p.id, p.pg_name, p.city, p.address, p.monthly_rent, p.capacity, p.sharing_type,
          (SELECT image_path FROM pg_images WHERE pg_id = p.id ORDER BY id LIMIT 1) AS cover_image
        FROM pg_listings p
        WHERE p.status $statusWhere
        ORDER BY p.created_at DESC
        LIMIT 4");
  $stmt->execute();
  $bestPgs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  $bestPgs = [];
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>PGConnect – Find PGs Across India</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
  <style>
    body {
      background: #f5f5f7;
      color: #0f172a;
      font-family: system-ui,-apple-system,"Segoe UI",sans-serif;
    }
    .navbar {
      background-color: rgba(255,255,255,0.9);
      backdrop-filter: blur(16px);
      box-shadow: 0 8px 24px rgba(15,23,42,0.04);
    }
    .navbar-brand span {
      font-weight: 700;
      letter-spacing: .08em;
      text-transform: uppercase;
    }
    .hero-wrap {
      position: relative;
      padding-top: 5.5rem;
      padding-bottom: 3.5rem;
      overflow: hidden;
    }
    .hero-bg {
      position: absolute;
      inset: 0;
      background:
        linear-gradient(135deg,#e0f2fe 0,#f9fafb 35%,#e5e7eb 70%,#eef2ff 100%);
      z-index: -2;
    }
    .hero-photo {
      border-radius: 32px;
      overflow: hidden;
      box-shadow: 0 32px 80px rgba(15,23,42,0.25);
    }
    .hero-photo img {
      width: 100%;
      height: 420px;
      object-fit: cover;
    }
    .hero-title {
      font-size: clamp(2.4rem,3.7vw,3.1rem);
      font-weight: 700;
      line-height: 1.02;
    }
    .hero-title span {
      background: linear-gradient(120deg,#2563eb,#22c55e);
      background-clip: text;
      -webkit-background-clip: text;
      color: transparent;
    }
    .hero-subtitle {
      color: #6b7280;
      max-width: 30rem;
    }
    .eyebrow {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 4px 10px;
      border-radius: 999px;
      background: #e0f2fe;
      color: #0369a1;
      font-size: .75rem;
      margin-bottom: .8rem;
    }
    .eyebrow span {
      width: 9px;
      height: 9px;
      border-radius: 999px;
      background: #22c55e;
      box-shadow: 0 0 0 4px rgba(34,197,94,0.25);
    }
    .search-card {
      position: absolute;
      left: 50%;
      bottom: -2.2rem;
      transform: translateX(-50%);
      width: min(840px, 92%);
      border-radius: 1.8rem;
      background: rgba(255,255,255,0.75);
      border: 1px solid rgba(209,213,219,0.6);
      backdrop-filter: blur(20px);
      box-shadow:
        0 18px 40px rgba(15,23,42,0.14),
        0 0 0 1px rgba(255,255,255,0.9) inset;
      padding: 1.1rem 1.4rem 1.2rem;
    }
    .pill-toggle button.active {
      background-color: #111827;
      color: #f9fafb;
      box-shadow: 0 12px 28px rgba(15,23,42,.35);
    }
    .pill-toggle button { transition: background-color .18s ease, color .18s ease, box-shadow .18s ease; }
    .pill-toggle button:not(.active) {
      color: #6b7280;
    }
    .form-control,
    .form-select {
      background-color: #f9fafb;
      border-color: #d1d5db;
      color: #111827;
      font-size: .9rem;
    }
    .form-control::placeholder { color:#9ca3af; }
    .form-control:focus,
    .form-select:focus {
      background-color: #ffffff;
      border-color: #2563eb;
      box-shadow: 0 0 0 .15rem rgba(37,99,235,.35);
      color:#111827;
    }
    .btn-gradient {
      background: linear-gradient(120deg,#22c55e,#2563eb);
      border: none;
      color: #fff;
      box-shadow: 0 14px 30px rgba(37,99,235,.35);
    }
    .hero-metrics span strong {
      display:block;
      font-size:.95rem;
    }
    .section-shell {
      margin-top: 5.5rem;
      padding-top: 2.5rem;
      padding-bottom: 3rem;
      background: #ffffff;
      border-radius: 2.4rem 2.4rem 0 0;
      box-shadow: 0 -6px 24px rgba(15,23,42,0.06);
    }
    .pg-card {
      background: #ffffff;
      border-radius: 1.3rem;
      overflow: hidden;
      border: 1px solid #e5e7eb;
      box-shadow: 0 14px 30px rgba(15,23,42,.08);
      transition: all 0.3s ease;
    }
    .pg-card:hover {
      transform: translateY(-8px);
      box-shadow: 0 24px 48px rgba(15,23,42,.15);
    }
    .pg-card img {
      object-fit: cover;
      height: 190px;
    }
    /* map marker custom style */
    .pg-marker { line-height: 1; }
    .map-intro { background: rgba(255,255,255,0.95); border-radius: 8px; padding: 8px; }
    .tag {
      font-size: .7rem;
      border-radius: 999px;
      padding: .2rem .6rem;
      background: #eff6ff;
      border: 1px solid #bfdbfe;
      color: #1d4ed8;
    }
    #pg-map {
      height: 260px;
      border-radius: 1.4rem;
      overflow: hidden;
      border: 1px solid #e5e7eb;
      box-shadow: 0 14px 30px rgba(15,23,42,.08);
    }
    .owner-earn-icon-wrap {
      width: 128px;
      height: 128px;
      margin: 0 auto;
      border-radius: 28px;
      display: flex;
      align-items: center;
      justify-content: center;
      background: linear-gradient(135deg, #eff6ff, #ecfeff);
      border: 1px solid #dbeafe;
      box-shadow: inset 0 1px 0 rgba(255,255,255,.9), 0 12px 28px rgba(37,99,235,.08);
      position: relative;
    }
    .owner-earn-icon-wrap .fa-house {
      font-size: 3.15rem;
      color: #111827;
    }
    .owner-earn-icon-wrap .fa-suitcase-rolling {
      position: absolute;
      right: 14px;
      bottom: 14px;
      font-size: 1.45rem;
      color: #2563eb;
      background: #ffffff;
      border-radius: 16px;
      padding: 10px;
      box-shadow: 0 10px 24px rgba(37,99,235,.16);
    }
    footer {
      font-size: .8rem;
      color: #6b7280;
      background: #ffffff;
      border-top: 1px solid #e5e7eb;
    }
    @media (max-width: 991.98px) {
      .hero-wrap { padding-bottom: 5.3rem; }
      .search-card { position: static; transform:none; margin-top:1.4rem; }
      .hero-photo img { height: 320px; }
      .hero-subtitle { max-width: 100%; }
    }
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
      <ul class="navbar-nav ms-auto mb-2 mb-lg-0 me-3">
        <li class="nav-item"><a class="nav-link" href="#listings">PGs</a></li>
        <li class="nav-item"><a class="nav-link" href="#owners">For owners</a></li>
      </ul>
      <?php if (!isset($_SESSION['user_id'])): ?>
        <div class="d-flex gap-2">
          <a href="<?php echo BASE_URL; ?>/backend/login.php" class="btn btn-outline-secondary btn-sm px-3">Login</a>
          <a href="<?php echo BASE_URL; ?>/backend/signup.php" class="btn btn-primary btn-sm px-3">Sign Up</a>
        </div>
      <?php else: ?>
        <ul class="navbar-nav mb-2 mb-lg-0">
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown"><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'User'); ?></a>
            <ul class="dropdown-menu dropdown-menu-end">
              <?php if (($_SESSION['user_role'] ?? '') === 'owner'): ?>
                <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/owner/owner-add-pg.php">Add New PG</a></li>
                <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/owner/bulk-upload.php">Bulk Upload PGs</a></li>
                <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/owner/owner-dashboard.php">Owner Dashboard</a></li>
              <?php elseif (($_SESSION['user_role'] ?? '') === 'admin'): ?>
                <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/admin/admin-dashboard.php">Admin Panel</a></li>
              <?php else: ?>
                <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/user/user-profile.php">User Dashboard</a></li>
              <?php endif; ?>
              <li><hr class="dropdown-divider"></li>
              <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/logout.php">Logout</a></li>
            </ul>
          </li>
        </ul>
      <?php endif; ?>
    </div>
  </div>
</nav>

<!-- HERO -->
<header class="hero-wrap">
  <div class="hero-bg"></div>
  <div class="container position-relative">
    <div class="row g-4 align-items-center">
      <div class="col-lg-5">
        <div class="eyebrow">
          <span></span>
          Verified PGs • Zero brokerage • Pan India
        </div>
        <h1 class="hero-title mb-3">
          Your next <span>PG room</span> is just a few clicks away.
        </h1>
        <p class="hero-subtitle mb-3">
          PGConnect helps working professionals and students discover clean, verified PGs across India with the right commute, budget, and amenities.
        </p>
        <div class="d-flex flex-wrap gap-4 small text-muted hero-metrics">
          <span><strong>25K+</strong> verified PGs</span>
          <span><strong>4.9★</strong> average rating</span>
          <span><strong>120+</strong> cities</span>
        </div>
      </div>
      <div class="col-lg-7 position-relative">
        <div class="hero-photo mb-4 mb-lg-0">
          <img src="https://images.pexels.com/photos/1454806/pexels-photo-1454806.jpeg" alt="Modern PG room">
        </div>
        <div class="search-card">
          <div class="d-flex pill-toggle bg-light rounded-pill p-1 mb-3">
            <button class="btn btn-sm flex-fill rounded-pill active" type="button">Working Professionals</button>
            <button class="btn btn-sm flex-fill rounded-pill" type="button">Students</button>
            <button class="btn btn-sm flex-fill rounded-pill" type="button">Co‑living</button>
          </div>
          <form action="user/pg-listings.php" method="GET">
            <div class="row g-2 align-items-end">
              <div class="col-md-4">
                <label class="form-label small mb-1">City / Area</label>
                <input type="text" name="city" class="form-control" placeholder="Mumbai, BLR, Delhi, Kochi">
              </div>
              <div class="col-md-3">
                <label class="form-label small mb-1">Room type</label>
                <select class="form-select" name="sharing">
                  <option value="">Any</option>
                  <option value="single">Single</option>
                  <option value="double">2‑sharing</option>
                  <option value="triple">3‑sharing</option>
                </select>
              </div>
              <div class="col-md-2">
                <label class="form-label small mb-1">Gender</label>
                <select class="form-select" name="gender">
                  <option value="any">Any</option>
                  <option value="gents">Gents</option>
                  <option value="ladies">Ladies</option>
                  <option value="coed">Co‑ed</option>
                </select>
              </div>
              <div class="col-md-3">
                <label class="form-label small mb-1">Budget / month</label>
                <select class="form-select" name="budget">
                  <option value="">Any</option>
                  <option value="5000-10000">₹5k – ₹10k</option>
                  <option value="10000-15000">₹10k – ₹15k</option>
                  <option value="15000-20000">₹15k – ₹20k</option>
                  <option value="20000-">₹20k+</option>
                </select>
              </div>
            </div>
            <!-- hidden fields mapped from budget range for search.php compatibility -->
            <input type="hidden" name="min_rent" id="min_rent">
            <input type="hidden" name="max_rent" id="max_rent">
            <!-- audience: working|students|coliving -->
            <input type="hidden" name="audience" id="audience" value="working">
            <div class="row mt-3">
              <div class="col-12">
                <button class="btn btn-gradient w-100 py-2" type="submit">
                  <i class="fas fa-search me-2"></i>Search PGs in India
                </button>
              </div>
            </div>
            <div class="row mt-2">
              <div class="col-8 text-center">
                <button id="nearbyBtn" type="button" class="btn btn-outline-primary btn-sm mt-2">
                  <i class="fas fa-location-dot me-1"></i>Nearby me
                </button>
                <small class="d-block text-muted mt-2">Allow location access to find listings near you</small>
              </div>
              <div class="col-4 text-center">
                <label class="form-label small mb-1">Radius</label>
                <select id="radiusSelect" class="form-select form-select-sm">
                  <option value="1">1 km</option>
                  <option value="3">3 km</option>
                  <option value="5" selected>5 km</option>
                  <option value="10">10 km</option>
                  <option value="20">20 km</option>
                </select>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</header>

<!-- LISTINGS + MAP -->
<section id="listings" class="section-shell">
  <div class="container">
    <div class="row g-4 align-items-start">
      <div class="col-lg-7">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h2 class="h5 mb-0">Best PGs right now</h2>
          <a href="user/pg-listings.php" class="small text-primary">View all →</a>
        </div>
        <div class="row g-3">
          <?php if (empty($bestPgs)): ?>
            <div class="col-12"><div class="alert alert-info">No PGs to show yet.</div></div>
          <?php else: ?>
            <?php foreach ($bestPgs as $pg): 
              $img = $pg['cover_image'] ?: '';
              if ($img && !preg_match('#^(https?://|/)#', $img)) $img = BASE_URL . '/' . ltrim($img, '/');
              if (!$img) $img = pg_fallback_image((int)$pg['id']);
            ?>
              <div class="col-md-6">
                <article class="pg-card h-100">
                  <img src="<?php echo htmlspecialchars($img); ?>" class="w-100" alt="PG">
                  <div class="p-3">
                    <div class="d-flex justify-content-between mb-1 small">
                      <span><?php echo htmlspecialchars($pg['pg_name']); ?></span>
                      <span class="text-warning fw-semibold">₹<?php echo number_format((float)$pg['monthly_rent']); ?>/mo</span>
                    </div>
                    <p class="small text-muted mb-2"><?php echo htmlspecialchars($pg['address']); ?></p>
                    <div class="d-flex flex-wrap gap-2">
                      <span class="tag"><?php echo htmlspecialchars(ucfirst($pg['sharing_type'])); ?></span>
                      <span class="tag"><?php echo (int)$pg['capacity']; ?> beds</span>
                    </div>
                    <div class="mt-3">
                      <a href="user/pg-detail.php?id=<?php echo (int)$pg['id']; ?>" class="btn btn-sm btn-outline-primary">View details</a>
                    </div>
                  </div>
                </article>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>

      <div class="col-lg-5">
        <div class="d-flex justify-content-between mb-2">
          <h2 class="h6 mb-0">India Map View</h2>
          <span class="small text-muted">LeafletJS + OpenStreetMap</span>
        </div>
        <div id="pg-map"></div>
        <div id="nearby-results" class="mt-3"></div>
        <p class="small text-muted mt-2">
          Zoom into your city and tap markers to preview PGs.
        </p>
      </div>
    </div>
  </div>
</section>

<!-- FOR OWNERS -->
<section id="owners" class="section-shell">
  <div class="container py-5">
    <div class="row align-items-center g-4">
      <div class="col-lg-6">
        <h2 class="h4 mb-3">For owners</h2>
        <p class="text-muted">List your PG quickly, manage listings, and reach tenants with no brokerage. Easy dashboard, photo uploads, and bulk import tools.</p>
        <ul class="list-unstyled">
          <li>• Easy listing flow with photos</li>
          <li>• Bulk CSV import for many PGs</li>
          <li>• Owner dashboard to manage approvals</li>
        </ul>
        <div class="d-flex gap-2 mt-3">
          <a href="owner/owner-add-pg.php" class="btn btn-gradient">List your PG</a>
          <a href="owner/bulk-upload.php" class="btn btn-outline-primary">Bulk upload</a>
          <a href="owner/owner-dashboard.php" class="btn btn-outline-secondary">Owner dashboard</a>
        </div>
      </div>
      <div class="col-lg-6">
        <div class="card border-0 shadow-sm p-3">
          <div class="row g-3">
            <div class="col-4 d-flex align-items-center justify-content-center">
              <div class="owner-earn-icon-wrap" aria-hidden="true">
                <i class="fa-solid fa-house"></i>
                <i class="fa-solid fa-suitcase-rolling"></i>
              </div>
            </div>
            <div class="col-8">
              <h5 class="mb-1">Start earning from day one</h5>
              <p class="small text-muted mb-0">Add accurate details, upload photos, and our team will verify your listing. Manage bookings and tenant queries from the dashboard.</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<footer class="py-3">
  <div class="container d-flex flex-wrap justify-content-between">
    <span>© 2025 PGConnect. Built for India.</span>
    <span class="text-muted">HTML · CSS · Bootstrap 5 · PHP · MySQL · LeafletJS</span>
  </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<!-- MarkerCluster (for many map markers) -->
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css" />
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css" />
<script src="https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js"></script>
<script>
// Pill toggle for audience (Working Professionals / Students / Co-living)
(function(){
  document.addEventListener('DOMContentLoaded', function () {
    const pill = document.querySelector('.pill-toggle');
    if (!pill) return;
    const buttons = Array.from(pill.querySelectorAll('button'));
    const hidden = document.getElementById('audience');
    const mapValue = {0: 'working', 1: 'students', 2: 'coliving'};

    function setActive(index) {
      buttons.forEach((b, i) => b.classList.toggle('active', i === index));
      if (hidden) hidden.value = mapValue[index] || 'working';
    }

    buttons.forEach((btn, idx) => {
      btn.addEventListener('click', function () { setActive(idx); });
      btn.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); setActive(idx); } });
    });

    // initialize from URL param 'audience' if present (scoped var name)
    const audienceParams = new URLSearchParams(window.location.search);
    const a = audienceParams.get('audience');
    if (a) {
      const idx = Object.values(mapValue).indexOf(a);
      if (idx >= 0) setActive(idx);
    } else {
      // default: first button active
      setActive(0);
    }
  });
})();
</script>
<script>
  // Initialize map once
  const map = L.map('pg-map').setView([20.5937, 78.9629], 5);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '© OpenStreetMap contributors'
  }).addTo(map);

  // Static city markers
  const cities = [
    { coords: [19.0760, 72.8777], label: 'Mumbai · 200+ PGs' },
    { coords: [12.9716, 77.5946], label: 'Bangalore · 300+ PGs' },
    { coords: [28.6139, 77.2090], label: 'Delhi · 250+ PGs' },
    { coords: [9.9312, 76.2673],  label: 'Kochi · 80+ PGs' }
  ];

  cities.forEach(c => {
    L.marker(c.coords).addTo(map).bindPopup(`<strong>${c.label}</strong>`);
  });

  // Load PG markers from backend and add to map
  // helper: create a white+blue divIcon
  const blueIcon = L.divIcon({
    className: 'pg-marker',
    html: '<div style="background:#fff;border:2px solid #2563eb;border-radius:14px;padding:6px 8px;color:#2563eb;font-weight:700;box-shadow:0 6px 14px rgba(37,99,235,0.14)">PG</div>',
    iconSize: [36, 36],
    iconAnchor: [18, 36]
  });

  // map intro control
  const intro = L.control({position: 'topright'});
  intro.onAdd = function () {
    const div = L.DomUtil.create('div', 'map-intro card p-2 shadow-sm');
    div.innerHTML = '<small><strong>PGConnect Map</strong><br>Showing PGs across India. Click markers to preview.</small>';
    return div;
  };
  intro.addTo(map);

  // use marker clustering
  let markersLayer = L.markerClusterGroup();
  map.addLayer(markersLayer);

  function updateIntroCount(count) {
    const introEl = document.querySelector('.map-intro');
    if (introEl) {
      introEl.innerHTML = `<small><strong>PGConnect Map</strong><br>Showing <strong>${count}</strong> PGs on map. Click markers to preview.</small>`;
    }
  }

  const BASE_URL = '<?php echo BASE_URL; ?>';
  function loadPgs(lat, lng, radius) {
    let url = 'backend/getPgs.php';
    if (typeof lat !== 'undefined' && lat !== null && typeof lng !== 'undefined' && lng !== null) {
      url += `?lat=${encodeURIComponent(lat)}&lng=${encodeURIComponent(lng)}&radius=${encodeURIComponent(radius||5)}`;
    }

    // clear UI state
    markersLayer.clearLayers();
    const panel = document.getElementById('nearby-results');
    if (panel) panel.innerHTML = '<div class="text-muted">Loading nearby PGs...</div>';

    fetch(url)
      .then(res => {
        if (!res.ok) return res.json().then(j => { throw j; });
        return res.json();
      })
      .then(pgs => {
        if (!Array.isArray(pgs)) {
          if (pgs && pgs.error) throw new Error(pgs.error || 'Invalid response');
          throw new Error('Invalid response from server');
        }

        pgs.forEach(pg => {
          const plat = parseFloat(pg.latitude);
          const plng = parseFloat(pg.longitude);
          if (!isFinite(plat) || !isFinite(plng)) return;
          const marker = L.marker([plat, plng], {icon: blueIcon});
          // attach pg id so we can find this marker later
          marker.options._pgid = pg.id;
          let popupHtml = `<div style="min-width:200px">`;
          if (pg.cover_image) {
            let img = pg.cover_image;
            if (!img.match(/^(https?:\/\/|\/)/)) img = BASE_URL + '/' + img;
            else if (img.startsWith('/') && !img.startsWith(BASE_URL + '/')) img = BASE_URL + img;
            popupHtml += `<img src="${img}" alt="thumb" style="width:100%;height:90px;object-fit:cover;border-radius:6px;margin-bottom:6px">`;
          }
          popupHtml += `<strong>${pg.pg_name}</strong><br>${pg.location||''}, ${pg.city||''}<br><strong>₹${pg.rent||''}</strong>/month`;
          popupHtml += `<div class="mt-2"><a class="btn btn-sm btn-outline-primary" href="user/pg-detail.php?id=${pg.id}">View details</a></div></div>`;
          marker.bindPopup(popupHtml);
          markersLayer.addLayer(marker);
        });
        updateIntroCount(markersLayer.getLayers().length);

        // if this was a nearby call (lat provided), highlight top N nearest PGs and provide CTAs
        if (typeof lat !== 'undefined' && lat !== null) {
          const topN = 6; // show up to 6 nearby PGs
          const nearest = pgs.slice(0, topN);
          // build list HTML
          if (panel) {
            if (nearest.length === 0) {
              panel.innerHTML = '<div class="alert alert-info">No nearby PGs found within the selected radius.</div>';
              // Add Search Nearby CTA (paged results)
              const searchBtn = document.createElement('a');
              searchBtn.className = 'btn btn-sm btn-primary mt-2';
              searchBtn.href = `user/search.php?lat=${encodeURIComponent(lat)}&lng=${encodeURIComponent(lng)}&radius=${encodeURIComponent(radius)}`;
              searchBtn.textContent = 'Search nearby results';
              panel.appendChild(searchBtn);

              // Show seed button only when server indicates DEV_MODE
              fetch('backend/dev_mode.php').then(r => r.json()).then(j => {
                if (j && j.dev_mode) {
                  const seedBtn = document.createElement('button');
                  seedBtn.className = 'btn btn-sm btn-outline-secondary mt-2 ms-2';
                  seedBtn.textContent = 'Seed demo PGs near me (6)';
                  seedBtn.addEventListener('click', function () { runSeedNearby(lat, lng, radius || 2); });
                  panel.appendChild(seedBtn);
                }
              }).catch(()=>{});
            } else {
              let html = '<div class="card p-2 shadow-sm"><strong>Nearest PGs</strong><div class="list-group mt-2">';
              nearest.forEach(n => {
                let img = n.cover_image || '';
                if (img && !img.match(/^(https?:\/\/|\/)/)) img = BASE_URL + '/' + img;
                else if (img && img.startsWith('/') && !img.startsWith(BASE_URL + '/')) img = BASE_URL + img;
                const imgTag = img ? `<img src="${img}" style="width:64px;height:48px;object-fit:cover;border-radius:6px;margin-right:8px">` : '';
                html += `<a class="list-group-item list-group-item-action d-flex align-items-center" href="user/pg-detail.php?id=${n.id}">`;
                html += imgTag + `<div><strong>${n.pg_name}</strong><div class="small text-muted">${n.location||''}, ${n.city||''} • ₹${n.rent||''}</div></div></a>`;
              });
              html += '</div></div>';
              // add show all in radius link (paged search)
              const showAllLink = ` <div class="mt-2 text-end"><a class="btn btn-sm btn-primary" href="user/search.php?lat=${encodeURIComponent(lat)}&lng=${encodeURIComponent(lng)}&radius=${encodeURIComponent(radius)}">Show all in radius</a></div>`;
              panel.innerHTML = html + showAllLink;
            }
          }

          // open popups for the top N markers and pan to the first one
          if (nearest.length > 0) {
            const first = nearest[0];
            // find markers in the cluster layer with matching pg id
            const layers = markersLayer.getLayers();
            let firstMarker = null;
            layers.forEach(m => {
              if (m.options && m.options._pgid && nearest.find(x => x.id == m.options._pgid)) {
                // open popup for matched markers
                if (!firstMarker) firstMarker = m;
                m.openPopup();
              }
            });
            if (firstMarker) map.setView(firstMarker.getLatLng(), 14);
          }
        }
      })
      .catch(err => {
        console.error('PG load error', err);
        const panel = document.getElementById('nearby-results');
        if (panel) panel.innerHTML = '<div class="alert alert-danger">Unable to load nearby PGs. Please try again later.</div>';
      });
  }

  // helper to run dev seeding near a coordinate (calls backend/seed_pgs.php)
  function runSeedNearby(lat, lng, radiusKm, count = 6) {
    const panel = document.getElementById('nearby-results');
    // disable any seed buttons to avoid double clicks
    const seedButtons = Array.from(document.querySelectorAll('button')).filter(b => b.textContent && b.textContent.includes('Seed demo PGs'));
    seedButtons.forEach(b => b.disabled = true);

    if (panel) panel.innerHTML = '<div class="alert alert-info">Seeding demo PGs nearby. This may take a few seconds...</div>';
    const url = `backend/seed_pgs.php?count=${encodeURIComponent(count)}&center=${encodeURIComponent(lat + ',' + lng)}&radius_km=${encodeURIComponent(radiusKm || 2)}`;
    fetch(url).then(r => r.text()).then(text => {
      if (panel) panel.innerHTML = '<div class="alert alert-success">Seed complete. Refreshing nearby results...</div>';
      // small delay so DB has finished inserts (helps on slow local setups)
      setTimeout(() => {
        loadPgs(lat, lng, radiusKm);
        refreshFavCount();
        loadFavoriteIds();
        seedButtons.forEach(b => b.disabled = false);
      }, 800);
    }).catch(err => {
      console.error('Seed error', err);
      if (panel) panel.innerHTML = '<div class="alert alert-danger">Seeding failed. See console for details.</div>';
      seedButtons.forEach(b => b.disabled = false);
    });
  }

  // initial load: many markers across India
  loadPgs();

  // Smooth scrolling for anchor links
  document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
      e.preventDefault();
      document.querySelector(this.getAttribute('href')).scrollIntoView({ behavior: 'smooth' });
    });
  });

  // Navbar scroll effect
  window.addEventListener('scroll', () => {
    const navbar = document.querySelector('.navbar');
    navbar.style.background = window.scrollY > 50 ? 'rgba(255,255,255,0.95)' : 'rgba(255,255,255,0.9)';
  });

  // Map budget select to hidden min/max inputs before submitting
  const heroForm = document.querySelector('.search-card form');
  if (heroForm) {
    heroForm.addEventListener('submit', (e) => {
      const budget = heroForm.querySelector('select[name="budget"]').value;
      const minInput = document.getElementById('min_rent');
      const maxInput = document.getElementById('max_rent');
      minInput.value = '';
      maxInput.value = '';
      if (budget) {
        const parts = budget.split('-');
        if (parts.length === 2) {
          if (parts[0]) minInput.value = parts[0];
          if (parts[1]) maxInput.value = parts[1];
        }
      }
      // allow normal GET submit to user/pg-listings.php
    });

    // Prefill hero form from URL params if present
    const params = new URLSearchParams(window.location.search);
    if (params.has('city')) {
      heroForm.querySelector('input[name="city"]').value = params.get('city');
    }
    if (params.has('sharing')) {
      const s = params.get('sharing');
      const opt = heroForm.querySelector(`select[name="sharing"] option[value="${s}"]`);
      if (opt) opt.selected = true;
    }
    // Map min_rent/max_rent back to a budget option if both present
    if (params.has('min_rent') || params.has('max_rent')) {
      const min = params.get('min_rent') || '';
      const max = params.get('max_rent') || '';
      const budgetVal = `${min}-${max}`;
      const bOpt = heroForm.querySelector(`select[name="budget"] option[value="${budgetVal}"]`);
      if (bOpt) bOpt.selected = true;
    }
  }

  // Nearby me button: use Geolocation API and redirect to search.php with lat/lng
  const nearbyBtn = document.getElementById('nearbyBtn');
  if (nearbyBtn) {
    nearbyBtn.addEventListener('click', () => {
      if (!navigator.geolocation) {
        alert('Geolocation is not supported by your browser');
        return;
      }
      nearbyBtn.disabled = true;
      nearbyBtn.innerText = 'Locating...';
      navigator.geolocation.getCurrentPosition((pos) => {
        const lat = pos.coords.latitude;
        const lng = pos.coords.longitude;
        // read selected radius from selector
        const radiusSel = document.getElementById('radiusSelect');
        const radius = radiusSel ? parseFloat(radiusSel.value) : 5;
        // center map and place a marker
        map.setView([lat, lng], 13);
        L.circle([lat, lng], {radius: radius * 1000, color:'#2563eb', fillOpacity:0.06}).addTo(map);
        loadPgs(lat, lng, radius);
        nearbyBtn.disabled = false;
        nearbyBtn.innerText = 'Nearby me';
      }, (err) => {
        nearbyBtn.disabled = false;
        nearbyBtn.innerText = 'Nearby me';
        alert('Unable to retrieve your location');
      }, { enableHighAccuracy: true, timeout: 10000 });
    });
  }

  // fetch favorite IDs for marking saved markers and update fav badge
  let favoriteIds = [];
  function refreshFavCount() {
    fetch('backend/fav_count.php').then(r => r.json()).then(j => {
      const badge = document.getElementById('favCountBadge');
      if (badge) badge.innerText = j.count || 0;
    }).catch(()=>{});
  }

  function loadFavoriteIds() {
    fetch('backend/getFavorites.php').then(r => r.json()).then(j => {
      if (j && j.ok) {
        favoriteIds = j.favorites.map(f => f.id);
        // decorate markers with saved badge (simple approach: change popup content to include Saved)
        markersLayer.getLayers().forEach(m => {
          if (m.options && m.options._pgid && favoriteIds.includes(m.options._pgid)) {
            const old = m.getPopup() && m.getPopup().getContent();
            if (old && !old.includes('Saved')) {
              m.bindPopup(old + '<div class="mt-2"><span class="badge bg-danger">Saved</span></div>');
            }
          }
        });
      }
    }).catch(()=>{});
  }

  // initial badge refresh
  refreshFavCount();
  // if logged in, load favorite ids to mark markers
  loadFavoriteIds();
</script>

</body>
</html>
