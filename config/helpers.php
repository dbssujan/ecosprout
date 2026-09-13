<?php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

function start_session_safe(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

function is_logged_in(): bool {
    start_session_safe();
    return isset($_SESSION['user_id']);
}

function current_user(): ?array {
    start_session_safe();
    if (!is_logged_in()) {
        return null;
    }
    return [
        'id'        => $_SESSION['user_id'],
        'full_name' => $_SESSION['user_name'] ?? 'User',
        'email'     => $_SESSION['user_email'] ?? '',
        'role'      => $_SESSION['user_role'] ?? 'customer'
    ];
}

function has_role(string|array $roles): bool {
    $user = current_user();
    if (!$user) return false;
    if (is_array($roles)) {
        return in_array($user['role'], $roles, true);
    }
    return $user['role'] === $roles;
}

function require_login(): void {
    if (!is_logged_in()) {
        header("Location: " . APP_URL . "/pages/login.php?redirect=" . urlencode($_SERVER['REQUEST_URI']));
        exit;
    }
}

function require_role(string|array $roles): void {
    require_login();
    if (!has_role($roles)) {
        http_response_code(403);
        die("403 Forbidden: You do not have permission to access this resource.");
    }
}

function generate_csrf_token(): string {
    start_session_safe();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf_token(?string $token): bool {
    start_session_safe();
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

function sanitize(string $data): string {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

function json_response(array $data, int $statusCode = 200): void {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($data);
    exit;
}
?>
