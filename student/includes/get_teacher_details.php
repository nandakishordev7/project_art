<?php
/**
 * Digital Art School - Get Teacher Details API
 * Returns teacher information for modal display
 */

require_once 'config.php';
startSecureSession();

header('Content-Type: application/json');

// Check if user is logged in
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

// Get teacher ID
$teacher_id = isset($_GET['teacher_id']) ? (int)$_GET['teacher_id'] : 0;

if ($teacher_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid teacher ID']);
    exit;
}

// Get teacher details
$conn = getDatabaseConnection();
if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Database connection error']);
    exit;
}

$stmt = $conn->prepare("
    SELECT * FROM teachers 
    WHERE teacher_id = ? AND is_active = 1
");
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $teacher = $result->fetch_assoc();
    echo json_encode([
        'success' => true,
        'teacher' => $teacher
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Teacher not found'
    ]);
}

$stmt->close();
closeDatabaseConnection($conn);
?>
