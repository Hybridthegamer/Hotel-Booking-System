<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'hotel_booking');

define('SITE_NAME', 'Grand Royale Hotel');
define('SITE_URL', 'http://localhost/Hotel-Booking-System');
define('TEMP_RESERVATION_MINUTES', 15);
define('ADMIN_EMAIL', 'admin@hotelbooking.com');

define('TRUST_SCORE_BOOKING_COMPLETED', 2.0);
define('TRUST_SCORE_STAY_COMPLETED', 5.0);
define('TRUST_SCORE_CANCELLATION', -3.0);
define('TRUST_SCORE_FAILED_PAYMENT', -5.0);
define('TRUST_SCORE_NO_SHOW', -10.0);
define('TRUST_SCORE_LONG_STAY', 8.0);

define('TRUST_PRIORITY_THRESHOLD', 80.0);

define('QUEUE_PRIORITY_TIME_WEIGHT', 0.5);
define('QUEUE_PRIORITY_TRUST_WEIGHT', 0.5);

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die(json_encode(['error' => 'Database connection failed: ' . $conn->connect_error]));
}
$conn->set_charset('utf8mb4');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function isLoggedIn(): bool {
    return isset($_SESSION['user_id']);
}

function isAdmin(): bool {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: ' . SITE_URL . '/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
        exit;
    }
}

function requireAdmin(): void {
    requireLogin();
    if (!isAdmin()) {
        header('Location: ' . SITE_URL . '/index.php');
        exit;
    }
}

function sanitize(string $input): string {
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

function generatePaymentId(): string {
    return 'HB' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 10));
}

function generateToken(): string {
    return bin2hex(random_bytes(32));
}

function formatCurrency(float $amount): string {
    return '₦' . number_format($amount, 2);
}

function flashMessage(string $type, string $message): void {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlashMessage(): ?array {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}
