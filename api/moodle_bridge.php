<?php
require_once dirname(__DIR__) . '/auth.php';
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: ' . CORS_ORIGIN);

$db     = DB::conn();
$tid    = (int)$_SESSION['teacher_id'];
$action = $_GET['action'] ?? '';

function moodle_call($function, $params = []) {
    $params['wstoken']            = MOODLE_TOKEN;
    $params['wsfunction']         = $function;
    $params['moodlewsrestformat'] = 'json';
    $ch = curl_init(MOODLE_REST_URL);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($params),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_SSL_VERIFYPEER => false, // set true in production with real SSL
    ]);
    $response = curl_exec($ch);
    $err      = curl_error($ch);
    curl_close($ch);
    if ($err) return ['error' => 'Moodle connection failed: ' . $err];
    $decoded = json_decode($response, true);
    if ($decoded === null)           return ['error' => 'Moodle returned invalid JSON'];
    if (isset($decoded['exception'])) return ['error' => $decoded['message'] ?? 'Moodle exception'];
    return $decoded;
}

if ($action === 'launch') {
    $course_id = (int)($_GET['course_id'] ?? 0);
    if (!$course_id) { http_response_code(400); echo json_encode(['error'=>'course_id required']); exit; }

    $s = $db->prepare('SELECT moodle_user_id FROM teachers WHERE teacher_id=?');
    $s->execute([$tid]);
    $row = $s->fetch();

    // No Moodle user linked yet — send to course page directly
    if (!$row || empty($row['moodle_user_id'])) {
        echo json_encode(['url' => MOODLE_URL.'/course/view.php?id='.$course_id, 'mode'=>'manual_login']);
        exit;
    }

    // SSO via auth_userkey plugin
    $result = moodle_call('auth_userkey_request_login_url', ['user[id]' => (int)$row['moodle_user_id']]);
    if (isset($result['error'])) {
        echo json_encode(['url' => MOODLE_URL.'/course/view.php?id='.$course_id, 'mode'=>'fallback', 'note'=>$result['error']]);
        exit;
    }
    $login_url = $result['loginurl'] ?? ($result[0]['loginurl'] ?? '');
    if ($login_url && $course_id) {
        $login_url .= (str_contains($login_url,'?')?'&':'?').'wantsurl='.urlencode(MOODLE_URL.'/course/view.php?id='.$course_id);
    }
    echo json_encode(['url' => $login_url ?: MOODLE_URL.'/course/view.php?id='.$course_id, 'mode'=>'sso']);
    exit;
}

if ($action === 'enrolled') {
    $course_id = (int)($_GET['course_id'] ?? 0);
    if (!$course_id) { http_response_code(400); echo json_encode(['error'=>'course_id required']); exit; }
    $result = moodle_call('core_enrol_get_enrolled_users', ['courseid'=>$course_id]);
    if (isset($result['error'])) { echo json_encode(['error'=>$result['error'],'students':[]]); exit; }
    $students = array_map(fn($u) => ['moodle_id'=>(int)$u['id'],'fullname'=>$u['fullname']??'','email'=>$u['email']??''], $result);
    echo json_encode(['students'=>$students,'count'=>count($students)]); exit;
}

http_response_code(400);
echo json_encode(['error'=>'Unknown action. Use: launch|enrolled']);
