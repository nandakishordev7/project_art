<?php
// ============================================================
// config/config.php  — Edit this file with your credentials
// ============================================================

// Database
define('DB_HOST',    'localhost');
define('DB_NAME',    'kathakali_bridge');
define('DB_USER',    'kb_user');
define('DB_PASS',    'moodlepass');
define('DB_CHARSET', 'utf8mb4');

// Moodle — root URL only, no trailing slash
define('MOODLE_URL',      'http://localhost/moodle');
define('MOODLE_TOKEN',    '77bb65bb92d7bccce8c9acac524982db');
define('MOODLE_REST_URL', 'http://localhost/moodle/webservice/rest/server.php');

// Session
define('SESSION_NAME',     'kb_session');
define('SESSION_LIFETIME', 7200);

// CORS
define('CORS_ORIGIN', '*');
