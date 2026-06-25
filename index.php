<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';
$pageTitle = 'Welcome';
include 'includes/header.php';

$roomTypes = getRoomTypeInfo();
?>

<!-- HERO -->
<section class="hero py-5">
  <div class="container hero-content">
    <div class="row align-items-center gy-5">
      <div class="col-lg-6">
        <div class="hero-badge"><i class="bi bi-stars"></i>Intelligent Reservation System</div>
        <h1 class="hero-title text-white mb-3">Your Perfect Stay <span class="text-warning">Awaits</span></h1>
        <p class="hero-subtitle text-white mb-4">Fair, smart room allocation with real-time availability, temporary hold protection, and trust-based priority queuing.</p>
        <div class="d-flex gap-3 flex-wrap">
          <a href="rooms.php" class="btn btn-warning btn-lg fw-semibold px-4">Explore Rooms</a>
          <?php if (!isLoggedIn()): ?>
          <a href="register.php" class="btn btn-outline-light btn-lg px-4">Create Account</a>
          <?php endif; ?>
        </div>
        <div class="row g-3 mt-4">
          <div class="col-4 text-center text-white">
            <div class="fs-2 fw-bold text-warning">12+</div>
            <div class="small opacity-75">Room Options</div>
          </div>
          <div class="col-4 text-center text-white">
            <div class="fs-2 fw-bold text-warning">24/7</div>
            <div class="small opacity-75">Online Booking</div>
          </div>
          <div class="col-4 text-center text-white">
            <div class="fs-2 fw-bold text-warning">100%</div>
            <div class="small opacity-75">Secure Payment</div>
          </div>
        </div>
      </div>
      <div class="col-lg-6">
        <!-- QUICK SEARCH BOX -->
        <div class="search-box">
          <h5 class="fw-bold mb-4"><i class="bi bi-search me-2 text-warning"></i>Search Available Rooms</h5>
          <form action="rooms.php" method="GET">
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label">Check-In</label>
                <input type="date" name="check_in" id="check_in" class="form-control" required value="<?= date('Y-m-d') ?>">
              </div>
              <div class="col-md-6">
                <label class="form-label">Check-Out</label>
                <input type="date" name="check_out" id="check_out" class="form-control" required value="<?= date('Y-m-d', strtotime('+1 day')) ?>">
              </div>
              <div class="col-md-6">
                <label class="form-label">Room Type</label>
                <select name="room_type" class="form-select">
                  <option value="">Any Type</option>
                  <?php foreach ($roomTypes as $type => $info): ?>
                  <option value="<?= $type ?>"><?= $type ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label">Guests</label>
                <select name="guests" class="form-select">
                  <option value="1">1 Guest</option>
                  <option value="2" selected>2 Guests</option>
                  <option value="3">3 Guests</option>
                  <option value="4">4 Guests</option>
                </select>
              </div>
              <div class="col-12">
                <button type="submit" class="btn btn-book btn-lg w-100 fw-semibold">
                  <i class="bi bi-search me-2"></i>Check Availability
                </button>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- INTELLIGENT FEATURES -->
<section class="py-6 bg-white py-5">
  <div class="container">
    <div class="text-center mb-5">
      <div class="section-label"><i class="bi bi-cpu"></i>What Makes Us Different</div>
      <h2 class="section-title">Intelligent Reservation Technology</h2>
      <div class="divider mx-auto"></div>
    </div>
    <div class="row g-4">
      <div class="col-md-4">
        <div class="feature-card h-100">
          <div class="feature-icon bg-primary bg-opacity-10 text-primary"><i class="bi bi-list-ol"></i></div>
          <h5 class="fw-bold mt-2">Smart Queue Management</h5>
          <p class="text-muted small">When rooms are fully booked, our intelligent queue places you fairly in line based on booking time and trust history — no more manual waiting.</p>
        </div>
      </div>
      <div class="col-md-4">
        <div class="feature-card h-100">
          <div class="feature-icon bg-warning bg-opacity-10 text-warning"><i class="bi bi-clock-history"></i></div>
          <h5 class="fw-bold mt-2">Temporary Room Hold</h5>
          <p class="text-muted small">Once you select a room, it is held exclusively for <?= TEMP_RESERVATION_MINUTES ?> minutes while you complete payment — preventing double booking.</p>
        </div>
      </div>
      <div class="col-md-4">
        <div class="feature-card h-100">
          <div class="feature-icon bg-success bg-opacity-10 text-success"><i class="bi bi-shield-check"></i></div>
          <h5 class="fw-bold mt-2">Trust Score System</h5>
          <p class="text-muted small">Reliable guests earn higher trust scores, gaining priority access in queues. Complete your stays, avoid no-shows, and your score grows automatically.</p>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ROOM TYPES PREVIEW -->
<section class="py-5 bg-light">
  <div class="container">
    <div class="text-center mb-5">
      <div class="section-label"><i class="bi bi-grid"></i>Accommodation</div>
      <h2 class="section-title">Our Room Categories</h2>
      <div class="divider mx-auto"></div>
    </div>
    <div class="row g-4">
      <?php foreach ($roomTypes as $type => $info): ?>
      <div class="col-lg-4 col-md-6">
        <div class="room-card card h-100">
          <div class="room-placeholder <?= strtolower($type) ?>">
            <i class="bi bi-<?= $info['icon'] ?>"></i>
          </div>
          <div class="card-body">
            <h5 class="card-title fw-bold"><?= $type ?> Room</h5>
            <p class="text-muted small mb-3">
              <?= match($type) {
                'Commercial' => 'Comfortable budget rooms with all essential amenities.',
                'Business'   => 'Professional rooms with work desk and premium WiFi.',
                'Executive'  => 'Elegant rooms with panoramic views and premium finishes.',
                'Double'     => 'Spacious rooms with two large beds, ideal for families.',
                'Suite'      => 'Luxury suites with living rooms and butler service.',
                default      => ''
              } ?>
            </p>
            <div class="d-flex justify-content-between align-items-center mt-auto">
              <span class="fw-bold text-gold fs-5">From <?= formatCurrency($info['min_rate']) ?><small class="fw-normal text-muted">/night</small></span>
              <a href="rooms.php?room_type=<?= $type ?>" class="btn btn-sm btn-outline-dark">View Rooms</a>
            </div>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <div class="text-center mt-4">
      <a href="rooms.php" class="btn btn-lg btn-book px-5 fw-semibold">View All Available Rooms</a>
    </div>
  </div>
</section>

<!-- HOW IT WORKS -->
<section class="py-5 bg-white">
  <div class="container">
    <div class="text-center mb-5">
      <div class="section-label"><i class="bi bi-diagram-3"></i>Process</div>
      <h2 class="section-title">How Booking Works</h2>
      <div class="divider mx-auto"></div>
    </div>
    <div class="row g-4 text-center">
      <?php $steps = [
        ['icon'=>'person-plus','title'=>'Register / Login','desc'=>'Create your account to get started. Your trust score begins at 100.'],
        ['icon'=>'search','title'=>'Search Rooms','desc'=>'Enter your dates and browse available rooms in real-time.'],
        ['icon'=>'lock','title'=>'Room is Held','desc'=>'Select a room and it\'s reserved exclusively for you for '.TEMP_RESERVATION_MINUTES.' minutes.'],
        ['icon'=>'credit-card','title'=>'Confirm & Pay','desc'=>'Complete payment securely within the hold window to confirm your booking.'],
      ]; foreach ($steps as $i => $step): ?>
      <div class="col-md-3">
        <div class="position-relative">
          <div class="feature-icon bg-dark text-warning mx-auto mb-3"><i class="bi bi-<?= $step['icon'] ?>"></i></div>
          <div class="position-absolute top-0 start-0 badge bg-warning text-dark rounded-pill" style="margin:-4px 0 0 -4px"><?= $i+1 ?></div>
        </div>
        <h6 class="fw-bold"><?= $step['title'] ?></h6>
        <p class="text-muted small"><?= $step['desc'] ?></p>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<?php include 'includes/footer.php'; ?>
