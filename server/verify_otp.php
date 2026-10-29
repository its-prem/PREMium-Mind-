<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

error_reporting(0);
ini_set('display_errors', 0);
include 'db_connect.php';
require_once __DIR__ . '/pm_session_token.php';   // PDF session token minter

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = $conn->real_escape_string(trim($_POST['email']));
    $otp = $conn->real_escape_string(trim($_POST['otp']));

    // Match Email and OTP
    $check = $conn->query("SELECT * FROM users WHERE email='$email' AND otp='$otp'");

    if($check->num_rows > 0) {
        // Match successful! Make account active
        $conn->query("UPDATE users SET is_active=1, otp='' WHERE email='$email'");
        // OTP verified = identity proven → mint a signed PDF session token.
        echo json_encode([
            "status" => "success",
            "message" => "Account successfully verified!",
            "pdf_session" => pm_session_make(trim($_POST['email']))
        ]);
    } else {
        echo json_encode(["status" => "error", "message" => "Invalid OTP. Please check and try again."]);
    }
}
?>
