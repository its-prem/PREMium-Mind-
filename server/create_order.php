<?php
/**
 * Upload to Hostinger premind/ as: create_order.php   (replaces the old one)
 *
 * Starts a Cashfree payment for ONE course. What is different from the old file:
 *   - WHO is buying comes only from the signed login token (X-PM-Session). A raw email sent by the
 *     browser is never trusted, so nobody can buy for (or spoof) someone else.
 *   - The price, the phone number and the name come from YOUR database, not from the browser.
 *   - Already-owned, trashed, "coming soon" and app-only courses are refused.
 *   - The page Cashfree returns to is built on the server from an allowed website, so it cannot be
 *     turned into an open redirect.
 *   - Every attempt is saved in the `orders` table, which verify_payment.php and webhook.php check.
 *
 * Request  (POST JSON):  { "course_id": 12, "return_origin": "https://premind.netlify.app", "from_app": false }
 * Response (JSON):       { "status":"success", "order_id":"...", "payment_session_id":"...", "amount":49 }
 *                    or  { "status":"error", "message":"...", "code":"..." }
 */

require_once __DIR__ . '/pm_cors.php';
pm_cors_json(['POST']);

require_once __DIR__ . '/pm_load_env.php';
pm_load_dotenv(__DIR__ . '/.env');
require_once __DIR__ . '/pm_session_token.php';
require_once __DIR__ . '/pm_orders.php';
include __DIR__ . '/db_connect.php';

function pm_fail(string $message, string $code, int $http = 400, array $extra = []): void {
    http_response_code($http);
    echo json_encode(array_merge(['status' => 'error', 'message' => $message, 'code' => $code], $extra));
    exit;
}

$data  = pm_request_body();
$email = strtolower(trim(pm_session_email(pm_request_token($data))));
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    pm_fail('Please log in again, then try the payment.', 'login_required', 401);
}

// ---- Cashfree keys (server only, from .env) ----
$appId     = pm_env('CASHFREE_APP_ID');
$secretKey = pm_env('CASHFREE_SECRET_KEY');
$env       = pm_env('CASHFREE_ENV', 'PROD');
if ($appId === '' || $appId === 'CHANGE_ME' || $secretKey === '' || $secretKey === 'CHANGE_ME') {
    pm_fail('Payment gateway is not configured on the server.', 'gateway_not_configured', 500);
}

// ---- the course ----
$courseId = (int)($data['course_id'] ?? $data['courseId'] ?? 0);
if ($courseId <= 0) pm_fail('Course id is missing.', 'bad_course');

$stmt = $conn->prepare('SELECT * FROM courses WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $courseId);
$stmt->execute();
$course = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$course || (int)($course['is_deleted'] ?? 0) === 1) {
    pm_fail('This product is not available.', 'course_not_found', 404);
}
if (in_array((string)($course['btn_type'] ?? ''), ['coming_soon', 'disabled', 'disabled_look'], true)) {
    pm_fail('This product is not open for sale yet.', 'not_for_sale', 403);
}
$fromApp = !empty($data['from_app']) && in_array($data['from_app'], [true, 1, '1', 'true'], true);
if ((int)($course['app_only'] ?? 0) === 1 && !$fromApp) {
    pm_fail('This course can be bought only in the PREMium Mind app.', 'app_only', 403);
}

// ---- what is being bought: the product, or the micro format of an Important Questions product ----
$kind = (($data['kind'] ?? '') === 'micro') ? 'micro' : 'course';
pm_orders_ensure_tables($conn);
if ($kind === 'micro') {
    if (strtolower((string)($course['category'] ?? '')) !== 'important' || empty($course['micro_enabled'])) {
        pm_fail('The micro format is not available for this product.', 'micro_unavailable', 403);
    }
    if (!pm_user_owns($conn, $email, $courseId)) {
        pm_fail('Please buy this product first, then you can buy its micro format.', 'buy_first', 403);
    }
    if (pm_user_owns_micro($conn, $email, $courseId)) {
        pm_fail('You already own the micro format.', 'already_owned', 409);
    }
    $priceRaw = (string)($course['micro_price'] ?? '');
} else {
    if (pm_user_owns($conn, $email, $courseId)) {
        pm_fail('You already own this product.', 'already_owned', 409);
    }
    $priceRaw = (string)$course['price'];
}

// ---- price from the database ----
$price = preg_replace('/[^0-9.]/', '', $priceRaw);
$price = round((float)$price, 2);
if ($price < 1) pm_fail('This product has no valid price. Please contact support.', 'bad_price', 500);

// ---- customer profile from the database ----
$name  = 'Student';
$phone = '';
$stmt = $conn->prepare('SELECT name, phone FROM users WHERE email = ? LIMIT 1');
$stmt->bind_param('s', $email);
$stmt->execute();
$u = $stmt->get_result()->fetch_assoc();
$stmt->close();
if ($u) {
    if (trim((string)$u['name']) !== '') $name = trim((string)$u['name']);
    $digits = substr(preg_replace('/\D/', '', (string)$u['phone']), -10);
    if (strlen($digits) === 10 && $digits !== '0000000000' && $digits !== '9999999999') $phone = $digits;
}
if ($phone === '') {
    pm_fail('Your profile has no valid 10-digit mobile number. Please update your profile, then try again.', 'phone_missing');
}

// ---- where Cashfree sends the buyer back (built here, never copied from the browser) ----
$returnOrigin = rtrim((string)($data['return_origin'] ?? ''), '/');
if ($returnOrigin === '' || !pm_origin_ok($returnOrigin) || strpos($returnOrigin, 'http') !== 0) {
    $returnOrigin = rtrim(pm_env('SITE_URL', 'https://premind.netlify.app'), '/');
}
// Cashfree replaces {order_id} itself
$returnUrl = $returnOrigin . '/payment-return?oid={order_id}&cid=' . $courseId . ($kind === 'micro' ? '&kind=micro' : '');

$orderId = 'ORDER_' . time() . '_' . random_int(100000, 999999);
$customerId = 'CUST_' . substr(preg_replace('/[^a-zA-Z0-9]/', '', $email), 0, 40);
if (strlen($customerId) < 6) $customerId = 'CUST_' . random_int(100000, 999999);

$payload = [
    'order_id'       => $orderId,
    'order_amount'   => $price,
    'order_currency' => 'INR',
    'customer_details' => [
        'customer_id'    => $customerId,
        'customer_name'  => $name,
        'customer_email' => $email,
        'customer_phone' => $phone,
    ],
    'order_meta' => ['return_url' => $returnUrl],
    'order_tags' => ['course_id' => (string)$courseId, 'kind' => $kind],
];

$url = ($env === 'PROD') ? 'https://api.cashfree.com/pg/orders' : 'https://sandbox.cashfree.com/pg/orders';
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 25);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'x-api-version: 2023-08-01',
    "x-client-id: $appId",
    "x-client-secret: $secretKey",
]);
$response = curl_exec($ch);
$curlErr  = curl_error($ch);
curl_close($ch);

if ($response === false) {
    error_log('create_order cURL: ' . $curlErr);
    pm_fail('Could not reach the payment gateway. Please try again.', 'gateway_unreachable', 502);
}
$cf = json_decode($response, true);
if (!is_array($cf) || empty($cf['payment_session_id'])) {
    error_log('create_order Cashfree said: ' . $response);
    pm_fail((string)($cf['message'] ?? 'The payment gateway refused this order.'), 'gateway_error', 502);
}

// ---- remember the attempt ----
$stmt = $conn->prepare("INSERT INTO orders (order_id, user_email, course_id, amount, status, kind) VALUES (?, ?, ?, ?, 'CREATED', ?)");
$stmt->bind_param('ssids', $orderId, $email, $courseId, $price, $kind);
$stmt->execute();
$stmt->close();

echo json_encode([
    'status'             => 'success',
    'order_id'           => $orderId,
    'payment_session_id' => $cf['payment_session_id'],
    'amount'             => $price,
    'course_id'          => $courseId,
    'kind'               => $kind,
    'env'                => ($env === 'PROD') ? 'production' : 'sandbox',
]);
