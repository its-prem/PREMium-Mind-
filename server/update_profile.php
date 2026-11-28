<?php
/**
 * Upload to Hostinger premind/ as: update_profile.php
 *
 * A student changes HIS OWN name and phone number (nothing else: not the email, not the password, not what he owns).
 *   POST  update_profile.php   { "name": "Ravi Kumar", "phone": "9876543210" }      (login token in the X-PM-Session header)
 * Answer: { status, name, phone }  or  { status: "error", message, code }
 * Who is changed comes ONLY from the signed login token, never from a field the browser sends.
 */
require_once __DIR__ . '/pm_cors.php';
pm_cors_json(['POST']);

require_once __DIR__ . '/pm_session_token.php';
include __DIR__ . '/db_connect.php';
require_once __DIR__ . '/pm_rate_limit.php';

function pm_prof_fail(string $msg, int $http, string $code): void {
    http_response_code($http);
    echo json_encode(['status' => 'error', 'message' => $msg, 'code' => $code]);
    exit;
}

$body  = pm_request_body();
$email = strtolower(trim(pm_session_email(pm_request_token($body))));
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) pm_prof_fail('Please log in again.', 401, 'login_required');
if (!pm_rate_limit($conn, 'profile:' . $email, 20, 3600)) pm_prof_fail('Too many changes. Please wait a while.', 429, 'rate_limited');
require_once __DIR__ . '/pm_user_guard.php';
pm_user_check($conn, $email);

$name  = trim(preg_replace('/\s+/u', ' ', (string)($body['name'] ?? '')));
$phone = preg_replace('/[^0-9]/', '', (string)($body['phone'] ?? ''));
if (strlen($phone) > 10) $phone = substr($phone, -10);      // drop a +91 / 91 in front

if (mb_strlen($name) < 2 || mb_strlen($name) > 60) pm_prof_fail('Please enter your name (2 to 60 letters).', 400, 'bad_name');
if (preg_match('/[<>]/', $name)) pm_prof_fail('The name cannot contain < or >.', 400, 'bad_name');
if (!preg_match('/^[6-9][0-9]{9}$/', $phone)) pm_prof_fail('Please enter a valid 10 digit mobile number.', 400, 'bad_phone');

$stmt = $conn->prepare('UPDATE users SET name = ?, phone = ? WHERE email = ?');
$stmt->bind_param('sss', $name, $phone, $email);
if (!$stmt->execute()) pm_prof_fail('Could not save. Please try again.', 500, 'db');
if ($stmt->affected_rows === 0) {
    // nothing changed (same values) is fine, but an email that is not in the list is not
    $chk = $conn->prepare('SELECT 1 FROM users WHERE email = ? LIMIT 1');
    $chk->bind_param('s', $email);
    $chk->execute();
    $chk->store_result();
    if ($chk->num_rows === 0) pm_prof_fail('Account not found.', 404, 'not_found');
}
echo json_encode(['status' => 'success', 'name' => $name, 'phone' => $phone]);
