<?php
/**
 * Upload to Hostinger premind/ as: pm_ip_guard.php
 *
 * Blocking bad IP addresses.
 *   - The admin blocks an address from Admin -> IP block (for an hour, a day, a week, or for ever).
 *   - A blocked address gets "403 Access blocked" from every sensitive file (login, OTP, payments, PDFs, admin)
 *     because those files call pm_rate_limit(), which calls this guard first.
 *   - An ADMIN is never locked out: a valid admin login token passes even from a blocked address.
 *   - Every time somebody goes over a rate limit, the address and the kind of try are written to pm_ip_events,
 *     so the admin panel can show "suspicious addresses" with a Block button. Nothing is blocked automatically
 *     (many students share one address on a college Wi-Fi or a phone network; a wrong automatic block would
 *     lock them all out).
 * The tables are created by this file.
 */

if (!function_exists('pm_ip_tables')) {
    function pm_ip_tables(mysqli $conn): void {
        static $done = false;
        if ($done) return;
        $conn->query('CREATE TABLE IF NOT EXISTS pm_blocked_ips (
            ip VARCHAR(45) NOT NULL PRIMARY KEY,
            reason VARCHAR(200) NOT NULL DEFAULT \'\',
            blocked_by VARCHAR(190) NOT NULL DEFAULT \'\',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            expires_at DATETIME NULL DEFAULT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci');
        $conn->query('CREATE TABLE IF NOT EXISTS pm_ip_events (
            ip VARCHAR(45) NOT NULL,
            kind VARCHAR(40) NOT NULL,
            hits INT NOT NULL DEFAULT 1,
            first_seen DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            last_seen DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (ip, kind)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci');
        $done = true;
    }
}

if (!function_exists('pm_ip_is_blocked')) {
    function pm_ip_is_blocked(mysqli $conn, string $ip): bool {
        if ($ip === '' || $ip === 'unknown') return false;
        pm_ip_tables($conn);
        $stmt = $conn->prepare('SELECT 1 FROM pm_blocked_ips WHERE ip = ? AND (expires_at IS NULL OR expires_at > NOW()) LIMIT 1');
        if (!$stmt) return false;                // never lock everybody out because of a database hiccup
        $stmt->bind_param('s', $ip);
        $stmt->execute();
        $stmt->store_result();
        $blocked = $stmt->num_rows > 0;
        $stmt->close();
        return $blocked;
    }
}

/** Stops the request with "403 Access blocked" when the caller's address is blocked (an admin always passes). */
if (!function_exists('pm_ip_guard_or_die')) {
    function pm_ip_guard_or_die(mysqli $conn): void {
        static $checked = false;
        if ($checked) return;
        $checked = true;
        if (!function_exists('pm_client_ip')) require_once __DIR__ . '/pm_rate_limit.php';
        $ip = pm_client_ip();
        if (!pm_ip_is_blocked($conn, $ip)) return;

        // an admin is never locked out of his own panel
        require_once __DIR__ . '/pm_cors.php';
        require_once __DIR__ . '/pm_session_token.php';
        require_once __DIR__ . '/pm_admin_emails.php';
        $email = strtolower(trim(pm_session_email(pm_request_token([]))));
        if ($email !== '' && in_array($email, pm_admin_email_list(), true)) return;

        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['status' => 'error', 'message' => 'Access blocked.', 'code' => 'ip_blocked']);
        exit;
    }
}

/** Writes down that this address went over a limit (kind: "login-ip", "order", ...). */
if (!function_exists('pm_ip_event')) {
    function pm_ip_event(mysqli $conn, string $ip, string $kind): void {
        if ($ip === '' || $ip === 'unknown') return;
        pm_ip_tables($conn);
        $kind = substr(preg_replace('/[^a-z0-9_\-]/i', '', $kind), 0, 40);
        $stmt = $conn->prepare('INSERT INTO pm_ip_events (ip, kind) VALUES (?, ?) ON DUPLICATE KEY UPDATE hits = hits + 1, last_seen = NOW()');
        if (!$stmt) return;
        $stmt->bind_param('ss', $ip, $kind);
        $stmt->execute();
        $stmt->close();
    }
}
