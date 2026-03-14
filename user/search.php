<?php
require_once '../includes/header.php';
require_once '../backend/connect.php';
require_once '../backend/config.php';
require_once '../backend/booking_schema.php';
require_once '../backend/reviews_schema.php';
require_once '../backend/feature_schema.php';

// Debugging helpers: log incoming requests and optionally enable display_errors via ?debug=1
$debugLog = __DIR__ . '/../backend/search_debug.log';
function safe_log($path, $msg) {
    $dir = dirname($path);
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    // If directory isn't writable, skip logging to avoid fatal errors.
    if (!is_writable($dir)) return;
    if (!file_exists($path)) @file_put_contents($path, '');
    if (is_writable($path)) {
        @file_put_contents($path, $msg, FILE_APPEND);
    }
}

function filter_params_for_sql($sql, $params) {
    if (empty($params)) return [];
    if (!preg_match_all('/:([a-zA-Z_][a-zA-Z0-9_]*)/', $sql, $m)) return [];
    $keys = array_flip($m[1]);
    return array_intersect_key($params, $keys);
}

function exec_stmt($pdo, $sql, $params) {
    $stmt = $pdo->prepare($sql);
    $filtered = filter_params_for_sql($sql, $params);
    try {
        $stmt->execute($filtered);
    } catch (Exception $e) {
        safe_log(__DIR__ . '/../backend/search_errors.log', date('Y-m-d H:i:s') . " SQL_ERROR: " . $e->getMessage() . "\nSQL: " . $sql . "\nPARAMS: " . json_encode($filtered) . "\n");
        throw $e;
    }
    return $stmt;
}
safe_log($debugLog, date('Y-m-d H:i:s') . " REQUEST: " . json_encode($_REQUEST) . "\n");
if (isset($_GET['debug']) && $_GET['debug']) {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
    safe_log($debugLog, date('Y-m-d H:i:s') . " DEBUG enabled\n");
}

// Pagination settings
$limit = 6;  // PGs per page
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Get filters (trim inputs)
$city = isset($_GET['city']) ? trim($_GET['city']) : '';
$min_rent = isset($_GET['min_rent']) && $_GET['min_rent'] !== '' ? (int)$_GET['min_rent'] : '';
$max_rent = isset($_GET['max_rent']) && $_GET['max_rent'] !== '' ? (int)$_GET['max_rent'] : '';
$sharing = isset($_GET['sharing']) ? trim($_GET['sharing']) : '';

// Support a single "budget" GET param coming from index (e.g. "5000-10000" or "20000-")
if ((empty($min_rent) && empty($max_rent)) && !empty($_GET['budget'])) {
    $budget = trim($_GET['budget']);
    if (preg_match('/^(\d+)-(\d*)$/', $budget, $m)) {
        $min_rent = $m[1] !== '' ? (int)$m[1] : '';
        $max_rent = $m[2] !== '' ? (int)$m[2] : '';
    }
}

// Build WHERE clause safely with named parameters
$statusWhere = (defined('SHOW_PENDING_LISTINGS') && SHOW_PENDING_LISTINGS) ? "IN ('approved','pending')" : "= 'approved'";
$where = "WHERE (p.status $statusWhere";
$params = [];
// If the current viewer is an owner, allow them to see their own listings (even if not approved)
if (isset($_SESSION['user_id']) && (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'owner')) {
    $viewerOwner = (int)$_SESSION['user_id'];
    $where .= " OR p.owner_id = :viewer_owner";
    $params['viewer_owner'] = $viewerOwner;
}
$where .= ")";
if ($city !== '') {
    // match across many text fields (case-insensitive substring match)
    $cityLike = '%' . mb_strtolower($city, 'UTF-8') . '%';
    $fields = ['city','address','pg_name','pg_code','location_area','district','state'];
    $ors = [];
    foreach ($fields as $i => $field) {
        $ph = 'city_' . $i;
        $ors[] = "LOWER(p.$field) LIKE :$ph";
        $params[$ph] = $cityLike;
    }
    $where .= " AND (" . implode(' OR ', $ors) . ")";
}
if ($min_rent !== '') {
    $where .= " AND p.monthly_rent >= :min_rent";
    $params['min_rent'] = $min_rent;
}
if ($max_rent !== '') {
    $where .= " AND p.monthly_rent <= :max_rent";
    $params['max_rent'] = $max_rent;
}
if ($sharing !== '') {
    $where .= " AND p.sharing_type = :sharing";
    $params['sharing'] = $sharing;
}

// Optional proximity filter
$lat = isset($_GET['lat']) && is_numeric($_GET['lat']) ? (float)$_GET['lat'] : null;
$lng = isset($_GET['lng']) && is_numeric($_GET['lng']) ? (float)$_GET['lng'] : null;
$radius = isset($_GET['radius']) && is_numeric($_GET['radius']) ? (float)$_GET['radius'] : null;

if ($lat !== null && ($lat < -90 || $lat > 90)) $lat = null;
if ($lng !== null && ($lng < -180 || $lng > 180)) $lng = null;

if (isset($_SESSION['user_id']) && ($_SESSION['user_role'] ?? '') === 'user') {
    ensure_feature_schema($pdo);
    if (isset($_GET['save_search']) && $_GET['save_search'] == '1') {
        $ins = $pdo->prepare('INSERT INTO saved_searches (user_id, city, min_rent, max_rent, sharing, is_active, last_match_count) VALUES (?, ?, ?, ?, ?, 1, 0)');
        $ins->execute([
            (int)$_SESSION['user_id'],
            $city !== '' ? $city : null,
            $min_rent !== '' ? (int)$min_rent : null,
            $max_rent !== '' ? (int)$max_rent : null,
            $sharing !== '' ? $sharing : null
        ]);
        $_SESSION['search_saved_flash'] = 'Search alert saved.';
        header('Location: search.php?city=' . urlencode($city) . '&min_rent=' . urlencode((string)$min_rent) . '&max_rent=' . urlencode((string)$max_rent) . '&sharing=' . urlencode($sharing));
        exit;
    }
}

// Count total results and fetch rows, wrapped in try/catch to avoid white pages on DB errors
try {
    // Count total results
    $count_sql = "SELECT COUNT(*) as total FROM pg_listings p $where";
    $count_stmt = exec_stmt($pdo, $count_sql, $params);
    $total_rows = (int)$count_stmt->fetchColumn();
    $total_pages = $total_rows > 0 ? (int)ceil($total_rows / $limit) : 1;

    // Get paginated results — support ordering by distance if lat/lng provided
    if ($lat !== null && $lng !== null) {
        $radius = $radius ?? 5.0; // default radius
        // compute distance using Haversine and filter using HAVING
        $sql = "SELECT p.id, p.pg_name, p.city, p.monthly_rent, p.capacity, p.sharing_type,
                       (SELECT image_path FROM pg_images WHERE pg_id = p.id LIMIT 1) AS cover_image,
                       (6371 * acos(
                           cos(radians(:lat)) * cos(radians(p.latitude)) * cos(radians(p.longitude) - radians(:lng)) +
                           sin(radians(:lat)) * sin(radians(p.latitude))
                       )) AS distance
                FROM pg_listings p $where
                HAVING distance <= :radius
                ORDER BY distance ASC
                LIMIT $offset, $limit";

        $params['lat'] = $lat;
        $params['lng'] = $lng;
        $params['radius'] = $radius;
        $stmt = exec_stmt($pdo, $sql, $params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $sql = "SELECT p.id, p.pg_name, p.city, p.monthly_rent, p.capacity, p.sharing_type,
                   (SELECT image_path FROM pg_images WHERE pg_id = p.id LIMIT 1) AS cover_image,
                   (SELECT AVG(rating) FROM reviews r WHERE r.pg_id = p.id) AS avg_rating
                FROM pg_listings p $where ORDER BY p.created_at DESC LIMIT $offset, $limit";
        $stmt = exec_stmt($pdo, $sql, $params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    // Log error and show friendly message instead of blank page
    safe_log(__DIR__ . '/../backend/search_errors.log', date('Y-m-d H:i:s') . " " . $e->getMessage() . "\n");
    $rows = [];
    $total_rows = 0;
    $total_pages = 1;
    $error = 'No results found or server error. Please try a different location.';
}

// Fallback: if no rows found and user provided a city/location query, try tokenized matching
if (empty($rows) && !empty($city)) {
    try {
        // split city input into tokens (words/numbers), ignore short tokens
        $parts = preg_split('/[^\p{L}\p{N}]+/u', $city);
        $tokens = array_values(array_filter(array_map('trim', $parts), function($t){ return mb_strlen($t, 'UTF-8') >= 2; }));
        if (!empty($tokens)) {
            // build tokenized WHERE clause: each token must be present in any of the searchable fields
            $tokenConds = [];
            $paramsToken = [];
            foreach ($tokens as $i => $tok) {
                $tokLike = '%' . mb_strtolower($tok, 'UTF-8') . '%';
                $fields = ['city','address','pg_name','pg_code','location_area','district','state'];
                $ors = [];
                foreach ($fields as $j => $field) {
                    $ph = 't' . $i . '_' . $j;
                    $ors[] = "LOWER(p.$field) LIKE :$ph";
                    $paramsToken[$ph] = $tokLike;
                }
                $tokenConds[] = "(" . implode(' OR ', $ors) . ")";
            }
            // Allow owners to see their own pending listings too
            $tokenWhere = "WHERE (p.status $statusWhere";
            if (isset($viewerOwner)) {
                $tokenWhere .= " OR p.owner_id = :viewer_owner";
                // ensure param is present for token query
                $paramsToken['viewer_owner'] = $viewerOwner;
            }
            $tokenWhere .= ") AND " . implode(' AND ', $tokenConds);

            // If proximity filter present, include distance computation and HAVING clause
            if ($lat !== null && $lng !== null) {
                $radius = $radius ?? 5.0;
                $sqlToken = "SELECT p.id, p.pg_name, p.city, p.monthly_rent, p.capacity, p.sharing_type,
                               (SELECT image_path FROM pg_images WHERE pg_id = p.id LIMIT 1) AS cover_image,
                               (6371 * acos(
                                   cos(radians(:lat)) * cos(radians(p.latitude)) * cos(radians(p.longitude) - radians(:lng)) +
                                   sin(radians(:lat)) * sin(radians(p.latitude))
                               )) AS distance
                        FROM pg_listings p $tokenWhere
                        HAVING distance <= :radius
                        ORDER BY distance ASC
                        LIMIT $offset, $limit";
                // merge params
                $paramsToken['lat'] = $lat;
                $paramsToken['lng'] = $lng;
                $paramsToken['radius'] = $radius;
                $stmt2 = exec_stmt($pdo, $sqlToken, $paramsToken);
                $rows2 = $stmt2->fetchAll(PDO::FETCH_ASSOC);
            } else {
                $sqlToken = "SELECT p.id, p.pg_name, p.city, p.monthly_rent, p.capacity, p.sharing_type,
                               (SELECT image_path FROM pg_images WHERE pg_id = p.id LIMIT 1) AS cover_image,
                               (SELECT AVG(rating) FROM reviews r WHERE r.pg_id = p.id) AS avg_rating
                        FROM pg_listings p $tokenWhere
                        ORDER BY p.created_at DESC LIMIT $offset, $limit";
                $stmt2 = exec_stmt($pdo, $sqlToken, $paramsToken);
                $rows2 = $stmt2->fetchAll(PDO::FETCH_ASSOC);
            }

            if (!empty($rows2)) {
                $rows = $rows2;
                $total_rows = count($rows2);
                $total_pages = max(1, (int)ceil($total_rows / $limit));
                // clear any previous error so the results show
                $error = '';
            }
        }
    } catch (Exception $e) {
        // ignore fallback errors
        safe_log(__DIR__ . '/../backend/search_errors.log', date('Y-m-d H:i:s') . " FALLBACK_ERROR: " . $e->getMessage() . "\n");
    }
}

// Prepare suggested cities if no results
$suggestedCities = [];
if (empty($rows)) {
    try {
        if (!empty($city)) {
            $sstmt = $pdo->prepare('SELECT DISTINCT city, COUNT(*) as cnt FROM pg_listings WHERE status ' . $statusWhere . ' AND LOWER(city) LIKE :q GROUP BY city ORDER BY cnt DESC LIMIT 5');
            $sstmt->execute([':q' => '%' . mb_strtolower($city, 'UTF-8') . '%']);
            $suggestedCities = $sstmt->fetchAll(PDO::FETCH_ASSOC);
        }
        if (empty($suggestedCities)) {
            $sstmt = $pdo->prepare('SELECT city, COUNT(*) as cnt FROM pg_listings WHERE status ' . $statusWhere . ' GROUP BY city ORDER BY cnt DESC LIMIT 5');
            $sstmt->execute();
            $suggestedCities = $sstmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Exception $e) {
        // ignore suggestion errors
    }
}

// If user is logged in, fetch their favorite pg ids and booking statuses to mark in results
$favIds = [];
$bookingByPg = [];
if (isset($_SESSION['user_id'])) {
    try {
        $fstmt = $pdo->prepare('SELECT pg_id FROM favorites WHERE user_id = ?');
        $fstmt->execute([$_SESSION['user_id']]);
        $favIds = $fstmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {
        // favorites table might not exist; ignore to keep search working
        $favIds = [];
    }
    try {
        ensure_bookings_schema($pdo);
        $bstmt = $pdo->prepare('SELECT pg_id, status FROM bookings WHERE user_id = ? ORDER BY created_at DESC');
        $bstmt->execute([$_SESSION['user_id']]);
        foreach ($bstmt->fetchAll(PDO::FETCH_ASSOC) as $b) {
            if (!isset($bookingByPg[$b['pg_id']])) {
                $bookingByPg[$b['pg_id']] = $b['status'];
            }
        }
    } catch (Exception $e) {
        $bookingByPg = [];
    }
}
?>

<section class="section-shell">
  <div class="container py-5">
    <?php if (!empty($_SESSION['search_saved_flash'])): ?>
      <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['search_saved_flash']); unset($_SESSION['search_saved_flash']); ?></div>
    <?php endif; ?>
    <?php if (!isset($_SESSION['user_id'])): ?>
      <div class="alert alert-warning mb-4">
        Please <a href="<?php echo BASE_URL; ?>/backend/login.php">login</a> or
        <a href="<?php echo BASE_URL; ?>/backend/signup.php">sign up</a> to save PGs, compare, or make booking requests.
      </div>
    <?php endif; ?>
    <!-- Search form -->
    <div class="row mb-5">
        <div class="col-12">
            <form method="GET" class="card shadow-sm">
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">City / Area / PG</label>
                            <input list="citySuggestions" id="cityInput" type="text" class="form-control" name="city" 
                                   value="<?php echo htmlspecialchars($city); ?>" placeholder="Bengaluru or Area or PG name">
                            <datalist id="citySuggestions"></datalist>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Min Rent</label>
                            <input type="number" class="form-control" name="min_rent" 
                                   value="<?php echo htmlspecialchars($min_rent); ?>" placeholder="5000">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Max Rent</label>
                            <input type="number" class="form-control" name="max_rent" 
                                   value="<?php echo htmlspecialchars($max_rent); ?>" placeholder="15000">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Sharing</label>
                            <select class="form-select" name="sharing">
                                <option value="">All</option>
                                <option value="single" <?php echo $sharing=='single'?'selected':'';?>>Single</option>
                                <option value="double" <?php echo $sharing=='double'?'selected':'';?>>Double</option>
                                <option value="triple" <?php echo $sharing=='triple'?'selected':'';?>>Triple</option>
                            </select>
                        </div>
                        <div class="col-md-1">
                            <label class="form-label">&nbsp;</label>
                            <button type="submit" class="btn btn-primary w-100">Search</button>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">&nbsp;</label>
                            <a href="search.php" class="btn btn-outline-secondary w-100">Clear</a>
                        </div>
                        <?php if (isset($_SESSION['user_id']) && ($_SESSION['user_role'] ?? '') === 'user'): ?>
                        <div class="col-md-2">
                            <label class="form-label">&nbsp;</label>
                            <a href="search.php?city=<?php echo urlencode($city); ?>&min_rent=<?php echo urlencode((string)$min_rent); ?>&max_rent=<?php echo urlencode((string)$max_rent); ?>&sharing=<?php echo urlencode($sharing); ?>&save_search=1" class="btn btn-outline-success w-100">Save Alert</a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Results summary -->
    <div class="row mb-4">
        <div class="col">
            <h2 class="h4 mb-2">
                <?php echo $total_rows; ?> PG<?php echo $total_rows==1?'':'s'; ?> 
                <?php echo $city||$min_rent||$max_rent||$sharing ? 'match your search' : 'available'; ?>
            </h2>
            <?php if ($total_pages > 1): ?>
                <p class="text-muted mb-0">
                    Page <?php echo $page; ?> of <?php echo $total_pages; ?> 
                    (<?php echo $limit; ?> per page)
                </p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Results -->
    <?php if (!empty($rows)): ?>
        <div class="row g-4 mb-5">
            <?php foreach ($rows as $row): 
                $img = $row['cover_image'] ?: '';
                $fallback = pg_fallback_image((int)$row['id']);
                if (empty($img)) {
                    $img = $fallback;
                } else {
                    $img = pg_image_url($img, $fallback);
                }
            ?>
                <div class="col-lg-4 col-md-6">
                    <div class="card h-100 shadow-sm border-0 position-relative">
                        <?php $isFav = in_array($row['id'], $favIds); ?>
                        <button class="fav-btn fav-heart-btn <?php echo $isFav ? 'active' : ''; ?>" data-pg="<?php echo $row['id']; ?>" aria-label="Save PG">
                            <i class="fa-solid fa-heart"></i>
                        </button>
                        <img src="<?php echo htmlspecialchars($img); ?>" 
                             class="card-img-top" style="height:200px;object-fit:cover;"
                             alt="PG photo">
                        <div class="card-body d-flex flex-column">
                            <h5 class="card-title"><?php echo htmlspecialchars($row['pg_name']); ?></h5>
                            <p class="card-text text-muted"><?php echo htmlspecialchars($row['city']); ?></p>
                            <?php
                              $rating = $row['avg_rating'] ? round((float)$row['avg_rating'], 1) : pg_fallback_rating((int)$row['id']);
                            ?>
                            <div class="mb-2"><span class="rating-badge">★ <?php echo htmlspecialchars($rating); ?></span></div>
                            <p class="card-text"><strong>₹<?php echo number_format($row['monthly_rent']); ?>/month</strong></p>
                            <p class="card-text mb-3">
                                <?php echo htmlspecialchars($row['capacity']); ?> beds • 
                                <span class="badge bg-light text-dark"><?php echo htmlspecialchars(ucfirst($row['sharing_type'])); ?></span>
                            </p>
                            <div class="d-flex gap-2 mt-2">
                                <a href="pg-detail.php?id=<?php echo $row['id']; ?>" class="btn btn-success">View Details</a>
                                <?php if (!empty($bookingByPg[$row['id']])): ?>
                                    <span class="badge bg-warning text-dark align-self-center"><?php echo htmlspecialchars($bookingByPg[$row['id']]); ?></span>
                                <?php endif; ?>
                                <button class="btn btn-outline-secondary compare-btn" data-pg="<?php echo $row['id']; ?>">Compare</button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <nav aria-label="PG listings pagination">
                <ul class="pagination justify-content-center">
                    <!-- First & Previous -->
                    <?php if ($page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=1<?php echo $city?'&city='.urlencode($city):'';?><?php echo $min_rent?'&min_rent='.$min_rent:'';?><?php echo $max_rent?'&max_rent='.$max_rent:'';?><?php echo $sharing?'&sharing='.$sharing:'';?>">First</a>
                        </li>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?php echo $page-1;?><?php echo $city?'&city='.urlencode($city):'';?><?php echo $min_rent?'&min_rent='.$min_rent:'';?><?php echo $max_rent?'&max_rent='.$max_rent:'';?><?php echo $sharing?'&sharing='.$sharing:'';?>">Previous</a>
                        </li>
                    <?php endif; ?>

                    <!-- Page numbers -->
                    <?php 
                    $start = max(1, $page - 2);
                    $end = min($total_pages, $page + 2);
                    for ($i = $start; $i <= $end; $i++): 
                    ?>
                        <li class="page-item <?php echo $i==$page ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i;?><?php echo $city?'&city='.urlencode($city):'';?><?php echo $min_rent?'&min_rent='.$min_rent:'';?><?php echo $max_rent?'&max_rent='.$max_rent:'';?><?php echo $sharing?'&sharing='.$sharing:'';?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>

                    <!-- Next & Last -->
                    <?php if ($page < $total_pages): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?php echo $page+1;?><?php echo $city?'&city='.urlencode($city):'';?><?php echo $min_rent?'&min_rent='.$min_rent:'';?><?php echo $max_rent?'&max_rent='.$max_rent:'';?><?php echo $sharing?'&sharing='.$sharing:'';?>">Next</a>
                        </li>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?php echo $total_pages;?><?php echo $city?'&city='.urlencode($city):'';?><?php echo $min_rent?'&min_rent='.$min_rent:'';?><?php echo $max_rent?'&max_rent='.$max_rent:'';?><?php echo $sharing?'&sharing='.$sharing:'';?>">Last</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
        <?php endif; ?>

    <?php else: ?>
        <div class="alert alert-info text-center py-5">
            <h5><?php echo htmlspecialchars($error ?? 'No PGs match your filters'); ?></h5>
            <p><?php echo isset($error) ? 'Try again later or contact support.' : 'Try different city, price range or sharing type.'; ?></p>
            <?php if (!empty($suggestedCities)): ?>
                <div class="mt-3">
                    <p class="mb-1"><strong>Try these cities instead:</strong></p>
                    <?php foreach ($suggestedCities as $sc): ?>
                        <a href="?city=<?php echo urlencode($sc['city']); ?>" class="btn btn-sm btn-outline-secondary m-1"><?php echo htmlspecialchars($sc['city']); ?> (<?php echo (int)$sc['cnt']; ?>)</a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <div class="mt-3"><a href="search.php" class="btn btn-outline-primary">Clear all filters</a></div>
        </div>
    <?php endif; ?>
  </div>
</section>

<?php require_once '../includes/footer.php'; ?>

<script>
// City/Area/PG suggestions
;(function(){
    const input = document.getElementById('cityInput');
    const list = document.getElementById('citySuggestions');
    let lastQ = '';
    let suggestions = [];

    if (!input) return;

    input.addEventListener('input', function(e){
        const q = input.value.trim();
        if (q.length < 2) { list.innerHTML = ''; return; }
        if (q === lastQ) return;
        lastQ = q;
        fetch('/PGConnect/backend/search_suggestions.php?q=' + encodeURIComponent(q))
            .then(r=>r.json())
            .then(data=>{
                suggestions = (data.suggestions || []);
                list.innerHTML = '';
                suggestions.forEach(s => {
                    const opt = document.createElement('option');
                    opt.value = s.label;
                    // we attach metadata on option element as data attributes
                    if (s.type === 'pg') opt.setAttribute('data-pg-id', s.id);
                    opt.setAttribute('data-type', s.type);
                    list.appendChild(opt);
                });
            }).catch(()=>{});
    });

    // When user presses Enter or blurs the input, try to find a PG suggestion whose label contains
    // the typed value (case-insensitive). This allows partial matches and case differences.
    function navigateIfPg() {
        const val = input.value.trim();
        if (!val) return;
        const q = val.toLowerCase();
        // prefer exact match, then startsWith, then contains
        let matched = suggestions.find(s => s.type === 'pg' && s.label.toLowerCase() === q);
        if (!matched) matched = suggestions.find(s => s.type === 'pg' && s.label.toLowerCase().startsWith(q));
        if (!matched) matched = suggestions.find(s => s.type === 'pg' && s.label.toLowerCase().includes(q));
        if (matched) {
            window.location.href = '/PGConnect/user/pg-detail.php?id=' + matched.id;
        }
    }

    input.addEventListener('change', navigateIfPg);
    input.addEventListener('keydown', function(e){ if (e.key === 'Enter') navigateIfPg(); });
})();
</script>
