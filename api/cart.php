<?php
require_once __DIR__ . '/../config/helpers.php';
start_session_safe();

header('Content-Type: application/json; charset=UTF-8');

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$plantId = (int)($_POST['plant_id'] ?? $_GET['plant_id'] ?? 0);
$quantity = (int)($_POST['quantity'] ?? 1);

switch ($action) {
    case 'add':
        if ($plantId <= 0) {
            json_response(['status' => 'error', 'message' => 'Invalid plant selection'], 400);
        }
        if (isset($_SESSION['cart'][$plantId])) {
            $_SESSION['cart'][$plantId] += $quantity;
        } else {
            $_SESSION['cart'][$plantId] = $quantity;
        }
        json_response([
            'status' => 'success',
            'message' => 'Plant added to cart',
            'total_items' => array_sum($_SESSION['cart'])
        ]);
        break;

    case 'update':
        if ($plantId > 0) {
            if ($quantity > 0) {
                $_SESSION['cart'][$plantId] = $quantity;
            } else {
                unset($_SESSION['cart'][$plantId]);
            }
        }
        json_response([
            'status' => 'success',
            'total_items' => array_sum($_SESSION['cart'])
        ]);
        break;

    case 'remove':
        if (isset($_SESSION['cart'][$plantId])) {
            unset($_SESSION['cart'][$plantId]);
        }
        json_response([
            'status' => 'success',
            'total_items' => array_sum($_SESSION['cart'])
        ]);
        break;

    case 'clear':
        $_SESSION['cart'] = [];
        json_response([
            'status' => 'success',
            'total_items' => 0
        ]);
        break;

    default:
        json_response(['status' => 'error', 'message' => 'Unsupported action'], 400);
}
