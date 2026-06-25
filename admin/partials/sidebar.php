<div class="col-lg-2 sidebar py-4 d-none d-lg-block">
  <div class="px-3 mb-4">
    <div class="text-warning fw-bold small text-uppercase">Admin Panel</div>
    <div class="text-white-50 small"><?= SITE_NAME ?></div>
  </div>
  <?php
  $currentPage = basename($_SERVER['PHP_SELF']);
  $menuItems = [
    ['index.php',    'speedometer2',  'Dashboard'],
    ['bookings.php', 'calendar-check','Bookings'],
    ['rooms.php',    'grid',          'Rooms'],
    ['users.php',    'people',        'Guests'],
    ['reports.php',  'bar-chart',     'Reports'],
  ];
  ?>
  <nav class="nav flex-column">
    <?php foreach ($menuItems as [$file, $icon, $label]): ?>
    <a class="nav-link <?= $currentPage === $file ? 'active' : '' ?>" href="<?= $file ?>">
      <i class="bi bi-<?= $icon ?> me-2"></i><?= $label ?>
    </a>
    <?php endforeach; ?>
    <hr class="border-secondary mx-3">
    <a class="nav-link" href="../index.php" target="_blank"><i class="bi bi-globe me-2"></i>View Site</a>
    <a class="nav-link text-danger" href="../logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a>
  </nav>
</div>
