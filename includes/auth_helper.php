<?php
/**
 * Meesho E-Commerce Platform - Authentication & Utility Helpers
 */

require_once __DIR__ . '/../config/db.php';

// Ensure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Check if a user is logged in
 */
function is_logged_in() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Get current logged in user details
 */
function get_logged_user() {
    if (!is_logged_in()) {
        return null;
    }
    return [
        'id'        => $_SESSION['user_id'] ?? null,
        'name'      => $_SESSION['user_name'] ?? 'User',
        'email'     => $_SESSION['user_email'] ?? '',
        'role'      => $_SESSION['user_role'] ?? 'customer',
        'phone'     => $_SESSION['user_phone'] ?? ''
    ];
}

/**
 * Role Checkers
 */
function is_admin() {
    return is_logged_in() && ($_SESSION['user_role'] === 'admin');
}

function is_supplier() {
    return is_logged_in() && ($_SESSION['user_role'] === 'supplier');
}

function is_customer() {
    return is_logged_in() && ($_SESSION['user_role'] === 'customer');
}

/**
 * Role Guards / Redirects
 */
function require_login($redirectUrl = '/MEESHO/auth.php') {
    if (!is_logged_in()) {
        header("Location: $redirectUrl");
        exit();
    }
}

function require_supplier($redirectUrl = '/MEESHO/supplier/index.php') {
    if (!is_supplier()) {
        header("Location: $redirectUrl");
        exit();
    }
}

function require_admin($redirectUrl = '/MEESHO/admin.php') {
    if (!is_admin()) {
        header("Location: $redirectUrl");
        exit();
    }
}

/**
 * Get Supplier Info for logged-in user
 */
function get_current_supplier() {
    if (!is_supplier()) return null;
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT * FROM suppliers WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch() ?: null;
}

/**
 * Session ID identifier for guest carts
 */
function get_cart_session_id() {
    if (!isset($_SESSION['cart_session_id'])) {
        $_SESSION['cart_session_id'] = 'cart_' . bin2hex(random_bytes(16));
    }
    return $_SESSION['cart_session_id'];
}

/**
 * Get Cart Count (sum of quantities)
 */
function get_cart_count() {
    $pdo = getDBConnection();
    $sessionId = get_cart_session_id();
    $userId = $_SESSION['user_id'] ?? null;

    if ($userId) {
        $stmt = $pdo->prepare("SELECT SUM(quantity) FROM cart WHERE user_id = ? OR session_id = ?");
        $stmt->execute([$userId, $sessionId]);
    } else {
        $stmt = $pdo->prepare("SELECT SUM(quantity) FROM cart WHERE session_id = ?");
        $stmt->execute([$sessionId]);
    }
    return (int)($stmt->fetchColumn() ?: 0);
}

/**
 * Currency Formatter
 */
function format_price($amount) {
    return '₹' . number_format((float)$amount, 0);
}

/**
 * Generate CSRF Token
 */
function get_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF Token
 */
function verify_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}
