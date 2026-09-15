<?php
// Load necessary system dependencies and database wrappers.
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';

// Safe session start for auth checks and CSRF validation.
start_session_safe();

// Helper to sanitize incoming user inputs and satisfy static analysis.
if (!function_exists('sanitize_input')) {
    /**
     * Sanitize string input to prevent XSS.
     *
     * @param mixed $data Raw input data.
     * @return string Sanitized string.
     */
    function sanitize_input($data): string {
        if (function_exists('sanitize')) {
            return (string)sanitize($data);
        }
        return htmlspecialchars(trim((string)($data ?? '')), ENT_QUOTES, 'UTF-8');
    }
}

// Set JSON output format header.
header('Content-Type: application/json');

// Only allow HTTP POST requests.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['status' => 'error', 'message' => 'Invalid request method.'], 405);
}

// Verify CSRF token to prevent unauthorized form submission.
$csrfToken = $_POST['csrf_token'] ?? '';
if (!verify_csrf_token($csrfToken)) {
    json_response(['status' => 'error', 'message' => 'Security token expired. Please refresh and try again.'], 403);
}

// Determine request action.
$action = $_POST['action'] ?? 'submit';

// Obtain database connection instance.
$pdo = Database::getConnection();

// Handle customer inquiry submission.
if ($action === 'submit') {
    $name = trim(sanitize_input($_POST['customer_name'] ?? ''));
    $email = trim(sanitize_input($_POST['customer_email'] ?? ''));
    $category = trim(sanitize_input($_POST['category'] ?? 'General Care'));
    $subject = trim(sanitize_input($_POST['subject'] ?? ''));
    $message = trim(sanitize_input($_POST['message'] ?? ''));

    // Validate required fields are not empty.
    if (empty($name) || empty($email) || empty($subject) || empty($message)) {
        json_response(['status' => 'error', 'message' => 'Please fill in all required inquiry fields.'], 422);
    }

    // Validate customer email format.
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        json_response(['status' => 'error', 'message' => 'Please provide a valid email address.'], 422);
    }

    // Determine user ID if authenticated.
    $userId = null;
    if (is_logged_in()) {
        $user = current_user();
        $userId = $user['id'] ?? null;
    }

    try {
        // Detect whether category column is present in schema.
        $colCheck = $pdo->query("SHOW COLUMNS FROM inquiries LIKE 'category'");
        $hasCategory = ($colCheck && $colCheck->fetch());

        if ($hasCategory) {
            $stmt = $pdo->prepare("
                INSERT INTO inquiries (user_id, customer_name, customer_email, category, subject, message, status)
                VALUES (:user_id, :customer_name, :customer_email, :category, :subject, :message, 'pending')
            ");
            $stmt->execute([
                ':user_id'        => $userId,
                ':customer_name'  => $name,
                ':customer_email' => $email,
                ':category'       => $category,
                ':subject'        => $subject,
                ':message'        => $message
            ]);
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO inquiries (user_id, customer_name, customer_email, subject, message, status)
                VALUES (:user_id, :customer_name, :customer_email, :subject, :message, 'pending')
            ");
            $stmt->execute([
                ':user_id'        => $userId,
                ':customer_name'  => $name,
                ':customer_email' => $email,
                ':subject'        => $subject,
                ':message'        => $message
            ]);
        }

        $newInquiryId = (int)$pdo->lastInsertId();

        // Send successful creation response with newly created record details.
        json_response([
            'status'  => 'success',
            'message' => 'Your plant care inquiry has been submitted successfully. Our agronomist team will get back to you shortly.',
            'inquiry' => [
                'id'             => $newInquiryId,
                'category'       => $category,
                'subject'        => $subject,
                'message'        => $message,
                'status'         => 'pending',
                'created_at'     => date('Y-m-d H:i:s'),
                'formatted_date' => date('M d, Y · h:i A'),
                'staff_reply'    => null,
                'replied_at'     => null
            ]
        ], 201);

    } catch (PDOException $e) {
        json_response(['status' => 'error', 'message' => 'Failed to save inquiry due to a database error.'], 500);
    }
}

// Handle staff or administrator response submission.
if ($action === 'reply') {
    if (!is_logged_in()) {
        json_response(['status' => 'error', 'message' => 'Authentication required.'], 401);
    }

    $currentUser = current_user();
    $userRole = $currentUser['role'] ?? '';

    // Restrict reply permissions strictly to staff and administrators.
    if (!in_array($userRole, ['staff', 'admin'])) {
        json_response(['status' => 'error', 'message' => 'Forbidden: Only staff members and administrators can reply to inquiries.'], 403);
    }

    $inquiryId = (int)($_POST['inquiry_id'] ?? 0);
    $reply = trim(sanitize_input($_POST['staff_reply'] ?? ''));
    $status = trim(sanitize_input($_POST['status'] ?? 'answered'));

    if ($inquiryId <= 0 || empty($reply)) {
        json_response(['status' => 'error', 'message' => 'Valid inquiry ID and staff response text are required.'], 422);
    }

    // Normalize and validate status value.
    $allowedStatuses = ['pending', 'in_progress', 'answered', 'closed'];
    if (!in_array($status, $allowedStatuses)) {
        $status = 'answered';
    }

    try {
        // Verify target inquiry exists.
        $chk = $pdo->prepare("SELECT id FROM inquiries WHERE id = :id LIMIT 1");
        $chk->execute([':id' => $inquiryId]);
        if (!$chk->fetch()) {
            json_response(['status' => 'error', 'message' => 'Inquiry record not found.'], 404);
        }

        // Save staff response permanently with the inquiry record.
        $stmt = $pdo->prepare("
            UPDATE inquiries 
            SET staff_reply = :reply, 
                staff_id = :staff_id, 
                replied_at = NOW(), 
                status = :status 
            WHERE id = :id
        ");
        $stmt->execute([
            ':reply'    => $reply,
            ':staff_id' => $currentUser['id'],
            ':status'   => $status,
            ':id'       => $inquiryId
        ]);

        $now = date('Y-m-d H:i:s');
        $formattedDate = date('M d, Y · h:i A');

        json_response([
            'status'         => 'success',
            'message'        => 'Agronomist response saved and inquiry updated successfully.',
            'inquiry_id'     => $inquiryId,
            'status_updated' => $status,
            'staff_name'     => $currentUser['full_name'] ?? 'EcoSprout Specialist',
            'replied_at'     => $now,
            'formatted_date' => $formattedDate,
            'staff_reply'    => $reply
        ]);

    } catch (PDOException $e) {
        json_response(['status' => 'error', 'message' => 'Database error while saving staff reply.'], 500);
    }
}

// Handle inquiry status update by staff or administrator.
if ($action === 'update_status') {
    if (!is_logged_in()) {
        json_response(['status' => 'error', 'message' => 'Authentication required.'], 401);
    }

    $currentUser = current_user();
    $userRole = $currentUser['role'] ?? '';

    if (!in_array($userRole, ['staff', 'admin'])) {
        json_response(['status' => 'error', 'message' => 'Forbidden: Only staff members and administrators can update inquiry status.'], 403);
    }

    $inquiryId = (int)($_POST['inquiry_id'] ?? 0);
    $status = trim(sanitize_input($_POST['status'] ?? ''));

    $allowedStatuses = ['pending', 'in_progress', 'answered', 'closed'];
    if ($inquiryId <= 0 || !in_array($status, $allowedStatuses)) {
        json_response(['status' => 'error', 'message' => 'Invalid inquiry ID or status value.'], 422);
    }

    try {
        $stmt = $pdo->prepare("UPDATE inquiries SET status = :status WHERE id = :id");
        $stmt->execute([':status' => $status, ':id' => $inquiryId]);

        json_response([
            'status'     => 'success',
            'message'    => 'Inquiry status changed to ' . ucfirst($status) . '.',
            'inquiry_id' => $inquiryId,
            'new_status' => $status
        ]);
    } catch (PDOException $e) {
        json_response(['status' => 'error', 'message' => 'Database error while updating status.'], 500);
    }
}

// Handle inquiry deletion by administrator.
if ($action === 'delete') {
    if (!is_logged_in()) {
        json_response(['status' => 'error', 'message' => 'Authentication required.'], 401);
    }

    $currentUser = current_user();
    $userRole = $currentUser['role'] ?? '';

    // Restrict deletion strictly to administrator role.
    if ($userRole !== 'admin') {
        json_response(['status' => 'error', 'message' => 'Forbidden: Administrator privileges required to delete inquiries.'], 403);
    }

    $inquiryId = (int)($_POST['inquiry_id'] ?? 0);
    if ($inquiryId <= 0) {
        json_response(['status' => 'error', 'message' => 'Invalid inquiry ID provided.'], 422);
    }

    try {
        $stmt = $pdo->prepare("DELETE FROM inquiries WHERE id = :id");
        $stmt->execute([':id' => $inquiryId]);

        json_response([
            'status'     => 'success',
            'message'    => 'Inquiry #' . $inquiryId . ' deleted successfully.',
            'inquiry_id' => $inquiryId
        ]);
    } catch (PDOException $e) {
        json_response(['status' => 'error', 'message' => 'Database error while deleting inquiry.'], 500);
    }
}

// Default response for unhandled action.
json_response(['status' => 'error', 'message' => 'Unrecognized API action requested.'], 400);