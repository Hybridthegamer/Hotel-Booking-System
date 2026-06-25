<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'queue/TrustScore.php';

requireLogin();

$payId   = sanitize($_GET['pid'] ?? '');
$booking = getBookingByPaymentId($payId);

if (!$booking || (int)$booking['user_id'] !== (int)$_SESSION['user_id']) {
    flashMessage('error', 'Booking not found.');
    header('Location: ' . SITE_URL . '/my-bookings.php');
    exit;
}

if ($booking['status'] !== 'confirmed') {
    flashMessage('error', 'Only confirmed bookings can be cancelled.');
    header('Location: ' . SITE_URL . '/my-bookings.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $conn->begin_transaction();
    try {
        // Cancel booking
        $stmt = $conn->prepare(
            "UPDATE bookings SET status='cancelled', payment_status='refunded' WHERE id=?"
        );
        $stmt->bind_param('i', $booking['id']);
        $stmt->execute();
        $stmt->close();

        // Release room
        $conn->query("UPDATE rooms SET status='available' WHERE id={$booking['room_id']}");

        // Penalise trust score
        $trust = new TrustScore($conn);
        $trust->recordEvent((int)$_SESSION['user_id'], 'cancellation', (int)$booking['id']);

        $conn->commit();
        flashMessage('success', 'Booking cancelled. Note: a trust score adjustment has been applied.');
        header('Location: ' . SITE_URL . '/my-bookings.php');
        exit;
    } catch (Exception $e) {
        $conn->rollback();
        flashMessage('error', 'Cancellation failed. Please try again.');
        header('Location: ' . SITE_URL . '/my-bookings.php');
        exit;
    }
}

$pageTitle = 'Cancel Booking';
include 'includes/header.php';

require_once 'queue/TrustScore.php';
$trust      = new TrustScore($conn);
$trustScore = $trust->getScore((int)$_SESSION['user_id']);
$newScore   = max(0, $trustScore + TRUST_SCORE_CANCELLATION);
?>

<section class="py-5 bg-light">
  <div class="container">
    <div class="row justify-content-center">
      <div class="col-lg-6">
        <div class="card border-0 shadow rounded-4">
          <div class="card-body p-4 p-lg-5 text-center">
            <div class="mb-3 text-warning" style="font-size:3rem"><i class="bi bi-exclamation-triangle-fill"></i></div>
            <h4 class="fw-bold">Cancel Booking?</h4>
            <p class="text-muted mb-4">You are about to cancel the following reservation:</p>

            <div class="bg-light rounded-3 p-3 text-start mb-4">
              <div class="d-flex justify-content-between small mb-1">
                <span class="text-muted">Booking Ref</span>
                <strong><?= $booking['payment_id'] ?></strong>
              </div>
              <div class="d-flex justify-content-between small mb-1">
                <span class="text-muted">Room</span>
                <strong><?= $booking['room_type'] ?> — #<?= $booking['room_number'] ?></strong>
              </div>
              <div class="d-flex justify-content-between small mb-1">
                <span class="text-muted">Check-In</span>
                <strong><?= date('D, d M Y', strtotime($booking['check_in'])) ?></strong>
              </div>
              <div class="d-flex justify-content-between small">
                <span class="text-muted">Amount Paid</span>
                <strong class="text-gold"><?= formatCurrency($booking['total_amount']) ?></strong>
              </div>
            </div>

            <div class="alert alert-warning text-start small mb-4">
              <i class="bi bi-info-circle me-1"></i>
              <strong>Trust Score Impact:</strong> Cancelling will reduce your trust score from
              <strong><?= number_format($trustScore,1) ?></strong> to approximately
              <strong><?= number_format($newScore,1) ?></strong>
              (<?= TRUST_SCORE_CANCELLATION ?> adjustment).
              Lower trust scores affect your queue priority in future bookings.
            </div>

            <form method="POST">
              <div class="d-flex gap-3">
                <a href="my-bookings.php" class="btn btn-outline-secondary flex-grow-1">Keep Booking</a>
                <button type="submit" class="btn btn-danger flex-grow-1 fw-semibold">Confirm Cancellation</button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<?php include 'includes/footer.php'; ?>
