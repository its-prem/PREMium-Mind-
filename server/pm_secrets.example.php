<?php
/**
 * Copy this file to: pm_secrets.php  (same Hostinger folder as proxy_pdf.php)
 * DO NOT commit pm_secrets.php to GitHub.
 *
 * Upload path: public_html/premind/pm_secrets.php
 */
return [
    // Long random string — used by get_pdf_token.php + proxy_pdf.php
    'pdf_hmac_secret' => 'CHANGE_ME_TO_A_LONG_RANDOM_SECRET',

    // Proves a request genuinely came from the Android app. Must match the
    // secret compiled into the app. See APP_ONLY_COURSES_SETUP.txt.
    'app_shared_secret' => 'CHANGE_ME_TO_ANOTHER_LONG_RANDOM_SECRET',
];
