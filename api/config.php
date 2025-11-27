<?php
/**
 * Sports Camp Management System - Configuration
 * Database connection, constants, session management, CSRF protection
 */

// Error reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database configuration
// XAMPP-friendly defaults (local MySQL on localhost with root user and no password)
define('DB_HOST', 'localhost');
define('DB_NAME', 'khorasan_club');
define('DB_USER', 'root');
define('DB_PASS', '');
// Optional: allow overriding via environment variables (for advanced setups)
if (getenv('DB_HOST')) define('DB_HOST', getenv('DB_HOST'));
if (getenv('DB_NAME')) define('DB_NAME', getenv('DB_NAME'));
if (getenv('DB_USER')) define('DB_USER', getenv('DB_USER'));
if (getenv('DB_PASS') !== false) define('DB_PASS', getenv('DB_PASS'));
define('DB_CHARSET', 'utf8mb4');

// Application constants
define('PAGINATION_PER_PAGE', 20);
define('SESSION_TIMEOUT', 1800); // 30 minutes
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_DURATION', 900); // 15 minutes in seconds
define('UPLOAD_DIR', __DIR__ . '/../public/assets/uploads/');
define('INVOICE_DIR', __DIR__ . '/../public/assets/invoices/');
define('MAX_UPLOAD_SIZE', 5242880); // 5MB

// Timezone
date_default_timezone_set('Asia/Kabul');

// PDO Database connection
class Database {
    private static $instance = null;
    private $pdo;

    private function __construct() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            
            // Explicitly set charset for the connection
            $this->pdo->exec("SET NAMES 'utf8mb4' COLLATE 'utf8mb4_unicode_ci'");
            $this->pdo->exec("SET CHARACTER SET utf8mb4");
            $this->pdo->exec("SET character_set_connection=utf8mb4");
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            die("Database connection failed. Please check configuration.");
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->pdo;
    }
}

// CSRF Token management
class CSRF {
    public static function generateToken() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public static function validateToken($token) {
        if (!isset($_SESSION['csrf_token'])) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $token);
    }

    public static function getToken() {
        return self::generateToken();
    }
}

// Session management
class Session {
    public static function isLoggedIn() {
        if (!isset($_SESSION['user_id'])) {
            return false;
        }
        
        // Check session timeout
        if (isset($_SESSION['last_activity']) && 
            (time() - $_SESSION['last_activity']) > SESSION_TIMEOUT) {
            self::destroy();
            return false;
        }
        
        $_SESSION['last_activity'] = time();
        return true;
    }

    public static function requireAuth() {
        if (!self::isLoggedIn()) {
            http_response_code(401);
            echo json_encode(['error' => 'Authentication required']);
            exit;
        }
    }

    public static function requireAdmin() {
        self::requireAuth();
        if ($_SESSION['role'] !== 'admin') {
            http_response_code(403);
            echo json_encode(['error' => 'Admin access required']);
            exit;
        }
    }

    public static function getUser() {
        if (!self::isLoggedIn()) {
            return null;
        }
        return [
            'id' => $_SESSION['user_id'],
            'username' => $_SESSION['username'],
            'role' => $_SESSION['role']
        ];
    }

    public static function destroy() {
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        session_destroy();
    }
}

// Set UTF-8 encoding for PHP
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');
mb_regex_encoding('UTF-8');

// CORS headers (if needed for API) - only set if running via web server
if (php_sapi_name() !== 'cli' && isset($_SERVER['REQUEST_METHOD'])) {
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');

    // Handle preflight requests
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit;
    }
}

// Create upload directories if they don't exist
if (!file_exists(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0755, true);
}
if (!file_exists(INVOICE_DIR)) {
    mkdir(INVOICE_DIR, 0755, true);
}


