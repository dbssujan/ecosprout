<?php
$pageTitle = 'Gardening & Landscaping Services';
require_once __DIR__ . '/../includes/header.php';

$pdo = Database::getConnection();
$services = $pdo->query("SELECT * FROM services ORDER BY id ASC")->fetchAll();
$successMsg = '';
$errorMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $serviceId = (int)($_POST['service_id'] ?? 0);
    $bookingDate = $_POST['booking_date'] ?? '';
    $preferredTime = sanitize($_POST['preferred_time'] ?? '');
    $address = sanitize($_POST['address'] ?? '');
    $notes = sanitize($_POST['notes'] ?? '');
    $userId = current_user()['id'] ?? null;

    if (!$userId) {
        $errorMsg = 'Please log in or register before scheduling a landscaping consultation.';
    } elseif ($serviceId <= 0 || empty($bookingDate) || empty($preferredTime) || empty($address)) {
        $errorMsg = 'Please complete all required booking fields.';
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO service_bookings (user_id, service_id, booking_date, preferred_time, location_address, special_notes) 
                                   VALUES (:uid, :sid, :bdate, :btime, :addr, :notes)");
            $stmt->execute([
                ':uid'   => $userId,
                ':sid'   => $serviceId,
                ':bdate' => $bookingDate,
                ':btime' => $preferredTime,
                ':addr'  => $address,
                ':notes' => $notes
            ]);
            $successMsg = 'Consultation successfully scheduled! Our Kegalle team will contact you to confirm on-site logistics.';
        } catch (PDOException $e) {
            $errorMsg = 'Booking failed: ' . $e->getMessage();
        }
    }
}
?>

<div class="container" style="padding-top: 2rem;">
    <h1 class="section-title">Professional Gardening & Landscaping Services</h1>
    <p class="section-subtitle">From master residential landscaping to drip micro-irrigation, EcoSprout brings specialized botanical care to Kegalle and surrounding areas.</p>

    <?php if ($successMsg): ?>
        <div style="background: var(--primary-subtle); color: var(--primary-dark); padding: 1rem; border-radius: var(--radius-md); margin-bottom: 2rem;">
            <?= htmlspecialchars($successMsg) ?>
        </div>
    <?php endif; ?>

    <?php if ($errorMsg): ?>
        <div style="background: #fee2e2; color: var(--danger); padding: 1rem; border-radius: var(--radius-md); margin-bottom: 2rem;">
            <?= htmlspecialchars($errorMsg) ?>
        </div>
    <?php endif; ?>

    <div class="services-grid">
        <?php foreach ($services as $srv): ?>
            <div class="service-card">
                <div class="service-icon">🌱</div>
                <h3><?= htmlspecialchars($srv['title']) ?></h3>
                <p style="color: var(--text-muted); margin-bottom: 1.5rem;"><?= htmlspecialchars($srv['short_desc']) ?></p>
                <div style="font-weight: 700; color: var(--primary-dark);"><?= htmlspecialchars($srv['price_estimate']) ?></div>
            </div>
        <?php endforeach; ?>
    </div>

    
    <div style="background: var(--surface-white); border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 2.5rem; max-width: 700px; margin: 0 auto 3rem;">
        <h2 style="margin-bottom: 1.5rem; font-size: 1.4rem;">Schedule an On-Site Consultation</h2>
        <form method="POST" action="">
            <div class="form-group">
                <label>Select Required Service</label>
                <select name="service_id" class="form-control" required>
                    <option value="">-- Choose a Service --</option>
                    <?php foreach ($services as $s): ?>
                        <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['title']) ?> (<?= htmlspecialchars($s['price_estimate']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="form-group">
                    <label>Preferred Date</label>
                    <input type="date" name="booking_date" class="form-control" min="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="form-group">
                    <label>Preferred Time Slot</label>
                    <select name="preferred_time" class="form-control" required>
                        <option value="Morning (9:00 AM - 12:00 PM)">Morning (9:00 AM - 12:00 PM)</option>
                        <option value="Afternoon (1:00 PM - 4:00 PM)">Afternoon (1:00 PM - 4:00 PM)</option>
                        <option value="Late Afternoon (4:00 PM - 6:00 PM)">Late Afternoon (4:00 PM - 6:00 PM)</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label>Site Address (in Kegalle / Sabaragamuwa)</label>
                <textarea name="address" class="form-control" rows="2" placeholder="Street, town, landmark..." required></textarea>
            </div>
            <div class="form-group">
                <label>Specific Garden Notes or Plant Requirements</label>
                <textarea name="notes" class="form-control" rows="3" placeholder="Describe your soil, sunlight, garden dimensions, or specific problems..."></textarea>
            </div>
            <button type="submit" class="btn btn-primary" style="width: 100%;">Book Professional Consultation</button>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
