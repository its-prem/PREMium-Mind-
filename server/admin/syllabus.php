<?php
/**
 * Diploma syllabus subjects (admin only).
 *   GET  admin/syllabus.php                          -> all uploaded subjects
 *   POST admin/syllabus.php {action:'save', subject:{id?, title, sem, branches, code, ltp, icon, units}}
 *   POST admin/syllabus.php {action:'delete', id}
 * The website reads them through ../syllabus.php. Stored in pm_settings under the key "syllabus".
 */
require_once __DIR__ . '/_boot.php';
require_once __DIR__ . '/../pm_settings.php';
pm_admin_require();

function pm_syl_load(mysqli $conn): array {
    $cfg = pm_setting_get($conn, 'syllabus');
    $list = (is_array($cfg) && isset($cfg['subjects']) && is_array($cfg['subjects'])) ? $cfg['subjects'] : [];
    $out = [];
    foreach ($list as $s) if (is_array($s) && !empty($s['id'])) $out[(string)$s['id']] = $s;
    return $out;
}

function pm_syl_save(mysqli $conn, array $map): bool {
    return pm_setting_set($conn, 'syllabus', ['subjects' => array_values($map)]);
}

function pm_syl_text($v, int $max): string {
    $v = preg_replace('/\s+/u', ' ', trim((string)$v));
    return mb_substr($v, 0, $max);
}

/** Cleans the units the browser sent: units -> sections -> topics, all plain text, with size limits. */
function pm_syl_units($in): array {
    if (!is_array($in)) return [];
    $units = [];
    foreach (array_slice($in, 0, 60) as $u) {
        if (!is_array($u)) continue;
        $title = pm_syl_text($u['title'] ?? '', 200);
        $sections = [];
        foreach (array_slice(is_array($u['sections'] ?? null) ? $u['sections'] : [], 0, 40) as $sec) {
            if (!is_array($sec)) continue;
            $topics = [];
            foreach (array_slice(is_array($sec['topics'] ?? null) ? $sec['topics'] : [], 0, 200) as $t) {
                $t = pm_syl_text($t, 500);
                if ($t !== '') $topics[] = $t;
            }
            if (!$topics) continue;
            $item = ['topics' => $topics];
            $h = pm_syl_text($sec['heading'] ?? '', 200);
            if ($h !== '') $item['heading'] = $h;
            $sections[] = $item;
        }
        if ($title === '' || !$sections) continue;
        $units[] = ['title' => $title, 'sections' => $sections];
    }
    return $units;
}

$map = pm_syl_load($conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)pm_in('action', 'save');

    if ($action === 'delete') {
        $id = (string)pm_in('id', '');
        if (!isset($map[$id])) pm_admin_fail('Subject not found.', 404);
        unset($map[$id]);
        if (!pm_syl_save($conn, $map)) pm_admin_fail('Could not save.', 500);
        pm_admin_ok(['subjects' => array_values($map)]);
    }

    if ($action === 'save') {
        $s = pm_in('subject', []);
        if (is_string($s)) $s = json_decode($s, true);
        if (!is_array($s)) pm_admin_fail('Subject data missing.');

        $title = pm_syl_text($s['title'] ?? '', 120);
        if ($title === '') pm_admin_fail('Subject name is required.');
        $sem = (int)($s['sem'] ?? 0);
        if ($sem < 1 || $sem > 6) pm_admin_fail('Choose a semester from 1 to 6.');
        $units = pm_syl_units($s['units'] ?? []);
        if (!$units) pm_admin_fail('No units found in the syllabus text.');

        $branches = 'all';
        if (is_array($s['branches'] ?? null)) {
            $b = [];
            foreach ($s['branches'] as $x) {
                $x = preg_replace('/[^a-z0-9_-]/i', '', (string)$x);
                if ($x !== '' && !in_array($x, $b, true)) $b[] = $x;
            }
            if ($b) $branches = $b;
        }

        $icon = preg_replace('/[^a-z0-9-]/i', '', (string)($s['icon'] ?? ''));
        if ($icon === '') $icon = 'book-outline';

        $id = (string)($s['id'] ?? '');
        if ($id === '' || !isset($map[$id])) {
            // new subject: an id made from the name, with a short number if that name exists
            $base = 's-' . trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($title)), '-');
            $base = substr($base, 0, 50);
            $id = $base;
            $n = 2;
            while (isset($map[$id]) || $id === 's-') { $id = $base . '-' . $n++; }
        }

        $map[$id] = [
            'id'       => $id,
            'title'    => $title,
            'sem'      => $sem,
            'branches' => $branches,
            'code'     => pm_syl_text($s['code'] ?? '', 30),
            'ltp'      => pm_syl_text($s['ltp'] ?? '', 20),
            'icon'     => $icon,
            'units'    => $units,
        ];
        if (strlen(json_encode($map)) > 8000000) pm_admin_fail('Too much syllabus data.');
        if (!pm_syl_save($conn, $map)) pm_admin_fail('Could not save.', 500);
        pm_admin_ok(['id' => $id, 'subjects' => array_values($map)]);
    }

    pm_admin_fail('Unknown action.');
}

pm_admin_ok(['subjects' => array_values($map)]);
