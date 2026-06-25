<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'queue/ReservationExpiry.php';

requireLogin();

$roomId   = (int)($_GET['room_id']   ?? 0);
$checkIn  = sanitize($_GET['check_in']  ?? date('Y-m-d'));
$checkOut = sanitize($_GET['check_out'] ?? date('Y-m-d', strtotime('+1 day')));

if (!$roomId) { header('Location: ' . SITE_URL . '/rooms.php'); exit; }

$room = getRoomById($roomId);
if (!$room || $room['status'] !== 'available') {
    flashMessage('error', 'Sorry, that room is no longer available. Please choose another.');
    header('Location: ' . SITE_URL . '/rooms.php?check_in=' . $checkIn . '&check_out=' . $checkOut);
    exit;
}

$nights = max(1, (int)((strtotime($checkOut) - strtotime($checkIn)) / 86400));
$total  = $room['rate'] * $nights;
$amenities = explode(',', $room['amenities'] ?? '');

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $adults   = (int)($_POST['adults']   ?? 1);
    $children = (int)($_POST['children'] ?? 0);
    $requests = sanitize($_POST['special_requests'] ?? '');

    if ($adults < 1 || $adults > $room['capacity']) {
        $errors[] = 'Number of adults must be between 1 and ' . $room['capacity'] . '.';
    }

    if (!$errors) {
        $expiry = new ReservationExpiry($conn);
        $token  = $expiry->createTempReservation($_SESSION['user_id'], $roomId, $checkIn, $checkOut);

        $_SESSION['booking_draft'] = [
            'token'            => $token,
            'room_id'          => $roomId,
            'check_in'         => $checkIn,
            'check_out'        => $checkOut,
            'nights'           => $nights,
            'adults'           => $adults,
            'children'         => $children,
            'special_requests' => $requests,
            'total'            => $total,
        ];

        header('Location: ' . SITE_URL . '/payment.php?token=' . urlencode($token));
        exit;
    }
}

$pageTitle = 'Book Room ' . $room['room_number'];
include 'includes/header.php';
$roomTypes = getRoomTypeInfo();
?>

<section class="py-5">
  <div class="container">
    <div class="row g-4">
      <!-- FORM -->
      <div class="col-lg-7">
        <nav aria-label="breadcrumb" class="mb-3">
          <ol class="breadcrumb small">
            <li class="breadcrumb-item"><a href="index.php">Home</a></li>
            <li class="breadcrumb-item"><a href="rooms.php?check_in=<?= $checkIn ?>&check_out=<?= $checkOut ?>">Rooms</a></li>
            <li class="breadcrumb-item active">Book Room <?= $room['room_number'] ?></li>
          </ol>
        </nav>

        <div class="card border-0 shadow-sm rounded-4 p-4">
          <h4 class="fw-bold mb-1"><i class="bi bi-calendar-plus me-2 text-warning"></i>Booking Details</h4>
          <p class="text-muted small mb-4">Complete the form below to proceed to payment</p>

          <?php if ($errors): ?>
          <div class="alert alert-danger small py-2">
            <?php foreach ($errors as $e): ?><div><i class="bi bi-x-circle me-1"></i><?= $e ?></div><?php endforeach; ?>
          </div>
          <?php endif; ?>

          <form method="POST" novalidate>
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label fw-medium">Check-In Date</label>
                <input type="text" class="form-control bg-light" value="<?= date('D, d M Y', strtotime($checkIn)) ?>" readonly>
                <input type="hidden" name="check_in" value="<?= $checkIn ?>">
              </div>
              <div class="col-md-6">
                <label class="form-label fw-medium">Check-Out Date</label>
                <input type="text" class="form-control bg-light" value="<?= date('D, d M Y', strtotime($checkOut)) ?>" readonly>
              </div>
              <div class="col-md-6">
                <label class="form-label fw-medium">Adults <span class="text-danger">*</span></label>
                <select name="adults" class="form-select">
                  <?php for ($i = 1; $i <= $room['capacity']; $i++): ?>
                  <option value="<?= $i ?>" <?= $i === 1 ? 'selected' : '' ?>><?= $i ?> Adult<?= $i > 1 ? 's' : '' ?></option>
                  <?php endfor; ?>
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label fw-medium">Children</label>
                <select name="children" class="form-select">
                  <?php for ($i = 0; $i <= 3; $i++): ?>
                  <option value="<?= $i ?>"><?= $i === 0 ? 'No children' : $i . ' Child' . ($i > 1 ? 'ren' : '') ?></option>
                  <?php endfor; ?>
                </select>
              </div>
              <div class="col-12">
                <label class="form-label fw-medium">Special Requests <span class="text-muted small">(optional)</span></label>
                <textarea name="special_requests" class="form-control" rows="3" placeholder="e.g. early check-in, high floor, extra pillows..."><?= $_POST['special_requests'] ?? '' ?></textarea>
              </div>

              <div class="col-12">
                <div class="alert alert-info py-2 small mb-0">
                  <i class="bi bi-info-circle me-2"></i>
                  Once you proceed, your room will be held for <strong><?= TEMP_RESERVATION_MINUTES ?> minutes</strong> while you complete payment.
                </div>
              </div>

              <div class="col-12">
                <button type="submit" class="btn btn-book btn-lg w-100 fw-semibold">
                  <i class="bi bi-lock me-2"></i>Hold Room &amp; Proceed to Payment
                </button>
              </div>
            </div>
          </form>
        </div>
      </div>

      <!-- SUMMARY -->
      <div class="col-lg-5">
        <div class="card border-0 shadow-sm rounded-4 overflow-hidden sticky-top" style="top:80px">
          <div class="room-placeholder <?= strtolower($room['room_type']) ?>" style="height:160px">
            <i class="bi bi-<?= $roomTypes[$room['room_type']]['icon'] ?? 'bed' ?> fs-1"></i>
          </div>
          <div class="card-body p-4">
            <div class="d-flex justify-content-between align-items-start mb-2">
              <div>
                <span class="badge bg-secondary me-1"><?= $room['room_type'] ?></span>
                <span class="badge bg-light text-dark">Floor <?= $room['floor'] ?></span>
              </div>
              <span class="badge bg-success">Available</span>
            </div>
            <h5 class="fw-bold"><?= $room['room_type'] ?> Room <?= $room['room_number'] ?></h5>
            <p class="text-muted small"><?= $room['description'] ?></p>

            <div class="mb-3">
              <?php foreach ($amenities as $a): ?>
              <span class="amenity-tag"><i class="bi bi-check-circle-fill text-success"></i><?= trim($a) ?></span>
              <?php endforeach; ?>
            </div>

            <hr>
            <div class="small">
              <div class="d-flex justify-content-between mb-1">
                <span class="text-muted">Room rate</span>
                <span><?= formatCurrency($room['rate']) ?>/night</span>
              </div>
              <div class="d-flex justify-content-between mb-1">
                <span class="text-muted">Duration</span>
                <span><?= $nights ?> night<?= $nights !== 1 ? 's' : '' ?></span>
              </div>
              <div class="d-flex justify-content-between mb-1">
                <span class="text-muted">Check-in</span>
                <span><?= date('d M Y', strtotime($checkIn)) ?></span>
              </div>
              <div class="d-flex justify-content-between mb-1">
                <span class="text-muted">Check-out</span>
                <span><?= date('d M Y', strtotime($checkOut)) ?></span>
              </div>
            </div>
            <hr>
            <div class="d-flex justify-content-between fw-bold fs-5">
              <span>Total</span>
              <span class="text-gold"><?= formatCurrency($total) ?></span>
            </div>
            <div class="small text-muted mt-1">
              <i class="bi bi-shield-check me-1 text-success"></i>Room held for <?= TEMP_RESERVATION_MINUTES ?> min after proceeding
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<?php include 'includes/footer.php'; ?>
