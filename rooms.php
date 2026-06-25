<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

$checkIn   = sanitize($_GET['check_in']   ?? date('Y-m-d'));
$checkOut  = sanitize($_GET['check_out']  ?? date('Y-m-d', strtotime('+1 day')));
$roomType  = sanitize($_GET['room_type']  ?? '');
$guests    = (int)($_GET['guests'] ?? 1);

// Validate dates
if ($checkIn < date('Y-m-d'))         $checkIn  = date('Y-m-d');
if ($checkOut <= $checkIn)            $checkOut = date('Y-m-d', strtotime($checkIn . ' +1 day'));
$nights = (int)((strtotime($checkOut) - strtotime($checkIn)) / 86400);

$availableRooms = getAvailableRooms($checkIn, $checkOut, $roomType);
$roomTypes      = getRoomTypeInfo();

require_once 'queue/QueueManager.php';
$qm         = new QueueManager($conn);
$queueStats = $qm->getQueueStats();

$pageTitle = 'Available Rooms';
include 'includes/header.php';
?>

<section class="bg-dark text-white py-4">
  <div class="container">
    <div class="row align-items-center">
      <div class="col-lg-8">
        <h2 class="fw-bold mb-1"><i class="bi bi-grid me-2 text-warning"></i>Available Rooms</h2>
        <p class="mb-0 opacity-75">
          <?= date('D, d M Y', strtotime($checkIn)) ?> &rarr; <?= date('D, d M Y', strtotime($checkOut)) ?>
          &nbsp;&bull;&nbsp; <?= $nights ?> night<?= $nights !== 1 ? 's' : '' ?>
          <?= $roomType ? '&nbsp;&bull;&nbsp;' . $roomType : '' ?>
        </p>
      </div>
      <div class="col-lg-4 mt-3 mt-lg-0">
        <span class="badge bg-success fs-6 px-3 py-2">
          <i class="bi bi-check-circle me-1"></i><?= count($availableRooms) ?> room<?= count($availableRooms) !== 1 ? 's' : '' ?> available
        </span>
      </div>
    </div>
  </div>
</section>

<!-- SEARCH REFINE -->
<section class="bg-white border-bottom py-3">
  <div class="container">
    <form class="row g-2 align-items-end" method="GET">
      <div class="col-md-3">
        <label class="form-label small fw-medium mb-1">Check-In</label>
        <input type="date" name="check_in" class="form-control form-control-sm" id="check_in" value="<?= $checkIn ?>">
      </div>
      <div class="col-md-3">
        <label class="form-label small fw-medium mb-1">Check-Out</label>
        <input type="date" name="check_out" class="form-control form-control-sm" id="check_out" value="<?= $checkOut ?>">
      </div>
      <div class="col-md-3">
        <label class="form-label small fw-medium mb-1">Room Type</label>
        <select name="room_type" class="form-select form-select-sm">
          <option value="">All Types</option>
          <?php foreach ($roomTypes as $t => $info): ?>
          <option value="<?= $t ?>" <?= $roomType === $t ? 'selected' : '' ?>><?= $t ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-1">
        <label class="form-label small fw-medium mb-1">Guests</label>
        <select name="guests" class="form-select form-select-sm">
          <?php for ($i = 1; $i <= 4; $i++): ?>
          <option value="<?= $i ?>" <?= $guests === $i ? 'selected' : '' ?>><?= $i ?></option>
          <?php endfor; ?>
        </select>
      </div>
      <div class="col-md-2">
        <button type="submit" class="btn btn-book btn-sm w-100 fw-semibold">
          <i class="bi bi-search me-1"></i>Search
        </button>
      </div>
    </form>
  </div>
</section>

<section class="py-4">
  <div class="container">

    <!-- TYPE FILTER TABS -->
    <div class="d-flex gap-2 flex-wrap mb-4">
      <button class="btn btn-sm btn-dark active" data-filter="all">All Rooms</button>
      <?php foreach ($roomTypes as $t => $info): ?>
      <button class="btn btn-sm btn-outline-dark" data-filter="<?= $t ?>">
        <i class="bi bi-<?= $info['icon'] ?> me-1"></i><?= $t ?>
        <?php if (!empty($queueStats[$t])): ?>
        <span class="badge bg-warning text-dark ms-1"><?= $queueStats[$t] ?> waiting</span>
        <?php endif; ?>
      </button>
      <?php endforeach; ?>
    </div>

    <?php if (empty($availableRooms)): ?>
    <div class="text-center py-5">
      <i class="bi bi-calendar-x display-3 text-muted mb-3"></i>
      <h4 class="text-muted">No rooms available for your selected dates</h4>
      <p class="text-muted">
        All rooms may be fully booked. You can join the waitlist queue to be notified when a room becomes available.
      </p>
      <?php if (isLoggedIn()): ?>
      <a href="queue-status.php?room_type=<?= $roomType ?>&check_in=<?= $checkIn ?>&check_out=<?= $checkOut ?>" class="btn btn-warning mt-2">
        <i class="bi bi-list-ol me-2"></i>Join Reservation Queue
      </a>
      <?php else: ?>
      <a href="login.php" class="btn btn-warning mt-2">Login to Join Queue</a>
      <?php endif; ?>
    </div>
    <?php else: ?>
    <div class="row g-4">
      <?php foreach ($availableRooms as $room):
        $total = $room['rate'] * $nights;
        $amenities = explode(',', $room['amenities'] ?? '');
      ?>
      <div class="col-lg-4 col-md-6 room-card-wrap" data-type="<?= $room['room_type'] ?>">
        <div class="room-card card h-100">
          <div class="position-relative">
            <div class="room-placeholder <?= strtolower($room['room_type']) ?>">
              <i class="bi bi-<?= $roomTypes[$room['room_type']]['icon'] ?? 'bed' ?>"></i>
            </div>
            <span class="rate-badge"><?= formatCurrency($room['rate']) ?>/night</span>
            <span class="position-absolute top-0 start-0 m-2 badge bg-dark bg-opacity-75">Floor <?= $room['floor'] ?></span>
          </div>
          <div class="card-body d-flex flex-column">
            <div class="d-flex justify-content-between align-items-start mb-2">
              <div>
                <span class="badge bg-secondary me-1"><?= $room['room_type'] ?></span>
                <span class="badge bg-light text-dark">Room <?= $room['room_number'] ?></span>
              </div>
              <span class="badge bg-success">Available</span>
            </div>
            <h5 class="card-title fw-bold mb-1"><?= $room['room_type'] ?> Room <?= $room['room_number'] ?></h5>
            <p class="text-muted small mb-2"><?= $room['description'] ?></p>
            <div class="mb-3">
              <?php foreach (array_slice($amenities, 0, 4) as $amenity): ?>
              <span class="amenity-tag"><i class="bi bi-check-circle-fill text-success"></i><?= trim($amenity) ?></span>
              <?php endforeach; ?>
              <?php if (count($amenities) > 4): ?>
              <span class="amenity-tag text-muted">+<?= count($amenities) - 4 ?> more</span>
              <?php endif; ?>
            </div>
            <div class="mt-auto">
              <div class="bg-light rounded p-2 mb-3 small">
                <div class="d-flex justify-content-between">
                  <span class="text-muted"><?= $nights ?> night<?= $nights !== 1 ? 's' : '' ?> &times; <?= formatCurrency($room['rate']) ?></span>
                  <strong class="text-gold"><?= formatCurrency($total) ?></strong>
                </div>
                <div class="text-muted" style="font-size:.75rem">Capacity: up to <?= $room['capacity'] ?> guest<?= $room['capacity'] !== 1 ? 's' : '' ?></div>
              </div>
              <a href="book.php?room_id=<?= $room['id'] ?>&check_in=<?= $checkIn ?>&check_out=<?= $checkOut ?>"
                 class="btn btn-book w-100 fw-semibold">
                <i class="bi bi-calendar-plus me-2"></i>Book Now
              </a>
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
