<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'queue/QueueManager.php';
require_once 'queue/TrustScore.php';

requireLogin();

$qm        = new QueueManager($conn);
$trust     = new TrustScore($conn);

$roomType  = sanitize($_GET['room_type'] ?? '');
$checkIn   = sanitize($_GET['check_in']  ?? date('Y-m-d'));
$checkOut  = sanitize($_GET['check_out'] ?? date('Y-m-d', strtotime('+1 day')));
$queueId   = (int)($_GET['id'] ?? 0);

$message = '';
$queueEntry = null;

// Join queue
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['join_queue'])) {
    if (!$roomType) { $message = 'error:Please select a room type.'; }
    else {
        $qid = $qm->enqueue((int)$_SESSION['user_id'], $roomType, $checkIn, $checkOut);
        $pos = $qm->getPosition((int)$_SESSION['user_id'], $roomType, $checkIn, $checkOut);
        flashMessage('success', "You've joined the queue for a {$roomType} room. Position: #{$pos}");
        header('Location: queue-status.php');
        exit;
    }
}

// Cancel queue entry
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_queue'])) {
    $qm->cancelFromQueue((int)$_SESSION['user_id'], $roomType);
    flashMessage('success', 'Removed from queue.');
    header('Location: dashboard.php');
    exit;
}

$userQueues  = $qm->getUserQueueEntries((int)$_SESSION['user_id']);
$queueStats  = $qm->getQueueStats();
$trustScore  = $trust->getScore((int)$_SESSION['user_id']);
$roomTypes   = getRoomTypeInfo();

$pageTitle = 'Reservation Queue';
include 'includes/header.php';
?>

<section class="py-5 bg-light">
  <div class="container">
    <div class="row g-4">
      <div class="col-lg-7">
        <h3 class="fw-bold mb-1"><i class="bi bi-list-ol me-2 text-warning"></i>Reservation Queue</h3>
        <p class="text-muted mb-4">Join the waitlist when your preferred room type is fully booked. Our intelligent system allocates rooms fairly based on booking time and trust score.</p>

        <!-- JOIN FORM -->
        <div class="card border-0 shadow-sm rounded-4 mb-4">
          <div class="card-body p-4">
            <h5 class="fw-bold mb-3">Join a Queue</h5>
            <form method="POST">
              <div class="row g-3">
                <div class="col-md-4">
                  <label class="form-label fw-medium">Room Type</label>
                  <select name="room_type" id="room_type" class="form-select" required>
                    <option value="">Select type</option>
                    <?php foreach ($roomTypes as $t => $info): ?>
                    <option value="<?= $t ?>" <?= $roomType === $t ? 'selected' : '' ?>>
                      <?= $t ?> <?= !empty($queueStats[$t]) ? '(' . $queueStats[$t] . ' waiting)' : '' ?>
                    </option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-md-3">
                  <label class="form-label fw-medium">Check-In</label>
                  <input type="date" name="check_in" id="check_in" class="form-control" value="<?= $checkIn ?>" min="<?= date('Y-m-d') ?>">
                </div>
                <div class="col-md-3">
                  <label class="form-label fw-medium">Check-Out</label>
                  <input type="date" name="check_out" id="check_out" class="form-control" value="<?= $checkOut ?>">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                  <button type="submit" name="join_queue" class="btn btn-book w-100 fw-semibold">Join</button>
                </div>
              </div>
            </form>
          </div>
        </div>

        <!-- MY QUEUE ENTRIES -->
        <div class="card border-0 shadow-sm rounded-4">
          <div class="card-header bg-white border-0 pt-4 pb-0 px-4">
            <h5 class="fw-bold mb-3">My Queue Positions</h5>
          </div>
          <div class="card-body p-4">
            <?php if (empty($userQueues)): ?>
            <div class="text-center py-4">
              <i class="bi bi-inbox display-4 text-muted mb-3"></i>
              <p class="text-muted">You are not in any queue. Join a queue above to be notified when a room becomes available.</p>
            </div>
            <?php else: ?>
            <?php foreach ($userQueues as $q): ?>
            <div class="d-flex align-items-center gap-3 p-3 bg-light rounded-3 mb-3">
              <div class="queue-position flex-shrink-0"><?= $q['queue_position'] ?></div>
              <div class="flex-grow-1">
                <div class="fw-semibold"><?= $q['room_type'] ?> Room</div>
                <div class="small text-muted">
                  <?= date('d M Y', strtotime($q['check_in'])) ?> – <?= date('d M Y', strtotime($q['check_out'])) ?>
                </div>
                <div class="small text-muted">
                  Joined: <?= date('d M H:i', strtotime($q['created_at'])) ?>
                  &nbsp;&bull;&nbsp; Expires: <?= date('d M H:i', strtotime($q['expires_at'])) ?>
                </div>
                <div class="mt-1">
                  <span class="badge bg-primary">Priority Score: <?= number_format($q['priority_score'], 2) ?></span>
                  <span class="badge bg-<?= $q['queue_position'] <= 3 ? 'success' : 'secondary' ?> ms-1">
                    <?= $q['queue_position'] <= 3 ? 'High Priority' : 'In Queue' ?>
                  </span>
                </div>
              </div>
              <form method="POST" class="flex-shrink-0">
                <input type="hidden" name="room_type" value="<?= $q['room_type'] ?>">
                <button type="submit" name="cancel_queue" class="btn btn-sm btn-outline-danger"
                        data-confirm="Leave the queue for <?= $q['room_type'] ?> room?">Leave</button>
              </form>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- RIGHT: QUEUE STATS + INFO -->
      <div class="col-lg-5">
        <!-- TRUST SCORE CONTEXT -->
        <div class="card border-0 shadow-sm rounded-4 bg-dark text-white mb-4">
          <div class="card-body p-4">
            <h6 class="fw-bold mb-3 text-warning">How Queue Priority Works</h6>
            <div class="small mb-3 opacity-85">
              Priority is calculated using your <strong>trust score</strong> and <strong>queue join time</strong>.
              Users with higher trust scores get a small priority advantage.
            </div>
            <div class="d-flex align-items-center gap-3 mb-2">
              <div class="trust-ring text-<?= $trust->getTrustClass($trustScore) ?> flex-shrink-0" style="width:60px;height:60px;font-size:1.1rem">
                <?= number_format($trustScore, 0) ?>
              </div>
              <div>
                <div class="small fw-semibold">Your Trust Score: <?= $trust->getTrustLabel($trustScore) ?></div>
                <div class="small opacity-75">
                  <?php if ($trustScore >= TRUST_PRIORITY_THRESHOLD): ?>
                  <i class="bi bi-star-fill text-warning me-1"></i>You receive priority queue placement
                  <?php else: ?>
                  Reach <?= TRUST_PRIORITY_THRESHOLD ?> to unlock priority placement
                  <?php endif; ?>
                </div>
              </div>
            </div>
            <hr class="border-secondary">
            <div class="small opacity-75">
              <div class="mb-1"><i class="bi bi-plus-circle text-success me-1"></i>Completing stays: +<?= TRUST_SCORE_STAY_COMPLETED ?> points</div>
              <div class="mb-1"><i class="bi bi-plus-circle text-success me-1"></i>Confirmed booking: +<?= TRUST_SCORE_BOOKING_COMPLETED ?> points</div>
              <div class="mb-1"><i class="bi bi-dash-circle text-danger me-1"></i>Cancellation: <?= TRUST_SCORE_CANCELLATION ?> points</div>
              <div><i class="bi bi-dash-circle text-danger me-1"></i>No-show: <?= TRUST_SCORE_NO_SHOW ?> points</div>
            </div>
          </div>
        </div>

        <!-- LIVE QUEUE STATS -->
        <div class="card border-0 shadow-sm rounded-4">
          <div class="card-header bg-white border-0 pt-4 pb-2 px-4">
            <h6 class="fw-bold mb-0">Current Queue Sizes</h6>
          </div>
          <div class="card-body p-4">
            <?php foreach ($roomTypes as $type => $info): ?>
            <div class="d-flex justify-content-between align-items-center mb-3">
              <div class="d-flex align-items-center gap-2">
                <i class="bi bi-<?= $info['icon'] ?> text-muted"></i>
                <span class="fw-medium"><?= $type ?></span>
              </div>
              <?php $waiting = $queueStats[$type] ?? 0; ?>
              <span class="badge bg-<?= $waiting > 0 ? 'warning text-dark' : 'success' ?>">
                <?= $waiting > 0 ? $waiting . ' waiting' : 'No queue' ?>
              </span>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<?php include 'includes/footer.php'; ?>
