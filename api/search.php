<?php
/**
 * Meesho Search & Quick View API Endpoint
 */
header('Content-Type: application/json');

require_once __DIR__ . '/../config/db.php';

$pdo = getDBConnection();
$action = $_GET['action'] ?? '';

if ($action === 'quick_view') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(null);
        exit;
    }

    $stmt = $pdo->prepare("SELECT p.*, c.name as category_name, s.shop_name,
        (SELECT image_url FROM product_images WHERE product_id = p.id ORDER BY is_primary DESC, sort_order ASC LIMIT 1) as primary_image
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        LEFT JOIN suppliers s ON p.supplier_id = s.id
        WHERE p.id = ?");
    $stmt->execute([$id]);
    $prod = $stmt->fetch();

    echo json_encode($prod ?: null);
    exit;
}

// Live search suggestions
$query = trim($_GET['q'] ?? '');
if (strlen($query) < 2) {
    echo json_encode([]);
    exit;
}

$stmt = $pdo->prepare("SELECT p.id, p.title, p.price, p.mrp,
    (SELECT image_url FROM product_images WHERE product_id = p.id ORDER BY is_primary DESC, sort_order ASC LIMIT 1) as primary_image
    FROM products p
    WHERE p.status = 'approved' AND (p.title LIKE ? OR p.description LIKE ?)
    ORDER BY p.rating_avg DESC
    LIMIT 6");
$searchTerm = "%{$query}%";
$stmt->execute([$searchTerm, $searchTerm]);
$results = $stmt->fetchAll();

echo json_encode($results);
