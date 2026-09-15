<?php
$pageTitle = 'Plant Catalogue';
$extraScript = 'catalog.js';
require_once __DIR__ . '/../includes/header.php';

$pdo = Database::getConnection();
$categories = $pdo->query("SELECT * FROM categories ORDER BY id ASC")->fetchAll();
$plants = $pdo->query("SELECT p.*, c.name AS category_name, c.slug AS category_slug FROM plants p JOIN categories c ON p.category_id = c.id WHERE p.is_active = 1 ORDER BY p.id DESC")->fetchAll();
?>

<style>
    #plantGridContainer {
        display: grid !important;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)) !important;
        gap: 1.5rem !important;
        width: 100% !important;
        align-items: stretch !important;
    }
    .plant-card {
        width: 100% !important;
        max-width: 100% !important;
    }
    .filter-pills {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        align-items: center !important; 
    }
    .filter-pill {
        padding: 8px 16px;
        border-radius: 9999px;
        border: 1px solid #d1d5db;
        background: #f9fafb;
        color: #374151;
        cursor: pointer;
        font-weight: 500;
        transition: 0.2s;
        height: max-content !important; 
    }
    .filter-pill.active {
        background: #059669;
        color: white;
        border-color: #059669;
    }
    .filter-pill:hover:not(.active) {
        background: #e5e7eb;
    }
</style>

<div class="container" style="padding-top: 2rem;">
    <h1 class="section-title">Plant Catalogue & Botanical Collection</h1>
    <p class="section-subtitle">Browse through our nursery inventory of indoor, outdoor, ornamental, and edible flora in Kegalle.</p>

    <div class="search-filter-bar" style="margin-bottom: 2rem; background: white; padding: 1.5rem; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); border: 1px solid #e5e7eb;">
        <div class="search-input-wrap" style="margin-bottom: 1.5rem;">
            <input type="text" id="plantSearchInput" class="form-control"
                placeholder="Search plants by common or botanical name..." autocomplete="off" style="width: 100%; padding: 12px 15px; border-radius: 8px; border: 1px solid #d1d5db; font-size: 1rem;">
        </div>
        <div class="filter-pills">
            <button class="filter-pill active" data-category="all">All Flora</button>
            <?php foreach ($categories as $cat): ?>
                <button class="filter-pill"
                    data-category="<?= htmlspecialchars($cat['slug']) ?>"><?= htmlspecialchars($cat['name']) ?></button>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="plant-grid" id="plantGridContainer">
        <?php foreach ($plants as $plant): ?>
            <div class="plant-card" style="background: #ffffff; border: 1px solid #e5e7eb; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); display: flex; flex-direction: column; transition: transform 0.2s, box-shadow 0.2s; height: 100%;">
                
                <!-- Clickable Area (Navigates to Plant Details) -->
                <a href="plant_details.php?id=<?= $plant['id'] ?>" style="text-decoration: none; color: inherit; display: flex; flex-direction: column; flex-grow: 1;">
                    <div class="plant-img-container" style="position: relative; height: 200px; overflow: hidden; background: #f9fafb;">
                        <img src="../<?= htmlspecialchars($plant['image_url'] ?: 'assets/img/default_plant.png') ?>" 
                             alt="<?= htmlspecialchars($plant['name']) ?>" 
                             style="width: 100%; height: 100%; object-fit: cover; transition: transform 0.3s;"
                             onmouseover="this.style.transform='scale(1.05)'" onmouseout="this.style.transform='scale(1)'">
                        <?php if ($plant['stock_quantity'] > 0): ?>
                            <span style="position: absolute; top: 10px; right: 10px; background: #10b981; color: white; padding: 4px 10px; border-radius: 6px; font-size: 0.75rem; font-weight: bold; box-shadow: 0 2px 4px rgba(0,0,0,0.2);">In Stock</span>
                        <?php else: ?>
                            <span style="position: absolute; top: 10px; right: 10px; background: #ef4444; color: white; padding: 4px 10px; border-radius: 6px; font-size: 0.75rem; font-weight: bold; box-shadow: 0 2px 4px rgba(0,0,0,0.2);">Out of Stock</span>
                        <?php endif; ?>
                    </div>

                    <div class="plant-info" style="padding: 1.25rem; display: flex; flex-direction: column; flex-grow: 1;">
                        <span class="plant-category-tag" style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: #059669; letter-spacing: 0.05em; margin-bottom: 0.3rem; display: block;">
                            <?= htmlspecialchars($plant['category_name']) ?>
                        </span>
                        <h3 class="plant-title" style="font-size: 1.1rem; font-weight: 700; color: #111827; margin: 0 0 0.25rem 0;">
                            <?= htmlspecialchars($plant['name']) ?>
                        </h3>
                        
                        <?php if (($plant['category_slug'] ?? '') !== 'tools-accessories' && !empty($plant['care_sunlight']) && $plant['care_sunlight'] !== 'Not Applicable' && $plant['care_sunlight'] !== 'N/A'): ?>
                            <div class="plant-care-badges" style="display: flex; gap: 6px; flex-wrap: wrap; margin-top: 0.5rem;">
                                <span class="care-badge" style="background: #fef3c7; color: #d97706; padding: 3px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: 500;">
                                    <?= htmlspecialchars($plant['care_sunlight']) ?>
                                </span>
                                <span class="care-badge" style="background: #e0f2fe; color: #0284c7; padding: 3px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: 500;">
                                    <?= htmlspecialchars($plant['care_water'] ?? 'Moderate') ?>
                                </span>
                            </div>
                        <?php endif; ?>
                    </div>
                </a>

                <!-- Clean Card Footer -->
                <div class="plant-footer" style="padding: 1rem 1.25rem 1.25rem 1.25rem; border-top: 1px solid #f3f4f6; margin-top: auto; display: flex; flex-direction: column; gap: 10px;">
                    <div class="plant-price" style="font-weight: 700; font-size: 1.25rem; color: #111827;">
                        <?= APP_CURRENCY ?> <?= number_format($plant['price'], 2) ?>
                    </div>

                    <button class="btn btn-primary" onclick="addToCart(<?= $plant['id'] ?>, 1)" 
                            style="width: 100%; text-align: center; background: #065f46; color: white; padding: 10px 0; font-size: 0.9rem; font-weight: 600; border-radius: 6px; border: none; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 6px; transition: 0.2s;"
                            onmouseover="this.style.background='#047857'" onmouseout="this.style.background='#065f46'">
                        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path>
                        </svg>
                        Add to Cart
                    </button>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>