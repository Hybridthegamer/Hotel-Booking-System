<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

requireLogin();

$bookings  = getUserBookings((int)$_SESSION['user_id']);
$filter    = sanitize($_GET['status'] ?? 'all');

if ($filter !== 'all') {
    $bookings = array_filter($bookings, fn($b) => $b['status'] === $filter);
}

$pageTitle = 'My Bookings';
include 'includes/header.php';
?>

<section class="py-5">
  <div class="container">
    <div class="row align-items-center mb-4">
      <div class="col">
        <h3 class="fw-bold mb-1"><i class="bi bi-calendar2-check me-2 text-warning"></i>My Bookings</h3>
        <p class="text-muted mb-0">All your reservation history</p>
      </div>
      <div class="col-auto">
        <a href="rooms.php" class="btn btn-book fw-semibold"><i class="bi bi-plus me-1"></i>New Booking</a>
      </div>
    </div>

    <!-- FILTER TABS -->
    <div class="d-flex gap-2 flex-wrap mb-4">
      <?php foreach (['all'=>'All','confirmed'=>'Confirmed','checked_in'=>'Checked In','checked_out'=>'Completed','cancelled'=>'Cancelled'] as $val => $label): ?>
      <a href="?status=<?= $val ?>" class="btn btn-sm <?= $filter === $val ? 'btn-dark' : 'btn-outline-dark' ?>"><?= $label ?></a>
      <?php endforeach; ?>
    </div>

    <?php if (empty($bookings)): ?>
    <div class="text-center py-5">
      <i class="bi bi-calendar-x display-3 text-muted mb-3"></i>
      <h5 class="text-muted">No bookings found</h5>
      <a href="rooms.php" class="btn btn-book mt-3">Browse Rooms</a>
    </div>
    <?php else: ?>
    <div class="row g-4">
      <?php foreach ($bookings as $b): ?>
      <div class="col-12">
        <div class="card border-0 shadow-sm rounded-4">
          <div class="card-body p-4">
            <div class="row g-3 align-items-start">
              <div class="col-md-1 text-center">
                <div class="room-placeholder <?= strtolower($b['room_type']) ?> rounded-3" style="width:56px;height:56px;font-size:1.2rem;margin:0 auto"></div>
              </div>
              <div class="col-md-5">
                <div class="d-flex gap-2 flex-wrap mb-1">
                  <?= getStatusBadge($b['status']) ?>
                  <?= getStatusBadge($b['payment_status']) ?>
                </div>
                <h6 class="fw-bold mb-0"><?= $b['room_type'] ?> Room — #<?= $b['room_number'] ?>, Floor <?= $b['floor'] ?></h6>
                <div class="small text-muted mt-1">
                  Ref: <span class="fw-mono text-dark"><?= $b['payment_id'] ?></span>
                </div>
              </div>
              <div class="col-md-3 small">
                <div class="mb-1"><i class="bi bi-calendar-event me-1 text-muted"></i>
                  <strong>In:</strong> <?= date('D, d M Y', strtotime($b['check_in'])) ?>
                </div>
                <div class="mb-1"><i class="bi bi-calendar-x me-1 text-muted"></i>
                  <strong>Out:</strong> <?= date('D, d M Y', strtotime($b['check_out'])) ?>
                </div>
                <div><i class="bi bi-moon me-1 text-muted"></i><?= $b['nights'] ?> night<?= $b['nights'] !== 1 ? 's' : '' ?>, <?= $b['adults'] ?> guest<?= $b['adults'] > 1 ? 's' : '' ?></div>
              </div>
              <div class="col-md-3 text-md-end">
                <div class="fs-5 fw-bold text-gold mb-2"><?= formatCurrency($b['total_amount']) ?></div>
                <div class="d-flex gap-2 justify-content-md-end flex-wrap">
                  <?php if ($b['status'] === 'confirmed'): ?>
                  <a href="confirmation.php?pid=<?= $b['payment_id'] ?>" class="btn btn-sm btn-outline-dark">View</a>
                  <a href="cancel-booking.php?pid=<?= $b['payment_id'] ?>" class="btn btn-sm btn-outline-danger"
                     data-confirm="Cancel booking <?= $b['payment_id'] ?>? This may affect your trust score.">Cancel</a>
                  <?php elseif ($b['status'] === 'checked_out'): ?>
                  <a href="confirmation.php?pid=<?= $b['payment_id'] ?>" class="btn btn-sm btn-outline-dark">Receipt</a>
                  <?php else: ?>
                  <a href="confirmation.php?pid=<?= $b['payment_id'] ?>" class="btn btn-sm btn-outline-secondary">View</a>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</section>

<?php include 'includes/footer.php'; ?>
