<?php
$pageTitle = 'Plant Catalogue';
$extraScript = 'catalog.js';
require_once __DIR__ . '/../includes/header.php';

$pdo = Database::getConnection();
$categories = $pdo->query("SELECT * FROM categories ORDER BY id ASC")->fetchAll();
$plants = $pdo->query("SELECT p.*, c.name AS category_name FROM plants p JOIN categories c ON p.category_id = c.id ORDER BY p.id DESC")->fetchAll();
?>

<div class="container" style="padding-top: 2rem;">
    <h1 class="section-title">Plant Catalogue & Botanical Collection</h1>
    <p class="section-subtitle">Browse through our nursery inventory of indoor, outdoor, ornamental, and edible flora in Kegalle.</p>

    
    <div class="search-filter-bar">
        <div class="search-input-wrap">
            <input type="text" id="plantSearchInput" class="form-control" placeholder="🔍 Search plants by common or botanical name..." autocomplete="off">
        </div>
        <div class="filter-pills">
            <button class="filter-pill active" data-category="all">All Flora</button>
            <?php foreach ($categories as $cat): ?>
                <button class="filter-pill" data-category="<?= htmlspecialchars($cat['slug']) ?>"><?= htmlspecialchars($cat['name']) ?></button>
            <?php endforeach; ?>
        </div>
    </div>

    
    <div class="plant-grid" id="plantGridContainer">
        <?php foreach ($plants as $plant): ?>
            <div class="plant-card">
                <div class="plant-img-placeholder">🌱</div>
                <div class="plant-info">
                    <span class="plant-category-tag"><?= htmlspecialchars($plant['category_name']) ?></span>
                    <h3 class="plant-title"><?= htmlspecialchars($plant['name']) ?></h3>
                    <p class="botanical-name"><?= htmlspecialchars($plant['botanical_name']) ?></p>
                    <div class="plant-care-badges">
                        <span class="care-badge">☀️ <?= htmlspecialchars($plant['care_sunlight']) ?></span>
                        <span class="care-badge">💧 <?= htmlspecialchars($plant['care_water']) ?></span>
                    </div>
                    <div class="plant-footer">
                        <span class="plant-price"><?= APP_CURRENCY ?> <?= number_format($plant['price'], 2) ?></span>
                        <button class="btn btn-primary-sm" onclick="addToCart(<?= $plant['id'] ?>)">Add to Cart</button>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<script src="<?= APP_URL ?>/assets/js/cart.js"></script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
