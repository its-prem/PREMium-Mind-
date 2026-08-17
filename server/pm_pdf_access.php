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
 * Returns the owning course's id + allow_download + app_only flags, or
 * null if no active course has this exact PDF.
 */
function pm_find_course_for_pdf(mysqli $conn, string $file): ?array {
    if ($file === '') return null;

    $result = $conn->query("SELECT id, pdf_file, allow_download, app_only FROM courses WHERE pdf_file IS NOT NULL AND pdf_file <> '' AND is_deleted = 0");
    if (!$result) return null;

    while ($row = $result->fetch_assoc()) {
        if (pm_pdf_matches((string)$row['pdf_file'], $file)) {
            return [
                'id' => (int)$row['id'],
                'allow_download' => !empty($row['allow_download']) && (int)$row['allow_download'] === 1,
                'app_only' => !empty($row['app_only']) && (int)$row['app_only'] === 1,
            ];
        }
    }
    return null;
}

/**
 * Origins that only the native (Capacitor) app WebView can send — a normal
 * browser visiting the website can never present one of these, since
 * browsers set Origin to the page's real https:// domain and JS cannot
 * override it. Used to gate app_only courses at the file-serving layer,
 * not just at purchase time.
 */
function pm_native_app_origins(): array {
    return ['https://localhost', 'http://localhost', 'capacitor://localhost', 'ionic://localhost'];
}

function pm_is_native_app_request(): bool {
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($origin !== '' && in_array($origin, pm_native_app_origins(), true)) return true;

    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    foreach (pm_native_app_origins() as $appOrigin) {
        if ($referer !== '' && strpos($referer, $appOrigin) === 0) return true;
    }
    return false;
}

/**
 * Returns true only if a non-deleted course owns this exact PDF, the email
 * is enrolled in that course (or is an admin), AND — when the course is
 * marked app_only — the request actually comes from the native app, not a
 * regular browser. Marking a course app_only in the admin panel previously
 * only blocked the *purchase* flow (create_order.php); the PDF endpoints
 * never checked it, so an enrolled email could still open/download the
 * file from any normal website browser.
 */
function pm_user_can_access_pdf(mysqli $conn, string $email, string $file): bool {
    $email = strtolower(trim($email));
    if ($email === '' || $file === '') return false;
    if (pm_pdf_admin_bypass($email)) return true;

    $course = pm_find_course_for_pdf($conn, $file);
    if ($course === null) return false; // File doesn't belong to any active course

    if (!empty($course['app_only']) && !pm_is_native_app_request()) {
        return false; // App-exclusive course; reject website/browser requests
    }

    $chk = $conn->prepare("SELECT 1 FROM user_courses WHERE user_email = ? AND course_id = ? LIMIT 1");
    if (!$chk) return false;
    $chk->bind_param('si', $email, $course['id']);
    $chk->execute();
    $ok = (bool)$chk->get_result()->fetch_row();
    $chk->close();
    return $ok;
}

/**
 * Whether this email is allowed to use purpose=download for this PDF.
 * Deliberately follows the course's own allow_download setting for
 * everyone, including admins — admin emails only bypass the enrollment
 * check above (so they can view/QA any course without being "enrolled"),
 * not this per-course download toggle. This is the authoritative source;
 * the client's `download` URL parameter is just a UI hint and must never
 * be trusted.
 */
function pm_user_can_download_pdf(mysqli $conn, string $email, string $file): bool {
    $course = pm_find_course_for_pdf($conn, $file);
    return $course !== null && $course['allow_download'];
}
