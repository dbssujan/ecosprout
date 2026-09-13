<?php
$pageTitle = 'Register Account';
require_once __DIR__ . '/../includes/header.php';
$csrf = generate_csrf_token();
$error = $_GET['error'] ?? '';
?>

<div class="container" style="padding-top: 3rem; max-width: 500px;">
    <div style="background: var(--surface-white); border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 2.5rem; box-shadow: var(--shadow-sm);">
        <h2 style="margin-bottom: 0.5rem; text-align: center;">Join EcoSprout 🌿</h2>
        <p style="text-align: center; color: var(--text-muted); font-size: 0.9rem; margin-bottom: 1.5rem;">Create an account to order flora, book workshops, and access care guides</p>

        <?php if ($error === 'email_exists'): ?>
            <div style="background: #fee2e2; color: var(--danger); padding: 10px; border-radius: var(--radius-sm); margin-bottom: 1rem; font-size: 0.85rem;">
                That email is already registered. Please login instead.
            </div>
        <?php elseif ($error): ?>
            <div style="background: #fee2e2; color: var(--danger); padding: 10px; border-radius: var(--radius-sm); margin-bottom: 1rem; font-size: 0.85rem;">
                Registration error. Ensure your password is at least 6 characters.
            </div>
        <?php endif; ?>

        <form method="POST" action="<?= APP_URL ?>/api/auth.php">
            <input type="hidden" name="action" value="register">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            
            <div class="form-group">
                <label>Full Legal Name</label>
                <input type="text" name="full_name" class="form-control" placeholder="e.g. Ruwan Perera" required>
            </div>
            <div class="form-group">
                <label>Email Address</label>
                <input type="email" name="email" class="form-control" placeholder="you@domain.com" required>
            </div>
            <div class="form-group">
                <label>Password (min 6 characters)</label>
                <input type="password" name="password" class="form-control" placeholder="••••••••" minlength="6" required>
            </div>
            <div class="form-group">
                <label>Phone Number</label>
                <input type="tel" name="phone" class="form-control" placeholder="+94 7X XXX XXXX">
            </div>
            <div class="form-group">
                <label>Delivery Address</label>
                <textarea name="address" class="form-control" rows="2" placeholder="Address in Kegalle or Sri Lanka"></textarea>
            </div>
            <button type="submit" class="btn btn-primary" style="width: 100%;">Create Account</button>
        </form>

        <div style="margin-top: 1.5rem; text-align: center; font-size: 0.85rem; color: var(--text-muted);">
            Already have an account? <a href="<?= APP_URL ?>/pages/login.php">Sign In</a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
