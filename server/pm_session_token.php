<?php
/**
 * Upload to Hostinger premind/ as: pm_session_token.php
 *
 * A small, signed "PDF session token" minted at login (after the server has
 * verified the user via password / OTP / Firebase) and stored on the client.
 * The PDF endpoints read the email back from this token, so access is tied to
 * a proven identity — the client cannot change it without the server secret.
 *
 * Self-contained: only needs the existing HMAC secret (same one get_pdf_token
 * already uses). Never touches the DB, never calls out to the network, so
 * including it in a login endpoint cannot slow down or break login.
 */

require_once __DIR__ . '/pm_load_secrets.php';

if (!function_exists('pm_session_secret')) {
    function pm_session_secret(): string {
        if (function_exists('pm_pdf_hmac_secret')) {
            $s = (string)pm_pdf_hmac_secret();
            if ($s !== '' && $s !== 'CHANGE_ME') return $s;
        }
        return '';
    }
}

/** Make a signed token for $email (default 30-day life). '' if secret missing. */
if (!function_exists('pm_session_make')) {
    function pm_session_make(string $email, int $days = 30): string {
        $email  = strtolower(trim($email));
        $secret = pm_session_secret();
        if ($email === '' || $secret === '') return '';
        $payload = ['e' => $email, 'x' => time() + ($days * 86400)];
        $b64 = rtrim(strtr(base64_encode(json_encode($payload)), '+/', '-_'), '=');
        $sig = hash_hmac('sha256', $b64, $secret);
        return $b64 . '.' . $sig;
    }
}

/** Verified lowercase email from a token, or '' if invalid / expired / tampered. */
if (!function_exists('pm_session_email')) {
    function pm_session_email(string $token): string {
        $token  = trim($token);
        $secret = pm_session_secret();
        if ($token === '' || $secret === '' || substr_count($token, '.') !== 1) return '';

        list($b64, $sig) = explode('.', $token, 2);
        $expected = hash_hmac('sha256', $b64, $secret);
        if (!hash_equals($expected, (string)$sig)) return '';

        $json = base64_decode(strtr($b64, '-_', '+/'));
        if ($json === false || $json === '') return '';
        $d = json_decode($json, true);
        if (!is_array($d) || empty($d['e']) || empty($d['x'])) return '';
        if (time() >= (int)$d['x']) return '';

        return strtolower(trim((string)$d['e']));
    }
}
