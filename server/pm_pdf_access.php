<?php
/**
 * Shared PDF entitlement check.
 * Upload to Hostinger premind/ as: pm_pdf_access.php
 *
 * Confirms an email is actually enrolled in the course that owns a given
 * PDF before a token is issued or the file is streamed. Without this,
 * knowing a course's pdf_file path (or a self-declared email) was enough
 * to read/download any paid PDF.
 */

$PM_PDF_ADMIN_EMAILS = ['premku0237@gmail.com', 'ar0319515@gmail.com'];

function pm_pdf_admin_bypass(string $email): bool {
    global $PM_PDF_ADMIN_EMAILS;
    $email = strtolower(trim($email));
    foreach ($PM_PDF_ADMIN_EMAILS as $admin) {
        if ($email === strtolower(trim($admin))) return true;
    }
    return false;
}

/** True if a course's stored pdf_file value resolves to the same normalized path as $wantFile. */
function pm_pdf_matches(string $dbValue, string $wantFile): bool {
    $dbValue = str_replace('\\', '/', trim($dbValue));
    if ($dbValue === '') return false;

    if (preg_match('#^https?://#i', $dbValue)) {
        $path = parse_url($dbValue, PHP_URL_PATH) ?: '';
        $dbValue = ltrim($path, '/');
    }
    $dbValue = ltrim($dbValue, '/');
    if (strpos($dbValue, '/') === false && preg_match('/\.pdf$/i', $dbValue)) {
        $dbValue = 'uploads/pdfs/' . $dbValue;
    }

    return strcasecmp($dbValue, $wantFile) === 0;
}

/**
 * $file must already be normalized to the "uploads/pdfs/..." form.
 * Returns true only if a non-deleted course owns this exact PDF and the
 * email is enrolled in that course (or is an admin).
 */
function pm_user_can_access_pdf(mysqli $conn, string $email, string $file): bool {
    $email = strtolower(trim($email));
    if ($email === '' || $file === '') return false;
    if (pm_pdf_admin_bypass($email)) return true;

    $result = $conn->query("SELECT id, pdf_file FROM courses WHERE pdf_file IS NOT NULL AND pdf_file <> '' AND is_deleted = 0");
    if (!$result) return false;

    $courseId = null;
    while ($row = $result->fetch_assoc()) {
        if (pm_pdf_matches((string)$row['pdf_file'], $file)) {
            $courseId = (int)$row['id'];
            break;
        }
    }
    if ($courseId === null) return false; // File doesn't belong to any active course

    $chk = $conn->prepare("SELECT 1 FROM user_courses WHERE user_email = ? AND course_id = ? LIMIT 1");
    if (!$chk) return false;
    $chk->bind_param('si', $email, $courseId);
    $chk->execute();
    $ok = (bool)$chk->get_result()->fetch_row();
    $chk->close();
    return $ok;
}
