<?php
/**
 * Digital Art School - Apply to Teacher API
 * Handles student requests to join a teacher's class
 */

require_once 'config.php';
startSecureSession();

header('Content-Type: application/json');

// Check if user is logged in
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
$teacher_id = isset($input['teacher_id']) ? (int)$input['teacher_id'] : 0;
$student_id = getCurrentStudentId();

if ($teacher_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid teacher ID']);
    exit;
}

// Get database connection
$conn = getDatabaseConnection();
if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Database connection error']);
    exit;
}

// Check if teacher exists and has available slots
$check_stmt = $conn->prepare("
    SELECT teacher_id, teacher_name, current_students, max_students 
    FROM teachers 
    WHERE teacher_id = ? AND is_active = 1
");
$check_stmt->bind_param("i", $teacher_id);
$check_stmt->execute();
$teacher_result = $check_stmt->get_result();

if ($teacher_result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Teacher not found']);
    $check_stmt->close();
    closeDatabaseConnection($conn);
    exit;
}

$teacher = $teacher_result->fetch_assoc();
$check_stmt->close();

// Check if class is full
if ($teacher['current_students'] >= $teacher['max_students']) {
    echo json_encode(['success' => false, 'message' => 'This class is currently full']);
    closeDatabaseConnection($conn);
    exit;
}

// Check if student already has a pending or accepted request for this teacher
$existing_stmt = $conn->prepare("
    SELECT request_id, request_status 
    FROM teacher_requests 
    WHERE student_id = ? AND teacher_id = ?
");
$existing_stmt->bind_param("ii", $student_id, $teacher_id);
$existing_stmt->execute();
$existing_result = $existing_stmt->get_result();

if ($existing_result->num_rows > 0) {
    $existing = $existing_result->fetch_assoc();
    $status = $existing['request_status'];
    
    if ($status === 'pending') {
        echo json_encode(['success' => false, 'message' => 'You already have a pending request to this teacher']);
    } elseif ($status === 'accepted') {
        echo json_encode(['success' => false, 'message' => 'You are already enrolled in this teacher\'s class']);
    } else {
        // Status is 'rejected', allow reapplication
        // Update the existing request to pending
        $update_stmt = $conn->prepare("
            UPDATE teacher_requests 
            SET request_status = 'pending', request_date = CURRENT_TIMESTAMP, response_date = NULL
            WHERE request_id = ?
        ");
        $update_stmt->bind_param("i", $existing['request_id']);
        
        if ($update_stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Request sent successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to send request']);
        }
        
        $update_stmt->close();
    }
    
    $existing_stmt->close();
    closeDatabaseConnection($conn);
    exit;
}

$existing_stmt->close();

// Get student information for the request message
$student_stmt = $conn->prepare("
    SELECT full_name, art_category, art_discipline, skill_level, learning_purpose 
    FROM students 
    WHERE student_id = ?
");
$student_stmt->bind_param("i", $student_id);
$student_stmt->execute();
$student_result = $student_stmt->get_result();
$student = $student_result->fetch_assoc();
$student_stmt->close();

// Create request message
$request_message = "New student application from {$student['full_name']}.\n\n";
$request_message .= "Art Interest: {$student['art_category']} - {$student['art_discipline']}\n";
$request_message .= "Skill Level: " . ucwords(str_replace('_', ' ', $student['skill_level'])) . "\n";
$request_message .= "Learning Purpose: " . ucfirst($student['learning_purpose']);

// Insert new request
$insert_stmt = $conn->prepare("
    INSERT INTO teacher_requests (student_id, teacher_id, request_message, request_status) 
    VALUES (?, ?, ?, 'pending')
");
$insert_stmt->bind_param("iis", $student_id, $teacher_id, $request_message);

if ($insert_stmt->execute()) {
    echo json_encode([
        'success' => true,
        'message' => 'Your request has been sent to ' . $teacher['teacher_name']
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to send request. Please try again.'
    ]);
}

$insert_stmt->close();
closeDatabaseConnection($conn);
?>
