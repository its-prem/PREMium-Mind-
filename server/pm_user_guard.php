<?php
/**
 * Upload to Hostinger premind/ as: pm_user_guard.php
 *
 * Who is logged in, and blocking a student's account.
 *
 *   pm_user_check($conn, $email)   call it after the login token has been checked. It
 *       1. refuses a BLOCKED student (403 "This account is blocked", code user_blocked) - an admin is never refused;
 *       2. notes that the student is online: last seen, IP address, device, and the list of addresses used
 *          (written at most once every 3 minutes per student, so it adds almost no load).
 *   pm_user_is_blocked($conn, $email)   plain yes / no (used by the login files, which must refuse before a token exists)
 *
 * The admin panel (Students) reads these tables and blocks / unblocks. Tables are created by this file:
 *   pm_user_activity (one row per student), pm_user_ips (one row per student and address), pm_blocked_users.
 */

if (!function_exists('pm_user_tables')) {
    function pm_user_tables(mysqli $conn): void {
        static $done = false;
        if ($done) return;
        $conn->query('CREATE TABLE IF NOT EXISTS pm_user_activity (
            email VARCHAR(190) NOT NULL PRIMARY KEY,
            first_seen DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            last_seen DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            last_ip VARCHAR(45) NOT NULL DEFAULT \'\',
            last_ua VARCHAR(200) NOT NULL DEFAULT \'\',
            seen_count INT NOT NULL DEFAULT 1
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        $conn->query('CREATE TABLE IF NOT EXISTS pm_user_ips (
            email VARCHAR(190) NOT NULL,
            ip VARCHAR(45) NOT NULL,
            hits INT NOT NULL DEFAULT 1,
            first_seen DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            last_seen DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (email, ip)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        $conn->query('CREATE TABLE IF NOT EXISTS pm_blocked_users (
            email VARCHAR(190) NOT NULL PRIMARY KEY,
            reason VARCHAR(200) NOT NULL DEFAULT \'\',
            blocked_by VARCHAR(190) NOT NULL DEFAULT \'\',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        $done = true;
    }
}

if (!function_exists('pm_user_is_blocked')) {
    function pm_user_is_blocked(mysqli $conn, string $email): bool {
        $email = strtolower(trim($email));
        if ($email === '') return false;
        pm_user_tables($conn);
        $stmt = $conn->prepare('SELECT 1 FROM pm_blocked_users WHERE email = ? LIMIT 1');
        if (!$stmt) return false;                 // never lock everybody out because of a database hiccup
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $stmt->store_result();
        $blocked = $stmt->num_rows > 0;
        $stmt->close();
        return $blocked;
    }
}

if (!function_exists('pm_user_touch')) {
    /** Notes that the student is online now (cheap: usually only one small read). */
    function pm_user_touch(mysqli $conn, string $email): void {
        if (!function_exists('pm_client_ip')) require_once __DIR__ . '/pm_rate_limit.php';
        pm_user_tables($conn);
        $ip = pm_client_ip();
        $ua = substr(preg_replace('/[\r\n]+/', ' ', (string)($_SERVER['HTTP_USER_AGENT'] ?? '')), 0, 200);

        $stmt = $conn->prepare('SELECT last_ip, TIMESTAMPDIFF(SECOND, last_seen, NOW()) AS age FROM pm_user_activity WHERE email = ? LIMIT 1');
        if (!$stmt) return;
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($row && (int)$row['age'] < 180 && $row['last_ip'] === $ip) return;   // seen a moment ago from the same address

        $st = $conn->prepare('INSERT INTO pm_user_activity (email, last_ip, last_ua) VALUES (?, ?, ?)
                              ON DUPLICATE KEY UPDATE last_seen = NOW(), last_ip = VALUES(last_ip), last_ua = VALUES(last_ua), seen_count = seen_count + 1');
        if ($st) {
            $st->bind_param('sss', $email, $ip, $ua);
            $st->execute();
            $st->close();
        }
        if ($ip !== 'unknown') {
            $st = $conn->prepare('INSERT INTO pm_user_ips (email, ip) VALUES (?, ?) ON DUPLICATE KEY UPDATE hits = hits + 1, last_seen = NOW()');
            if ($st) {
                $st->bind_param('ss', $email, $ip);
                $st->execute();
                $st->close();
            }
        }
    }
}

if (!function_exists('pm_user_check')) {
    function pm_user_check(mysqli $conn, string $email): void {
        $email = strtolower(trim($email));
        if ($email === '') return;
        if (pm_user_is_blocked($conn, $email)) {
            require_once __DIR__ . '/pm_admin_emails.php';
            if (!in_array($email, pm_admin_email_list(), true)) {   // an admin is never refused
                http_response_code(403);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['status' => 'error', 'message' => 'This account is blocked. Please contact support.', 'code' => 'user_blocked']);
                exit;
            }
        }
        pm_user_touch($conn, $email);
    }
}
