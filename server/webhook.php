<?php
/**
 * Hostinger: public_html/premind/webhook.php
 * Cashfree PAYMENT_SUCCESS_WEBHOOK receiver — grants course access as soon
 * as Cashfree confirms payment, independent of the frontend redirect.
 */

require_once __DIR__ . '/pm_load_env.php';
pm_load_dotenv(__DIR__ . '/.env');

// Debugging ke liye ek file banayenge — protected by .htaccess (never
// reachable over HTTP), since it logs customer emails/course ids.
$log_file = __DIR__ . '/webhook_debug.txt';
file_put_contents($log_file, "--- Webhook Hit at " . date('Y-m-d H:i:s') . " ---\n", FILE_APPEND);

$payload = file_get_contents('php://input');
$headers = getallheaders();

$cf_signature = $headers['x-webhook-signature'] ?? '';
$cf_timestamp = $headers['x-webhook-timestamp'] ?? '';

// Read from .env — must match whatever create_order.php/verify_payment.php
// use, or a key rotation on one side but not this one breaks every webhook.
$secretKey = pm_env('CASHFREE_SECRET_KEY');

if ($secretKey === '' || $secretKey === 'CHANGE_ME') {
    file_put_contents($log_file, "ERROR: CASHFREE_SECRET_KEY not configured in .env\n------------------------\n\n", FILE_APPEND);
    http_response_code(500);
    exit();
}

$dataToBind = $cf_timestamp . $payload;
$generatedSignature = base64_encode(hash_hmac('sha256', $dataToBind, $secretKey, true));

file_put_contents($log_file, "Signature Match Check: " . ($cf_signature === $generatedSignature ? "SUCCESS" : "FAILED") . "\n", FILE_APPEND);

if (hash_equals($generatedSignature, (string)$cf_signature)) {
    $data = json_decode($payload, true);
    $event = $data['type'] ?? '';

    file_put_contents($log_file, "Event Type: " . $event . "\n", FILE_APPEND);

    if ($event == 'PAYMENT_SUCCESS_WEBHOOK') {
        $userEmail = $data['data']['customer_details']['customer_email'] ?? '';
        $courseId = $data['data']['order']['order_tags']['course_id'] ?? '';

        file_put_contents($log_file, "Email Extracted: " . $userEmail . "\n", FILE_APPEND);
        file_put_contents($log_file, "Course ID Extracted: " . $courseId . "\n", FILE_APPEND);

        if (!empty($userEmail) && !empty($courseId)) {
            include __DIR__ . '/db_connect.php';

            date_default_timezone_set('Asia/Kolkata');
            $stmt = $conn->prepare("INSERT INTO user_courses (user_email, course_id, purchased_at) VALUES (?, ?, ?)");
            $time_now = date('Y-m-d H:i:s');
            $stmt->bind_param("sis", $userEmail, $courseId, $time_now);

            if ($stmt->execute()) {
                file_put_contents($log_file, "DB Insert SUCCESS! Course Added.\n", FILE_APPEND);
                http_response_code(200);
            } else {
                file_put_contents($log_file, "DB Insert FAILED: " . $stmt->error . "\n", FILE_APPEND);
                http_response_code(500);
            }
            $stmt->close();
            $conn->close();
        } else {
            file_put_contents($log_file, "ERROR: Email ya Course ID missing hai!\n", FILE_APPEND);
            http_response_code(200);
        }
    }
} else {
    http_response_code(401);
}
file_put_contents($log_file, "------------------------\n\n", FILE_APPEND);
