<?php
/**
 * Shared admin-session check for pages/APIs that live next to admin_panel.php.
 * Upload to Hostinger premind/ as: pm_admin_auth.php
 *
 * Login itself still happens on admin_panel.php (Google → Firebase ID token →
 * PHP session). This file just reads that same session so other tools on the
 * same origin (e.g. d2d_notes.php) can trust it without re-implementing login.
 *
 * Keep $PM_AUTH_ADMIN_EMAILS in sync with $PM_ADMIN_EMAILS in admin_panel.php.
 */

$PM_AUTH_ADMIN_EMAILS = ['premku0237@gmail.com', 'ar0319515@gmail.com'];

if (session_status() !== PHP_SESSION_ACTIVE) {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function pm_auth_is_admin_email(string $email): bool {
    global $PM_AUTH_ADMIN_EMAILS;
    $email = strtolower(trim($email));
    foreach ($PM_AUTH_ADMIN_EMAILS as $a) {
        if ($email === strtolower(trim($a))) return true;
    }
    return false;
}

function pm_auth_admin_email(): string {
    $e = isset($_SESSION['pm_admin_email']) ? (string)$_SESSION['pm_admin_email'] : '';
    return ($e !== '' && pm_auth_is_admin_email($e)) ? $e : '';
}

function pm_auth_admin_ok(): bool {
    return pm_auth_admin_email() !== '';
}

/** JSON 401 + exit if there is no admin session. */
function pm_auth_require_admin_json(): void {
    if (pm_auth_admin_ok()) return;
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => 'error', 'msg' => 'Login required. Open admin_panel.php and sign in first.', 'code' => 'unauthenticated']);
    exit;
}
