<?php
require_once dirname(__DIR__) . '/auth.php';
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: ' . CORS_ORIGIN);

$db  = DB::conn();
$tid = (int)$_SESSION['teacher_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_GET['action'] ?? '';
    $id     = (int)($_GET['id'] ?? 0);
    if ($action === 'review' && $id > 0) {
        $db->prepare("UPDATE assignments SET review_status='reviewed' WHERE assignment_id=? AND teacher_id=?")->execute([$id,$tid]);
        echo json_encode(['ok'=>true]); exit;
    }
    if ($action === 'feedback' && $id > 0) {
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $msg  = trim($body['message'] ?? '');
        if (!$msg) { http_response_code(400); die(json_encode(['error'=>'Empty message'])); }
        $db->prepare("UPDATE assignments SET review_status='reviewed' WHERE assignment_id=? AND teacher_id=?")->execute([$id,$tid]);
        echo json_encode(['ok'=>true]); exit;
    }
    http_response_code(400); echo json_encode(['error'=>'Unknown action']); exit;
}

$s = $db->prepare("
    SELECT a.assignment_id, a.title, a.diff_score, a.ref_label, a.sub_label,
           a.ref_image, a.sub_image, a.submitted_at, a.moodle_assign_id,
           s.label AS student_label, c.name AS class_name
    FROM assignments a
    JOIN students s ON s.student_id=a.student_id
    JOIN classes  c ON c.class_id=a.class_id
    WHERE a.teacher_id=? AND a.review_status='pending'
    ORDER BY a.diff_score DESC, a.submitted_at ASC LIMIT 20
");
$s->execute([$tid]);
$rows = $s->fetchAll();

function ago($dt) {
    $d = time()-strtotime($dt);
    if ($d < 3600)  return floor($d/60).' min ago';
    if ($d < 86400) return floor($d/3600).' hours ago';
    return floor($d/86400).' day'.(floor($d/86400)>1?'s':'').' ago';
}
foreach ($rows as &$r) {
    $r['diff_score']       = (int)$r['diff_score'];
    $r['assignment_id']    = (int)$r['assignment_id'];
    $r['moodle_assign_id'] = (int)$r['moodle_assign_id'];
    $r['submitted_label']  = ago($r['submitted_at']);
    unset($r['submitted_at']);
} unset($r);

echo json_encode(['assignments'=>$rows,'total'=>count($rows)]);
