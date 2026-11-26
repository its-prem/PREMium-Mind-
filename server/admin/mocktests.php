<?php
/**
 * MCQ mock tests (admin only).
 *   GET  admin/mocktests.php                         -> all uploaded tests
 *   POST admin/mocktests.php {action:'save', test:{id?, title, minutes, icon, tint, questions:[{q, options, answer, why}]}}
 *   POST admin/mocktests.php {action:'delete', id}
 * The website reads them through ../mocktests.php. Stored in pm_settings under the key "mocktests".
 */
require_once __DIR__ . '/_boot.php';
require_once __DIR__ . '/../pm_settings.php';
pm_admin_require();

function pm_mt_load(mysqli $conn): array {
    $cfg = pm_setting_get($conn, 'mocktests');
    $list = (is_array($cfg) && isset($cfg['tests']) && is_array($cfg['tests'])) ? $cfg['tests'] : [];
    $out = [];
    foreach ($list as $t) if (is_array($t) && !empty($t['id'])) $out[(string)$t['id']] = $t;
    return $out;
}

function pm_mt_save(mysqli $conn, array $map): bool {
    return pm_setting_set($conn, 'mocktests', ['tests' => array_values($map)]);
}

function pm_mt_text($v, int $max): string {
    $v = preg_replace('/\s+/u', ' ', trim((string)$v));
    return mb_substr($v, 0, $max);
}

/** Cleans the questions the browser sent. Every question needs 2-6 options and a valid answer. */
function pm_mt_questions($in): array {
    if (!is_array($in)) return [];
    $out = [];
    foreach (array_slice($in, 0, 200) as $i => $q) {
        if (!is_array($q)) continue;
        $text = pm_mt_text($q['q'] ?? '', 600);
        $opts = [];
        foreach (array_slice(is_array($q['options'] ?? null) ? $q['options'] : [], 0, 6) as $o) {
            $o = pm_mt_text($o, 300);
            if ($o !== '') $opts[] = $o;
        }
        $ans = $q['answer'] ?? -1;
        if (!is_numeric($ans)) pm_admin_fail('Question ' . ($i + 1) . ': the answer is missing.');
        $ans = (int)$ans;
        if ($text === '' || count($opts) < 2) pm_admin_fail('Question ' . ($i + 1) . ': needs a question and at least 2 options.');
        if ($ans < 0 || $ans >= count($opts)) pm_admin_fail('Question ' . ($i + 1) . ': the answer is not one of the options.');
        $item = ['q' => $text, 'options' => $opts, 'answer' => $ans];
        $why = pm_mt_text($q['why'] ?? '', 500);
        if ($why !== '') $item['why'] = $why;
        $out[] = $item;
    }
    return $out;
}

$map = pm_mt_load($conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)pm_in('action', 'save');

    if ($action === 'delete') {
        $id = (string)pm_in('id', '');
        if (!isset($map[$id])) pm_admin_fail('Test not found.', 404);
        unset($map[$id]);
        if (!pm_mt_save($conn, $map)) pm_admin_fail('Could not save.', 500);
        pm_admin_ok(['tests' => array_values($map)]);
    }

    if ($action === 'save') {
        $t = pm_in('test', []);
        if (is_string($t)) $t = json_decode($t, true);
        if (!is_array($t)) pm_admin_fail('Test data missing.');

        $title = pm_mt_text($t['title'] ?? '', 120);
        if ($title === '') pm_admin_fail('Test name is required.');
        $minutes = (int)($t['minutes'] ?? 0);
        if ($minutes < 1 || $minutes > 300) pm_admin_fail('Time must be 1 to 300 minutes.');
        $questions = pm_mt_questions($t['questions'] ?? []);
        if (!$questions) pm_admin_fail('No questions found.');

        $icon = preg_replace('/[^a-z0-9-]/i', '', (string)($t['icon'] ?? ''));
        if ($icon === '') $icon = 'document-text-outline';
        $tint = (string)($t['tint'] ?? '');
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $tint)) $tint = '#38bdf8';

        $id = (string)($t['id'] ?? '');
        if ($id === '' || !isset($map[$id])) {
            $base = 'm-' . trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($title)), '-');
            $base = substr($base, 0, 50);
            $id = $base;
            $n = 2;
            while (isset($map[$id]) || $id === 'm-') { $id = $base . '-' . $n++; }
        }

        $map[$id] = ['id' => $id, 'title' => $title, 'icon' => $icon, 'tint' => $tint, 'minutes' => $minutes, 'questions' => $questions];
        if (strlen(json_encode($map)) > 8000000) pm_admin_fail('Too much data.');
        if (!pm_mt_save($conn, $map)) pm_admin_fail('Could not save.', 500);
        pm_admin_ok(['id' => $id, 'tests' => array_values($map)]);
    }

    pm_admin_fail('Unknown action.');
}

pm_admin_ok(['tests' => array_values($map)]);
