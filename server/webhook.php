<?php
/**
 * Upload to Hostinger premind/ as: webhook.php   (replaces the old one)
 *
 * Cashfree calls this by itself when a payment succeeds, so the buyer gets the course even if they
 * close the page before coming back. Set the URL in the Cashfree dashboard:
 *   Developers -> Webhooks -> Payment Success ->  https://premind.diplomawallah.in/webhook.php
 *
 * Differences from the old file:
 *   - the buyer and course are read from OUR `orders` row (written by create_order.php), and the paid
 *     amount is checked, so a forged or replayed webhook cannot give a different course away;
 *   - granting twice is safe (no duplicate rows);
 *   - the log file never contains the secret and is blocked from the web by .htaccess.
 */

require_once __DIR__ . '/pm_load_env.php';
pm_load_dotenv(__DIR__ . '/.env');
require_once __DIR__ . '/pm_orders.php';

$log = function (string $line): void {
    @file_put_contents(__DIR__ . '/webhook_debug.txt', '[' . date('Y-m-d H:i:s') . '] ' . $line . "\n", FILE_APPEND);
};

$payload   = (string)file_get_contents('php://input');
$headers   = function_exists('getallheaders') ? array_change_key_case(getallheaders(), CASE_LOWER) : [];
$signature = (string)($headers['x-webhook-signature'] ?? '');
$timestamp = (string)($headers['x-webhook-timestamp'] ?? '');

$secretKey = pm_env('CASHFREE_SECRET_KEY');
if ($secretKey === '' || $secretKey === 'CHANGE_ME') {
    $log('ERROR: CASHFREE_SECRET_KEY is not set in .env');
    http_response_code(500);
    exit;
}

$expected = base64_encode(hash_hmac('sha256', $timestamp . $payload, $secretKey, true));
if ($signature === '' || !hash_equals($expected, $signature)) {
    $log('Signature check FAILED');
    http_response_code(401);
    exit;
}

$data  = json_decode($payload, true);
$event = (string)($data['type'] ?? '');
$log("Event: $event");

if ($event !== 'PAYMENT_SUCCESS_WEBHOOK') {
    http_response_code(200); // other events: nothing to do
    exit;
}

$orderId = trim((string)($data['data']['order']['order_id'] ?? ''));
$amount  = round((float)($data['data']['order']['order_amount'] ?? 0), 2);
if ($orderId === '') {
    $log('ERROR: order id missing');
    http_response_code(200);
    exit;
}

include __DIR__ . '/db_connect.php';
pm_orders_ensure_tables($conn);

$stmt = $conn->prepare('SELECT user_email, course_id, amount, status, kind FROM orders WHERE order_id = ? LIMIT 1');
$stmt->bind_param('s', $orderId);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) {
    // An order we did not create (for example from the old website): fall back to the order tags.
    $email    = strtolower(trim((string)($data['data']['customer_details']['customer_email'] ?? '')));
    $courseId = (int)($data['data']['order']['order_tags']['course_id'] ?? 0);
    $log("Unknown order $orderId, using the webhook data");
} else {
    $email    = strtolower(trim((string)$order['user_email']));
    $courseId = (int)$order['course_id'];
    if (abs($amount - (float)$order['amount']) > 0.009) {
        $log("ERROR: amount mismatch for $orderId ($amount vs {$order['amount']})");
        http_response_code(200);
        exit;
    }
}

if ($email === '' || $courseId <= 0) {
    $log("ERROR: email/course missing for $orderId");
    http_response_code(200);
    exit;
}

if ($order ? pm_grant_for_order($conn, $order) : pm_grant_course($conn, $email, $courseId)) {
    $log("Course $courseId given to $email ($orderId)");
    if ($order) {
        $stmt = $conn->prepare("UPDATE orders SET status = 'PAID', paid_at = NOW() WHERE order_id = ?");
        $stmt->bind_param('s', $orderId);
        $stmt->execute();
        $stmt->close();
    }
    http_response_code(200);
} else {
    $log("ERROR: could not give course $courseId to $email ($orderId)");
    http_response_code(500); // Cashfree will retry
}
