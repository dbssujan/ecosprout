<?php
$pageTitle = 'Plant Details';
require_once __DIR__ . '/../includes/header.php';

$pdo = Database::getConnection();
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $pdo->prepare("SELECT p.*, c.name AS category_name 
                       FROM plants p 
                       JOIN categories c ON p.category_id = c.id 
                       WHERE p.id = :id AND p.is_active = 1");
$stmt->execute([':id' => $id]);
$plant = $stmt->fetch();

if (!$plant) {
    echo "<div class='container' style='padding: 4rem 1rem; text-align: center;'>
            <h2>Plant Not Found</h2>
            <p style='color: #6b7280; margin: 1rem 0 2rem;'>The requested plant variety is unavailable or has been archived.</p>
            <a href='catalog.php' class='btn btn-primary' style='background: #065f46; color: white; padding: 10px 20px; border-radius: 8px; text-decoration: none;'>Back to Catalog</a>
          </div>";
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}
?>

<div class="container" style="padding-top: 2rem; padding-bottom: 3rem;">
    <!-- Navigation Back Link -->
    <div style="margin-bottom: 1.5rem;">
        <a href="catalog.php" style="text-decoration: none; color: #059669; font-weight: 600; font-size: 0.95rem; display: inline-flex; align-items: center; gap: 6px;">
            &larr; Back to Catalog
        </a>
    </div>

    <!-- Main Detail Card Wrapper -->
    <div style="background: #ffffff; border: 1px solid #e5e7eb; border-radius: 16px; padding: 2.5rem; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); display: grid; grid-template-columns: 1fr 1.2fr; gap: 2.5rem; align-items: start;">
        
        <!-- Left Column: Botanical Image -->
        <div style="border-radius: 12px; overflow: hidden; background: #f9fafb; height: 450px; display: flex; align-items: center; justify-content: center; border: 1px solid #f3f4f6;">
            <img src="../<?= htmlspecialchars($plant['image_url'] ?: 'assets/img/default_plant.png') ?>" 
                 alt="<?= htmlspecialchars($plant['name']) ?>" 
                 style="width: 100%; height: 100%; object-fit: cover;">
        </div>

        <!-- Right Column: Specs & Purchase Controls -->
        <div style="display: flex; flex-direction: column;">
            <span style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: #059669; background: #ecfdf5; padding: 4px 10px; border-radius: 6px; width: max-content; margin-bottom: 0.75rem;">
                <?= htmlspecialchars($plant['category_name']) ?>
            </span>

            <h1 style="font-size: 2rem; font-weight: 800; color: #111827; margin: 0 0 0.25rem;">
                <?= htmlspecialchars($plant['name']) ?>
            </h1>

            <p style="font-style: italic; font-size: 1rem; color: #6b7280; margin: 0 0 1.5rem;">
                <?= htmlspecialchars($plant['botanical_name']) ?>
            </p>

            <div style="font-size: 1.85rem; font-weight: 800; color: #065f46; margin-bottom: 1.5rem;">
                <?= APP_CURRENCY ?> <?= number_format($plant['price'], 2) ?>
            </div>

            <!-- Environmental Requirements -->
            <div style="display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 2rem;">
                <span style="background: #fef3c7; color: #d97706; padding: 6px 12px; border-radius: 6px; font-size: 0.85rem; font-weight: 600;">
                    ☀️ <?= htmlspecialchars($plant['care_sunlight'] ?? 'Full Sun') ?>
                </span>
                <span style="background: #e0f2fe; color: #0284c7; padding: 6px 12px; border-radius: 6px; font-size: 0.85rem; font-weight: 600;">
                    💧 <?= htmlspecialchars($plant['care_water'] ?? 'Moderate') ?>
                </span>
            </div>

            <!-- Description -->
            <div style="border-top: 1px solid #f3f4f6; padding-top: 1.5rem; margin-bottom: 1.75rem;">
                <h4 style="font-size: 1rem; font-weight: 700; color: #111827; margin: 0 0 0.5rem;">Care & Description</h4>
                <p style="color: #4b5563; line-height: 1.6; margin: 0; font-size: 0.95rem;">
                    <?= nl2br(htmlspecialchars($plant['description'] ?: 'No botanical overview provided.')) ?>
                </p>
            </div>

            <!-- Stock Availability -->
            <div style="margin-bottom: 1.5rem;">
                <?php if ($plant['stock_quantity'] > 0): ?>
                    <span style="color: #059669; font-weight: 600; font-size: 0.95rem;">
                        ✓ In Stock (<?= $plant['stock_quantity'] ?> available)
                    </span>
                <?php else: ?>
                    <span style="color: #dc2626; font-weight: 600; font-size: 0.95rem;">
                        ✕ Currently Out of Stock
                    </span>
                <?php endif; ?>
            </div>

            <!-- Interactive Quantity Selector & Cart CTA -->
            <?php if ($plant['stock_quantity'] > 0): ?>
                <div style="display: flex; gap: 12px; align-items: center; margin-top: 0.5rem;">
                    <!-- Stepper Control -->
                    <div style="display: flex; align-items: center; border: 1px solid #d1d5db; border-radius: 8px; overflow: hidden; background: #fff; height: 48px;">
                        <button type="button" onclick="adjustQty(-1)" 
                                style="border: none; background: #f9fafb; padding: 0 16px; font-size: 1.25rem; cursor: pointer; color: #374151; height: 100%; transition: 0.2s;"
                                onmouseover="this.style.background='#f3f4f6'" onmouseout="this.style.background='#f9fafb'">−</button>
                        
                        <input type="number" id="detailQty" value="1" min="1" max="<?= $plant['stock_quantity'] ?>" 
                               style="width: 55px; height: 100%; text-align: center; border: none; font-weight: 700; font-size: 1.05rem; outline: none; background: white;" readonly>
                        
                        <button type="button" onclick="adjustQty(1, <?= $plant['stock_quantity'] ?>)" 
                                style="border: none; background: #f9fafb; padding: 0 16px; font-size: 1.25rem; cursor: pointer; color: #374151; height: 100%; transition: 0.2s;"
                                onmouseover="this.style.background='#f3f4f6'" onmouseout="this.style.background='#f9fafb'">+</button>
                    </div>

                    <!-- Add to Cart Button -->
                    <button type="button" onclick="addToCart(<?= $plant['id'] ?>, document.getElementById('detailQty').value)" 
                            style="flex: 1; height: 48px; background: #065f46; color: white; font-weight: 600; font-size: 1rem; border-radius: 8px; border: none; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; transition: 0.2s;"
                            onmouseover="this.style.background='#047857'" onmouseout="this.style.background='#065f46'">
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path>
                        </svg>
                        Add to Cart
                    </button>
                </div>
            <?php else: ?>
                <button disabled style="width: 100%; height: 48px; background: #e5e7eb; color: #9ca3af; font-weight: 600; font-size: 1rem; border-radius: 8px; border: none; cursor: not-allowed;">
                    Sold Out
                </button>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
function adjustQty(change, maxStock = 999) {
    const input = document.getElementById('detailQty');
    let current = parseInt(input.value) || 1;
    let target = current + change;

    if (target >= 1 && target <= maxStock) {
        input.value = target;
    }
}

function addToCart(plantId, quantity) {
    const qty = parseInt(quantity) || 1;

    fetch('../api/add_to_cart.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ plant_id: plantId, quantity: qty })
    })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'success') {
            Swal.fire({
                title: 'Added to Cart!',
                text: `${qty} item(s) added to your basket.`,
                icon: 'success',
                timer: 1800,
                showConfirmButton: false
            });
            const cartBadge = document.getElementById('cart-count');
            if (cartBadge) cartBadge.innerText = data.total_items;
        } else {
            Swal.fire('Error', data.message || 'Unable to update cart.', 'error');
        }
    })
    .catch(() => {
        Swal.fire('Error', 'Could not reach the cart service.', 'error');
    });
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>