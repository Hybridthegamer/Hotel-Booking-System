<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

requireAdmin();

// Handle status changes
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['booking_id'], $_POST['new_status'])) {
    $bid    = (int)$_POST['booking_id'];
    $status = sanitize($_POST['new_status']);
    $allowed = ['confirmed','checked_in','checked_out','cancelled'];
    if (in_array($status, $allowed)) {
        $stmt = $conn->prepare('UPDATE bookings SET status=? WHERE id=?');
        $stmt->bind_param('si', $status, $bid);
        $stmt->execute();
        $stmt->close();

        // Free room if checked_out or cancelled
        if (in_array($status, ['checked_out','cancelled'])) {
            $conn->query("UPDATE rooms r JOIN bookings b ON b.room_id = r.id SET r.status='available' WHERE b.id={$bid}");
        }
        if ($status === 'checked_in') {
            $conn->query("UPDATE rooms r JOIN bookings b ON b.room_id = r.id SET r.status='occupied' WHERE b.id={$bid}");
        }

        flashMessage('success', 'Booking status updated.');
    }
    header('Location: bookings.php');
    exit;
}

$search = sanitize($_GET['q'] ?? '');
$statusFilter = sanitize($_GET['status'] ?? '');

$sql = 'SELECT b.*, r.room_number, r.room_type, u.full_name, u.email
        FROM bookings b
        JOIN rooms r ON r.id = b.room_id
        JOIN users u ON u.id = b.user_id
        WHERE 1=1';
$params = []; $types = '';

if ($search) {
    $sql .= ' AND (b.payment_id LIKE ? OR u.full_name LIKE ? OR r.room_number LIKE ?)';
    $like = "%{$search}%";
    $params = array_merge($params, [$like, $like, $like]);
    $types .= 'sss';
}
if ($statusFilter) {
    $sql .= ' AND b.status = ?';
    $params[] = $statusFilter; $types .= 's';
}
$sql .= ' ORDER BY b.created_at DESC';

$stmt = $conn->prepare($sql);
if ($params) { $stmt->bind_param($types, ...$params); }
$stmt->execute();
$bookings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$pageTitle = 'Manage Bookings';
include '../includes/header.php';
?>

<div class="container-fluid px-0">
  <div class="row g-0">
    <?php include 'partials/sidebar.php'; ?>
    <div class="col-lg-10 py-4 px-4">
      <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="fw-bold mb-0"><i class="bi bi-calendar-check me-2 text-warning"></i>Manage Bookings</h4>
        <span class="badge bg-dark fs-6"><?= count($bookings) ?> records</span>
      </div>

      <!-- FILTERS -->
      <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body p-3">
          <form class="row g-2">
            <div class="col-md-5">
              <input type="text" name="q" id="tableSearch" class="form-control form-control-sm" placeholder="Search by ref, guest, room..." value="<?= $search ?>">
            </div>
            <div class="col-md-3">
              <select name="status" class="form-select form-select-sm">
                <option value="">All Statuses</option>
                <?php foreach (['pending','confirmed','checked_in','checked_out','cancelled'] as $s): ?>
                <option value="<?= $s ?>" <?= $statusFilter === $s ? 'selected' : '' ?>><?= ucwords(str_replace('_',' ',$s)) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-2">
              <button type="submit" class="btn btn-sm btn-book w-100">Filter</button>
            </div>
            <div class="col-md-2">
              <a href="bookings.php" class="btn btn-sm btn-outline-secondary w-100">Reset</a>
            </div>
          </form>
        </div>
      </div>

      <div class="card border-0 shadow-sm rounded-4">
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle small">
              <thead class="table-dark">
                <tr>
                  <th class="ps-4">Ref</th>
                  <th>Guest</th>
                  <th>Room</th>
                  <th>Check-In</th>
                  <th>Nts</th>
                  <th>Amount</th>
                  <th>Payment</th>
                  <th>Status</th>
                  <th>Update Status</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($bookings as $b): ?>
                <tr>
                  <td class="ps-4 fw-mono text-muted small"><?= $b['payment_id'] ?></td>
                  <td>
                    <div class="fw-semibold"><?= sanitize($b['full_name']) ?></div>
                    <div class="text-muted"><?= sanitize($b['email']) ?></div>
                  </td>
                  <td><?= $b['room_type'] ?> #<?= $b['room_number'] ?></td>
                  <td><?= date('d M Y', strtotime($b['check_in'])) ?><br><span class="text-muted">→ <?= date('d M Y', strtotime($b['check_out'])) ?></span></td>
                  <td><?= $b['nights'] ?></td>
                  <td class="fw-semibold text-gold"><?= formatCurrency($b['total_amount']) ?></td>
                  <td><?= getStatusBadge($b['payment_status']) ?></td>
                  <td><?= getStatusBadge($b['status']) ?></td>
                  <td>
                    <form method="POST" class="d-flex gap-1">
                      <input type="hidden" name="booking_id" value="<?= $b['id'] ?>">
                      <select name="new_status" class="form-select form-select-sm" style="width:120px">
                        <?php foreach (['confirmed','checked_in','checked_out','cancelled'] as $s): ?>
                        <option value="<?= $s ?>" <?= $b['status'] === $s ? 'selected' : '' ?>><?= ucwords(str_replace('_',' ',$s)) ?></option>
                        <?php endforeach; ?>
                      </select>
                      <button type="submit" class="btn btn-sm btn-book px-2">
                        <i class="bi bi-check-lg"></i>
                      </button>
                    </form>
                  </td>
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
