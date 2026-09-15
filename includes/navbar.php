<?php
$currentUser = current_user();
$cartCount = isset($_SESSION['cart']) ? array_sum($_SESSION['cart']) : 0;
?>
<header class="navbar-wrapper">
    <div class="container navbar-container">
        <a href="<?= APP_URL ?>/index.php" class="navbar-brand">
            <span class="brand-icon">🌱</span>
            <span class="brand-text">Eco<strong>Sprout</strong></span>
        </a>

        <button class="nav-toggle-btn" id="navToggleBtn" aria-label="Toggle navigation">
            <span></span>
            <span></span>
            <span></span>
        </button>

        <nav class="nav-menu" id="navMenu">
            <ul class="nav-links">
                <li><a href="<?= APP_URL ?>/index.php">Home</a></li>
                <li><a href="<?= APP_URL ?>/pages/catalog.php">Plant Catalogue</a></li>
                <li><a href="<?= APP_URL ?>/pages/services.php">Gardening Services</a></li>
                <li><a href="<?= APP_URL ?>/pages/workshops.php">Workshops & Events</a></li>
                <li><a href="<?= APP_URL ?>/pages/inquiry.php">Plant Care Advice</a></li>
            </ul>

            <div class="nav-actions">
                <a href="<?= APP_URL ?>/pages/cart.php" class="cart-btn" id="cartBtn">
                    <span class="cart-icon">🛒</span>
                    <span class="cart-label">Cart</span>
                    <span class="cart-badge" id="cartBadgeCount"><?= $cartCount ?></span>
                </a>

                <?php if ($currentUser): ?>
                    <div class="user-dropdown">
                        <button class="user-btn" id="userMenuBtn">
                            <span class="user-avatar"><?= strtoupper(substr($currentUser['full_name'], 0, 1)) ?></span>
                            <span class="user-name"><?= htmlspecialchars($currentUser['full_name']) ?></span>
                            <span class="role-pill"><?= htmlspecialchars($currentUser['role']) ?></span>
                        </button>
                        <div class="dropdown-menu" id="userDropdownMenu">
                            <a href="<?= APP_URL ?>/pages/profile.php">My Profile</a>
                            <?php if (has_role(['admin'])): ?>
                                <a href="<?= APP_URL ?>/pages/admin_dashboard.php">Admin Dashboard</a>
                            <?php endif; ?>
                            <?php if (has_role(['admin', 'staff'])): ?>
                                <a href="<?= APP_URL ?>/pages/staff_inventory.php">Manage Plant Inventory</a>
                            <?php endif; ?>
                            <a href="<?= APP_URL ?>/api/auth.php?action=logout">Logout</a>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="auth-buttons">
                        <a href="<?= APP_URL ?>/pages/login.php" class="btn btn-outline-sm">Login</a>
                        <a href="<?= APP_URL ?>/pages/register.php" class="btn btn-primary-sm">Register</a>
                    </div>
                <?php endif; ?>
            </div>
        </nav>
    </div>
</header>