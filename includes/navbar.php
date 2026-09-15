<?php
$currentUser = current_user();
$cartCount = isset($_SESSION['cart']) ? array_sum($_SESSION['cart']) : 0;
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<div class="nav-outer-wrapper">
    <header class="floating-navbar">
        <!-- Logo -->
        <a href="<?= APP_URL ?>/index.php" class="brand-link">
            <div class="brand-icon-box"><svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path></svg></div>
            <div class="brand-text-group">
                <span class="brand-title">Eco<span class="brand-accent">Sprout</span></span>
                <span class="brand-tag">Kegalle Hub</span>
            </div>
        </a>

        <!-- Desktop Navigation Links -->
        <nav class="nav-pill-menu">
            <ul class="nav-pill-list">
                <li><a href="<?= APP_URL ?>/index.php" class="nav-item <?= $currentPage === 'index.php' ? 'active' : '' ?>">Home</a></li>
                <li><a href="<?= APP_URL ?>/pages/catalog.php" class="nav-item <?= $currentPage === 'catalog.php' ? 'active' : '' ?>">Plants</a></li>
                <li><a href="<?= APP_URL ?>/pages/services.php" class="nav-item <?= $currentPage === 'services.php' ? 'active' : '' ?>">Services</a></li>
                <li><a href="<?= APP_URL ?>/pages/workshops.php" class="nav-item <?= $currentPage === 'workshops.php' ? 'active' : '' ?>">Workshops</a></li>
                <li><a href="<?= APP_URL ?>/pages/inquiry.php" class="nav-item <?= $currentPage === 'inquiry.php' ? 'active' : '' ?>">Care Advice</a></li>
            </ul>
        </nav>

        <!-- Right Side Controls -->
        <div class="nav-controls">
            <!-- Modern Cart Capsule -->
            <a href="<?= APP_URL ?>/pages/cart.php" class="cart-pill-btn">
                <span class="cart-text">Cart</span>
                <span class="cart-count-bubble" id="cartBadgeCount"><?= $cartCount ?></span>
            </a>

            <?php if ($currentUser): ?>
                <!-- User Account Dropdown -->
                <div class="user-profile-widget">
                    <button class="user-chip-btn" id="userMenuBtn" type="button">
                        <span class="user-initial"><?= strtoupper(substr($currentUser['full_name'], 0, 1)) ?></span>
                        <span class="user-firstname"><?= htmlspecialchars(explode(' ', $currentUser['full_name'])[0]) ?></span>
                    </button>
                    <div class="glass-dropdown" id="userDropdownMenu">
                        <div class="dropdown-header">
                            <strong><?= htmlspecialchars($currentUser['full_name']) ?></strong>
                            <small><?= htmlspecialchars($currentUser['email']) ?></small>
                        </div>
                        <div class="dropdown-line"></div>
                        <a href="<?= APP_URL ?>/pages/profile.php">My Profile</a>
                        <?php if (has_role(['admin'])): ?>
                            <a href="<?= APP_URL ?>/pages/admin_dashboard.php">Admin Dashboard</a>
                        <?php endif; ?>
                        <?php if (has_role(['admin', 'staff'])): ?>
                            <a href="<?= APP_URL ?>/pages/staff_inventory.php">Manage Plants</a>
                        <?php endif; ?>
                        <div class="dropdown-line"></div>
                        <a href="<?= APP_URL ?>/api/auth.php?action=logout" class="dropdown-logout">Sign Out</a>
                    </div>
                </div>
            <?php else: ?>
                <!-- Login & Sign Up -->
                <div class="guest-cta-group">
                    <a href="<?= APP_URL ?>/pages/login.php" class="cta-login">Sign In</a>
                    <a href="<?= APP_URL ?>/pages/register.php" class="cta-signup">Join</a>
                </div>
            <?php endif; ?>
        </div>
    </header>
</div>