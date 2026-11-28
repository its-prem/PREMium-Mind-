<?php
/**
 * Students and what they own.
 *   GET  admin/students.php?search=text&page=1      -> students (30 per page) + the product list
 *   POST admin/students.php action=grant   {email, course_id}   give one product
 *   POST admin/students.php action=revoke  {email, course_id}   take one product (a copy is kept)
 *   POST admin/students.php action=verify  {email, value}       mark verified / pending
 *   POST admin/students.php action=profile {email, name, phone}  fix the name / phone (10 digits) of a student, e.g. one added by bulk enrol
 *   POST admin/students.php action=delete  {email}              delete the student and their access
 *   POST admin/students.php action=history {email}              last seen, device and every address the student used
 *   POST admin/students.php action=block_user   {email, reason}  the account can no longer log in or use the website
 *   POST admin/students.php action=unblock_user {email}
 *   GET  admin/students.php?online=1                            the students who were online in the last 5 minutes
 */
require_once __DIR__ . '/_boot.php';
$adminEmail = pm_admin_require();
require_once __DIR__ . '/../pm_user_guard.php';
pm_user_tables($conn);

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

    if ($action === 'profile') {
        $name  = mb_substr(trim((string)pm_in('name')), 0, 80);
        $phone = preg_replace('/[^0-9]/', '', (string)pm_in('phone'));
        if (strlen($phone) > 10) $phone = substr($phone, -10);   // drop a +91 / 91 in front
        if ($phone !== '' && !preg_match('/^[6-9][0-9]{9}$/', $phone)) pm_admin_fail('The phone number must be 10 digits (starting with 6, 7, 8 or 9).');
        if ($name === '') pm_admin_fail('Name is required.');
        $stmt = $conn->prepare('UPDATE users SET name = ?, phone = ? WHERE email = ?');
        $stmt->bind_param('sss', $name, $phone, $email);
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

    if ($action === 'history') {
        $st = $conn->prepare('SELECT first_seen, last_seen, last_ip, last_ua, seen_count FROM pm_user_activity WHERE email = ? LIMIT 1');
        $st->bind_param('s', $email);
        $st->execute();
        $act = $st->get_result()->fetch_assoc();
        $st->close();
        $st = $conn->prepare('SELECT ip, hits, first_seen, last_seen FROM pm_user_ips WHERE email = ? ORDER BY last_seen DESC LIMIT 30');
        $st->bind_param('s', $email);
        $st->execute();
        $ips = pm_rows($st->get_result());
        $st->close();
        $st = $conn->prepare('SELECT reason, created_at FROM pm_blocked_users WHERE email = ? LIMIT 1');
        $st->bind_param('s', $email);
        $st->execute();
        $blk = $st->get_result()->fetch_assoc();
        $st->close();
        pm_admin_ok(['activity' => $act ?: null, 'ips' => $ips, 'blocked' => $blk ?: null]);
    }

    if ($action === 'block_user') {
        if (in_array($email, pm_admin_list(), true)) pm_admin_fail('An admin account cannot be blocked.');
        $reason = mb_substr(trim((string)pm_in('reason', '')), 0, 200);
        $st = $conn->prepare('REPLACE INTO pm_blocked_users (email, reason, blocked_by) VALUES (?, ?, ?)');
        $st->bind_param('sss', $email, $reason, $adminEmail);
        if (!$st->execute()) pm_admin_fail('Could not block.', 500);
        $st->close();
        pm_admin_ok();
    }

    if ($action === 'unblock_user') {
        $st = $conn->prepare('DELETE FROM pm_blocked_users WHERE email = ?');
        $st->bind_param('s', $email);
        $st->execute();
        $st->close();
        pm_admin_ok();
    }

    pm_admin_fail('Unknown action.');
}

// who is online right now (the last 5 minutes)
if (pm_in_flag('online')) {
    $rows = pm_rows($conn->query(
        'SELECT a.email, a.last_seen, a.last_ip, a.last_ua, TIMESTAMPDIFF(SECOND, a.last_seen, NOW()) AS seen_ago,
                (SELECT name FROM users u WHERE CONVERT(u.email USING utf8mb4) COLLATE utf8mb4_general_ci = a.email COLLATE utf8mb4_general_ci LIMIT 1) AS name
           FROM pm_user_activity a WHERE a.last_seen > DATE_SUB(NOW(), INTERVAL 5 MINUTE) ORDER BY a.last_seen DESC LIMIT 100'
    ));
    pm_admin_ok(['online' => $rows]);
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

$sql = 'SELECT u.id, u.name, u.email, u.phone, u.is_active, u.created_at, GROUP_CONCAT(uc.course_id) AS enrolled_ids,
                 (SELECT a.last_seen FROM pm_user_activity a WHERE a.email COLLATE utf8mb4_general_ci = CONVERT(u.email USING utf8mb4) COLLATE utf8mb4_general_ci LIMIT 1) AS last_seen,
                 (SELECT TIMESTAMPDIFF(SECOND, a.last_seen, NOW()) FROM pm_user_activity a WHERE a.email COLLATE utf8mb4_general_ci = CONVERT(u.email USING utf8mb4) COLLATE utf8mb4_general_ci LIMIT 1) AS seen_ago,
                 (SELECT a.last_ip FROM pm_user_activity a WHERE a.email COLLATE utf8mb4_general_ci = CONVERT(u.email USING utf8mb4) COLLATE utf8mb4_general_ci LIMIT 1) AS last_ip,
                 (SELECT a.last_ua FROM pm_user_activity a WHERE a.email COLLATE utf8mb4_general_ci = CONVERT(u.email USING utf8mb4) COLLATE utf8mb4_general_ci LIMIT 1) AS last_ua,
                 (SELECT COUNT(*) FROM pm_user_ips i WHERE i.email COLLATE utf8mb4_general_ci = CONVERT(u.email USING utf8mb4) COLLATE utf8mb4_general_ci AND i.last_seen > DATE_SUB(NOW(), INTERVAL 30 DAY)) AS ip_count,
                 (SELECT COUNT(*) FROM pm_blocked_users b WHERE b.email COLLATE utf8mb4_general_ci = CONVERT(u.email USING utf8mb4) COLLATE utf8mb4_general_ci) AS is_blocked,
                 (SELECT b.reason FROM pm_blocked_users b WHERE b.email COLLATE utf8mb4_general_ci = CONVERT(u.email USING utf8mb4) COLLATE utf8mb4_general_ci LIMIT 1) AS block_reason
          FROM users u LEFT JOIN user_courses uc ON uc.user_email = u.email '
     . ($search !== '' ? 'WHERE u.name LIKE ? OR u.email LIKE ? ' : '')
     . 'GROUP BY u.id ORDER BY u.id DESC LIMIT ? OFFSET ?';
$stmt = $conn->prepare($sql);
if (!$stmt) {   // the activity tables could not be read: still show the students (without the online / blocked columns)
    error_log('students.php: activity columns failed: ' . $conn->error);
    $sql = preg_replace('/,\s*\(SELECT a\.last_seen.*?AS block_reason\s*FROM users u/s', ' FROM users u', $sql);
    $stmt = $conn->prepare($sql);
}
if (!$stmt) pm_admin_fail('Database error: ' . $conn->error, 500);   // the real reason is shown to the admin, not a blank page
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

$onlineNow = (int)pm_scalar($conn, 'SELECT COUNT(*) FROM pm_user_activity WHERE last_seen > DATE_SUB(NOW(), INTERVAL 5 MINUTE)');
pm_admin_ok(['students' => $students, 'courses' => $courses, 'total' => (int)$total, 'page' => $page, 'perPage' => $per, 'onlineNow' => $onlineNow]);
