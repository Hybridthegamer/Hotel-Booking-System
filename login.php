<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

if (isLoggedIn()) {
    header('Location: ' . (isAdmin() ? SITE_URL . '/admin/index.php' : SITE_URL . '/dashboard.php'));
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username && $password) {
        $stmt = $conn->prepare('SELECT id, full_name, username, password, role, status FROM users WHERE username = ? OR email = ?');
        $stmt->bind_param('ss', $username, $username);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($user && password_verify($password, $user['password'])) {
            if ($user['status'] === 'suspended') {
                $error = 'Your account has been suspended. Please contact support.';
            } else {
                $_SESSION['user_id']   = $user['id'];
                $_SESSION['username']  = $user['username'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['role']      = $user['role'];

                $redirect = $_GET['redirect'] ?? '';
                if ($user['role'] === 'admin') {
                    header('Location: ' . SITE_URL . '/admin/index.php');
                } elseif ($redirect) {
                    header('Location: ' . $redirect);
                } else {
                    header('Location: ' . SITE_URL . '/dashboard.php');
                }
                exit;
            }
        } else {
            $error = 'Invalid username or password.';
        }
    } else {
        $error = 'Please fill in all fields.';
    }
}

$pageTitle = 'Login';
include 'includes/header.php';
?>

<section class="py-5 min-vh-100 d-flex align-items-center bg-light">
  <div class="container">
    <div class="row justify-content-center">
      <div class="col-lg-9 col-xl-8">
        <div class="auth-card row g-0">
          <div class="col-md-5 auth-side">
            <i class="bi bi-building display-3 text-warning mb-3"></i>
            <h3 class="fw-bold"><?= SITE_NAME ?></h3>
            <p class="small opacity-75 mt-2">Sign in to access your bookings, manage reservations, and check your trust score.</p>
            <hr class="border-light opacity-25 w-75 my-4">
            <div class="small opacity-75">
              <div class="mb-2"><i class="bi bi-check-circle-fill text-warning me-2"></i>Real-time room availability</div>
              <div class="mb-2"><i class="bi bi-check-circle-fill text-warning me-2"></i>15-minute room hold system</div>
              <div class="mb-2"><i class="bi bi-check-circle-fill text-warning me-2"></i>Smart priority queue</div>
            </div>
          </div>
          <div class="col-md-7 bg-white p-4 p-lg-5">
            <h4 class="fw-bold mb-1">Welcome back</h4>
            <p class="text-muted small mb-4">Enter your credentials to continue</p>

            <?php if ($error): ?>
            <div class="alert alert-danger py-2 small"><i class="bi bi-exclamation-triangle me-2"></i><?= $error ?></div>
            <?php endif; ?>

            <form method="POST" novalidate>
              <div class="mb-3">
                <label class="form-label fw-medium">Username or Email</label>
                <div class="input-group">
                  <span class="input-group-text bg-light"><i class="bi bi-person"></i></span>
                  <input type="text" name="username" class="form-control" placeholder="Enter username or email" value="<?= sanitize($_POST['username'] ?? '') ?>" required autofocus>
                </div>
              </div>
              <div class="mb-4">
                <label class="form-label fw-medium">Password</label>
                <div class="input-group">
                  <span class="input-group-text bg-light"><i class="bi bi-lock"></i></span>
                  <input type="password" name="password" id="passwordField" class="form-control" placeholder="Enter your password" required>
                  <button class="btn btn-light border" type="button" onclick="togglePwd()"><i class="bi bi-eye" id="eyeIcon"></i></button>
                </div>
              </div>
              <button type="submit" class="btn btn-book btn-lg w-100 fw-semibold">
                <i class="bi bi-box-arrow-in-right me-2"></i>Sign In
              </button>
            </form>
            <hr class="my-4">
            <p class="text-center text-muted small mb-0">
              Don't have an account? <a href="register.php" class="text-decoration-none fw-semibold text-gold">Create one</a>
            </p>
            <p class="text-center mt-2 small text-muted">
              <strong>Demo admin:</strong> admin / Admin@1234
            </p>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<script>
function togglePwd() {
    const f = document.getElementById('passwordField');
    const i = document.getElementById('eyeIcon');
    f.type = f.type === 'password' ? 'text' : 'password';
    i.className = f.type === 'password' ? 'bi bi-eye' : 'bi bi-eye-slash';
}
</script>

<?php include 'includes/footer.php'; ?>
