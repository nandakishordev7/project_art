<?php
require_once dirname(__DIR__) . '/auth.php';
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: ' . CORS_ORIGIN);

$db  = DB::conn();
$tid = (int)$_SESSION['teacher_id'];

if (isset($_GET['id'])) {
    $s = $db->prepare("SELECT s.*,c.name AS class_name FROM students s JOIN classes c ON c.class_id=s.class_id WHERE s.student_id=? AND c.teacher_id=?");
    $s->execute([(int)$_GET['id'], $tid]);
    $student = $s->fetch();
    if (!$student) { http_response_code(404); die(json_encode(['error'=>'Not found'])); }
    $student['accuracy_pct']     = (int)$student['accuracy_pct'];
    $student['attendance_pct']   = (int)$student['attendance_pct'];
    $student['submission_count'] = (int)$student['submission_count'];
    echo json_encode(['student'=>$student]); exit;
}

$s = $db->prepare("SELECT s.student_id,s.label,s.status,s.accuracy_pct,s.attendance_pct,s.submission_count,s.email,s.joined_date,c.class_id,c.name AS class_name FROM students s JOIN classes c ON c.class_id=s.class_id WHERE c.teacher_id=? ORDER BY s.label ASC");
$s->execute([$tid]);
$students = $s->fetchAll();
foreach ($students as &$st) {
    $st['accuracy_pct']     = (int)$st['accuracy_pct'];
    $st['attendance_pct']   = (int)$st['attendance_pct'];
    $st['submission_count'] = (int)$st['submission_count'];
} unset($st);
echo json_encode(['students'=>$students]);
