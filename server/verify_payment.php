<?php
/**
 * Upload to Hostinger premind/ as: verify_payment.php   (replaces the old one)
 *
 * The website calls this after Cashfree sends the buyer back. It never trusts the redirect:
 * it asks Cashfree whether the order is really PAID, then checks that
 *   - the order belongs to the logged-in user (signed token),
 *   - the paid amount is the one we stored when the order was created,
 * and only then gives the course.
 *
 * Request:  POST/GET  ?order_id=ORDER_...        (header X-PM-Session: <login token>)
 * Response: { "status":"success", "course_id":12 }       paid + course given
 *           { "status":"pending" }                       money not confirmed yet (ask again in a few seconds)
 *           { "status":"failed",  "message":"..." }      cancelled / failed / not found
 */

require_once __DIR__ . '/pm_cors.php';
pm_cors_json(['GET', 'POST']);

require_once __DIR__ . '/pm_session_token.php';
require_once __DIR__ . '/pm_orders.php';
include __DIR__ . '/db_connect.php';
require_once __DIR__ . '/pm_rate_limit.php';

$data    = pm_request_body();
$email   = strtolower(trim(pm_session_email(pm_request_token($data))));
$orderId = trim((string)($_GET['order_id'] ?? $data['order_id'] ?? ''));

if ($email === '') {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Please log in again.', 'code' => 'login_required']);
    exit;
}
if (!pm_rate_limit($conn, 'verify:' . $email, 60, 600)) {
    http_response_code(429);
    echo json_encode(['status' => 'error', 'message' => 'Too many checks. Please wait a minute.', 'code' => 'rate_limited']);
    exit;
}
require_once __DIR__ . '/pm_user_guard.php';
pm_user_check($conn, $email);
if ($orderId === '' || !preg_match('/^[A-Za-z0-9_\-]{6,64}$/', $orderId)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Order id is missing.']);
    exit;
}

pm_orders_ensure_tables($conn);

// Our record of this order
$stmt = $conn->prepare('SELECT order_id, user_email, course_id, amount, status, kind FROM orders WHERE order_id = ? LIMIT 1');
$stmt->bind_param('s', $orderId);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order || strtolower((string)$order['user_email']) !== $email) {
    // Not this user's order: say the same thing as for a missing one.
    echo json_encode(['status' => 'failed', 'message' => 'Order not found.']);
    exit;
}
$courseId = (int)$order['course_id'];
$kind = ($order['kind'] ?? 'course') === 'micro' ? 'micro' : 'course';

// Already handled earlier (webhook may have been first)
if ($order['status'] === 'PAID' && pm_order_owned($conn, $order)) {
    echo json_encode(['status' => 'success', 'course_id' => $courseId, 'kind' => $kind]);
    exit;
}

// Ask Cashfree
$cf = pm_cashfree_fetch_order($orderId);
if ($cf === null) {
    echo json_encode(['status' => 'pending', 'message' => 'Could not reach the payment gateway. Please check again in a moment.']);
    exit;
}

$cfStatus = strtoupper((string)($cf['order_status'] ?? ''));
if ($cfStatus === 'PAID') {
    $paidEmail  = strtolower(trim((string)($cf['customer_details']['customer_email'] ?? '')));
    $paidAmount = round((float)($cf['order_amount'] ?? 0), 2);
    $paidCourse = (int)($cf['order_tags']['course_id'] ?? 0);

    // The paid order must match what we created.
    if ($paidEmail !== $email || $paidCourse !== $courseId || abs($paidAmount - (float)$order['amount']) > 0.009) {
        error_log("verify_payment mismatch for $orderId");
        echo json_encode(['status' => 'failed', 'message' => 'Payment details do not match. Please contact support with order id ' . $orderId . '.']);
        exit;
    }

    if (!pm_grant_for_order($conn, $order)) {
        error_log("verify_payment could not grant $courseId to $email ($orderId)");
        echo json_encode(['status' => 'failed', 'message' => 'Payment received but access could not be saved. Contact support with order id ' . $orderId . '.']);
        exit;
    }

    $stmt = $conn->prepare("UPDATE orders SET status = 'PAID', paid_at = NOW() WHERE order_id = ?");
    $stmt->bind_param('s', $orderId);
    $stmt->execute();
    $stmt->close();

    echo json_encode(['status' => 'success', 'course_id' => $courseId, 'kind' => $kind]);
    exit;
}

if ($cfStatus === 'ACTIVE') {
    // Created but not paid (yet)
    echo json_encode(['status' => 'pending']);
    exit;
}

$stmt = $conn->prepare("UPDATE orders SET status = ? WHERE order_id = ? AND status <> 'PAID'");
$label = substr($cfStatus !== '' ? $cfStatus : 'FAILED', 0, 20);
$stmt->bind_param('ss', $label, $orderId);
$stmt->execute();
$stmt->close();

echo json_encode(['status' => 'failed', 'message' => 'Payment was not completed.']);
