<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

if (isLoggedIn()) { header('Location: ' . SITE_URL . '/dashboard.php'); exit; }

$errors = [];
$data   = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'full_name' => sanitize($_POST['full_name'] ?? ''),
        'username'  => sanitize($_POST['username'] ?? ''),
        'email'     => filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL),
        'phone'     => sanitize($_POST['phone'] ?? ''),
        'gender'    => sanitize($_POST['gender'] ?? 'Male'),
        'state'     => sanitize($_POST['state'] ?? ''),
        'address'   => sanitize($_POST['address'] ?? ''),
    ];
    $password  = $_POST['password'] ?? '';
    $password2 = $_POST['password2'] ?? '';

    if (!$data['full_name'])     $errors[] = 'Full name is required.';
    if (!$data['username'] || strlen($data['username']) < 3) $errors[] = 'Username must be at least 3 characters.';
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email address required.';
    if (strlen($password) < 6)   $errors[] = 'Password must be at least 6 characters.';
    if ($password !== $password2) $errors[] = 'Passwords do not match.';

    if (!$errors) {
        $check = $conn->prepare('SELECT id FROM users WHERE username = ? OR email = ?');
        $check->bind_param('ss', $data['username'], $data['email']);
        $check->execute();
        if ($check->get_result()->num_rows > 0) $errors[] = 'Username or email already taken.';
        $check->close();
    }

    if (!$errors) {
        $hashed = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $conn->prepare(
            'INSERT INTO users (full_name, username, email, password, phone, gender, state, address) VALUES (?,?,?,?,?,?,?,?)'
        );
        $stmt->bind_param('ssssssss',
            $data['full_name'], $data['username'], $data['email'], $hashed,
            $data['phone'], $data['gender'], $data['state'], $data['address']
        );
        if ($stmt->execute()) {
            $stmt->close();
            flashMessage('success', 'Registration successful! Please sign in.');
            header('Location: ' . SITE_URL . '/login.php');
            exit;
        }
        $errors[] = 'Registration failed. Please try again.';
        $stmt->close();
    }
}

$pageTitle = 'Register';
include 'includes/header.php';

$nigerian_states = ['Abia','Adamawa','Akwa Ibom','Anambra','Bauchi','Bayelsa','Benue','Borno','Cross River',
    'Delta','Ebonyi','Edo','Ekiti','Enugu','FCT Abuja','Gombe','Imo','Jigawa','Kaduna','Kano','Katsina',
    'Kebbi','Kogi','Kwara','Lagos','Nasarawa','Niger','Ogun','Ondo','Osun','Oyo','Plateau',
    'Rivers','Sokoto','Taraba','Yobe','Zamfara'];
?>

<section class="py-5 bg-light">
  <div class="container">
    <div class="row justify-content-center">
      <div class="col-lg-10">
        <div class="auth-card row g-0">
          <div class="col-md-4 auth-side">
            <i class="bi bi-person-plus display-3 text-warning mb-3"></i>
            <h4 class="fw-bold">Join <?= SITE_NAME ?></h4>
            <p class="small opacity-75 mt-2">Create your account and start booking. New guests start with a trust score of 100.</p>
            <hr class="border-light opacity-25 w-75 my-4">
            <div class="text-start small opacity-75 w-100">
              <div class="mb-2"><i class="bi bi-star-fill text-warning me-2"></i>Trust Score starts at 100</div>
              <div class="mb-2"><i class="bi bi-clock-fill text-warning me-2"></i>15-min room hold included</div>
              <div class="mb-2"><i class="bi bi-shield-fill text-warning me-2"></i>Secure online payment</div>
              <div class="mb-2"><i class="bi bi-bell-fill text-warning me-2"></i>Instant booking confirmation</div>
            </div>
          </div>
          <div class="col-md-8 bg-white p-4 p-lg-5">
            <h4 class="fw-bold mb-1">Create Account</h4>
            <p class="text-muted small mb-4">Fill in the form below to get started</p>

            <?php if ($errors): ?>
            <div class="alert alert-danger py-2 small">
              <i class="bi bi-exclamation-triangle me-2"></i>
              <ul class="mb-0 ps-3"><?php foreach ($errors as $e): ?><li><?= $e ?></li><?php endforeach; ?></ul>
            </div>
            <?php endif; ?>

            <form method="POST" novalidate>
              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label fw-medium">Full Name <span class="text-danger">*</span></label>
                  <input type="text" name="full_name" class="form-control" value="<?= $data['full_name'] ?? '' ?>" required>
                </div>
                <div class="col-md-6">
                  <label class="form-label fw-medium">Username <span class="text-danger">*</span></label>
                  <input type="text" name="username" class="form-control" value="<?= $data['username'] ?? '' ?>" required>
                </div>
                <div class="col-md-6">
                  <label class="form-label fw-medium">Email <span class="text-danger">*</span></label>
                  <input type="email" name="email" class="form-control" value="<?= $data['email'] ?? '' ?>" required>
                </div>
                <div class="col-md-6">
                  <label class="form-label fw-medium">Phone Number</label>
                  <input type="tel" name="phone" class="form-control" value="<?= $data['phone'] ?? '' ?>">
                </div>
                <div class="col-md-4">
                  <label class="form-label fw-medium">Gender</label>
                  <select name="gender" class="form-select">
                    <?php foreach (['Male','Female','Other'] as $g): ?>
                    <option <?= ($data['gender'] ?? '') === $g ? 'selected' : '' ?>><?= $g ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-md-8">
                  <label class="form-label fw-medium">State of Origin</label>
                  <select name="state" class="form-select">
                    <option value="">-- Select State --</option>
                    <?php foreach ($nigerian_states as $s): ?>
                    <option <?= ($data['state'] ?? '') === $s ? 'selected' : '' ?>><?= $s ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-12">
                  <label class="form-label fw-medium">Address</label>
                  <input type="text" name="address" class="form-control" value="<?= $data['address'] ?? '' ?>">
                </div>
                <div class="col-md-6">
                  <label class="form-label fw-medium">Password <span class="text-danger">*</span></label>
                  <input type="password" name="password" class="form-control" required>
                </div>
                <div class="col-md-6">
                  <label class="form-label fw-medium">Confirm Password <span class="text-danger">*</span></label>
                  <input type="password" name="password2" class="form-control" required>
                </div>
                <div class="col-12 mt-2">
                  <button type="submit" class="btn btn-book btn-lg w-100 fw-semibold">
                    <i class="bi bi-person-check me-2"></i>Create My Account
                  </button>
                </div>
              </div>
            </form>
            <hr class="my-3">
            <p class="text-center text-muted small mb-0">
              Already have an account? <a href="login.php" class="text-decoration-none fw-semibold text-gold">Sign In</a>
            </p>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<?php include 'includes/footer.php'; ?>
