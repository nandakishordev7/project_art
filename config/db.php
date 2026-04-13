<?php
// config/db.php  — Do not edit. Provides DB::conn()
require_once __DIR__ . '/config.php';

class DB {
    private static $instance = null;

    public static function conn() {
        if (self::$instance === null) {
            $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
            try {
                self::$instance = new PDO($dsn, DB_USER, DB_PASS, [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]);
            } catch (PDOException $e) {
                http_response_code(503);
                die('<p style="font-family:sans-serif;padding:2rem;color:#c0392b">'
                  . '<strong>Database connection failed.</strong><br>'
                  . 'Edit <code>config/config.php</code> with correct credentials.<br><br>'
                  . 'Error: ' . htmlspecialchars($e->getMessage()) . '</p>');
            }
        }
        return self::$instance;
    }
}
