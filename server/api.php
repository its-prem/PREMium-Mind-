<?php
/**
 * Hostinger: public_html/premind/api.php
 *
 * Store catalog + this user's enrolment. The catalog stays public (people must
 * be able to browse the store), but two leaks the old version had are closed:
 *
 *   1. Premium file paths (pdf_file) are returned ONLY for a course the caller
 *      actually owns, and only when ownership is proven by the signed
 *      pm_pdf_session token. Nobody can scrape "uploads/pdfs/..." paths for
 *      content they didn't buy.
 *   2. Enrolment is tied to the token's verified email. The ?email= param is
 *      still accepted for showing a logged-in user's own owned-state (so the
 *      store/My-Courses keep working), but it can NEVER reveal a file path.
 */
error_reporting(0);
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, X-PM-Session");
header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-store");
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

include 'db_connect.php';
require_once __DIR__ . '/pm_session_token.php';

// --- verified identity (signed token) ---
$token = '';
if (!empty($_SERVER['HTTP_X_PM_SESSION'])) $token = (string)$_SERVER['HTTP_X_PM_SESSION'];
if ($token === '' && isset($_GET['pdf_session']))  $token = (string)$_GET['pdf_session'];
if ($token === '' && isset($_POST['pdf_session'])) $token = (string)$_POST['pdf_session'];
$verified_email = ($token !== '') ? pm_session_email($token) : '';

// For owned-state display we may use the token email, or fall back to the raw
// ?email= (so a logged-in user without a fresh token still sees their courses).
// This value is used ONLY for is_enrolled/req_status — never to expose paths.
$display_email = $verified_email !== ''
    ? $verified_email
    : strtolower(trim((string)($_GET['email'] ?? '')));

$courses = array();

if ($display_email !== '') {
    $e = $conn->real_escape_string($display_email);
    $sql = "SELECT c.*,
            IF(uc.course_id IS NOT NULL, 1, 0) as is_enrolled,
            (SELECT status FROM course_requests WHERE user_email = '$e' AND course_id = c.id ORDER BY id DESC LIMIT 1) as req_status,
            (SELECT status FROM reports WHERE email = '$e' AND course_id = c.id ORDER BY id DESC LIMIT 1) as report_status
            FROM courses c
            LEFT JOIN user_courses uc ON c.id = uc.course_id AND uc.user_email = '$e'
            ORDER BY c.id DESC";
} else {
    $sql = "SELECT *, 0 as is_enrolled, NULL as req_status, NULL as report_status FROM courses ORDER BY id DESC";
}

$result = $conn->query($sql);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        // Reveal the premium file path ONLY when the caller owns the course AND
        // identity was proven by the token. Email-only (unverified) callers
        // never get a path, so paths can't be scraped by spoofing an email.
        $owns_verified = ($verified_email !== '' && (int)($row['is_enrolled'] ?? 0) === 1);
        if (!$owns_verified && array_key_exists('pdf_file', $row)) {
            $row['pdf_file'] = '';
        }
        $courses[] = $row;
    }
    echo json_encode($courses);
} else {
    // SQL Error Handler (same shape as before)
    echo json_encode([[
        "id" => 0,
        "title" => "⚠️ Database Error",
        "desc1" => "Query Fail: Check if tables exist.",
        "desc2" => "Error: " . $conn->error,
        "price" => 0,
        "old_price" => 0,
        "image" => "small-logo.png",
        "btn_text" => "System Error",
        "btn_type" => "disabled",
        "is_enrolled" => 0,
        "req_status" => null,
        "report_status" => null
    ]]);
}

$conn->close();
