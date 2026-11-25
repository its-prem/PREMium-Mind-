<?php
/**
 * Problem reports sent by students.
 *   GET  admin/reports.php?status=pending|resolved   -> list (default pending)
 *   POST admin/reports.php {action:"resolve", id}    -> mark resolved
 *   POST admin/reports.php {action:"reopen",  id}    -> back to pending
 *   POST admin/reports.php {action:"delete",  id}    -> remove the report
 */
require_once __DIR__ . '/_boot.php';
pm_admin_require();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)pm_in('action');
    $id = pm_in_int('id');
    if ($id <= 0) pm_admin_fail('Bad report id.');

    if ($action === 'resolve' || $action === 'reopen') {
        $status = $action === 'resolve' ? 'resolved' : 'pending';
        $stmt = $conn->prepare('UPDATE reports SET status = ? WHERE id = ?');
        $stmt->bind_param('si', $status, $id);
    } elseif ($action === 'delete') {
        $stmt = $conn->prepare('DELETE FROM reports WHERE id = ?');
        $stmt->bind_param('i', $id);
    } else {
        pm_admin_fail('Unknown action.');
    }
    if (!$stmt->execute()) pm_admin_fail('Failed: ' . $conn->error, 500);
    pm_admin_ok();
}

$status = pm_in('status', 'pending') === 'resolved' ? 'resolved' : 'pending';
$stmt = $conn->prepare(
    'SELECT r.*, c.title AS course_title, u.name AS user_name, u.phone AS user_phone
       FROM reports r
       LEFT JOIN courses c ON r.course_id = c.id
       LEFT JOIN users u ON r.email = u.email
      WHERE r.status = ? ORDER BY r.id ' . ($status === 'pending' ? 'ASC' : 'DESC') . ' LIMIT 200'
);
$stmt->bind_param('s', $status);
$stmt->execute();
pm_admin_ok(['reports' => pm_rows($stmt->get_result())]);
