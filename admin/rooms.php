<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

requireAdmin();

$action = sanitize($_GET['action'] ?? '');
$errors = [];

// Add / Edit room
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id          = (int)($_POST['id'] ?? 0);
    $roomNumber  = sanitize($_POST['room_number'] ?? '');
    $roomType    = sanitize($_POST['room_type']   ?? '');
    $floor       = (int)($_POST['floor']          ?? 1);
    $capacity    = (int)($_POST['capacity']        ?? 2);
    $rate        = (float)($_POST['rate']          ?? 0);
    $description = sanitize($_POST['description'] ?? '');
    $amenities   = sanitize($_POST['amenities']   ?? '');
    $status      = sanitize($_POST['status']      ?? 'available');

    if (!$roomNumber)                 $errors[] = 'Room number required.';
    if (!$roomType)                   $errors[] = 'Room type required.';
    if ($rate <= 0)                   $errors[] = 'Valid rate required.';

    if (!$errors) {
        if ($id) {
            $stmt = $conn->prepare(
                'UPDATE rooms SET room_number=?,room_type=?,floor=?,capacity=?,rate=?,description=?,amenities=?,status=? WHERE id=?'
            );
            $stmt->bind_param('ssiidsssi', $roomNumber, $roomType, $floor, $capacity, $rate, $description, $amenities, $status, $id);
        } else {
            $stmt = $conn->prepare(
                'INSERT INTO rooms (room_number,room_type,floor,capacity,rate,description,amenities,status) VALUES (?,?,?,?,?,?,?,?)'
            );
            $stmt->bind_param('ssiidsss', $roomNumber, $roomType, $floor, $capacity, $rate, $description, $amenities, $status);
        }
        $stmt->execute();
        $stmt->close();
        flashMessage('success', $id ? 'Room updated.' : 'Room added successfully.');
        header('Location: rooms.php');
        exit;
    }
}

// Delete room
if ($action === 'delete' && isset($_GET['id'])) {
    $rid = (int)$_GET['id'];
    $conn->query("DELETE FROM rooms WHERE id={$rid}");
    flashMessage('success', 'Room deleted.');
    header('Location: rooms.php');
    exit;
}

// Edit room — pre-load
$editRoom = null;
if ($action === 'edit' && isset($_GET['id'])) {
    $editRoom = getRoomById((int)$_GET['id']);
}

$rooms = $conn->query(
    'SELECT r.*, (SELECT COUNT(*) FROM bookings b WHERE b.room_id=r.id AND b.status NOT IN ("cancelled","checked_out")) as active_bookings
     FROM rooms r ORDER BY r.room_type, r.room_number'
)->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'Manage Rooms';
include '../includes/header.php';

$roomTypeOptions = ['Commercial','Business','Executive','Double','Suite'];
$statusOptions   = ['available','occupied','reserved','maintenance'];
?>

<div class="container-fluid px-0">
  <div class="row g-0">
    <?php include 'partials/sidebar.php'; ?>
    <div class="col-lg-10 py-4 px-4">
      <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="fw-bold mb-0"><i class="bi bi-grid me-2 text-warning"></i>Manage Rooms</h4>
        <button class="btn btn-book fw-semibold" data-bs-toggle="modal" data-bs-target="#roomModal">
          <i class="bi bi-plus-circle me-1"></i>Add Room
        </button>
      </div>

      <!-- ROOMS TABLE -->
      <div class="card border-0 shadow-sm rounded-4">
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle small">
              <thead class="table-dark">
                <tr>
                  <th class="ps-4">Room #</th>
                  <th>Type</th>
                  <th>Floor</th>
                  <th>Capacity</th>
                  <th>Rate/Night</th>
                  <th>Amenities</th>
                  <th>Active Bookings</th>
                  <th>Status</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($rooms as $r): ?>
                <tr>
                  <td class="ps-4 fw-bold"><?= $r['room_number'] ?></td>
                  <td><span class="badge bg-secondary"><?= $r['room_type'] ?></span></td>
                  <td><?= $r['floor'] ?></td>
                  <td><?= $r['capacity'] ?></td>
                  <td class="text-gold fw-semibold"><?= formatCurrency($r['rate']) ?></td>
                  <td class="text-muted" style="max-width:180px;overflow:hidden;white-space:nowrap;text-overflow:ellipsis"><?= $r['amenities'] ?></td>
                  <td class="text-center">
                    <span class="badge bg-<?= $r['active_bookings'] > 0 ? 'warning text-dark' : 'light text-muted' ?>">
                      <?= $r['active_bookings'] ?>
                    </span>
                  </td>
                  <td><?= getStatusBadge($r['status']) ?></td>
                  <td>
                    <div class="d-flex gap-1">
                      <button class="btn btn-sm btn-outline-primary py-0 px-2"
                              onclick="editRoom(<?= htmlspecialchars(json_encode($r), ENT_QUOTES) ?>)"
                              data-bs-toggle="modal" data-bs-target="#roomModal">
                        <i class="bi bi-pencil"></i>
                      </button>
                      <?php if ($r['active_bookings'] == 0): ?>
                      <a href="?action=delete&id=<?= $r['id'] ?>" class="btn btn-sm btn-outline-danger py-0 px-2"
                         data-confirm="Delete room <?= $r['room_number'] ?>? This cannot be undone.">
                        <i class="bi bi-trash"></i>
                      </a>
                      <?php endif; ?>
                    </div>
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

<!-- ADD/EDIT MODAL -->
<div class="modal fade" id="roomModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content rounded-4 border-0 shadow">
      <div class="modal-header border-0 bg-dark text-white">
        <h5 class="modal-title fw-bold" id="modalTitle">Add New Room</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST">
        <div class="modal-body p-4">
          <?php if ($errors): ?>
          <div class="alert alert-danger small py-2">
            <?php foreach ($errors as $e): ?><div><?= $e ?></div><?php endforeach; ?>
          </div>
          <?php endif; ?>
          <input type="hidden" name="id" id="roomId" value="">
          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label fw-medium">Room Number *</label>
              <input type="text" name="room_number" id="roomNumber" class="form-control" required>
            </div>
            <div class="col-md-4">
              <label class="form-label fw-medium">Room Type *</label>
              <select name="room_type" id="roomType" class="form-select" required>
                <?php foreach ($roomTypeOptions as $t): ?>
                <option value="<?= $t ?>"><?= $t ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label fw-medium">Status</label>
              <select name="status" id="roomStatus" class="form-select">
                <?php foreach ($statusOptions as $s): ?>
                <option value="<?= $s ?>"><?= ucfirst($s) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label fw-medium">Floor</label>
              <input type="number" name="floor" id="roomFloor" class="form-control" min="1" value="1">
            </div>
            <div class="col-md-4">
              <label class="form-label fw-medium">Capacity</label>
              <input type="number" name="capacity" id="roomCapacity" class="form-control" min="1" max="10" value="2">
            </div>
            <div class="col-md-4">
              <label class="form-label fw-medium">Rate per Night (₦) *</label>
              <input type="number" name="rate" id="roomRate" class="form-control" min="1" step="500" required>
            </div>
            <div class="col-12">
              <label class="form-label fw-medium">Description</label>
              <textarea name="description" id="roomDescription" class="form-control" rows="2"></textarea>
            </div>
            <div class="col-12">
              <label class="form-label fw-medium">Amenities <span class="text-muted small">(comma-separated)</span></label>
              <input type="text" name="amenities" id="roomAmenities" class="form-control" placeholder="WiFi,TV,Air Conditioning,Hot Water">
            </div>
          </div>
        </div>
        <div class="modal-footer border-0">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-book fw-semibold px-4">Save Room</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function editRoom(room) {
    document.getElementById('modalTitle').textContent = 'Edit Room ' + room.room_number;
    document.getElementById('roomId').value          = room.id;
    document.getElementById('roomNumber').value      = room.room_number;
    document.getElementById('roomType').value        = room.room_type;
    document.getElementById('roomStatus').value      = room.status;
    document.getElementById('roomFloor').value       = room.floor;
    document.getElementById('roomCapacity').value    = room.capacity;
    document.getElementById('roomRate').value        = room.rate;
    document.getElementById('roomDescription').value = room.description || '';
    document.getElementById('roomAmenities').value   = room.amenities || '';
}
</script>

<?php include '../includes/footer.php'; ?>
