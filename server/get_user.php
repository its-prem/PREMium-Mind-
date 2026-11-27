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
require_once __DIR__ . '/pm_cors.php';
pm_cors_json(['GET', 'POST']);   // only the PREMium Mind websites may read a profile (it holds a phone number)

include 'db_connect.php';
require_once __DIR__ . '/pm_session_token.php';

require_once __DIR__ . '/pm_admin_emails.php';
$ADMIN_EMAILS = pm_admin_email_list();   // from .env ADMIN_EMAILS

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

$stmt = $conn->prepare('SELECT name, email, phone FROM users WHERE email = ? LIMIT 1');
$stmt->bind_param('s', $target);
$stmt->execute();
$res = $stmt->get_result();
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
