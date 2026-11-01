<?php
/**
 * Hostinger: public_html/premind/get_user.php
 *
 * Returns a user's OWN profile (name + phone). Identity is taken from the
 * signed pm_pdf_session token — NOT from a ?email= parameter — so a visitor
 * can no longer read another user's phone/PII by passing arbitrary emails
 * (the IDOR is closed). An admin token may look up any email (for support).
 *
 * Backwards compatible for the frontend: it just needs to send pdf_session
 * (header X-PM-Session, or ?pdf_session=, or POST). No valid token -> 401.
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

$ADMIN_EMAILS = ['premku0237@gmail.com', 'ar0319515@gmail.com'];

// --- identity: signed token only ---
$token = '';
if (!empty($_SERVER['HTTP_X_PM_SESSION'])) $token = (string)$_SERVER['HTTP_X_PM_SESSION'];
if ($token === '' && isset($_GET['pdf_session']))  $token = (string)$_GET['pdf_session'];
if ($token === '' && isset($_POST['pdf_session'])) $token = (string)$_POST['pdf_session'];

$identity = ($token !== '') ? pm_session_email($token) : '';
if ($identity === '') {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Login required", "code" => "unauthenticated"]);
    exit;
}

// Self by default. An admin may look up another email via ?email=.
$isAdmin   = in_array(strtolower($identity), array_map('strtolower', $ADMIN_EMAILS), true);
$requested = strtolower(trim((string)($_GET['email'] ?? $_POST['email'] ?? '')));
$target    = ($isAdmin && $requested !== '') ? $requested : $identity;

$e = $conn->real_escape_string($target);
$res = $conn->query("SELECT name, email, phone FROM users WHERE email='$e' LIMIT 1");
if ($res && $res->num_rows > 0) {
    $row = $res->fetch_assoc();
    echo json_encode([
        "status" => "success",
        "name"   => $row['name'],
        "phone"  => $row['phone'],
        "email"  => $row['email'],
    ]);
} else {
    echo json_encode(["status" => "error", "message" => "User not found"]);
}
$conn->close();
