<?php
/**
 * Digital Art School - Moodle Integration Configuration
 * 
 * This file handles both:
 * 1. Moodle REST API integration (Web Services)
 * 2. Direct Moodle database access for sync operations
 */

// ===== MOODLE DATABASE CONNECTION =====
define('MOODLE_DB_HOST', '127.0.0.1');
define('MOODLE_DB_PORT', '3306'); // Change to 3307 if using MariaDB
define('MOODLE_DB_USER', 'root');
define('MOODLE_DB_PASS', ''); // MySQL database password (same as phpMyAdmin)
define('MOODLE_DB_NAME', 'moodle_db');
define('MOODLE_DB_PREFIX', 'mdl_');
define('MOODLE_DB_CHARSET', 'utf8mb4');
define('MOODLE_DB_COLLATE', 'utf8mb4_unicode_ci');

// ===== MOODLE REST API SETTINGS =====
define('MOODLE_URL', 'http://localhost/moodle'); // Your Moodle installation URL
define('MOODLE_WS_TOKEN', ''); // Admin web service token (will be set after Moodle setup)
define('MOODLE_WS_FORMAT', 'json'); // Response format: json or xml

// ===== MOODLE INTEGRATION SETTINGS =====
define('MOODLE_AUTO_SYNC', true); // Auto-sync users to Moodle on registration
define('MOODLE_AUTO_ENROLL', true); // Auto-enroll when teacher accepts request
define('MOODLE_DEFAULT_AUTH', 'manual'); // Moodle auth method: manual, email, oauth2
define('MOODLE_DEFAULT_LANG', 'en'); // Default language
define('MOODLE_DEFAULT_TIMEZONE', 'Asia/Kolkata'); // India timezone
define('MOODLE_DEFAULT_COUNTRY', 'IN'); // India

// ===== COURSE SETTINGS =====
define('MOODLE_DEFAULT_COURSE_FORMAT', 'topics'); // Course format
define('MOODLE_COURSE_CATEGORY_DANCE', 2); // Category ID for dance courses
define('MOODLE_COURSE_CATEGORY_MUSIC', 5); // Category ID for music courses
define('MOODLE_COURSE_CATEGORY_VISUAL', 4); // Category ID for visual arts

// ===== ENROLLMENT SETTINGS =====
define('MOODLE_ENROL_METHOD', 'manual'); // Enrollment method
define('MOODLE_STUDENT_ROLE_ID', 5); // Student role ID in Moodle (default: 5)
define('MOODLE_TEACHER_ROLE_ID', 3); // Teacher role ID in Moodle (default: 3)

/**
 * Get Moodle Database Connection
 * 
 * @return mysqli|null Moodle database connection
 */
function getMoodleConnection() {
    $conn = new mysqli(
        MOODLE_DB_HOST, 
        MOODLE_DB_USER, 
        MOODLE_DB_PASS, 
        MOODLE_DB_NAME, 
        MOODLE_DB_PORT
    );
    
    if ($conn->connect_error) {
        error_log("Moodle DB connection failed: " . $conn->connect_error);
        return null;
    }
    
    if (!$conn->set_charset(MOODLE_DB_CHARSET)) {
        error_log("Error setting Moodle charset: " . $conn->error);
        return null;
    }
    
    return $conn;
}

/**
 * Call Moodle REST API
 * 
 * @param string $function Moodle web service function name
 * @param array $params Function parameters
 * @param string $token Optional specific token (defaults to admin token)
 * @return array|false API response or false on failure
 */
function callMoodleAPI($function, $params = [], $token = null) {
    if ($token === null) {
        $token = MOODLE_WS_TOKEN;
    }
    
    if (empty($token)) {
        error_log("Moodle API: No token provided");
        return false;
    }
    
    $serverurl = MOODLE_URL . '/webservice/rest/server.php';
    
    $params['wstoken'] = $token;
    $params['wsfunction'] = $function;
    $params['moodlewsrestformat'] = MOODLE_WS_FORMAT;
    
    // Initialize cURL
    $ch = curl_init($serverurl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // Follow redirects
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // For localhost SSL
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        error_log("Moodle API cURL error: " . $error);
        return false;
    }
    
    if ($httpCode !== 200) {
        error_log("Moodle API HTTP error: " . $httpCode);
        return false;
    }
    
    $result = json_decode($response, true);
    
    // Check for Moodle errors
    if (isset($result['exception'])) {
        error_log("Moodle API error: " . $result['message']);
        return false;
    }
    
    return $result;
}

/**
 * Generate Moodle Username from Email
 * 
 * @param string $email Email address
 * @return string Moodle-compatible username
 */
function generateMoodleUsername($email) {
    // Remove domain, keep only local part
    $username = strtolower(explode('@', $email)[0]);
    
    // Replace special characters with underscore
    $username = preg_replace('/[^a-z0-9_]/', '_', $username);
    
    // Ensure it starts with a letter
    if (!preg_match('/^[a-z]/', $username)) {
        $username = 'student_' . $username;
    }
    
    // Limit length to 100 chars
    return substr($username, 0, 100);
}

/**
 * Generate Random Moodle Password
 * 
 * @param int $length Password length
 * @return string Random password
 */
function generateMoodlePassword($length = 12) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
    $password = '';
    $charLength = strlen($chars);
    
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[random_int(0, $charLength - 1)];
    }
    
    return $password;
}

/**
 * Log Moodle Sync Activity
 * 
 * @param mysqli $conn DAS database connection
 * @param string $syncType Type of sync operation
 * @param string $entityType Entity type (student/teacher/enrollment)
 * @param int $entityId Entity ID
 * @param mixed $response Moodle response
 * @param bool $success Success status
 * @param string $error Error message if failed
 */
function logMoodleSync($conn, $syncType, $entityType, $entityId, $response, $success, $error = null) {
    $stmt = $conn->prepare("
        INSERT INTO moodle_sync_log 
        (sync_type, entity_type, entity_id, moodle_response, sync_status, error_message) 
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    
    $responseJson = is_array($response) ? json_encode($response) : $response;
    $status = $success ? 'success' : 'failed';
    
    $stmt->bind_param("ssisss", $syncType, $entityType, $entityId, $responseJson, $status, $error);
    $stmt->execute();
    $stmt->close();
}

/**
 * Get Moodle Course Category by Art Category
 * 
 * @param string $artCategory Art category
 * @return int Moodle course category ID
 */
function getMoodleCourseCategoryByArt($artCategory) {
    $categories = [
        'Classical Dance' => MOODLE_COURSE_CATEGORY_DANCE,
        'Vocal Music' => MOODLE_COURSE_CATEGORY_MUSIC,
        'Instrumental Music' => MOODLE_COURSE_CATEGORY_MUSIC,
        'Visual Arts' => MOODLE_COURSE_CATEGORY_VISUAL,
    ];
    
    return $categories[$artCategory] ?? MOODLE_COURSE_CATEGORY_DANCE;
}

/**
 * Check if Moodle is Reachable
 * 
 * @return bool True if Moodle is accessible
 */
function isMoodleReachable() {
    $ch = curl_init(MOODLE_URL);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // Follow redirects
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // For localhost SSL
    curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    // Accept 200, 301 (redirect), 302 (temporary redirect) as valid
    return in_array($httpCode, [200, 301, 302]);
}

/**
 * Validate Moodle Token
 * 
 * @param string $token Token to validate
 * @return bool True if valid
 */
function validateMoodleToken($token) {
    if (empty($token)) {
        return false;
    }
    
    $result = callMoodleAPI('core_webservice_get_site_info', [], $token);
    return $result !== false;
}
?>
