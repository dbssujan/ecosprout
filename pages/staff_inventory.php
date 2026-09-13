<?php
$pageTitle = 'Staff Inventory Management';
require_once __DIR__ . '/../includes/header.php';
require_role(['admin', 'staff']);

$pdo = Database::getConnection();
$msg = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_plant'])) {
    $name = sanitize($_POST['name'] ?? '');
    $botanical = sanitize($_POST['botanical_name'] ?? '');
    $catId = (int)($_POST['category_id'] ?? 1);
    $price = (float)($_POST['price'] ?? 0);
    $stock = (int)($_POST['stock_quantity'] ?? 0);
    $sunlight = $_POST['care_sunlight'] ?? 'Partial Shade';
    $water = $_POST['care_water'] ?? '2-3 Times a Week';
    $desc = sanitize($_POST['description'] ?? '');

    if (empty($name) || $price <= 0) {
        $error = 'Plant name and valid price are required.';
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO plants (category_id, name, botanical_name, description, care_sunlight, care_water, price, stock_quantity) 
                                   VALUES (:cat, :name, :bot, :desc, :sun, :wat, :price, :stock)");
            $stmt->execute([
                ':cat'   => $catId,
                ':name'  => $name,
                ':bot'   => $botanical,
                ':desc'  => $desc,
                ':sun'   => $sunlight,
                ':wat'   => $water,
                ':price' => $price,
                ':stock' => $stock
            ]);
            $msg = 'New botanical variety added to catalog!';
        } catch (PDOException $e) {
            $error = 'Failed to add plant: ' . $e->getMessage();
        }
    }
}

$categories = $pdo->query("SELECT * FROM categories")->fetchAll();
$plants = $pdo->query("SELECT p.*, c.name AS category_name FROM plants p JOIN categories c ON p.category_id = c.id ORDER BY p.id DESC")->fetchAll();
?>

<div class="container" style="padding-top: 2rem;">
    <h1 class="section-title">Nursery Staff Inventory Panel</h1>
    <p class="section-subtitle">Manage plant stock levels, update botanical descriptions, and add new varieties.</p>

    <?php if ($msg): ?>
        <div style="background: var(--primary-subtle); color: var(--primary-dark); padding: 12px; border-radius: var(--radius-md); margin-bottom: 1.5rem;">
            <?= htmlspecialchars($msg) ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div style="background: #fee2e2; color: var(--danger); padding: 12px; border-radius: var(--radius-md); margin-bottom: 1.5rem;">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    
    <div style="background: var(--surface-white); border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 2rem; margin-bottom: 3rem;">
        <h3 style="margin-bottom: 1.2rem;">+ Register New Plant Variety</h3>
        <form method="POST" action="">
            <input type="hidden" name="add_plant" value="1">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                <div class="form-group">
                    <label>Plant Common Name</label>
                    <input type="text" name="name" class="form-control" placeholder="e.g. Ceylon Cinnamon" required>
                </div>
                <div class="form-group">
                    <label>Botanical / Scientific Name</label>
                    <input type="text" name="botanical_name" class="form-control" placeholder="e.g. Cinnamomum verum">
                </div>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 1.5rem;">
                <div class="form-group">
                    <label>Category</label>
                    <select name="category_id" class="form-control">
                        <?php foreach ($categories as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Price (LKR)</label>
                    <input type="number" step="0.01" name="price" class="form-control" placeholder="1500.00" required>
                </div>
                <div class="form-group">
                    <label>Initial Stock</label>
                    <input type="number" name="stock_quantity" class="form-control" value="25" required>
                </div>
                <div class="form-group">
                    <label>Sunlight Requirement</label>
                    <select name="care_sunlight" class="form-control">
                        <option value="Full Sun">Full Sun</option>
                        <option value="Partial Shade">Partial Shade</option>
                        <option value="Indirect Light">Indirect Light</option>
                        <option value="Low Light">Low Light</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label>Description & Care Instructions</label>
                <textarea name="description" class="form-control" rows="3" placeholder="Soil composition, watering tips, growth habits..."></textarea>
            </div>
            <button type="submit" class="btn btn-primary">Save to Live Inventory</button>
        </form>
    </div>

    
    <h3 style="margin-bottom: 1rem;">Current Stock Levels</h3>
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Botanical</th>
                    <th>Category</th>
                    <th>Price</th>
                    <th>Stock</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($plants as $p): ?>
                    <tr>
                        <td>#<?= $p['id'] ?></td>
                        <td><strong><?= htmlspecialchars($p['name']) ?></strong></td>
                        <td><em><?= htmlspecialchars($p['botanical_name']) ?></em></td>
                        <td><?= htmlspecialchars($p['category_name']) ?></td>
                        <td><?= APP_CURRENCY ?> <?= number_format($p['price'], 2) ?></td>
                        <td><strong><?= $p['stock_quantity'] ?> units</strong></td>
                        <td>
                            <?php if ($p['stock_quantity'] <= 5): ?>
                                <span class="role-pill" style="background: #fee2e2; color: var(--danger);">Low Stock</span>
                            <?php else: ?>
                                <span class="role-pill" style="background: var(--primary-subtle); color: var(--primary-dark);">In Stock</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
