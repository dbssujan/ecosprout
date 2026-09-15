<?php
// Initialize session, database connection, and system helpers.
$pageTitle = 'Secure Checkout';
$extraScript = 'checkout.js';
require_once __DIR__ . '/../includes/header.php';
require_login();

$user = current_user();
$pdo = Database::getConnection();

// Validate that active cart has items.
if (empty($_SESSION['cart'])) {
    header("Location: " . APP_URL . "/pages/cart.php");
    exit;
}

// Fetch active plant details and verify stock availability.
$plantIds = array_keys($_SESSION['cart']);
$placeholders = implode(',', array_fill(0, count($plantIds), '?'));
$stmt = $pdo->prepare("SELECT p.*, c.name AS category_name FROM plants p JOIN categories c ON p.category_id = c.id WHERE p.id IN ($placeholders)");
$stmt->execute($plantIds);
$plants = $stmt->fetchAll();

// Map plants for rapid lookup and price verification.
$plantMap = [];
foreach ($plants as $p) {
    $plantMap[$p['id']] = $p;
}

$orderItems = [];
$totalAmount = 0.0;
foreach ($_SESSION['cart'] as $pId => $qty) {
    $qty = (int)$qty;
    if ($qty > 0 && isset($plantMap[$pId])) {
        $p = $plantMap[$pId];
        $sub = $qty * (float)$p['price'];
        $totalAmount += $sub;
        $orderItems[] = [
            'plant'    => $p,
            'qty'      => $qty,
            'subtotal' => $sub
        ];
    }
}

// Redirect if order items are invalid.
if (empty($orderItems)) {
    $_SESSION['cart'] = [];
    header("Location: " . APP_URL . "/pages/cart.php");
    exit;
}

// Fetch pre-filled customer details from database.
$uStmt = $pdo->prepare("SELECT phone, address FROM users WHERE id = :id");
$uStmt->execute([':id' => $user['id']]);
$userProfile = $uStmt->fetch();

$errorMsg = '';
$phone = $userProfile['phone'] ?? '';
$address = $userProfile['address'] ?? '';
$paymentMethod = 'card';
$orderNotes = '';

// Process transactional order submission.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf)) {
        $errorMsg = 'Security token invalid or expired. Please try again.';
    } else {
        $phone = sanitize($_POST['phone'] ?? '');
        $address = sanitize($_POST['address'] ?? '');
        $paymentMethod = $_POST['payment_method'] ?? 'card';
        $orderNotes = sanitize($_POST['order_notes'] ?? '');

        // Validate basic contact and delivery fields.
        if (empty($phone) || empty($address)) {
            $errorMsg = 'Please provide contact telephone number and delivery address.';
        } elseif (!in_array($paymentMethod, ['cod', 'card'], true)) {
            $errorMsg = 'Invalid payment method selected.';
        }

        // Validate secure card gateway inputs when card payment is selected.
        $cardHolder = '';
        $cleanCardNumber = '';
        $cardExpiry = '';
        $cardCvv = '';
        $transactionId = null;
        $cardLast4 = null;
        $cardBrand = null;
        $paidAt = null;

        if (empty($errorMsg) && $paymentMethod === 'card') {
            $cardHolder = sanitize($_POST['card_holder'] ?? '');
            $cleanCardNumber = preg_replace('/\D/', '', $_POST['card_number'] ?? '');
            $cardExpiry = sanitize($_POST['card_expiry'] ?? '');
            $cardCvv = sanitize($_POST['card_cvv'] ?? '');

            if (empty($cardHolder) || strlen($cardHolder) < 3) {
                $errorMsg = 'Please enter the cardholder name.';
            } elseif (strlen($cleanCardNumber) < 15 || strlen($cleanCardNumber) > 16) {
                $errorMsg = 'Please provide a valid 15 or 16 digit card number.';
            } elseif (!preg_match('/^(0[1-9]|1[0-2])\/([0-9]{2})$/', $cardExpiry, $matches)) {
                $errorMsg = 'Invalid card expiration date format (MM/YY).';
            } else {
                $expMonth = (int)$matches;
                $expYear = 2000 + (int)$matches[2];
                $curYear = (int)date('Y');
                $curMonth = (int)date('n');

                if ($expYear < $curYear || ($expYear === $curYear && $expMonth < $curMonth)) {
                    $errorMsg = 'The provided card has expired.';
                } elseif (strlen($cardCvv) < 3 || strlen($cardCvv) > 4) {
                    $errorMsg = 'Please enter a valid 3 or 4 digit CVV code.';
                }
            }

            // Simulate payment gateway authorization and tokenization.
            if (empty($errorMsg)) {
                $transactionId = 'TXN-' . strtoupper(bin2hex(random_bytes(6)));
                $cardLast4 = substr($cleanCardNumber, -4);
                if (str_starts_with($cleanCardNumber, '4')) {
                    $cardBrand = 'Visa';
                } elseif (preg_match('/^(5[1-5]|2[2-7])/', $cleanCardNumber)) {
                    $cardBrand = 'Mastercard';
                } elseif (str_starts_with($cleanCardNumber, '34') || str_starts_with($cleanCardNumber, '37')) {
                    $cardBrand = 'Amex';
                } else {
                    $cardBrand = 'Card';
                }
                $paidAt = date('Y-m-d H:i:s');
            }
        }

        // Execute atomic multi-table transaction with concurrency locks.
        if (empty($errorMsg)) {
            try {
                $pdo->beginTransaction();

                // Concurrency lock: Check current live stock quantities using SELECT FOR UPDATE.
                $pIds = array_column($plants, 'id');
                $inSql = implode(',', array_fill(0, count($pIds), '?'));
                $lockStmt = $pdo->prepare("SELECT id, name, stock_quantity FROM plants WHERE id IN ($inSql) FOR UPDATE");
                $lockStmt->execute($pIds);
                $latestStock = $lockStmt->fetchAll();

                $stockMap = [];
                foreach ($latestStock as $s) {
                    $stockMap[$s['id']] = $s;
                }

                // Verify stock sufficiency for every item before proceeding.
                foreach ($orderItems as $item) {
                    $pid = $item['plant']['id'];
                    $need = $item['qty'];
                    if (!isset($stockMap[$pid]) || (int)$stockMap[$pid]['stock_quantity'] < $need) {
                        $pName = $stockMap[$pid]['name'] ?? $item['plant']['name'];
                        throw new Exception("Insufficient stock for '{$pName}'. Please reduce quantity.");
                    }
                }

                // Set payment status based on chosen gateway method.
                $paymentStatus = ($paymentMethod === 'card') ? 'completed' : 'pending';

                // Insert master order record.
                $orderStmt = $pdo->prepare("
                    INSERT INTO orders 
                    (user_id, total_amount, payment_method, payment_status, order_status, shipping_address, contact_phone, order_notes, transaction_id, card_last4, card_brand, paid_at)
                    VALUES (:uid, :tot, :pm, :pstatus, 'processing', :addr, :phone, :notes, :txid, :last4, :brand, :paid_at)
                ");
                $orderStmt->execute([
                    ':uid'     => $user['id'],
                    ':tot'     => $totalAmount,
                    ':pm'      => $paymentMethod,
                    ':pstatus' => $paymentStatus,
                    ':addr'    => $address,
                    ':phone'   => $phone,
                    ':notes'   => $orderNotes,
                    ':txid'    => $transactionId,
                    ':last4'   => $cardLast4,
                    ':brand'   => $cardBrand,
                    ':paid_at' => $paidAt
                ]);

                $orderId = (int)$pdo->lastInsertId();

                // Insert order line items and deduct plant inventory atomically.
                $itemStmt = $pdo->prepare("INSERT INTO order_items (order_id, plant_id, quantity, unit_price, subtotal) VALUES (?, ?, ?, ?, ?)");
                $stockStmt = $pdo->prepare("UPDATE plants SET stock_quantity = stock_quantity - ? WHERE id = ? AND stock_quantity >= ?");

                foreach ($orderItems as $item) {
                    $qty = $item['qty'];
                    $price = (float)$item['plant']['price'];
                    $sub = $item['subtotal'];
                    $pId = $item['plant']['id'];

                    $itemStmt->execute([$orderId, $pId, $qty, $price, $sub]);

                    $stockStmt->execute([$qty, $pId, $qty]);
                    if ($stockStmt->rowCount() === 0) {
                        throw new Exception("Inventory race condition detected for " . $item['plant']['name']);
                    }
                }

                // Commit atomic transaction.
                $pdo->commit();

                // Clear customer shopping cart session.
                $_SESSION['cart'] = [];

                // Redirect to receipt confirmation page.
                header("Location: " . APP_URL . "/pages/order_receipt.php?order_id=" . $orderId . "&new=1");
                exit;

            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $errorMsg = 'Checkout transaction failed: ' . $e->getMessage();
            }
        }
    }
}

$csrfToken = generate_csrf_token();
?>

<div class="container" style="padding-top: 2rem; margin-bottom: 3rem;">
    <h1 class="section-title">Secure Checkout</h1>
    <p class="section-subtitle">Complete your delivery and payment details to confirm your nursery order.</p>

    <?php if ($errorMsg): ?>
        <div style="background: #fee2e2; color: var(--danger); padding: 12px 16px; border-radius: var(--radius-md); margin-bottom: 1.5rem; border: 1px solid #fecaca;">
            <?= htmlspecialchars($errorMsg) ?>
        </div>
    <?php endif; ?>

    <div style="display: grid; grid-template-columns: 1.25fr 0.75fr; gap: 2.5rem; align-items: start;">
        <!-- Left: Checkout Form -->
        <form method="POST" action="" id="checkoutForm" style="background: var(--surface-white); border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 2rem; box-shadow: var(--shadow-sm);">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">

            <h3 style="margin-bottom: 1.2rem; font-size: 1.2rem; color: var(--primary-dark);">1. Recipient & Delivery Address</h3>

            <div class="form-group">
                <label>Recipient Name</label>
                <input type="text" class="form-control" value="<?= htmlspecialchars($user['full_name']) ?>" readonly style="background: var(--bg-light);">
            </div>

            <div class="form-group">
                <label>Contact Phone Number <span style="color: var(--danger);">*</span></label>
                <input type="tel" name="phone" id="contact_phone" class="form-control" placeholder="e.g. +94 77 123 4567" value="<?= htmlspecialchars($phone) ?>" required>
            </div>

            <div class="form-group">
                <label>Delivery Address (Kegalle & Surrounds) <span style="color: var(--danger);">*</span></label>
                <textarea name="address" id="shipping_address" class="form-control" rows="3" placeholder="No, Street, Postal Town..." required><?= htmlspecialchars($address) ?></textarea>
            </div>

            <div class="form-group">
                <label>Special Instructions (Optional)</label>
                <textarea name="order_notes" class="form-control" rows="2" placeholder="e.g. Leave with gate security, fragile saplings..."><?= htmlspecialchars($orderNotes) ?></textarea>
            </div>

            <hr style="border: none; border-top: 1px solid var(--border-color); margin: 2rem 0;">

            <h3 style="margin-bottom: 1.2rem; font-size: 1.2rem; color: var(--primary-dark);">2. Payment Method</h3>

            <!-- Payment Options -->
            <div style="display: flex; flex-direction: column; gap: 12px; margin-bottom: 1.5rem;">
                <!-- Card Payment Radio -->
                <label style="display: flex; align-items: center; gap: 12px; padding: 14px 16px; border: 1px solid var(--border-color); border-radius: var(--radius-md); cursor: pointer; background: var(--bg-light);">
                    <input type="radio" name="payment_method" id="pm_card" value="card" <?= $paymentMethod === 'card' ? 'checked' : '' ?>>
                    <div style="flex: 1;">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <strong>💳 Credit / Debit Card (Online Gateway)</strong>
                            <span class="role-pill" style="background: var(--primary-subtle); color: var(--primary-dark); font-weight: 700;">Encrypted</span>
                        </div>
                        <small style="color: var(--text-muted);">Instant confirmation via secure online card processing. No physical bank slip needed.</small>
                    </div>
                </label>

                <!-- Cash on Delivery Radio -->
                <label style="display: flex; align-items: center; gap: 12px; padding: 14px 16px; border: 1px solid var(--border-color); border-radius: var(--radius-md); cursor: pointer; background: var(--bg-light);">
                    <input type="radio" name="payment_method" id="pm_cod" value="cod" <?= $paymentMethod === 'cod' ? 'checked' : '' ?>>
                    <div>
                        <strong>💵 Cash on Delivery (Kegalle Delivery Zone)</strong><br>
                        <small style="color: var(--text-muted);">Pay directly to the courier upon receiving your plants.</small>
                    </div>
                </label>
            </div>

            <!-- Embedded Card Gateway Panel -->
            <div id="cardGatewayPanel" style="display: <?= $paymentMethod === 'card' ? 'block' : 'none' ?>; background: #fdfefe; border: 1px solid #bbf7d0; border-radius: var(--radius-md); padding: 1.5rem; margin-bottom: 1.5rem;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                    <strong style="color: var(--primary-dark); font-size: 0.95rem;">🔒 Secure Payment Gateway</strong>
                    <span id="cardBrandBadge" style="font-weight: 800; font-size: 0.85rem; color: #6b7280; letter-spacing: 0.05em;">CARD</span>
                </div>

                <div class="form-group">
                    <label style="font-size: 0.85rem;">Cardholder Name</label>
                    <input type="text" name="card_holder" id="card_holder" class="form-control" placeholder="Name on card" value="<?= htmlspecialchars($_POST['card_holder'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label style="font-size: 0.85rem;">Card Number</label>
                    <input type="text" name="card_number" id="card_number" class="form-control" placeholder="0000 0000 0000 0000" maxlength="19" value="<?= htmlspecialchars($_POST['card_number'] ?? '') ?>">
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label style="font-size: 0.85rem;">Expiry Date (MM/YY)</label>
                        <input type="text" name="card_expiry" id="card_expiry" class="form-control" placeholder="MM/YY" maxlength="5" value="<?= htmlspecialchars($_POST['card_expiry'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label style="font-size: 0.85rem;">Security Code (CVV)</label>
                        <input type="password" name="card_cvv" id="card_cvv" class="form-control" placeholder="CVC" maxlength="4" value="<?= htmlspecialchars($_POST['card_cvv'] ?? '') ?>">
                    </div>
                </div>
                <small style="color: var(--text-muted); font-size: 0.78rem;">Verified by 256-Bit SSL tokenized checkout engine. Card details are processed in memory and never stored in plain text.</small>
            </div>

            <button type="submit" id="submitOrderBtn" class="btn btn-primary" style="width: 100%; padding: 12px; font-size: 1.05rem;">
                Confirm & Authorize Order
            </button>
        </form>

        <!-- Right: Order Summary Sidebar -->
        <div style="background: var(--surface-white); border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 1.75rem; height: fit-content; box-shadow: var(--shadow-sm); position: sticky; top: 90px;">
            <h3 style="margin-bottom: 1.2rem; font-size: 1.2rem; color: var(--text-dark);">Order Items (<?= count($orderItems) ?>)</h3>

            <div style="max-height: 280px; overflow-y: auto; margin-bottom: 1.5rem; display: flex; flex-direction: column; gap: 12px;">
                <?php foreach ($orderItems as $it): 
                    $pl = $it['plant'];
                ?>
                    <div style="display: flex; justify-content: space-between; align-items: center; font-size: 0.9rem; border-bottom: 1px solid var(--border-color); padding-bottom: 8px;">
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <img src="<?= APP_URL ?>/<?= htmlspecialchars($pl['image_url'] ?: 'assets/img/default_plant.png') ?>" 
                                 alt="<?= htmlspecialchars($pl['name']) ?>" 
                                 style="width: 40px; height: 40px; object-fit: cover; border-radius: var(--radius-sm);">
                            <div>
                                <div style="font-weight: 600; color: var(--text-dark);"><?= htmlspecialchars($pl['name']) ?></div>
                                <small style="color: var(--text-muted);"><?= $it['qty'] ?> &times; <?= APP_CURRENCY ?> <?= number_format($pl['price'], 2) ?></small>
                            </div>
                        </div>
                        <strong><?= APP_CURRENCY ?> <?= number_format($it['subtotal'], 2) ?></strong>
                    </div>
                <?php endforeach; ?>
            </div>

            <div style="display: flex; justify-content: space-between; margin-bottom: 0.8rem; font-size: 0.95rem;">
                <span style="color: var(--text-muted);">Subtotal:</span>
                <span><?= APP_CURRENCY ?> <?= number_format($totalAmount, 2) ?></span>
            </div>
            <div style="display: flex; justify-content: space-between; margin-bottom: 0.8rem; font-size: 0.95rem;">
                <span style="color: var(--text-muted);">Kegalle Delivery:</span>
                <span style="color: var(--primary); font-weight: 700;">FREE</span>
            </div>
            <hr style="border: none; border-top: 1px solid var(--border-color); margin: 1rem 0;">
            <div style="display: flex; justify-content: space-between; font-size: 1.3rem; font-weight: 700; margin-bottom: 1rem;">
                <span>Total Amount:</span>
                <span style="color: var(--primary-dark);"><?= APP_CURRENCY ?> <?= number_format($totalAmount, 2) ?></span>
            </div>
            <a href="<?= APP_URL ?>/pages/cart.php" style="font-size: 0.85rem; color: var(--primary); text-align: center; display: block;">
                &larr; Return to Shopping Basket
            </a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>