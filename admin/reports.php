<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

requireAdmin();

$period = sanitize($_GET['period'] ?? 'month');
$dateFilter = match($period) {
    'today' => 'DATE(b.created_at) = CURDATE()',
    'week'  => 'b.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)',
    'year'  => 'YEAR(b.created_at) = YEAR(NOW())',
    default  => 'MONTH(b.created_at) = MONTH(NOW()) AND YEAR(b.created_at) = YEAR(NOW())',
};

// Revenue summary
$revSummary = $conn->query(
    "SELECT COUNT(*) as total_bookings,
            SUM(CASE WHEN payment_status='paid' THEN total_amount ELSE 0 END) as revenue,
            SUM(CASE WHEN status='cancelled' THEN 1 ELSE 0 END) as cancellations,
            SUM(CASE WHEN status='checked_out' THEN 1 ELSE 0 END) as completed
     FROM bookings b WHERE {$dateFilter}"
)->fetch_assoc();

// Revenue by room type
$byType = $conn->query(
    "SELECT r.room_type, COUNT(*) as bookings, SUM(b.total_amount) as revenue
     FROM bookings b JOIN rooms r ON r.id = b.room_id
     WHERE b.payment_status='paid' AND {$dateFilter}
     GROUP BY r.room_type ORDER BY revenue DESC"
)->fetch_all(MYSQLI_ASSOC);

// Daily revenue (last 14 days)
$daily = $conn->query(
    "SELECT DATE(b.created_at) as day, SUM(b.total_amount) as revenue, COUNT(*) as bookings
     FROM bookings b WHERE b.payment_status='paid' AND b.created_at >= DATE_SUB(NOW(), INTERVAL 14 DAY)
     GROUP BY DATE(b.created_at) ORDER BY day ASC"
)->fetch_all(MYSQLI_ASSOC);

// Top guests
$topGuests = $conn->query(
    "SELECT u.full_name, u.email, u.trust_score,
            COUNT(b.id) as bookings, SUM(b.total_amount) as spent
     FROM bookings b JOIN users u ON u.id = b.user_id
     WHERE b.payment_status='paid'
     GROUP BY u.id ORDER BY spent DESC LIMIT 10"
)->fetch_all(MYSQLI_ASSOC);

// Queue analytics
$queueAnalytics = $conn->query(
    "SELECT room_type, status, COUNT(*) as count
     FROM reservation_queue GROUP BY room_type, status ORDER BY room_type, status"
)->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'Reports & Analytics';
include '../includes/header.php';

$dayLabels  = array_column($daily, 'day');
$dayRevenue = array_column($daily, 'revenue');
?>

<div class="container-fluid px-0">
  <div class="row g-0">
    <?php include 'partials/sidebar.php'; ?>
    <div class="col-lg-10 py-4 px-4">
      <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="fw-bold mb-0"><i class="bi bi-bar-chart me-2 text-warning"></i>Reports &amp; Analytics</h4>
        <div class="btn-group btn-group-sm">
          <?php foreach (['today'=>'Today','week'=>'7 Days','month'=>'This Month','year'=>'This Year'] as $val => $label): ?>
          <a href="?period=<?= $val ?>" class="btn btn-<?= $period === $val ? 'dark' : 'outline-dark' ?>"><?= $label ?></a>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- SUMMARY CARDS -->
      <div class="row g-3 mb-4">
        <?php $summaryCards = [
          ['Total Bookings',  $revSummary['total_bookings'], 'calendar-check', 'primary'],
          ['Revenue',         formatCurrency($revSummary['revenue'] ?? 0), 'cash-coin', 'success'],
          ['Cancellations',   $revSummary['cancellations'], 'x-circle', 'danger'],
          ['Stays Completed', $revSummary['completed'], 'patch-check', 'info'],
        ];
        foreach ($summaryCards as [$label, $value, $icon, $color]): ?>
        <div class="col-6 col-md-3">
          <div class="stat-card bg-white text-center">
            <div class="stat-icon bg-<?= $color ?> bg-opacity-10 text-<?= $color ?> mx-auto mb-2"><i class="bi bi-<?= $icon ?>"></i></div>
            <div class="fs-4 fw-bold"><?= $value ?></div>
            <div class="small text-muted"><?= $label ?></div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>

      <div class="row g-4 mb-4">
        <!-- REVENUE BY ROOM TYPE -->
        <div class="col-lg-5">
          <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-header bg-white border-0 pt-4 pb-2 px-4">
              <h6 class="fw-bold mb-0">Revenue by Room Type</h6>
            </div>
            <div class="card-body p-4">
              <?php
              $totalRevByType = array_sum(array_column($byType, 'revenue'));
              $typeColors = ['Commercial'=>'secondary','Business'=>'primary','Executive'=>'success','Double'=>'warning','Suite'=>'info'];
              foreach ($byType as $row):
                $pct = $totalRevByType > 0 ? round($row['revenue'] / $totalRevByType * 100) : 0;
              ?>
              <div class="mb-3">
                <div class="d-flex justify-content-between small mb-1">
                  <span><?= $row['room_type'] ?> (<?= $row['bookings'] ?> bookings)</span>
                  <strong><?= formatCurrency($row['revenue']) ?></strong>
                </div>
                <div class="progress" style="height:8px">
                  <div class="progress-bar bg-<?= $typeColors[$row['room_type']] ?? 'secondary' ?>" style="width:<?= $pct ?>%"></div>
                </div>
              </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>

        <!-- QUEUE ANALYTICS -->
        <div class="col-lg-7">
          <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-header bg-white border-0 pt-4 pb-2 px-4">
              <h6 class="fw-bold mb-0">Queue Analytics</h6>
            </div>
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-hover mb-0 small align-middle">
                  <thead class="table-light">
                    <tr><th class="ps-4">Room Type</th><th>Status</th><th>Count</th></tr>
                  </thead>
                  <tbody>
                    <?php foreach ($queueAnalytics as $qa): ?>
                    <tr>
                      <td class="ps-4"><?= $qa['room_type'] ?></td>
                      <td><?= getStatusBadge($qa['status']) ?></td>
                      <td class="fw-bold"><?= $qa['count'] ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($queueAnalytics)): ?>
                    <tr><td colspan="3" class="text-center text-muted py-3">No queue data</td></tr>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- DAILY REVENUE CHART -->
      <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-header bg-white border-0 pt-4 pb-2 px-4">
          <h6 class="fw-bold mb-0">Daily Revenue — Last 14 Days</h6>
        </div>
        <div class="card-body p-4">
          <?php if (empty($daily)): ?>
          <div class="text-center text-muted py-4">No revenue data for this period.</div>
          <?php else: ?>
          <div class="d-flex align-items-end gap-2" style="height:160px">
            <?php
            $maxRev = max(array_column($daily, 'revenue') ?: [1]);
            foreach ($daily as $d):
              $height = max(4, round($d['revenue'] / $maxRev * 140));
            ?>
            <div class="flex-grow-1 d-flex flex-column align-items-center gap-1" title="<?= $d['day'] ?>: <?= formatCurrency($d['revenue']) ?>">
              <div class="small text-muted" style="font-size:.65rem"><?= formatCurrency($d['revenue']) ?></div>
              <div class="bg-warning rounded-top w-100" style="height:<?= $height ?>px" data-bs-toggle="tooltip" title="<?= date('d M', strtotime($d['day'])) ?>: <?= formatCurrency($d['revenue']) ?>"></div>
              <div class="text-muted" style="font-size:.65rem;white-space:nowrap"><?= date('d/m', strtotime($d['day'])) ?></div>
            </div>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- TOP GUESTS -->
      <div class="card border-0 shadow-sm rounded-4">
        <div class="card-header bg-white border-0 pt-4 pb-2 px-4">
          <h6 class="fw-bold mb-0">Top Guests by Spend</h6>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-hover mb-0 small align-middle">
              <thead class="table-light">
                <tr>
                  <th class="ps-4">#</th>
                  <th>Guest</th>
                  <th>Trust Score</th>
                  <th>Bookings</th>
                  <th>Total Spent</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($topGuests as $i => $g): ?>
                <tr>
                  <td class="ps-4 fw-bold text-muted"><?= $i+1 ?></td>
                  <td>
                    <div class="fw-semibold"><?= sanitize($g['full_name']) ?></div>
                    <div class="text-muted"><?= sanitize($g['email']) ?></div>
                  </td>
                  <td>
                    <span class="badge bg-<?= (float)$g['trust_score'] >= 75 ? 'success' : ((float)$g['trust_score'] >= 50 ? 'warning' : 'danger') ?>">
                      <?= number_format($g['trust_score'],1) ?>
                    </span>
                  </td>
                  <td><?= $g['bookings'] ?></td>
                  <td class="fw-bold text-gold"><?= formatCurrency($g['spent']) ?></td>
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
