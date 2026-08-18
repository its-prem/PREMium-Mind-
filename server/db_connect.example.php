<?php
/**
 * Shared session + MySQL connection, credentials from .env (see .env.example).
 * Upload to Hostinger premind/ as: db_connect.php
 */

require_once __DIR__ . '/pm_load_env.php';
pm_load_dotenv(__DIR__ . '/.env');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'lifetime' => 86400, // 1 din
        'path' => '/',
        'secure' => true,     // HTTPS-only — matches the site, which is HTTPS everywhere
        'httponly' => true,   // JS ko block karega (Security)
        'samesite' => 'Strict'
    ]);
    session_start();
}

$DB_HOST = pm_env('DB_HOST', 'localhost');
$DB_NAME = pm_env('DB_NAME');
$DB_USER = pm_env('DB_USER');
$DB_PASS = pm_env('DB_PASSWORD');

if ($DB_NAME === '' || $DB_NAME === 'CHANGE_ME' || $DB_USER === '' || $DB_USER === 'CHANGE_ME') {
    http_response_code(500);
    error_log('db_connect.php: DB_NAME/DB_USER missing or still CHANGE_ME — set them in .env');
    die('Database not configured.');
}

// mysqli throws an uncaught mysqli_sql_exception on connect failure by
// default since PHP 8.1 (MYSQLI_REPORT_ERROR|STRICT is the default report
// mode) — so a bad password/host doesn't just set ->connect_error, it
// fatals the whole request with a blank page before that check ever runs.
// Wrap it so a credential typo/rotation mistake fails cleanly instead of
// crashing every script that includes this file.
mysqli_report(MYSQLI_REPORT_OFF);
$conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if ($conn->connect_error) {
    http_response_code(500);
    error_log('DB connect failed: ' . $conn->connect_error);
    die('Database connection failed.');
}
