<?php
/**
 * Public PDF preview for courses with show_preview = 1.
 * Upload to: public_html/premind/preview_pdf.php
 * URL: https://premind.diplomawallah.in/preview_pdf.php?course_id=123
 *
 * Prefer first-page PNG via Imagick when available (true 1-page).
 * Otherwise stream PDF for client-side page-1 render (PDF.js).
 */
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');

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
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Vary: Origin');
    header('Access-Control-Allow-Methods: GET, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
} elseif ($origin) {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => 'error', 'message' => 'Origin not allowed']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => 'error', 'message' => 'GET only']);
    exit;
}

$courseId = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;
if ($courseId <= 0) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => 'error', 'message' => 'Missing course_id']);
    exit;
}

require_once __DIR__ . '/db_connect.php';
$conn->set_charset('utf8mb4');

// Ensure column exists (safe if admin_panel not hit yet)
$colCheck = @$conn->query("SHOW COLUMNS FROM courses LIKE 'show_preview'");
if ($colCheck && $colCheck->num_rows === 0) {
    @$conn->query("ALTER TABLE courses ADD COLUMN show_preview TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = show 1-page PDF preview on store card'");
}

$stmt = $conn->prepare("SELECT id, title, pdf_file, show_preview, is_deleted FROM courses WHERE id = ? LIMIT 1");
if (!$stmt) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => 'error', 'message' => 'DB error']);
    exit;
}
$stmt->bind_param('i', $courseId);
$stmt->execute();
$res = $stmt->get_result();
$row = $res ? $res->fetch_assoc() : null;
$stmt->close();

if (!$row) {
    http_response_code(404);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => 'error', 'message' => 'Course not found']);
    exit;
}

if (!empty($row['is_deleted']) && (int)$row['is_deleted'] === 1) {
    http_response_code(404);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => 'error', 'message' => 'Course unavailable']);
    exit;
}

if (empty($row['show_preview']) || (int)$row['show_preview'] !== 1) {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => 'error', 'message' => 'Preview is disabled for this course']);
    exit;
}

$pdfRel = trim((string)($row['pdf_file'] ?? ''));
if ($pdfRel === '') {
    http_response_code(404);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => 'error', 'message' => 'No PDF uploaded']);
    exit;
}

$pdfRel = str_replace('\\', '/', $pdfRel);
if (preg_match('#^https?://#i', $pdfRel)) {
    $path = parse_url($pdfRel, PHP_URL_PATH) ?: '';
    $pdfRel = ltrim($path, '/');
    // strip leading premind/ if present in URL path
    if (stripos($pdfRel, 'premind/') === 0) {
        $pdfRel = substr($pdfRel, strlen('premind/'));
    }
}
$pdfRel = ltrim($pdfRel, '/');
if (strpos($pdfRel, '..') !== false) {
    http_response_code(400);
    exit;
}

$abs = __DIR__ . '/' . $pdfRel;
if (!is_file($abs) || !is_readable($abs)) {
    http_response_code(404);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => 'error', 'message' => 'PDF file missing on server']);
    exit;
}

$wantImage = isset($_GET['as']) && strtolower((string)$_GET['as']) === 'image';

// True 1-page preview when Imagick is available
if ($wantImage && extension_loaded('imagick')) {
    try {
        $im = new Imagick();
        $im->setResolution(140, 140);
        $im->readImage($abs . '[0]');
        $im->setImageFormat('png');
        $im->setImageBackgroundColor('white');
        $im = $im->mergeImageLayers(Imagick::LAYERMETHOD_FLATTEN);
        header('Content-Type: image/png');
        header('Content-Disposition: inline; filename="preview-page1.png"');
        echo $im->getImageBlob();
        $im->clear();
        $im->destroy();
        exit;
    } catch (Throwable $e) {
        // fall through to PDF stream
    }
}

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="preview.pdf"');
header('Content-Length: ' . (string)filesize($abs));
readfile($abs);
exit;
