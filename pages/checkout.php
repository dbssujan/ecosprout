<?php
$pageTitle = 'Secure Checkout';
require_once __DIR__ . '/../includes/header.php';

require_login();
$user = current_user();
$pdo = Database::getConnection();

if (empty($_SESSION['cart'])) {
    header("Location: " . APP_URL . "/pages/cart.php");
    exit;
}

$ids = array_keys($_SESSION['cart']);
$placeholders = implode(',', array_fill(0, count($ids), '?'));
$stmt = $pdo->prepare("SELECT * FROM plants WHERE id IN ($placeholders)");
$stmt->execute($ids);
$plants = $stmt->fetchAll();

$totalAmount = 0.0;
foreach ($plants as $p) {
    $totalAmount += ((int)$_SESSION['cart'][$p['id']]) * (float)$p['price'];
}

$successOrderId = null;
$errorMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $phone = sanitize($_POST['phone'] ?? '');
    $address = sanitize($_POST['address'] ?? '');
    $paymentMethod = $_POST['payment_method'] ?? 'cod';
    $notes = sanitize($_POST['order_notes'] ?? '');

    if (empty($phone) || empty($address)) {
        $errorMsg = 'Please provide contact phone number and delivery address.';
    } else {
        try {
            $pdo->beginTransaction();

            // Insert order
            $orderStmt = $pdo->prepare("INSERT INTO orders 
                (user_id, total_amount, payment_method, payment_status, order_status, shipping_address, contact_phone, order_notes) 
                VALUES (:uid, :tot, :pm, 'pending', 'processing', :addr, :phone, :notes)");
            $orderStmt->execute([
                ':uid'   => $user['id'],
                ':tot'   => $totalAmount,
                ':pm'    => $paymentMethod,
                ':addr'  => $address,
                ':phone' => $phone,
                ':notes' => $notes
            ]);
            $orderId = $pdo->lastInsertId();

            // Insert items & decrement stock
            $itemStmt = $pdo->prepare("INSERT INTO order_items (order_id, plant_id, quantity, unit_price, subtotal) VALUES (?, ?, ?, ?, ?)");
            $stockStmt = $pdo->prepare("UPDATE plants SET stock_quantity = stock_quantity - ? WHERE id = ?");

            foreach ($plants as $p) {
                $qty = (int)$_SESSION['cart'][$p['id']];
                $subtotal = $qty * (float)$p['price'];
                $itemStmt->execute([$orderId, $p['id'], $qty, $p['price'], $subtotal]);
                $stockStmt->execute([$qty, $p['id']]);
            }

            $pdo->commit();
            $_SESSION['cart'] = [];
            $successOrderId = $orderId;
        } catch (Exception $e) {
            $pdo->rollBack();
            $errorMsg = 'Order processing failed: ' . $e->getMessage();
        }
    }
}
?>

<div class="container" style="padding-top: 2rem;">
    <h1 class="section-title">Checkout & Order Confirmation</h1>

    <?php if ($successOrderId): ?>
        <div style="background: var(--surface-white); border-radius: var(--radius-lg); padding: 3rem; text-align: center; border: 1px solid var(--border-color); max-width: 600px; margin: 2rem auto;">
            <div style="margin-bottom: 1rem; color: var(--success);">
                <svg width="64" height="64" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            </div>
            <h2 style="color: var(--primary-dark); margin: 1rem 0;">Order #<?= $successOrderId ?> Confirmed!</h2>
            <p style="color: var(--text-muted); margin-bottom: 2rem;">Thank you, <?= htmlspecialchars($user['full_name']) ?>. Our Kegalle nursery team is packing your flora with care. We will contact you at your provided number prior to delivery.</p>
            <a href="<?= APP_URL ?>/pages/catalog.php" class="btn btn-primary">Continue Shopping</a>
        </div>
    <?php else: ?>
        <?php if ($errorMsg): ?>
            <div style="background: #fee2e2; color: var(--danger); padding: 1rem; border-radius: var(--radius-md); margin-bottom: 1.5rem;">
                <?= htmlspecialchars($errorMsg) ?>
            </div>
        <?php endif; ?>

        <div style="display: grid; grid-template-columns: 1.2fr 0.8fr; gap: 2.5rem;">
            <form method="POST" action="" style="background: var(--surface-white); border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 2rem;">
                <h3 style="margin-bottom: 1.2rem;">Delivery & Payment Information</h3>
                <div class="form-group">
                    <label>Recipient Name</label>
                    <input type="text" class="form-control" value="<?= htmlspecialchars($user['full_name']) ?>" readonly style="background: var(--bg-light);">
                </div>
                <div class="form-group">
                    <label>Contact Phone Number</label>
                    <input type="tel" name="phone" class="form-control" placeholder="+94 7X XXX XXXX" required>
                </div>
                <div class="form-group">
                    <label>Delivery Address (Kegalle & Surrounds)</label>
                    <textarea name="address" class="form-control" rows="3" placeholder="No, Street, Postal Area..." required></textarea>
                </div>
                <div class="form-group">
                    <label>Payment Method</label>
                    <select name="payment_method" class="form-control">
                        <option value="cod">Cash on Delivery (Kegalle)</option>
                        <option value="bank_transfer">Direct Bank Transfer</option>
                        <option value="card">Visa / Mastercard (Simulated)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Special Instructions</label>
                    <textarea name="order_notes" class="form-control" rows="2" placeholder="e.g. Leave with security, fragile flowering plants..."></textarea>
                </div>
                <button type="submit" class="btn btn-primary" style="width: 100%;">Place Nursery Order</button>
            </form>

            <div style="background: var(--surface-white); border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 2rem; height: fit-content;">
                <h3 style="margin-bottom: 1.2rem;">Your Items (<?= count($plants) ?>)</h3>
                <?php foreach ($plants as $p): 
                    $q = $_SESSION['cart'][$p['id']];
                ?>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 0.8rem; font-size: 0.9rem;">
                        <span><?= htmlspecialchars($p['name']) ?> &times; <?= $q ?></span>
                        <strong><?= APP_CURRENCY ?> <?= number_format($q * $p['price'], 2) ?></strong>
                    </div>
                <?php endforeach; ?>
                <hr style="margin: 1rem 0; border: none; border-top: 1px solid var(--border-color);">
                <div style="display: flex; justify-content: space-between; font-size: 1.2rem; font-weight: 700;">
                    <span>Grand Total:</span>
                    <span style="color: var(--primary-dark);"><?= APP_CURRENCY ?> <?= number_format($totalAmount, 2) ?></span>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
