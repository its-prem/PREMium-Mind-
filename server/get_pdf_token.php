<?php
/**
 * Upload to Hostinger root as: get_pdf_token.php
 * Issues short-lived (60s), one-time PDF access tokens.
 * Secret NEVER goes to the browser.
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

$allowed_origins = [
    'https://premind.diplomawallah.in',
    'https://diplomawallah.in',
    'https://www.diplomawallah.in',
    'https://premind.netlify.app',
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin && in_array($origin, $allowed_origins, true)) {
    header("Access-Control-Allow-Origin: $origin");
    header('Vary: Origin');
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
} elseif ($origin) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Origin not allowed']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'POST only']);
    exit;
}

// Same shared secret used by proxy_pdf.php (HMAC only — never accept as plain GET token)
$SECRET = 'PREM_MIND_SECURE_2026';
$TOKEN_TTL = 60; // seconds

function is_allowed_site_request(): bool {
    global $allowed_origins;
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($origin && in_array($origin, $allowed_origins, true)) {
        return true;
    }
    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    if ($referer === '') {
        return false;
    }
    foreach ($allowed_origins as $allowed) {
        if (strpos($referer, $allowed) === 0) {
            return true;
        }
    }
    // legacy netlify frontends if still used
    if (strpos($referer, 'netlify.app') !== false) {
        return true;
    }
    return false;
}

if (!is_allowed_site_request()) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Direct access blocked']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
    $data = $_POST;
}

$file  = trim((string)($data['file'] ?? ''));
$email = strtolower(trim((string)($data['email'] ?? '')));
$purpose = trim((string)($data['purpose'] ?? 'view')); // view | download

if ($file === '' || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
    exit;
}

// Path must stay inside uploads/pdfs/
if (strpos($file, 'uploads/pdfs/') !== 0 || strpos($file, '..') !== false) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid file path']);
    exit;
}

if (!in_array($purpose, ['view', 'download'], true)) {
    $purpose = 'view';
}

$exp   = time() + $TOKEN_TTL;
$nonce = bin2hex(random_bytes(16));
$payload = [
    'f' => $file,
    'e' => $email,
    'x' => $exp,
    'n' => $nonce,
    'p' => $purpose,
];

$payload_json = json_encode($payload, JSON_UNESCAPED_SLASHES);
$payload_b64  = rtrim(strtr(base64_encode($payload_json), '+/', '-_'), '=');
$sig          = hash_hmac('sha256', $payload_b64, $SECRET);
$token        = $payload_b64 . '.' . $sig;

echo json_encode([
    'status'  => 'success',
    'token'   => $token,
    'expires' => $exp,
]);
