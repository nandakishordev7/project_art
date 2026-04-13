<?php
// auth.php — require_once this ONCE at the top of every protected page.

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

// If not logged in, redirect to login page
if (!isset($_SESSION['teacher_id'])) {
    header('Location: login.php');
    exit;
}

// Load teacher row from DB
$teacher = null;
try {
    $stmt = DB::conn()->prepare(
        'SELECT * FROM teachers WHERE teacher_id = ?'
    );
    $stmt->execute([$_SESSION['teacher_id']]);
    $teacher = $stmt->fetch();
} catch (PDOException $e) {
    $teacher = null;
}

if (!$teacher) {
    session_destroy();
    header('Location: login.php?error=session_expired');
    exit;
}

// Writes window.__KB so shared.js picks up live teacher data
function kb_inject_script($teacher) {
    $initials = '';
    $parts = explode(' ', trim($teacher['name'] ?? ''));
    foreach ($parts as $p) { if ($p) $initials .= strtoupper($p[0]); }
    $initials = substr($initials, 0, 2) ?: 'T';

    $data = json_encode([
        'teacher' => [
            'id'        => (int)$teacher['teacher_id'],
            'name'      => $teacher['name'],
            'specialty' => $teacher['specialty'] ?? 'Instructor',
            'initials'  => $initials,
            'email'     => $teacher['email'],
            'avatar'    => $teacher['avatar_path'] ?? '',
            'art_form'  => $teacher['art_form']    ?? '',
        ]
    ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    return '<script>window.__KB=' . $data . ';</script>';
}
