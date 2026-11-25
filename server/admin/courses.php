<?php
/**
 * Products (the "courses" table).
 *   GET  admin/courses.php                      -> every product + how many students own it
 *   POST admin/courses.php  action=save         -> add (no id) or edit (id)  [multipart: image_file, pdf_file]
 *   POST admin/courses.php  action=flag         -> {id, field, value}   one switch / dropdown
 *   POST admin/courses.php  action=duplicate    -> {id}                 copy a product
 *   POST admin/courses.php  action=trash        -> {id}                 move to trash
 *   POST admin/courses.php  action=restore      -> {id}
 *   POST admin/courses.php  action=delete       -> {id}                 delete for ever (only when trashed)
 *   POST admin/courses.php  action=revoke_all   -> {id}                 take the product away from every student
 *
 * A new product field = add its name to $TEXT_FIELDS (or $FLAG_FIELDS) below, and a line in
 * src/admin/tabs/courses/fields.js. Nothing else.
 */
require_once __DIR__ . '/_boot.php';
$adminEmail = pm_admin_require();

$TEXT_FIELDS = ['title', 'category', 'badge', 'desc1', 'desc2', 'price', 'old_price', 'link',
                'demo_link', 'website_link', 'btn_text', 'btn_type', 'tnc_text', 'download_msg'];
$FLAG_FIELDS = ['allow_download', 'show_tnc', 'show_report_btn', 'app_only', 'show_preview', 'grants_pyq'];
$BTN_TYPES   = ['normal', 'coming_soon', 'disabled_look', 'preview_buy', 'disabled'];

function pm_course_id(): int {
    $id = pm_in_int('id');
    if ($id <= 0) pm_admin_fail('Bad product id.');
    return $id;
}

/** Saves an uploaded file after checking type + size. Returns the stored relative path or ''. */
function pm_save_upload(string $field, string $subdir, array $okExt, int $maxBytes): string {
    if (empty($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) return '';
    $f = $_FILES[$field];
    if ($f['error'] !== UPLOAD_ERR_OK) pm_admin_fail('Upload failed (code ' . (int)$f['error'] . ').');
    if ($f['size'] > $maxBytes) pm_admin_fail('File is too big (max ' . round($maxBytes / 1048576) . ' MB).');
    $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $okExt, true)) pm_admin_fail('Only ' . implode(', ', $okExt) . ' files are allowed here.');
    $dir = __DIR__ . '/../uploads/' . $subdir . '/';
    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) pm_admin_fail('Could not create the upload folder.', 500);
    $name = time() . '_' . bin2hex(random_bytes(3)) . '.' . $ext;   // a fresh safe name, the original is not trusted
    if (!move_uploaded_file($f['tmp_name'], $dir . $name)) pm_admin_fail('Could not save the file.', 500);
    return 'uploads/' . $subdir . '/' . $name;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    pm_admin_ok(['courses' => pm_rows($conn->query(
        'SELECT c.*, (SELECT COUNT(*) FROM user_courses uc WHERE uc.course_id = c.id) AS enrolled_count
           FROM courses c ORDER BY c.is_deleted ASC, c.sort_order ASC, c.id DESC'
    ))]);
}

$action = (string)pm_in('action');

/* ---------- add / edit ---------- */
if ($action === 'save') {
    $id = pm_in_int('id');
    $vals = [];
    foreach ($TEXT_FIELDS as $f) $vals[$f] = trim((string)pm_in($f, ''));
    foreach ($FLAG_FIELDS as $f) {
        if ($f === 'grants_pyq' && !pm_has_column($conn, 'courses', 'grants_pyq')) continue;
        $vals[$f] = pm_in_flag($f);
    }
    if ($vals['title'] === '') pm_admin_fail('Title is required.');
    if (!in_array($vals['btn_type'], $BTN_TYPES, true)) $vals['btn_type'] = 'normal';
    if ($vals['btn_text'] === '') $vals['btn_text'] = 'Buy Now';
    if ($vals['category'] === '') $vals['category'] = 'notes';

    $image = pm_save_upload('image_file', 'thumbnails', ['jpg', 'jpeg', 'png', 'webp', 'gif'], 5 * 1048576);
    if ($image !== '') {
        $image = 'https://premind.diplomawallah.in/' . $image;
    } else {
        $image = trim((string)pm_in('existing_image', ''));
        if ($image === '') $image = 'small-logo.png';
    }
    $pdf = pm_save_upload('pdf_file', 'pdfs', ['pdf'], 60 * 1048576);
    if ($pdf === '') $pdf = trim((string)pm_in('existing_pdf', ''));

    $vals['image'] = $image;
    $vals['pdf_file'] = $pdf;

    $cols = array_keys($vals);
    if ($id > 0) {
        $set = implode(', ', array_map(fn($c) => "`$c` = ?", $cols));
        $stmt = $conn->prepare("UPDATE courses SET $set WHERE id = ?");
        $types = str_repeat('s', count($cols)) . 'i';
        $params = array_merge(array_values($vals), [$id]);
    } else {
        $conn->query('UPDATE courses SET sort_order = sort_order + 1');   // a new product goes to rank 1
        $cols[] = 'sort_order';
        $vals['sort_order'] = 1;
        $names = implode(', ', array_map(fn($c) => "`$c`", $cols));
        $marks = implode(', ', array_fill(0, count($cols), '?'));
        $stmt = $conn->prepare("INSERT INTO courses ($names) VALUES ($marks)");
        $types = str_repeat('s', count($cols));
        $params = array_values($vals);
    }
    if (!$stmt) pm_admin_fail('Database error: ' . $conn->error, 500);
    $stmt->bind_param($types, ...$params);
    if (!$stmt->execute()) pm_admin_fail('Save failed: ' . $stmt->error, 500);
    pm_admin_ok(['id' => $id > 0 ? $id : $stmt->insert_id]);
}

/* ---------- one switch / dropdown ---------- */
if ($action === 'flag') {
    $id = pm_course_id();
    $field = (string)pm_in('field');
    if ($field === 'btn_type') {
        $v = (string)pm_in('value');
        if (!in_array($v, $BTN_TYPES, true)) pm_admin_fail('Bad button type.');
        $stmt = $conn->prepare('UPDATE courses SET btn_type = ? WHERE id = ?');
        $stmt->bind_param('si', $v, $id);
    } elseif (in_array($field, $FLAG_FIELDS, true)) {
        $v = ((string)pm_in('value') === '1' || pm_in('value') === true) ? 1 : 0;
        $stmt = $conn->prepare("UPDATE courses SET `$field` = ? WHERE id = ?");
        $stmt->bind_param('ii', $v, $id);
    } else {
        pm_admin_fail('Unknown field.');
    }
    if (!$stmt->execute()) pm_admin_fail('Update failed: ' . $stmt->error, 500);
    pm_admin_ok();
}

/* ---------- copy ---------- */
if ($action === 'duplicate') {
    $id = pm_course_id();
    $res = $conn->query('SELECT * FROM courses WHERE id = ' . $id);
    $row = $res ? $res->fetch_assoc() : null;
    if (!$row) pm_admin_fail('Product not found.', 404);
    unset($row['id']);
    $row['title'] = $row['title'] . ' (copy)';
    $row['is_deleted'] = 0;
    $conn->query('UPDATE courses SET sort_order = sort_order + 1');
    $row['sort_order'] = 1;
    $cols = array_keys($row);
    $names = implode(', ', array_map(fn($c) => "`$c`", $cols));
    $marks = implode(', ', array_fill(0, count($cols), '?'));
    $stmt = $conn->prepare("INSERT INTO courses ($names) VALUES ($marks)");
    $stmt->bind_param(str_repeat('s', count($cols)), ...array_values($row));
    if (!$stmt->execute()) pm_admin_fail('Copy failed: ' . $stmt->error, 500);
    pm_admin_ok(['id' => $stmt->insert_id]);
}

/* ---------- trash / restore / delete ---------- */
if ($action === 'trash' || $action === 'restore') {
    $id = pm_course_id();
    $flag = $action === 'trash' ? 1 : 0;
    $stmt = $conn->prepare('UPDATE courses SET is_deleted = ? WHERE id = ?');
    $stmt->bind_param('ii', $flag, $id);
    if (!$stmt->execute()) pm_admin_fail('Failed: ' . $stmt->error, 500);
    pm_admin_ok();
}

if ($action === 'delete') {
    $id = pm_course_id();
    $stmt = $conn->prepare('DELETE FROM courses WHERE id = ? AND is_deleted = 1');   // only from the trash
    $stmt->bind_param('i', $id);
    if (!$stmt->execute()) pm_admin_fail('Failed: ' . $stmt->error, 500);
    if ($stmt->affected_rows < 1) pm_admin_fail('Move it to trash first.');
    pm_admin_ok();
}

/* ---------- take the product away from every student (a copy is kept in user_courses_revoked) ---------- */
if ($action === 'revoke_all') {
    $id = pm_course_id();
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

    $removed = (int)pm_scalar($conn, "SELECT COUNT(*) FROM user_courses WHERE course_id = $id");
    $hasAt = pm_has_column($conn, 'user_courses', 'purchased_at');
    $stmt = $conn->prepare($hasAt
        ? 'INSERT INTO user_courses_revoked (user_email, course_id, purchased_at, revoked_by) SELECT user_email, course_id, purchased_at, ? FROM user_courses WHERE course_id = ?'
        : 'INSERT INTO user_courses_revoked (user_email, course_id, revoked_by) SELECT user_email, course_id, ? FROM user_courses WHERE course_id = ?');
    $stmt->bind_param('si', $adminEmail, $id);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare('DELETE FROM user_courses WHERE course_id = ?');
    $stmt->bind_param('i', $id);
    if (!$stmt->execute()) pm_admin_fail('Failed: ' . $stmt->error, 500);
    pm_admin_ok(['removed' => $removed]);
}

pm_admin_fail('Unknown action.');
