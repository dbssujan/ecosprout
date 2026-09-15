<?php
$pageTitle = 'Login';
require_once __DIR__ . '/../includes/header.php';
$csrf = generate_csrf_token();
$error = $_GET['error'] ?? '';
?>

<div class="container" style="padding-top: 4rem; max-width: 440px;">
    <div style="background: var(--surface-white); border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 2.5rem; box-shadow: var(--shadow-sm);">
        <h2 style="margin-bottom: 0.5rem; text-align: center;">Welcome Back</h2>
        <p style="text-align: center; color: var(--text-muted); font-size: 0.9rem; margin-bottom: 1.5rem;">Access your EcoSprout customer or staff portal</p>

        <?php if ($error): ?>
            <div style="background: #fee2e2; color: var(--danger); padding: 10px; border-radius: var(--radius-sm); margin-bottom: 1rem; font-size: 0.85rem;">
                Invalid email or password. Please try again.
            </div>
        <?php endif; ?>

        <form method="POST" action="<?= APP_URL ?>/api/auth.php">
            <input type="hidden" name="action" value="login">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            
            <div class="form-group">
                <label>Email Address</label>
                <input type="email" name="email" class="form-control" placeholder="user@domain.com" required>
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" class="form-control" placeholder="••••••••" required>
            </div>
            <button type="submit" class="btn btn-primary" style="width: 100%;">Sign In</button>
        </form>

        <div style="margin-top: 1.5rem; text-align: center; font-size: 0.85rem; color: var(--text-muted);">
            Don't have an account? <a href="<?= APP_URL ?>/pages/register.php">Register now</a>
        </div>

        <div style="margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid var(--border-color); font-size: 0.75rem; color: var(--text-muted);">
            <strong>Demo Accounts:</strong><br>
            • Admin: <code>admin@ecosprout.lk</code> | <code>Password@123</code><br>
            • Staff: <code>staff@ecosprout.lk</code> | <code>Password@123</code><br>
            • Customer: <code>customer@gmail.com</code> | <code>Password@123</code>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
