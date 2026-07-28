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
];
