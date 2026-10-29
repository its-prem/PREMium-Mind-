<?php
/**
 * Upload to Hostinger premind/ as: get_pdf_session.php
 *
 * Google-login path: the website sends the Firebase ID token here once, right
 * after sign-in. We verify it with Google and return a signed PDF session
 * token (same kind password/OTP logins get). The website stores it and sends
 * it with every PDF request — so no live Firebase session is needed later.
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$allowed_origins = [
    'https://premind.diplomawallah.in',
    'https://diplomawallah.in',
    'https://www.diplomawallah.in',
    'https://premind.netlify.app',
    'https://localhost', 'http://localhost', 'capacitor://localhost', 'ionic://localhost',
];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$originOk = ($origin !== '' && (in_array($origin, $allowed_origins, true)
    || preg_match('~^https://[a-z0-9-]+\.netlify\.app$~i', $origin)));
if ($originOk) {
    header("Access-Control-Allow-Origin: $origin");
    header('Vary: Origin');
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
} elseif ($origin !== '') {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Origin not allowed']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'POST only']);
    exit;
}

require_once __DIR__ . '/pm_firebase_verify.php';
require_once __DIR__ . '/pm_session_token.php';

$data = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($data)) $data = $_POST;

$idToken = (string)($data['id_token'] ?? $data['idToken'] ?? '');
$email   = $idToken !== '' ? pm_firebase_email_from_token($idToken) : '';

if ($email === '') {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Could not verify login token', 'reason' => $GLOBALS['pm_fb_last_reason'] ?? '']);
    exit;
}

$token = pm_session_make($email);
if ($token === '') {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Session secret not configured']);
    exit;
}

echo json_encode(['status' => 'success', 'pdf_session' => $token, 'email' => $email]);
