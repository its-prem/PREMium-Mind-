<?php
/**
 * Upload to Hostinger premind/ as: maintenance_status.php
 *
 * PUBLIC, read-only. The website asks this once a minute whether maintenance mode is on.
 * The admin panel (admin/maintenance.php) writes the answer. When nobody has saved anything yet, "config" is
 * null and the website falls back to its own public/maintenance.json.
 */
require_once __DIR__ . '/pm_cors.php';
pm_cors_json(['GET'], true);

include __DIR__ . '/db_connect.php';
require_once __DIR__ . '/pm_settings.php';

$cfg = pm_setting_get($conn, 'maintenance');
if ($cfg !== null) {
    // only the fields the website needs
    $cfg = [
        'enabled'  => !empty($cfg['enabled']),
        'title'    => (string)($cfg['title'] ?? ''),
        'message'  => (string)($cfg['message'] ?? ''),
        'returnAt' => (string)($cfg['returnAt'] ?? ''),
        'whatsapp' => (string)($cfg['whatsapp'] ?? ''),
        'pages'    => array_values(array_filter((array)($cfg['pages'] ?? []), 'is_string')),
    ];
}
echo json_encode(['status' => 'success', 'config' => $cfg]);
