<?php
require_once __DIR__ . '/../config/helpers.php';
start_session_safe();

header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['status' => 'error', 'message' => 'Invalid method'], 405);
}

$name = sanitize($_POST['customer_name'] ?? '');
$email = filter_var($_POST['customer_email'] ?? '', FILTER_VALIDATE_EMAIL);
$subject = sanitize($_POST['subject'] ?? '');
$message = sanitize($_POST['message'] ?? '');
$userId = current_user()['id'] ?? null;

if (empty($name) || !$email || empty($subject) || empty($message)) {
    json_response(['status' => 'error', 'message' => 'All inquiry fields are required.'], 400);
}

$pdo = Database::getConnection();

try {
    $stmt = $pdo->prepare("INSERT INTO inquiries (user_id, customer_name, customer_email, subject, message, status) 
                           VALUES (:uid, :name, :email, :subject, :message, 'open')");
    $stmt->execute([
        ':uid'     => $userId,
        ':name'    => $name,
        ':email'   => $email,
        ':subject' => $subject,
        ':message' => $message
    ]);

    json_response([
        'status'  => 'success',
        'message' => 'Your inquiry has been submitted! Our nursery agronomists will respond within 24 hours.'
    ]);
} catch (PDOException $e) {
    error_log("Inquiry Error: " . $e->getMessage());
    json_response(['status' => 'error', 'message' => 'Failed to save inquiry.'], 500);
}
