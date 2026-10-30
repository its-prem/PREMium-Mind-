<?php
/**
 * Upload to Hostinger premind/ as: d2d_pyq_access.php
 *
 * Student check: does this email have FULL access to the D2D PYQ page?
 * Emails are granted by the admin (d2d_pyq_admin.php). Everyone else only
 * gets the free year on the page (2026). Identity is taken from the signed
 * pm_pdf_session token when present (can't be faked), else the raw email.
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
    echo json_encode(['status' => 'error', 'full' => false]);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

include 'db_connect.php';
require_once __DIR__ . '/pm_session_token.php';

$conn->query("CREATE TABLE IF NOT EXISTS d2d_pyq_access (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(190) NOT NULL UNIQUE,
    granted_by VARCHAR(190) NOT NULL DEFAULT '',
    granted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$data = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($data)) $data = $_POST;

// Prefer the verified session token; fall back to the raw email.
$email = '';
$sess = (string)($data['pdf_session'] ?? '');
if ($sess !== '') $email = pm_session_email($sess);
if ($email === '') $email = strtolower(trim((string)($data['email'] ?? '')));

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['status' => 'success', 'full' => false]);
    exit;
}

$e = $conn->real_escape_string($email);
$r = $conn->query("SELECT 1 FROM d2d_pyq_access WHERE email='$e' LIMIT 1");
$full = ($r && $r->num_rows > 0);

echo json_encode(['status' => 'success', 'full' => $full]);
