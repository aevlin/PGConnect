<?php
require_once '../backend/connect.php';
require_once '../backend/booking_schema.php';
require_once '../backend/system_schema.php';
require_once '../backend/notify.php';
require_once '../backend/audit.php';
require_once '../backend/auth.php';
require_role('user');

ensure_bookings_schema($pdo);
ensure_system_schema($pdo);
$userId = (int)$_SESSION['user_id'];
$bookingId = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : (int)($_POST['booking_id'] ?? 0);
if ($bookingId <= 0) {
    header('Location: booking-request.php'); exit;
}

$stmt = $pdo->prepare('SELECT b.*, p.pg_name, p.monthly_rent, p.id AS listing_id FROM bookings b JOIN pg_listings p ON p.id = b.pg_id WHERE b.id = ? AND b.user_id = ? LIMIT 1');
$stmt->execute([$bookingId, $userId]);
$booking = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$booking) {
    header('Location: booking-request.php'); exit;
}

$alert = '';
$paymentSuccess = false;
$paymentSuccessMessage = '';
$paymentRefValue = '';
if (!isset($_SESSION['payment_otp']) || !isset($_SESSION['payment_otp_booking']) || (int)$_SESSION['payment_otp_booking'] !== (int)$bookingId) {
    $_SESSION['payment_otp'] = (string)random_int(100000, 999999);
    $_SESSION['payment_otp_booking'] = (int)$bookingId;
}
$otpHint = $_SESSION['payment_otp'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $otp = trim($_POST['otp'] ?? '');
    if ($otp === '' || !isset($_SESSION['payment_otp']) || $otp !== (string)$_SESSION['payment_otp']) {
        $alert = '<div class="alert alert-danger">Invalid OTP. Please enter the OTP sent to your phone.</div>';
    } elseif (!in_array($booking['status'], ['payment_pending','owner_approved'], true)) {
        $alert = '<div class="alert alert-warning">This booking is not ready for payment.</div>';
    } else {
        $paymentRef = 'PGP-' . strtoupper(substr(md5($bookingId . '-' . time()), 0, 10));
        $pdo->beginTransaction();
        try {
            $upd = $pdo->prepare('UPDATE bookings SET status = ?, payment_status = ?, payment_ref = ?, paid_at = NOW() WHERE id = ?');
            $upd->execute(['paid', 'paid', $paymentRef, $bookingId]);

            $dec = $pdo->prepare('UPDATE pg_listings SET available_beds = GREATEST(available_beds - 1, 0) WHERE id = ?');
            $dec->execute([(int)$booking['pg_id']]);

            $st = $pdo->prepare('SELECT available_beds FROM pg_listings WHERE id = ?');
            $st->execute([(int)$booking['pg_id']]);
            $avail = (int)$st->fetchColumn();
            $occ = $avail <= 0 ? 'full' : ($avail <= 2 ? 'filling_fast' : 'available');
            $pdo->prepare('UPDATE pg_listings SET occupancy_status = ? WHERE id = ?')->execute([$occ, (int)$booking['pg_id']]);
            $pdo->commit();

            unset($_SESSION['payment_otp'], $_SESSION['payment_otp_booking']);
            $_SESSION['payment_success_message'] = 'Payment successful. Welcome to your new PG at ' . ($booking['pg_name'] ?? 'PG') . '.';
            $_SESSION['payment_success_booking_id'] = (int)$bookingId;
            $paymentSuccess = true;
            $paymentSuccessMessage = $_SESSION['payment_success_message'];
            $paymentRefValue = $paymentRef;

            // notify owner + admins
            try {
                $ow = $pdo->prepare('SELECT owner_id FROM pg_listings WHERE id = ? LIMIT 1');
                $ow->execute([(int)$booking['pg_id']]);
                $ownerId = (int)$ow->fetchColumn();
                if ($ownerId > 0) {
                    notify_user($pdo, $ownerId, 'owner', 'Payment received', "User completed payment for booking #{$bookingId}.", base_url('owner/owner-bookings.php'));
                }
                $admins = $pdo->query("SELECT id FROM users WHERE role = 'admin'")->fetchAll(PDO::FETCH_COLUMN);
                foreach ($admins as $aid) {
                    notify_user($pdo, (int)$aid, 'admin', 'Payment completed', "Booking #{$bookingId} has been paid.", base_url('admin/admin-bookings.php'));
                }
            } catch (Throwable $e) {}
            audit_log($pdo, 'payment_success', 'booking', (int)$bookingId, 'ref=' . $paymentRef);

            @file_put_contents(__DIR__ . '/../backend/booking_notifications.log', date('Y-m-d H:i:s') . " PAYMENT_SUCCESS: booking_id={$bookingId} ref={$paymentRef}\n", FILE_APPEND);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $alert = '<div class="alert alert-danger">Payment failed. Please try again.</div>';
        }
    }
}

require_once '../includes/header.php';
?>

<div class="container py-5">
  <div class="row justify-content-center">
    <div class="col-lg-6">
      <?php if ($paymentSuccess): ?>
      <style>
        .payment-success-shell {
          position: relative;
          overflow: hidden;
          border-radius: 24px;
          background: linear-gradient(135deg, #ecfdf5 0%, #eff6ff 100%);
          border: 1px solid #bfdbfe;
          box-shadow: 0 22px 60px rgba(15, 23, 42, 0.12);
        }
        .payment-success-glow {
          position: absolute;
          inset: -30% auto auto -10%;
          width: 260px;
          height: 260px;
          background: radial-gradient(circle, rgba(34,197,94,0.26) 0%, rgba(34,197,94,0) 70%);
          pointer-events: none;
        }
        .payment-check {
          width: 90px;
          height: 90px;
          border-radius: 999px;
          display: inline-flex;
          align-items: center;
          justify-content: center;
          background: linear-gradient(135deg, #22c55e, #2563eb);
          color: #fff;
          font-size: 2rem;
          box-shadow: 0 18px 36px rgba(37,99,235,.24);
          animation: paymentPop .65s ease-out both;
        }
        .payment-progress {
          height: 8px;
          border-radius: 999px;
          background: #dbeafe;
          overflow: hidden;
        }
        .payment-progress > span {
          display: block;
          height: 100%;
          width: 100%;
          background: linear-gradient(90deg, #22c55e, #2563eb);
          transform-origin: left center;
          animation: paymentCountdown 3.2s linear forwards;
        }
        @keyframes paymentPop {
          0% { transform: scale(.55); opacity: 0; }
          70% { transform: scale(1.08); opacity: 1; }
          100% { transform: scale(1); opacity: 1; }
        }
        @keyframes paymentCountdown {
          from { transform: scaleX(1); }
          to { transform: scaleX(0); }
        }
      </style>
      <div class="card border-0 payment-success-shell">
        <div class="payment-success-glow"></div>
        <div class="card-body p-5 text-center position-relative">
          <div class="payment-check mb-4"><i class="fa-solid fa-check"></i></div>
          <h1 class="h3 mb-2">Payment successful</h1>
          <p class="text-muted mb-3"><?php echo htmlspecialchars($paymentSuccessMessage); ?></p>
          <div class="small text-muted mb-1">Booking #<?php echo (int)$booking['id']; ?> · Ref <?php echo htmlspecialchars($paymentRefValue); ?></div>
          <div class="small text-muted mb-4">Redirecting to your bookings in a moment...</div>
          <div class="payment-progress mb-4"><span></span></div>
          <div class="d-flex justify-content-center gap-2 flex-wrap">
            <a class="btn btn-primary" href="booking-request.php">Open bookings</a>
            <a class="btn btn-outline-secondary" href="receipt.php?booking_id=<?php echo (int)$booking['id']; ?>">View receipt</a>
          </div>
        </div>
      </div>
      <script>
      (function(){
        setTimeout(function(){
          window.location.href = 'booking-request.php';
        }, 3200);
      })();
      </script>
      <?php else: ?>
      <div class="card border-0 shadow-sm">
        <div class="card-body p-4">
          <h1 class="h4 mb-1">Complete payment</h1>
          <p class="small text-muted mb-4">Booking #<?php echo (int)$booking['id']; ?> for <?php echo htmlspecialchars($booking['pg_name']); ?></p>
          <?php echo $alert; ?>
          <div class="mb-3">
            <div class="small text-muted">Move-in date</div>
            <div class="fw-semibold"><?php echo htmlspecialchars($booking['move_in_date'] ?: '-'); ?></div>
          </div>
          <div class="mb-3">
            <div class="small text-muted">Amount</div>
            <div class="fw-semibold">₹<?php echo number_format((float)($booking['payment_amount'] ?: $booking['monthly_rent']), 2); ?></div>
          </div>
          <div class="alert alert-info small">
            OTP sent for demo verification: <strong><?php echo htmlspecialchars($otpHint); ?></strong>
          </div>

          <form method="POST">
            <input type="hidden" name="booking_id" value="<?php echo (int)$booking['id']; ?>">
            <div class="mb-3">
              <label class="form-label">Card holder name</label>
              <input type="text" class="form-control" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Card number</label>
              <input type="text" class="form-control" maxlength="19" required>
            </div>
            <div class="row g-2 mb-3">
              <div class="col-6">
                <label class="form-label">Expiry</label>
                <input type="text" class="form-control" placeholder="MM/YY" required>
              </div>
              <div class="col-6">
                <label class="form-label">CVV</label>
                <input type="password" class="form-control" maxlength="4" required>
              </div>
            </div>
            <div class="mb-3">
              <label class="form-label">OTP</label>
              <input type="text" name="otp" class="form-control" maxlength="6" placeholder="Enter 6-digit OTP" required>
            </div>
            <button class="btn btn-primary w-100">Pay now</button>
          </form>
        </div>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php require_once '../includes/footer.php'; ?>
