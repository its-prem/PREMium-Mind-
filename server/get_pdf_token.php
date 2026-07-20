<?php
/**
 * Upload to Hostinger premind/ as: get_pdf_token.php
 * Issues short-lived (90s) HMAC tokens. Secret stays on server only.
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

function origin_allowed(string $origin): bool {
    global $allowed_origins;
    if ($origin === '') return false;
    if (in_array($origin, $allowed_origins, true)) return true;
    // Allow any Netlify deploy preview / branch URL
    if (preg_match('#^https://[a-z0-9-]+\\.netlify\\.app$#i', $origin)) return true;
    return false;
}

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin && origin_allowed($origin)) {
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

$SECRET = 'PREM_MIND_SECURE_2026';
$TOKEN_TTL = 90;

function is_allowed_site_request(): bool {
    global $allowed_origins;
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if (origin_allowed($origin)) return true;

    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    if ($referer === '') return false;

    foreach ($allowed_origins as $allowed) {
        if (strpos($referer, $allowed) === 0) return true;
    }
    if (strpos($referer, 'netlify.app') !== false) return true;
    return false;
}

function normalize_pdf_path(string $file): string {
    $file = trim(rawurldecode($file));
    $file = str_replace('\\', '/', $file);

    // Full proxy URL? extract file=
    if (stripos($file, 'proxy_pdf.php') !== false && preg_match('/[?&]file=([^&]+)/i', $file, $m)) {
        $file = rawurldecode($m[1]);
        $file = str_replace('\\', '/', $file);
    }

    // Absolute URL to pdf on our host → keep path only
    if (preg_match('#^https?://#i', $file)) {
        $path = parse_url($file, PHP_URL_PATH) ?: '';
        $file = ltrim($path, '/');
    }

    $file = ltrim($file, '/');

    // If DB stored only filename, assume uploads/pdfs/
    if ($file !== '' && strpos($file, '/') === false && preg_match('/\.pdf$/i', $file)) {
        $file = 'uploads/pdfs/' . $file;
    }

    // Collapse accidental double prefixes
    if (preg_match('#uploads/pdfs/uploads/pdfs/#i', $file)) {
        $file = preg_replace('#(?:uploads/pdfs/)+#i', 'uploads/pdfs/', $file, 1);
    }

    return $file;
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

$file  = normalize_pdf_path((string)($data['file'] ?? ''));
$email = strtolower(trim((string)($data['email'] ?? '')));
$purpose = trim((string)($data['purpose'] ?? 'view'));

if ($file === '' || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
    exit;
}

if (strpos($file, 'uploads/pdfs/') !== 0 || strpos($file, '..') !== false) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid file path', 'file' => $file]);
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
    'file'    => $file,
    'expires' => $exp,
]);
