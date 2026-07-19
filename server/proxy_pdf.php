<?php
/**
 * Upload to Hostinger root as: proxy_pdf.php (REPLACE old file)
 * Serves PDF only with a short-lived, one-time HMAC token from get_pdf_token.php
 */

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
header('X-Content-Type-Options: nosniff');

$allowed_origins = [
    'https://premind.diplomawallah.in',
    'https://diplomawallah.in',
    'https://www.diplomawallah.in',
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin && in_array($origin, $allowed_origins, true)) {
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

// Block old permanent-token GET URLs (console/Network copy-paste)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Error: Method not allowed');
}

$SECRET = 'PREM_MIND_SECURE_2026';

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
    if (strpos($referer, 'netlify.app') !== false) {
        return true;
    }
    return false;
}

function b64url_decode(string $data): string|false {
    $remainder = strlen($data) % 4;
    if ($remainder) {
        $data .= str_repeat('=', 4 - $remainder);
    }
    return base64_decode(strtr($data, '-_', '+/'), true);
}

function mark_token_used(string $nonce): bool {
    $dir = sys_get_temp_dir() . '/premind_pdf_tokens';
    if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
        return false;
    }
    $path = $dir . '/' . hash('sha256', $nonce);
    if (file_exists($path)) {
        return false; // already used
    }
    // best-effort cleanup of very old nonce files
    foreach (glob($dir . '/*') ?: [] as $old) {
        if (is_file($old) && (time() - filemtime($old)) > 300) {
            @unlink($old);
        }
    }
    return file_put_contents($path, (string)time(), LOCK_EX) !== false;
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

$file  = trim((string)($data['file'] ?? ''));
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

if (($payload['f'] ?? '') !== $file || strtolower((string)($payload['e'] ?? '')) !== $email) {
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

if (!file_exists($file) || !is_readable($file)) {
    http_response_code(404);
    exit('Error: Document not found');
}

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="PREMium_Secure_Doc.pdf"');
header('Content-Length: ' . filesize($file));
header('X-Robots-Tag: noindex, nofollow');
header('Accept-Ranges: none');

readfile($file);
exit;
