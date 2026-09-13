<?php
require_once __DIR__ . '/../config/helpers.php';

header('Content-Type: application/json; charset=UTF-8');
$pdo = Database::getConnection();

$search = isset($_GET['q']) ? trim($_GET['q']) : '';
$category = isset($_GET['category']) ? trim($_GET['category']) : '';

try {
    $sql = "SELECT p.*, c.name AS category_name, c.slug AS category_slug 
            FROM plants p 
            JOIN categories c ON p.category_id = c.id 
            WHERE 1=1";
    $params = [];

    if ($search !== '') {
        $sql .= " AND (p.name LIKE :q OR p.botanical_name LIKE :q OR p.description LIKE :q)";
        $params[':q'] = '%' . $search . '%';
    }

    if ($category !== '' && $category !== 'all') {
        $sql .= " AND c.slug = :category";
        $params[':category'] = $category;
    }

    $sql .= " ORDER BY p.id DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $plants = $stmt->fetchAll();

    echo json_encode([
        'status' => 'success',
        'count'  => count($plants),
        'plants' => $plants
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'status'  => 'error',
        'message' => 'Failed to query botanical inventory: ' . $e->getMessage()
    ]);
}
