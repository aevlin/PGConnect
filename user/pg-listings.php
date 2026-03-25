<?php
require_once '../includes/header.php';
require_once '../backend/connect.php';
require_once '../backend/config.php';
require_once '../backend/favorites_schema.php';
require_once '../backend/reviews_schema.php';
require_once '../backend/trust.php';
require_once '../backend/user_schema.php';

ensure_user_profile_schema($pdo);

$city = trim((string)($_GET['city'] ?? ''));
$minRent = isset($_GET['min_rent']) && $_GET['min_rent'] !== '' ? max(0, (int)$_GET['min_rent']) : null;
$maxRent = isset($_GET['max_rent']) && $_GET['max_rent'] !== '' ? max(0, (int)$_GET['max_rent']) : null;
$sharing = trim((string)($_GET['sharing'] ?? ''));
$occupancyType = trim((string)($_GET['occupancy_type'] ?? ''));
$amenity = trim((string)($_GET['amenity'] ?? ''));
$verifiedOnly = !empty($_GET['verified_only']);
$availableOnly = !empty($_GET['available_only']);
$lat = isset($_GET['lat']) && is_numeric($_GET['lat']) ? (float)$_GET['lat'] : null;
$lng = isset($_GET['lng']) && is_numeric($_GET['lng']) ? (float)$_GET['lng'] : null;
$radius = isset($_GET['radius']) && is_numeric($_GET['radius']) ? max(1, min(100, (int)$_GET['radius'])) : 25;
$allowedPerPage = [12, 24, 48, 96];
$perPage = isset($_GET['per_page']) && in_array((int)$_GET['per_page'], $allowedPerPage, true) ? (int)$_GET['per_page'] : 12;
$page = isset($_GET['page']) && (int)$_GET['page'] > 0 ? (int)$_GET['page'] : 1;
$sort = trim((string)($_GET['sort'] ?? 'latest'));
if ($lat !== null && $lng !== null && $sort === 'latest') {
    $sort = 'distance';
}

$statusWhere = (defined('SHOW_PENDING_LISTINGS') && SHOW_PENDING_LISTINGS) ? "IN ('approved','pending')" : "= 'approved'";
$where = ["p.status $statusWhere"];
$params = [];

if ($city !== '') {
    $like = '%' . mb_strtolower($city, 'UTF-8') . '%';
    $where[] = "(LOWER(p.city) LIKE :q_city OR LOWER(p.location_area) LIKE :q_area OR LOWER(p.pg_name) LIKE :q_name OR LOWER(p.address) LIKE :q_address)";
    $params['q_city'] = $like;
    $params['q_area'] = $like;
    $params['q_name'] = $like;
    $params['q_address'] = $like;
}
if ($minRent !== null) {
    $where[] = "p.monthly_rent >= :min_rent";
    $params['min_rent'] = $minRent;
}
if ($maxRent !== null) {
    $where[] = "p.monthly_rent <= :max_rent";
    $params['max_rent'] = $maxRent;
}
if ($sharing !== '') {
    $where[] = "p.sharing_type = :sharing";
    $params['sharing'] = $sharing;
}
if ($occupancyType !== '') {
    $where[] = "p.occupancy_type = :occupancy_type";
    $params['occupancy_type'] = $occupancyType;
}
if ($amenity !== '') {
    $where[] = "LOWER(COALESCE(p.amenities, '')) LIKE :amenity";
    $params['amenity'] = '%' . mb_strtolower($amenity, 'UTF-8') . '%';
}
if ($verifiedOnly) {
    $where[] = "COALESCE(u.owner_verification_status, 'pending') = 'verified'";
}
if ($availableOnly) {
    $where[] = "COALESCE(p.available_beds, p.capacity, 0) > 0";
    $where[] = "COALESCE(p.occupancy_status, 'available') <> 'full'";
}

$orderBy = "p.created_at DESC";
if ($sort === 'rent_low') $orderBy = "p.monthly_rent ASC";
if ($sort === 'rent_high') $orderBy = "p.monthly_rent DESC";
if ($sort === 'distance' && $lat !== null && $lng !== null) $orderBy = "distance_km ASC";

$rows = [];
$allRows = [];
$listError = '';
$distanceSelect = $lat !== null && $lng !== null
    ? "(6371 * ACOS(
            COS(RADIANS(:user_lat_a)) * COS(RADIANS(p.latitude)) *
            COS(RADIANS(p.longitude) - RADIANS(:user_lng_a)) +
            SIN(RADIANS(:user_lat_b)) * SIN(RADIANS(p.latitude))
       ))"
    : "NULL";
$sql = "SELECT p.id, p.pg_name, p.city, p.location_area, p.address, p.monthly_rent, p.capacity, p.available_beds, p.sharing_type,
               p.occupancy_type, p.occupancy_status, p.amenities, p.latitude, p.longitude,
               COALESCE(u.owner_verification_status, 'pending') AS owner_verification_status,
               $distanceSelect AS distance_km,
               (SELECT image_path FROM pg_images WHERE pg_id = p.id LIMIT 1) AS cover_image
        FROM pg_listings p
        LEFT JOIN users u ON u.id = p.owner_id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY $orderBy";
try {
    $stmt = $pdo->prepare($sql);
    if ($lat !== null && $lng !== null) {
        $params['user_lat_a'] = $lat;
        $params['user_lat_b'] = $lat;
        $params['user_lng_a'] = $lng;
    }
    $stmt->execute($params);
    $allRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if ($lat !== null && $lng !== null) {
        $allRows = array_values(array_filter($allRows, static function($row) use ($radius) {
            return !isset($row['distance_km']) || $row['distance_km'] === null || (float)$row['distance_km'] <= $radius;
        }));
        if ($sort === 'distance') {
            usort($allRows, static function($a, $b) {
                return ((float)($a['distance_km'] ?? PHP_INT_MAX)) <=> ((float)($b['distance_km'] ?? PHP_INT_MAX));
            });
        }
    }
} catch (Throwable $e) {
    $listError = 'Unable to load PG listings right now. Please try again.';
    @file_put_contents(__DIR__ . '/../backend/error.log', date('Y-m-d H:i:s') . " PG_LISTINGS ERROR: " . $e->getMessage() . "\n", FILE_APPEND);
}

$totalResults = count($allRows);
$totalPages = max(1, (int)ceil($totalResults / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $perPage;
$rows = array_slice($allRows, $offset, $perPage);

$favIds = [];
if (isset($_SESSION['user_id'])) {
    try {
        ensure_favorites_schema($pdo);
        $f = $pdo->prepare('SELECT pg_id FROM favorites WHERE user_id = ?');
        $f->execute([$_SESSION['user_id']]);
        $favIds = $f->fetchAll(PDO::FETCH_COLUMN);
    } catch (Throwable $e) {
        $favIds = [];
    }
}
?>

<section class="section-shell">
  <div class="container py-4">
    <style>
      .estate-board { border: 1px solid #e5e7eb; border-radius: 28px; background: #fbfbfa; box-shadow: 0 20px 55px rgba(10,20,30,.08); overflow: hidden; }
      .estate-hero { position: relative; padding: 28px; background: linear-gradient(140deg,#2e6ac8 0%,#18202b 58%,#efe7dd 100%); color: #fff; min-height: 220px; }
      .estate-hero h1 { font-size: clamp(1.8rem, 3vw, 2.4rem); font-weight: 700; margin-bottom: 0; }
      .estate-hero .sub { opacity: .9; margin-top: 6px; }
      .estate-search { margin-top: 18px; background: rgba(255,255,255,.95); border-radius: 18px; padding: 14px; }
      .estate-search .form-label { font-size: .78rem; margin-bottom: 4px; color: #4b5563; }
      .estate-body { padding: 22px; background: #f5f6f4; }
      .result-card { border: 1px solid #e7e7e7; border-radius: 16px; background: #fff; padding: 14px; margin-bottom: 14px; box-shadow: 0 8px 18px rgba(17,24,39,.05); }
      .result-card img { width: 145px; height: 102px; object-fit: cover; border-radius: 12px; }
      .result-card .title { font-weight: 700; font-size: 1.05rem; margin-bottom: 2px; }
      .result-card .meta { font-size: .88rem; color: #4b5563; margin-bottom: 4px; }
      .result-actions { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 8px; }
      .map-pane { background: #fff; border: 1px solid #e6e7ea; border-radius: 16px; padding: 12px; box-shadow: 0 12px 24px rgba(17,24,39,.08); position: sticky; top: 96px; }
      #pgListingsMap { height: 540px; border-radius: 12px; overflow: hidden; }
      .chip { font-size: .72rem; border-radius: 999px; border: 1px solid #dbe3f2; background: #eff6ff; color: #1d4ed8; padding: .2rem .55rem; }
      .price { font-weight: 700; font-size: 1.2rem; color: #111827; }
      .toolbar-line { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; }
      .map-note { font-size: .78rem; color: #6b7280; }
      .filter-check {
        display: flex;
        align-items: center;
        gap: 10px;
        background: #f8fafc;
        border: 1px solid #dbe3f2;
        border-radius: 14px;
        padding: 10px 14px;
        min-height: 48px;
      }
      .filter-check .form-check-input {
        margin: 0;
        width: 1.1rem;
        height: 1.1rem;
        border-color: #94a3b8;
      }
      .filter-check .form-check-label {
        margin: 0;
        color: #334155;
        font-size: .95rem;
        font-weight: 600;
        line-height: 1.2;
      }
      .pagination-shell .page-link {
        border-radius: 12px;
        margin: 0 3px;
      }
      @media (max-width: 991.98px) {
        #pgListingsMap { height: 320px; }
        .map-pane { position: static; margin-top: 14px; }
      }
    </style>

    <?php if (!isset($_SESSION['user_id'])): ?>
      <div class="alert alert-warning mb-3">
        Please <a href="<?php echo BASE_URL; ?>/backend/login.php">login</a> or
        <a href="<?php echo BASE_URL; ?>/backend/signup.php">sign up</a> to save PGs, compare, or make booking requests.
      </div>
    <?php endif; ?>

    <div class="estate-board">
      <div class="estate-hero">
        <h1>Search PGs Across India</h1>
        <div class="sub">Find by city, rent, and sharing type with live map preview</div>
        <form method="GET" class="estate-search" id="pgSearchForm">
          <input type="hidden" name="lat" id="searchLat" value="<?php echo htmlspecialchars($lat !== null ? (string)$lat : ''); ?>">
          <input type="hidden" name="lng" id="searchLng" value="<?php echo htmlspecialchars($lng !== null ? (string)$lng : ''); ?>">
          <div class="row g-2 align-items-end">
            <div class="col-lg-3 col-md-6">
              <label class="form-label">City / Area / PG</label>
              <input type="text" class="form-control" name="city" value="<?php echo htmlspecialchars($city); ?>" placeholder="Kochi, Bengaluru, Amala Hostel">
            </div>
            <div class="col-lg-2 col-md-6">
              <label class="form-label">Min rent</label>
              <input type="number" class="form-control" name="min_rent" value="<?php echo htmlspecialchars((string)($minRent ?? '')); ?>" placeholder="5000">
            </div>
            <div class="col-lg-2 col-md-6">
              <label class="form-label">Max rent</label>
              <input type="number" class="form-control" name="max_rent" value="<?php echo htmlspecialchars((string)($maxRent ?? '')); ?>" placeholder="15000">
            </div>
            <div class="col-lg-2 col-md-6">
              <label class="form-label">Sharing</label>
              <select class="form-select" name="sharing">
                <option value="">All</option>
                <option value="single" <?php echo $sharing === 'single' ? 'selected' : ''; ?>>Single</option>
                <option value="double" <?php echo $sharing === 'double' ? 'selected' : ''; ?>>Double</option>
                <option value="triple" <?php echo $sharing === 'triple' ? 'selected' : ''; ?>>Triple</option>
                <option value="other" <?php echo $sharing === 'other' ? 'selected' : ''; ?>>Other</option>
              </select>
            </div>
            <div class="col-lg-2 col-md-6">
              <label class="form-label">Gender / Type</label>
              <select class="form-select" name="occupancy_type">
                <option value="">All</option>
                <option value="boys" <?php echo $occupancyType === 'boys' ? 'selected' : ''; ?>>Boys</option>
                <option value="girls" <?php echo $occupancyType === 'girls' ? 'selected' : ''; ?>>Girls</option>
                <option value="co-ed" <?php echo $occupancyType === 'co-ed' ? 'selected' : ''; ?>>Co-ed</option>
              </select>
            </div>
            <div class="col-lg-2 col-md-6">
              <label class="form-label">Amenity</label>
              <select class="form-select" name="amenity">
                <option value="">Any</option>
                <?php foreach (['wifi' => 'Wi-Fi', 'food' => 'Food', 'ac' => 'AC', 'parking' => 'Parking', 'security' => 'Security'] as $amenityValue => $amenityLabel): ?>
                  <option value="<?php echo htmlspecialchars($amenityValue); ?>" <?php echo $amenity === $amenityValue ? 'selected' : ''; ?>><?php echo htmlspecialchars($amenityLabel); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-lg-2 col-md-6">
              <label class="form-label">Sort by</label>
              <select class="form-select" name="sort">
                <option value="latest" <?php echo $sort === 'latest' ? 'selected' : ''; ?>>Latest</option>
                <option value="rent_low" <?php echo $sort === 'rent_low' ? 'selected' : ''; ?>>Rent low-high</option>
                <option value="rent_high" <?php echo $sort === 'rent_high' ? 'selected' : ''; ?>>Rent high-low</option>
                <option value="distance" <?php echo $sort === 'distance' ? 'selected' : ''; ?>>Nearest first</option>
              </select>
            </div>
          </div>
          <div class="row g-2 align-items-end mt-1">
            <div class="col-lg-2 col-md-6">
              <label class="form-label">Radius (km)</label>
              <input type="number" min="1" max="100" class="form-control" name="radius" value="<?php echo htmlspecialchars((string)$radius); ?>">
            </div>
            <div class="col-lg-2 col-md-6">
              <label class="form-label">PGs per page</label>
              <select class="form-select" name="per_page">
                <?php foreach ($allowedPerPage as $limitOption): ?>
                  <option value="<?php echo (int)$limitOption; ?>" <?php echo $perPage === (int)$limitOption ? 'selected' : ''; ?>><?php echo (int)$limitOption; ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-lg-2 col-md-6">
              <div class="filter-check mt-4">
                <input class="form-check-input" type="checkbox" name="verified_only" id="verifiedOnly" value="1" <?php echo $verifiedOnly ? 'checked' : ''; ?>>
                <label class="form-check-label" for="verifiedOnly">Verified owners only</label>
              </div>
            </div>
            <div class="col-lg-2 col-md-6">
              <div class="filter-check mt-4">
                <input class="form-check-input" type="checkbox" name="available_only" id="availableOnly" value="1" <?php echo $availableOnly ? 'checked' : ''; ?>>
                <label class="form-check-label" for="availableOnly">Available beds only</label>
              </div>
            </div>
            <div class="col-lg-4 col-md-12">
              <div class="toolbar-line mt-3">
                <button class="btn btn-outline-primary btn-sm" id="useCurrentLocationBtn" type="button">Use current location</button>
                <button class="btn btn-outline-secondary btn-sm" id="clearCurrentLocationBtn" type="button">Clear location</button>
                <span class="map-note" id="locationStatus"><?php echo $lat !== null && $lng !== null ? 'Distance search is active.' : 'Add your live location for nearby sorting.'; ?></span>
              </div>
            </div>
            <div class="col-lg-2 col-md-12 d-flex gap-2">
              <button class="btn btn-primary w-100" type="submit">Search</button>
            </div>
          </div>
          <div class="mt-2">
            <a class="btn btn-sm btn-outline-secondary" href="pg-listings.php">Clear</a>
          </div>
        </form>
      </div>

      <div class="estate-body">
        <div class="row g-3">
          <div class="col-lg-7">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <h2 class="h5 mb-0">Best options</h2>
              <span class="text-muted small"><?php echo (int)$totalResults; ?> result(s) · Page <?php echo (int)$page; ?> of <?php echo (int)$totalPages; ?></span>
            </div>

            <?php if ($listError): ?>
              <div class="alert alert-danger"><?php echo htmlspecialchars($listError); ?></div>
            <?php elseif (empty($rows)): ?>
              <div class="alert alert-info">No PGs found for this filter. Try another city or rent range.</div>
            <?php else: ?>
              <?php foreach ($rows as $row): ?>
                <?php
                  $img = $row['cover_image'] ?: '';
                  $fallback = pg_fallback_image((int)$row['id']);
                  $img = $img ? pg_image_url($img, $fallback) : $fallback;
                  $rating = pg_fallback_rating((int)$row['id']);
                  $isFav = in_array($row['id'], $favIds);
                  $lat = is_numeric($row['latitude'] ?? null) ? (float)$row['latitude'] : null;
                  $lng = is_numeric($row['longitude'] ?? null) ? (float)$row['longitude'] : null;
                  $distanceKm = isset($row['distance_km']) && $row['distance_km'] !== null ? round((float)$row['distance_km'], 1) : null;
                ?>
                <article class="result-card">
                  <div class="d-flex gap-3">
                    <img src="<?php echo htmlspecialchars($img); ?>" alt="PG image">
                    <div class="flex-grow-1">
                      <div class="d-flex justify-content-between align-items-start">
                        <div>
                          <div class="title"><?php echo htmlspecialchars($row['pg_name']); ?></div>
                          <div class="meta"><?php echo htmlspecialchars(($row['location_area'] ?: $row['city']) . ', ' . $row['city']); ?></div>
                        </div>
                        <button class="fav-btn fav-heart-btn <?php echo $isFav ? 'active' : ''; ?>" data-pg="<?php echo (int)$row['id']; ?>" aria-label="Save PG">
                          <i class="fa-solid fa-heart"></i>
                        </button>
                      </div>
                      <div class="d-flex gap-2 flex-wrap mb-2">
                        <span class="chip">★ <?php echo htmlspecialchars($rating); ?></span>
                        <span class="chip">Trust <?php echo (int)pg_trust_score($pdo, (int)$row['id']); ?>/100</span>
                        <span class="chip"><?php echo (int)$row['capacity']; ?> beds</span>
                        <span class="chip"><?php echo (int)($row['available_beds'] ?? $row['capacity']); ?> available</span>
                        <span class="chip"><?php echo htmlspecialchars(ucfirst($row['sharing_type'])); ?></span>
                        <span class="chip"><?php echo htmlspecialchars(ucfirst((string)($row['occupancy_type'] ?? 'Co-ed'))); ?></span>
                        <span class="chip"><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', (string)($row['occupancy_status'] ?? 'available')))); ?></span>
                        <?php if (($row['owner_verification_status'] ?? '') === 'verified'): ?>
                          <span class="chip">Verified owner</span>
                        <?php endif; ?>
                        <?php if ($distanceKm !== null): ?>
                          <span class="chip"><?php echo htmlspecialchars((string)$distanceKm); ?> km away</span>
                        <?php endif; ?>
                      </div>
                      <div class="price">₹<?php echo number_format((float)$row['monthly_rent'], 0); ?>/month</div>
                    </div>
                  </div>
                  <div class="result-actions">
                    <a href="pg-detail.php?id=<?php echo (int)$row['id']; ?>" class="btn btn-success btn-sm">View details</a>
                    <button class="btn btn-outline-secondary btn-sm compare-btn" data-pg="<?php echo (int)$row['id']; ?>">Compare</button>
                    <button class="btn btn-outline-primary btn-sm map-focus-btn"
                            data-lat="<?php echo $lat !== null ? htmlspecialchars((string)$lat) : ''; ?>"
                            data-lng="<?php echo $lng !== null ? htmlspecialchars((string)$lng) : ''; ?>"
                            data-name="<?php echo htmlspecialchars($row['pg_name']); ?>">
                      Show on map
                    </button>
                  </div>
                </article>
              <?php endforeach; ?>

              <?php
                $queryBase = $_GET;
                unset($queryBase['page']);
              ?>
              <?php if ($totalPages > 1): ?>
                <nav class="pagination-shell mt-4" aria-label="PG pagination">
                  <ul class="pagination flex-wrap">
                    <?php
                      $prevQuery = $queryBase;
                      $prevQuery['page'] = max(1, $page - 1);
                      $nextQuery = $queryBase;
                      $nextQuery['page'] = min($totalPages, $page + 1);
                    ?>
                    <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                      <a class="page-link" href="?<?php echo htmlspecialchars(http_build_query($prevQuery)); ?>">Previous</a>
                    </li>
                    <?php
                      $startPage = max(1, $page - 2);
                      $endPage = min($totalPages, $page + 2);
                      for ($p = $startPage; $p <= $endPage; $p++):
                        $pageQuery = $queryBase;
                        $pageQuery['page'] = $p;
                    ?>
                      <li class="page-item <?php echo $p === $page ? 'active' : ''; ?>">
                        <a class="page-link" href="?<?php echo htmlspecialchars(http_build_query($pageQuery)); ?>"><?php echo (int)$p; ?></a>
                      </li>
                    <?php endfor; ?>
                    <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                      <a class="page-link" href="?<?php echo htmlspecialchars(http_build_query($nextQuery)); ?>">Next</a>
                    </li>
                  </ul>
                </nav>
              <?php endif; ?>
            <?php endif; ?>
          </div>

          <div class="col-lg-5">
            <aside class="map-pane">
              <div class="d-flex justify-content-between align-items-center mb-2">
                <strong>Map View</strong>
                <button id="fitAllBtn" type="button" class="btn btn-sm btn-primary">Go to map</button>
              </div>
              <div class="toolbar-line mb-2">
                <button class="btn btn-outline-primary btn-sm" id="mapUseCurrentLocationBtn" type="button">Use current location</button>
                <button class="btn btn-outline-secondary btn-sm" id="mapClearCurrentLocationBtn" type="button">Reset nearby</button>
              </div>
              <div id="pgListingsMap"></div>
              <div class="small text-muted mt-2">Click marker to preview and open PG details. Nearby PGs will move to the top when live location is enabled.</div>
            </aside>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<?php
$mapRows = [];
foreach ($rows as $r) {
    if (!is_numeric($r['latitude'] ?? null) || !is_numeric($r['longitude'] ?? null)) continue;
    $img = $r['cover_image'] ?: '';
    $fallback = pg_fallback_image((int)$r['id']);
    $img = $img ? pg_image_url($img, $fallback) : $fallback;
    $mapRows[] = [
        'id' => (int)$r['id'],
        'name' => (string)$r['pg_name'],
        'city' => (string)$r['city'],
        'rent' => (float)$r['monthly_rent'],
        'lat' => (float)$r['latitude'],
        'lng' => (float)$r['longitude'],
        'img' => (string)$img
    ];
}
?>
<?php require_once '../includes/footer.php'; ?>
<script>
(function(){
  let mapInstance = null;
  let userMarker = null;

  function refreshMapSize() {
    if (!mapInstance) return;
    setTimeout(function(){ mapInstance.invalidateSize(); }, 100);
    setTimeout(function(){ mapInstance.invalidateSize(); }, 400);
    setTimeout(function(){ mapInstance.invalidateSize(); }, 900);
  }

  function initPgMap() {
    const mapEl = document.getElementById('pgListingsMap');
    if (!mapEl) return;
    if (typeof L === 'undefined') {
      setTimeout(initPgMap, 120);
      return;
    }
    if (mapInstance) return;

    const pgs = <?php echo json_encode($mapRows, JSON_UNESCAPED_SLASHES); ?> || [];
    const map = L.map(mapEl).setView([20.5937, 78.9629], 5);
    mapInstance = map;
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      maxZoom: 19,
      attribution: '© OpenStreetMap contributors'
    }).addTo(map);

    const markers = [];
    pgs.forEach(function(pg){
      const marker = L.marker([pg.lat, pg.lng]).addTo(map);
      marker.bindPopup(
        '<div style="min-width:180px">' +
        '<img src="' + pg.img + '" style="width:100%;height:80px;object-fit:cover;border-radius:8px;margin-bottom:6px">' +
        '<div><strong>' + pg.name + '</strong></div>' +
        '<div class="small text-muted">' + pg.city + '</div>' +
        '<div><strong>₹' + Number(pg.rent).toLocaleString() + '/month</strong></div>' +
        '<a class="btn btn-sm btn-outline-primary mt-2" href="pg-detail.php?id=' + pg.id + '">View details</a>' +
        '</div>'
      );
      markers.push(marker);
    });

    const fitBtn = document.getElementById('fitAllBtn');
    let group = null;
    if (markers.length) {
      group = L.featureGroup(markers);
      map.fitBounds(group.getBounds().pad(0.2));
    }
    const activeLat = parseFloat(document.getElementById('searchLat')?.value || '');
    const activeLng = parseFloat(document.getElementById('searchLng')?.value || '');
    if (isFinite(activeLat) && isFinite(activeLng)) {
      userMarker = L.circleMarker([activeLat, activeLng], {
        radius: 10,
        color: '#2563eb',
        fillColor: '#60a5fa',
        fillOpacity: 0.95,
        weight: 3
      }).addTo(map).bindPopup('Your current location');
    }
    if (fitBtn) {
      fitBtn.addEventListener('click', function(){
        mapEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
        if (group) {
          map.fitBounds(group.getBounds().pad(0.2));
          if (userMarker) userMarker.bringToFront();
        } else if (userMarker) {
          map.setView(userMarker.getLatLng(), 14);
        } else {
          map.setView([20.5937, 78.9629], 5);
        }
      });
    }

    document.querySelectorAll('.map-focus-btn').forEach(function(btn){
      btn.addEventListener('click', function(){
        const lat = parseFloat(btn.getAttribute('data-lat'));
        const lng = parseFloat(btn.getAttribute('data-lng'));
        if (!isFinite(lat) || !isFinite(lng)) {
          alert('Location not available for this PG.');
          return;
        }
        map.setView([lat, lng], 15);
        let target = null;
        markers.forEach(function(m){
          const ll = m.getLatLng();
          if (Math.abs(ll.lat - lat) < 0.000001 && Math.abs(ll.lng - lng) < 0.000001) target = m;
        });
        if (target) target.openPopup();
      });
    });

    refreshMapSize();
    window.addEventListener('resize', refreshMapSize);
  }

  function initLocationButtons() {
    const useBtn = document.getElementById('useCurrentLocationBtn');
    const mapUseBtn = document.getElementById('mapUseCurrentLocationBtn');
    const clearBtn = document.getElementById('clearCurrentLocationBtn');
    const mapClearBtn = document.getElementById('mapClearCurrentLocationBtn');
    const latInput = document.getElementById('searchLat');
    const lngInput = document.getElementById('searchLng');
    const sortInput = document.querySelector('select[name="sort"]');
    const form = document.getElementById('pgSearchForm');
    const status = document.getElementById('locationStatus');
    const mapEl = document.getElementById('pgListingsMap');

    function applyNearbySearch(latitude, longitude) {
      latInput.value = String(latitude);
      lngInput.value = String(longitude);
      if (sortInput) sortInput.value = 'distance';
      if (status) status.textContent = 'Location found. Loading nearby PGs...';
      if (mapEl) {
        mapEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
      }
      if (form) {
        form.requestSubmit();
      }
    }

    function requestLocation() {
      if (!navigator.geolocation) {
        if (status) status.textContent = 'Geolocation is not supported on this device.';
        return;
      }
      if (status) status.textContent = 'Fetching your location...';
      navigator.geolocation.getCurrentPosition(function(pos){
        applyNearbySearch(pos.coords.latitude, pos.coords.longitude);
      }, function(err){
        if (status) status.textContent = err && err.code === 1
          ? 'Location permission was denied. Please allow access in the browser.'
          : 'Unable to fetch location. Please try again.';
      }, { enableHighAccuracy: true, timeout: 12000, maximumAge: 30000 });
    }

    function clearLocation() {
      latInput.value = '';
      lngInput.value = '';
      if (sortInput && sortInput.value === 'distance') sortInput.value = 'latest';
      if (status) status.textContent = 'Nearby mode cleared.';
      if (form) form.requestSubmit();
    }

    if (useBtn) useBtn.addEventListener('click', requestLocation);
    if (mapUseBtn) mapUseBtn.addEventListener('click', requestLocation);
    if (clearBtn) clearBtn.addEventListener('click', clearLocation);
    if (mapClearBtn) mapClearBtn.addEventListener('click', clearLocation);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function(){ initPgMap(); initLocationButtons(); });
  } else {
    initPgMap();
    initLocationButtons();
  }
})();
</script>
