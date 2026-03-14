<?php 
$page_title = "Add New PG";
require_once '../backend/connect.php';
require_once '../backend/config.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!defined('BASE_URL')) define('BASE_URL', '/PGConnect');

if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'owner') {
  header('Location: ' . BASE_URL . '/backend/login.php');
  exit;
}

$success = $error = $coordNotice = '';
$user_id = $_SESSION['user_id'];
$ownerVerified = true;
try {
  require_once '../backend/user_schema.php';
  ensure_user_profile_schema($pdo);
  $vs = $pdo->prepare('SELECT owner_verification_status FROM users WHERE id = ?');
  $vs->execute([$user_id]);
  $ownerVerified = ($vs->fetchColumn() === 'verified');
} catch (Throwable $e) { $ownerVerified = true; }

// determine if we're editing an existing PG
$edit_id = 0;
if (isset($_GET['id'])) $edit_id = (int)$_GET['id'];
if (isset($_POST['edit_id'])) $edit_id = max($edit_id, (int)$_POST['edit_id']);

// fetch existing record for edit mode (prefill)
$existing = null;
if ($edit_id > 0) {
  $s = $pdo->prepare('SELECT * FROM pg_listings WHERE id = ? AND owner_id = ? LIMIT 1');
  $s->execute([$edit_id, $user_id]);
  $existing = $s->fetch(PDO::FETCH_ASSOC);
  if (!$existing) {
    $error = 'Listing not found or you do not have permission to edit it.';
    $edit_id = 0; // fallback to add mode
  }
}

// load existing images for edit mode
$existingImages = [];
if ($edit_id > 0) {
  $gi = $pdo->prepare('SELECT id, image_path FROM pg_images WHERE pg_id = ? ORDER BY id');
  $gi->execute([$edit_id]);
  $existingImages = $gi->fetchAll(PDO::FETCH_ASSOC);
}

// Handle POST before any output so we can redirect or set headers safely
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  try {
    // debug hook: log POST keys when DEV_MODE enabled
    if (defined('DEV_MODE') && DEV_MODE) {
      @file_put_contents(__DIR__ . '/../backend/error.log', date('Y-m-d H:i:s') . " OWNER-ADD POST: " . json_encode(array_keys($_POST)) . " FILES: " . json_encode(array_keys($_FILES)) . "\n", FILE_APPEND);
    }
    $pg_code = trim($_POST['pg_code'] ?? ($existing['pg_code'] ?? ''));
    $pg_name = trim($_POST['pg_name'] ?? '');
  $district = trim($_POST['district'] ?? '');
  $state = trim($_POST['state'] ?? '');
  $location_area = trim($_POST['location_area'] ?? '');
  $city = trim($_POST['city'] ?? '');
  $address = trim($_POST['address'] ?? '');
  $occupancy_type = trim($_POST['occupancy_type'] ?? 'co-ed');
  $capacity = (int)($_POST['capacity'] ?? 0);
  $available_beds = (int)($_POST['available_beds'] ?? 0);
  $occupancy_status = trim($_POST['occupancy_status'] ?? 'available');
  $monthly_rent = (float)($_POST['monthly_rent'] ?? 0);
  $amenities = trim($_POST['amenities'] ?? '');
  $sharing_type = strtolower(trim($_POST['sharing_type'] ?? ($existing['sharing_type'] ?? '')));
  $allowed_sharing = ['single','double','triple'];
  if ($sharing_type === '' || !in_array($sharing_type, $allowed_sharing, true)) {
    $sharing_type = 'single';
  }
  $latRaw = trim((string)($_POST['latitude'] ?? ''));
  $lngRaw = trim((string)($_POST['longitude'] ?? ''));
  $latitude = ($latRaw === '') ? null : (float)$latRaw;
  $longitude = ($lngRaw === '') ? null : (float)$lngRaw;

  // Auto-correct common Google Maps paste mistake: longitude,latitude entered as latitude,longitude
  if ($latitude !== null && $longitude !== null) {
    $shouldSwap = false;
    // Generic geo-range hint: latitude cannot exceed +/-90, longitude can be +/-180
    if (($latitude < -90 || $latitude > 90) && $longitude >= -90 && $longitude <= 90 && $latitude >= -180 && $latitude <= 180) {
      $shouldSwap = true;
    }
    // India-specific hint: if values look like [lng, lat] such as 76.x, 9.x
    if (!$shouldSwap && $latitude >= 68 && $latitude <= 97 && $longitude >= 6 && $longitude <= 37) {
      $shouldSwap = true;
    }
    if ($shouldSwap) {
      $tmp = $latitude;
      $latitude = $longitude;
      $longitude = $tmp;
      $coordNotice = 'Latitude/Longitude looked reversed, so we auto-corrected them for you.';
    }
  }
  $postedEditId = isset($_POST['edit_id']) ? (int)$_POST['edit_id'] : 0;
  $isEdit = $postedEditId > 0 || $edit_id > 0;
  if ($postedEditId > 0) $edit_id = $postedEditId;
    
  // validate required fields
  $requiredMissing = [];
  foreach (['pg_code' => $pg_code, 'pg_name' => $pg_name, 'district' => $district, 'state' => $state, 'location_area' => $location_area, 'city' => $city, 'address' => $address, 'occupancy_type' => $occupancy_type, 'capacity' => $capacity, 'available_beds' => $available_beds, 'occupancy_status' => $occupancy_status, 'monthly_rent' => $monthly_rent] as $k => $v) {
    if ($v === '' || $v === null || (is_numeric($v) && $v === 0 && in_array($k, ['capacity','available_beds']))) {
      $requiredMissing[] = $k;
    }
  }

  if (!empty($requiredMissing)) {
    $error = 'Missing required fields: ' . implode(', ', $requiredMissing);
  } elseif ($monthly_rent < 100) {
    $error = 'Invalid rent amount';
  } elseif ($capacity < 1) {
    $error = 'Capacity must be at least 1';
  } elseif ($available_beds < 0 || $available_beds > $capacity) {
    $error = 'Available beds must be between 0 and total capacity';
  } elseif (($latitude !== null && ($latitude < -90 || $latitude > 90)) || ($longitude !== null && ($longitude < -180 || $longitude > 180))) {
    $error = 'Invalid latitude/longitude values.';
  } elseif (($latitude === null) xor ($longitude === null)) {
    $error = 'Please provide both latitude and longitude, or leave both blank.';
  } else {
    // Insert or update PG listing (starts as pending for admin approval on new)
    if ($isEdit && $edit_id > 0) {
      // ensure owner owns this listing
      $u = $pdo->prepare('SELECT id FROM pg_listings WHERE id = ? AND owner_id = ? LIMIT 1');
      $u->execute([$edit_id, $user_id]);
      if (!$u->fetchColumn()) {
        $error = 'Invalid edit id or permission denied';
      } else {
        $upd = $pdo->prepare("UPDATE pg_listings SET pg_code = ?, pg_name = ?, district = ?, state = ?, location_area = ?, city = ?, address = ?, occupancy_type = ?, capacity = ?, available_beds = ?, occupancy_status = ?, monthly_rent = ?, amenities = ?, sharing_type = ?, latitude = ?, longitude = ? WHERE id = ? AND owner_id = ?");
        $ok = $upd->execute([$pg_code, $pg_name, $district, $state, $location_area, $city, $address, $occupancy_type, $capacity, $available_beds, $occupancy_status, $monthly_rent, $amenities, $sharing_type, $latitude, $longitude, $edit_id, $user_id]);
        if ($ok) {
          $pg_id = $edit_id;
          $success = 'PG updated successfully.';
          if ($coordNotice !== '') $success .= ' ' . $coordNotice;
        } else {
          $error = 'Failed to update PG.';
        }
      }
    } else {
      $newStatus = (defined('AUTO_APPROVE_LISTINGS') && AUTO_APPROVE_LISTINGS) ? 'approved' : 'pending';
      if (!$ownerVerified) $newStatus = 'pending';
      $stmt = $pdo->prepare("INSERT INTO pg_listings (owner_id, pg_code, pg_name, district, state, location_area, city, address, occupancy_type, capacity, available_beds, occupancy_status, monthly_rent, amenities, sharing_type, latitude, longitude, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
      if ($stmt->execute([$user_id, $pg_code, $pg_name, $district, $state, $location_area, $city, $address, $occupancy_type, $capacity, $available_beds, $occupancy_status, $monthly_rent, $amenities, $sharing_type, $latitude, $longitude, $newStatus])) {
        $pg_id = $pdo->lastInsertId();
        $success = 'PG added successfully! Awaiting admin approval.';
        if ($coordNotice !== '') $success .= ' ' . $coordNotice;
      } else {
        $error = 'Failed to add PG. Try again.';
      }
    }

    // handle delete requests for existing images (edit mode)
    if (!empty($_POST['delete_images']) && is_array($_POST['delete_images']) && $pg_id) {
      foreach ($_POST['delete_images'] as $delId) {
        $delId = (int)$delId;
        if ($delId <= 0) continue;
        try {
          $s = $pdo->prepare('SELECT image_path FROM pg_images WHERE id = ? AND pg_id = ? LIMIT 1');
          $s->execute([$delId, $pg_id]);
          $r = $s->fetch(PDO::FETCH_ASSOC);
          if ($r) {
            $path = __DIR__ . '/../' . ltrim($r['image_path'], '/');
            @unlink($path);
            $d = $pdo->prepare('DELETE FROM pg_images WHERE id = ? AND pg_id = ?');
            $d->execute([$delId, $pg_id]);
          }
        } catch (Exception $e) { /* ignore */ }
      }
    }

    // Process uploaded images (optional)
      $imageErrors = [];
      if (!empty($_FILES['images']) && is_array($_FILES['images']['name'])) {
        $allowed = ['jpg','jpeg','png','gif'];
        $maxFiles = 5;
        $maxSize = 5 * 1024 * 1024; // 5MB
        $count = count(array_filter($_FILES['images']['name']));
        $toProcess = min($count, $maxFiles);

        $uploadDir = __DIR__ . '/../uploads/';
        if (!is_dir($uploadDir)) {
          mkdir($uploadDir, 0755, true);
        }

        for ($i = 0, $saved = 0; $i < $toProcess; $i++) {
          $name = $_FILES['images']['name'][$i];
          $tmp = $_FILES['images']['tmp_name'][$i];
          $err = $_FILES['images']['error'][$i];
          $size = $_FILES['images']['size'][$i];

          if ($err !== UPLOAD_ERR_OK) {
            $imageErrors[] = "$name: upload error code $err";
            continue;
          }
          if ($size > $maxSize) {
            $imageErrors[] = "$name: exceeds max size of 5MB";
            continue;
          }
          $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
          if (!in_array($ext, $allowed)) {
            $imageErrors[] = "$name: invalid file type";
            continue;
          }

          // Generate unique filename
          $safeName = preg_replace('/[^A-Za-z0-9_\-\.]/', '_', pathinfo($name, PATHINFO_FILENAME));
          $filename = sprintf('pg_%s_%s.%s', $pg_id, uniqid(), $ext);
          $target = $uploadDir . $filename;

          if (is_uploaded_file($tmp) && move_uploaded_file($tmp, $target)) {
            // Insert record into pg_images table
            try {
              $webPath = 'uploads/' . $filename; // store path relative to project root
              $ins = $pdo->prepare("INSERT INTO pg_images (pg_id, image_path) VALUES (?, ?)");
              $ins->execute([$pg_id, $webPath]);
              $saved++;
            } catch (Exception $e) {
              // rollback file if DB insert fails
              @unlink($target);
              $imageErrors[] = "$name: failed to save image record";
            }
          } else {
            $imageErrors[] = "$name: failed to move uploaded file";
          }
        }
      }
    if (!empty($imageErrors)) {
      // don't overwrite success message for update
      $error = (!empty($error) ? $error . ' | ' : '') . 'Some images failed: ' . implode('; ', $imageErrors);
    }
    // clear POST for niceness if create succeeded
    if (empty($error)) {
      $_POST = [];
      // redirect to listing page so user sees result and avoid accidental resubmit
      if (isset($pg_id) && $pg_id) {
        header('Location: owner-pg-list.php?success=1');
      } else {
        header('Location: owner-dashboard.php?success=1');
      }
      exit;
    }
  }
  } catch (Throwable $t) {
    // log and show an error so the page doesn't go blank
    $msg = date('Y-m-d H:i:s') . ' owner-add-pg error: ' . $t->getMessage() . "\n" . $t->getTraceAsString() . "\n";
    @file_put_contents(__DIR__ . '/../backend/error.log', $msg, FILE_APPEND);
    $error = 'Server error occurred while saving the listing. Please try again or contact support.';
  }
}
?>

<?php require_once '../includes/header.php'; ?>

<!-- Hero Section (same design as index.html) -->
  <div class="container">
    <div class="row g-4 align-items-center">
      <div class="col-lg-8">
        <div class="eyebrow">
          <span></span> Add New Property • Step 1/2
        </div>
        <h1 class="hero-title">
          List your <span>PG accommodation</span>
        </h1>
        <p class="hero-subtitle mb-0">
          Add your property details. Our team will verify and approve within 24 hours.
        </p>
      </div>
      <div class="col-lg-4 text-center">
        <div class="hero-photo" style="height: 200px;">
          <img src="https://images.pexels.com/photos/1454806/pexels-photo-1454806.jpeg?auto=compress&cs=tinysrgb&w=400" alt="PG listing preview">
        </div>
      </div>
    </div>
  </div>
</section>

<!-- Form Section (same .section-shell design) -->
<section class="section-shell">
  <div class="container">
    <div class="row justify-content-center">
      <div class="col-lg-10 col-xl-8">
        
        <?php if ($success): ?>
          <div class="alert alert-success text-center py-5 mb-5 rounded-3">
            <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
            <h4><?php echo htmlspecialchars($success); ?></h4>
            <a href="owner-dashboard.php" class="btn btn-gradient px-4">View Dashboard</a>
            <a href="owner-add-pg.php" class="btn btn-outline-primary px-4 ms-2">Add Another PG</a>
          </div>
        <?php endif; ?>

        <?php if ($error): ?>
          <div class="alert alert-danger rounded-3"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if ($coordNotice && !$success): ?>
          <div class="alert alert-info rounded-3"><?php echo htmlspecialchars($coordNotice); ?></div>
        <?php endif; ?>

        <?php if (!$success): ?>
        <div class="card border-0 shadow-lg">
          <div class="card-body p-5">
            <form method="POST" enctype="multipart/form-data">
              <?php if ($edit_id > 0): ?>
                <input type="hidden" name="edit_id" value="<?php echo (int)$edit_id; ?>">
              <?php endif; ?>
              <div class="row g-4">
                <!-- Basic Info -->
                <div class="col-md-4">
                  <label class="form-label fw-semibold mb-2">PG ID / Code *</label>
        <input type="text" name="pg_code" class="form-control form-control-lg" 
          value="<?php echo htmlspecialchars($_POST['pg_code'] ?? $existing['pg_code'] ?? ''); ?>" placeholder="PG-1234" required>
                </div>
                <div class="col-md-4">
                  <label class="form-label fw-semibold mb-2">PG Name *</label>
        <input type="text" name="pg_name" class="form-control form-control-lg" 
          value="<?php echo htmlspecialchars($_POST['pg_name'] ?? $existing['pg_name'] ?? ''); ?>" placeholder="Green Nest PG" required>
                </div>
                <div class="col-md-4">
                  <label class="form-label fw-semibold mb-2">City *</label>
        <input type="text" name="city" class="form-control form-control-lg" 
          value="<?php echo htmlspecialchars($_POST['city'] ?? $existing['city'] ?? ''); ?>" placeholder="Bengaluru, Mumbai..." required>
                </div>

                <div class="col-md-4">
                  <label class="form-label fw-semibold mb-2">District *</label>
                  <input type="text" name="district" class="form-control form-control-lg" value="<?php echo htmlspecialchars($_POST['district'] ?? $existing['district'] ?? ''); ?>" required>
                </div>
                <div class="col-md-4">
                  <label class="form-label fw-semibold mb-2">State *</label>
                  <input type="text" name="state" class="form-control form-control-lg" value="<?php echo htmlspecialchars($_POST['state'] ?? $existing['state'] ?? ''); ?>" required>
                </div>
                <div class="col-md-4">
                  <label class="form-label fw-semibold mb-2">Location / Area *</label>
                  <input type="text" name="location_area" class="form-control form-control-lg" value="<?php echo htmlspecialchars($_POST['location_area'] ?? $existing['location_area'] ?? ''); ?>" required>
                </div>

                <!-- Address & Pricing -->
                <div class="col-12">
                  <label class="form-label fw-semibold mb-2">Full Address *</label>
        <input type="text" name="address" class="form-control form-control-lg" 
          value="<?php echo htmlspecialchars($_POST['address'] ?? $existing['address'] ?? ''); ?>" placeholder="HSR Layout, Sector 2, Near Forum Mall" required>
                </div>
                <div class="col-md-4">
                  <label class="form-label fw-semibold mb-2">Monthly Rent (₹) *</label>
        <input type="number" name="monthly_rent" class="form-control form-control-lg" 
          value="<?php echo htmlspecialchars($_POST['monthly_rent'] ?? $existing['monthly_rent'] ?? ''); ?>" min="100" placeholder="8500" required>
                </div>
                <div class="col-md-4">
                  <label class="form-label fw-semibold mb-2">Total Capacity *</label>
        <input type="number" name="capacity" class="form-control form-control-lg" 
          value="<?php echo htmlspecialchars($_POST['capacity'] ?? $existing['capacity'] ?? ''); ?>" min="1" max="1000" placeholder="30" required>
                </div>
                <div class="col-md-4">
                  <label class="form-label fw-semibold mb-2">Available Beds *</label>
        <input type="number" name="available_beds" class="form-control form-control-lg" 
          value="<?php echo htmlspecialchars($_POST['available_beds'] ?? $existing['available_beds'] ?? ''); ?>" min="0" max="1000" placeholder="10" required>
                </div>

                <div class="col-md-4">
                  <label class="form-label fw-semibold mb-2">Occupancy Type *</label>
                  <select name="occupancy_type" class="form-select form-select-lg" required>
                    <option value="boys" <?php echo (($_POST['occupancy_type'] ?? $existing['occupancy_type'] ?? '') == 'boys') ? 'selected' : ''; ?>>Boys</option>
                    <option value="girls" <?php echo (($_POST['occupancy_type'] ?? $existing['occupancy_type'] ?? '') == 'girls') ? 'selected' : ''; ?>>Girls</option>
                    <option value="co-ed" <?php echo (($_POST['occupancy_type'] ?? $existing['occupancy_type'] ?? '') == 'co-ed' || !isset($_POST['occupancy_type']) && empty($existing)) ? 'selected' : ''; ?>>Co-ed</option>
                  </select>
                </div>
                <div class="col-md-4">
                  <label class="form-label fw-semibold mb-2">Occupancy Status *</label>
                  <select name="occupancy_status" class="form-select form-select-lg" required>
                    <option value="available" <?php echo (($_POST['occupancy_status'] ?? $existing['occupancy_status'] ?? '') == 'available') ? 'selected' : ''; ?>>Available</option>
                    <option value="filling_fast" <?php echo (($_POST['occupancy_status'] ?? $existing['occupancy_status'] ?? '') == 'filling_fast') ? 'selected' : ''; ?>>Filling Fast</option>
                    <option value="full" <?php echo (($_POST['occupancy_status'] ?? $existing['occupancy_status'] ?? '') == 'full') ? 'selected' : ''; ?>>Full</option>
                  </select>
                </div>
                <div class="col-md-4">
                  <label class="form-label fw-semibold mb-2">Sharing Type *</label>
                  <select name="sharing_type" class="form-select form-select-lg" required>
                    <option value="single" <?php echo (($_POST['sharing_type'] ?? $existing['sharing_type'] ?? '') == 'single') ? 'selected' : ''; ?>>Single</option>
                    <option value="double" <?php echo (($_POST['sharing_type'] ?? $existing['sharing_type'] ?? '') == 'double') ? 'selected' : ''; ?>>Double</option>
                    <option value="triple" <?php echo (($_POST['sharing_type'] ?? $existing['sharing_type'] ?? '') == 'triple') ? 'selected' : ''; ?>>Triple</option>
                  </select>
                </div>
                <div class="col-md-4">
                  <label class="form-label fw-semibold mb-2">Amenities (comma separated)</label>
                  <input type="text" name="amenities" class="form-control form-control-lg" value="<?php echo htmlspecialchars($_POST['amenities'] ?? $existing['amenities'] ?? ''); ?>" placeholder="WiFi, Meals, Laundry">
                </div>

                <!-- Location -->
                <div class="col-md-6">
                  <label class="form-label fw-semibold mb-2">Latitude</label>
        <input type="number" step="any" name="latitude" id="pgLatitude" class="form-control form-control-lg" 
          value="<?php echo htmlspecialchars($_POST['latitude'] ?? $existing['latitude'] ?? ''); ?>" placeholder="12.911000">
                  <small class="text-muted">Click map below to auto-fill</small>
                </div>
                <div class="col-md-6">
                  <label class="form-label fw-semibold mb-2">Longitude</label>
        <input type="number" step="any" name="longitude" id="pgLongitude" class="form-control form-control-lg" 
          value="<?php echo htmlspecialchars($_POST['longitude'] ?? $existing['longitude'] ?? ''); ?>" placeholder="77.641000">
                  <small class="text-muted">Click map below to auto-fill</small>
                </div>
                <div class="col-12">
                  <label class="form-label fw-semibold mb-2">Pick Location on Map</label>
                  <div id="ownerLocationMap" style="height:280px;border:1px solid #d1d5db;border-radius:14px;overflow:hidden;"></div>
                  <small class="text-muted">Tip: Click on the exact PG position, or drag the marker.</small>
                </div>

                <!-- Images -->
                <div class="col-12">
                  <label class="form-label fw-semibold mb-2">Photos (optional, up to 5 images)</label>
                  <input type="file" name="images[]" class="form-control" accept="image/*" multiple>
                  <small class="text-muted">Tip: Upload up to 5 photos. Max 5MB per image.</small>
                </div>

                <?php if (!empty($existingImages)): ?>
                  <div class="col-12">
                    <label class="form-label fw-semibold mt-3">Existing photos</label>
                    <div class="d-flex flex-wrap gap-2">
                      <?php foreach ($existingImages as $ei):
                        $ip = $ei['image_path'];
                        if (!preg_match('#^(https?://|/)#', $ip)) $ip = '/' . ltrim($ip, '/');
                      ?>
                      <div style="width:110px;">
                        <img src="<?php echo htmlspecialchars($ip); ?>" style="width:100%;height:70px;object-fit:cover;border-radius:6px;" alt="img">
                        <div class="form-check mt-1 small">
                          <input class="form-check-input" type="checkbox" name="delete_images[]" value="<?php echo (int)$ei['id']; ?>" id="delimg<?php echo (int)$ei['id']; ?>">
                          <label class="form-check-label" for="delimg<?php echo (int)$ei['id']; ?>">Delete</label>
                        </div>
                      </div>
                      <?php endforeach; ?>
                    </div>
                  </div>
                <?php endif; ?>

                <!-- Submit -->
                <div class="col-12">
                  <div class="d-grid">
                    <button type="submit" class="btn btn-gradient btn-lg py-3 fw-bold rounded-3">
                      <i class="fas fa-plus-circle me-2"></i>
                      <?php echo $edit_id > 0 ? 'Update PG' : 'List PG for Approval'; ?>
                    </button>
                  </div>
                  <div class="text-center mt-4">
                    <a href="owner-dashboard.php" class="btn btn-outline-secondary px-4">
                      ← Back to Dashboard
                    </a>
                  </div>
                </div>
              </div>
            </form>
          </div>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>

<?php require_once '../includes/footer.php'; ?>
<script>
(function(){
  const latInput = document.getElementById('pgLatitude');
  const lngInput = document.getElementById('pgLongitude');
  const mapEl = document.getElementById('ownerLocationMap');
  if (!latInput || !lngInput || !mapEl || typeof L === 'undefined') return;

  let lat = parseFloat(latInput.value);
  let lng = parseFloat(lngInput.value);
  if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
    // default India center
    lat = 20.5937;
    lng = 78.9629;
  }
  const hasExact = Number.isFinite(parseFloat(latInput.value)) && Number.isFinite(parseFloat(lngInput.value));
  const zoom = hasExact ? 15 : 5;

  const map = L.map('ownerLocationMap').setView([lat, lng], zoom);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '&copy; OpenStreetMap contributors'
  }).addTo(map);

  const marker = L.marker([lat, lng], { draggable: true }).addTo(map);

  function setLatLng(newLat, newLng) {
    latInput.value = Number(newLat).toFixed(6);
    lngInput.value = Number(newLng).toFixed(6);
    marker.setLatLng([newLat, newLng]);
  }

  map.on('click', function(e){
    setLatLng(e.latlng.lat, e.latlng.lng);
  });

  marker.on('dragend', function(e){
    const p = e.target.getLatLng();
    setLatLng(p.lat, p.lng);
  });

  function syncFromInputs() {
    const iLat = parseFloat(latInput.value);
    const iLng = parseFloat(lngInput.value);
    if (!Number.isFinite(iLat) || !Number.isFinite(iLng)) return;
    marker.setLatLng([iLat, iLng]);
    map.panTo([iLat, iLng]);
  }

  latInput.addEventListener('change', syncFromInputs);
  lngInput.addEventListener('change', syncFromInputs);

  setTimeout(() => map.invalidateSize(), 200);
})();
</script>
