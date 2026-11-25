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
require_once __DIR__ . '/pm_rate_limit.php';      // stops password guessing

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $password = (string)($_POST['password'] ?? '');

    if ($email === '' || $password === '') {
        echo json_encode(["status" => "error", "message" => "Email and password are required."]);
        exit;
    }

    // at most 8 tries per email and 60 per address in 15 minutes (a bot cannot guess passwords)
    if (!pm_rate_limit($conn, 'login-email:' . $email, 8, 900) || !pm_rate_limit($conn, 'login-ip:' . pm_client_ip(), 60, 900)) {
        http_response_code(429);
        echo json_encode(["status" => "error", "message" => "Too many attempts. Please wait 15 minutes and try again."]);
        exit;
    }

    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
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
