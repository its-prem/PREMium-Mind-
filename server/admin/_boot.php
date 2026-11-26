<?php
/**
 * Shared start-up for every admin API file (upload folder: public_html/premind/admin/).
 *
 *  - CORS (same rules as the other APIs, see ../pm_cors.php)
 *  - who may use the admin: the signed login token (pm_pdf_session) must belong to an
 *    email listed in $PM_ADMIN_EMAILS below (or ADMIN_EMAILS in .env, comma separated)
 *  - database connection ($conn) and small helpers
 *
 * To make someone an admin: add their email to the list below. Nothing else to change.
 */

require_once __DIR__ . '/../pm_cors.php';
require_once __DIR__ . '/../pm_session_token.php';
require_once __DIR__ . '/../db_connect.php';   // gives $conn
require_once __DIR__ . '/../pm_orders.php';

$PM_ADMIN_EMAILS = ['premku0237@gmail.com', 'ar0319515@gmail.com'];

pm_cors_json(['GET', 'POST']);

function pm_admin_list(): array {
    global $PM_ADMIN_EMAILS;
    $list = array_map('strtolower', array_map('trim', $PM_ADMIN_EMAILS));
    foreach (explode(',', pm_env('ADMIN_EMAILS')) as $e) {
        $e = strtolower(trim($e));
        if ($e !== '') $list[] = $e;
    }
    return array_values(array_unique($list));
}

/** Stops with 401/403 unless the request carries an admin's login token. Returns the admin email. */
function pm_admin_require(): string {
    $body = pm_request_body();
    $email = pm_session_email(pm_request_token($body));
    if ($email === '') {
        pm_admin_fail('Please log in first.', 401, 'unauthenticated');
    }
    if (!in_array($email, pm_admin_list(), true)) {
        pm_admin_fail('This account is not an admin.', 403, 'forbidden');
    }
    return $email;
}

function pm_admin_fail(string $msg, int $code = 400, string $errCode = 'error'): void {
    http_response_code($code);
    echo json_encode(['status' => 'error', 'message' => $msg, 'code' => $errCode]);
    exit;
}

function pm_admin_ok(array $data = []): void {
    echo json_encode(array_merge(['status' => 'success'], $data));
    exit;
}

/** One value from the request: JSON body first, then form fields, then the query string. */
function pm_in(string $key, $default = '') {
    static $body = null;
    if ($body === null) $body = pm_request_body();
    if (isset($body[$key])) return $body[$key];
    if (isset($_POST[$key])) return $_POST[$key];
    if (isset($_GET[$key]))  return $_GET[$key];
    return $default;
}

function pm_in_int(string $key, int $default = 0): int {
    return (int)pm_in($key, $default);
}

function pm_in_flag(string $key): int {
    $v = pm_in($key, 0);
    return ($v === true || $v === 1 || $v === '1' || $v === 'true' || $v === 'on') ? 1 : 0;
}

/** Does this column exist? (some old databases miss optional columns) */
function pm_has_column(mysqli $conn, string $table, string $col): bool {
    $t = preg_replace('/[^a-z0-9_]/i', '', $table);
    $stmt = $conn->prepare("SHOW COLUMNS FROM `$t` LIKE ?");
    if (!$stmt) return false;
    $stmt->bind_param('s', $col);
    $stmt->execute();
    $stmt->store_result();
    $has = $stmt->num_rows > 0;
    $stmt->close();
    return $has;
}

/** All rows of a mysqli result as an array (empty array when the query failed). */
function pm_rows($res): array {
    $rows = [];
    if ($res instanceof mysqli_result) {
        while ($r = $res->fetch_assoc()) $rows[] = $r;
    }
    return $rows;
}

/** Single number from "SELECT COUNT(*) ..." style queries. */
function pm_scalar(mysqli $conn, string $sql): float {
    $res = $conn->query($sql);
    if (!($res instanceof mysqli_result)) return 0;
    $row = $res->fetch_row();
    return $row ? (float)$row[0] : 0;
}

/** The option lists the admin forms use (the React side asks for them, so there is one source). */
function pm_admin_options(): array {
    return [
        'categories' => [
            ['id' => 'notes',     'label' => 'Notes'],
            ['id' => 'ebooks',    'label' => 'E-Books'],
            ['id' => 'important', 'label' => 'Important Questions'],
            ['id' => 'projects',  'label' => 'Projects & Reports'],
            ['id' => 'resume',    'label' => 'Resume Templates'],
        ],
        'btnTypes' => [
            ['id' => 'normal',        'label' => 'Normal (clickable)'],
            ['id' => 'coming_soon',   'label' => 'Coming Soon (unclickable)'],
            ['id' => 'disabled_look', 'label' => 'Disabled look (orange)'],
            ['id' => 'preview_buy',   'label' => 'Preview (demo) + Buy'],
            ['id' => 'disabled',      'label' => 'Completely disabled'],
        ],
        'resumeTemplates' => ['modern', 'classic', 'compact'],
    ];
}
