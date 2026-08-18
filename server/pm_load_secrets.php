<?php
/**
 * Shared secrets loader for PDF token / proxy.
 * Prefer pm_secrets.php; fall back to legacy secret so PDFs keep working
 * if Hostinger upload of pm_secrets.php was skipped.
 */
function pm_load_secrets(): array {
    static $cached = null;
    if (is_array($cached)) return $cached;

    $path = __DIR__ . '/pm_secrets.php';
    if (!is_file($path)) {
        $cached = [];
        return $cached;
    }
    $cfg = require $path;
    $cached = is_array($cfg) ? $cfg : [];
    return $cached;
}

/** Primary signing secret (file first, else legacy). */
function pm_pdf_hmac_secret(): string {
    $cfg = pm_load_secrets();
    $secret = isset($cfg['pdf_hmac_secret']) ? trim((string)$cfg['pdf_hmac_secret']) : '';
    if ($secret !== '' && $secret !== 'CHANGE_ME_TO_A_LONG_RANDOM_SECRET') {
        return $secret;
    }
    // Legacy fallback (pre-rotation) — keeps PDFs working without pm_secrets.php
    return 'PREM_MIND_SECURE_2026';
}

/** All secrets accepted when verifying tokens (migration-safe). */
function pm_pdf_hmac_secrets(): array {
    $list = [];
    $primary = pm_pdf_hmac_secret();
    if ($primary !== '') $list[] = $primary;

    $legacy = 'PREM_MIND_SECURE_2026';
    if (!in_array($legacy, $list, true)) {
        $list[] = $legacy;
    }

    $cfg = pm_load_secrets();
    $fileSecret = isset($cfg['pdf_hmac_secret']) ? trim((string)$cfg['pdf_hmac_secret']) : '';
    if ($fileSecret !== '' && $fileSecret !== 'CHANGE_ME_TO_A_LONG_RANDOM_SECRET'
        && !in_array($fileSecret, $list, true)) {
        $list[] = $fileSecret;
    }

    return $list;
}

/**
 * Secret shared with the Android app for proving a request genuinely came
 * from it (see APP_ONLY_COURSES_SETUP.txt). Empty string if not configured
 * yet — callers must treat that as "app signature not available" rather
 * than a valid empty secret.
 */
function pm_app_shared_secret(): string {
    $cfg = pm_load_secrets();
    $secret = isset($cfg['app_shared_secret']) ? trim((string)$cfg['app_shared_secret']) : '';
    if ($secret === '' || $secret === 'CHANGE_ME_TO_ANOTHER_LONG_RANDOM_SECRET') {
        return '';
    }
    return $secret;
}
