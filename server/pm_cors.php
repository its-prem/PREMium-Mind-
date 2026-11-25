<?php
/**
 * Upload to Hostinger premind/ as: pm_cors.php
 *
 * ONE place that decides which websites may call the JSON APIs from a browser.
 * The new endpoints (create_order, verify_payment, progress, my_courses, ...) all use it,
 * so you add a new website here (or in .env) instead of editing every file.
 *
 * .env keys (all optional):
 *   EXTRA_ALLOWED_ORIGINS = https://mynewsite.com,https://www.mynewsite.com
 *   ALLOW_LOCALHOST_DEV   = 1      <- lets http://localhost:5173 (any port) call the APIs while you
 *                                     test. Set it to 0 (or delete the line) on the real server.
 */

require_once __DIR__ . '/pm_load_env.php';
pm_load_dotenv(__DIR__ . '/.env');

if (!function_exists('pm_allowed_origins')) {
    function pm_allowed_origins(): array {
        $list = [
            'https://premind.diplomawallah.in',
            'https://diplomawallah.in',
            'https://www.diplomawallah.in',
            'https://premind.netlify.app',
            'https://localhost', 'http://localhost', 'capacitor://localhost', 'ionic://localhost',
        ];
        foreach (explode(',', pm_env('EXTRA_ALLOWED_ORIGINS')) as $o) {
            $o = rtrim(trim($o), '/');
            if ($o !== '') $list[] = $o;
        }
        return $list;
    }
}

if (!function_exists('pm_origin_ok')) {
    function pm_origin_ok(string $origin): bool {
        if ($origin === '') return false;
        if (in_array($origin, pm_allowed_origins(), true)) return true;
        if (preg_match('~^https://[a-z0-9-]+\.netlify\.app$~i', $origin)) return true;
        if (pm_env('ALLOW_LOCALHOST_DEV') === '1'
            && preg_match('~^http://(localhost|127\.0\.0\.1)(:\d+)?$~', $origin)) return true;
        return false;
    }
}

/**
 * Sends the JSON + CORS headers, answers the browser's OPTIONS check and refuses unknown websites.
 * Call it first in every API file:  pm_cors_json(['POST']);
 */
if (!function_exists('pm_cors_json')) {
    function pm_cors_json(array $methods = ['POST'], bool $publicRead = false): void {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

        if ($publicRead) {
            // Public, read-only data (like the store catalog): any site may read it.
            header('Access-Control-Allow-Origin: *');
        } elseif ($origin !== '') {
            if (!pm_origin_ok($origin)) {
                http_response_code(403);
                echo json_encode(['status' => 'error', 'message' => 'Origin not allowed']);
                exit;
            }
            header("Access-Control-Allow-Origin: $origin");
            header('Vary: Origin');
        }
        header('Access-Control-Allow-Methods: ' . implode(', ', array_unique(array_merge($methods, ['OPTIONS']))));
        header('Access-Control-Allow-Headers: Content-Type, X-PM-Session');

        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
            http_response_code(204);
            exit;
        }
        if (!in_array($_SERVER['REQUEST_METHOD'] ?? '', $methods, true)) {
            http_response_code(405);
            echo json_encode(['status' => 'error', 'message' => implode('/', $methods) . ' only']);
            exit;
        }
    }
}

/** The signed pm_pdf_session token sent by the website (header, query or body). */
if (!function_exists('pm_request_token')) {
    function pm_request_token(array $body = []): string {
        if (!empty($_SERVER['HTTP_X_PM_SESSION'])) return (string)$_SERVER['HTTP_X_PM_SESSION'];
        if (!empty($body['pdf_session'])) return (string)$body['pdf_session'];
        if (!empty($_GET['pdf_session']))  return (string)$_GET['pdf_session'];
        if (!empty($_POST['pdf_session'])) return (string)$_POST['pdf_session'];
        return '';
    }
}

/** JSON body (or form body) of the request as an array. */
if (!function_exists('pm_request_body')) {
    function pm_request_body(): array {
        $data = json_decode((string)file_get_contents('php://input'), true);
        if (!is_array($data)) $data = $_POST;
        return is_array($data) ? $data : [];
    }
}
