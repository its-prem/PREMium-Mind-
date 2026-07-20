<?php
/**
 * Upload to Hostinger premind/ as: proxy_pdf.php (REPLACE)
 * Serves PDF with short-lived HMAC token from get_pdf_token.php
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

$SECRET = 'PREM_MIND_SECURE_2026';

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

    if (stripos($file, 'proxy_pdf.php') !== false && preg_match('/[?&]file=([^&]+)/i', $file, $m)) {
        $file = rawurldecode($m[1]);
        $file = str_replace('\\', '/', $file);
    }

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

/**
 * One-time nonce store. Prefer local writable folder under this script.
 * If storage is unavailable, allow the request (still protected by HMAC + expiry).
 */
function mark_token_used(string $nonce): bool {
    $dirs = [
        __DIR__ . '/.pdf_tokens',
        sys_get_temp_dir() . '/premind_pdf_tokens',
    ];

    foreach ($dirs as $dir) {
        if (!is_dir($dir)) {
            @mkdir($dir, 0700, true);
        }
        if (!is_dir($dir) || !is_writable($dir)) {
            continue;
        }

        $path = $dir . '/' . hash('sha256', $nonce);
        if (file_exists($path)) {
            return false; // already used
        }

        foreach (glob($dir . '/*') ?: [] as $old) {
            if (is_file($old) && (time() - filemtime($old)) > 300) {
                @unlink($old);
            }
        }

        $ok = @file_put_contents($path, (string)time(), LOCK_EX);
        return $ok !== false;
    }

    // Could not persist nonce — do not hard-fail PDF viewing
    return true;
}

if (!is_allowed_site_request()) {
    http_response_code(403);
    exit('Error: Direct download not allowed');
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
    $data = $_POST;
}

$file  = normalize_pdf_path((string)($data['file'] ?? ''));
$email = strtolower(trim((string)($data['email'] ?? '')));
$token = trim((string)($data['token'] ?? ''));

if ($file === '' || $email === '' || $token === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    exit('Access Denied');
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
$expected = hash_hmac('sha256', $payload_b64, $SECRET);
if (!hash_equals($expected, $sig)) {
    http_response_code(403);
    exit('Error: Unauthorized Access (Invalid Token)');
}

$payload_json = b64url_decode($payload_b64);
$payload = $payload_json ? json_decode($payload_json, true) : null;
if (!is_array($payload)) {
    http_response_code(403);
    exit('Error: Unauthorized Access (Invalid Token)');
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

$nonce = (string)($payload['n'] ?? '');
if ($nonce === '' || !mark_token_used($nonce)) {
    http_response_code(403);
    exit('Error: Token already used');
}

// Resolve file relative to this PHP location (premind/)
$fullPath = __DIR__ . '/' . $file;
if (!is_file($fullPath) || !is_readable($fullPath)) {
    // Fallback: cwd relative (older setups)
    if (!is_file($file) || !is_readable($file)) {
        http_response_code(404);
        exit('Error: Document not found');
    }
    $fullPath = $file;
}

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="PREMium_Secure_Doc.pdf"');
header('Content-Length: ' . filesize($fullPath));
header('X-Robots-Tag: noindex, nofollow');
header('Accept-Ranges: none');

readfile($fullPath);
exit;
