<?php
/**
 * Shared secrets loader for PDF token / proxy.
 * Order of preference: .env (server/.env, see .env.example) → pm_secrets.php
 * → legacy hardcoded fallback, so PDFs keep working no matter which of
 * these has actually been uploaded to Hostinger.
 */

require_once __DIR__ . '/pm_load_env.php';
pm_load_dotenv(__DIR__ . '/.env');

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

/** Primary signing secret: .env → pm_secrets.php → legacy hardcoded fallback. */
function pm_pdf_hmac_secret(): string {
    $envSecret = pm_env('PDF_HMAC_SECRET');
    if ($envSecret !== '' && $envSecret !== 'CHANGE_ME_TO_A_LONG_RANDOM_SECRET') {
        return $envSecret;
    }

    $cfg = pm_load_secrets();
    $secret = isset($cfg['pdf_hmac_secret']) ? trim((string)$cfg['pdf_hmac_secret']) : '';
    if ($secret !== '' && $secret !== 'CHANGE_ME_TO_A_LONG_RANDOM_SECRET') {
        return $secret;
    }
    // Legacy fallback (pre-rotation) — keeps PDFs working without .env/pm_secrets.php
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
 * from it (see APP_ONLY_COURSES_SETUP.txt). Checks .env first, then
 * pm_secrets.php. Empty string if not configured anywhere — callers must
 * treat that as "app signature not available" rather than a valid secret.
 */
function pm_app_shared_secret(): string {
    $envSecret = pm_env('APP_SHARED_SECRET');
    if ($envSecret !== '' && $envSecret !== 'CHANGE_ME_TO_ANOTHER_LONG_RANDOM_SECRET') {
        return $envSecret;
    }

    $cfg = pm_load_secrets();
    $secret = isset($cfg['app_shared_secret']) ? trim((string)$cfg['app_shared_secret']) : '';
    if ($secret === '' || $secret === 'CHANGE_ME_TO_ANOTHER_LONG_RANDOM_SECRET') {
        return '';
    }
    return $secret;
}
