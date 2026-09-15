<?php
// Initialize session, database connection, and system helpers.
$pageTitle = 'Order Receipt';
require_once __DIR__ . '/../includes/header.php';
require_login();

$currentUser = current_user();
$orderId = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
$isNewOrder = isset($_GET['new']) && $_GET['new'] === '1';

if ($orderId <= 0) {
    header("Location: " . APP_URL . "/pages/profile.php");
    exit;
}

$pdo = Database::getConnection();

// Query order details ensuring customer ownership or staff authorization.
$orderStmt = $pdo->prepare("
    SELECT o.*, u.full_name, u.email 
    FROM orders o 
    JOIN users u ON o.user_id = u.id 
    WHERE o.id = :id
");
$orderStmt->execute([':id' => $orderId]);
$order = $orderStmt->fetch();

if (!$order) {
    header("Location: " . APP_URL . "/pages/profile.php");
    exit;
}

if ((int)$order['user_id'] !== (int)$currentUser['id'] && !has_role(['admin', 'staff'])) {
    header("Location: " . APP_URL . "/pages/profile.php");
    exit;
}

// Fetch line items for this order.
$itemsStmt = $pdo->prepare("
    SELECT oi.*, p.name AS plant_name, p.image_url, c.name AS category_name
    FROM order_items oi
    JOIN plants p ON oi.plant_id = p.id
    JOIN categories c ON p.category_id = c.id
    WHERE oi.order_id = :id
    ORDER BY oi.id ASC
");
$itemsStmt->execute([':id' => $orderId]);
$orderItems = $itemsStmt->fetchAll();
?>

<style>
@media print {
    .navbar-wrapper, .footer-wrapper, .no-print {
        display: none !important;
    }
    body, .main-content {
        background: #fff !important;
        padding: 0 !important;
    }
    .receipt-box {
        border: none !important;
        box-shadow: none !important;
        padding: 0 !important;
    }
}
</style>

<div class="container" style="padding-top: 2rem; max-width: 800px; margin-bottom: 4rem;">
    <!-- Confirmation Banner for freshly placed order -->
    <?php if ($isNewOrder): ?>
        <div class="no-print" style="background: var(--primary-subtle); color: var(--primary-dark); padding: 1.25rem 1.5rem; border-radius: var(--radius-md); margin-bottom: 2rem; border: 1px solid var(--primary-light); display: flex; align-items: center; gap: 15px;">
            <div style="font-size: 2.2rem;">🎉</div>
            <div>
                <h3 style="margin: 0 0 4px 0; font-size: 1.15rem;">Order Placed Successfully!</h3>
                <p style="margin: 0; font-size: 0.9rem;">Thank you, <?= htmlspecialchars($order['full_name']) ?>. Your order #<?= $order['id'] ?> has been recorded and is currently being packed at our Kegalle nursery.</p>
            </div>
        </div>
    <?php endif; ?>

    <!-- Print and Back Navigation Buttons -->
    <div class="no-print" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
        <a href="<?= APP_URL ?>/pages/profile.php" class="btn btn-outline-sm">&larr; Back to Order History</a>
        <button onclick="window.print()" class="btn btn-secondary" style="border-color: var(--primary); color: var(--primary); gap: 6px;">
            🖨️ Print Receipt
        </button>
    </div>

    <!-- Official Purchase Invoice Container -->
    <div class="receipt-box" style="background: var(--surface-white); border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 2.5rem; box-shadow: var(--shadow-sm);">
        <!-- Invoice Header -->
        <div style="display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 1px solid var(--border-color); padding-bottom: 1.5rem; margin-bottom: 1.5rem;">
            <div>
                <h2 style="color: var(--primary-dark); margin: 0 0 4px 0; font-size: 1.6rem;">🌿 EcoSprout Nursery</h2>
                <p style="margin: 0; color: var(--text-muted); font-size: 0.85rem; line-height: 1.4;">
                    Colombo-Kandy Road, Kegalle, Sri Lanka<br>
                    Hotline: +94 35 222 3456 | Email: contact@ecosprout.lk
                </p>
            </div>
            <div style="text-align: right;">
                <span class="role-pill" style="font-size: 0.85rem; padding: 4px 10px; background: var(--bg-light); color: var(--text-dark); border: 1px solid var(--border-color);">
                    Invoice #<?= str_pad($order['id'], 5, '0', STR_PAD_LEFT) ?>
                </span>
                <div style="font-size: 0.85rem; color: var(--text-muted); margin-top: 6px;">
                    Date: <?= date('M d, Y · h:i A', strtotime($order['created_at'])) ?>
                </div>
            </div>
        </div>

        <!-- Customer and Delivery Coordinates -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-bottom: 2rem;">
            <div>
                <h4 style="font-size: 0.85rem; text-transform: uppercase; color: var(--text-muted); margin-bottom: 8px;">Customer Coordinates</h4>
                <strong style="color: var(--text-dark); font-size: 1.05rem;"><?= htmlspecialchars($order['full_name']) ?></strong><br>
                <span style="font-size: 0.9rem; color: var(--text-muted);"><?= htmlspecialchars($order['email']) ?></span><br>
                <span style="font-size: 0.9rem; color: var(--text-muted);">Phone: <?= htmlspecialchars($order['contact_phone']) ?></span>
            </div>
            <div>
                <h4 style="font-size: 0.85rem; text-transform: uppercase; color: var(--text-muted); margin-bottom: 8px;">Delivery Destination</h4>
                <div style="font-size: 0.9rem; color: var(--text-dark); line-height: 1.5;">
                    <?= nl2br(htmlspecialchars($order['shipping_address'])) ?>
                </div>
                <?php if (!empty($order['order_notes'])): ?>
                    <small style="color: var(--text-muted); font-style: italic; display: block; margin-top: 4px;">
                        Note: <?= htmlspecialchars($order['order_notes']) ?>
                    </small>
                <?php endif; ?>
            </div>
        </div>

        <!-- Payment Meta Badges -->
        <div style="background: var(--bg-light); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 1rem 1.5rem; margin-bottom: 2rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
            <div>
                <span style="font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); display: block;">Payment Method</span>
                <strong>
                    <?php if ($order['payment_method'] === 'card'): ?>
                        💳 Online Card Gateway (<?= htmlspecialchars($order['card_brand'] ?? 'Card') ?> <?= !empty($order['card_last4']) ? '**** ' . htmlspecialchars($order['card_last4']) : '' ?>)
                    <?php else: ?>
                        💵 Cash on Delivery (COD)
                    <?php endif; ?>
                </strong>
            </div>
            <?php if (!empty($order['transaction_id'])): ?>
                <div>
                    <span style="font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); display: block;">Transaction Reference</span>
                    <code style="font-weight: 700; color: var(--primary);"><?= htmlspecialchars($order['transaction_id']) ?></code>
                </div>
            <?php endif; ?>
            <div>
                <span style="font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); display: block;">Payment Status</span>
                <?php if ($order['payment_status'] === 'completed'): ?>
                    <span class="role-pill" style="background: var(--primary-subtle); color: var(--primary-dark); font-weight: bold;">PAID</span>
                <?php else: ?>
                    <span class="role-pill" style="background: #fef3c7; color: #b45309; font-weight: bold;">PENDING PAYMENT</span>
                <?php endif; ?>
            </div>
            <div>
                <span style="font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); display: block;">Fulfillment</span>
                <span class="role-pill" style="background: #e0e7ff; color: #3730a3; font-weight: bold;">
                    <?= strtoupper(htmlspecialchars($order['order_status'])) ?>
                </span>
            </div>
        </div>

        <!-- Purchased Line Items Table -->
        <div class="table-responsive" style="margin-bottom: 1.5rem;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Item Description</th>
                        <th>Category</th>
                        <th style="text-align: center;">Unit Price</th>
                        <th style="text-align: center;">Qty</th>
                        <th style="text-align: right;">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orderItems as $it): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($it['plant_name']) ?></strong>
                            </td>
                            <td><?= htmlspecialchars($it['category_name']) ?></td>
                            <td style="text-align: center;"><?= APP_CURRENCY ?> <?= number_format($it['unit_price'], 2) ?></td>
                            <td style="text-align: center;"><?= $it['quantity'] ?></td>
                            <td style="text-align: right;"><strong><?= APP_CURRENCY ?> <?= number_format($it['subtotal'], 2) ?></strong></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Totals Breakdown -->
        <div style="margin-left: auto; max-width: 320px; border-top: 1px solid var(--border-color); padding-top: 1rem;">
            <div style="display: flex; justify-content: space-between; margin-bottom: 6px; font-size: 0.95rem;">
                <span style="color: var(--text-muted);">Subtotal:</span>
                <span><?= APP_CURRENCY ?> <?= number_format($order['total_amount'], 2) ?></span>
            </div>
            <div style="display: flex; justify-content: space-between; margin-bottom: 6px; font-size: 0.95rem;">
                <span style="color: var(--text-muted);">Delivery (Kegalle Hub):</span>
                <span style="color: var(--primary); font-weight: 700;">FREE</span>
            </div>
            <hr style="border: none; border-top: 1px solid var(--border-color); margin: 8px 0;">
            <div style="display: flex; justify-content: space-between; font-size: 1.3rem; font-weight: 700; color: var(--primary-dark);">
                <span>Grand Total:</span>
                <span><?= APP_CURRENCY ?> <?= number_format($order['total_amount'], 2) ?></span>
            </div>
        </div>

        <!-- Receipt Footer Note -->
        <div style="margin-top: 2.5rem; padding-top: 1.5rem; border-top: 1px solid var(--border-color); text-align: center; color: var(--text-muted); font-size: 0.8rem;">
            Thank you for cultivating green living with EcoSprout Kegalle.<br>
            Please inspect plants upon arrival. For botanical care guidance, visit our Advisory Desk or contact support.
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>