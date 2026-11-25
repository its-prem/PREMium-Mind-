<?php
/**
 * Students and what they own.
 *   GET  admin/students.php?search=text&page=1      -> students (30 per page) + the product list
 *   POST admin/students.php action=grant   {email, course_id}   give one product
 *   POST admin/students.php action=revoke  {email, course_id}   take one product (a copy is kept)
 *   POST admin/students.php action=verify  {email, value}       mark verified / pending
 *   POST admin/students.php action=delete  {email}              delete the student and their access
 */
require_once __DIR__ . '/_boot.php';
$adminEmail = pm_admin_require();

function pm_student_email(): string {
    $e = strtolower(trim((string)pm_in('email')));
    if ($e === '' || !filter_var($e, FILTER_VALIDATE_EMAIL)) pm_admin_fail('Bad email.');
    return $e;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)pm_in('action');
    $email = pm_student_email();

    if ($action === 'grant') {
        $cid = pm_in_int('course_id');
        if ($cid <= 0) pm_admin_fail('Bad product id.');
        if (!pm_grant_course($conn, $email, $cid)) pm_admin_fail('Could not give access.', 500);
        pm_admin_ok();
    }

    if ($action === 'revoke') {
        $cid = pm_in_int('course_id');
        if ($cid <= 0) pm_admin_fail('Bad product id.');
        $conn->query("CREATE TABLE IF NOT EXISTS user_courses_revoked (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_email VARCHAR(190) NOT NULL,
            course_id INT NOT NULL,
            purchased_at DATETIME NULL,
            revoked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            revoked_by VARCHAR(190) NOT NULL DEFAULT '',
            INDEX idx_course (course_id),
            INDEX idx_email (user_email)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $hasAt = pm_has_column($conn, 'user_courses', 'purchased_at');
        $stmt = $conn->prepare($hasAt
            ? 'INSERT INTO user_courses_revoked (user_email, course_id, purchased_at, revoked_by) SELECT user_email, course_id, purchased_at, ? FROM user_courses WHERE user_email = ? AND course_id = ?'
            : 'INSERT INTO user_courses_revoked (user_email, course_id, revoked_by) SELECT user_email, course_id, ? FROM user_courses WHERE user_email = ? AND course_id = ?');
        $stmt->bind_param('ssi', $adminEmail, $email, $cid);
        $stmt->execute();
        $stmt->close();
        $stmt = $conn->prepare('DELETE FROM user_courses WHERE user_email = ? AND course_id = ?');
        $stmt->bind_param('si', $email, $cid);
        if (!$stmt->execute()) pm_admin_fail('Failed: ' . $stmt->error, 500);
        pm_admin_ok();
    }

    if ($action === 'verify') {
        $v = pm_in_flag('value');
        $stmt = $conn->prepare('UPDATE users SET is_active = ? WHERE email = ?');
        $stmt->bind_param('is', $v, $email);
        if (!$stmt->execute()) pm_admin_fail('Failed: ' . $stmt->error, 500);
        pm_admin_ok();
    }

    if ($action === 'delete') {
        if (in_array($email, pm_admin_list(), true)) pm_admin_fail('An admin account cannot be deleted here.');
        $stmt = $conn->prepare('DELETE FROM user_courses WHERE user_email = ?');
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $stmt->close();
        $stmt = $conn->prepare('DELETE FROM users WHERE email = ?');
        $stmt->bind_param('s', $email);
        if (!$stmt->execute()) pm_admin_fail('Failed: ' . $stmt->error, 500);
        pm_admin_ok();
    }

    pm_admin_fail('Unknown action.');
}

$search = trim((string)pm_in('search'));
$page = max(1, pm_in_int('page', 1));
$per = 30;
$offset = ($page - 1) * $per;
$like = '%' . $search . '%';

if ($search !== '') {
    $stmt = $conn->prepare('SELECT COUNT(*) FROM users WHERE name LIKE ? OR email LIKE ?');
    $stmt->bind_param('ss', $like, $like);
} else {
    $stmt = null;
}
if ($stmt) {
    $stmt->execute();
    $stmt->bind_result($total);
    $stmt->fetch();
    $stmt->close();
} else {
    $total = (int)pm_scalar($conn, 'SELECT COUNT(*) FROM users');
}

$sql = 'SELECT u.id, u.name, u.email, u.phone, u.is_active, u.created_at, GROUP_CONCAT(uc.course_id) AS enrolled_ids
          FROM users u LEFT JOIN user_courses uc ON uc.user_email = u.email '
     . ($search !== '' ? 'WHERE u.name LIKE ? OR u.email LIKE ? ' : '')
     . 'GROUP BY u.id ORDER BY u.id DESC LIMIT ? OFFSET ?';
$stmt = $conn->prepare($sql);
if ($search !== '') $stmt->bind_param('ssii', $like, $like, $per, $offset);
else $stmt->bind_param('ii', $per, $offset);
$stmt->execute();
$students = pm_rows($stmt->get_result());
foreach ($students as &$s) {
    $s['enrolled'] = $s['enrolled_ids'] === null || $s['enrolled_ids'] === ''
        ? [] : array_map('intval', explode(',', $s['enrolled_ids']));
    unset($s['enrolled_ids']);
}
unset($s);

$courses = pm_rows($conn->query('SELECT id, title, category FROM courses WHERE is_deleted = 0 ORDER BY sort_order ASC, id DESC'));

pm_admin_ok(['students' => $students, 'courses' => $courses, 'total' => (int)$total, 'page' => $page, 'perPage' => $per]);
