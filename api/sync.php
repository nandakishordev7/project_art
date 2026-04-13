<?php
/**
 * KATHAKALI BRIDGE — api/sync.php
 * Place at: htdocs/kathakali_bridge/api/sync.php
 *
 * GET  ?action=test          → test Moodle connection
 * GET  ?action=status        → show last 50 sync log entries
 * POST ?action=bulk          → sync all unsynced records
 * POST ?action=teacher&id=N  → sync one teacher to Moodle
 * POST ?action=student&id=N  → sync one student to Moodle
 * POST ?action=event&id=N    → sync one class calendar event
 */

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/db.php';
require_once __DIR__ . '/MoodleSync.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$db     = DB::conn();
$sync   = new MoodleSync();
$action = $_GET['action'] ?? '';

// ── TEST CONNECTION ───────────────────────────────────────────
if ($action === 'test') {
    echo json_encode($sync->testConnection(), JSON_PRETTY_PRINT);
    exit;
}

// ── SYNC STATUS LOG ───────────────────────────────────────────
if ($action === 'status') {
    try {
        $logs = $db->query(
            "SELECT * FROM sync_log ORDER BY created_at DESC LIMIT 50"
        )->fetchAll();
        echo json_encode(['logs' => $logs], JSON_PRETTY_PRINT);
    } catch (PDOException $e) {
        echo json_encode([
            'logs' => [],
            'note' => 'sync_log table missing — run schema_sync.sql first',
        ]);
    }
    exit;
}

// ── BULK SYNC ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'bulk') {
    $results = $sync->bulkSync();
    echo json_encode(['ok' => true, 'results' => $results], JSON_PRETTY_PRINT);
    exit;
}

// ── SYNC ONE TEACHER ──────────────────────────────────────────
if ($action === 'teacher') {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) { http_response_code(400); die(json_encode(['error' => 'id required'])); }
    $stmt = $db->prepare("SELECT * FROM teachers WHERE teacher_id = ?");
    $stmt->execute([$id]);
    $teacher = $stmt->fetch();
    if (!$teacher) { http_response_code(404); die(json_encode(['error' => 'Teacher not found'])); }
    echo json_encode($sync->createTeacher($teacher), JSON_PRETTY_PRINT);
    exit;
}

// ── SYNC ONE STUDENT ──────────────────────────────────────────
if ($action === 'student') {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) { http_response_code(400); die(json_encode(['error' => 'id required'])); }
    $stmt = $db->prepare("
        SELECT s.*, c.moodle_course_id
        FROM   students s
        JOIN   classes c ON c.class_id = s.class_id
        WHERE  s.student_id = ?
    ");
    $stmt->execute([$id]);
    $student = $stmt->fetch();
    if (!$student) { http_response_code(404); die(json_encode(['error' => 'Student not found'])); }
    echo json_encode(
        $sync->createStudent($student, (int)$student['moodle_course_id']),
        JSON_PRETTY_PRINT
    );
    exit;
}

// ── SYNC ONE CLASS EVENT ──────────────────────────────────────
if ($action === 'event') {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) { http_response_code(400); die(json_encode(['error' => 'id required'])); }
    $stmt = $db->prepare("SELECT * FROM classes WHERE class_id = ?");
    $stmt->execute([$id]);
    $class = $stmt->fetch();
    if (!$class) { http_response_code(404); die(json_encode(['error' => 'Class not found'])); }
    echo json_encode($sync->syncClassEvent($class), JSON_PRETTY_PRINT);
    exit;
}

// ── UNKNOWN ACTION ────────────────────────────────────────────
http_response_code(400);
echo json_encode([
    'error'   => 'Unknown action.',
    'usage'   => [
        'GET  ?action=test'            => 'Test Moodle connection',
        'GET  ?action=status'          => 'View sync log',
        'POST ?action=bulk'            => 'Bulk sync all unsynced records',
        'GET  ?action=teacher&id=N'    => 'Sync one teacher',
        'GET  ?action=student&id=N'    => 'Sync one student',
        'GET  ?action=event&id=N'      => 'Sync one class event',
    ],
]);