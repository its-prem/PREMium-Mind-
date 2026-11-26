<?php
/**
 * Lets the admin open an uploaded PDF (the uploads/pdfs folder is blocked from the web on purpose).
 *   GET admin/file.php?path=uploads/pdfs/123_abc.pdf     (with the admin login header)
 * Only PDFs inside uploads/pdfs/ can be read, nothing else on the server.
 */
require_once __DIR__ . '/_boot.php';
pm_admin_require();

$f = str_replace('\\', '/', trim((string)pm_in('path')));
if (preg_match('#^https?://#i', $f)) {
    $f = ltrim((string)(parse_url($f, PHP_URL_PATH) ?: ''), '/');
}
$f = ltrim($f, '/');
if ($f !== '' && strpos($f, '/') === false) $f = 'uploads/pdfs/' . $f;   // only a file name was stored
if (stripos($f, 'premind/') === 0) $f = substr($f, strlen('premind/'));

if (strpos($f, 'uploads/pdfs/') !== 0 || strpos($f, '..') !== false || !preg_match('/\.pdf$/i', $f)) {
    pm_admin_fail('That is not an uploaded PDF.');
}

$base = realpath(__DIR__ . '/../uploads/pdfs');
$full = realpath(__DIR__ . '/../' . $f);
if ($base === false || $full === false || strpos($full, $base . DIRECTORY_SEPARATOR) !== 0 || !is_file($full) || !is_readable($full)) {
    pm_admin_fail('PDF file not found on the server.', 404);
}

header_remove('Content-Type');
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . preg_replace('/[^A-Za-z0-9._-]/', '_', basename($full)) . '"');
header('Content-Length: ' . filesize($full));
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');
readfile($full);
exit;
