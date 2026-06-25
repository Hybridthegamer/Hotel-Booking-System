<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../queue/QueueManager.php';

requireAdmin();

$stats   = getDashboardStats();
$qm      = new QueueManager($conn);

// Recent bookings
$recentBookings = $conn->query(
    'SELECT b.*, r.room_number, r.room_type, u.full_name
     FROM bookings b
     JOIN rooms r ON r.id = b.room_id
     JOIN users u ON u.id = b.user_id
     ORDER BY b.created_at DESC LIMIT 8'
)->fetch_all(MYSQLI_ASSOC);

// Revenue by room type
$revenueByType = $conn->query(
    "SELECT r.room_type, SUM(b.total_amount) as revenue, COUNT(*) as count
     FROM bookings b JOIN rooms r ON r.id = b.room_id
     WHERE b.payment_status = 'paid'
     GROUP BY r.room_type"
)->fetch_all(MYSQLI_ASSOC);

// Occupancy
$occupancy = round(
    $stats['total_rooms'] > 0
        ? (($stats['total_rooms'] - $stats['available_rooms']) / $stats['total_rooms']) * 100
        : 0
);

$pageTitle = 'Admin Dashboard';
include '../includes/header.php';
?>

<div class="container-fluid px-0">
  <div class="row g-0">
    <!-- SIDEBAR -->
    <?php include 'partials/sidebar.php'; ?>

    <!-- MAIN -->
    <div class="col-lg-10 py-4 px-4">
      <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
          <h4 class="fw-bold mb-1">Dashboard Overview</h4>
          <p class="text-muted small mb-0">Welcome, <?= sanitize($_SESSION['full_name']) ?> — <?= date('l, d F Y') ?></p>
        </div>
        <a href="../rooms.php" class="btn btn-book fw-semibold" target="_blank"><i class="bi bi-eye me-1"></i>View Site</a>
      </div>

      <!-- STAT CARDS -->
      <div class="row g-3 mb-4">
        <?php $cards = [
          ['Total Bookings',    $stats['total_bookings'],    'calendar-check',    'primary',  null],
          ['Active Bookings',   $stats['active_bookings'],   'door-open',         'success',  null],
          ['Available Rooms',   $stats['available_rooms'].'/'.$stats['total_rooms'], 'grid', 'info', null],
          ['Total Revenue',     formatCurrency($stats['total_revenue']), 'cash-coin', 'warning', null],
          ['Customers',         $stats['total_customers'],   'people',            'secondary', null],
          ['Queue Waiting',     $stats['queue_waiting'],     'list-ol',           'danger',   null],
        ];
        foreach ($cards as [$label, $value, $icon, $color, $_]): ?>
        <div class="col-6 col-lg-2">
          <div class="stat-card bg-white h-100 text-center">
            <div class="stat-icon bg-<?= $color ?> bg-opacity-10 text-<?= $color ?> mx-auto mb-2">
              <i class="bi bi-<?= $icon ?>"></i>
            </div>
            <div class="fs-4 fw-bold"><?= $value ?></div>
            <div class="small text-muted"><?= $label ?></div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>

      <div class="row g-4 mb-4">
        <!-- OCCUPANCY -->
        <div class="col-lg-4">
          <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-body p-4 text-center">
              <h6 class="fw-bold text-muted small text-uppercase mb-3">Occupancy Rate</h6>
              <div class="position-relative d-inline-flex align-items-center justify-content-center mb-3" style="width:120px;height:120px">
                <svg width="120" height="120" viewBox="0 0 120 120">
                  <circle cx="60" cy="60" r="50" fill="none" stroke="#e9ecef" stroke-width="12"/>
                  <circle cx="60" cy="60" r="50" fill="none"
                    stroke="<?= $occupancy >= 80 ? '#dc3545' : ($occupancy >= 50 ? '#ffc107' : '#198754') ?>"
                    stroke-width="12"
                    stroke-dasharray="<?= round(314.16 * $occupancy / 100) ?> 314.16"
                    stroke-dashoffset="78.54"
                    stroke-linecap="round"/>
                </svg>
                <div class="position-absolute text-center">
                  <div class="fs-3 fw-bold"><?= $occupancy ?>%</div>
                  <div class="small text-muted">Occupied</div>
                </div>
              </div>
              <div class="row g-2 text-center small">
                <div class="col-6">
                  <div class="fw-bold text-danger"><?= $stats['total_rooms'] - $stats['available_rooms'] ?></div>
                  <div class="text-muted">Occupied</div>
                </div>
                <div class="col-6">
                  <div class="fw-bold text-success"><?= $stats['available_rooms'] ?></div>
                  <div class="text-muted">Available</div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- REVENUE BY TYPE -->
        <div class="col-lg-8">
          <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-header bg-white border-0 pt-4 pb-0 px-4">
              <h6 class="fw-bold mb-0">Revenue by Room Type</h6>
            </div>
            <div class="card-body p-4">
              <?php
              $totalRev = array_sum(array_column($revenueByType, 'revenue'));
              $colors   = ['Commercial'=>'secondary','Business'=>'primary','Executive'=>'success','Double'=>'warning','Suite'=>'purple'];
              foreach ($revenueByType as $row):
                $pct = $totalRev > 0 ? round($row['revenue'] / $totalRev * 100) : 0;
              ?>
              <div class="mb-3">
                <div class="d-flex justify-content-between small mb-1">
                  <span class="fw-medium"><?= $row['room_type'] ?></span>
                  <span><?= formatCurrency($row['revenue']) ?> (<?= $pct ?>%)</span>
                </div>
                <div class="progress" style="height:6px">
                  <div class="progress-bar bg-<?= $colors[$row['room_type']] ?? 'primary' ?>" style="width:<?= $pct ?>%"></div>
                </div>
              </div>
              <?php endforeach; ?>
              <div class="text-end small text-muted mt-2">
                Total Revenue: <strong class="text-gold"><?= formatCurrency($totalRev) ?></strong>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- RECENT BOOKINGS TABLE -->
      <div class="card border-0 shadow-sm rounded-4">
        <div class="card-header bg-white border-0 pt-4 pb-0 px-4 d-flex justify-content-between">
          <h6 class="fw-bold mb-3">Recent Bookings</h6>
          <a href="bookings.php" class="btn btn-sm btn-outline-dark">All Bookings</a>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle small">
              <thead class="table-light">
                <tr>
                  <th class="ps-4">Ref</th>
                  <th>Guest</th>
                  <th>Room</th>
                  <th>Check-In</th>
                  <th>Nights</th>
                  <th>Amount</th>
                  <th>Status</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($recentBookings as $b): ?>
                <tr>
                  <td class="ps-4 fw-mono text-muted"><?= $b['payment_id'] ?></td>
                  <td class="fw-semibold"><?= sanitize($b['full_name']) ?></td>
                  <td><?= $b['room_type'] ?> #<?= $b['room_number'] ?></td>
                  <td><?= date('d M Y', strtotime($b['check_in'])) ?></td>
                  <td><?= $b['nights'] ?></td>
                  <td class="text-gold fw-semibold"><?= formatCurrency($b['total_amount']) ?></td>
                  <td><?= getStatusBadge($b['status']) ?></td>
                  <td><a href="bookings.php?action=view&id=<?= $b['id'] ?>" class="btn btn-xs btn-outline-secondary btn-sm py-0 px-2">View</a></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

    </div>
  </div>
</div>

<?php include '../includes/footer.php'; ?>
