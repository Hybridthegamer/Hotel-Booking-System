<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../queue/TrustScore.php';

requireAdmin();

// Suspend / Activate user
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_id'], $_POST['action'])) {
    $uid    = (int)$_POST['user_id'];
    $action = $_POST['action'] === 'suspend' ? 'suspended' : 'active';
    if ($uid !== (int)$_SESSION['user_id']) { // can't suspend self
        $stmt = $conn->prepare('UPDATE users SET status=? WHERE id=?');
        $stmt->bind_param('si', $action, $uid);
        $stmt->execute();
        $stmt->close();
        flashMessage('success', 'User status updated.');
    }
    header('Location: users.php');
    exit;
}

$search = sanitize($_GET['q'] ?? '');
$sql    = "SELECT u.*, (SELECT COUNT(*) FROM bookings b WHERE b.user_id=u.id) as booking_count
           FROM users u WHERE u.role='customer'";
if ($search) {
    $sql .= " AND (u.full_name LIKE '%{$conn->real_escape_string($search)}%' OR u.email LIKE '%{$conn->real_escape_string($search)}%')";
}
$sql  .= ' ORDER BY u.created_at DESC';
$users = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);

$trust     = new TrustScore($conn);
$pageTitle = 'Manage Guests';
include '../includes/header.php';
?>

<div class="container-fluid px-0">
  <div class="row g-0">
    <?php include 'partials/sidebar.php'; ?>
    <div class="col-lg-10 py-4 px-4">
      <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="fw-bold mb-0"><i class="bi bi-people me-2 text-warning"></i>Manage Guests</h4>
        <span class="badge bg-dark fs-6"><?= count($users) ?> registered guests</span>
      </div>

      <!-- SEARCH -->
      <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body p-3">
          <form class="row g-2">
            <div class="col-md-8">
              <input type="text" name="q" id="tableSearch" class="form-control form-control-sm" placeholder="Search by name or email..." value="<?= $search ?>">
            </div>
            <div class="col-md-2">
              <button type="submit" class="btn btn-sm btn-book w-100">Search</button>
            </div>
            <div class="col-md-2">
              <a href="users.php" class="btn btn-sm btn-outline-secondary w-100">Reset</a>
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
                  <th class="ps-4">Guest</th>
                  <th>Contact</th>
                  <th>Trust Score</th>
                  <th>Bookings</th>
                  <th>Completed</th>
                  <th>Cancellations</th>
                  <th>Joined</th>
                  <th>Status</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($users as $u): ?>
                <tr>
                  <td class="ps-4">
                    <div class="fw-semibold"><?= sanitize($u['full_name']) ?></div>
                    <div class="text-muted">@<?= sanitize($u['username']) ?></div>
                  </td>
                  <td>
                    <div><?= sanitize($u['email']) ?></div>
                    <div class="text-muted"><?= sanitize($u['phone'] ?? '-') ?></div>
                  </td>
                  <td>
                    <div class="d-flex align-items-center gap-2">
                      <span class="badge bg-<?= $trust->getTrustClass((float)$u['trust_score']) ?>"><?= number_format($u['trust_score'],1) ?></span>
                      <span class="text-muted"><?= $trust->getTrustLabel((float)$u['trust_score']) ?></span>
                    </div>
                  </td>
                  <td><?= $u['booking_count'] ?></td>
                  <td class="text-success"><?= $u['completed_stays'] ?></td>
                  <td class="text-danger"><?= $u['cancellations'] ?></td>
                  <td><?= date('d M Y', strtotime($u['created_at'])) ?></td>
                  <td><?= getStatusBadge($u['status']) ?></td>
                  <td>
                    <form method="POST">
                      <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                      <?php if ($u['status'] === 'active'): ?>
                      <button type="submit" name="action" value="suspend" class="btn btn-sm btn-outline-danger py-0 px-2"
                              data-confirm="Suspend <?= sanitize($u['full_name']) ?>?">Suspend</button>
                      <?php else: ?>
                      <button type="submit" name="action" value="activate" class="btn btn-sm btn-outline-success py-0 px-2">Activate</button>
                      <?php endif; ?>
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
