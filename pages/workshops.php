<?php
$pageTitle = 'Workshops & Events';
$extraScript = 'workshops.js';
require_once __DIR__ . '/../includes/header.php';

$pdo = Database::getConnection();
$workshops = $pdo->query("SELECT * FROM workshops ORDER BY schedule_date ASC")->fetchAll();
?>

<div class="container" style="padding-top: 2rem;">
    <h1 class="section-title">Seasonal Gardening Workshops & Events</h1>
    <p class="section-subtitle">Learn hands-on composting, hydroponics, and bonsai cultivation from regional experts at our Kegalle training grounds.</p>

    <div class="workshop-grid" style="margin-bottom: 3rem;">
        <?php foreach ($workshops as $ws): 
            $availableSeats = $ws['capacity'] - $ws['seats_booked'];
        ?>
            <div class="workshop-card">
                <span class="workshop-date">📅 <?= date('M d, Y', strtotime($ws['schedule_date'])) ?> | <?= htmlspecialchars($ws['schedule_time']) ?></span>
                <h3 style="margin-bottom: 8px;"><?= htmlspecialchars($ws['title']) ?></h3>
                <p style="color: var(--text-muted); margin-bottom: 8px;">Instructor: <strong><?= htmlspecialchars($ws['instructor']) ?></strong></p>
                <p style="font-size: 0.9rem; color: var(--text-muted); margin-bottom: 12px;">📍 <?= htmlspecialchars($ws['location']) ?></p>
                <div style="background: var(--bg-light); padding: 8px 12px; border-radius: var(--radius-sm); margin-bottom: 16px; font-size: 0.85rem;">
                    Seats Remaining: <strong><?= max(0, $availableSeats) ?> / <?= $ws['capacity'] ?></strong>
                </div>
                <div style="margin-top: auto; display: flex; justify-content: space-between; align-items: center;">
                    <span style="font-weight: 700; font-size: 1.15rem; color: var(--primary-dark);"><?= APP_CURRENCY ?> <?= number_format($ws['fee'], 2) ?></span>
                    <?php if ($availableSeats > 0): ?>
                        <button class="btn btn-primary-sm" onclick="initiateWorkshopBooking(<?= $ws['id'] ?>, '<?= addslashes(htmlspecialchars($ws['title'])) ?>', <?= $ws['fee'] ?>)">Register Seat</button>
                    <?php else: ?>
                        <span class="role-pill" style="background: #fee2e2; color: var(--danger);">Sold Out</span>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<div class="modal-backdrop" id="workshopModal">
    <div class="modal-box">
        <button class="modal-close-btn" onclick="closeModal('workshopModal')">&times;</button>
        <h3 style="margin-bottom: 10px;">Register for Workshop</h3>
        <p style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 1.5rem;">
            Topic: <strong id="bookingWorkshopTitle"></strong><br>
            Fee: <strong id="bookingWorkshopFee" style="color: var(--primary);"></strong>
        </p>

        <form id="workshopBookingForm">
            <input type="hidden" name="workshop_id" id="bookingWorkshopId" value="">
            <div class="form-group">
                <label>Participant Full Name</label>
                <input type="text" name="name" class="form-control" value="<?= htmlspecialchars(current_user()['full_name'] ?? '') ?>" required>
            </div>
            <div class="form-group">
                <label>Email Address</label>
                <input type="email" name="email" class="form-control" value="<?= htmlspecialchars(current_user()['email'] ?? '') ?>" required>
            </div>
            <div class="form-group">
                <label>Contact Phone Number</label>
                <input type="tel" name="phone" class="form-control" placeholder="+94 7X XXX XXXX" required>
            </div>
            <button type="submit" class="btn btn-primary" style="width: 100%;">Confirm Registration</button>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
