<?php
/**
 * Public app version JSON for website / Netlify.
 * Upload to: public_html/premind/get_app_version.php
 */
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$path = __DIR__ . '/app-version.json';
if (!file_exists($path)) {
    echo json_encode([
        'appName' => 'PREMium Mind',
        'packageId' => 'com.premiummind.app',
        'latestVersion' => '1.0.0',
        'versionCode' => 1,
        'releaseDate' => date('Y-m-d'),
        'minAndroid' => '8.0+',
        'apkFileName' => 'PREMium-Mind.apk',
        'apkUrl' => 'https://premind.diplomawallah.in/download_apk.php',
        'changelog' => [
            'Login + Google Sign-In',
            'Cashfree UPI (GPay / PhonePe)',
            'Secure PDF reader',
            'App-only courses support'
        ],
        'forceUpdate' => false,
        'deepLinkScheme' => 'premiummind'
    ], JSON_UNESCAPED_SLASHES);
    exit;
}

readfile($path);
