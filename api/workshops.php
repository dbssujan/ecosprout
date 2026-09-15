<?php
require_once __DIR__ . '/../config/helpers.php';
start_session_safe();
header('Content-Type: application/json; charset=UTF-8');

$pdo = Database::getConnection();
$currentUser = current_user();
$isStaffOrAdmin = has_role(['admin', 'staff']);

$action = $_POST['action'] ?? $_GET['action'] ?? 'apply';

// -------------------------------------------------------------------------
// 1. ACTION: Apply for a Workshop (Customer or Guest)
// -------------------------------------------------------------------------
if ($action === 'apply' || $action === 'apply_workshop') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_response(['status' => 'error', 'message' => 'Invalid request method'], 405);
    }

    $workshopId = (int)($_POST['workshop_id'] ?? 0);
    $name = sanitize($_POST['name'] ?? '');
    $email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
    $phone = sanitize($_POST['phone'] ?? '');
    $userId = !empty($currentUser['id']) ? (int)$currentUser['id'] : null;

    if ($workshopId <= 0 || empty($name) || !$email || empty($phone)) {
        json_response(['status' => 'error', 'message' => 'Please provide valid registration details.'], 400);
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("SELECT capacity, seats_booked, title FROM workshops WHERE id = :id FOR UPDATE");
        $stmt->execute([':id' => $workshopId]);
        $workshop = $stmt->fetch();

        if (!$workshop) {
            $pdo->rollBack();
            json_response(['status' => 'error', 'message' => 'Selected workshop not found'], 404);
        }

        if ($workshop['seats_booked'] >= $workshop['capacity']) {
            $pdo->rollBack();
            json_response(['status' => 'error', 'message' => 'Sorry, this workshop is already fully booked.'], 400);
        }

        // Check for duplicate application
        $dupStmt = $pdo->prepare("SELECT id FROM workshop_registrations WHERE workshop_id = :wid AND participant_email = :email");
        $dupStmt->execute([':wid' => $workshopId, ':email' => $email]);
        if ($dupStmt->fetch()) {
            $pdo->rollBack();
            json_response(['status' => 'error', 'message' => 'You have already applied for this workshop.'], 400);
        }

        $ins = $pdo->prepare("INSERT INTO workshop_registrations 
            (workshop_id, user_id, participant_name, participant_email, participant_phone, payment_status, status) 
            VALUES (:wid, :uid, :pname, :pemail, :pphone, 'unpaid', 'pending')");
        $ins->execute([
            ':wid'    => $workshopId,
            ':uid'    => $userId,
            ':pname'  => $name,
            ':pemail' => $email,
            ':pphone' => $phone
        ]);

        $pdo->commit();
        json_response([
            'status'  => 'success',
            'message' => "Application submitted for '{$workshop['title']}'! The nursery team will review and confirm your seat."
        ]);
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Workshop Registration Error: " . $e->getMessage());
        json_response(['status' => 'error', 'message' => 'Database error during registration.'], 500);
    }
}

// -------------------------------------------------------------------------
// 2. ACTION: Update Application Status (Staff & Admin Only)
// -------------------------------------------------------------------------
if ($action === 'update_status') {
    if (!$isStaffOrAdmin) {
        json_response(['status' => 'error', 'message' => 'Unauthorized access.'], 403);
    }

    $regId = (int)($_POST['registration_id'] ?? 0);
    $newStatus = in_array($_POST['status'] ?? '', ['accepted', 'rejected', 'pending'], true) ? $_POST['status'] : '';
    $workshopId = (int)($_POST['workshop_id'] ?? 0);

    if ($regId <= 0 || empty($newStatus) || $workshopId <= 0) {
        json_response(['status' => 'error', 'message' => 'Missing registration or status identifier.'], 400);
    }

    try {
        $pdo->beginTransaction();

        $upd = $pdo->prepare("UPDATE workshop_registrations SET status = :st WHERE id = :id");
        $upd->execute([':st' => $newStatus, ':id' => $regId]);

        // Sync seats_booked with current accepted count
        $cntStmt = $pdo->prepare("SELECT COUNT(*) FROM workshop_registrations WHERE workshop_id = :wid AND status = 'accepted'");
        $cntStmt->execute([':wid' => $workshopId]);
        $acceptedCount = (int)$cntStmt->fetchColumn();

        $wsUpd = $pdo->prepare("UPDATE workshops SET seats_booked = :cnt WHERE id = :wid");
        $wsUpd->execute([':cnt' => $acceptedCount, ':wid' => $workshopId]);

        $pdo->commit();
        json_response([
            'status'  => 'success',
            'message' => "Member status updated to " . ucfirst($newStatus) . ".",
            'seats_booked' => $acceptedCount
        ]);
    } catch (Exception $e) {
        $pdo->rollBack();
        json_response(['status' => 'error', 'message' => 'Failed to update member status.'], 500);
    }
}

// Fallback for unsupported actions
json_response(['status' => 'error', 'message' => 'Unsupported action requested.'], 400);