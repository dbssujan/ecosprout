<?php
require_once __DIR__ . '/../config/helpers.php';

header('Content-Type: application/json; charset=UTF-8');
$pdo = Database::getConnection();

$search = isset($_GET['q']) ? trim($_GET['q']) : '';
$category = isset($_GET['category']) ? trim($_GET['category']) : '';

try {
    // Only fetch active plants!
    $sql = "SELECT p.*, c.name AS category_name, c.slug AS category_slug 
            FROM plants p 
            JOIN categories c ON p.category_id = c.id 
            WHERE p.is_active = 1";
    $params = [];

    // Search filter with unique PDO placeholders
    if ($search !== '') {
        $sql .= " AND (p.name LIKE :q1 OR p.botanical_name LIKE :q2 OR p.description LIKE :q3)";
        $searchTerm = '%' . $search . '%';
        $params[':q1'] = $searchTerm;
        $params[':q2'] = $searchTerm;
        $params[':q3'] = $searchTerm;
    }

    // Category filter
    if ($category !== '' && $category !== 'all') {
        $sql .= " AND c.slug = :category";
        $params[':category'] = $category;
    }

    $sql .= " ORDER BY p.id DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $plants = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // THE FIX: Just send back the raw array so JavaScript's .map() function works!
    echo json_encode($plants);

} catch (PDOException $e) {
    http_response_code(500);
    // Return an empty array on error so JS doesn't crash
    echo json_encode([]); 
}