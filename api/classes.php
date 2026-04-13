<?php
require_once dirname(__DIR__) . '/auth.php';
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: ' . CORS_ORIGIN);

$db    = DB::conn();
$tid   = (int)$_SESSION['teacher_id'];
$scope = $_GET['scope'] ?? 'today';

if ($scope === 'today') {
    $s = $db->prepare("
        SELECT c.class_id, c.name, c.class_time, c.duration_min, c.status, c.moodle_course_id,
               COUNT(s.student_id) AS student_count
        FROM classes c
        LEFT JOIN students s ON s.class_id=c.class_id AND s.status='active'
        WHERE c.teacher_id=? AND c.class_date=CURDATE() AND c.status!='cancelled'
        GROUP BY c.class_id ORDER BY c.class_time ASC
    ");
    $s->execute([$tid]);
    $rows = $s->fetchAll();
    foreach ($rows as &$r) {
        $r['student_count']    = (int)$r['student_count'];
        $r['moodle_course_id'] = (int)$r['moodle_course_id'];
        $r['time_display']     = date('g:i A', strtotime($r['class_time']));
        $end = strtotime($r['class_time']) + ($r['duration_min'] * 60);
        $r['time_range']       = $r['time_display'] . ' - ' . date('g:i A', $end);
    } unset($r);
    echo json_encode(['classes' => $rows]); exit;
}

if ($scope === 'next') {
    $s = $db->prepare("
        SELECT c.class_id, c.name, c.class_date, c.class_time, c.duration_min, c.moodle_course_id,
               COUNT(s.student_id) AS student_count
        FROM classes c
        LEFT JOIN students s ON s.class_id=c.class_id AND s.status='active'
        WHERE c.teacher_id=? AND c.status IN ('active','upcoming')
          AND CONCAT(c.class_date,' ',c.class_time) >= NOW()
        GROUP BY c.class_id ORDER BY c.class_date ASC, c.class_time ASC LIMIT 1
    ");
    $s->execute([$tid]);
    $cls = $s->fetch();
    if (!$cls) { echo json_encode(['class'=>null]); exit; }
    $cls['student_count'] = (int)$cls['student_count'];
    $cls['moodle_course_id'] = (int)$cls['moodle_course_id'];
    $starts = strtotime($cls['class_date'].' '.$cls['class_time']);
    $diff = $starts - time();
    $cls['time_until'] = $diff <= 0 ? 'starting now'
        : ($diff < 3600 ? 'in '.floor($diff/60).' minutes'
        : 'in '.floor($diff/3600).' hour'.(floor($diff/3600)>1?'s':''));
    $end = $starts + ($cls['duration_min'] * 60);
    $cls['time_display'] = date('g:i A',$starts).' - '.date('g:i A',$end);
    $ss = $db->prepare("SELECT student_id,label FROM students WHERE class_id=? AND status='active' LIMIT 8");
    $ss->execute([$cls['class_id']]);
    $cls['students'] = $ss->fetchAll();
    echo json_encode(['class' => $cls]); exit;
}

if ($scope === 'heatmap') {
    $fs = $db->prepare("SELECT ROUND(AVG(a.diff_score)) AS avg_diff FROM assignments a JOIN classes c ON c.class_id=a.class_id WHERE c.teacher_id=? AND a.submitted_at >= DATE_SUB(NOW(),INTERVAL 7 DAY)");
    $fs->execute([$tid]); $focus = $fs->fetch();
    $ps = $db->prepare("SELECT COUNT(*) AS cnt FROM assignments WHERE teacher_id=? AND review_status='pending'");
    $ps->execute([$tid]); $pending = (int)$ps->fetch()['cnt'];
    $qs = $db->prepare("SELECT SUM(CASE WHEN s.status='active' THEN 1 ELSE 0 END) AS active_count, COUNT(*) AS total_count FROM students s JOIN classes c ON c.class_id=s.class_id WHERE c.teacher_id=?");
    $qs->execute([$tid]); $pulse = $qs->fetch();
    $avg = (int)($focus['avg_diff'] ?? 0);
    $onTrack = $pulse['total_count'] > 0 ? round(($pulse['active_count']/$pulse['total_count'])*4) : 0;
    echo json_encode([
        'focus'    => ['value'=>(100-$avg).'%',        'state'=>$avg<20?'good':($avg<35?'warn':'bad')],
        'speed'    => ['value'=>$pending.' pending',    'state'=>$pending<=2?'good':($pending<=5?'warn':'bad')],
        'friction' => ['value'=>$avg.'% avg dev',       'state'=>$avg<15?'good':($avg<30?'warn':'bad')],
        'pulse'    => ['value'=>$onTrack.' of 4 on track','state'=>$onTrack>=3?'good':($onTrack>=2?'warn':'bad')],
    ]); exit;
}

http_response_code(400); echo json_encode(['error'=>'Unknown scope. Use: today|next|heatmap']);
