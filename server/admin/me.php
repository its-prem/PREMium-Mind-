<?php
/** GET  admin/me.php  -> who am I + the dropdown options. Used when /admin opens. */
require_once __DIR__ . '/_boot.php';

$email = pm_admin_require();
$pending = (int)pm_scalar($conn, "SELECT COUNT(*) FROM reports WHERE status = 'pending'");
pm_admin_ok(['email' => $email, 'options' => pm_admin_options(), 'badges' => ['reports' => $pending]]);
