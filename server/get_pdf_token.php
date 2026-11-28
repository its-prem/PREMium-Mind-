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
    header('Access-Control-Allow-Headers: Content-Type, X-PM-App-Ts, X-PM-App-Sign');
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
    if (preg_match('~^https://[a-z0-9-]+\.netlify\.app(?:[/?#]|$)~i', $referer)) return true;
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

require_once __DIR__ . '/pm_firebase_verify.php';
require_once __DIR__ . '/pm_pdf_access.php';

$file  = normalize_pdf_path((string)($data['file'] ?? ''));
// Email proven by a Firebase token when sent; otherwise the body email
// (old behaviour) until PM_PDF_REQUIRE_IDENTITY is turned on.
$email = pm_pdf_resolve_email($data, pm_is_native_app_request());
$purpose = trim((string)($data['purpose'] ?? 'view'));

if ($file === '' || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    $msg = 'Invalid request';
    if ($email === '' && !empty($GLOBALS['pm_pdf_identity_reason'])) {
        $msg = 'Login required to open this PDF (' . $GLOBALS['pm_pdf_identity_reason'] . ')';
    }
    echo json_encode(['status' => 'error', 'message' => $msg]);
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
require_once __DIR__ . '/pm_rate_limit.php';
if (!pm_rate_limit($conn, 'pdftoken:' . $email, 150, 600) || !pm_rate_limit($conn, 'pdftoken-ip:' . pm_client_ip(), 400, 600)) {
    http_response_code(429);
    echo json_encode(['status' => 'error', 'message' => 'Too many requests. Please wait a few minutes.']);
    exit;
}
require_once __DIR__ . '/pm_user_guard.php';
pm_user_check($conn, $email);

// Friendlier message for the common case — only shown when EVERY course
// tied to this file requires the app (if even one matching course doesn't,
// pm_user_can_access_pdf() below will correctly resolve per-course, so
// this is just clearer wording, not the actual security check).
$coursesForFile = pm_find_courses_for_pdf($conn, $file);
if (!empty($coursesForFile) && !pm_pdf_admin_bypass($email) && !pm_is_native_app_request()) {
    $allAppOnly = true;
    foreach ($coursesForFile as $c) {
        if (empty($c['app_only'])) { $allAppOnly = false; break; }
    }
    if ($allAppOnly) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'This content is only available in the PREMium Mind app. Please open it from the app.']);
        exit;
    }
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

// proxy_pdf.php hands back the complete original file no matter which
// purpose the token was issued for — 'view' is not actually safer than
// 'download' there. So for a locked course, 'view' has to be refused
// here too; only 'page_view' (watermarked, per-page images via
// secure_page_image.php) is allowed to view it. Without this, the
// watermarking added above was opt-in from the client's side only —
// anyone calling this API directly with purpose=view could still pull
// the clean, un-watermarked file.
if (($purpose === 'download' || $purpose === 'view') && !$canDownload) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Download is not allowed for this course. Use the secure viewer instead.']);
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

/**
 * Number of pages in a PDF, as reliably as we can get it.
 * Imagick's pingImage() reports only 1 page for many multi-page PDFs on shared hosting,
 * which made the reader show a single page. So we take the biggest of three answers:
 *   1) Imagick ping   2) the page objects / /Count written inside the PDF   3) probing pages one by one
 */
function pm_pdf_page_count(string $path): ?int {
    if (!is_file($path)) return null;
    $best = 0;

    if (extension_loaded('imagick')) {
        try {
            $im = new Imagick();
            $im->pingImage($path);
            $best = max($best, (int)$im->getNumberImages());
            $im->clear();
            $im->destroy();
        } catch (Throwable $e) { /* try the next way */ }
    }

    $size = (int)@filesize($path);
    if ($size > 0 && $size <= 150 * 1024 * 1024) {
        $raw = @file_get_contents($path);
        if (is_string($raw) && $raw !== '') {
            $n = preg_match_all('~/Type\s*/Page(?![A-Za-z])~', $raw);
            if ($n) $best = max($best, (int)$n);
            if (preg_match_all('~/Type\s*/Pages\b[^>]*?/Count\s+(\d+)~s', $raw, $m)) {
                $best = max($best, (int)max($m[1]));
            }
        }
    }

    // Still 1? Check whether page 2 really exists by trying to open it.
    if ($best <= 1 && extension_loaded('imagick')) {
        $exists = function (int $index) use ($path): bool {
            try {
                $t = new Imagick();
                $t->setResolution(10, 10);
                $t->readImage($path . '[' . $index . ']');
                $t->clear();
                $t->destroy();
                return true;
            } catch (Throwable $e) {
                return false;
            }
        };
        if ($exists(1)) {
            $lo = 1;                       // page index $lo exists
            $hi = 2;
            while ($hi < 2000 && $exists($hi)) { $lo = $hi; $hi *= 2; }
            while ($hi - $lo > 1) {        // binary search the last existing page
                $mid = intdiv($lo + $hi, 2);
                if ($exists($mid)) $lo = $mid; else $hi = $mid;
            }
            $best = $lo + 1;
        }
    }

    return $best > 0 ? $best : null;
}

$pageCount = null;
if ($purpose === 'page_view') {
    $pageCount = pm_pdf_page_count(__DIR__ . '/' . $file);
}

echo json_encode([
    'status'         => 'success',
    'token'          => $token,
    'file'           => $file,
    'expires'        => $exp,
    'allow_download' => $canDownload,
    'pages'          => $pageCount,
]);
