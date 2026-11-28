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

require_once __DIR__ . '/pm_admin_emails.php';
$PM_PDF_ADMIN_EMAILS = pm_admin_email_list();   // from .env ADMIN_EMAILS

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

    // download_at = the time (India time) when a "download on time" product unlocks its download. The column may not exist yet.
    $hasAt = false;
    $col = @$conn->query("SHOW COLUMNS FROM courses LIKE 'download_at'");
    if ($col && $col->num_rows > 0) $hasAt = true;
    $result = $conn->query("SELECT id, pdf_file, allow_download, app_only" . ($hasAt ? ", download_at" : "") . " FROM courses WHERE pdf_file IS NOT NULL AND pdf_file <> '' AND is_deleted = 0");
    if (!$result) return [];
    $nowIst = new DateTime('now', new DateTimeZone('Asia/Kolkata'));

    $matches = [];
    while ($row = $result->fetch_assoc()) {
        if (pm_pdf_matches((string)$row['pdf_file'], $file)) {
            $matches[] = [
                'id' => (int)$row['id'],
                'allow_download' => (!empty($row['allow_download']) && (int)$row['allow_download'] === 1)
                    || (!empty($row['download_at']) && strpos((string)$row['download_at'], '0000') !== 0
                        && (new DateTime((string)$row['download_at'], new DateTimeZone('Asia/Kolkata'))) <= $nowIst),
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
 * Real proof the request came from the Android app.
 *
 * The app keeps a secret inside its own code (never in the website) and signs EVERY request it sends to this server:
 *   X-PM-App-Ts   = current unix time (seconds)
 *   X-PM-App-Sign = hex( HMAC-SHA256( "<ts>|<METHOD>|<path>", app_shared_secret ) )
 * <path> is the URL path of this very request (e.g. /create_order.php), so a signature made for one request cannot
 * be reused for another endpoint, and it is only valid for 5 minutes. A browser or curl cannot make it without the
 * secret. The secret lives in .env (APP_SHARED_SECRET) or pm_secrets.php ('app_shared_secret').
 */
function pm_app_signature_valid(): bool {
    $secret = pm_app_shared_secret();
    if ($secret === '') return false;

    $ts = $_SERVER['HTTP_X_PM_APP_TS'] ?? '';
    $sign = strtolower(trim((string)($_SERVER['HTTP_X_PM_APP_SIGN'] ?? '')));
    if ($ts === '' || $sign === '' || !ctype_digit((string)$ts) || !preg_match('/^[0-9a-f]{64}$/', $sign)) return false;
    if (abs(time() - (int)$ts) > 300) return false;   // stale or replayed

    $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    $path   = (string)(parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?? '');
    $expected = hash_hmac('sha256', $ts . '|' . $method . '|' . $path, $secret);
    return hash_equals($expected, $sign);
}

/**
 * True if this request can be trusted as coming from the native app.
 *
 * APP_SIGNATURE_REQUIRED=1 in .env  -> ONLY a valid signature counts (secure; turn it on once everybody has the new app).
 * otherwise (the transition)        -> the old hints (WebView user-agent / app origin) still count, so the app that is
 *                                      already installed keeps working. Those hints can be faked by anyone.
 */
function pm_is_native_app_request(): bool {
    if (pm_app_signature_valid()) return true;
    if (pm_env('APP_SIGNATURE_REQUIRED') === '1') return false;
    return pm_is_native_app_origin() || pm_is_native_app_user_agent();
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

/**
 * The real path of a PDF, but only when it sits inside uploads/pdfs (no ../, no symlink out of the folder).
 * Returns the safe real path, or '' when the file is outside / missing.
 */
if (!function_exists('pm_pdf_contain')) {
    function pm_pdf_contain(string $fullPath): string {
        $real = realpath($fullPath);
        if ($real === false) return '';
        foreach ([__DIR__, getcwd() ?: __DIR__] as $base) {
            $root = realpath($base . '/uploads/pdfs');
            if ($root !== false && strpos($real, $root . DIRECTORY_SEPARATOR) === 0) return $real;
        }
        return '';
    }
}
