<?php
/**
 * Serves the Android APK via PHP (Hostinger often blocks direct .apk URLs).
 * Upload to: public_html/premind/download_apk.php
 * Public URL: https://premind.diplomawallah.in/download_apk.php
 */
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$metaPath = __DIR__ . '/app-version.json';
$fileName = 'PREMium-Mind.apk';

if (is_file($metaPath)) {
    $meta = json_decode((string)file_get_contents($metaPath), true);
    if (is_array($meta) && !empty($meta['apkFileName'])) {
        $fileName = basename((string)$meta['apkFileName']);
    }
}

// Optional override from client (?filename=PREMium-Mind.apk) — keep .apk only
$reqName = '';
if (!empty($_GET['filename'])) $reqName = (string)$_GET['filename'];
elseif (!empty($_GET['name'])) $reqName = (string)$_GET['name'];
if ($reqName !== '') {
    $reqName = basename($reqName);
    $reqName = preg_replace('/[^\w.\-]+/', '_', $reqName);
    if (preg_match('/\.pdf$/i', $reqName)) {
        $reqName = preg_replace('/\.pdf$/i', '.apk', $reqName);
    }
    if (!preg_match('/\.apk$/i', $reqName)) {
        $reqName = preg_replace('/\.[a-z0-9]+$/i', '', $reqName) . '.apk';
    }
    if ($reqName !== '' && $reqName !== '.apk') {
        $fileName = $reqName;
    }
}

if (!preg_match('/\.apk$/i', $fileName)) {
    $fileName = 'PREMium-Mind.apk';
}

// Physical file on disk (always the real APK asset)
$diskName = 'PREMium-Mind.apk';
if (is_file($metaPath)) {
    $meta2 = json_decode((string)@file_get_contents($metaPath), true);
    if (is_array($meta2) && !empty($meta2['apkFileName'])) {
        $candidate = basename((string)$meta2['apkFileName']);
        if (is_file(__DIR__ . '/downloads/' . $candidate)) {
            $diskName = $candidate;
        }
    }
}

$filePath = __DIR__ . '/downloads/' . $diskName;
if (!is_file($filePath) || !is_readable($filePath)) {
    // fallback: any .apk in downloads/
    $found = glob(__DIR__ . '/downloads/*.apk');
    if (!empty($found[0]) && is_readable($found[0])) {
        $filePath = $found[0];
        if ($fileName === 'PREMium-Mind.apk') {
            $fileName = basename($filePath);
        }
    }
}

if (!is_file($filePath) || !is_readable($filePath)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'APK not found. Upload file to: public_html/premind/downloads/' . $diskName;
    exit;
}

$size = filesize($filePath);
if ($size === false) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Could not read APK file size.';
    exit;
}

// Force correct APK headers so Android does not save as app.pdf
header('Content-Type: application/vnd.android.package-archive');
header('Content-Disposition: attachment; filename="' . $fileName . '"; filename*=UTF-8\'\'' . rawurlencode($fileName));
header('Content-Length: ' . $size);
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

$fp = fopen($filePath, 'rb');
if ($fp === false) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Could not open APK file.';
    exit;
}

while (!feof($fp)) {
    echo fread($fp, 8192);
    flush();
}
fclose($fp);
exit;
