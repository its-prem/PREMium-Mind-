<?php
/**
 * Upload to Hostinger premind/ as: secure_pdf.php (NEW)
 * One-request PDF stream — faster than token + second fetch.
 * Still locked to allowed Origins + uploads/pdfs/ path only.
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
    exit('Origin not allowed');
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('POST only');
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
    return strpos($referer, 'netlify.app') !== false;
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

if (!is_allowed_site_request()) {
    http_response_code(403);
    exit('Direct access blocked');
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) $data = $_POST;

$file  = normalize_pdf_path((string)($data['file'] ?? ''));
$email = strtolower(trim((string)($data['email'] ?? '')));

if ($file === '' || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    exit('Invalid request');
}

if (strpos($file, 'uploads/pdfs/') !== 0 || strpos($file, '..') !== false) {
    http_response_code(400);
    exit('Invalid file path');
}

$fullPath = __DIR__ . '/' . $file;
if (!is_file($fullPath) || !is_readable($fullPath)) {
    if (!is_file($file) || !is_readable($file)) {
        http_response_code(404);
        exit('Document not found');
    }
    $fullPath = $file;
}

$size = filesize($fullPath);
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="PREMium_Secure_Doc.pdf"');
header('Content-Length: ' . $size);
header('X-Robots-Tag: noindex, nofollow');
header('Accept-Ranges: none');

// Stream in chunks — faster TTFB / less memory
$fp = fopen($fullPath, 'rb');
if ($fp === false) {
    http_response_code(500);
    exit('Read error');
}
while (!feof($fp)) {
    echo fread($fp, 8192);
    flush();
}
fclose($fp);
exit;
