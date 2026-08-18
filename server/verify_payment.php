<?php
/**
 * Hostinger: public_html/premind/verify_payment.php
 * Frontend calls this after Cashfree redirect to confirm the order was
 * actually PAID (server-side, via Cashfree's Orders API) before granting
 * course access — never trust the redirect alone.
 */
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . '/pm_load_env.php';
pm_load_dotenv(__DIR__ . '/.env');

include 'db_connect.php';

// =============== CASHFREE CREDENTIALS ===============
// Read from .env only — must match whatever create_order.php uses to
// actually create the order, or every verification fails after a key
// rotation on one side but not the other.
$appId     = pm_env('CASHFREE_APP_ID');
$secretKey = pm_env('CASHFREE_SECRET_KEY');
$env       = pm_env('CASHFREE_ENV', 'PROD');

if ($appId === '' || $appId === 'CHANGE_ME' || $secretKey === '' || $secretKey === 'CHANGE_ME') {
    echo json_encode(['status' => 'error', 'message' => 'Payment gateway not configured.']);
    exit();
}
// ====================================================

$order_id = $conn->real_escape_string($_GET['order_id'] ?? '');
$course_id = $conn->real_escape_string($_GET['course_id'] ?? '');
$user_email = $conn->real_escape_string($_GET['email'] ?? '');

if ($order_id === '' || $course_id === '' || $user_email === '') {
    echo json_encode(["status" => "error", "message" => "Missing order_id/course_id/email"]);
    exit();
}

$url = ($env == "PROD") ? "https://api.cashfree.com/pg/orders/$order_id" : "https://sandbox.cashfree.com/pg/orders/$order_id";

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Content-Type: application/json",
    "x-api-version: 2023-08-01",
    "x-client-id: $appId",
    "x-client-secret: $secretKey"
]);

$response = curl_exec($ch);
curl_close($ch);

$resData = json_decode($response, true);

if (isset($resData['order_status']) && $resData['order_status'] === "PAID") {
    // 🎉 Database me course update karo
    $check = $conn->query("SELECT * FROM user_courses WHERE user_email='$user_email' AND course_id='$course_id'");

    if ($check->num_rows == 0) {
        $conn->query("INSERT INTO user_courses (user_email, course_id) VALUES ('$user_email', '$course_id')");
    }

    // Frontend ko Success signal bhej do
    echo json_encode(["status" => "success"]);
} else {
    // Payment Fail signal
    echo json_encode(["status" => "error", "message" => "Payment verification failed."]);
}
