<?php
// Initialize session, database, and system helpers.
$pageTitle = 'Shopping Basket';
$extraScript = 'cart.js';
require_once __DIR__ . '/../includes/header.php';

$pdo = Database::getConnection();
$cartItems = [];
$totalAmount = 0.0;

// Query plant details if cart has active items in session.
if (!empty($_SESSION['cart'])) {
    $ids = array_keys($_SESSION['cart']);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("SELECT p.*, c.name AS category_name FROM plants p JOIN categories c ON p.category_id = c.id WHERE p.id IN ($placeholders)");
    $stmt->execute($ids);
    $plants = $stmt->fetchAll();

    foreach ($plants as $plant) {
        $qty = (int)($_SESSION['cart'][$plant['id']] ?? 0);
        if ($qty > 0) {
            $subtotal = $qty * (float)$plant['price'];
            $totalAmount += $subtotal;
            $cartItems[] = [
                'plant'    => $plant,
                'qty'      => $qty,
                'subtotal' => $subtotal
            ];
        }
    }
}
?>

<div class="container" style="padding-top: 2rem; margin-bottom: 3rem;">
    <!-- Section Title & Basket Controls -->
    <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 1.5rem;">
        <div>
            <h1 class="section-title">Your Green Basket</h1>
            <p class="section-subtitle" style="margin-bottom: 0;">Review your selected flora, adjust quantities, and proceed to order summary.</p>
        </div>
        <?php if (!empty($cartItems)): ?>
            <button type="button" class="btn btn-outline-sm" style="border-color: var(--danger); color: var(--danger);" onclick="clearEntireCart()">
                Empty Basket
            </button>
        <?php endif; ?>
    </div>

    <?php if (empty($cartItems)): ?>
        <!-- Empty Basket View -->
        <div style="background: var(--surface-white); border-radius: var(--radius-lg); padding: 4rem 2rem; text-align: center; border: 1px solid var(--border-color); box-shadow: var(--shadow-sm);">
            <div style="font-size: 3.5rem; margin-bottom: 1rem;">🌿</div>
            <h2 style="color: var(--text-dark); margin-bottom: 0.5rem;">Your basket is currently empty</h2>
            <p style="color: var(--text-muted); margin-bottom: 1.5rem;">Explore our nursery catalog to discover healthy varieties for your garden.</p>
            <a href="<?= APP_URL ?>/pages/catalog.php" class="btn btn-primary">Browse Plant Catalogue</a>
        </div>
    <?php else: ?>
        <!-- Cart Table and Summary Grid -->
        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 2rem;">
            <!-- Cart Items Table -->
            <div class="table-responsive">
                <table class="data-table" id="cartTable">
                    <thead>
                        <tr>
                            <th>Plant Variety</th>
                            <th>Unit Price</th>
                            <th style="text-align: center;">Quantity</th>
                            <th>Subtotal</th>
                            <th style="text-align: right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cartItems as $item):
                            $p = $item['plant'];
                        ?>
                            <tr id="cart-row-<?= $p['id'] ?>">
                                <td>
                                    <div style="display: flex; align-items: center; gap: 12px;">
                                        <img src="<?= APP_URL ?>/<?= htmlspecialchars($p['image_url'] ?: 'assets/img/default_plant.png') ?>"
                                            alt="<?= htmlspecialchars($p['name']) ?>"
                                            style="width: 50px; height: 50px; object-fit: cover; border-radius: var(--radius-sm); border: 1px solid var(--border-color);">
                                        <div>
                                            <strong><?= htmlspecialchars($p['name']) ?></strong><br>
                                            <small style="color: var(--primary); font-weight: 600;"><?= htmlspecialchars($p['category_name']) ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td><?= APP_CURRENCY ?> <?= number_format($p['price'], 2) ?></td>
                                <td style="text-align: center;">
                                    <div style="display: inline-flex; align-items: center; border: 1px solid var(--border-color); border-radius: var(--radius-sm); overflow: hidden; background: white;">
                                        <button type="button" class="btn-cart-dec" data-id="<?= $p['id'] ?>"
                                            style="border: none; background: var(--bg-light); padding: 4px 10px; cursor: pointer; font-weight: bold; color: var(--text-dark);">&minus;</button>
                                        <input type="number" id="qty-input-<?= $p['id'] ?>" class="cart-qty-field" data-id="<?= $p['id'] ?>"
                                            min="1" max="<?= $p['stock_quantity'] ?>" value="<?= $item['qty'] ?>"
                                            style="width: 45px; text-align: center; border: none; font-size: 0.9rem; font-weight: 600;">
                                        <button type="button" class="btn-cart-inc" data-id="<?= $p['id'] ?>" data-max="<?= $p['stock_quantity'] ?>"
                                            style="border: none; background: var(--bg-light); padding: 4px 10px; cursor: pointer; font-weight: bold; color: var(--text-dark);">&plus;</button>
                                    </div>
                                    <div style="font-size: 0.72rem; color: var(--text-muted); margin-top: 3px;">Max: <?= $p['stock_quantity'] ?> in stock</div>
                                </td>
                                <td>
                                    <strong class="item-subtotal-val" id="subtotal-<?= $p['id'] ?>" data-subtotal="<?= $item['subtotal'] ?>">
                                        <?= APP_CURRENCY ?> <?= number_format($item['subtotal'], 2) ?>
                                    </strong>
                                </td>
                                <td style="text-align: right;">
                                    <button type="button" class="btn btn-outline-sm" style="border-color: var(--danger); color: var(--danger); padding: 4px 8px;"
                                        onclick="removeFromCart(<?= $p['id'] ?>)">Remove</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Order Summary Sidebar -->
            <div style="background: var(--surface-white); border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 1.75rem; height: fit-content; box-shadow: var(--shadow-sm);">
                <h3 style="margin-bottom: 1.25rem; font-size: 1.25rem; color: var(--text-dark);">Order Summary</h3>
                <div style="display: flex; justify-content: space-between; margin-bottom: 0.8rem; font-size: 0.95rem;">
                    <span style="color: var(--text-muted);">Basket Subtotal:</span>
                    <span id="cartSubtotalAmount" style="font-weight: 600;"><?= APP_CURRENCY ?> <?= number_format($totalAmount, 2) ?></span>
                </div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 0.8rem; font-size: 0.95rem;">
                    <span style="color: var(--text-muted);">Delivery (Kegalle Area):</span>
                    <span style="color: var(--primary); font-weight: 700;">FREE</span>
                </div>
                <hr style="border: none; border-top: 1px solid var(--border-color); margin: 1rem 0;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 1.5rem; font-size: 1.25rem; font-weight: 700;">
                    <span>Grand Total:</span>
                    <span id="cartGrandTotalAmount" style="color: var(--primary-dark);"><?= APP_CURRENCY ?> <?= number_format($totalAmount, 2) ?></span>
                </div>
                <div style="display: flex; flex-direction: column; gap: 10px;">
                    <a href="<?= APP_URL ?>/pages/order_summary.php" class="btn btn-primary" style="width: 100%; text-align: center;">
                        Review Order Summary &rarr;
                    </a>
                    <a href="<?= APP_URL ?>/pages/checkout.php" class="btn btn-secondary" style="width: 100%; text-align: center; border-color: var(--primary); color: var(--primary);">
                        Fast Secure Checkout
                    </a>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>