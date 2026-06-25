<footer class="bg-dark text-white mt-5 pt-5 pb-3">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-4">
                <h5 class="fw-bold text-warning mb-3"><i class="bi bi-building me-2"></i><?= SITE_NAME ?></h5>
                <p class="text-white-50 small">Experience luxury and comfort with our intelligent booking system. Fair room allocation, real-time availability, and seamless reservations.</p>
                <div class="d-flex gap-3 mt-3">
                    <a href="#" class="text-white-50 hover-warning"><i class="bi bi-facebook fs-5"></i></a>
                    <a href="#" class="text-white-50"><i class="bi bi-twitter-x fs-5"></i></a>
                    <a href="#" class="text-white-50"><i class="bi bi-instagram fs-5"></i></a>
                </div>
            </div>
            <div class="col-lg-2 col-6">
                <h6 class="text-warning mb-3">Quick Links</h6>
                <ul class="list-unstyled small">
                    <li class="mb-1"><a href="<?= SITE_URL ?>/index.php" class="text-white-50 text-decoration-none">Home</a></li>
                    <li class="mb-1"><a href="<?= SITE_URL ?>/rooms.php" class="text-white-50 text-decoration-none">Our Rooms</a></li>
                    <li class="mb-1"><a href="<?= SITE_URL ?>/login.php" class="text-white-50 text-decoration-none">Login</a></li>
                    <li class="mb-1"><a href="<?= SITE_URL ?>/register.php" class="text-white-50 text-decoration-none">Register</a></li>
                </ul>
            </div>
            <div class="col-lg-3 col-6">
                <h6 class="text-warning mb-3">Room Types</h6>
                <ul class="list-unstyled small">
                    <li class="mb-1 text-white-50">Commercial — from ₦7,000/night</li>
                    <li class="mb-1 text-white-50">Business — from ₦12,000/night</li>
                    <li class="mb-1 text-white-50">Executive — from ₦11,000/night</li>
                    <li class="mb-1 text-white-50">Double — from ₦56,000/night</li>
                    <li class="mb-1 text-white-50">Suite — from ₦75,000/night</li>
                </ul>
            </div>
            <div class="col-lg-3">
                <h6 class="text-warning mb-3">Contact</h6>
                <ul class="list-unstyled small text-white-50">
                    <li class="mb-2"><i class="bi bi-geo-alt me-2"></i>1 Hotel Boulevard, Victoria Island, Lagos</li>
                    <li class="mb-2"><i class="bi bi-telephone me-2"></i>+234 (0) 800 HOTEL 01</li>
                    <li class="mb-2"><i class="bi bi-envelope me-2"></i><?= ADMIN_EMAIL ?></li>
                    <li class="mb-2"><i class="bi bi-clock me-2"></i>Reception: 24 hours / 7 days</li>
                </ul>
            </div>
        </div>
        <hr class="border-secondary mt-4">
        <div class="row align-items-center">
            <div class="col-md-6 small text-white-50">
                &copy; <?= date('Y') ?> <?= SITE_NAME ?>. All rights reserved.
            </div>
            <div class="col-md-6 text-md-end small text-white-50">
                Built with PHP &amp; MySQL | Intelligent Reservation Queue System
            </div>
        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= SITE_URL ?>/js/main.js"></script>
<?= isset($extraScript) ? $extraScript : '' ?>
</body>
</html>
