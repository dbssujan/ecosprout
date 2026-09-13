<?php
require_once __DIR__ . '/../config/helpers.php';
start_session_safe();

header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['status' => 'error', 'message' => 'Invalid request method'], 405);
}

$workshopId = (int)($_POST['workshop_id'] ?? 0);
$name = sanitize($_POST['name'] ?? '');
$email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
$phone = sanitize($_POST['phone'] ?? '');
$userId = current_user()['id'] ?? 1; // Default fallback to user or guest

if ($workshopId <= 0 || empty($name) || !$email || empty($phone)) {
    json_response(['status' => 'error', 'message' => 'Please provide valid registration details.'], 400);
}

$pdo = Database::getConnection();

try {
    $pdo->beginTransaction();

    // Check capacity
    $stmt = $pdo->prepare("SELECT capacity, seats_booked, title FROM workshops WHERE id = :id FOR UPDATE");
    $stmt->execute([':id' => $workshopId]);
    $workshop = $stmt->fetch();

    if (!$workshop) {
        $pdo->rollBack();
        json_response(['status' => 'error', 'message' => 'Selected workshop not found'], 404);
    }

    if ($workshop['seats_booked'] >= $workshop['capacity']) {
        $pdo->rollBack();
        json_response(['status' => 'error', 'message' => 'Sorry, this workshop is fully booked.'], 400);
    }

    // Insert registration
    $ins = $pdo->prepare("INSERT INTO workshop_registrations 
        (workshop_id, user_id, participant_name, participant_email, participant_phone, payment_status) 
        VALUES (:wid, :uid, :pname, :pemail, :pphone, 'paid')");
    $ins->execute([
        ':wid'    => $workshopId,
        ':uid'    => $userId,
        ':pname'  => $name,
        ':pemail' => $email,
        ':pphone' => $phone
    ]);

    // Increment seats
    $upd = $pdo->prepare("UPDATE workshops SET seats_booked = seats_booked + 1 WHERE id = :id");
    $upd->execute([':id' => $workshopId]);

    $pdo->commit();

    json_response([
        'status'  => 'success',
        'message' => "Successfully registered for '{$workshop['title']}'! Confirmation sent to {$email}."
    ]);
} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Workshop Registration Error: " . $e->getMessage());
    json_response(['status' => 'error', 'message' => 'Database error during registration.'], 500);
}
