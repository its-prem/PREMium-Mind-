<?php
/**
 * Upload to Hostinger premind/ as: pm_admin_emails.php
 *
 * THE ONE PLACE that says who is an admin. The list is NOT written in any code file (so it is not in GitHub and
 * not in anything a visitor can see). It lives only in the server's .env:
 *
 *     ADMIN_EMAILS=first@gmail.com,second@gmail.com
 *
 * If the line is missing nobody is an admin (the admin panel stays locked), never "everybody".
 */
require_once __DIR__ . '/pm_load_env.php';
pm_load_dotenv(__DIR__ . '/.env');

if (!function_exists('pm_admin_email_list')) {
    /** @return string[] lower-case admin emails from .env */
    function pm_admin_email_list(): array {
        $out = [];
        foreach (explode(',', (string)pm_env('ADMIN_EMAILS')) as $e) {
            $e = strtolower(trim($e));
            if ($e !== '' && filter_var($e, FILTER_VALIDATE_EMAIL)) $out[] = $e;
        }
        return array_values(array_unique($out));
    }
}
