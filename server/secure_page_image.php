<?php
/**
 * Upload to Hostinger premind/ as: secure_page_image.php
 *
 * Streams ONE page of a PDF as a watermarked PNG image (viewer's email +
 * phone burned into the pixels). Used instead of proxy_pdf.php whenever a
 * course's allow_download is off, so the browser never receives the
 * original, un-watermarked PDF bytes for that course — only what's
 * needed to display the current page on screen.
 *
 * Token comes from get_pdf_token.php with purpose=page_view. Unlike the
 * single-use file token, this one is not consumed on use — it backs a
 * whole viewing session (multiple page fetches) until it expires.
 */

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
header('X-Content-Type-Options: nosniff');

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
    exit('Error: Origin not allowed');
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Error: Method not allowed');
}

require_once __DIR__ . '/pm_load_secrets.php';
$SECRETS = pm_pdf_hmac_secrets();
if (!$SECRETS) {
    http_response_code(500);
    exit('Error: PDF secrets not configured');
}

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

function b64url_decode(string $data) {
    $remainder = strlen($data) % 4;
    if ($remainder) {
        $data .= str_repeat('=', 4 - $remainder);
    }
    return base64_decode(strtr($data, '-_', '+/'), true);
}

function normalize_pdf_path(string $file): string {
    $file = trim(rawurldecode($file));
    $file = str_replace('\\', '/', $file);
    if (preg_match('#^https?://#i', $file)) {
        $path = parse_url($file, PHP_URL_PATH) ?: '';
        $file = ltrim($path, '/');
    }
    $file = ltrim($file, '/');
    if ($file !== '' && strpos($file, '/') === false && preg_match('/\.pdf$/i', $file)) {
        $file = 'uploads/pdfs/' . $file;
    }
    if (preg_match('#uploads/pdfs/uploads/pdfs/#i', $file)) {
        $file = preg_replace('#(?:uploads/pdfs/)+#i', 'uploads/pdfs/', $file, 1);
    }
    return $file;
}

if (!is_allowed_site_request()) {
    http_response_code(403);
    exit('Error: Direct access blocked');
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
    $data = $_POST;
}

$file  = normalize_pdf_path((string)($data['file'] ?? ''));
$email = strtolower(trim((string)($data['email'] ?? '')));
$token = trim((string)($data['token'] ?? ''));
$page  = max(1, (int)($data['page'] ?? 1));

if ($file === '' || $email === '' || $token === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    exit('Error: Invalid request');
}

if (strpos($file, 'uploads/pdfs/') !== 0 || strpos($file, '..') !== false) {
    http_response_code(400);
    exit('Error: Invalid file path');
}

$parts = explode('.', $token, 2);
if (count($parts) !== 2) {
    http_response_code(403);
    exit('Error: Unauthorized Access (Invalid Token)');
}

[$payload_b64, $sig] = $parts;
$sigOk = false;
foreach ($SECRETS as $trySecret) {
    if (hash_equals(hash_hmac('sha256', $payload_b64, $trySecret), $sig)) {
        $sigOk = true;
        break;
    }
}
if (!$sigOk) {
    http_response_code(403);
    exit('Error: Unauthorized Access (Invalid Token)');
}

$payload_json = b64url_decode($payload_b64);
$payload = $payload_json ? json_decode($payload_json, true) : null;
if (!is_array($payload)) {
    http_response_code(403);
    exit('Error: Unauthorized Access (Invalid Token)');
}

if (($payload['p'] ?? '') !== 'page_view') {
    http_response_code(403);
    exit('Error: Wrong token type');
}

$tokenFile = normalize_pdf_path((string)($payload['f'] ?? ''));
if ($tokenFile !== $file || strtolower((string)($payload['e'] ?? '')) !== $email) {
    http_response_code(403);
    exit('Error: Unauthorized Access (Token mismatch)');
}

if (!isset($payload['x']) || time() > (int)$payload['x']) {
    http_response_code(403);
    exit('Error: Token expired');
}

require_once __DIR__ . '/pm_pdf_access.php';
require_once __DIR__ . '/db_connect.php';
$conn->set_charset('utf8mb4');
if (!pm_user_can_access_pdf($conn, $email, $file)) {
    http_response_code(403);
    exit('Error: You are not enrolled in this course');
}

$fullPath = __DIR__ . '/' . $file;
if (!is_file($fullPath) || !is_readable($fullPath)) {
    http_response_code(404);
    exit('Error: Document not found');
}

if (!extension_loaded('imagick')) {
    http_response_code(500);
    exit('Error: Image renderer unavailable');
}

try {
    $im = new Imagick();
    // A bit higher than before so pinch-zoom (now up to 6x) stays readable
    // instead of turning to mush past ~2x on the old 150dpi render.
    $im->setResolution(190, 190);
    $im->readImage($fullPath . '[' . ($page - 1) . ']');
    $im->setImageFormat('png');
    $im->setImageBackgroundColor(new ImagickPixel('white'));
    $im = $im->mergeImageLayers(Imagick::LAYERMETHOD_FLATTEN);

    $w = $im->getImageWidth();
    $h = $im->getImageHeight();

    $phone = '';
    $stmt = $conn->prepare("SELECT phone FROM users WHERE email = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $r = $stmt->get_result()->fetch_assoc();
        if ($r && !empty($r['phone'])) $phone = (string)$r['phone'];
        $stmt->close();
    }
    $label = $email . ($phone !== '' ? ' | ' . $phone : '');

    $draw = new ImagickDraw();
    $draw->setFillColor(new ImagickPixel('rgba(100,100,100,0.14)'));
    // Don't force a specific font family — "Helvetica" isn't registered on
    // most Linux/ImageMagick installs and throws. Imagick's built-in
    // default font is always available and is fine for a watermark.
    $fontSize = max(10, (int)($w / 42));
    $draw->setFontSize($fontSize);
    $draw->setTextAlignment(Imagick::ALIGN_CENTER);

    // Tiled, semi-transparent, diagonally rotated, with a per-request
    // random offset — a fixed-region crop can't reliably remove it, and
    // it doesn't repeat identically between requests/pages.
    $angle = -30;
    $stepX = max(260, $fontSize * 14);
    $stepY = max(170, $fontSize * 9);
    $offsetX = random_int(0, (int)$stepX);
    $offsetY = random_int(0, (int)$stepY);

    for ($y = -$stepY + $offsetY; $y < $h + $stepY; $y += $stepY) {
        for ($x = -$stepX + $offsetX; $x < $w + $stepX; $x += $stepX) {
            $im->annotateImage($draw, $x, $y, $angle, $label);
        }
    }

    $blob = $im->getImageBlob();
    $im->clear();
    $im->destroy();

    header('Content-Type: image/png');
    header('Content-Length: ' . strlen($blob));
    header('X-Robots-Tag: noindex, nofollow');
    echo $blob;
} catch (Throwable $e) {
    http_response_code(500);
    exit('Error: Render error');
}
