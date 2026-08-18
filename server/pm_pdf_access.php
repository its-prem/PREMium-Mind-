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

require_once __DIR__ . '/pm_load_secrets.php';

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
 * Returns every active (non-deleted) course whose pdf_file resolves to
 * this exact path, each with its id + allow_download + app_only flags.
 *
 * More than one course can legitimately point at the same uploaded PDF
 * (e.g. the same notes re-listed under a different category/price), and
 * those courses can have different allow_download / app_only settings.
 * Callers must check every match the user is enrolled in, not just the
 * first one — otherwise a paying user can get "not enrolled" simply
 * because an unrelated course sharing the same file happened to come
 * back first from this query.
 */
function pm_find_courses_for_pdf(mysqli $conn, string $file): array {
    if ($file === '') return [];

    $result = $conn->query("SELECT id, pdf_file, allow_download, app_only FROM courses WHERE pdf_file IS NOT NULL AND pdf_file <> '' AND is_deleted = 0");
    if (!$result) return [];

    $matches = [];
    while ($row = $result->fetch_assoc()) {
        if (pm_pdf_matches((string)$row['pdf_file'], $file)) {
            $matches[] = [
                'id' => (int)$row['id'],
                'allow_download' => !empty($row['allow_download']) && (int)$row['allow_download'] === 1,
                'app_only' => !empty($row['app_only']) && (int)$row['app_only'] === 1,
            ];
        }
    }
    return $matches;
}

/**
 * Origins that only the native (Capacitor) app WebView can send — a normal
 * browser visiting the website can never present one of these, since
 * browsers set Origin to the page's real https:// domain and JS cannot
 * override it. Used to gate app_only courses at the file-serving layer,
 * not just at purchase time.
 *
 * Caveat: this is only a real guarantee against a *browser*. A raw HTTP
 * client (curl, Postman) can set any Origin/Referer header it wants, so
 * this alone does not stop someone who deliberately sets out to impersonate
 * the app. pm_app_signature_valid() below is the real guarantee once the
 * app is updated to send it; until then this Origin check is the best
 * available signal and is kept as a fallback even after that.
 */
function pm_native_app_origins(): array {
    return ['https://localhost', 'http://localhost', 'capacitor://localhost', 'ionic://localhost'];
}

function pm_is_native_app_origin(): bool {
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($origin !== '' && in_array($origin, pm_native_app_origins(), true)) return true;

    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    foreach (pm_native_app_origins() as $appOrigin) {
        if ($referer !== '' && strpos($referer, $appOrigin) === 0) return true;
    }
    return false;
}

/**
 * The app is a Capacitor WebView pointing at the *live Netlify site*
 * (see pm-app-gate.js: "Capacitor bridge (remote Netlify URL inside
 * app)") — so its Origin is the same premind.netlify.app used by
 * regular browsers, not a capacitor://localhost-style origin.
 * pm_native_app_origins() alone therefore misclassifies real app
 * traffic as "website" and was blocking paying app users from their
 * own app_only courses.
 *
 * The User-Agent is the one signal pm-app-gate.js's own isNativeApp()
 * already relies on for this exact case — mirror it here.
 */
function pm_is_native_app_user_agent(): bool {
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    if ($ua === '' || stripos($ua, 'Android') === false) return false;
    return (bool)preg_match('/; wv\)/i', $ua)
        || stripos($ua, 'Capacitor') !== false
        || stripos($ua, 'premiummind') !== false;
}

/**
 * Real proof the request came from the Android app: it must send
 * X-PM-App-Ts (current unix time) and X-PM-App-Sign =
 * hex(HMAC-SHA256(ts, app_shared_secret)). Unlike Origin/Referer, a raw
 * HTTP client cannot produce a valid signature without knowing the secret
 * compiled into the app — see APP_ONLY_COURSES_SETUP.txt for what the app
 * needs to send. Returns false (not an error) until the app is updated to
 * send this and app_shared_secret is configured in pm_secrets.php — the
 * feature quietly does nothing until both sides are wired up.
 */
function pm_app_signature_valid(): bool {
    $secret = pm_app_shared_secret();
    if ($secret === '') return false;

    $ts = $_SERVER['HTTP_X_PM_APP_TS'] ?? '';
    $sign = $_SERVER['HTTP_X_PM_APP_SIGN'] ?? '';
    if ($ts === '' || $sign === '' || !ctype_digit((string)$ts)) return false;

    // Reject stale/replayed signatures — 5 minute window.
    if (abs(time() - (int)$ts) > 300) return false;

    $expected = hash_hmac('sha256', (string)$ts, $secret);
    return hash_equals($expected, strtolower($sign));
}

/** True if this request can be trusted as coming from the native app. */
function pm_is_native_app_request(): bool {
    return pm_app_signature_valid() || pm_is_native_app_origin() || pm_is_native_app_user_agent();
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

    $courses = pm_find_courses_for_pdf($conn, $file);
    if (empty($courses)) return false; // File doesn't belong to any active course

    $isNativeApp = pm_is_native_app_request();
    $chk = $conn->prepare("SELECT 1 FROM user_courses WHERE user_email = ? AND course_id = ? LIMIT 1");
    if (!$chk) return false;

    foreach ($courses as $course) {
        if (!empty($course['app_only']) && !$isNativeApp) {
            continue; // this particular course is app-exclusive; try other matches
        }
        $chk->bind_param('si', $email, $course['id']);
        $chk->execute();
        // Must free each result before re-executing the same statement for
        // the next course, or mysqli throws "Commands out of sync" on the
        // second execute() (only shows up once a file has 2+ courses).
        $result = $chk->get_result();
        $found = $result ? $result->fetch_row() : null;
        if ($result) $result->free();
        if ($found) {
            $chk->close();
            return true;
        }
    }
    $chk->close();
    return false;
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
    $email = strtolower(trim($email));
    $courses = pm_find_courses_for_pdf($conn, $file);
    if (empty($courses)) return false;

    if (pm_pdf_admin_bypass($email)) {
        foreach ($courses as $course) {
            if ($course['allow_download']) return true;
        }
        return false;
    }

    $chk = $conn->prepare("SELECT 1 FROM user_courses WHERE user_email = ? AND course_id = ? LIMIT 1");
    if (!$chk) return false;

    foreach ($courses as $course) {
        if (!$course['allow_download']) continue;
        $chk->bind_param('si', $email, $course['id']);
        $chk->execute();
        $result = $chk->get_result();
        $found = $result ? $result->fetch_row() : null;
        if ($result) $result->free();
        if ($found) {
            $chk->close();
            return true;
        }
    }
    $chk->close();
    return false;
}
