<?php
/**
 * Upload to Hostinger premind/ as: pm_settings.php
 *
 * A tiny key/value store in the database (table pm_settings, created by itself) for settings the admin
 * changes from the panel, like the maintenance switch.
 *
 *   pm_setting_get($conn, 'maintenance')              -> array or null
 *   pm_setting_set($conn, 'maintenance', ['enabled' => true, ...])
 */

if (!function_exists('pm_settings_ensure')) {
    function pm_settings_ensure(mysqli $conn): void {
        static $done = false;
        if ($done) return;
        $conn->query('CREATE TABLE IF NOT EXISTS pm_settings (
            k VARCHAR(64) NOT NULL PRIMARY KEY,
            v MEDIUMTEXT NOT NULL,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        $done = true;
    }
}

if (!function_exists('pm_setting_get')) {
    function pm_setting_get(mysqli $conn, string $key): ?array {
        pm_settings_ensure($conn);
        $stmt = $conn->prepare('SELECT v FROM pm_settings WHERE k = ? LIMIT 1');
        if (!$stmt) return null;
        $stmt->bind_param('s', $key);
        $stmt->execute();
        $stmt->bind_result($v);
        $found = $stmt->fetch();
        $stmt->close();
        if (!$found) return null;
        $data = json_decode((string)$v, true);
        return is_array($data) ? $data : null;
    }
}

if (!function_exists('pm_setting_set')) {
    function pm_setting_set(mysqli $conn, string $key, array $value): bool {
        pm_settings_ensure($conn);
        $json = json_encode($value, JSON_UNESCAPED_UNICODE);
        $stmt = $conn->prepare('INSERT INTO pm_settings (k, v) VALUES (?, ?) ON DUPLICATE KEY UPDATE v = VALUES(v)');
        if (!$stmt) return false;
        $stmt->bind_param('ss', $key, $json);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }
}
