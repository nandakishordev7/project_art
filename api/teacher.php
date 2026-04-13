<?php
require_once dirname(__DIR__) . '/auth.php';
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: ' . CORS_ORIGIN);

$db  = DB::conn();
$tid = (int)$_SESSION['teacher_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_GET['action'] ?? '';
    if ($action === 'mark_read') {
        $db->prepare('UPDATE notifications SET is_unread=0 WHERE teacher_id=?')->execute([$tid]);
        echo json_encode(['ok' => true]);
        exit;
    }
    if ($action === 'save_profile') {
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $name = trim($body['name'] ?? '');
        if (!$name) { http_response_code(400); die(json_encode(['error'=>'Name required'])); }
        $db->prepare('UPDATE teachers SET name=?, specialty=? WHERE teacher_id=?')
           ->execute([$name, trim($body['specialty'] ?? ''), $tid]);
        echo json_encode(['ok' => true]);
        exit;
    }
    http_response_code(400); echo json_encode(['error'=>'Unknown action']); exit;
}

// GET: teacher + notifications
$t = $db->prepare('SELECT teacher_id,name,specialty,email,initials FROM teachers WHERE teacher_id=?');
$t->execute([$tid]);

$n = $db->prepare('SELECT notif_id,text,is_unread,created_at FROM notifications WHERE teacher_id=? ORDER BY created_at DESC LIMIT 20');
$n->execute([$tid]);
$notifs = $n->fetchAll();

function time_ago($dt) {
    $d = time() - strtotime($dt);
    if ($d < 60)    return 'just now';
    if ($d < 3600)  return floor($d/60)   . ' min ago';
    if ($d < 86400) return floor($d/3600) . ' hr ago';
    return floor($d/86400) . ' day' . (floor($d/86400) > 1 ? 's' : '') . ' ago';
}

foreach ($notifs as &$row) {
    $row['time']     = time_ago($row['created_at']);
    $row['is_unread'] = (bool)$row['is_unread'];
    unset($row['created_at']);
}
unset($row);

echo json_encode([
    'teacher'       => $t->fetch(),
    'notifications' => $notifs,
    'unread_count'  => array_sum(array_column($notifs, 'is_unread')),
]);
