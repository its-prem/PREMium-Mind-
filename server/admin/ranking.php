<?php
/**
 * Home-page order of the courses.
 *   GET  admin/ranking.php            -> active courses in the current order
 *   POST admin/ranking.php  {order:[id,id,...]}  -> save the new order (1st = rank 1)
 */
require_once __DIR__ . '/_boot.php';
pm_admin_require();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ids = pm_in('order', []);
    if (is_string($ids)) $ids = json_decode($ids, true);
    if (!is_array($ids) || !$ids) pm_admin_fail('Invalid order list.');

    $cases = '';
    $list = [];
    $rank = 1;
    foreach ($ids as $cid) {
        $cid = (int)$cid;
        if ($cid <= 0) continue;
        $cases .= " WHEN $cid THEN $rank";   // every value is an int here
        $list[] = $cid;
        $rank++;
    }
    if (!$list) pm_admin_fail('Invalid order list.');

    $in = implode(',', $list);
    if (!$conn->query("UPDATE courses SET sort_order = CASE id$cases END WHERE id IN ($in) AND is_deleted = 0")) {
        pm_admin_fail('Save failed: ' . $conn->error, 500);
    }
    pm_admin_ok(['ranked' => count($list)]);
}

pm_admin_ok(['courses' => pm_rows($conn->query(
    'SELECT id, title, category, image, price, sort_order FROM courses WHERE is_deleted = 0 ORDER BY sort_order ASC, id DESC'
))]);
