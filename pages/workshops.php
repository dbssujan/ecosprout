<?php
$pageTitle = 'Workshops & Events';
$extraScript = 'workshops.js';
require_once __DIR__ . '/../includes/header.php';

$pdo = Database::getConnection();
$currentUser = current_user();
$isStaffOrAdmin = has_role(['admin', 'staff']);
$csrf = generate_csrf_token();

$msg = '';
$error = '';

// Ensure 'status' column exists in workshop_registrations for review & acceptance workflow
try {
    $colCheck = $pdo->query("SHOW COLUMNS FROM workshop_registrations LIKE 'status'")->fetch();
    if (!$colCheck) {
        $pdo->exec("ALTER TABLE workshop_registrations ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'pending'");
    }
} catch (Exception $e) {
    // If ALTER permissions are restricted, queries fall back to existing columns
}

// -------------------------------------------------------------------------
// SERVER-SIDE FORM HANDLERS (Fallback & Direct Submissions)
// -------------------------------------------------------------------------

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postCsrf = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($postCsrf)) {
        $error = 'Security session expired. Please refresh and try again.';
    } else {
        // 1. Staff/Admin: Create New Workshop
        if (isset($_POST['action']) && $_POST['action'] === 'create_workshop' && $isStaffOrAdmin) {
            $title = sanitize($_POST['title'] ?? '');
            $instructor = sanitize($_POST['instructor'] ?? '');
            $scheduleDate = $_POST['schedule_date'] ?? '';
            $scheduleTime = sanitize($_POST['schedule_time'] ?? '');
            $location = sanitize($_POST['location'] ?? 'EcoSprout Training Centre, Kegalle');
            $fee = (float) ($_POST['fee'] ?? 0);
            $capacity = (int) ($_POST['capacity'] ?? 25);
            $status = in_array($_POST['status'] ?? '', ['upcoming', 'completed', 'cancelled'], true) ? $_POST['status'] : 'upcoming';

            if (empty($title) || empty($instructor) || empty($scheduleDate) || empty($scheduleTime) || $capacity <= 0) {
                $error = 'Please fill out all required workshop fields correctly.';
            } else {
                try {
                    $insStmt = $pdo->prepare("INSERT INTO workshops (title, instructor, schedule_date, schedule_time, location, fee, capacity, seats_booked, status) 
                                             VALUES (:title, :inst, :sdate, :stime, :loc, :fee, :cap, 0, :status)");
                    $insStmt->execute([
                        ':title' => $title,
                        ':inst' => $instructor,
                        ':sdate' => $scheduleDate,
                        ':stime' => $scheduleTime,
                        ':loc' => $location,
                        ':fee' => $fee,
                        ':cap' => $capacity,
                        ':status' => $status
                    ]);
                    $msg = "Workshop '{$title}' created successfully!";
                } catch (PDOException $e) {
                    $error = 'Failed to create workshop: ' . $e->getMessage();
                }
            }
        }

        // 2. Staff/Admin: Accept or Reject Participant
        if (isset($_POST['action']) && $_POST['action'] === 'update_applicant_status' && $isStaffOrAdmin) {
            $regId = (int) ($_POST['registration_id'] ?? 0);
            $newStatus = in_array($_POST['target_status'] ?? '', ['accepted', 'rejected', 'pending'], true) ? $_POST['target_status'] : 'pending';
            $workshopId = (int) ($_POST['workshop_id'] ?? 0);

            if ($regId > 0 && $workshopId > 0) {
                try {
                    $pdo->beginTransaction();

                    // Update participant application status
                    $stmt = $pdo->prepare("UPDATE workshop_registrations SET status = :st WHERE id = :id");
                    $stmt->execute([':st' => $newStatus, ':id' => $regId]);

                    // Recalculate confirmed seats booked
                    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM workshop_registrations WHERE workshop_id = :wid AND status = 'accepted'");
                    $countStmt->execute([':wid' => $workshopId]);
                    $acceptedCount = (int) $countStmt->fetchColumn();

                    $updSeatStmt = $pdo->prepare("UPDATE workshops SET seats_booked = :cnt WHERE id = :wid");
                    $updSeatStmt->execute([':cnt' => $acceptedCount, ':wid' => $workshopId]);

                    $pdo->commit();
                    $msg = "Participant status updated to '" . ucfirst($newStatus) . "'.";
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $error = 'Failed to update member status: ' . $e->getMessage();
                }
            }
        }

        // 3. Customer: Apply for Workshop
        if (isset($_POST['action']) && $_POST['action'] === 'apply_workshop') {
            $workshopId = (int) ($_POST['workshop_id'] ?? 0);
            $name = sanitize($_POST['name'] ?? '');
            $email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
            $phone = sanitize($_POST['phone'] ?? '');
            $userId = $currentUser['id'] ?? null;

            if ($workshopId <= 0 || empty($name) || !$email || empty($phone)) {
                $error = 'Please complete all required application details.';
            } else {
                try {
                    $pdo->beginTransaction();

                    // Check workshop capacity
                    $wsStmt = $pdo->prepare("SELECT capacity, seats_booked, title FROM workshops WHERE id = :id FOR UPDATE");
                    $wsStmt->execute([':id' => $workshopId]);
                    $targetWs = $wsStmt->fetch();

                    if (!$targetWs) {
                        $pdo->rollBack();
                        $error = 'The requested workshop was not found.';
                    } elseif ($targetWs['seats_booked'] >= $targetWs['capacity']) {
                        $pdo->rollBack();
                        $error = 'Sorry, this workshop is already at full capacity.';
                    } else {
                        // Check if already registered
                        $dupQuery = "SELECT id FROM workshop_registrations WHERE workshop_id = :wid AND participant_email = :email";
                        $dupParams = [':wid' => $workshopId, ':email' => $email];
                        $dupStmt = $pdo->prepare($dupQuery);
                        $dupStmt->execute($dupParams);

                        if ($dupStmt->fetch()) {
                            $pdo->rollBack();
                            $error = 'You have already submitted an application for this workshop.';
                        } else {
                            $regStmt = $pdo->prepare("INSERT INTO workshop_registrations 
                                (workshop_id, user_id, participant_name, participant_email, participant_phone, payment_status, status) 
                                VALUES (:wid, :uid, :name, :email, :phone, 'unpaid', 'pending')");
                            $regStmt->execute([
                                ':wid' => $workshopId,
                                ':uid' => !empty($userId) ? $userId : null,
                                ':name' => $name,
                                ':email' => $email,
                                ':phone' => $phone
                            ]);

                            $pdo->commit();
                            $msg = "Application submitted for '{$targetWs['title']}'! Our nursery team will review and confirm your seat shortly.";
                        }
                    }
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $error = 'Application submission failed: ' . $e->getMessage();
                }
            }
        }
    }
}

// -------------------------------------------------------------------------
// DATA FETCHING
// -------------------------------------------------------------------------

// 1. Fetch all workshops
$workshops = $pdo->query("SELECT * FROM workshops ORDER BY schedule_date ASC")->fetchAll();

// 2. Fetch pending count & applicants for Staff/Admin
$allRegistrations = [];
$pendingCount = 0;
if ($isStaffOrAdmin) {
    $regQuery = "SELECT wr.*, w.title AS workshop_title, w.schedule_date, w.schedule_time, w.capacity, w.seats_booked 
                 FROM workshop_registrations wr 
                 JOIN workshops w ON wr.workshop_id = w.id 
                 ORDER BY wr.id DESC";
    $allRegistrations = $pdo->query($regQuery)->fetchAll();
    foreach ($allRegistrations as $r) {
        if (($r['status'] ?? 'pending') === 'pending') {
            $pendingCount++;
        }
    }
}

// 3. Fetch current customer's applications if logged in
$myApplications = [];
if ($currentUser) {
    $myStmt = $pdo->prepare("SELECT wr.*, w.title AS workshop_title, w.schedule_date, w.schedule_time, w.location, w.fee 
                             FROM workshop_registrations wr 
                             JOIN workshops w ON wr.workshop_id = w.id 
                             WHERE wr.user_id = :uid OR wr.participant_email = :email 
                             ORDER BY wr.id DESC");
    $myStmt->execute([
        ':uid' => $currentUser['id'],
        ':email' => $currentUser['email']
    ]);
    $myApplications = $myStmt->fetchAll();
}

// Active view tab (explore, manage, my_apps)
$activeTab = $_GET['tab'] ?? ($isStaffOrAdmin ? 'manage' : 'explore');
?>

<div class="container" style="padding-top: 2rem; padding-bottom: 4rem;">

    <!-- Page Header & Action Bar -->
    <div
        style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <h1 class="section-title" style="margin-bottom: 0.5rem;">Seasonal Gardening Workshops & Events</h1>
            <p class="section-subtitle" style="margin: 0;">Learn hands-on composting, hydroponics, and bonsai
                cultivation from regional experts at our Kegalle training grounds.</p>
        </div>

        <?php if ($isStaffOrAdmin): ?>
            <button type="button" class="btn btn-primary" onclick="openModal('createWorkshopModal')"
                style="display: inline-flex; align-items: center; gap: 8px;">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"></path>
                </svg>
                + Create New Workshop
            </button>
        <?php endif; ?>
    </div>

    <!-- Alert Notifications -->
    <?php if ($msg): ?>
        <div
            style="background: var(--primary-subtle); color: var(--primary-dark); padding: 12px 18px; border-radius: var(--radius-md); margin-bottom: 1.5rem; font-weight: 500; border-left: 4px solid var(--primary);">
            <?= htmlspecialchars($msg) ?>
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div
            style="background: #fee2e2; color: var(--danger); padding: 12px 18px; border-radius: var(--radius-md); margin-bottom: 1.5rem; font-weight: 500; border-left: 4px solid var(--danger);">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <!-- Navigation Tabs -->
    <div
        style="display: flex; gap: 10px; margin-bottom: 2rem; border-bottom: 1px solid var(--border-color); padding-bottom: 12px; flex-wrap: wrap;">
        <a href="?tab=explore" class="filter-pill <?= $activeTab === 'explore' ? 'active' : '' ?>">
            Explore Workshops (<?= count($workshops) ?>)
        </a>

        <?php if ($isStaffOrAdmin): ?>
            <a href="?tab=manage" class="filter-pill <?= $activeTab === 'manage' ? 'active' : '' ?>"
                style="display: inline-flex; align-items: center; gap: 6px;">
                Review Applicants & Members
                <?php if ($pendingCount > 0): ?>
                    <span
                        style="background: #ef4444; color: white; font-size: 0.75rem; font-weight: 700; padding: 2px 7px; border-radius: 9999px;">
                        <?= $pendingCount ?>
                    </span>
                <?php endif; ?>
            </a>
        <?php endif; ?>

        <?php if ($currentUser): ?>
            <a href="?tab=my_apps" class="filter-pill <?= $activeTab === 'my_apps' ? 'active' : '' ?>">
                My Applications (<?= count($myApplications) ?>)
            </a>
        <?php endif; ?>
    </div>

    <!-- TAB 1: EXPLORE WORKSHOPS (Customer View) -->
    <?php if ($activeTab === 'explore'): ?>
        <?php if (empty($workshops)): ?>
            <div
                style="background: var(--surface-white); border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 4rem; text-align: center;">
                <h3>No upcoming workshops scheduled yet</h3>
                <p class="text-muted">Check back soon for new organic horticulture and landscaping sessions in Kegalle.</p>
            </div>
        <?php else: ?>
            <div class="workshop-grid">
                <?php foreach ($workshops as $ws):
                    $availableSeats = $ws['capacity'] - $ws['seats_booked'];
                    $isSoldOut = $availableSeats <= 0;
                    ?>
                    <div class="workshop-card" style="position: relative;">
                        <!-- Status Tag -->
                        <div class="workshop-card-header">
                            <div class="workshop-status-row">
                                <?php if ($ws['status'] === 'completed'): ?>
                                    <span class="workshop-status-badge status-completed">
                                        <span class="status-dot"></span> Completed
                                    </span>
                                <?php elseif ($ws['status'] === 'cancelled'): ?>
                                    <span class="workshop-status-badge status-cancelled">
                                        <span class="status-dot"></span> Cancelled
                                    </span>
                                <?php elseif ($isSoldOut): ?>
                                    <span class="workshop-status-badge status-soldout">
                                        <span class="status-dot"></span> Fully Booked
                                    </span>
                                <?php else: ?>
                                    <span class="workshop-status-badge status-open">
                                        <span class="status-dot"></span> Open for Enrollment
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div class="workshop-schedule-banner">
                                <span class="schedule-item"><?= date('M d, Y', strtotime($ws['schedule_date'])) ?></span>
                                <span class="schedule-divider">|</span>
                                <span class="schedule-item"><?= htmlspecialchars($ws['schedule_time']) ?></span>
                            </div>
                        </div>

                        <h3 style="margin-bottom: 8px; color: var(--text-dark);"><?= htmlspecialchars($ws['title']) ?></h3>
                        <p style="color: var(--text-muted); margin-bottom: 6px; font-size: 0.95rem;">
                            Instructor: <strong><?= htmlspecialchars($ws['instructor']) ?></strong>
                        </p>
                        <p style="font-size: 0.9rem; color: var(--text-muted); margin-bottom: 16px;">
                            <?= htmlspecialchars($ws['location']) ?>
                        </p>

                        <!-- Seat Capacity Progress Bar -->
                        <div
                            style="background: var(--bg-light); border: 1px solid var(--border-color); padding: 10px 14px; border-radius: var(--radius-sm); margin-bottom: 18px;">
                            <div style="display: flex; justify-content: space-between; font-size: 0.85rem; margin-bottom: 6px;">
                                <span>Enrolled / Confirmed:</span>
                                <strong><?= $ws['seats_booked'] ?> / <?= $ws['capacity'] ?> Seats</strong>
                            </div>
                            <div style="height: 6px; background: #e2e8f0; border-radius: 3px; overflow: hidden;">
                                <?php
                                $pct = $ws['capacity'] > 0 ? min(100, round(($ws['seats_booked'] / $ws['capacity']) * 100)) : 0;
                                $barColor = $pct >= 90 ? 'var(--danger)' : ($pct >= 70 ? 'var(--warning)' : 'var(--primary)');
                                ?>
                                <div
                                    style="width: <?= $pct ?>%; height: 100%; background: <?= $barColor ?>; transition: width 0.3s ease;">
                                </div>
                            </div>
                        </div>

                        <!-- Card Footer -->
                        <div style="margin-top: auto; display: flex; justify-content: space-between; align-items: center; pt-2;">
                            <div>
                                <span
                                    style="font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); display: block;">Registration
                                    Fee</span>
                                <span style="font-weight: 800; font-size: 1.25rem; color: var(--primary-dark);">
                                    <?= APP_CURRENCY ?>             <?= number_format($ws['fee'], 2) ?>
                                </span>
                            </div>

                            <?php if ($ws['status'] === 'upcoming' && !$isSoldOut): ?>
                                <button type="button" class="btn btn-primary-sm"
                                    onclick="initiateWorkshopBooking(<?= $ws['id'] ?>, <?= htmlspecialchars(json_encode($ws['title']), ENT_QUOTES, 'UTF-8') ?>, <?= (float)$ws['fee'] ?>)">
                                    Apply for Seat
                                </button>
                            <?php elseif ($isSoldOut): ?>
                                <button disabled class="btn btn-secondary"
                                    style="font-size: 0.85rem; padding: 6px 12px; opacity: 0.6; cursor: not-allowed;">
                                    Sold Out
                                </button>
                            <?php else: ?>
                                <button disabled class="btn btn-secondary"
                                    style="font-size: 0.85rem; padding: 6px 12px; opacity: 0.6; cursor: not-allowed;">
                                    Closed
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <!-- TAB 2: STAFF & ADMIN MANAGEMENT VIEW -->
    <?php if ($activeTab === 'manage' && $isStaffOrAdmin): ?>
        <!-- Quick KPI Overview -->
        <div
            style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
            <div
                style="background: var(--surface-white); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 1.25rem;">
                <span
                    style="font-size: 0.8rem; color: var(--text-muted); text-transform: uppercase; font-weight: 600;">Total
                    Registrations</span>
                <div style="font-size: 1.8rem; font-weight: 700; color: var(--text-dark); margin-top: 4px;">
                    <?= count($allRegistrations) ?></div>
            </div>
            <div
                style="background: var(--surface-white); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 1.25rem;">
                <span style="font-size: 0.8rem; color: var(--warning); text-transform: uppercase; font-weight: 600;">Pending
                    Review</span>
                <div style="font-size: 1.8rem; font-weight: 700; color: var(--warning); margin-top: 4px;">
                    <?= $pendingCount ?></div>
            </div>
            <div
                style="background: var(--surface-white); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 1.25rem;">
                <span style="font-size: 0.8rem; color: var(--primary); text-transform: uppercase; font-weight: 600;">Active
                    Workshops</span>
                <div style="font-size: 1.8rem; font-weight: 700; color: var(--primary-dark); margin-top: 4px;">
                    <?= count($workshops) ?></div>
            </div>
        </div>

        <!-- Filter and Search Bar for Registrations -->
        <div
            style="background: var(--surface-white); border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 1.5rem; margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
            <div>
                <h3 style="margin: 0; font-size: 1.2rem;">Workshop Member Applications</h3>
                <p style="margin: 0; font-size: 0.85rem; color: var(--text-muted);">Review applicants, confirm their
                    enrollment, or decline seats.</p>
            </div>
            <div style="display: flex; gap: 10px; align-items: center;">
                <label style="font-size: 0.85rem; font-weight: 600; color: var(--text-muted);">Filter by Workshop:</label>
                <select id="workshopFilterSelect" class="form-control"
                    style="width: auto; padding: 6px 12px; font-size: 0.85rem;"
                    onchange="filterRegistrationsTable(this.value)">
                    <option value="all">All Workshops</option>
                    <?php foreach ($workshops as $ws): ?>
                        <option value="<?= $ws['id'] ?>"><?= htmlspecialchars($ws['title']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <!-- Registrations Data Table -->
        <div class="table-responsive">
            <table class="data-table" id="registrationsTable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Workshop Title</th>
                        <th>Applicant Details</th>
                        <th>Applied On</th>
                        <th>Status</th>
                        <th style="text-align: right;">Review & Accept</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($allRegistrations)): ?>
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 2rem; color: var(--text-muted);">
                                No member applications found.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($allRegistrations as $reg):
                            $status = $reg['status'] ?? 'pending';
                            ?>
                            <tr data-workshop-id="<?= $reg['workshop_id'] ?>">
                                <td>#<?= $reg['id'] ?></td>
                                <td>
                                    <strong><?= htmlspecialchars($reg['workshop_title']) ?></strong><br>
                                    <small class="text-muted"><?= date('M d, Y', strtotime($reg['schedule_date'])) ?>
                                        (<?= htmlspecialchars($reg['schedule_time']) ?>)</small>
                                </td>
                                <td>
                                    <strong><?= htmlspecialchars($reg['participant_name']) ?></strong><br>
                                    <small class="text-muted"><?= htmlspecialchars($reg['participant_email']) ?></small><br>
                                    <small class="text-muted"><?= htmlspecialchars($reg['participant_phone']) ?></small>
                                </td>
                                <td><?= date('Y-m-d H:i', strtotime($reg['registration_date'])) ?></td>
                                <td>
                                    <?php if ($status === 'accepted'): ?>
                                        <span class="role-pill"
                                            style="background: var(--primary-subtle); color: var(--primary-dark); font-weight: 700;">
                                            Accepted
                                        </span>
                                    <?php elseif ($status === 'rejected'): ?>
                                        <span class="role-pill" style="background: #fee2e2; color: var(--danger); font-weight: 700;">
                                            Declined
                                        </span>
                                    <?php else: ?>
                                        <span class="role-pill" style="background: #fef3c7; color: #d97706; font-weight: 700;">
                                            Pending Review
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: right; white-space: nowrap;">
                                    <div style="display: inline-flex; gap: 6px;">
                                        <!-- Accept Form -->
                                        <form method="POST" action="" style="margin: 0;">
                                            <input type="hidden" name="action" value="update_applicant_status">
                                            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                            <input type="hidden" name="registration_id" value="<?= $reg['id'] ?>">
                                            <input type="hidden" name="workshop_id" value="<?= $reg['workshop_id'] ?>">
                                            <input type="hidden" name="target_status" value="accepted">
                                            <button type="submit" class="btn btn-primary-sm"
                                                style="background: #059669; padding: 4px 10px; font-size: 0.8rem; <?= $status === 'accepted' ? 'opacity: 0.5; pointer-events: none;' : '' ?>"
                                                title="Accept participant and allocate seat">
                                                Accept
                                            </button>
                                        </form>

                                        <!-- Reject Form -->
                                        <form method="POST" action="" style="margin: 0;">
                                            <input type="hidden" name="action" value="update_applicant_status">
                                            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                            <input type="hidden" name="registration_id" value="<?= $reg['id'] ?>">
                                            <input type="hidden" name="workshop_id" value="<?= $reg['workshop_id'] ?>">
                                            <input type="hidden" name="target_status" value="rejected">
                                            <button type="submit" class="btn btn-outline-sm"
                                                style="border-color: var(--danger); color: var(--danger); padding: 4px 10px; font-size: 0.8rem; <?= $status === 'rejected' ? 'opacity: 0.5; pointer-events: none;' : '' ?>"
                                                title="Decline applicant">
                                                Decline
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <!-- TAB 3: CUSTOMER'S MY APPLICATIONS VIEW -->
    <?php if ($activeTab === 'my_apps' && $currentUser): ?>
        <h2 style="font-size: 1.3rem; margin-bottom: 1rem;">My Registered Workshop Sessions</h2>

        <?php if (empty($myApplications)): ?>
            <div
                style="background: var(--surface-white); border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 3rem; text-align: center;">
                <p class="text-muted" style="margin-bottom: 1rem;">You haven't submitted any workshop registrations yet.</p>
                <a href="?tab=explore" class="btn btn-primary">Browse Available Workshops</a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Registration #</th>
                            <th>Workshop Topic</th>
                            <th>Date & Time</th>
                            <th>Location</th>
                            <th>Tuition Fee</th>
                            <th>Application Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($myApplications as $my):
                            $status = $my['status'] ?? 'pending';
                            ?>
                            <tr>
                                <td>#<?= $my['id'] ?></td>
                                <td><strong><?= htmlspecialchars($my['workshop_title']) ?></strong></td>
                                <td><?= date('M d, Y', strtotime($my['schedule_date'])) ?> |
                                    <?= htmlspecialchars($my['schedule_time']) ?></td>
                                <td><?= htmlspecialchars($my['location']) ?></td>
                                <td><?= APP_CURRENCY ?>             <?= number_format($my['fee'], 2) ?></td>
                                <td>
                                    <?php if ($status === 'accepted'): ?>
                                        <span class="role-pill"
                                            style="background: var(--primary-subtle); color: var(--primary-dark); font-weight: 700;">
                                            Confirmed & Accepted
                                        </span>
                                    <?php elseif ($status === 'rejected'): ?>
                                        <span class="role-pill" style="background: #fee2e2; color: var(--danger); font-weight: 700;">
                                            Declined
                                        </span>
                                    <?php else: ?>
                                        <span class="role-pill" style="background: #fef3c7; color: #d97706; font-weight: 700;">
                                            Pending Nursery Review
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    <?php endif; ?>

</div>

<!-- MODAL: Customer Workshop Application -->
<div class="modal-backdrop" id="workshopModal" onclick="if(event.target === this) closeModal('workshopModal')">
    <div class="modal-box">
        <button type="button" class="modal-close-btn" onclick="closeModal('workshopModal')">&times;</button>
        <h3 style="margin-bottom: 8px;">Apply for Workshop Seat</h3>
        <p style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 1.5rem;">
            Topic: <strong id="bookingWorkshopTitle"></strong><br>
            Tuition Fee: <strong id="bookingWorkshopFee" style="color: var(--primary);"></strong>
        </p>
        <form method="POST" action="" id="workshopBookingForm">
            <input type="hidden" name="action" value="apply_workshop">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <input type="hidden" name="workshop_id" id="bookingWorkshopId" value="">

            <div class="form-group">
                <label>Applicant Full Name</label>
                <input type="text" name="name" class="form-control"
                    value="<?= htmlspecialchars($currentUser['full_name'] ?? '') ?>" required>
            </div>
            <div class="form-group">
                <label>Email Address for Confirmation</label>
                <input type="email" name="email" class="form-control"
                    value="<?= htmlspecialchars($currentUser['email'] ?? '') ?>" required>
            </div>
            <div class="form-group">
                <label>Contact Phone Number</label>
                <input type="tel" name="phone" class="form-control"
                    value="<?= htmlspecialchars($currentUser['phone'] ?? '') ?>" placeholder="+94 7X XXX XXXX" required>
            </div>
            <p style="font-size: 0.8rem; color: var(--text-muted); margin-bottom: 1.2rem;">
                * Submitting this application sends your request to our Kegalle botanical staff. You will receive
                email/SMS confirmation once accepted.
            </p>
            <button type="submit" class="btn btn-primary" style="width: 100%;">Submit Application</button>
        </form>
    </div>
</div>

<!-- MODAL: Staff & Admin Create Workshop -->
<?php if ($isStaffOrAdmin): ?>
    <div class="modal-backdrop" id="createWorkshopModal">
        <div class="modal-box" style="max-width: 600px;">
            <button type="button" class="modal-close-btn" onclick="closeModal('createWorkshopModal')">&times;</button>
            <h3 style="margin-bottom: 6px;">+ Host a New Workshop Session</h3>
            <p style="color: var(--text-muted); font-size: 0.85rem; margin-bottom: 1.5rem;">Schedule and publish botanical
                masterclasses and community sessions.</p>

            <form method="POST" action="">
                <input type="hidden" name="action" value="create_workshop">
                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">

                <div class="form-group">
                    <label>Workshop Title</label>
                    <input type="text" name="title" class="form-control"
                        placeholder="e.g. Master Organic Composting & Micro-Gardening" required>
                </div>

                <div class="form-group">
                    <label>Lead Instructor / Agronomist</label>
                    <input type="text" name="instructor" class="form-control" placeholder="e.g. Dr. Ruwan Wickramasinghe"
                        required>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label>Schedule Date</label>
                        <input type="date" name="schedule_date" class="form-control" min="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Time Window</label>
                        <input type="text" name="schedule_time" class="form-control" placeholder="e.g. 09:30 AM - 01:00 PM"
                            required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Session Location / Venue</label>
                    <input type="text" name="location" class="form-control" value="EcoSprout Training Centre, Kegalle"
                        required>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label>Participant Capacity (Seats)</label>
                        <input type="number" name="capacity" class="form-control" value="25" min="1" max="200" required>
                    </div>
                    <div class="form-group">
                        <label>Registration Fee (LKR)</label>
                        <input type="number" step="0.01" name="fee" class="form-control" value="2500.00" min="0" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Initial Status</label>
                    <select name="status" class="form-control">
                        <option value="upcoming">Upcoming (Accepting Applications)</option>
                        <option value="completed">Completed</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 0.5rem;">Publish Workshop to
                    Catalog</button>
            </form>
        </div>
    </div>
<?php endif; ?>

<script>
    function filterRegistrationsTable(workshopId) {
        const table = document.getElementById('registrationsTable');
        if (!table) return;
        const rows = table.querySelectorAll('tbody tr[data-workshop-id]');
        rows.forEach(row => {
            if (workshopId === 'all' || row.getAttribute('data-workshop-id') === workshopId) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    }
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>