<?php
/** GET  admin/me.php  -> who am I + the dropdown options. Used when /admin opens. */
require_once __DIR__ . '/_boot.php';

$email = pm_admin_require();
pm_admin_ok(['email' => $email, 'options' => pm_admin_options()]);
