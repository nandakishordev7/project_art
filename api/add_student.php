<?php
/**
 * api/add_student.php
 * Called from the teacher dashboard to add a student manually.
 *
 * POST body (JSON):
 * {
 *   "name":     "Student Name",
 *   "email":    "student@example.com",
 *   "phone":    "+91 98765 43210",
 *   "class_id": 2
 * }
 *
 * Returns:
 * {
 *   "ok": true,
 *   "student_id": 8,
 *   "moodle": { "ok": true, "moodle_user_id": 11, "enrolled": true }
 * }
 */

require_once dirname(__DIR__) . '/auth.php';
require_once dirname(__DIR__) . '/api/MoodleSync.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: ' . CORS_ORIGIN);
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode(['error' => 'POST required']));
}

$db   = DB::conn();
$tid  = (int)$_SESSION['teacher_id'];
$body = json_decode(file_get_contents('php://input'), true) ?? [];

$name     = trim($body['name']     ?? '');
$email    = trim($body['email']    ?? '');
$phone    = trim($body['phone']    ?? '');
$class_id = (int)($body['class_id'] ?? 0);

// ── VALIDATE ──────────────────────────────────────────────────
$errors = [];
if (!$name)  $errors[] = 'Name is required';
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email required';
if (!$class_id) $errors[] = 'class_id required';

// Confirm this class belongs to this teacher
if ($class_id) {
    $chk = $db->prepare("SELECT class_id, moodle_course_id FROM classes WHERE class_id = ? AND teacher_id = ?");
    $chk->execute([$class_id, $tid]);
    $classRow = $chk->fetch();
    if (!$classRow) $errors[] = 'Class not found or not yours';
}

// Check email unique
if (empty($errors)) {
    $dup = $db->prepare("SELECT student_id FROM students WHERE email = ?");
    $dup->execute([$email]);
    if ($dup->fetch()) $errors[] = 'A student with this email already exists';
}

if (!empty($errors)) {
    http_response_code(400);
    die(json_encode(['ok' => false, 'errors' => $errors]));
}

// ── INSERT STUDENT ────────────────────────────────────────────
$count = (int)$db->query("SELECT COUNT(*) FROM students")->fetchColumn();
$label = 'Student-' . ($count + 1);

$ins = $db->prepare("
    INSERT INTO students
        (label, email, class_id, status, joined_date,
         accuracy_pct, attendance_pct, submission_count, moodle_user_id)
    VALUES (?, ?, ?, 'active', CURDATE(), 0, 0, 0, 0)
");
$ins->execute([$name . ' (' . $label . ')', $email, $class_id]);
$studentId = (int)$db->lastInsertId();

// ── SYNC TO MOODLE ────────────────────────────────────────────
$sync           = new MoodleSync();
$moodleCourseId = (int)($classRow['moodle_course_id'] ?? 0);

$student = [
    'student_id'     => $studentId,
    'label'          => $label,
    'email'          => $email,
    'moodle_user_id' => 0,
];

$moodleResult = $moodleCourseId > 0
    ? $sync->createStudent($student, $moodleCourseId)
    : ['ok' => false, 'error' => 'No moodle_course_id set for this class'];

echo json_encode([
    'ok'         => true,
    'student_id' => $studentId,
    'label'      => $label,
    'moodle'     => $moodleResult,
]);