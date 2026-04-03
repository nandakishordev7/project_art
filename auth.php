<?php
// auth.php — Include at the TOP of every .php page. One line: require_once 'auth.php';
// Do NOT include it twice on the same page.

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';

session_name(SESSION_NAME);
session_set_cookie_params([
    'lifetime' => SESSION_LIFETIME,
    'path'     => '/',
    'secure'   => false,
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

// DEMO MODE: auto-login as teacher_id=1
// Remove this block and add a real login page later
if (!isset($_SESSION['teacher_id'])) {
    $_SESSION['teacher_id'] = 1;
}

// Load teacher from DB
$teacher = null;
try {
    $stmt = DB::conn()->prepare(
        'SELECT teacher_id, name, specialty, email, initials, moodle_user_id, moodle_token
         FROM teachers WHERE teacher_id = ?'
    );
    $stmt->execute([$_SESSION['teacher_id']]);
    $teacher = $stmt->fetch();
} catch (PDOException $e) {
    $teacher = null;
}

if (!$teacher) {
    session_destroy();
    die('<div style="font-family:sans-serif;padding:2rem;max-width:600px">'
      . '<h2 style="color:#c0392b">Setup required</h2>'
      . '<p>No teacher record found in the database.</p>'
      . '<p>Run this command in your terminal:</p>'
      . '<pre style="background:#f4f4f4;padding:12px;border-radius:6px">'
      . 'mysql -u root -p &lt; schema.sql</pre>'
      . '<p style="color:#666">Then refresh this page.</p></div>');
}

// Writes window.__KB into the HTML so shared.js reads live teacher data.
// Call: echo kb_inject_script($teacher); just before </head>
function kb_inject_script($teacher) {
    $data = json_encode([
        'teacher' => [
            'id'        => (int)$teacher['teacher_id'],
            'name'      => $teacher['name'],
            'specialty' => $teacher['specialty'],
            'initials'  => $teacher['initials'],
            'email'     => $teacher['email'],
        ]
    ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    return '<script>window.__KB=' . $data . ';</script>';
}
