<?php
/**
 * Upload to Hostinger premind/ as: my_courses.php
 *
 * The courses the logged-in user owns (ids + when they bought them).
 * The new website uses it to show "Purchased" on store cards and to fill My Courses.
 * Identity comes only from the signed login token.
 *
 * GET -> { "status":"success", "courses":[ { "course_id":12, "purchased_at":"2026-11-25 10:00:00" }, ... ] }
 */

require_once __DIR__ . '/pm_cors.php';
pm_cors_json(['GET', 'POST']);

require_once __DIR__ . '/pm_session_token.php';
include __DIR__ . '/db_connect.php';

$body  = pm_request_body();
$email = strtolower(trim(pm_session_email(pm_request_token($body))));
if ($email === '') {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Login required', 'code' => 'login_required']);
    exit;
}

$hasPurchasedAt = false;
$col = @$conn->query("SHOW COLUMNS FROM user_courses LIKE 'purchased_at'");
if ($col && $col->num_rows > 0) $hasPurchasedAt = true;

$sql = $hasPurchasedAt
    ? 'SELECT course_id, purchased_at FROM user_courses WHERE user_email = ? ORDER BY purchased_at DESC'
    : 'SELECT course_id, NULL AS purchased_at FROM user_courses WHERE user_email = ?';
$stmt = $conn->prepare($sql);
$stmt->bind_param('s', $email);
$stmt->execute();
$res = $stmt->get_result();

$list = [];
while ($r = $res->fetch_assoc()) {
    $list[] = ['course_id' => (int)$r['course_id'], 'purchased_at' => $r['purchased_at']];
}
$stmt->close();

echo json_encode(['status' => 'success', 'courses' => $list]);
