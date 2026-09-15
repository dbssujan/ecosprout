<?php
// Initialize session, database connection, and system helpers.
$pageTitle = 'Order Summary';
require_once __DIR__ . '/../includes/header.php';
require_login();

$user = current_user();
$pdo = Database::getConnection();

// Fetch latest user profile details for delivery preview.
$userStmt = $pdo->prepare("SELECT * FROM users WHERE id = :id");
$userStmt->execute([':id' => $user['id']]);
$userData = $userStmt->fetch();

// Check if reviewing an existing finalized order.
$orderId = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
$existingOrder = null;
$existingItems = [];

if ($orderId > 0) {
    $ordStmt = $pdo->prepare("SELECT o.*, u.full_name, u.email FROM orders o JOIN users u ON o.user_id = u.id WHERE o.id = :id");
    $ordStmt->execute([':id' => $orderId]);
    $existingOrder = $ordStmt->fetch();

    if ($existingOrder && ((int)$existingOrder['user_id'] === (int)$user['id'] || has_role(['admin', 'staff']))) {
        $itemStmt = $pdo->prepare("SELECT oi.*, p.name AS plant_name, p.image_url, c.name AS category_name FROM order_items oi JOIN plants p ON oi.plant_id = p.id JOIN categories c ON p.category_id = c.id WHERE oi.order_id = :id");
        $itemStmt->execute([':id' => $orderId]);
        $existingItems = $itemStmt->fetchAll();
    }
}

// Retrieve active cart contents if reviewing pre-checkout summary.
$cartItems = [];
$totalAmount = 0.0;

if (!$existingOrder) {
    if (empty($_SESSION['cart'])) {
        header("Location: " . APP_URL . "/pages/cart.php");
        exit;
    }
    $ids = array_keys($_SESSION['cart']);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("SELECT p.*, c.name AS category_name FROM plants p JOIN categories c ON p.category_id = c.id WHERE p.id IN ($placeholders)");
    $stmt->execute($ids);
    $plants = $stmt->fetchAll();

    foreach ($plants as $plant) {
        $qty = (int)($_SESSION['cart'][$plant['id']] ?? 0);
        if ($qty > 0) {
            $sub = $qty * (float)$plant['price'];
            $totalAmount += $sub;
            $cartItems[] = [
                'plant'    => $plant,
                'qty'      => $qty,
                'subtotal' => $sub
            ];
        }
    }
}
?>

<div class="container" style="padding-top: 2rem;">
    <!-- Breadcrumbs Navigation -->
    <div style="margin-bottom: 1.5rem; font-size: 0.9rem; color: var(--text-muted);">
        <a href="<?= APP_URL ?>/pages/cart.php" style="color: var(--primary);">&larr; Back to Basket</a>
    </div>

    <?php if ($existingOrder): ?>
        <!-- Summary of Existing Finalized Order -->
        <h1 class="section-title">Order Summary #<?= $existingOrder['id'] ?></h1>
        <p class="section-subtitle">Purchased on <?= date('F d, Y · h:i A', strtotime($existingOrder['created_at'])) ?></p>

        <div style="display: grid; grid-template-columns: 1.4fr 0.6fr; gap: 2rem; margin-bottom: 3rem;">
            <div style="background: var(--surface-white); border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 2rem;">
                <h3 style="margin-bottom: 1.5rem; font-size: 1.2rem;">Purchased Botanical Items</h3>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th>Unit Price</th>
                                <th>Quantity</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($existingItems as $it): ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($it['plant_name']) ?></strong><br>
                                        <small style="color: var(--primary);"><?= htmlspecialchars($it['category_name']) ?></small>
                                    </td>
                                    <td><?= APP_CURRENCY ?> <?= number_format($it['unit_price'], 2) ?></td>
                                    <td><?= $it['quantity'] ?></td>
                                    <td><strong><?= APP_CURRENCY ?> <?= number_format($it['subtotal'], 2) ?></strong></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div style="background: var(--surface-white); border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 2rem; height: fit-content;">
                <h3 style="margin-bottom: 1.2rem; font-size: 1.2rem;">Order Meta</h3>
                <div style="margin-bottom: 0.8rem;">
                    <span style="color: var(--text-muted); font-size: 0.9rem;">Payment Method:</span><br>
                    <strong><?= $existingOrder['payment_method'] === 'card' ? '💳 Secure Card Payment' : '💵 Cash on Delivery' ?></strong>
                </div>
                <div style="margin-bottom: 0.8rem;">
                    <span style="color: var(--text-muted); font-size: 0.9rem;">Payment Status:</span><br>
                    <span class="role-pill" style="background: var(--primary-subtle); color: var(--primary-dark); font-weight: bold;">
                        <?= strtoupper($existingOrder['payment_status']) ?>
                    </span>
                </div>
                <div style="margin-bottom: 1.2rem;">
                    <span style="color: var(--text-muted); font-size: 0.9rem;">Total Paid:</span><br>
                    <span style="font-size: 1.4rem; font-weight: 700; color: var(--primary-dark);">
                        <?= APP_CURRENCY ?> <?= number_format($existingOrder['total_amount'], 2) ?>
                    </span>
                </div>
                <a href="<?= APP_URL ?>/pages/order_receipt.php?order_id=<?= $existingOrder['id'] ?>" class="btn btn-primary" style="width: 100%; text-align: center;">
                    View Official Receipt
                </a>
            </div>
        </div>

    <?php else: ?>
        <!-- Pre-Checkout Order Review Summary -->
        <h1 class="section-title">Review Order Summary</h1>
        <p class="section-subtitle">Please verify your flora selection and destination details before finalizing secure payment.</p>

        <div style="display: grid; grid-template-columns: 1.3fr 0.7fr; gap: 2rem; margin-bottom: 3rem;">
            <!-- Left: Order Items Breakdown & Recipient Preview -->
            <div>
                <!-- Items Table Card -->
                <div style="background: var(--surface-white); border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 2rem; margin-bottom: 2rem; box-shadow: var(--shadow-sm);">
                    <h3 style="margin-bottom: 1.2rem; font-size: 1.2rem;">Selected Plant Varieties (<?= count($cartItems) ?>)</h3>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Plant</th>
                                    <th>Unit Price</th>
                                    <th>Qty</th>
                                    <th>Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($cartItems as $c): 
                                    $pl = $c['plant'];
                                ?>
                                    <tr>
                                        <td>
                                            <div style="display: flex; align-items: center; gap: 10px;">
                                                <img src="<?= APP_URL ?>/<?= htmlspecialchars($pl['image_url'] ?: 'assets/img/default_plant.png') ?>" 
                                                     alt="<?= htmlspecialchars($pl['name']) ?>" 
                                                     style="width: 40px; height: 40px; object-fit: cover; border-radius: var(--radius-sm);">
                                                <div>
                                                    <strong><?= htmlspecialchars($pl['name']) ?></strong><br>
                                                    <small style="color: var(--primary);"><?= htmlspecialchars($pl['category_name']) ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td><?= APP_CURRENCY ?> <?= number_format($pl['price'], 2) ?></td>
                                        <td><strong><?= $c['qty'] ?></strong></td>
                                        <td><strong><?= APP_CURRENCY ?> <?= number_format($c['subtotal'], 2) ?></strong></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Recipient Preview Card -->
                <div style="background: var(--surface-white); border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 2rem; box-shadow: var(--shadow-sm);">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                        <h3 style="margin: 0; font-size: 1.2rem;">Customer Delivery Destination</h3>
                        <a href="<?= APP_URL ?>/pages/profile.php" class="btn btn-outline-sm">Edit Profile</a>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; font-size: 0.95rem;">
                        <div>
                            <span style="color: var(--text-muted); font-size: 0.85rem;">Recipient:</span><br>
                            <strong><?= htmlspecialchars($userData['full_name']) ?></strong>
                        </div>
                        <div>
                            <span style="color: var(--text-muted); font-size: 0.85rem;">Contact Telephone:</span><br>
                            <strong><?= htmlspecialchars($userData['phone'] ?: 'Not set (will provide at checkout)') ?></strong>
                        </div>
                        <div style="grid-column: 1 / -1;">
                            <span style="color: var(--text-muted); font-size: 0.85rem;">Shipping Address:</span><br>
                            <div><?= nl2br(htmlspecialchars($userData['address'] ?: 'Not set (will provide at checkout)')) ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right: Order Payment Calculation -->
            <div style="background: var(--surface-white); border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 2rem; height: fit-content; box-shadow: var(--shadow-sm);">
                <h3 style="margin-bottom: 1.2rem; font-size: 1.2rem;">Financial Breakdown</h3>
                <div style="display: flex; justify-content: space-between; margin-bottom: 0.8rem; font-size: 0.95rem;">
                    <span style="color: var(--text-muted);">Basket Subtotal:</span>
                    <span><?= APP_CURRENCY ?> <?= number_format($totalAmount, 2) ?></span>
                </div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 0.8rem; font-size: 0.95rem;">
                    <span style="color: var(--text-muted);">Nursery Delivery (Kegalle):</span>
                    <span style="color: var(--primary); font-weight: 700;">FREE</span>
                </div>
                <hr style="border: none; border-top: 1px solid var(--border-color); margin: 1rem 0;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 1.5rem; font-size: 1.3rem; font-weight: 700;">
                    <span>Payable Total:</span>
                    <span style="color: var(--primary-dark);"><?= APP_CURRENCY ?> <?= number_format($totalAmount, 2) ?></span>
                </div>
                <div style="display: flex; flex-direction: column; gap: 10px;">
                    <a href="<?= APP_URL ?>/pages/checkout.php" class="btn btn-primary" style="width: 100%; text-align: center;">
                        Proceed to Secure Checkout &rarr;
                    </a>
                    <a href="<?= APP_URL ?>/pages/cart.php" class="btn btn-secondary" style="width: 100%; text-align: center;">
                        Modify Basket
                    </a>
                </div>
                <div style="margin-top: 1.5rem; font-size: 0.8rem; color: var(--text-muted); text-align: center;">
                    🔒 Transactions secured by EcoSprout 256-bit payment engine. No paper bank slips required.
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>