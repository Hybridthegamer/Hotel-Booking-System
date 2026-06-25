<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'queue/TrustScore.php';

requireLogin();

$payId   = sanitize($_GET['pid'] ?? '');
$booking = getBookingByPaymentId($payId);

if (!$booking || (int)$booking['user_id'] !== (int)$_SESSION['user_id']) {
    flashMessage('error', 'Booking not found.');
    header('Location: ' . SITE_URL . '/dashboard.php');
    exit;
}

$trust      = new TrustScore($conn);
$trustScore = $trust->getScore((int)$_SESSION['user_id']);

$pageTitle = 'Booking Confirmed';
include 'includes/header.php';
?>

<section class="py-5 bg-light">
  <div class="container">
    <div class="row justify-content-center">
      <div class="col-lg-8">

        <!-- SUCCESS BANNER -->
        <div class="text-center mb-5">
          <div class="mb-3" style="font-size:5rem;color:#198754"><i class="bi bi-check-circle-fill"></i></div>
          <h2 class="fw-bold text-success">Booking Confirmed!</h2>
          <p class="text-muted">Your reservation has been confirmed and payment received.</p>
        </div>

        <!-- CONFIRMATION CARD -->
        <div class="card border-0 shadow rounded-4 overflow-hidden mb-4">
          <div class="card-header bg-dark text-white py-3">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
              <div>
                <span class="small opacity-75">Booking Reference</span>
                <div class="fw-bold fs-5 text-warning"><?= $booking['payment_id'] ?></div>
              </div>
              <span class="badge bg-success fs-6 px-3 py-2"><i class="bi bi-check-circle me-1"></i>Confirmed</span>
            </div>
          </div>
          <div class="card-body p-4">
            <div class="row g-4">
              <div class="col-md-6">
                <h6 class="text-muted small text-uppercase fw-bold mb-3">Room Details</h6>
                <table class="table table-sm table-borderless small mb-0">
                  <tr><td class="text-muted">Room</td><td class="fw-semibold"><?= $booking['room_type'] ?> — Room <?= $booking['room_number'] ?></td></tr>
                  <tr><td class="text-muted">Floor</td><td><?= $booking['floor'] ?></td></tr>
                  <tr><td class="text-muted">Check-In</td><td class="fw-semibold"><?= date('D, d M Y', strtotime($booking['check_in'])) ?></td></tr>
                  <tr><td class="text-muted">Check-Out</td><td class="fw-semibold"><?= date('D, d M Y', strtotime($booking['check_out'])) ?></td></tr>
                  <tr><td class="text-muted">Duration</td><td><?= $booking['nights'] ?> night<?= $booking['nights'] !== 1 ? 's' : '' ?></td></tr>
                </table>
              </div>
              <div class="col-md-6">
                <h6 class="text-muted small text-uppercase fw-bold mb-3">Guest &amp; Payment</h6>
                <table class="table table-sm table-borderless small mb-0">
                  <tr><td class="text-muted">Guest</td><td class="fw-semibold"><?= sanitize($booking['full_name']) ?></td></tr>
                  <tr><td class="text-muted">Email</td><td><?= sanitize($booking['email']) ?></td></tr>
                  <tr><td class="text-muted">Guests</td><td><?= $booking['adults'] ?> adults<?= $booking['children'] > 0 ? ', ' . $booking['children'] . ' children' : '' ?></td></tr>
                  <tr><td class="text-muted">Method</td><td class="text-capitalize"><?= str_replace('_',' ',$booking['payment_method']) ?></td></tr>
                  <tr><td class="text-muted">Status</td><td><?= getStatusBadge($booking['payment_status']) ?></td></tr>
                </table>
              </div>
            </div>

            <hr>
            <div class="d-flex justify-content-between align-items-center">
              <span class="fs-5 fw-bold">Total Paid</span>
              <span class="fs-4 fw-bold text-gold"><?= formatCurrency($booking['total_amount']) ?></span>
            </div>

            <?php if ($booking['special_requests']): ?>
            <hr>
            <div class="small">
              <span class="text-muted fw-semibold">Special Requests: </span><?= sanitize($booking['special_requests']) ?>
            </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- TRUST SCORE UPDATE -->
        <div class="card border-0 shadow-sm rounded-4 mb-4 bg-dark text-white">
          <div class="card-body p-4 d-flex align-items-center gap-4">
            <div class="trust-ring text-<?= $trust->getTrustClass($trustScore) ?> flex-shrink-0">
              <?= number_format($trustScore, 0) ?>
            </div>
            <div>
              <div class="fw-bold fs-5">Trust Score Updated</div>
              <div class="small opacity-75">
                Your trust score is now <strong class="text-warning"><?= number_format($trustScore, 1) ?></strong>
                (<?= $trust->getTrustLabel($trustScore) ?>).
                Completing your stay will increase your score further, giving you higher queue priority in future bookings.
              </div>
            </div>
          </div>
        </div>

        <!-- NEXT STEPS -->
        <div class="card border-0 shadow-sm rounded-4 mb-4">
          <div class="card-body p-4">
            <h6 class="fw-bold mb-3">What happens next?</h6>
            <div class="row g-3 text-center">
              <div class="col-4">
                <div class="feature-icon bg-primary bg-opacity-10 text-primary mx-auto mb-2" style="width:48px;height:48px;font-size:1.2rem"><i class="bi bi-envelope-check"></i></div>
                <div class="small fw-semibold">Confirmation Email</div>
                <div class="small text-muted">Check your email for booking details</div>
              </div>
              <div class="col-4">
                <div class="feature-icon bg-warning bg-opacity-10 text-warning mx-auto mb-2" style="width:48px;height:48px;font-size:1.2rem"><i class="bi bi-calendar-check"></i></div>
                <div class="small fw-semibold">Arrive on Check-In Date</div>
                <div class="small text-muted"><?= date('D, d M Y', strtotime($booking['check_in'])) ?></div>
              </div>
              <div class="col-4">
                <div class="feature-icon bg-success bg-opacity-10 text-success mx-auto mb-2" style="width:48px;height:48px;font-size:1.2rem"><i class="bi bi-key"></i></div>
                <div class="small fw-semibold">Present Booking ID</div>
                <div class="small text-muted"><?= $booking['payment_id'] ?></div>
              </div>
            </div>
          </div>
        </div>

        <div class="d-flex gap-3 flex-wrap justify-content-center">
          <a href="my-bookings.php" class="btn btn-book px-4 fw-semibold">
            <i class="bi bi-calendar2 me-2"></i>My Bookings
          </a>
          <a href="rooms.php" class="btn btn-outline-dark px-4">
            <i class="bi bi-grid me-2"></i>Browse More Rooms
          </a>
          <button onclick="window.print()" class="btn btn-outline-secondary px-4">
            <i class="bi bi-printer me-2"></i>Print Confirmation
          </button>
        </div>

      </div>
    </div>
  </div>
</section>

<?php include 'includes/footer.php'; ?>
