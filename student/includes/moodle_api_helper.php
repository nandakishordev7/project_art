<?php
/**
 * Digital Art School - Moodle API Helper
 * Handles syncing student data to Moodle via REST API
 */

require_once 'config.php';
require_once 'moodle_config.php';

/**
 * Sync Student to Moodle
 * Creates a user in Moodle when student registers
 * 
 * @param array $studentData Student data from registration
 * @return array Result with success status and moodle_user_id
 */
function syncStudentToMoodle($studentData) {
    $result = [
        'success' => false,
        'moodle_user_id' => null,
        'message' => '',
        'error' => null
    ];
    
    // Check if Moodle is reachable
    if (!isMoodleReachable()) {
        $result['message'] = 'Moodle is not reachable';
        $result['error'] = 'MOODLE_UNREACHABLE';
        return $result;
    }
    
    // Check if web service token is configured
    if (empty(MOODLE_WS_TOKEN)) {
        $result['message'] = 'Moodle web service token not configured';
        $result['error'] = 'TOKEN_NOT_CONFIGURED';
        return $result;
    }
    
    try {
        // Generate Moodle-compatible username from email
        $username = generateMoodleUsername($studentData['email']);
        
        // Generate a random password for Moodle (student won't use it directly)
        $moodlePassword = generateMoodlePassword();
        
        // Prepare user data for Moodle
        $users = [
            [
                'username' => $username,
                'password' => $moodlePassword,
                'firstname' => explode(' ', $studentData['full_name'])[0],
                'lastname' => substr($studentData['full_name'], strpos($studentData['full_name'], ' ') + 1) ?: $studentData['full_name'],
                'email' => $studentData['email'],
                'auth' => MOODLE_DEFAULT_AUTH,
                'lang' => MOODLE_DEFAULT_LANG,
                'timezone' => MOODLE_DEFAULT_TIMEZONE,
                'country' => MOODLE_DEFAULT_COUNTRY,
                'city' => $studentData['location'] ?? 'India',
                'description' => buildStudentDescription($studentData),
                'customfields' => [
                    [
                        'type' => 'art_category',
                        'value' => $studentData['art_category'] ?? ''
                    ],
                    [
                        'type' => 'art_discipline', 
                        'value' => $studentData['art_discipline'] ?? ''
                    ],
                    [
                        'type' => 'skill_level',
                        'value' => $studentData['skill_level'] ?? ''
                    ]
                ]
            ]
        ];
        
        // Call Moodle API to create user
        $response = callMoodleAPI('core_user_create_users', ['users' => $users]);
        
        if ($response && is_array($response) && !empty($response)) {
            // User created successfully
            $moodleUser = $response[0];
            
            if (isset($moodleUser['id'])) {
                $result['success'] = true;
                $result['moodle_user_id'] = $moodleUser['id'];
                $result['message'] = 'Student synced to Moodle successfully';
                
                // Store Moodle user ID in DAS database
                updateStudentMoodleId($studentData['student_id'], $moodleUser['id'], $username);
                
                // Log the sync
                $conn = getDatabaseConnection();
                if ($conn) {
                    logMoodleSync(
                        $conn, 
                        'create_user', 
                        'student', 
                        $studentData['student_id'], 
                        $response, 
                        true
                    );
                    closeDatabaseConnection($conn);
                }
            } else {
                $result['message'] = 'Unexpected response from Moodle';
                $result['error'] = 'INVALID_RESPONSE';
            }
        } else {
            $result['message'] = 'Failed to create user in Moodle';
            $result['error'] = 'API_CALL_FAILED';
            
            // Log the failed sync
            $conn = getDatabaseConnection();
            if ($conn) {
                logMoodleSync(
                    $conn,
                    'create_user',
                    'student', 
                    $studentData['student_id'],
                    $response,
                    false,
                    'API call returned false or empty response'
                );
                closeDatabaseConnection($conn);
            }
        }
        
    } catch (Exception $e) {
        $result['message'] = 'Error syncing to Moodle: ' . $e->getMessage();
        $result['error'] = 'EXCEPTION';
        
        // Log the error
        error_log("Moodle sync error: " . $e->getMessage());
    }
    
    return $result;
}

/**
 * Build student description for Moodle profile
 * 
 * @param array $studentData Student data
 * @return string Description text
 */
function buildStudentDescription($studentData) {
    $description = "Digital Art School Student\n\n";
    
    if (!empty($studentData['art_category'])) {
        $description .= "Art Category: " . $studentData['art_category'] . "\n";
    }
    
    if (!empty($studentData['art_discipline'])) {
        $description .= "Discipline: " . $studentData['art_discipline'] . "\n";
    }
    
    if (!empty($studentData['skill_level'])) {
        $description .= "Skill Level: " . ucfirst(str_replace('_', ' ', $studentData['skill_level'])) . "\n";
    }
    
    if (!empty($studentData['learning_purpose'])) {
        $description .= "Learning Purpose: " . ucfirst($studentData['learning_purpose']) . "\n";
    }
    
    return $description;
}

/**
 * Update student record with Moodle user ID
 * 
 * @param int $studentId DAS student ID
 * @param int $moodleUserId Moodle user ID
 * @param string $moodleUsername Moodle username
 * @return bool Success status
 */
function updateStudentMoodleId($studentId, $moodleUserId, $moodleUsername) {
    $conn = getDatabaseConnection();
    if (!$conn) {
        return false;
    }
    
    $stmt = $conn->prepare("UPDATE students SET moodle_user_id = ?, moodle_username = ? WHERE student_id = ?");
    $stmt->bind_param("isi", $moodleUserId, $moodleUsername, $studentId);
    $success = $stmt->execute();
    
    $stmt->close();
    closeDatabaseConnection($conn);
    
    return $success;
}

/**
 * Get Moodle user by email
 * 
 * @param string $email Email address
 * @return array|false Moodle user data or false
 */
function getMoodleUserByEmail($email) {
    $params = [
        'criteria' => [
            [
                'key' => 'email',
                'value' => $email
            ]
        ]
    ];
    
    $response = callMoodleAPI('core_user_get_users', $params);
    
    if ($response && isset($response['users']) && !empty($response['users'])) {
        return $response['users'][0];
    }
    
    return false;
}

/**
 * Check if student is already synced to Moodle
 * 
 * @param int $studentId Student ID
 * @return bool True if synced
 */
function isStudentSyncedToMoodle($studentId) {
    $conn = getDatabaseConnection();
    if (!$conn) {
        return false;
    }
    
    $stmt = $conn->prepare("SELECT moodle_user_id FROM students WHERE student_id = ? AND moodle_user_id IS NOT NULL");
    $stmt->bind_param("i", $studentId);
    $stmt->execute();
    $result = $stmt->get_result();
    $isSynced = $result->num_rows > 0;
    
    $stmt->close();
    closeDatabaseConnection($conn);
    
    return $isSynced;
}
?>
