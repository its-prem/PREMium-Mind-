<?php
/**
 * Maintenance mode switch (admin only).
 *   GET  admin/maintenance.php   -> the saved setting (config is null until it was saved once)
 *   POST admin/maintenance.php   {enabled, title, message, returnAt, whatsapp}   save
 * The public website reads it through ../maintenance_status.php.
 */
require_once __DIR__ . '/_boot.php';
require_once __DIR__ . '/../pm_settings.php';
pm_admin_require();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cur = pm_setting_get($conn, 'maintenance') ?? [];
    $new = [
        'enabled'  => pm_in_flag('enabled') === 1,
        'title'    => mb_substr(trim((string)pm_in('title', $cur['title'] ?? '')), 0, 120),
        'message'  => mb_substr(trim((string)pm_in('message', $cur['message'] ?? '')), 0, 600),
        'returnAt' => mb_substr(trim((string)pm_in('returnAt', $cur['returnAt'] ?? '')), 0, 80),
        'whatsapp' => preg_replace('/\D/', '', (string)pm_in('whatsapp', $cur['whatsapp'] ?? '')),
    ];
    // pages closed one by one, e.g. ["/tools", "/mock-tests"]
    $pagesIn = pm_in('pages', $cur['pages'] ?? []);
    if (is_string($pagesIn)) $pagesIn = json_decode($pagesIn, true);
    $pages = [];
    foreach ((is_array($pagesIn) ? $pagesIn : []) as $p) {
        $p = (string)$p;
        if (preg_match('~^/[a-z0-9_/-]{0,60}$~i', $p) && !in_array($p, $pages, true)) $pages[] = $p;
        if (count($pages) >= 40) break;
    }
    $new['pages'] = $pages;
    if (strlen($new['whatsapp']) > 15) pm_admin_fail('WhatsApp number is too long.');
    if (!pm_setting_set($conn, 'maintenance', $new)) pm_admin_fail('Could not save the setting.', 500);
    pm_admin_ok(['config' => $new]);
}

pm_admin_ok(['config' => pm_setting_get($conn, 'maintenance')]);
