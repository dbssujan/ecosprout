<?php
// Initialize session, database connection, and helper utilities.
$pageTitle = 'My Profile & Order History';
$extraScript = 'auth.js?v=' . time();
require_once __DIR__ . '/../includes/header.php';
require_login();

$user = current_user();
$pdo = Database::getConnection();

$profileMsg = '';
$profileError = '';
$pwMsg = '';
$pwError = '';

// Handle profile information update.
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
                    ':name'    => $fullName,
                    ':phone'   => $phone,
                    ':address' => $address,
                    ':id'      => $user['id']
                ]);
                $_SESSION['user_name'] = $fullName;
                $profileMsg = 'Profile details updated successfully.';
            } catch (PDOException $e) {
                $profileError = 'Failed to update profile details.';
            }
        }
    }
}

// Handle secure password change.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf)) {
        $pwError = 'Invalid security token. Please try again.';
    } else {
        $currentPw = $_POST['current_password'] ?? '';
        $newPw = $_POST['new_password'] ?? '';
        $confirmPw = $_POST['confirm_new_password'] ?? '';

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

// Fetch fresh user record.
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $user['id']]);
$userData = $stmt->fetch();

// Fetch customer purchase orders history sorted by newest first.
$ordersStmt = $pdo->prepare("
    SELECT o.*, 
           (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) AS total_items_count
    FROM orders o 
    WHERE o.user_id = :uid 
    ORDER BY o.created_at DESC
");
$ordersStmt->execute([':uid' => $user['id']]);
$userOrders = $ordersStmt->fetchAll();

// Fetch customer submitted inquiries history.
$inqStmt = $pdo->prepare("
    SELECT * FROM inquiries 
    WHERE user_id = :uid OR customer_email = :email 
    ORDER BY created_at DESC
");
$inqStmt->execute([':uid' => $user['id'], ':email' => $userData['email']]);
$userInquiries = $inqStmt->fetchAll();

$csrf = generate_csrf_token();
?>

<div class="container" style="padding-top: 2rem; margin-bottom: 4rem;">
    <h1 class="section-title">Account Profile & Customer Portal</h1>
    <p class="section-subtitle">Manage personal coordinates, review past nursery orders, and track agronomy consultations.</p>

    <!-- Top Grid: Personal Coordinates & Security Credentials -->
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2.5rem; margin-bottom: 3.5rem;">
        <!-- Profile Details Form -->
        <div style="background: var(--surface-white); border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 2rem; box-shadow: var(--shadow-sm);">
            <h2 style="font-size: 1.3rem; margin-bottom: 1.2rem; color: var(--primary-dark);">Personal Information</h2>
            <?php if ($profileMsg): ?>
                <div style="background: var(--primary-subtle); color: var(--primary-dark); padding: 10px 14px; border-radius: var(--radius-sm); margin-bottom: 1rem; font-size: 0.9rem;">
                    <?= htmlspecialchars($profileMsg) ?>
                </div>
            <?php endif; ?>
            <?php if ($profileError): ?>
                <div style="background: #fee2e2; color: var(--danger); padding: 10px 14px; border-radius: var(--radius-sm); margin-bottom: 1rem; font-size: 0.9rem;">
                    <?= htmlspecialchars($profileError) ?>
                </div>
            <?php endif; ?>
            <form method="POST" action="">
                <input type="hidden" name="update_profile" value="1">
                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" class="form-control" value="<?= htmlspecialchars($userData['email']) ?>" readonly style="background: var(--bg-light); color: var(--text-muted);">
                    <small style="font-size: 0.75rem; color: var(--text-muted);">Account email cannot be modified.</small>
                </div>
                <div class="form-group">
                    <label>Assigned Role</label>
                    <div>
                        <span class="role-pill" style="text-transform: uppercase; font-weight: 700;"><?= htmlspecialchars($userData['role']) ?></span>
                    </div>
                </div>
                <div class="form-group">
                    <label>Full Legal Name</label>
                    <input type="text" name="full_name" class="form-control" value="<?= htmlspecialchars($userData['full_name']) ?>" required>
                </div>
                <div class="form-group">
                    <label>Contact Phone Number</label>
                    <input type="tel" name="phone" class="form-control" value="<?= htmlspecialchars($userData['phone'] ?? '') ?>" placeholder="+94 7X XXX XXXX">
                </div>
                <div class="form-group">
                    <label>Delivery Address</label>
                    <textarea name="address" class="form-control" rows="3" placeholder="Street, town, postal area..."><?= htmlspecialchars($userData['address'] ?? '') ?></textarea>
                </div>
                <button type="submit" class="btn btn-primary" style="width: 100%;">Save Profile Changes</button>
            </form>
        </div>

        <!-- Security & Password Change Form -->
        <div style="background: var(--surface-white); border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 2rem; box-shadow: var(--shadow-sm);">
            <h2 style="font-size: 1.3rem; margin-bottom: 1.2rem; color: var(--primary-dark);">Security & Credentials</h2>
            <?php if ($pwMsg): ?>
                <div style="background: var(--primary-subtle); color: var(--primary-dark); padding: 10px 14px; border-radius: var(--radius-sm); margin-bottom: 1rem; font-size: 0.9rem;">
                    <?= htmlspecialchars($pwMsg) ?>
                </div>
            <?php endif; ?>
            <?php if ($pwError): ?>
                <div style="background: #fee2e2; color: var(--danger); padding: 10px 14px; border-radius: var(--radius-sm); margin-bottom: 1rem; font-size: 0.9rem;">
                    <?= htmlspecialchars($pwError) ?>
                </div>
            <?php endif; ?>
            <form method="POST" action="" id="profilePwForm">
                <input type="hidden" name="change_password" value="1">
                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                <div class="form-group">
                    <label>Current Password</label>
                    <input type="password" name="current_password" class="form-control" placeholder="••••••••" required>
                </div>
                <div class="form-group">
                    <label>New Password (minimum 8 characters)</label>
                    <input type="password" name="new_password" id="profileNewPassword" class="form-control" placeholder="••••••••" required>
                    <div style="height: 4px; background: var(--border-color); border-radius: 2px; margin-top: 6px; overflow: hidden;">
                        <div id="profileStrengthMeter" style="height: 100%; width: 0%; transition: all 0.3s ease;"></div>
                    </div>
                    <small id="profileStrengthLabel" style="display: block; font-size: 0.75rem; margin-top: 4px;"></small>
                </div>
                <div class="form-group">
                    <label>Confirm New Password</label>
                    <input type="password" name="confirm_new_password" id="profileConfirmPassword" class="form-control" placeholder="••••••••" required>
                    <small id="profileMatchMsg" style="display: none; font-size: 0.8rem; margin-top: 4px;"></small>
                </div>
                <button type="submit" class="btn btn-secondary" style="width: 100%; border-color: var(--primary); color: var(--primary);">Update Password</button>
            </form>
            <div style="margin-top: 2rem; padding-top: 1.5rem; border-top: 1px solid var(--border-color); font-size: 0.85rem; color: var(--text-muted);">
                Registered on: <strong><?= date('F d, Y', strtotime($userData['created_at'])) ?></strong>
            </div>
        </div>
    </div>

    <!-- Middle Section: Customer Order History -->
    <div style="margin-bottom: 3.5rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
            <div>
                <h2 style="font-size: 1.35rem; margin: 0; color: var(--text-dark);">Past Purchase Orders (<?= count($userOrders) ?>)</h2>
                <p style="margin: 4px 0 0 0; color: var(--text-muted); font-size: 0.9rem;">Track orders, payment states, and access official purchase invoices.</p>
            </div>
            <a href="<?= APP_URL ?>/pages/catalog.php" class="btn btn-primary-sm">+ Order Plants</a>
        </div>

        <?php if (empty($userOrders)): ?>
            <div style="background: var(--surface-white); border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 3rem; text-align: center;">
                <div style="font-size: 3rem; margin-bottom: 0.5rem;">🛍️</div>
                <h3 style="margin-bottom: 0.5rem; font-size: 1.15rem;">No orders placed yet</h3>
                <p style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 1.2rem;">Browse our nursery catalog to find tropical species for your home.</p>
                <a href="<?= APP_URL ?>/pages/catalog.php" class="btn btn-primary-sm">Browse Catalog</a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Order #</th>
                            <th>Date & Time</th>
                            <th>Items</th>
                            <th>Payment Details</th>
                            <th>Fulfillment</th>
                            <th>Total Amount</th>
                            <th style="text-align: right;">Receipt</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($userOrders as $ord): ?>
                            <tr>
                                <td><strong>#<?= $ord['id'] ?></strong></td>
                                <td><?= date('Y-m-d H:i', strtotime($ord['created_at'])) ?></td>
                                <td><?= (int)$ord['total_items_count'] ?> item(s)</td>
                                <td>
                                    <strong><?= $ord['payment_method'] === 'card' ? '💳 Card Gateway' : '💵 Cash on Delivery' ?></strong><br>
                                    <?php if ($ord['payment_status'] === 'completed'): ?>
                                        <span class="role-pill" style="background: var(--primary-subtle); color: var(--primary-dark); font-weight: bold;">Paid</span>
                                    <?php else: ?>
                                        <span class="role-pill" style="background: #fef3c7; color: #b45309; font-weight: bold;">Pending</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="role-pill" style="background: #e0e7ff; color: #3730a3; font-weight: 600;">
                                        <?= ucfirst(htmlspecialchars($ord['order_status'])) ?>
                                    </span>
                                </td>
                                <td><strong style="color: var(--primary-dark);"><?= APP_CURRENCY ?> <?= number_format($ord['total_amount'], 2) ?></strong></td>
                                <td style="text-align: right; white-space: nowrap;">
                                    <a href="<?= APP_URL ?>/pages/order_receipt.php?order_id=<?= $ord['id'] ?>" class="btn btn-primary-sm" style="padding: 4px 10px; font-size: 0.8rem;">
                                        View Receipt
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Bottom Section: Customer Submitted Inquiries -->
    <div>
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
            <div>
                <h2 style="font-size: 1.35rem; margin: 0; color: var(--text-dark);">My Plant Consultations (<?= count($userInquiries) ?>)</h2>
                <p style="margin: 4px 0 0 0; color: var(--text-muted); font-size: 0.9rem;">Review answers from our certified Kegalle agronomists.</p>
            </div>
            <a href="<?= APP_URL ?>/pages/inquiry.php" class="btn btn-outline-sm">+ Submit Consultation</a>
        </div>

        <?php if (empty($userInquiries)): ?>
            <div style="background: var(--surface-white); border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 3rem; text-align: center;">
                <div style="font-size: 3rem; margin-bottom: 0.5rem;">🪴</div>
                <h3 style="margin-bottom: 0.5rem; font-size: 1.15rem;">No consultations logged</h3>
                <p style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 1.2rem;">Have questions regarding plant health or soil? Ask an agronomist today.</p>
                <a href="<?= APP_URL ?>/pages/inquiry.php" class="btn btn-primary-sm">Ask an Agronomist</a>
            </div>
        <?php else: ?>
            <div style="display: flex; flex-direction: column; gap: 1rem;">
                <?php foreach ($userInquiries as $inq): ?>
                    <div style="background: var(--surface-white); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 1.5rem; box-shadow: var(--shadow-sm);">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                            <strong style="color: var(--primary-dark); font-size: 1.05rem;"><?= htmlspecialchars($inq['subject']) ?></strong>
                            <?php if ($inq['status'] === 'resolved'): ?>
                                <span class="role-pill" style="background: var(--primary-subtle); color: var(--primary-dark); font-weight: bold;">Resolved</span>
                            <?php elseif ($inq['status'] === 'in_progress'): ?>
                                <span class="role-pill" style="background: #e0e7ff; color: #3730a3; font-weight: bold;">In Progress</span>
                            <?php else: ?>
                                <span class="role-pill" style="background: #fef3c7; color: #b45309; font-weight: bold;">Open / Pending</span>
                            <?php endif; ?>
                        </div>
                        <p style="color: var(--text-dark); font-size: 0.9rem; margin-bottom: 10px;">
                            <strong>Your Question:</strong> "<?= htmlspecialchars($inq['message']) ?>"
                        </p>
                        <small style="color: var(--text-muted); display: block; margin-bottom: 8px;">
                            Submitted: <?= date('M d, Y · h:i A', strtotime($inq['created_at'])) ?>
                        </small>
                        <?php if (!empty($inq['staff_reply'])): ?>
                            <div style="background: var(--bg-light); border-left: 3px solid var(--primary); padding: 10px 14px; border-radius: var(--radius-sm); font-size: 0.88rem; margin-top: 10px;">
                                <strong style="color: var(--primary); display: block; margin-bottom: 2px;">Agronomist Solution:</strong>
                                <?= nl2br(htmlspecialchars($inq['staff_reply'])) ?>
                                <?php if (!empty($inq['replied_at'])): ?>
                                    <small style="color: var(--text-muted); display: block; margin-top: 4px;">Answered on <?= date('M d, Y', strtotime($inq['replied_at'])) ?></small>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>