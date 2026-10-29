<?php
/**
 * Upload to Hostinger premind/ as: pm_firebase_verify.php
 *
 * Verifies a Firebase ID token with Google and returns the real email.
 * Used by the PDF endpoints so access is tied to a proven identity instead
 * of a self-declared "email" field in the request body.
 *
 * Verification is done by Google (tokeninfo) — the token's signature is
 * checked on Google's side; there is deliberately NO local-decode fallback.
 */

// ───────────────────────────────────────────────────────────────────────
//  ONE switch: jab website/app id_token bhejne lage (watch.html + pm-firebase.js
//  deploy ho jaayein), ise `true` kar do. Tab bina verified token wali website
//  request reject hogi — email-spoofing ka chance 0. (App apne signature se
//  phir bhi chalega.) Abhi `false` = purane clients bhi chalte rahenge.
// ───────────────────────────────────────────────────────────────────────
if (!defined('PM_PDF_REQUIRE_IDENTITY')) {
    define('PM_PDF_REQUIRE_IDENTITY', false);
}

if (!function_exists('pm_fb_project_id')) {
    function pm_fb_project_id(): string { return 'premium-mind-fcb16'; }
}

if (!function_exists('pm_fb_http_get')) {
    function pm_fb_http_get(string $url): string {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 6);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            $body = curl_exec($ch);
            curl_close($ch);
            return is_string($body) ? $body : '';
        }
        $ctx = stream_context_create(['http' => ['timeout' => 6]]);
        $body = @file_get_contents($url, false, $ctx);
        return is_string($body) ? $body : '';
    }
}

/**
 * Returns the verified, lowercase email for a valid Firebase ID token, or
 * '' if the token is missing / invalid / expired / unverified.
 */
if (!function_exists('pm_firebase_email_from_token')) {
    function pm_firebase_email_from_token(string $idToken): string {
        $idToken = trim($idToken);
        if ($idToken === '' || substr_count($idToken, '.') !== 2) return '';

        $projectId = pm_fb_project_id();
        $body = pm_fb_http_get('https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($idToken));
        if ($body === '') return '';

        $data = json_decode($body, true);
        if (!is_array($data) || !empty($data['error']) || empty($data['email'])) return '';

        $aud = (string)($data['aud'] ?? '');
        $iss = (string)($data['iss'] ?? '');
        $audOk = ($aud === $projectId);
        $issOk = ($iss === 'https://securetoken.google.com/' . $projectId);
        if (!$audOk && !$issOk) return '';

        if (!empty($data['exp']) && time() >= ((int)$data['exp'] + 30)) return '';

        // email_verified comes back as the string "true"/"false" from tokeninfo
        $ev = $data['email_verified'] ?? $data['verified_email'] ?? 'true';
        if ($ev === false || $ev === 'false' || $ev === 0 || $ev === '0') return '';

        return strtolower(trim((string)$data['email']));
    }
}

/**
 * Resolve the trusted email for a PDF request.
 *   - If a valid id_token is present → its verified email (body email ignored).
 *   - Else, when identity is NOT required (default) → the body email (old behaviour).
 *   - Else (identity required and no valid token, non-app request) → '' (caller rejects).
 *
 * $isNativeApp lets the native app (which has its own HMAC app-signature
 * check) keep working even when identity enforcement is turned on.
 */
if (!function_exists('pm_pdf_resolve_email')) {
    function pm_pdf_resolve_email(array $data, bool $isNativeApp = false): string {
        $bodyEmail  = strtolower(trim((string)($data['email'] ?? '')));
        $idToken    = (string)($data['id_token'] ?? $data['idToken'] ?? '');

        $verified = $idToken !== '' ? pm_firebase_email_from_token($idToken) : '';
        if ($verified !== '') return $verified;

        if (PM_PDF_REQUIRE_IDENTITY && !$isNativeApp) return '';

        return $bodyEmail;
    }
}
