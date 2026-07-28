<?php
/**
 * Shared secrets loader for PDF token / proxy.
 * Expects pm_secrets.php next to this file (Hostinger premind/).
 */
function pm_load_secrets(): array {
    static $cached = null;
    if (is_array($cached)) return $cached;

    $path = __DIR__ . '/pm_secrets.php';
    if (!is_file($path)) {
        return [];
    }
    $cfg = require $path;
    $cached = is_array($cfg) ? $cfg : [];
    return $cached;
}

function pm_pdf_hmac_secret(): string {
    $cfg = pm_load_secrets();
    $secret = isset($cfg['pdf_hmac_secret']) ? trim((string)$cfg['pdf_hmac_secret']) : '';
    if ($secret === '' || $secret === 'CHANGE_ME_TO_A_LONG_RANDOM_SECRET') {
        return '';
    }
    return $secret;
}
