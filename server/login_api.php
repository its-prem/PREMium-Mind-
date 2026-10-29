<?php
$allowed_origins = [
    'https://premind.netlify.app',
    'https://premind.diplomawallah.in',
    'https://diplomawallah.in',
    'https://www.diplomawallah.in',
];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin !== '' && (in_array($origin, $allowed_origins, true)
        || preg_match('#^https://[a-z0-9-]+\.netlify\.app$#i', $origin))) {
    header("Access-Control-Allow-Origin: $origin");
    header("Vary: Origin");
}
header("Access-Control-Allow-Credentials: true");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(204);
    exit;
}

include 'db_connect.php';
require_once __DIR__ . '/pm_session_token.php';   // PDF session token minter

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = $conn->real_escape_string($_POST['email']);
    $password = $_POST['password'];

    $sql = "SELECT * FROM users WHERE email='$email'";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();

        if ($user['is_active'] == 0) {
            echo json_encode(["status" => "error", "message" => "Account not verified."]);
            exit;
        }

        if (password_verify($password, $user['password'])) {

            session_set_cookie_params([
                'lifetime' => 86400,
                'path' => '/',
                'domain' => '.diplomawallah.in',
                'secure' => true,
                'httponly' => true,
                'samesite' => 'None'
            ]);

            session_start();
            $_SESSION['user_email'] = $user['email'];

            echo json_encode([
                "status"  => "success",
                "message" => "Login successful!",
                "name"    => $user['name'],
                "email"   => $user['email'],
                "phone"   => $user['phone'] ?? '',
                "pdf_session" => pm_session_make($user['email'])   // identity proven via password
            ]);
        } else {
            echo json_encode(["status" => "error", "message" => "Incorrect password!"]);
        }
    } else {
        echo json_encode(["status" => "error", "message" => "Email not found!"]);
    }
}
?>
