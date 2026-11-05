<?php
/**
 * Upload to Hostinger premind/ as: d2d_pyq_data.php
 * Also upload the folder:          d2d_pyq_data/  (holds data_YYYY.json + .htaccess)
 *
 * GATED question data for the D2D PYQ page. This is the server-side gate the
 * page depends on: the question files are NOT public anymore. The browser can
 * no longer fetch data/data_2017.js directly, and flipping a variable in
 * dev-tools does nothing — every year's questions are returned ONLY after this
 * endpoint confirms the caller is allowed that year.
 *
 *   - 2026 (FREE_YEARS) ....... any logged-in user
 *   - every other year ........ only emails granted FULL access by the admin
 *                               (the d2d_pyq_access table)
 *
 * Identity is read from the signed pm_pdf_session token (HMAC-signed, cannot be
 * faked without the server secret). For PAID years a valid token is REQUIRED —
 * a raw ?email= is never trusted, so nobody can type a buyer's email to unlock
 * papers. The free year also accepts a raw email (nothing to bypass there).
 * A disallowed year returns 403 and no questions.
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$allowed_origins = [
    'https://premind.netlify.app',
    'https://premind.diplomawallah.in',
    'https://diplomawallah.in',
    'https://www.diplomawallah.in',
    'https://localhost', 'http://localhost', 'capacitor://localhost', 'ionic://localhost',
];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$originOk = ($origin !== '' && (in_array($origin, $allowed_origins, true)
    || preg_match('~^https://[a-z0-9-]+\.netlify\.app$~i', $origin)));
if ($originOk) {
    header("Access-Control-Allow-Origin: $origin");
    header('Vary: Origin');
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
} elseif ($origin !== '') {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'origin']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

require_once __DIR__ . '/pm_session_token.php';

// Years everyone gets for free (no admin grant needed).
$FREE_YEARS = [2026];

$data = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($data)) $data = $_POST;

$year = (int)($data['year'] ?? ($_GET['year'] ?? 0));
if ($year < 2000 || $year > 2100) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'bad year']);
    exit;
}

// The data file must live in the locked folder; reject anything else.
$file = __DIR__ . '/d2d_pyq_data/data_' . $year . '.json';
if (!is_file($file)) {
    http_response_code(404);
    echo json_encode(['status' => 'error', 'message' => 'no such paper']);
    exit;
}

// ---- identify the caller ----
// The signed pm_pdf_session token is the ONLY proof of identity we trust for
// paid papers: it is HMAC-signed with the server secret, so it cannot be faked.
// A raw ?email= can be anyone's, so it unlocks nothing paid.
$sess        = (string)($data['pdf_session'] ?? '');
$token_email = ($sess !== '') ? strtolower(trim(pm_session_email($sess))) : '';
$raw_email   = strtolower(trim((string)($data['email'] ?? '')));

$is_free = in_array($year, $FREE_YEARS, true);

if ($is_free) {
    // Free year: nothing to bypass, so any logged-in user is fine. Prefer the
    // token, accept the raw email as a fallback.
    $email = $token_email !== '' ? $token_email : $raw_email;
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'login required']);
        exit;
    }
} else {
    // Paid year: identity MUST come from a valid token. No raw-email fallback —
    // that was the hole that let someone type a buyer's email to unlock papers.
    if ($token_email === '' || !filter_var($token_email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Please log in again to open paid papers.']);
        exit;
    }
    include 'db_connect.php';
    require_once __DIR__ . '/d2d_pyq_check.php';
    $conn->query("CREATE TABLE IF NOT EXISTS d2d_pyq_access (
        id INT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(190) NOT NULL UNIQUE,
        granted_by VARCHAR(190) NOT NULL DEFAULT '',
        granted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    if (!pm_d2d_pyq_has_full($conn, $token_email)) {
        http_response_code(403);
        echo json_encode(['status' => 'locked', 'message' => 'This year is locked. Contact admin for full access.']);
        exit;
    }
}

// ---- allowed: stream the questions straight through ----
$raw = file_get_contents($file);
if ($raw === false) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'read failed']);
    exit;
}
echo '{"status":"success","year":' . $year . ',"questions":' . $raw . '}';
