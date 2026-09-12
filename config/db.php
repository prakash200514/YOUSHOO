<?php
/**
 * Meesho E-Commerce Platform - Database Configuration
 */

// Parse database credentials from environment variables or use local XAMPP defaults
$dbUrl = getenv('DATABASE_URL') ?: getenv('MYSQL_URL');
if ($dbUrl) {
    $urlParts = parse_url($dbUrl);
    $host = $urlParts['host'] ?? '127.0.0.1';
    $port = $urlParts['port'] ?? 3306;
    $user = $urlParts['user'] ?? 'root';
    $pass = $urlParts['pass'] ?? '';
    $dbname = ltrim($urlParts['path'] ?? 'youshoo_db', '/');
} else {
    $host = getenv('DB_HOST') ?: '127.0.0.1';
    $port = getenv('DB_PORT') ?: '3306';
    $dbname = getenv('DB_NAME') ?: 'youshoo_db';
    $user = getenv('DB_USER') ?: 'root';
    $pass = getenv('DB_PASS') !== false ? getenv('DB_PASS') : 'password';
}

define('DB_HOST', $host);
define('DB_PORT', $port);
define('DB_NAME', $dbname);
define('DB_USER', $user);
define('DB_PASS', $pass);

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
        // Support SSL for Cloud MySQL providers like TiDB Cloud / Aiven
        if (DB_HOST !== '127.0.0.1' && DB_HOST !== 'localhost') {
            if (file_exists('/etc/ssl/certs/ca-certificates.crt')) {
                $options[PDO::MYSQL_ATTR_SSL_CA] = '/etc/ssl/certs/ca-certificates.crt';
            }
            if (defined('PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT')) {
                $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
            }
        }
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
