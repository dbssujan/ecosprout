<?php
// Initialize configuration and helper utilities.
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';

// Ensure session is started safely.
start_session_safe();

// Set appropriate JSON header for API responses.
header('Content-Type: application/json; charset=UTF-8');

// Ensure cart array exists in current session.
if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Parse request payload supporting standard POST or JSON input.
$input = $_POST;
if (empty($input)) {
    $raw = file_get_contents('php://input');
    if ($raw) {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $input = $decoded;
        }
    }
}

// Retrieve action and parameters.
$action = $input['action'] ?? $_GET['action'] ?? (isset($input['plant_id']) ? 'add' : 'get');
$plantId = isset($input['plant_id']) ? (int)$input['plant_id'] : (int)($_GET['plant_id'] ?? 0);
$quantity = isset($input['quantity']) ? (int)$input['quantity'] : (int)($_GET['quantity'] ?? 1);

// Establish database connection to verify plant data and inventory.
$pdo = Database::getConnection();

switch ($action) {
    case 'add':
        // Validate plant identifier.
        if ($plantId <= 0) {
            json_response(['status' => 'error', 'message' => 'Invalid plant selection.'], 400);
        }

        // Fetch plant details and verify stock availability.
        $stmt = $pdo->prepare("SELECT id, name, price, stock_quantity, is_active FROM plants WHERE id = :id");
        $stmt->execute([':id' => $plantId]);
        $plant = $stmt->fetch();

        if (!$plant || (isset($plant['is_active']) && (int)$plant['is_active'] === 0)) {
            json_response(['status' => 'error', 'message' => 'Requested plant is unavailable or inactive.'], 404);
        }

        // Calculate prospective quantity.
        $currentQty = $_SESSION['cart'][$plantId] ?? 0;
        $requestedTotalQty = $currentQty + max(1, $quantity);

        // Ensure requested quantity does not exceed live stock.
        if ($requestedTotalQty > (int)$plant['stock_quantity']) {
            json_response([
                'status' => 'error',
                'message' => 'Only ' . $plant['stock_quantity'] . ' units available in stock for ' . $plant['name'] . '.'
            ], 400);
        }

        // Update session cart state.
        $_SESSION['cart'][$plantId] = $requestedTotalQty;

        // Return updated item count and confirmation.
        json_response([
            'status' => 'success',
            'message' => $plant['name'] . ' added to basket.',
            'total_items' => array_sum($_SESSION['cart']),
            'plant_id' => $plantId,
            'quantity' => $_SESSION['cart'][$plantId]
        ]);
        break;

    case 'update':
        // Validate plant identifier.
        if ($plantId <= 0) {
            json_response(['status' => 'error', 'message' => 'Invalid plant selection.'], 400);
        }

        // Remove item if quantity is zero or negative.
        if ($quantity <= 0) {
            unset($_SESSION['cart'][$plantId]);
            json_response([
                'status' => 'success',
                'message' => 'Item removed from basket.',
                'total_items' => array_sum($_SESSION['cart'])
            ]);
        }

        // Verify stock limit before updating.
        $stmt = $pdo->prepare("SELECT id, name, price, stock_quantity FROM plants WHERE id = :id");
        $stmt->execute([':id' => $plantId]);
        $plant = $stmt->fetch();

        if (!$plant) {
            unset($_SESSION['cart'][$plantId]);
            json_response(['status' => 'error', 'message' => 'Plant no longer exists.'], 404);
        }

        if ($quantity > (int)$plant['stock_quantity']) {
            json_response([
                'status' => 'error',
                'message' => 'Maximum available stock is ' . $plant['stock_quantity'] . ' units.'
            ], 400);
        }

        // Save updated quantity to session.
        $_SESSION['cart'][$plantId] = $quantity;

        // Return updated state.
        json_response([
            'status' => 'success',
            'message' => 'Cart quantity updated.',
            'total_items' => array_sum($_SESSION['cart']),
            'plant_id' => $plantId,
            'quantity' => $quantity,
            'item_subtotal' => number_format($quantity * (float)$plant['price'], 2, '.', '')
        ]);
        break;

    case 'remove':
        // Remove specified plant from session cart.
        if (isset($_SESSION['cart'][$plantId])) {
            unset($_SESSION['cart'][$plantId]);
        }

        // Return updated total count.
        json_response([
            'status' => 'success',
            'message' => 'Plant removed from cart.',
            'total_items' => array_sum($_SESSION['cart'])
        ]);
        break;

    case 'clear':
        // Empty entire cart session.
        $_SESSION['cart'] = [];

        // Return empty cart status.
        json_response([
            'status' => 'success',
            'message' => 'Shopping basket emptied.',
            'total_items' => 0
        ]);
        break;

    case 'get':
    default:
        // Calculate item breakdown and totals for client display.
        $items = [];
        $subtotal = 0.0;

        if (!empty($_SESSION['cart'])) {
            $ids = array_keys($_SESSION['cart']);
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $pdo->prepare("SELECT p.*, c.name AS category_name FROM plants p JOIN categories c ON p.category_id = c.id WHERE p.id IN ($placeholders)");
            $stmt->execute($ids);
            $plants = $stmt->fetchAll();

            foreach ($plants as $p) {
                $qty = (int)($_SESSION['cart'][$p['id']] ?? 0);
                if ($qty > 0) {
                    $lineTotal = $qty * (float)$p['price'];
                    $subtotal += $lineTotal;
                    $items[] = [
                        'id' => (int)$p['id'],
                        'name' => $p['name'],
                        'category_name' => $p['category_name'],
                        'price' => (float)$p['price'],
                        'image_url' => $p['image_url'],
                        'stock_quantity' => (int)$p['stock_quantity'],
                        'quantity' => $qty,
                        'subtotal' => $lineTotal
                    ];
                }
            }
        }

        // Send detailed cart structure back to client.
        json_response([
            'status' => 'success',
            'total_items' => array_sum($_SESSION['cart']),
            'items' => $items,
            'subtotal' => $subtotal,
            'currency' => defined('APP_CURRENCY') ? APP_CURRENCY : 'LKR'
        ]);
        break;
}
