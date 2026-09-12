<?php
/**
 * Meesho E-Commerce Platform - Database Configuration
 */

// Define database credentials
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'youshoo_db');
define('DB_USER', 'root');
define('DB_PASS', 'password');

/**
 * Get PDO Database Connection
 * @return PDO
 */
function getDBConnection() {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            if ($e->getCode() == 1049) {
                die("Database '" . DB_NAME . "' does not exist. Please run <a href='config/setup_database.php'>config/setup_database.php</a> to initialize the database.");
            }
            die("Database Connection Error: " . htmlspecialchars($e->getMessage()));
        }
    }
    return $pdo;
}

// Start PHP session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
