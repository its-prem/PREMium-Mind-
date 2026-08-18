<?php
// ══════════════════════════════════════════
//  config.php  —  Database Configuration
//  Upload to Hostinger premind/ as: config.php
// ══════════════════════════════════════════

require_once __DIR__ . '/pm_load_env.php';
pm_load_dotenv(__DIR__ . '/.env');

define('DB_HOST', pm_env('DB_HOST', 'localhost'));
define('DB_NAME', pm_env('DB_NAME'));
define('DB_USER', pm_env('DB_USER'));
define('DB_PASS', pm_env('DB_PASSWORD'));
define('DB_CHARSET', 'utf8mb4');

if (DB_NAME === '' || DB_NAME === 'CHANGE_ME' || DB_USER === '' || DB_USER === 'CHANGE_ME') {
    http_response_code(500);
    error_log('config.php: DB_NAME/DB_USER missing or still CHANGE_ME — set them in .env');
    echo json_encode(['status' => 'error', 'message' => 'Database not configured.']);
    exit();
}

// ── CORS: allow only known frontends — no wildcard fallback ──
// The previous version had a trailing slash on the Netlify entry
// ('https://premind.netlify.app/') which a browser's Origin header never
// has, so the in_array() check always failed and every request fell
// through to `Access-Control-Allow-Origin: *`, open to any site. Fixed
// here, and the wildcard fallback is gone — an unrecognized Origin now
// just gets no CORS header (browsers block the cross-origin response)
// instead of being let through by default.
$allowed_origins = [
    'https://premind.diplomawallah.in',
    'https://diplomawallah.in',
    'https://www.diplomawallah.in',
    'https://premind.netlify.app',
    'https://localhost',
    'http://localhost',
    'capacitor://localhost',
    'ionic://localhost',
];

function pm_config_origin_allowed(string $origin): bool {
    global $allowed_origins;
    if ($origin === '') return false;
    if (in_array($origin, $allowed_origins, true)) return true;
    // Any Netlify deploy preview / branch URL
    if (preg_match('#^https://[a-z0-9-]+\.netlify\.app$#i', $origin)) return true;
    return false;
}

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (pm_config_origin_allowed($origin)) {
    header("Access-Control-Allow-Origin: $origin");
    header('Vary: Origin');
}

header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json; charset=UTF-8");

// Pre-flight OPTIONS request handle karo
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// ── PDO Connection ──
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET,
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    error_log('config.php DB connect failed: ' . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed.']);
    exit();
}

// ── Helper: send JSON response ──
function respond($status, $message, $data = []) {
    echo json_encode(array_merge(['status' => $status, 'message' => $message], $data));
    exit();
}
