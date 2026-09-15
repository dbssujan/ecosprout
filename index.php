<?php
$pageTitle = 'Home - Kegalle Plant Nursery & Gardening Services';$extraScript = 'slider.js';
require_once __DIR__ . '/includes/header.php';

$pdo = Database::getConnection();

// Query Top Picks based on sales volume, falling back to featured & active items
$topPicksQuery = "
    SELECT p.*, c.name AS category_name, c.slug AS category_slug,
           COALESCE(SUM(oi.quantity), 0) AS total_sold
    FROM plants p
    JOIN categories c ON p.category_id = c.id
    LEFT JOIN order_items oi ON p.id = oi.plant_id
    WHERE p.is_active = 1
    GROUP BY p.id, c.name, c.slug
    ORDER BY total_sold DESC, p.is_featured DESC, p.id DESC
    LIMIT 8
";
$topPicks = $pdo->query($topPicksQuery)->fetchAll();

// Dynamic Services & Upcoming Workshops
$services =$pdo->query("SELECT * FROM services LIMIT 3")->fetchAll();
$workshops =$pdo->query("SELECT * FROM workshops WHERE status = 'upcoming' ORDER BY schedule_date ASC LIMIT 2")->fetchAll();
?>

<!-- Hero Section -->
<section class="hero-section">
    <div class="container hero-grid">
        <div class="hero-content">
            <h1>Cultivating Thriving Green Spaces in Kegalle</h1>
            <p>Explore an authentic collection of indigenous Sri Lankan plants, ornamental indoor flora, edible garden essentials, and professional landscaping services.</p>
            <div class="hero-actions">
                <a href="<?= APP_URL ?>/pages/catalog.php" class="btn btn-primary btn-hero-primary">Explore Plant Catalogue</a>
                <a href="<?= APP_URL ?>/pages/services.php" class="btn btn-secondary btn-hero-secondary">Book Landscaping</a>
            </div>
        </div>
        <div class="hero-card">
            <h3>Kegalle Nursery Hub</h3>
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

<!-- Top Picks / Best Sellers Auto Slider -->
<section class="container home-section">
    <div class="section-header-flex">
        <div>
            <h2 class="section-title">Top Picks & Best Sellers</h2>
            <p class="section-subtitle section-subtitle-tight">Our most popular, highly-requested botanical varieties and customer favorites in Kegalle.</p>
        </div>
        <div class="slider-controls-group">
            <button type="button" id="sliderPrevBtn" class="slider-nav-btn" aria-label="Previous">&larr;</button>
            <button type="button" id="sliderNextBtn" class="slider-nav-btn" aria-label="Next">&rarr;</button>
            <a href="<?= APP_URL ?>/pages/catalog.php" class="btn btn-outline-sm">View All &rarr;</a>
        </div>
    </div>

    <!-- Viewport for Horizontal Slider -->
    <div class="slider-viewport" id="plantSliderViewport">
        <div class="plant-slider-track" id="plantSliderTrack">
            <?php foreach ($topPicks as$plant): ?>
                <div class="plant-card">
                    <a href="<?= APP_URL ?>/pages/plant_details.php?id=<?= $plant['id'] ?>" class="plant-card-link">
                        <div class="plant-img-container">
                            <?php if ($plant['total_sold'] > 0 || $plant['is_featured']): ?>
                                <span class="top-pick-badge">★ Top Pick</span>
                            <?php endif; ?>

                            <img src="<?= APP_URL ?>/<?= htmlspecialchars($plant['image_url'] ?: 'assets/img/default_plant.png') ?>" 
                                 alt="<?= htmlspecialchars($plant['name']) ?>">

                            <?php if ($plant['stock_quantity'] > 0): ?>
                                <span class="stock-tag in-stock">In Stock</span>
                            <?php else: ?>
                                <span class="stock-tag out-of-stock">Out of Stock</span>
                            <?php endif; ?>
                        </div>

                        <div class="plant-info">
                            <span class="plant-category-tag"><?= htmlspecialchars($plant['category_name']) ?></span>
                            <h3 class="plant-title"><?= htmlspecialchars($plant['name']) ?></h3>
                            <p class="botanical-name"><?= htmlspecialchars($plant['botanical_name'] ?? '') ?></p>

                            <?php if (($plant['category_slug'] ?? '') !== 'tools-accessories' && !empty($plant['care_sunlight']) && $plant['care_sunlight'] !== 'Not Applicable' && $plant['care_sunlight'] !== 'N/A'): ?>
                                <div class="plant-care-badges">
                                    <span class="care-badge care-sunlight"><?= htmlspecialchars($plant['care_sunlight']) ?></span>
                                    <span class="care-badge care-water"><?= htmlspecialchars($plant['care_water'] ?? 'Moderate') ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </a>

                    <div class="plant-footer">
                        <div class="plant-price">
                            <?= APP_CURRENCY ?> <?= number_format($plant['price'], 2) ?>
                        </div>
                        <?php if ($plant['stock_quantity'] > 0): ?>
                            <button type="button" class="btn btn-primary btn-full-width" onclick="addToCart(<?= $plant['id'] ?>, 1)">
                                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                </svg>
                                Add to Cart
                            </button>
                        <?php else: ?>
                            <button disabled class="btn btn-secondary btn-full-width btn-disabled">Sold Out</button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Gardening & Landscaping Services -->
<section class="services-band">
    <div class="container">
        <h2 class="section-title">Comprehensive Gardening & Landscaping</h2>
        <p class="section-subtitle">Tailored horticultural services for domestic residences, villas, and commercial spaces across Sabaragamuwa Province.</p>
        
        <div class="services-grid">
            <?php foreach ($services as$srv): ?>
                <div class="service-card">
                    <h3><?= htmlspecialchars($srv['title']) ?></h3>
                    <p class="service-desc"><?= htmlspecialchars($srv['short_desc']) ?></p>
                    <span class="service-price"><?= htmlspecialchars($srv['price_estimate']) ?></span>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="services-action-wrap">
            <a href="<?= APP_URL ?>/pages/services.php" class="btn btn-primary">Schedule a Site Consultation</a>
        </div>
    </div>
</section>

<!-- Upcoming Workshops Section -->
<section class="container home-section">
    <div class="section-header-flex">
        <div>
            <h2 class="section-title">Educational Workshops & Community Learning</h2>
            <p class="section-subtitle section-subtitle-tight">Hands-on interactive masterclasses led by seasoned agronomists and landscape artists.</p>
        </div>
        <a href="<?= APP_URL ?>/pages/workshops.php" class="btn btn-outline-sm">View All Workshops &rarr;</a>
    </div>

    <div class="workshop-grid">
        <?php foreach ($workshops as$ws): ?>
            <div class="workshop-card">
                <div class="workshop-card-header">
                    <div class="workshop-status-row">
                        <span class="workshop-status-badge status-open">
                            <span class="status-dot"></span> Open for Enrollment
                        </span>
                    </div>
                    <div class="workshop-schedule-banner">
                        <span class="schedule-item"><?= date('M d, Y', strtotime($ws['schedule_date'])) ?></span>
                        <span class="schedule-divider">|</span>
                        <span class="schedule-item"><?= htmlspecialchars($ws['schedule_time']) ?></span>
                    </div>
                </div>

                <h3 class="workshop-title"><?= htmlspecialchars($ws['title']) ?></h3>
                <p class="workshop-instructor">Instructor: <strong><?= htmlspecialchars($ws['instructor']) ?></strong></p>
                <p class="workshop-location"><?= htmlspecialchars($ws['location']) ?></p>
                
                <div class="workshop-footer">
                    <div>
                        <span class="workshop-fee-label">Fee</span>
                        <span class="workshop-fee-val"><?= APP_CURRENCY ?> <?= number_format($ws['fee'], 2) ?></span>
                    </div>
                    <a href="<?= APP_URL ?>/pages/workshops.php?book_id=<?= $ws['id'] ?>" class="btn btn-primary-sm">Register Seat</a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>