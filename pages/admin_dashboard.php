<?php
$pageTitle = 'Admin Dashboard';
require_once __DIR__ . '/../includes/header.php';
require_role(['admin']);

$pdo = Database::getConnection();

// Aggregate stats
$totalUsers = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalPlants = $pdo->query("SELECT COUNT(*) FROM plants")->fetchColumn();
$totalOrders = $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
$totalRevenue = $pdo->query("SELECT COALESCE(SUM(total_amount), 0) FROM orders")->fetchColumn();

// Recent orders
$orders = $pdo->query("SELECT o.*, u.full_name AS customer_name FROM orders o JOIN users u ON o.user_id = u.id ORDER BY o.id DESC LIMIT 5")->fetchAll();

// Inquiries needing reply
$inquiries = $pdo->query("SELECT * FROM inquiries WHERE status = 'open' ORDER BY id DESC LIMIT 5")->fetchAll();
?>

<div class="container" style="padding-top: 2rem;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
        <div>
            <h1 class="section-title">Administrator Management Hub</h1>
            <p class="section-subtitle" style="margin-bottom: 0;">EcoSprout Nursery operations, sales analytics, and role control.</p>
        </div>
        <a href="<?= APP_URL ?>/pages/staff_inventory.php" class="btn btn-primary">+ Manage Inventory</a>
    </div>

    
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1.5rem; margin-bottom: 3rem;">
        <div style="background: var(--surface-white); border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 1.5rem;">
            <div style="font-size: 0.85rem; color: var(--text-muted); text-transform: uppercase; font-weight: 600;">Total Revenue</div>
            <div style="font-size: 1.8rem; font-weight: 700; color: var(--primary-dark); margin-top: 4px;"><?= APP_CURRENCY ?> <?= number_format($totalRevenue, 2) ?></div>
        </div>
        <div style="background: var(--surface-white); border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 1.5rem;">
            <div style="font-size: 0.85rem; color: var(--text-muted); text-transform: uppercase; font-weight: 600;">Customer Orders</div>
            <div style="font-size: 1.8rem; font-weight: 700; color: var(--text-dark); margin-top: 4px;"><?= $totalOrders ?></div>
        </div>
        <div style="background: var(--surface-white); border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 1.5rem;">
            <div style="font-size: 0.85rem; color: var(--text-muted); text-transform: uppercase; font-weight: 600;">Plant Varieties</div>
            <div style="font-size: 1.8rem; font-weight: 700; color: var(--text-dark); margin-top: 4px;"><?= $totalPlants ?></div>
        </div>
        <div style="background: var(--surface-white); border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 1.5rem;">
            <div style="font-size: 0.85rem; color: var(--text-muted); text-transform: uppercase; font-weight: 600;">Registered Users</div>
            <div style="font-size: 1.8rem; font-weight: 700; color: var(--text-dark); margin-top: 4px;"><?= $totalUsers ?></div>
        </div>
    </div>

    
    <div style="margin-bottom: 3rem;">
        <h2 style="font-size: 1.3rem; margin-bottom: 1rem;">Recent Orders</h2>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Order #</th>
                        <th>Customer</th>
                        <th>Amount</th>
                        <th>Payment</th>
                        <th>Status</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $ord): ?>
                        <tr>
                            <td>#<?= $ord['id'] ?></td>
                            <td><?= htmlspecialchars($ord['customer_name']) ?></td>
                            <td><?= APP_CURRENCY ?> <?= number_format($ord['total_amount'], 2) ?></td>
                            <td><?= strtoupper($ord['payment_method']) ?></td>
                            <td><span class="role-pill" style="background: var(--primary-subtle); color: var(--primary-dark);"><?= htmlspecialchars($ord['order_status']) ?></span></td>
                            <td><?= date('Y-m-d H:i', strtotime($ord['created_at'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    
    <div>
        <h2 style="font-size: 1.3rem; margin-bottom: 1rem;">Customer Inquiries Awaiting Response</h2>
        <?php if (empty($inquiries)): ?>
            <p class="text-muted">No pending customer inquiries.</p>
        <?php else: ?>
            <div style="display: flex; flex-direction: column; gap: 1rem;">
                <?php foreach ($inquiries as $inq): ?>
                    <div style="background: var(--surface-white); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 1.25rem;">
                        <div style="display: flex; justify-content: space-between;">
                            <strong><?= htmlspecialchars($inq['subject']) ?></strong>
                            <small class="text-muted"><?= htmlspecialchars($inq['customer_name']) ?> (<?= htmlspecialchars($inq['customer_email']) ?>)</small>
                        </div>
                        <p style="font-size: 0.9rem; color: var(--text-muted); margin: 8px 0;"><?= htmlspecialchars($inq['message']) ?></p>
                        <a href="<?= APP_URL ?>/pages/inquiry.php" class="btn btn-outline-sm">Reply in Advisory Desk</a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
