<?php
$pageTitle = 'Home - Kegalle Plant Nursery & Gardening Services';
$extraScript = 'catalog.js';
require_once __DIR__ . '/includes/header.php';

$pdo = Database::getConnection();
$featuredPlants = $pdo->query("SELECT p.*, c.name AS category_name FROM plants p JOIN categories c ON p.category_id = c.id WHERE p.is_featured = 1 LIMIT 4")->fetchAll();
$services = $pdo->query("SELECT * FROM services LIMIT 3")->fetchAll();
$workshops = $pdo->query("SELECT * FROM workshops WHERE status = 'upcoming' ORDER BY schedule_date ASC LIMIT 2")->fetchAll();
?>

<section class="hero-section">
    <div class="container hero-grid">
        <div class="hero-content">
            <h1>Cultivating Thriving Green Spaces in Kegalle</h1>
            <p>Explore an authentic collection of indigenous Sri Lankan plants, ornamental indoor flora, edible garden essentials, and professional landscaping services.</p>
            <div class="hero-actions">
                <a href="<?= APP_URL ?>/pages/catalog.php" class="btn btn-primary" style="background: white; color: var(--primary-dark);">Explore Plant Catalogue</a>
                <a href="<?= APP_URL ?>/pages/services.php" class="btn btn-secondary" style="background: transparent; color: white; border-color: rgba(255,255,255,0.4);">Book Landscaping</a>
            </div>
        </div>
        <div class="hero-card">
            <h3>🌱 Kegalle Nursery Hub</h3>
            <p>Strategically situated on Colombo-Kandy Road, EcoSprout brings modern botanical care, organic composting workshops, and curated tropical species right to your doorstep.</p>
            <div class="hero-stats">
                <div class="stat-box">
                    <h4>150+</h4>
                    <span>Botanical Varieties</span>
                </div>
                <div class="stat-box">
                    <h4>100%</h4>
                    <span>Organic Practices</span>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="container" style="margin-bottom: 4rem;">
    <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 1.5rem;">
        <div>
            <h2 class="section-title">Featured Flora & Greenery</h2>
            <p class="section-subtitle" style="margin-bottom: 0;">Hand-picked varieties ideal for home interiors and regional gardens in Kegalle.</p>
        </div>
        <a href="<?= APP_URL ?>/pages/catalog.php" class="btn btn-outline-sm">View All Plants &rarr;</a>
    </div>

    <div class="plant-grid">
        <?php foreach ($featuredPlants as $plant): ?>
            <div class="plant-card">
                <div class="plant-img-placeholder">🌿</div>
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
</section>

<section style="background: var(--surface-white); padding: 4rem 0; border-top: 1px solid var(--border-color); border-bottom: 1px solid var(--border-color); margin-bottom: 4rem;">
    <div class="container">
        <h2 class="section-title">Comprehensive Gardening & Landscaping</h2>
        <p class="section-subtitle">Tailored horticultural services for domestic residences, villas, and commercial spaces across Sabaragamuwa Province.</p>

        <div class="services-grid">
            <?php foreach ($services as $srv): ?>
                <div class="service-card">
                    <div class="service-icon">🍃</div>
                    <h3><?= htmlspecialchars($srv['title']) ?></h3>
                    <p class="text-muted" style="margin-bottom: 1rem; color: var(--text-muted);"><?= htmlspecialchars($srv['short_desc']) ?></p>
                    <span style="font-weight: 700; color: var(--primary-dark);"><?= htmlspecialchars($srv['price_estimate']) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
        <div style="text-align: center;">
            <a href="<?= APP_URL ?>/pages/services.php" class="btn btn-primary">Schedule a Site Consultation</a>
        </div>
    </div>
</section>

<section class="container" style="margin-bottom: 4rem;">
    <h2 class="section-title">Educational Workshops & Community Learning</h2>
    <p class="section-subtitle">Hands-on interactive masterclasses led by seasoned agronomists and landscape artists.</p>

    <div class="workshop-grid">
        <?php foreach ($workshops as $ws): ?>
            <div class="workshop-card">
                <span class="workshop-date">📅 <?= date('M d, Y', strtotime($ws['schedule_date'])) ?> | <?= htmlspecialchars($ws['schedule_time']) ?></span>
                <h3 style="margin-bottom: 8px;"><?= htmlspecialchars($ws['title']) ?></h3>
                <p style="color: var(--text-muted); margin-bottom: 12px;">Instructor: <strong><?= htmlspecialchars($ws['instructor']) ?></strong></p>
                <p style="font-size: 0.9rem; color: var(--text-muted); margin-bottom: 16px;">📍 <?= htmlspecialchars($ws['location']) ?></p>
                <div style="margin-top: auto; display: flex; justify-content: space-between; align-items: center;">
                    <span style="font-weight: 700; font-size: 1.1rem; color: var(--primary);"><?= APP_CURRENCY ?> <?= number_format($ws['fee'], 2) ?></span>
                    <a href="<?= APP_URL ?>/pages/workshops.php" class="btn btn-primary-sm">Register Seat</a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
