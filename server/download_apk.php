<?php
/**
 * Upload to Hostinger premind/ as: download_apk.php   (replaces the old one)
 *
 * Gives the Android app (APK) to the phone.   https://premind.diplomawallah.in/download_apk.php
 *
 * What is new in this version:
 *   - resumable download (HTTP Range): if the network breaks, the phone continues instead of starting again;
 *   - always the NEWEST apk: the file named in app-version.json ("apkFileName") if it is in downloads/,
 *     otherwise the most recently uploaded *.apk of downloads/ (so a new version works even if you forget the json);
 *   - the download name is always a clean  PREMium-Mind-<version>.apk  (never .pdf, no odd characters);
 *   - HEAD requests, ETag / Last-Modified, a proper Content-Length, and no output buffering for big files;
 *   - a blocked IP (Admin -> IP block) cannot download; the file name cannot escape the downloads/ folder.
 *
 * Where the file lives:  public_html/premind/downloads/PREMium-Mind.apk   (upload the new apk there, keep the name
 * or change "apkFileName" + "latestVersion" in app-version.json).
 */

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, HEAD, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Range');
header('Access-Control-Expose-Headers: Content-Length, Content-Range, Accept-Ranges, Content-Disposition');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method === 'OPTIONS') { http_response_code(204); exit; }
if ($method !== 'GET' && $method !== 'HEAD') { http_response_code(405); header('Allow: GET, HEAD, OPTIONS'); exit; }

function pm_apk_text_fail(int $http, string $msg): void {
    http_response_code($http);
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: no-store');
    echo $msg;
    exit;
}

/* ---- a blocked address cannot download (skipped when db_connect.php / pm_ip_guard.php are not there) ---- */
try {
    if (is_file(__DIR__ . '/pm_ip_guard.php') && is_file(__DIR__ . '/db_connect.php')) {
        ob_start();
        include __DIR__ . '/db_connect.php';
        ob_end_clean();
        if (isset($conn) && $conn instanceof mysqli) {
            require_once __DIR__ . '/pm_ip_guard.php';
            pm_ip_guard_or_die($conn);
        }
    }
} catch (Throwable $e) { /* never stop the download because of the guard */ }

$dir = __DIR__ . '/downloads';
$metaPath = __DIR__ . '/app-version.json';
$meta = [];
if (is_file($metaPath)) {
    $m = json_decode((string)@file_get_contents($metaPath), true);
    if (is_array($m)) $meta = $m;
}

/* ---- which file ---- */
$path = '';
$want = isset($meta['apkFileName']) ? basename((string)$meta['apkFileName']) : '';
if ($want !== '' && preg_match('/^[\w.\-]+\.apk$/i', $want) && is_file($dir . '/' . $want)) {
    $path = $dir . '/' . $want;
}
if ($path === '') {
    $newest = 0;
    foreach ((array)glob($dir . '/*.apk') as $f) {
        if (is_file($f) && is_readable($f) && filemtime($f) >= $newest) { $newest = filemtime($f); $path = $f; }
    }
}
if ($path === '' || !is_readable($path)) {
    pm_apk_text_fail(404, 'The app file is not uploaded yet. Upload the apk to: public_html/premind/downloads/PREMium-Mind.apk');
}
$real = realpath($path);
$realDir = realpath($dir);
if ($real === false || $realDir === false || strpos($real, $realDir . DIRECTORY_SEPARATOR) !== 0) {
    pm_apk_text_fail(404, 'App file not found.');
}

$size  = (int)filesize($real);
$mtime = (int)filemtime($real);
if ($size <= 0) pm_apk_text_fail(500, 'The app file is empty. Upload it again.');

/* ---- the name the phone saves it as ---- */
$ver = preg_replace('/[^0-9A-Za-z.\-]+/', '', (string)($meta['latestVersion'] ?? ''));
$name = 'PREMium-Mind' . ($ver !== '' ? '-' . $ver : '') . '.apk';
if (!empty($_GET['filename']) || !empty($_GET['name'])) {          // the website sends ?filename=...
    $req = preg_replace('/[^\w.\-]+/', '_', basename((string)($_GET['filename'] ?? $_GET['name'])));
    $req = preg_replace('/\.[A-Za-z0-9]+$/', '', $req);            // drop any extension (.pdf, .zip ...)
    if ($req !== '' && $req !== '_') $name = $req . '.apk';
}

/* ---- headers ---- */
$etag = '"' . substr(md5($real . '|' . $size . '|' . $mtime), 0, 20) . '"';
header('Content-Type: application/vnd.android.package-archive');
header('Content-Disposition: attachment; filename="' . $name . '"; filename*=UTF-8\'\'' . rawurlencode($name));
header('Accept-Ranges: bytes');
header('ETag: ' . $etag);
header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $mtime) . ' GMT');
header('Cache-Control: no-cache, must-revalidate');
header('X-Content-Type-Options: nosniff');

/* ---- which bytes: the whole file, or the part the phone asks for (resume) ---- */
$start = 0;
$end = $size - 1;
$partial = false;
$range = $_SERVER['HTTP_RANGE'] ?? '';
$ifRange = $_SERVER['HTTP_IF_RANGE'] ?? '';
if ($range !== '' && ($ifRange === '' || $ifRange === $etag)) {
    if (!preg_match('/^bytes=(\d*)-(\d*)$/', trim($range), $mm) || ($mm[1] === '' && $mm[2] === '')) {
        header('Content-Range: bytes */' . $size);
        pm_apk_text_fail(416, 'Bad range.');
    }
    if ($mm[1] === '') {                       // the last N bytes
        $start = max(0, $size - (int)$mm[2]);
    } else {
        $start = (int)$mm[1];
        if ($mm[2] !== '') $end = min($end, (int)$mm[2]);
    }
    if ($start > $end || $start >= $size) {
        header('Content-Range: bytes */' . $size);
        pm_apk_text_fail(416, 'Range not satisfiable.');
    }
    $partial = true;
}

$length = $end - $start + 1;
if ($partial) {
    http_response_code(206);
    header('Content-Range: bytes ' . $start . '-' . $end . '/' . $size);
}
header('Content-Length: ' . $length);
if ($method === 'HEAD') exit;

/* ---- send it ---- */
@set_time_limit(0);
@ignore_user_abort(false);
while (ob_get_level() > 0) @ob_end_clean();
$fp = fopen($real, 'rb');
if ($fp === false) pm_apk_text_fail(500, 'Could not open the app file.');
if ($start > 0) fseek($fp, $start);
$left = $length;
while ($left > 0 && !feof($fp) && !connection_aborted()) {
    $chunk = fread($fp, (int)min(65536, $left));
    if ($chunk === false || $chunk === '') break;
    echo $chunk;
    $left -= strlen($chunk);
    flush();
}
fclose($fp);
exit;
