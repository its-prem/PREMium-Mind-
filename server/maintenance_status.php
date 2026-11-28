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
require_once __DIR__ . '/pm_rate_limit.php';      // pm_client_ip()
require_once __DIR__ . '/pm_ip_guard.php';
require_once __DIR__ . '/pm_user_guard.php';
require_once __DIR__ . '/pm_session_token.php';
require_once __DIR__ . '/pm_admin_emails.php';

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
// Is THIS visitor blocked by the admin? 'ip' = the address, 'user' = the logged-in student. The website then shows a
// "blocked" page instead of the site. An admin (valid admin token) is never reported as blocked.
$blocked = '';
$who = strtolower(trim(pm_session_email(pm_request_token([]))));
$isAdmin = $who !== '' && in_array($who, pm_admin_email_list(), true);
if (!$isAdmin) {
    if (pm_ip_is_blocked($conn, pm_client_ip())) $blocked = 'ip';
    elseif ($who !== '' && pm_user_is_blocked($conn, $who)) $blocked = 'user';
}
// home page photo + ant / butterfly switches (Admin -> Site look)
$siteCfg = pm_setting_get($conn, 'site');
$siteCfg = is_array($siteCfg) ? $siteCfg : [];
$site = [
    'heroImage' => (string)($siteCfg['heroImage'] ?? ''),
    'ant'       => !isset($siteCfg['ant']) || !empty($siteCfg['ant']),
    'butterfly' => !isset($siteCfg['butterfly']) || !empty($siteCfg['butterfly']),
    'forceAppUpdate' => !empty($siteCfg['forceAppUpdate']),
];
echo json_encode(['status' => 'success', 'config' => $cfg, 'blocked' => $blocked, 'site' => $site]);
