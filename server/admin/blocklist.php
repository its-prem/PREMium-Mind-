<?php
/**
 * Blocking bad IP addresses (admin only).
 *   GET  admin/blocklist.php                                  -> { you, blocked:[...], suspicious:[...] }
 *   POST admin/blocklist.php {action:'block', ip, reason, hours}   hours 0 = for ever
 *   POST admin/blocklist.php {action:'unblock', ip}
 *   POST admin/blocklist.php {action:'forget', ip}            remove an address from the suspicious list
 * The check itself is in ../pm_ip_guard.php (called by pm_rate_limit and by admin/_boot.php).
 */
require_once __DIR__ . '/_boot.php';
require_once __DIR__ . '/../pm_ip_guard.php';
$admin = pm_admin_require();
pm_ip_tables($conn);
$you = pm_client_ip();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)pm_in('action', '');
    $ip = trim((string)pm_in('ip', ''));
    if (!filter_var($ip, FILTER_VALIDATE_IP)) pm_admin_fail('That is not a valid IP address.');

    if ($action === 'block') {
        if ($ip === $you) pm_admin_fail('This is your own address. You would lock yourself out of the website (the admin panel stays open for you).');
        $reason = mb_substr(trim((string)pm_in('reason', '')), 0, 200);
        $hours = max(0, min(24 * 365, (int)pm_in('hours', 0)));
        $stmt = $conn->prepare('REPLACE INTO pm_blocked_ips (ip, reason, blocked_by, expires_at) VALUES (?, ?, ?, ' . ($hours > 0 ? 'DATE_ADD(NOW(), INTERVAL ? HOUR)' : 'NULL') . ')');
        if ($hours > 0) $stmt->bind_param('sssi', $ip, $reason, $admin, $hours);
        else $stmt->bind_param('sss', $ip, $reason, $admin);
        if (!$stmt->execute()) pm_admin_fail('Could not block.', 500);
        $stmt->close();
    } elseif ($action === 'unblock') {
        $stmt = $conn->prepare('DELETE FROM pm_blocked_ips WHERE ip = ?');
        $stmt->bind_param('s', $ip);
        $stmt->execute();
        $stmt->close();
    } elseif ($action === 'forget') {
        $stmt = $conn->prepare('DELETE FROM pm_ip_events WHERE ip = ?');
        $stmt->bind_param('s', $ip);
        $stmt->execute();
        $stmt->close();
    } else {
        pm_admin_fail('Unknown action.');
    }
}

$blocked = pm_rows($conn->query('SELECT ip, reason, blocked_by, created_at, expires_at FROM pm_blocked_ips WHERE expires_at IS NULL OR expires_at > NOW() ORDER BY created_at DESC LIMIT 500'));
$suspicious = pm_rows($conn->query(
    'SELECT e.ip, SUM(e.hits) AS hits, GROUP_CONCAT(CONCAT(e.kind, " x", e.hits) SEPARATOR ", ") AS what, MAX(e.last_seen) AS last_seen,
            (SELECT COUNT(*) FROM pm_blocked_ips b WHERE b.ip = e.ip AND (b.expires_at IS NULL OR b.expires_at > NOW())) AS is_blocked
       FROM pm_ip_events e
      WHERE e.last_seen > DATE_SUB(NOW(), INTERVAL 14 DAY)
      GROUP BY e.ip ORDER BY hits DESC, last_seen DESC LIMIT 100'
));
pm_admin_ok(['you' => $you, 'blocked' => $blocked, 'suspicious' => $suspicious]);
