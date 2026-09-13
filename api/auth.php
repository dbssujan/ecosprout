<?php
require_once __DIR__ . '/../config/helpers.php';
start_session_safe();

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$pdo = Database::getConnection();

if ($action === 'logout') {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
    header("Location: " . APP_URL . "/index.php?msg=logged_out");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf)) {
        header("Location: " . APP_URL . "/pages/login.php?error=invalid_csrf");
        exit;
    }

    if ($action === 'login') {
        $email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
        $password = $_POST['password'] ?? '';

        if (!$email || empty($password)) {
            header("Location: " . APP_URL . "/pages/login.php?error=empty_fields");
            exit;
        }

        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email LIMIT 1");
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['full_name'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_role'] = $user['role'];

            // Role redirection
            if ($user['role'] === 'admin') {
                header("Location: " . APP_URL . "/pages/admin_dashboard.php");
            } elseif ($user['role'] === 'staff') {
                header("Location: " . APP_URL . "/pages/staff_inventory.php");
            } else {
                header("Location: " . APP_URL . "/pages/catalog.php");
            }
            exit;
        } else {
            header("Location: " . APP_URL . "/pages/login.php?error=invalid_credentials");
            exit;
        }
    }

    if ($action === 'register') {
        $fullName = sanitize($_POST['full_name'] ?? '');
        $email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
        $password = $_POST['password'] ?? '';
        $phone = sanitize($_POST['phone'] ?? '');
        $address = sanitize($_POST['address'] ?? '');

        if (empty($fullName) || !$email || strlen($password) < 6) {
            header("Location: " . APP_URL . "/pages/register.php?error=invalid_input");
            exit;
        }

        // Check existing
        $check = $pdo->prepare("SELECT id FROM users WHERE email = :email");
        $check->execute([':email' => $email]);
        if ($check->fetch()) {
            header("Location: " . APP_URL . "/pages/register.php?error=email_exists");
            exit;
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        $ins = $pdo->prepare("INSERT INTO users (full_name, email, password_hash, role, phone, address) 
                             VALUES (:name, :email, :hash, 'customer', :phone, :address)");
        $ins->execute([
            ':name'    => $fullName,
            ':email'   => $email,
            ':hash'    => $hash,
            ':phone'   => $phone,
            ':address' => $address
        ]);

        $_SESSION['user_id'] = $pdo->lastInsertId();
        $_SESSION['user_name'] = $fullName;
        $_SESSION['user_email'] = $email;
        $_SESSION['user_role'] = 'customer';

        header("Location: " . APP_URL . "/pages/catalog.php?msg=registered");
        exit;
    }
}

header("Location: " . APP_URL . "/index.php");
exit;
