<?php
/**
 * Upload to Hostinger premind/ as: pm_rate_limit.php
 *
 * Tiny "too many tries" guard used by login and OTP checks, so a bot cannot guess passwords / OTPs.
 * It keeps the counters in the database (table pm_rate_limits, created automatically).
 *
 *   if (!pm_rate_limit($conn, 'login:' . $email, 8, 900)) { ... answer "too many tries" ... }
 *     -> allows 8 tries per 15 minutes (900 s) for that key
 */

if (!function_exists('pm_client_ip')) {
    function pm_client_ip(): string {
        // Hostinger sits behind Cloudflare, which puts the visitor's address in this header.
        $ip = (string)($_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? '');
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : 'unknown';
    }
}

if (!function_exists('pm_rate_limit')) {
    /** Counts this try. Returns true while the caller is still inside the limit. */
    function pm_rate_limit(mysqli $conn, string $bucket, int $max, int $windowSec): bool {
        // a blocked IP address (Admin -> IP block) is refused here, before anything is counted
        require_once __DIR__ . '/pm_ip_guard.php';
        pm_ip_guard_or_die($conn);
        static $ready = false;
        if (!$ready) {
            $conn->query('CREATE TABLE IF NOT EXISTS pm_rate_limits (
                k CHAR(64) NOT NULL PRIMARY KEY,
                hits INT NOT NULL DEFAULT 0,
                started INT NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
            $ready = true;
        }
        $key = hash('sha256', $bucket);
        $now = time();

        $stmt = $conn->prepare(
            'INSERT INTO pm_rate_limits (k, hits, started) VALUES (?, 1, ?)
             ON DUPLICATE KEY UPDATE
               hits = IF(started < ?, 1, hits + 1),
               started = IF(started < ?, ?, started)'
        );
        if (!$stmt) return true;                 // never lock everybody out because of a database hiccup
        $old = $now - $windowSec;
        $stmt->bind_param('siiii', $key, $now, $old, $old, $now);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare('SELECT hits FROM pm_rate_limits WHERE k = ?');
        if (!$stmt) return true;
        $stmt->bind_param('s', $key);
        $stmt->execute();
        $stmt->bind_result($hits);
        $stmt->fetch();
        $stmt->close();

        // tidy up old rows now and then
        if (random_int(1, 50) === 1) {
            $cut = $now - 86400;
            $conn->query('DELETE FROM pm_rate_limits WHERE started < ' . (int)$cut);
        }
        if ((int)$hits > $max) {
            // note the address in the "suspicious" list of the admin panel (it is never blocked automatically)
            pm_ip_event($conn, pm_client_ip(), explode(':', $bucket)[0]);
            return false;
        }
        return true;
    }
}
