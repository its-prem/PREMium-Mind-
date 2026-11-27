<?php
/**
 * Upload to Hostinger premind/ as: verify_otp.php   (replaces the old one)
 *
 * Fixes over the old file:
 *   - an EMPTY otp used to match every verified account (their otp is stored as ''), which handed out a login
 *     token for any email. Now the otp must be 4-10 letters/digits AND the stored otp must not be empty;
 *   - prepared statements (no SQL injection);
 *   - at most 5 wrong tries per email (and 40 per address) in 15 minutes, so an otp cannot be guessed;
 *   - only our own websites may call it from a browser (it used to allow every site).
 */
require_once __DIR__ . '/pm_cors.php';
pm_cors_json(['POST']);

error_reporting(0);
ini_set('display_errors', 0);
include __DIR__ . '/db_connect.php';
require_once __DIR__ . '/pm_session_token.php';   // PDF session token minter
require_once __DIR__ . '/pm_rate_limit.php';

function pm_otp_fail(string $msg, int $http = 200): void {
    http_response_code($http);
    echo json_encode(['status' => 'error', 'message' => $msg]);
    exit;
}

$email = strtolower(trim((string)($_POST['email'] ?? '')));
$otp   = trim((string)($_POST['otp'] ?? ''));

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !preg_match('/^[A-Za-z0-9]{4,10}$/', $otp)) {
    pm_otp_fail('Invalid OTP. Please check and try again.');
}

if (!pm_rate_limit($conn, 'otp-email:' . $email, 5, 900) || !pm_rate_limit($conn, 'otp-ip:' . pm_client_ip(), 40, 900)) {
    pm_otp_fail('Too many attempts. Please wait 15 minutes and try again.', 429);
}

$stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND otp = ? AND otp <> '' LIMIT 1");
$stmt->bind_param('ss', $email, $otp);
$stmt->execute();
$stmt->store_result();
$found = $stmt->num_rows > 0;
$stmt->close();

if (!$found) {
    pm_otp_fail('Invalid OTP. Please check and try again.');
}

require_once __DIR__ . '/pm_user_guard.php';
if (pm_user_is_blocked($conn, $email)) pm_otp_fail('This account is blocked. Please contact support.', 403);

// OTP is right: activate the account and clear the otp (it works only once).
$stmt = $conn->prepare("UPDATE users SET is_active = 1, otp = '' WHERE email = ?");
$stmt->bind_param('s', $email);
$stmt->execute();
$stmt->close();

echo json_encode([
    'status'      => 'success',
    'message'     => 'Account successfully verified!',
    'pdf_session' => pm_session_make($email),   // identity proven -> signed login token
]);
