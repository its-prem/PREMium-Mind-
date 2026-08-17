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
    'https://localhost',
    'http://localhost',
    'capacitor://localhost',
    'ionic://localhost',
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

require_once __DIR__ . '/pm_load_secrets.php';
$SECRET = pm_pdf_hmac_secret();
if ($SECRET === '') {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'PDF secrets not configured']);
    exit;
}
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

require_once __DIR__ . '/pm_pdf_access.php';
require_once __DIR__ . '/db_connect.php';
$conn->set_charset('utf8mb4');

$courseForFile = pm_find_course_for_pdf($conn, $file);
if ($courseForFile !== null && !empty($courseForFile['app_only'])
    && !pm_pdf_admin_bypass($email) && !pm_is_native_app_request()) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'This content is only available in the PREMium Mind app. Please open it from the app.']);
    exit;
}

if (!pm_user_can_access_pdf($conn, $email, $file)) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'You are not enrolled in this course']);
    exit;
}

if (!in_array($purpose, ['view', 'download', 'page_view'], true)) {
    $purpose = 'view';
}

// Authoritative download permission — never trust a client-supplied flag
// (e.g. a `download` URL parameter) for this. Only the course's own
// allow_download setting decides it.
$canDownload = pm_user_can_download_pdf($conn, $email, $file);
if ($purpose === 'download' && !$canDownload) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Download is not allowed for this course']);
    exit;
}

// page_view tokens back a whole viewing session (many page-image
// fetches via secure_page_image.php), not a single one-shot file
// request, so they get a longer TTL and are not single-use.
$ttl = $purpose === 'page_view' ? 300 : $TOKEN_TTL;
$exp   = time() + $ttl;
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

$pageCount = null;
if ($purpose === 'page_view' && extension_loaded('imagick')) {
    try {
        $im = new Imagick();
        $im->pingImage(__DIR__ . '/' . $file);
        $pageCount = $im->getNumberImages();
        $im->clear();
        $im->destroy();
    } catch (Throwable $e) {
        $pageCount = null;
    }
}

echo json_encode([
    'status'         => 'success',
    'token'          => $token,
    'file'           => $file,
    'expires'        => $exp,
    'allow_download' => $canDownload,
    'pages'          => $pageCount,
]);
