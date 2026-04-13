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
define('MOODLE_TOKEN',    'cb194a2de5577673d1dc91d3fe451b46');
define('MOODLE_REST_URL', 'http://localhost/moodle/webservice/rest/server.php');
// Session
define('SESSION_NAME',     'kb_session');
define('SESSION_LIFETIME', 7200);

// CORS
define('CORS_ORIGIN', '*');

// mail 

define('MAIL_HOST', 'smtp.gmail.com');
define('MAIL_PORT', 587);
define('MAIL_USERNAME', 'nandakishors.cs25@duk.ac.in');
define('MAIL_PASSWORD', 'stnh yqzg vkcv dsjm');
define('MAIL_FROM', 'nandakishors.cs25@duk.ac.in');
define('MAIL_FROM_NAME','kb_bridge');
define('OTP_EXPIRY_MIN', 10);

// admin pass 

define('ADMIN_PASSWORD', 'admin123');