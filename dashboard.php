<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'queue/TrustScore.php';
require_once 'queue/QueueManager.php';

requireLogin();

$user       = getUserById((int)$_SESSION['user_id']);
$trust      = new TrustScore($conn);
$qm         = new QueueManager($conn);
$trustScore = $trust->getScore((int)$_SESSION['user_id']);
$trustLog   = $trust->getUserLog((int)$_SESSION['user_id'], 5);
$bookings   = getUserBookings((int)$_SESSION['user_id']);
$queueItems = $qm->getUserQueueEntries((int)$_SESSION['user_id']);

$recentBookings = array_slice($bookings, 0, 5);

$activeCount    = count(array_filter($bookings, fn($b) => in_array($b['status'], ['confirmed','checked_in'])));
$completedCount = count(array_filter($bookings, fn($b) => $b['status'] === 'checked_out'));
$totalSpent     = array_sum(array_column(array_filter($bookings, fn($b) => $b['payment_status'] === 'paid'), 'total_amount'));

$pageTitle = 'My Dashboard';
include 'includes/header.php';
?>

<section class="py-5 bg-light">
  <div class="container">
    <!-- HEADER -->
    <div class="row g-3 mb-4 align-items-center">
      <div class="col-lg-8">
        <h3 class="fw-bold mb-1">Welcome back, <?= sanitize($user['full_name']) ?> 👋</h3>
        <p class="text-muted mb-0">Manage your bookings and track your reservation status</p>
      </div>
      <div class="col-lg-4 text-lg-end">
        <a href="rooms.php" class="btn btn-book fw-semibold px-4"><i class="bi bi-plus-circle me-2"></i>New Booking</a>
      </div>
    </div>

    <!-- STATS ROW -->
    <div class="row g-3 mb-4">
      <div class="col-6 col-md-3">
        <div class="stat-card bg-white text-center">
          <div class="stat-icon bg-primary bg-opacity-10 text-primary mx-auto mb-2"><i class="bi bi-calendar-check"></i></div>
          <div class="fs-3 fw-bold"><?= count($bookings) ?></div>
          <div class="small text-muted">Total Bookings</div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="stat-card bg-white text-center">
          <div class="stat-icon bg-success bg-opacity-10 text-success mx-auto mb-2"><i class="bi bi-door-open"></i></div>
          <div class="fs-3 fw-bold"><?= $activeCount ?></div>
          <div class="small text-muted">Active Bookings</div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="stat-card bg-white text-center">
          <div class="stat-icon bg-warning bg-opacity-10 text-warning mx-auto mb-2"><i class="bi bi-cash-coin"></i></div>
          <div class="fs-3 fw-bold"><?= formatCurrency($totalSpent) ?></div>
          <div class="small text-muted">Total Spent</div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="stat-card bg-white text-center">
          <div class="stat-icon bg-info bg-opacity-10 text-info mx-auto mb-2"><i class="bi bi-patch-check"></i></div>
          <div class="fs-3 fw-bold"><?= $completedCount ?></div>
          <div class="small text-muted">Stays Completed</div>
        </div>
      </div>
    </div>

    <div class="row g-4">
      <!-- LEFT: BOOKINGS + QUEUE -->
      <div class="col-lg-8">

        <!-- RECENT BOOKINGS -->
        <div class="card border-0 shadow-sm rounded-4 mb-4">
          <div class="card-header bg-white border-0 pt-4 pb-0 px-4 d-flex justify-content-between align-items-center">
            <h5 class="fw-bold mb-0">Recent Bookings</h5>
            <a href="my-bookings.php" class="btn btn-sm btn-outline-dark">View All</a>
          </div>
          <div class="card-body p-0">
            <?php if (empty($recentBookings)): ?>
            <div class="text-center py-5">
              <i class="bi bi-calendar-x display-4 text-muted mb-3"></i>
              <p class="text-muted">No bookings yet. <a href="rooms.php">Browse rooms</a> to make your first booking.</p>
            </div>
            <?php else: ?>
            <div class="table-responsive">
              <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                  <tr class="small text-muted">
                    <th class="ps-4">Ref</th>
                    <th>Room</th>
                    <th>Check-In</th>
                    <th>Nights</th>
                    <th>Amount</th>
                    <th>Status</th>
                    <th></th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($recentBookings as $b): ?>
                  <tr>
                    <td class="ps-4 fw-mono small text-muted"><?= $b['payment_id'] ?></td>
                    <td><span class="fw-semibold"><?= $b['room_type'] ?></span> <span class="text-muted small">#<?= $b['room_number'] ?></span></td>
                    <td class="small"><?= date('d M Y', strtotime($b['check_in'])) ?></td>
                    <td><?= $b['nights'] ?></td>
                    <td class="fw-semibold text-gold"><?= formatCurrency($b['total_amount']) ?></td>
                    <td><?= getStatusBadge($b['status']) ?></td>
                    <td>
                      <?php if ($b['status'] === 'confirmed'): ?>
                      <a href="cancel-booking.php?pid=<?= $b['payment_id'] ?>" class="btn btn-sm btn-outline-danger"
                         data-confirm="Cancel booking <?= $b['payment_id'] ?>?">Cancel</a>
                      <?php endif; ?>
                    </td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- QUEUE STATUS -->
        <?php if (!empty($queueItems)): ?>
        <div class="card border-0 shadow-sm rounded-4 mb-4">
          <div class="card-header bg-white border-0 pt-4 pb-0 px-4">
            <h5 class="fw-bold mb-0"><i class="bi bi-list-ol me-2 text-warning"></i>My Queue Positions</h5>
          </div>
          <div class="card-body p-4">
            <?php foreach ($queueItems as $q): ?>
            <div class="d-flex align-items-center gap-3 p-3 bg-light rounded-3 mb-2">
              <div class="queue-position"><?= $q['queue_position'] ?></div>
              <div class="flex-grow-1">
                <div class="fw-semibold"><?= $q['room_type'] ?> Room</div>
                <div class="small text-muted">
                  <?= date('d M', strtotime($q['check_in'])) ?> – <?= date('d M Y', strtotime($q['check_out'])) ?>
                  &nbsp;&bull;&nbsp; Queued <?= date('d M H:i', strtotime($q['created_at'])) ?>
                </div>
              </div>
              <a href="queue-status.php?id=<?= $q['id'] ?>" class="btn btn-sm btn-outline-primary">Details</a>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>
      </div>

      <!-- RIGHT: TRUST SCORE -->
      <div class="col-lg-4">
        <div class="card border-0 shadow-sm rounded-4 bg-dark text-white mb-4">
          <div class="card-body p-4 text-center">
            <div class="small text-white-50 mb-2 text-uppercase fw-bold">Trust Score</div>
            <div class="trust-ring text-<?= $trust->getTrustClass($trustScore) ?> mx-auto mb-3" style="width:100px;height:100px;font-size:2rem;border-width:5px">
              <?= number_format($trustScore, 0) ?>
            </div>
            <div class="badge bg-<?= $trust->getTrustClass($trustScore) ?> px-3 py-2 mb-3"><?= $trust->getTrustLabel($trustScore) ?></div>
            <div class="progress mb-3" style="height:8px">
              <div class="progress-bar bg-<?= $trust->getTrustClass($trustScore) ?>"
                   style="width:<?= $trustScore ?>%"></div>
            </div>
            <div class="row g-2 text-center small">
              <div class="col-4">
                <div class="fw-bold text-success"><?= $user['completed_stays'] ?></div>
                <div class="text-white-50">Stays Done</div>
              </div>
              <div class="col-4">
                <div class="fw-bold text-danger"><?= $user['cancellations'] ?></div>
                <div class="text-white-50">Cancellations</div>
              </div>
              <div class="col-4">
                <div class="fw-bold text-warning"><?= $user['total_bookings'] ?></div>
                <div class="text-white-50">Bookings</div>
              </div>
            </div>
          </div>
        </div>

        <!-- TRUST HISTORY -->
        <?php if (!empty($trustLog)): ?>
        <div class="card border-0 shadow-sm rounded-4">
          <div class="card-header bg-white border-0 pt-4 pb-2 px-4">
            <h6 class="fw-bold mb-0 small text-muted text-uppercase">Trust Score History</h6>
          </div>
          <div class="card-body p-3">
            <?php foreach ($trustLog as $log): ?>
            <div class="d-flex justify-content-between align-items-center py-2 border-bottom small">
              <div class="text-muted"><?= ucwords(str_replace('_',' ',$log['event_type'])) ?></div>
              <div>
                <span class="badge bg-<?= $log['score_change'] > 0 ? 'success' : 'danger' ?>">
                  <?= $log['score_change'] > 0 ? '+' : '' ?><?= $log['score_change'] ?>
                </span>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>

<?php include 'includes/footer.php'; ?>
