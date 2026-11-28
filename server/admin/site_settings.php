<?php
/**
 * Upload to Hostinger premind/admin/ as: site_settings.php   (admin only)
 * The home page photo and the ant / butterfly switches.
 *   GET   admin/site_settings.php                        -> { site: { heroImage, ant, butterfly } }
 *   POST  admin/site_settings.php  (multipart or JSON)
 *           ant=1|0, butterfly=1|0        switch the little animals on / off
 *           hero_file = an image          replace the home page photo (jpg, png, webp, max 5 MB)
 *           hero_reset = 1                go back to the photo that is built into the website
 * The public website reads it through ../maintenance_status.php ("site").
 */
require_once __DIR__ . '/_boot.php';
require_once __DIR__ . '/../pm_settings.php';
pm_admin_require();

function pm_site_defaults(): array {
    return ['heroImage' => '', 'ant' => true, 'butterfly' => true];
}
function pm_site_load(mysqli $conn): array {
    $cur = pm_setting_get($conn, 'site');
    return array_merge(pm_site_defaults(), is_array($cur) ? $cur : []);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $site = pm_site_load($conn);
    if (isset($_REQUEST['ant']) || pm_in('ant', null) !== null)             $site['ant'] = pm_in_flag('ant') === 1;
    if (isset($_REQUEST['butterfly']) || pm_in('butterfly', null) !== null) $site['butterfly'] = pm_in_flag('butterfly') === 1;

    $dir = __DIR__ . '/../uploads/thumbnails/';
    $removeOld = function () use (&$site, $dir) {
        $old = (string)($site['heroImage'] ?? '');
        if ($old !== '' && preg_match('~/uploads/thumbnails/(hero_[A-Za-z0-9_.\-]+)$~', $old, $m) && is_file($dir . $m[1])) @unlink($dir . $m[1]);
    };

    if (pm_in_flag('hero_reset') === 1) {
        $removeOld();
        $site['heroImage'] = '';
    }

    if (!empty($_FILES['hero_file']) && $_FILES['hero_file']['error'] !== UPLOAD_ERR_NO_FILE) {
        $f = $_FILES['hero_file'];
        if ($f['error'] !== UPLOAD_ERR_OK) pm_admin_fail('Upload failed (code ' . (int)$f['error'] . ').');
        if ($f['size'] > 5 * 1048576) pm_admin_fail('The photo is too big (max 5 MB).');
        $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) pm_admin_fail('Only jpg, png or webp photos are allowed.');
        $info = @getimagesize($f['tmp_name']);
        if ($info === false || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) pm_admin_fail('That file is not a real photo.');
        if (!is_dir($dir) && !@mkdir($dir, 0755, true)) pm_admin_fail('Could not create the upload folder.', 500);
        $name = 'hero_' . time() . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
        if (!move_uploaded_file($f['tmp_name'], $dir . $name)) pm_admin_fail('Could not save the photo.', 500);
        $removeOld();
        $host = (string)($_SERVER['HTTP_HOST'] ?? 'premind.diplomawallah.in');
        $site['heroImage'] = 'https://' . $host . '/uploads/thumbnails/' . $name;
    }

    if (!pm_setting_set($conn, 'site', $site)) pm_admin_fail('Could not save the setting.', 500);
    pm_admin_ok(['site' => $site]);
}

pm_admin_ok(['site' => pm_site_load($conn)]);
