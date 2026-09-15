</main>
<footer class="footer-wrapper">
    <div class="container footer-grid">
        <div class="footer-col brand-col">
            <div class="footer-brand">🌱 Eco<strong>Sprout</strong></div>
            <p>Kegalle's dedicated plant nursery & landscaping solution center. Empowering plant enthusiasts with healthy flora, botanical education, and sustainable gardening practices.</p>
            <p class="store-address">📍 <?= APP_LOCATION ?></p>
            <p>📞 +94 35 222 3456 | ✉️ contact@ecosprout.lk</p>
        </div>
        <div class="footer-col">
            <h4>Quick Navigation</h4>
            <ul>
                <li><a href="<?= APP_URL ?>/pages/catalog.php">Explore Plants</a></li>
                <li><a href="<?= APP_URL ?>/pages/services.php">Landscaping Services</a></li>
                <li><a href="<?= APP_URL ?>/pages/workshops.php">Upcoming Workshops</a></li>
                <li><a href="<?= APP_URL ?>/pages/inquiry.php">Ask an Agronomist</a></li>
            </ul>
        </div>
        <div class="footer-col">
            <h4>Nursery Operations</h4>
            <ul>
                <li>Monday – Saturday: 8:00 AM – 6:00 PM</li>
                <li>Sunday: 8:30 AM – 2:00 PM</li>
                <li>Free Plant Consultations on Saturdays</li>
                <li>Sabaragamuwa District Wide Delivery</li>
            </ul>
        </div>
                <div class="footer-col">
            <h4>Customer Support</h4>
            <ul>
                <li><a href="<?= APP_URL ?>/pages/inquiry.php">Plant Care Advice</a></li>
                <li><a href="<?= APP_URL ?>/pages/services.php">On-Site Consultations</a></li>
                <li><a href="<?= APP_URL ?>/pages/catalog.php">Care Guides & Tips</a></li>
                <li><a href="mailto:support@ecosprout.lk">support@ecosprout.lk</a></li>
            </ul>
        </div>
    </div>
    <div class="footer-bottom">
        <div class="container">
            <p>&copy; <?= date('Y') ?> EcoSprout Nursery Ltd. Built with PHP 8, MySQL, Vanilla JS & CSS.</p>
        </div>
    </div>
</footer>

<div id="toastContainer" class="toast-container"></div>
<script src="<?= APP_URL ?>/assets/js/cart.js"></script>
<script src="<?= APP_URL ?>/assets/js/main.js"></script>
<?php if (isset($extraScript)): ?>
    <script src="<?= APP_URL ?>/assets/js/<?= htmlspecialchars($extraScript) ?>"></script>
<?php endif; ?>
</body>
</html>
