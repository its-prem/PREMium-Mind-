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

$filePath = __DIR__ . '/downloads/' . $fileName;

if (!is_file($filePath) || !is_readable($filePath)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'APK not found. Upload file to: public_html/premind/downloads/' . $fileName;
    exit;
}

$size = filesize($filePath);
if ($size === false) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Could not read APK file size.';
    exit;
}

header('Content-Type: application/vnd.android.package-archive');
header('Content-Disposition: attachment; filename="' . $fileName . '"');
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
