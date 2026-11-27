<?php
/**
 * Shared start-up for every admin API file (upload folder: public_html/premind/admin/).
 *
 *  - CORS (same rules as the other APIs, see ../pm_cors.php)
 *  - who may use the admin: the signed login token (pm_pdf_session) must belong to an
 *    email listed in ADMIN_EMAILS in the server .env (comma separated)
 *  - database connection ($conn) and small helpers
 *
 * To make someone an admin: add their email to ADMIN_EMAILS in the server .env (comma separated). No code change.
 */

require_once __DIR__ . '/../pm_cors.php';
require_once __DIR__ . '/../pm_session_token.php';
require_once __DIR__ . '/../db_connect.php';   // gives $conn
require_once __DIR__ . '/../pm_orders.php';
require_once __DIR__ . '/../pm_admin_emails.php';
require_once __DIR__ . '/../pm_rate_limit.php';

pm_cors_json(['GET', 'POST']);
require_once __DIR__ . '/../pm_ip_guard.php';
pm_ip_guard_or_die($conn);   // a blocked address cannot use the admin API (an admin token always passes)

function pm_admin_list(): array {
    return pm_admin_email_list();   // from ADMIN_EMAILS in .env; empty list = nobody is an admin
}

/** Stops with 401/403 unless the request carries an admin's login token. Returns the admin email. */
function pm_admin_require(): string {
    $body = pm_request_body();
    global $conn;
    $email = pm_session_email(pm_request_token($body));
    $isAdmin = $email !== '' && in_array($email, pm_admin_list(), true);
    if (!$isAdmin) {
        // count the failed try: 30 per 15 minutes per address, then the address is blocked for a while
        if (!pm_rate_limit($conn, 'admin-fail:' . pm_client_ip(), 30, 900)) {
            pm_admin_fail('Too many attempts. Please wait a few minutes.', 429, 'rate_limited');
        }
        if ($email === '') pm_admin_fail('Please log in first.', 401, 'unauthenticated');
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
