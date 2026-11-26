<?php
/**
 * Upload to Hostinger premind/ as: mocktests.php
 *
 * PUBLIC, read-only. The website asks for the MCQ tests the admin uploaded (Admin -> MCQ tests).
 * Stored in the pm_settings table under the key "mocktests".
 */
require_once __DIR__ . '/pm_cors.php';
pm_cors_json(['GET'], true);

include __DIR__ . '/db_connect.php';
require_once __DIR__ . '/pm_settings.php';

$cfg = pm_setting_get($conn, 'mocktests');
$tests = (is_array($cfg) && isset($cfg['tests']) && is_array($cfg['tests'])) ? array_values($cfg['tests']) : [];
echo json_encode(['status' => 'success', 'tests' => $tests]);
