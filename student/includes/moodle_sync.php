<?php
/**
 * Digital Art School - Moodle Sync Helper Functions
 * Functions to sync student data with Moodle LMS
 */

require_once 'moodle_config.php';

/**
 * Sync Student to Moodle
 * Creates a new user in Moodle when a student registers
 * 
 * @param mysqli $conn DAS database connection
 * @param int $student_id Student ID from das_student database
 * @return bool|int Moodle user ID on success, false on failure
 */
function syncStudentToMoodle($conn, $student_id) {
    // Get student details
    $stmt = $conn->prepare("SELECT * FROM students WHERE student_id = ?");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $student = $result->fetch_assoc();
    $stmt->close();
    
    if (!$student) {
        error_log("Student not found: $student_id");
        return false;
    }
    
    // Check if Moodle is enabled
    if (!MOODLE_AUTO_SYNC) {
        error_log("Moodle auto-sync is disabled");
        return false;
    }
    
    // Check if Moodle is reachable
    if (!isMoodleReachable()) {
        error_log("Moodle is not reachable at " . MOODLE_URL);
        return false;
    }
    
    // Generate Moodle username from email
    $moodle_username = generateMoodleUsername($student['email']);
    
    // Generate a temporary password for Moodle (will be synced with DAS password in production)
    $moodle_password = generateMoodlePassword();
    
    // Split full name into firstname and lastname
    $name_parts = explode(' ', trim($student['full_name']), 2);
    $firstname = $name_parts[0];
    $lastname = isset($name_parts[1]) ? $name_parts[1] : '';
    
    // Prepare user data for Moodle
    $users = [
        [
            'username' => $moodle_username,
            'password' => $moodle_password,
            'firstname' => $firstname,
            'lastname' => $lastname,
            'email' => $student['email'],
            'auth' => MOODLE_DEFAULT_AUTH,
            'lang' => MOODLE_DEFAULT_LANG,
            'timezone' => MOODLE_DEFAULT_TIMEZONE,
            'country' => MOODLE_DEFAULT_COUNTRY,
            'city' => $student['location'] ?? '',
            'description' => "Art Category: " . ($student['art_category'] ?? '') . "\nArt Discipline: " . ($student['art_discipline'] ?? ''),
            'customfields' => [
                [
                    'type' => 'text',
                    'name' => 'das_student_id',
                    'value' => $student_id
                ],
                [
                    'type' => 'text',
                    'name' => 'art_discipline',
                    'value' => $student['art_discipline'] ?? ''
                ],
                [
                    'type' => 'text',
                    'name' => 'skill_level',
                    'value' => $student['skill_level'] ?? ''
                ]
            ]
        ]
    ];
    
    // Call Moodle API to create user
    $response = callMoodleAPI('core_user_create_users', ['users' => $users]);
    
    if ($response && isset($response[0]['id'])) {
        $moodle_user_id = $response[0]['id'];
        
        // Log successful sync
        logMoodleSync($conn, 'user_create', 'student', $student_id, $response, true);
        
        // Store Moodle user ID in a sync table (optional)
        // You can create a moodle_user_sync table to track this mapping
        
        error_log("Successfully synced student $student_id to Moodle user $moodle_user_id");
        return $moodle_user_id;
    } else {
        // Log failed sync
        $error = isset($response['exception']) ? $response['message'] : 'Unknown error';
        logMoodleSync($conn, 'user_create', 'student', $student_id, $response, false, $error);
        
        error_log("Failed to sync student $student_id to Moodle: " . $error);
        return false;
    }
}

/**
 * Update Student in Moodle
 * 
 * @param mysqli $conn DAS database connection
 * @param int $student_id Student ID
 * @param int $moodle_user_id Moodle user ID
 * @return bool Success status
 */
function updateStudentInMoodle($conn, $student_id, $moodle_user_id) {
    // Get student details
    $stmt = $conn->prepare("SELECT * FROM students WHERE student_id = ?");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $student = $result->fetch_assoc();
    $stmt->close();
    
    if (!$student) {
        return false;
    }
    
    // Split full name
    $name_parts = explode(' ', trim($student['full_name']), 2);
    $firstname = $name_parts[0];
    $lastname = isset($name_parts[1]) ? $name_parts[1] : '';
    
    $users = [
        [
            'id' => $moodle_user_id,
            'firstname' => $firstname,
            'lastname' => $lastname,
            'email' => $student['email'],
            'city' => $student['location'] ?? '',
            'description' => "Art Category: " . ($student['art_category'] ?? '') . "\nArt Discipline: " . ($student['art_discipline'] ?? '')
        ]
    ];
    
    $response = callMoodleAPI('core_user_update_users', ['users' => $users]);
    
    if ($response !== false) {
        logMoodleSync($conn, 'user_update', 'student', $student_id, $response, true);
        return true;
    } else {
        logMoodleSync($conn, 'user_update', 'student', $student_id, $response, false, 'Update failed');
        return false;
    }
}

/**
 * Enroll Student in Moodle Course
 * 
 * @param int $moodle_user_id Moodle user ID
 * @param int $course_id Moodle course ID
 * @return bool Success status
 */
function enrollStudentInCourse($moodle_user_id, $course_id) {
    $enrolments = [
        [
            'roleid' => MOODLE_STUDENT_ROLE_ID,
            'userid' => $moodle_user_id,
            'courseid' => $course_id
        ]
    ];
    
    $response = callMoodleAPI('enrol_manual_enrol_users', ['enrolments' => $enrolments]);
    
    return $response !== false;
}

/**
 * Check if Student Exists in Moodle by Email
 * 
 * @param string $email Student email
 * @return int|false Moodle user ID if found, false otherwise
 */
function getMoodleUserByEmail($email) {
    $response = callMoodleAPI('core_user_get_users_by_field', [
        'field' => 'email',
        'values' => [$email]
    ]);
    
    if ($response && isset($response[0]['id'])) {
        return $response[0]['id'];
    }
    
    return false;
}
?>
