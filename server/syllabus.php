<?php
/**
 * Upload to Hostinger premind/ as: syllabus.php
 *
 * PUBLIC, read-only. The website asks for the subjects the admin uploaded (Admin -> Syllabus).
 * They are stored in the pm_settings table under the key "syllabus" (no new table needed).
 */
require_once __DIR__ . '/pm_cors.php';
pm_cors_json(['GET'], true);

include __DIR__ . '/db_connect.php';
require_once __DIR__ . '/pm_settings.php';

$cfg = pm_setting_get($conn, 'syllabus');
$subjects = (is_array($cfg) && isset($cfg['subjects']) && is_array($cfg['subjects'])) ? array_values($cfg['subjects']) : [];
echo json_encode(['status' => 'success', 'subjects' => $subjects]);
