<?php
$pageTitle = 'My Profile';
$extraScript = 'auth.js?v=' . time();
require_once __DIR__ . '/../includes/header.php';
require_login();

$user = current_user();
$pdo = Database::getConnection();

$profileMsg = '';
$profileError = '';
$pwMsg = '';
$pwError = '';

// 1. Handle Profile Information Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf)) {
        $profileError = 'Invalid security token. Please try again.';
    } else {
        $fullName = sanitize($_POST['full_name'] ?? '');
        $phone = sanitize($_POST['phone'] ?? '');
        $address = sanitize($_POST['address'] ?? '');

        if (empty($fullName)) {
            $profileError = 'Full legal name is required.';
        } else {
            try {
                $stmt = $pdo->prepare("UPDATE users SET full_name = :name, phone = :phone, address = :address WHERE id = :id");
                $stmt->execute([
                    ':name' => $fullName,
                    ':phone' => $phone,
                    ':address' => $address,
                    ':id' => $user['id']
                ]);
                // Update active session name so navbar updates instantly
                $_SESSION['user_name'] = $fullName;
                $profileMsg = 'Profile details updated successfully.';
            } catch (PDOException $e) {
                $profileError = 'Failed to update profile details.';
            }
        }
    }
}

// 2. Handle Secure Password Change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf)) {
        $pwError = 'Invalid security token. Please try again.';
    } else {
        $currentPw = $_POST['current_password'] ?? '';
        $newPw = $_POST['new_password'] ?? '';
        $confirmPw = $_POST['confirm_new_password'] ?? '';

        // Fetch the currently stored hash from MySQL
        $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $user['id']]);
        $storedHash = $stmt->fetchColumn();

        if (!$storedHash || !password_verify($currentPw, $storedHash)) {
            $pwError = 'Incorrect current password.';
        } elseif (strlen($newPw) < 8) {
            $pwError = 'New password must be at least 8 characters long.';
        } elseif ($newPw !== $confirmPw) {
            $pwError = 'New password and confirmation do not match.';
        } else {
            try {
                $newHash = password_hash($newPw, PASSWORD_DEFAULT);
                $upd = $pdo->prepare("UPDATE users SET password_hash = :hash WHERE id = :id");
                $upd->execute([':hash' => $newHash, ':id' => $user['id']]);
                $pwMsg = 'Password changed successfully.';
            } catch (PDOException $e) {
                $pwError = 'Failed to update password.';
            }
        }
    }
}

// Fetch fresh user record
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $user['id']]);
$userData = $stmt->fetch();

// Fetch customer's recent orders
$ordersStmt = $pdo->prepare("SELECT * FROM orders WHERE user_id = :uid ORDER BY id DESC LIMIT 5");
$ordersStmt->execute([':uid' => $user['id']]);
$userOrders = $ordersStmt->fetchAll();

$csrf = generate_csrf_token();
?>

<div class="container" style="padding-top: 2rem;">
    <h1 class="section-title">Account Profile</h1>
    <p class="section-subtitle">Manage your personal contact details, security credentials, and account activity.</p>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2.5rem; margin-bottom: 3rem;">

        <!-- Profile Details Form -->
        <div
            style="background: var(--surface-white); border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 2rem;">
            <h2 style="font-size: 1.3rem; margin-bottom: 1.2rem;">Personal Information</h2>

            <?php if ($profileMsg): ?>
                <div
                    style="background: var(--primary-subtle); color: var(--primary-dark); padding: 10px 14px; border-radius: var(--radius-sm); margin-bottom: 1rem; font-size: 0.9rem;">
                    <?= htmlspecialchars($profileMsg) ?>
                </div>
            <?php endif; ?>

            <?php if ($profileError): ?>
                <div
                    style="background: #fee2e2; color: var(--danger); padding: 10px 14px; border-radius: var(--radius-sm); margin-bottom: 1rem; font-size: 0.9rem;">
                    <?= htmlspecialchars($profileError) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <input type="hidden" name="update_profile" value="1">
                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">

                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" class="form-control" value="<?= htmlspecialchars($userData['email']) ?>"
                        readonly style="background: var(--bg-light); color: var(--text-muted);">
                    <small style="font-size: 0.75rem; color: var(--text-muted);">Account email cannot be
                        modified.</small>
                </div>

                <div class="form-group">
                    <label>Assigned Role</label>
                    <div>
                        <span class="role-pill"
                            style="text-transform: uppercase; font-weight: 700;"><?= htmlspecialchars($userData['role']) ?></span>
                    </div>
                </div>

                <div class="form-group">
                    <label>Full Legal Name</label>
                    <input type="text" name="full_name" class="form-control"
                        value="<?= htmlspecialchars($userData['full_name']) ?>" required>
                </div>

                <div class="form-group">
                    <label>Contact Phone Number</label>
                    <input type="tel" name="phone" class="form-control"
                        value="<?= htmlspecialchars($userData['phone'] ?? '') ?>" placeholder="+94 7X XXX XXXX">
                </div>

                <div class="form-group">
                    <label>Delivery Address</label>
                    <textarea name="address" class="form-control" rows="3"
                        placeholder="Street, town, postal area..."><?= htmlspecialchars($userData['address'] ?? '') ?></textarea>
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%;">Save Profile Changes</button>
            </form>
        </div>

        <!-- Password Change Form -->
        <div
            style="background: var(--surface-white); border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 2rem;">
            <h2 style="font-size: 1.3rem; margin-bottom: 1.2rem;">Security & Password</h2>

            <?php if ($pwMsg): ?>
                <div
                    style="background: var(--primary-subtle); color: var(--primary-dark); padding: 10px 14px; border-radius: var(--radius-sm); margin-bottom: 1rem; font-size: 0.9rem;">
                    <?= htmlspecialchars($pwMsg) ?>
                </div>
            <?php endif; ?>

            <?php if ($pwError): ?>
                <div
                    style="background: #fee2e2; color: var(--danger); padding: 10px 14px; border-radius: var(--radius-sm); margin-bottom: 1rem; font-size: 0.9rem;">
                    <?= htmlspecialchars($pwError) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <input type="hidden" name="change_password" value="1">
                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">

                <div class="form-group">
                    <label>Current Password</label>
                    <input type="password" name="current_password" class="form-control" placeholder="••••••••" required>
                </div>

                <div class="form-group">
                    <label>New Password (minimum 8 characters)</label>
                    <input type="password" name="new_password" id="profileNewPassword" class="form-control"
                        placeholder="••••••••" required>
                    <div
                        style="height: 4px; background: var(--border-color); border-radius: 2px; margin-top: 6px; overflow: hidden;">
                        <div id="profileStrengthMeter" style="height: 100%; width: 0%; transition: all 0.3s ease;">
                        </div>
                    </div>
                    <small id="profileStrengthLabel"
                        style="display: block; font-size: 0.75rem; margin-top: 4px;"></small>
                </div>

                <div class="form-group">
                    <label>Confirm New Password</label>
                    <input type="password" name="confirm_new_password" id="profileConfirmPassword" class="form-control"
                        placeholder="••••••••" required>
                    <small id="profileMatchMsg" style="display: none; font-size: 0.8rem; margin-top: 4px;"></small>
                </div>

                <button type="submit" class="btn btn-secondary"
                    style="width: 100%; border-color: var(--primary); color: var(--primary);">Update Password</button>
            </form>

            <div style="margin-top: 2rem; padding-top: 1.5rem; border-top: 1px solid var(--border-color);">
                <div style="font-size: 0.85rem; color: var(--text-muted);">
                    Member Since: <strong><?= date('F d, Y', strtotime($userData['created_at'])) ?></strong>
                </div>
            </div>
        </div>

    </div>

    <!-- Recent Account Orders -->
    <?php if (!empty($userOrders)): ?>
        <div style="margin-bottom: 3rem;">
            <h2 style="font-size: 1.3rem; margin-bottom: 1rem;">My Recent Orders</h2>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Order #</th>
                            <th>Total Amount</th>
                            <th>Payment Method</th>
                            <th>Order Status</th>
                            <th>Placed On</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($userOrders as $ord): ?>
                            <tr>
                                <td>#<?= $ord['id'] ?></td>
                                <td><?= APP_CURRENCY ?>         <?= number_format($ord['total_amount'], 2) ?></td>
                                <td><?= strtoupper($ord['payment_method']) ?></td>
                                <td><span class="role-pill"
                                        style="background: var(--primary-subtle); color: var(--primary-dark);"><?= htmlspecialchars($ord['order_status']) ?></span>
                                </td>
                                <td><?= date('Y-m-d H:i', strtotime($ord['created_at'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>