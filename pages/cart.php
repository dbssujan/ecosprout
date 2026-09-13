<?php
$pageTitle = 'Shopping Cart';
$extraScript = 'cart.js';
require_once __DIR__ . '/../includes/header.php';

$pdo = Database::getConnection();
$cartItems = [];
$totalAmount = 0.0;

if (!empty($_SESSION['cart'])) {
    $ids = array_keys($_SESSION['cart']);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("SELECT p.*, c.name AS category_name FROM plants p JOIN categories c ON p.category_id = c.id WHERE p.id IN ($placeholders)");
    $stmt->execute($ids);
    $plants = $stmt->fetchAll();

    foreach ($plants as $plant) {
        $qty = (int)$_SESSION['cart'][$plant['id']];
        $subtotal = $qty * (float)$plant['price'];
        $totalAmount += $subtotal;
        $cartItems[] = [
            'plant'    => $plant,
            'qty'      => $qty,
            'subtotal' => $subtotal
        ];
    }
}
?>

<div class="container" style="padding-top: 2rem;">
    <h1 class="section-title">Your Green Basket</h1>
    <p class="section-subtitle">Review your selected flora and gardening supplies before checkout.</p>

    <?php if (empty($cartItems)): ?>
        <div style="background: var(--surface-white); border-radius: var(--radius-lg); padding: 4rem; text-align: center; border: 1px solid var(--border-color);">
            <div style="font-size: 4rem; margin-bottom: 1rem;">🪴</div>
            <h2>Your cart is currently empty</h2>
            <p class="text-muted" style="margin-bottom: 1.5rem;">Explore our nursery catalog to discover healthy varieties for your garden.</p>
            <a href="<?= APP_URL ?>/pages/catalog.php" class="btn btn-primary">Browse Catalogue</a>
        </div>
    <?php else: ?>
        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 2rem;">
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Plant</th>
                            <th>Unit Price</th>
                            <th>Quantity</th>
                            <th>Subtotal</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cartItems as $item): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($item['plant']['name']) ?></strong><br>
                                    <small class="text-muted"><?= htmlspecialchars($item['plant']['category_name']) ?></small>
                                </td>
                                <td><?= APP_CURRENCY ?> <?= number_format($item['plant']['price'], 2) ?></td>
                                <td>
                                    <input type="number" min="1" max="<?= $item['plant']['stock_quantity'] ?>" value="<?= $item['qty'] ?>" 
                                           style="width: 60px; padding: 4px 6px;" 
                                           onchange="updateCartQuantity(<?= $item['plant']['id'] ?>, this.value)">
                                </td>
                                <td><strong><?= APP_CURRENCY ?> <?= number_format($item['subtotal'], 2) ?></strong></td>
                                <td>
                                    <button class="btn btn-outline-sm" style="border-color: var(--danger); color: var(--danger);" 
                                            onclick="removeFromCart(<?= $item['plant']['id'] ?>)">Remove</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            
            <div style="background: var(--surface-white); border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 1.5rem; height: fit-content;">
                <h3 style="margin-bottom: 1rem;">Order Summary</h3>
                <div style="display: flex; justify-content: space-between; margin-bottom: 0.8rem;">
                    <span class="text-muted">Subtotal:</span>
                    <span><?= APP_CURRENCY ?> <?= number_format($totalAmount, 2) ?></span>
                </div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 0.8rem;">
                    <span class="text-muted">Delivery (Kegalle):</span>
                    <span>Free</span>
                </div>
                <hr style="border: none; border-top: 1px solid var(--border-color); margin: 1rem 0;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 1.5rem; font-size: 1.2rem; font-weight: 700;">
                    <span>Total:</span>
                    <span style="color: var(--primary-dark);"><?= APP_CURRENCY ?> <?= number_format($totalAmount, 2) ?></span>
                </div>
                <a href="<?= APP_URL ?>/pages/checkout.php" class="btn btn-primary" style="width: 100%;">Proceed to Checkout</a>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
