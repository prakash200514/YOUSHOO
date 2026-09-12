<?php
/**
 * Meesho Cart API Endpoint
 */
header('Content-Type: application/json');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_helper.php';

$action = $_REQUEST['action'] ?? '';
$pdo = getDBConnection();
$sessionId = get_cart_session_id();
$userId = $_SESSION['user_id'] ?? null;

if ($action === 'add') {
    $productId = (int)($_POST['product_id'] ?? 0);
    $size = trim($_POST['size'] ?? 'Free Size');
    $color = trim($_POST['color'] ?? 'Default');
    $quantity = max(1, (int)($_POST['quantity'] ?? 1));

    if ($productId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid product ID']);
        exit;
    }

    // Check if product exists and stock available
    $stmtCheck = $pdo->prepare("SELECT id, stock, title FROM products WHERE id = ?");
    $stmtCheck->execute([$productId]);
    $prod = $stmtCheck->fetch();

    if (!$prod) {
        echo json_encode(['success' => false, 'message' => 'Product not found']);
        exit;
    }

    // Check if already in cart with same size
    if ($userId) {
        $stmtExist = $pdo->prepare("SELECT id, quantity FROM cart WHERE user_id = ? AND product_id = ? AND size = ?");
        $stmtExist->execute([$userId, $productId, $size]);
    } else {
        $stmtExist = $pdo->prepare("SELECT id, quantity FROM cart WHERE session_id = ? AND product_id = ? AND size = ?");
        $stmtExist->execute([$sessionId, $productId, $size]);
    }
    $existing = $stmtExist->fetch();

    if ($existing) {
        $newQty = $existing['quantity'] + $quantity;
        $stmtUp = $pdo->prepare("UPDATE cart SET quantity = ? WHERE id = ?");
        $stmtUp->execute([$newQty, $existing['id']]);
    } else {
        $stmtIns = $pdo->prepare("INSERT INTO cart (user_id, session_id, product_id, size, color, quantity) VALUES (?, ?, ?, ?, ?, ?)");
        $stmtIns->execute([$userId, $sessionId, $productId, $size, $color, $quantity]);
    }

    echo json_encode([
        'success' => true,
        'message' => 'Added to cart successfully',
        'cart_count' => get_cart_count()
    ]);
    exit;
}

if ($action === 'update') {
    $cartId = (int)($_POST['cart_id'] ?? 0);
    $quantity = (int)($_POST['quantity'] ?? 1);

    if ($cartId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid cart item']);
        exit;
    }

    if ($quantity <= 0) {
        // delete item
        $stmtDel = $pdo->prepare("DELETE FROM cart WHERE id = ? AND (user_id = ? OR session_id = ?)");
        $stmtDel->execute([$cartId, $userId, $sessionId]);
    } else {
        $stmtUp = $pdo->prepare("UPDATE cart SET quantity = ? WHERE id = ? AND (user_id = ? OR session_id = ?)");
        $stmtUp->execute([$quantity, $cartId, $userId, $sessionId]);
    }

    echo json_encode([
        'success' => true,
        'cart_count' => get_cart_count()
    ]);
    exit;
}

if ($action === 'remove') {
    $cartId = (int)($_POST['cart_id'] ?? 0);
    $stmtDel = $pdo->prepare("DELETE FROM cart WHERE id = ? AND (user_id = ? OR session_id = ?)");
    $stmtDel->execute([$cartId, $userId, $sessionId]);

    echo json_encode([
        'success' => true,
        'cart_count' => get_cart_count()
    ]);
    exit;
}

if ($action === 'get_count') {
    echo json_encode([
        'success' => true,
        'cart_count' => get_cart_count()
    ]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Unknown action']);
