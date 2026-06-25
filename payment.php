<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'queue/ReservationExpiry.php';
require_once 'queue/TrustScore.php';

requireLogin();

$token  = sanitize($_GET['token'] ?? '');
$expiry = new ReservationExpiry($conn);
$trust  = new TrustScore($conn);

$reservation = $expiry->validateToken($token);
if (!$reservation || (int)$reservation['user_id'] !== (int)$_SESSION['user_id']) {
    flashMessage('error', 'Your reservation has expired or is invalid. Please start a new booking.');
    header('Location: ' . SITE_URL . '/rooms.php');
    exit;
}

$secondsLeft = $expiry->getSecondsRemaining($token);
$draft       = $_SESSION['booking_draft'] ?? [];

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Simulate payment (real integration would call a payment gateway here)
    $cardName   = sanitize($_POST['card_name']   ?? '');
    $cardNumber = preg_replace('/\D/', '', $_POST['card_number'] ?? '');
    $cardExpiry = sanitize($_POST['card_expiry'] ?? '');
    $cardCvv    = preg_replace('/\D/', '', $_POST['card_cvv'] ?? '');
    $method     = sanitize($_POST['pay_method'] ?? 'card');

    if ($method === 'card') {
        if (!$cardName)                        $errors[] = 'Cardholder name is required.';
        if (strlen($cardNumber) < 13)          $errors[] = 'Valid card number required.';
        if (!preg_match('/^\d{2}\/\d{2}$/', $cardExpiry)) $errors[] = 'Card expiry format: MM/YY.';
        if (strlen($cardCvv) < 3)              $errors[] = 'Valid CVV required.';
    }

    if (!$errors) {
        // Re-validate token
        $reservation = $expiry->validateToken($token);
        if (!$reservation) {
            flashMessage('error', 'Your hold time expired during payment. Please try again.');
            header('Location: ' . SITE_URL . '/rooms.php');
            exit;
        }

        $nights   = $draft['nights']   ?? max(1, (int)((strtotime($reservation['check_out']) - strtotime($reservation['check_in'])) / 86400));
        $total    = $reservation['rate'] * $nights;
        $payId    = generatePaymentId();
        $txnRef   = 'TXN' . strtoupper(bin2hex(random_bytes(6)));
        $adults   = $draft['adults']   ?? 1;
        $children = $draft['children'] ?? 0;
        $requests = $draft['special_requests'] ?? '';

        $conn->begin_transaction();
        try {
            // Create booking
            $stmt = $conn->prepare(
                'INSERT INTO bookings (payment_id, user_id, room_id, check_in, check_out, nights, total_amount, adults, children, special_requests, status, payment_status, payment_method)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)'
            );
            $status = 'confirmed';
            $pstatus = 'paid';
            $stmt->bind_param('siisssdiiisss',
                $payId, $_SESSION['user_id'], $reservation['room_id'],
                $reservation['check_in'], $reservation['check_out'],
                $nights, $total, $adults, $children, $requests,
                $status, $pstatus, $method
            );
            $stmt->execute();
            $bookingId = $conn->insert_id;
            $stmt->close();

            // Mark room as occupied
            $conn->query("UPDATE rooms SET status='occupied' WHERE id={$reservation['room_id']}");

            // Log payment
            $pStmt = $conn->prepare(
                'INSERT INTO payments (booking_id, transaction_ref, amount, method, status, paid_at) VALUES (?,?,?,?,?,NOW())'
            );
            $pstatus2 = 'success';
            $pStmt->bind_param('isdss', $bookingId, $txnRef, $total, $method, $pstatus2);
            $pStmt->execute();
            $pStmt->close();

            // Convert temp reservation
            $expiry->convertToBooking($token, $bookingId);

            // Update trust score
            $trust->recordEvent((int)$_SESSION['user_id'], 'booking_completed', $bookingId);
            if ($nights >= 5) $trust->recordEvent((int)$_SESSION['user_id'], 'long_stay_completed', $bookingId);

            // Update user total_bookings
            $conn->query("UPDATE users SET total_bookings = total_bookings + 1 WHERE id = {$_SESSION['user_id']}");

            $conn->commit();
            unset($_SESSION['booking_draft']);

            header('Location: ' . SITE_URL . '/confirmation.php?pid=' . urlencode($payId));
            exit;
        } catch (Exception $e) {
            $conn->rollback();
            $errors[] = 'Payment processing failed. Please try again.';
        }
    }
}

$pageTitle = 'Secure Payment';
$user = getUserById((int)$_SESSION['user_id']);
$trustScore = $trust->getScore((int)$_SESSION['user_id']);

$extraHead = '<style>
.card-input { letter-spacing:.1em; font-family:monospace; }
</style>';
include 'includes/header.php';
?>

<section class="py-5 bg-light">
  <div class="container">
    <div class="row g-4 justify-content-center">
      <div class="col-lg-7">

        <!-- COUNTDOWN TIMER -->
        <div class="timer-wrapper mb-4" id="reservation-timer" data-seconds="<?= $secondsLeft ?>">
          <div class="small opacity-75 mb-1"><i class="bi bi-clock me-1"></i>Room held exclusively for you</div>
          <div class="countdown" id="countdown-display"><?= sprintf('%02d:%02d', floor($secondsLeft/60), $secondsLeft%60) ?></div>
          <div class="small opacity-75 mt-1">Complete payment before timer expires</div>
          <div class="alert alert-warning py-1 mt-2 small mb-0" id="timer-warning" style="display:none">
            <i class="bi bi-exclamation-triangle me-1"></i>Less than 2 minutes remaining!
          </div>
        </div>

        <div class="card border-0 shadow-sm rounded-4" id="payment-body">
          <div class="card-body p-4">
            <h5 class="fw-bold mb-4"><i class="bi bi-credit-card me-2 text-warning"></i>Payment Details</h5>

            <?php if ($errors): ?>
            <div class="alert alert-danger small py-2">
              <?php foreach ($errors as $e): ?><div><i class="bi bi-x-circle me-1"></i><?= $e ?></div><?php endforeach; ?>
            </div>
            <?php endif; ?>

            <form method="POST" id="payForm">
              <!-- Payment Method Tabs -->
              <div class="btn-group w-100 mb-4" role="group">
                <input type="radio" class="btn-check" name="pay_method" id="pm_card" value="card" checked>
                <label class="btn btn-outline-dark" for="pm_card"><i class="bi bi-credit-card me-1"></i>Card</label>
                <input type="radio" class="btn-check" name="pay_method" id="pm_bank" value="bank_transfer">
                <label class="btn btn-outline-dark" for="pm_bank"><i class="bi bi-bank me-1"></i>Bank Transfer</label>
                <input type="radio" class="btn-check" name="pay_method" id="pm_cash" value="cash">
                <label class="btn btn-outline-dark" for="pm_cash"><i class="bi bi-cash-stack me-1"></i>Cash on Arrival</label>
              </div>

              <div id="card-fields">
                <div class="mb-3">
                  <label class="form-label fw-medium">Cardholder Name</label>
                  <input type="text" name="card_name" class="form-control" placeholder="Name on card" value="<?= sanitize($user['full_name'] ?? '') ?>">
                </div>
                <div class="mb-3">
                  <label class="form-label fw-medium">Card Number</label>
                  <input type="text" name="card_number" class="form-control card-input" placeholder="0000 0000 0000 0000" maxlength="19" oninput="formatCard(this)">
                </div>
                <div class="row g-3 mb-3">
                  <div class="col-6">
                    <label class="form-label fw-medium">Expiry Date</label>
                    <input type="text" name="card_expiry" class="form-control card-input" placeholder="MM/YY" maxlength="5" oninput="formatExpiry(this)">
                  </div>
                  <div class="col-6">
                    <label class="form-label fw-medium">CVV</label>
                    <input type="password" name="card_cvv" class="form-control card-input" placeholder="•••" maxlength="4">
                  </div>
                </div>
              </div>

              <div id="bank-fields" style="display:none">
                <div class="alert alert-info small">
                  <strong>Bank Transfer Details:</strong><br>
                  Bank: First Bank Nigeria<br>
                  Account Number: 3012345678<br>
                  Account Name: <?= SITE_NAME ?><br><br>
                  Use your name as the payment reference. Booking will be confirmed after verification.
                </div>
              </div>

              <div id="cash-fields" style="display:none">
                <div class="alert alert-warning small">
                  <i class="bi bi-info-circle me-1"></i>
                  Cash payment is accepted at the front desk upon arrival. Your booking will be held pending payment verification.
                  Please bring this confirmation to the hotel.
                </div>
              </div>

              <div class="d-flex gap-2">
                <a href="rooms.php" class="btn btn-outline-secondary flex-grow-0 px-3"
                   onclick="return confirm('Cancel and release the room hold?')"
                   data-confirm="Cancel this reservation and release the room hold?">
                  <i class="bi bi-arrow-left"></i>
                </a>
                <button type="submit" class="btn btn-book btn-lg flex-grow-1 fw-semibold">
                  <i class="bi bi-shield-lock me-2"></i>Confirm Payment — <?= formatCurrency($reservation['rate'] * ($draft['nights'] ?? 1)) ?>
                </button>
              </div>

              <div class="text-center mt-3 small text-muted">
                <i class="bi bi-lock-fill text-success me-1"></i>256-bit SSL encrypted &nbsp;|&nbsp;
                <i class="bi bi-patch-check-fill text-primary me-1"></i>PCI DSS compliant
              </div>
            </form>
          </div>
        </div>
      </div>

      <!-- ORDER SUMMARY -->
      <div class="col-lg-4">
        <div class="card border-0 shadow-sm rounded-4 sticky-top" style="top:80px">
          <div class="card-body p-4">
            <h6 class="fw-bold mb-3 text-muted text-uppercase small">Order Summary</h6>
            <div class="d-flex gap-3 align-items-start mb-3">
              <div class="room-placeholder <?= strtolower($reservation['room_type']) ?> rounded-3 flex-shrink-0" style="width:60px;height:60px;font-size:1.4rem"></div>
              <div>
                <div class="fw-semibold"><?= $reservation['room_type'] ?> Room <?= $reservation['room_number'] ?></div>
                <div class="small text-muted">Floor <?= $reservation['floor'] ?></div>
              </div>
            </div>
            <hr>
            <div class="small">
              <div class="d-flex justify-content-between mb-2">
                <span class="text-muted">Check-in</span>
                <span><?= date('d M Y', strtotime($reservation['check_in'])) ?></span>
              </div>
              <div class="d-flex justify-content-between mb-2">
                <span class="text-muted">Check-out</span>
                <span><?= date('d M Y', strtotime($reservation['check_out'])) ?></span>
              </div>
              <div class="d-flex justify-content-between mb-2">
                <span class="text-muted">Nights</span>
                <span><?= $draft['nights'] ?? 1 ?></span>
              </div>
              <div class="d-flex justify-content-between mb-2">
                <span class="text-muted">Guests</span>
                <span><?= ($draft['adults'] ?? 1) ?> adult<?= ($draft['adults'] ?? 1) > 1 ? 's' : '' ?><?= ($draft['children'] ?? 0) > 0 ? ', ' . $draft['children'] . ' child' : '' ?></span>
              </div>
              <div class="d-flex justify-content-between mb-2">
                <span class="text-muted">Rate/night</span>
                <span><?= formatCurrency($reservation['rate']) ?></span>
              </div>
            </div>
            <hr>
            <div class="d-flex justify-content-between fw-bold">
              <span>Total</span>
              <span class="text-gold fs-5"><?= formatCurrency($reservation['rate'] * ($draft['nights'] ?? 1)) ?></span>
            </div>
            <hr>
            <div class="small text-muted">
              <div class="d-flex gap-2 align-items-center mb-1">
                <span class="badge bg-<?= $trust->getTrustClass($trustScore) ?>"><?= $trustScore ?></span>
                <span>Your Trust Score</span>
              </div>
              <div>Completing this booking will increase your trust score.</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<script>
function formatCard(el) {
    let v = el.value.replace(/\D/g,'').substring(0,16);
    el.value = v.replace(/(.{4})/g,'$1 ').trim();
}
function formatExpiry(el) {
    let v = el.value.replace(/\D/g,'');
    if (v.length >= 3) v = v.substring(0,2) + '/' + v.substring(2,4);
    el.value = v;
}
document.querySelectorAll('[name="pay_method"]').forEach(radio => {
    radio.addEventListener('change', () => {
        document.getElementById('card-fields').style.display = radio.value === 'card' ? '' : 'none';
        document.getElementById('bank-fields').style.display = radio.value === 'bank_transfer' ? '' : 'none';
        document.getElementById('cash-fields').style.display = radio.value === 'cash' ? '' : 'none';
    });
});
</script>

<?php include 'includes/footer.php'; ?>
