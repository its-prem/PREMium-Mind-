<?php
/**
 * Upload to Hostinger premind/ as: pm_firebase_verify.php
 *
 * Verifies a Firebase ID token with Google and returns the real email, so
 * PDF access is tied to a proven identity instead of a self-declared "email"
 * field. Verification is done by Google — no insecure local-decode fallback.
 */

// ───────────────────────────────────────────────────────────────────────
//  ONE switch: jab website/app id_token bhejne lage (watch.html + pm-firebase.js
//  live ho jaayein) aur test pass ho jaaye, ise `true` kar do → bina verified
//  token wali website PDF request reject hogi (spoofing 0). App apne signature
//  se phir bhi chalega. Abhi `false` = purane clients bhi chalte rahenge.
// ───────────────────────────────────────────────────────────────────────
if (!defined('PM_PDF_REQUIRE_IDENTITY')) {
    define('PM_PDF_REQUIRE_IDENTITY', false);
}

if (!function_exists('pm_fb_project_id')) {
    function pm_fb_project_id(): string { return 'premium-mind-fcb16'; }
}
if (!function_exists('pm_fb_api_key')) {
    // Public Firebase web API key (same one in admin_panel.php + login.html).
    function pm_fb_api_key(): string { return 'AIzaSyBMF_RAmopbnPC7OpNcJCo2CUGS6CDiMEY'; }
}

if (!function_exists('pm_fb_http')) {
    function pm_fb_http(string $url, ?array $postJson = null): string {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 8);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            if ($postJson !== null) {
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postJson));
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            }
            $body = curl_exec($ch);
            curl_close($ch);
            return is_string($body) ? $body : '';
        }
        $opts = ['http' => ['timeout' => 8, 'ignore_errors' => true]];
        if ($postJson !== null) {
            $opts['http']['method']  = 'POST';
            $opts['http']['header']  = "Content-Type: application/json\r\n";
            $opts['http']['content'] = json_encode($postJson);
        }
        $body = @file_get_contents($url, false, stream_context_create($opts));
        return is_string($body) ? $body : '';
    }
}

/**
 * Returns the verified, lowercase email for a valid Firebase ID token, or ''.
 * Sets $GLOBALS['pm_fb_last_reason'] so callers can report what went wrong.
 */
if (!function_exists('pm_firebase_email_from_token')) {
    function pm_firebase_email_from_token(string $idToken): string {
        $GLOBALS['pm_fb_last_reason'] = '';
        $idToken = trim($idToken);
        if ($idToken === '' || substr_count($idToken, '.') !== 2) {
            $GLOBALS['pm_fb_last_reason'] = 'no_token';
            return '';
        }
        $pid = pm_fb_project_id();

        // 1) accounts:lookup — exactly what admin_panel.php login uses, so it
        //    works wherever admin login already works on this host.
        $key = pm_fb_api_key();
        if ($key !== '') {
            $body = pm_fb_http('https://identitytoolkit.googleapis.com/v1/accounts:lookup?key=' . urlencode($key), ['idToken' => $idToken]);
            if ($body !== '') {
                $d = json_decode($body, true);
                if (is_array($d) && !empty($d['users'][0]['email'])) {
                    $u = $d['users'][0];
                    if (isset($u['emailVerified']) && $u['emailVerified'] === false) {
                        $GLOBALS['pm_fb_last_reason'] = 'email_unverified';
                        return '';
                    }
                    return strtolower(trim((string)$u['email']));
                }
            }
        }

        // 2) tokeninfo fallback
        $body = pm_fb_http('https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($idToken));
        if ($body !== '') {
            $d = json_decode($body, true);
            if (is_array($d) && empty($d['error']) && !empty($d['email'])) {
                $aud = (string)($d['aud'] ?? '');
                $iss = (string)($d['iss'] ?? '');
                if ($aud === $pid || $iss === 'https://securetoken.google.com/' . $pid) {
                    if (empty($d['exp']) || time() < ((int)$d['exp'] + 30)) {
                        return strtolower(trim((string)$d['email']));
                    }
                    $GLOBALS['pm_fb_last_reason'] = 'expired';
                    return '';
                }
                $GLOBALS['pm_fb_last_reason'] = 'aud_mismatch';
                return '';
            }
        }

        $GLOBALS['pm_fb_last_reason'] = 'google_unreachable_or_invalid';
        return '';
    }
}

/**
 * Resolve the trusted email for a PDF request.
 *   - valid id_token present  → its verified email (body email ignored)
 *   - else, identity NOT required → body email (old behaviour)
 *   - else (required + no valid token, non-app) → '' and sets a reason
 */
if (!function_exists('pm_pdf_resolve_email')) {
    function pm_pdf_resolve_email(array $data, bool $isNativeApp = false): string {
        $GLOBALS['pm_pdf_identity_reason'] = '';
        $bodyEmail = strtolower(trim((string)($data['email'] ?? '')));

        // Preferred: our own signed session token (minted at login) — fast, no
        // network call, works for every login type (password / OTP / Google).
        $sess = (string)($data['pdf_session'] ?? '');
        if ($sess !== '') {
            require_once __DIR__ . '/pm_session_token.php';
            $e = pm_session_email($sess);
            if ($e !== '') return $e;
        }

        // Fallback: a live Firebase ID token, if one was sent.
        $idToken = (string)($data['id_token'] ?? $data['idToken'] ?? '');
        $verified = $idToken !== '' ? pm_firebase_email_from_token($idToken) : '';
        if ($verified !== '') return $verified;

        if (PM_PDF_REQUIRE_IDENTITY && !$isNativeApp) {
            $GLOBALS['pm_pdf_identity_reason'] = ($idToken === '')
                ? 'login_token_missing'
                : ('token_not_verified:' . ($GLOBALS['pm_fb_last_reason'] ?? ''));
            return '';
        }
        return $bodyEmail;
    }
}
