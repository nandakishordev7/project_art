<?php
/**
 * Digital Art School - Database Configuration
 * 
 * Connection Specifications:
 * - Host: 127.0.0.1 (for stability over localhost)
 * - Port: 3306 (MySQL - change if your port is different)
 * - User: root
 * - Password: 
 * - Collation: utf8mb4_unicode_ci (Moodle compatible)
 * 
 * NOTE: Run find_port.php to find your actual database port!
 */

// Database Configuration
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306'); // IMPORTANT: Run find_port.php to find your actual port!
define('DB_USER', 'root');
define('DB_PASS', ''); // Database password
define('DB_NAME', 'das_student');
define('DB_CHARSET', 'utf8mb4');
define('DB_COLLATE', 'utf8mb4_unicode_ci');

// Session Configuration
// Session Configuration
define('SESSION_LIFETIME', 3600); // 1 hour

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.gc_maxlifetime', SESSION_LIFETIME);

    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
}

// Security Configuration
define('PASSWORD_MIN_LENGTH', 8);
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_TIME', 900); // 15 minutes

// File Upload Configuration
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('MAX_FILE_SIZE', 10485760); // 10MB
define('ALLOWED_FILE_TYPES', ['jpg', 'jpeg', 'png', 'pdf', 'mp4', 'mov', 'avi']);

/**
 * Create Database Connection
 * 
 * @return mysqli|null Database connection object or null on failure
 */
function getDatabaseConnection() {
    $mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
    
    if ($mysqli->connect_error) {
        error_log("Database connection failed: " . $mysqli->connect_error);
        return null;
    }
    
    // Set charset
    if (!$mysqli->set_charset(DB_CHARSET)) {
        error_log("Error setting charset: " . $mysqli->error);
        return null;
    }
    
    return $mysqli;
}

/**
 * Close Database Connection
 * 
 * @param mysqli $conn Database connection object
 */
function closeDatabaseConnection($conn) {
    if ($conn) {
        $conn->close();
    }
}

/**
 * Sanitize Input
 * 
 * @param string $data Input data
 * @return string Sanitized data
 */
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

/**
 * Validate Email
 * 
 * @param string $email Email address
 * @return bool True if valid, false otherwise
 */
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Hash Password
 * 
 * @param string $password Plain text password
 * @return string Hashed password
 */
function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
}

/**
 * Verify Password
 * 
 * @param string $password Plain text password
 * @param string $hash Hashed password
 * @return bool True if match, false otherwise
 */
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * Generate CSRF Token
 * 
 * @return string CSRF token
 */
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF Token
 * 
 * @param string $token Token to verify
 * @return bool True if valid, false otherwise
 */
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Start Secure Session
 */
function startSecureSession() {

    if (session_status() !== PHP_SESSION_ACTIVE) {

        session_start();

        if (empty($_SESSION['initiated'])) {

            session_regenerate_id(true);

            $_SESSION['initiated'] = time();
        }
    }
}

/**
 * Check if User is Logged In
 * 
 * @return bool True if logged in, false otherwise
 */
function isLoggedIn() {
    return isset($_SESSION['student_id']) && isset($_SESSION['email']);
}

/**
 * Get Current Student ID
 * 
 * @return int|null Student ID or null if not logged in
 */
function getCurrentStudentId() {
    return isset($_SESSION['student_id']) ? (int)$_SESSION['student_id'] : null;
}

/**
 * JSON Response
 * 
 * @param bool $success Success status
 * @param string $message Response message
 * @param array $data Additional data
 */
function jsonResponse($success, $message, $data = []) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    exit;
}

// Error reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../error.log');
?>
