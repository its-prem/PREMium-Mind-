<?php
// ════════════════════════════════════════════════
//  PREMium Mind — Professional Admin Dashboard
// ════════════════════════════════════════════════

$PM_ADMIN_EMAILS = ['premku0237@gmail.com', 'ar0319515@gmail.com'];
$PM_FIREBASE_PROJECT_ID = 'premium-mind-fcb16';
// Same web API key as website Firebase config (used only to verify ID tokens server-side)
$PM_FIREBASE_API_KEY = 'AIzaSyBMF_RAmopbnPC7OpNcJCo2CUGS6CDiMEY';

if (session_status() !== PHP_SESSION_ACTIVE) {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function pm_is_admin_email($email, $list) {
    $email = strtolower(trim((string)$email));
    foreach ($list as $allowed) {
        if ($email === strtolower(trim((string)$allowed))) return true;
    }
    return false;
}

/** Safe for plain HTML text/attribute content (student names, emails, messages, etc). */
function pm_h($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

/**
 * Safe to splice directly into onclick='fn(...)' as a JS string argument —
 * json_encode() adds + escapes the quotes, then htmlspecialchars() makes
 * the whole thing safe as an HTML attribute value. Do not add extra quotes
 * around the result.
 */
function pm_js_attr($v) {
    return htmlspecialchars(json_encode((string)$v), ENT_QUOTES, 'UTF-8');
}

/** Button Style options — kept in sync with the edit form's <select>. */
function pm_btn_type_options(): array {
    return [
        'normal'        => 'Normal (Black clickable)',
        'coming_soon'   => 'Coming Soon (Unclickable)',
        'disabled_look' => 'Disabled Look (Orange)',
        'preview_buy'   => 'Preview (Demo) + Buy',
        'disabled'      => 'Completely Disabled',
    ];
}

/**
 * ON/OFF pill for a boolean flag column in the course list. Clicking only
 * stages the change client-side (data-original vs data-value) — nothing is
 * written until "Update All Changes" posts them in one batch.
 */
function pm_flag_toggle(int $id, string $field, bool $isOn, string $icon, string $label): string {
    $val = $isOn ? '1' : '0';
    return "<button type='button' class='flag-btn" . ($isOn ? " is-on" : "") . "'"
        . " data-id='$id' data-field='" . pm_h($field) . "' data-original='$val' data-value='$val'"
        . " onclick='toggleFlagBtn(this)' title='Click to turn " . pm_h($label) . " " . ($isOn ? 'OFF' : 'ON') . "'>"
        . "<ion-icon name='" . pm_h($icon) . "'></ion-icon>"
        . "<span class='flag-btn-label'>" . pm_h($label) . "</span>"
        . "<span class='flag-btn-state'>" . ($isOn ? 'ON' : 'OFF') . "</span>"
        . "</button>";
}

/** Button Style dropdown in the list — staged the same way as the toggles. */
function pm_btn_type_select(int $id, string $current): string {
    $opts = pm_btn_type_options();
    if (!isset($opts[$current])) $current = 'normal';

    $html = "<div class='flag-select-row' data-flag-row='$id:btn_type'>"
        . "<ion-icon name='color-palette-outline'></ion-icon>"
        . "<span class='flag-select-label'>Button</span>"
        . "<select class='flag-select' data-id='$id' data-field='btn_type' data-original='" . pm_h($current) . "' onchange='markFlagDirty(this)'>";
    foreach ($opts as $val => $labelText) {
        $html .= "<option value='" . pm_h($val) . "'" . ($val === $current ? " selected" : "") . ">" . pm_h($labelText) . "</option>";
    }
    return $html . "</select></div>";
}

function pm_http_request($url, $method = 'GET', $jsonBody = null) {
    $method = strtoupper($method);
    $headers = [];
    $body = null;
    if ($jsonBody !== null) {
        $body = json_encode($jsonBody);
        $headers[] = 'Content-Type: application/json';
    }

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_CUSTOMREQUEST => $method,
        ];
        if ($headers) $opts[CURLOPT_HTTPHEADER] = $headers;
        if ($body !== null) $opts[CURLOPT_POSTFIELDS] = $body;
        curl_setopt_array($ch, $opts);
        $resp = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        return ['ok' => ($resp !== false && $code > 0 && $code < 500), 'code' => $code, 'body' => $resp, 'err' => $err];
    }

    $http = [
        'method' => $method,
        'timeout' => 15,
        'ignore_errors' => true,
        'header' => implode("\r\n", $headers),
    ];
    if ($body !== null) $http['content'] = $body;
    $ctx = stream_context_create(['http' => $http]);
    $resp = @file_get_contents($url, false, $ctx);
    $code = 0;
    if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m)) {
        $code = (int)$m[1];
    }
    return ['ok' => ($resp !== false), 'code' => $code, 'body' => $resp, 'err' => ''];
}

function pm_b64url_decode($data) {
    $remainder = strlen($data) % 4;
    if ($remainder) $data .= str_repeat('=', 4 - $remainder);
    return base64_decode(strtr($data, '-_', '+/'));
}

function pm_jwt_payload($jwt) {
    $parts = explode('.', (string)$jwt);
    if (count($parts) !== 3) return null;
    $json = pm_b64url_decode($parts[1]);
    if ($json === false || $json === '') return null;
    $data = json_decode($json, true);
    return is_array($data) ? $data : null;
}

/**
 * Verify Firebase ID token.
 * Returns ['ok'=>true,'data'=>...] or ['ok'=>false,'reason'=>...]
 */
function pm_verify_firebase_id_token($idToken, $projectId, $apiKey) {
    $idToken = trim((string)$idToken);
    if ($idToken === '' || substr_count($idToken, '.') !== 2) {
        return ['ok' => false, 'reason' => 'Token missing or malformed.'];
    }

    // 1) Preferred: Identity Toolkit accounts:lookup (works well on shared hosting)
    if ($apiKey) {
        $url = 'https://identitytoolkit.googleapis.com/v1/accounts:lookup?key=' . urlencode($apiKey);
        $res = pm_http_request($url, 'POST', ['idToken' => $idToken]);
        if (!empty($res['body'])) {
            $data = json_decode((string)$res['body'], true);
            if (is_array($data) && !empty($data['users'][0]['email'])) {
                $user = $data['users'][0];
                return [
                    'ok' => true,
                    'data' => [
                        'email' => $user['email'],
                        'email_verified' => !empty($user['emailVerified']),
                        'name' => $user['displayName'] ?? 'Admin',
                        'user_id' => $user['localId'] ?? '',
                    ],
                ];
            }
            if (is_array($data) && !empty($data['error']['message'])) {
                // keep trying other methods, but remember reason
                $lookupErr = (string)$data['error']['message'];
            }
        }
    }

    // 2) Fallback: Google tokeninfo
    $info = pm_http_request('https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($idToken), 'GET');
    if (!empty($info['body'])) {
        $data = json_decode((string)$info['body'], true);
        if (is_array($data) && empty($data['error']) && !empty($data['email'])) {
            $aud = (string)($data['aud'] ?? '');
            $iss = (string)($data['iss'] ?? '');
            $audOk = ($aud === $projectId);
            $issOk = (
                $iss === ('https://securetoken.google.com/' . $projectId) ||
                $iss === 'accounts.google.com' ||
                $iss === 'https://accounts.google.com'
            );
            if (!$audOk && !$issOk) {
                return ['ok' => false, 'reason' => 'Token audience mismatch (aud=' . $aud . ').'];
            }
            if (!empty($data['exp']) && time() >= ((int)$data['exp'] + 30)) {
                return ['ok' => false, 'reason' => 'Token expired.'];
            }
            return ['ok' => true, 'data' => $data];
        }
    }

    // 3) Last resort: decode JWT claims locally (Hostinger blocked Google APIs)
    $claims = pm_jwt_payload($idToken);
    if (is_array($claims) && !empty($claims['email'])) {
        $aud = (string)($claims['aud'] ?? '');
        $iss = (string)($claims['iss'] ?? '');
        $exp = (int)($claims['exp'] ?? 0);
        $issOk = ($iss === ('https://securetoken.google.com/' . $projectId));
        $audOk = ($aud === $projectId);
        if (!$issOk || !$audOk) {
            return ['ok' => false, 'reason' => 'JWT claims invalid (iss/aud).'];
        }
        if ($exp && time() >= ($exp + 30)) {
            return ['ok' => false, 'reason' => 'Token expired.'];
        }
        return ['ok' => true, 'data' => $claims];
    }

    $reason = 'Could not verify token with Google.';
    if (!empty($lookupErr)) $reason .= ' (' . $lookupErr . ')';
    if (!empty($res['err'])) $reason .= ' curl: ' . $res['err'];
    if (!empty($info['err'])) $reason .= ' tokeninfo: ' . $info['err'];
    return ['ok' => false, 'reason' => $reason];
}

function pm_establish_admin_session($email, $name, $via = 'firebase') {
    global $PM_ADMIN_EMAILS;
    $email = strtolower(trim((string)$email));
    if (!pm_is_admin_email($email, $PM_ADMIN_EMAILS)) return false;
    session_regenerate_id(true);
    $_SESSION['pm_admin_email'] = $email;
    $_SESSION['pm_admin_name'] = trim((string)$name) !== '' ? trim((string)$name) : 'Admin';
    $_SESSION['pm_admin_via'] = $via;
    $_SESSION['pm_admin_login_at'] = time();
    return true;
}

function pm_admin_session_ok() {
    global $PM_ADMIN_EMAILS;
    return !empty($_SESSION['pm_admin_email']) && pm_is_admin_email($_SESSION['pm_admin_email'], $PM_ADMIN_EMAILS);
}

function pm_require_admin($asJson = true) {
    if (pm_admin_session_ok()) return;
    http_response_code(403);
    if ($asJson) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['status' => 'error', 'msg' => 'Unauthorized. Admin login required.']);
    } else {
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Unauthorized. Admin login required.';
    }
    exit;
}

// Logout
if (isset($_GET['logout'])) {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'] ?? '', $p['secure'], $p['httponly']);
    }
    session_destroy();
    header('Location: admin_panel.php');
    exit;
}

// Secure login: Firebase ID token (from admin Google login OR app SSO)
if (isset($_POST['ajax_admin_login'])) {
    header('Content-Type: application/json; charset=utf-8');
    $idToken = isset($_POST['id_token']) ? (string)$_POST['id_token'] : '';
    $verified = pm_verify_firebase_id_token($idToken, $PM_FIREBASE_PROJECT_ID, $PM_FIREBASE_API_KEY);
    if (empty($verified['ok'])) {
        http_response_code(401);
        echo json_encode([
            'status' => 'error',
            'msg' => 'Invalid or expired login token.',
            'reason' => $verified['reason'] ?? 'unknown',
        ]);
        exit;
    }
    $payload = $verified['data'];
    $email = strtolower(trim((string)$payload['email']));
    $name = trim((string)($_POST['name'] ?? ($payload['name'] ?? 'Admin')));
    if (!pm_establish_admin_session($email, $name, 'firebase')) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'msg' => 'Access denied for ' . $email]);
        exit;
    }
    echo json_encode([
        'status' => 'success',
        'email' => $_SESSION['pm_admin_email'],
        'name' => $_SESSION['pm_admin_name'],
    ]);
    exit;
}

// App / website → admin with Firebase ID token (POST preferred; GET supported)
if (
    (isset($_POST['app_admin_sso']) && (string)$_POST['app_admin_sso'] === '1' && !empty($_POST['id_token']))
    || !empty($_GET['id_token'])
) {
    $idToken = !empty($_POST['id_token']) ? (string)$_POST['id_token'] : (string)$_GET['id_token'];
    $verified = pm_verify_firebase_id_token($idToken, $PM_FIREBASE_PROJECT_ID, $PM_FIREBASE_API_KEY);
    if (!empty($verified['ok'])) {
        $payload = $verified['data'];
        $email = strtolower(trim((string)$payload['email']));
        $name = trim((string)($payload['name'] ?? 'Admin'));
        if (pm_establish_admin_session($email, $name, 'app_token')) {
            header('Location: admin_panel.php');
            exit;
        }
        header('Location: admin_panel.php?auth_error=denied');
        exit;
    }
    $reason = urlencode((string)($verified['reason'] ?? 'token'));
    header('Location: admin_panel.php?auth_error=1&reason=' . $reason);
    exit;
}

$pmAdminSessionOk = pm_admin_session_ok();
$pmAdminSessionEmail = $pmAdminSessionOk ? (string)$_SESSION['pm_admin_email'] : '';
$pmAdminSessionName  = $pmAdminSessionOk ? (string)($_SESSION['pm_admin_name'] ?? 'Admin') : '';

// --- DB CONNECTION ---
include 'db_connect.php'; 
$conn->set_charset('utf8mb4');

// Ensure app_only column exists (buy/access only in Android app)
$colCheck = @$conn->query("SHOW COLUMNS FROM courses LIKE 'app_only'");
if ($colCheck && $colCheck->num_rows === 0) {
    @$conn->query("ALTER TABLE courses ADD COLUMN app_only TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = buy/open only in Android app'");
}

// Ensure show_preview column exists (1-page PDF preview on store card)
$colPrev = @$conn->query("SHOW COLUMNS FROM courses LIKE 'show_preview'");
if ($colPrev && $colPrev->num_rows === 0) {
    @$conn->query("ALTER TABLE courses ADD COLUMN show_preview TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = show 1-page PDF preview on store card'");
}

// Custom T&C text per course (empty = use default sample on website)
$colTnc = @$conn->query("SHOW COLUMNS FROM courses LIKE 'tnc_text'");
if ($colTnc && $colTnc->num_rows === 0) {
    @$conn->query("ALTER TABLE courses ADD COLUMN tnc_text TEXT NULL COMMENT 'Custom T&C for this course; empty = default sample'");
}

// Custom message when student clicks Download (locked / info)
$colDlMsg = @$conn->query("SHOW COLUMNS FROM courses LIKE 'download_msg'");
if ($colDlMsg && $colDlMsg->num_rows === 0) {
    @$conn->query("ALTER TABLE courses ADD COLUMN download_msg TEXT NULL COMMENT 'Custom text shown when Download is clicked (esp. if locked)'");
}

// Index store ranking (lower number = higher on homepage)
$colSort = @$conn->query("SHOW COLUMNS FROM courses LIKE 'sort_order'");
if ($colSort && $colSort->num_rows === 0) {
    @$conn->query("ALTER TABLE courses ADD COLUMN sort_order INT NOT NULL DEFAULT 0 COMMENT 'Homepage display rank; lower = higher'");
    // Keep current homepage order (was newest id first)
    $initSort = @$conn->query("SELECT id FROM courses ORDER BY id DESC");
    if ($initSort) {
        $rank = 1;
        while ($sr = $initSort->fetch_assoc()) {
            $sid = (int)$sr['id'];
            @$conn->query("UPDATE courses SET sort_order=$rank WHERE id=$sid");
            $rank++;
        }
    }
}

// ════════════════════════════════════════════════
//  AJAX HANDLERS (API ENDPOINTS) — auth required
// ════════════════════════════════════════════════

// 1. FETCH COURSES
if (isset($_GET['fetch_table'])) {
    pm_require_admin(false);
    $result = $conn->query("SELECT * FROM courses ORDER BY sort_order ASC, id DESC");
    // db_connect.php now sets MYSQLI_REPORT_OFF, so a failed query returns
    // false instead of throwing — calling ->num_rows on that is a fatal
    // (blank 500), which is indistinguishable from a network error in the
    // browser. Fail with a readable message instead.
    if (!$result) {
        http_response_code(500);
        echo "<tr><td colspan='5' style='text-align:center;padding:24px;color:#991b1b;'>Query failed: "
            . pm_h($conn->error) . "</td></tr>";
        exit;
    }
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $json = htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8');
            
            // Mobile optimized badges
            $pdf_status = !empty($row['pdf_file']) 
                ? "<span class='badge-success' style='display:inline-flex; align-items:center; gap:4px; margin-bottom:6px; white-space:nowrap;'>📄 PDF Uploaded</span>" 
                : "<span class='badge-danger' style='display:inline-flex; align-items:center; gap:4px; margin-bottom:6px; white-space:nowrap;'>No PDF</span>";
            
            $dl_on = !empty($row['allow_download']) && (int)$row['allow_download'] === 1;
            $dl_status = pm_flag_toggle((int)$row['id'], 'allow_download', $dl_on, 'download-outline', 'Download');

            $app_only_on = !empty($row['app_only']) && (int)$row['app_only'] === 1;
            $app_only_status = pm_flag_toggle((int)$row['id'], 'app_only', $app_only_on, 'phone-portrait-outline', 'App Only');

            $preview_on = !empty($row['show_preview']) && (int)$row['show_preview'] === 1;
            $preview_status = pm_flag_toggle((int)$row['id'], 'show_preview', $preview_on, 'eye-outline', 'Preview');

            $btn_type_status = pm_btn_type_select((int)$row['id'], (string)($row['btn_type'] ?? 'normal'));

            // Check if course is deleted
            $is_deleted = isset($row['is_deleted']) && $row['is_deleted'] == 1;
            $row_style = $is_deleted ? "opacity: 0.6; background: #fef2f2;" : "";
            $status_badge = $is_deleted ? "<div style='margin-top:6px;'><span class='badge-danger' style='white-space:nowrap;'>🗑️ Trashed</span></div>" : "<div style='margin-top:6px;'><span class='badge-success' style='white-space:nowrap;'>🟢 Active</span></div>";

            echo "<tr style='$row_style' class='fade-in'>
                <td style='font-weight:700;'>
                    <div style='font-size:0.75rem;color:var(--dark-muted);'>ID #".$row['id']."</div>
                    <div style='margin-top:4px;background:#eef2ff;color:#3730a3;display:inline-block;padding:3px 8px;border-radius:8px;font-size:0.8rem;font-weight:800;'>Rank ".(int)($row['sort_order'] ?? 0)."</div>
                </td>
                <td class='course-info-td'>
                    <img src='".$row['image']."' onerror='this.src=\"small-logo.png\"'>
                    <div>
                        <div style='font-weight: 700; color: var(--dark); font-size: 0.95rem; line-height:1.2; margin-bottom:4px;'>".$row['title']."</div>
                        <span style='background: var(--gray-soft); padding: 3px 8px; border-radius: 6px; font-size: 0.75rem; font-weight: 700; color: var(--primary); white-space:nowrap;'>".strtoupper($row['category'])."</span>
                        $status_badge
                    </div>
                </td>
                <td style='white-space:nowrap;'>
                    <strong style='color: var(--dark); font-size: 1.1rem;'>₹".$row['price']."</strong><br>
                    <strike style='color: var(--gray-dark); font-size: 0.8rem;'>₹".$row['old_price']."</strike>
                </td>
                <td>
                    <div style='display:flex; flex-direction:column; align-items:flex-start;'>
                        $pdf_status
                        $dl_status
                        $app_only_status
                        $preview_status
                        $btn_type_status
                    </div>
                </td>
                <td>
                    <div class='action-buttons'>";
                    
            if ($is_deleted) {
                echo "<button class='btn-icon btn-edit' style='background:#dcfce7; color:#166534;' onclick='restoreCard(".$row['id'].")' title='Restore Course'><ion-icon name='refresh-outline'></ion-icon></button>
                      <button class='btn-icon btn-delete' onclick='hardDeleteCard(".$row['id'].")' title='Permanent Delete'><ion-icon name='trash-bin-outline'></ion-icon></button>";
            } else {
                echo "<button class='btn-icon btn-edit' data-course='$json' onclick='editCard(this)' title='Edit'><ion-icon name='create-outline'></ion-icon></button>
                      <button class='btn-icon btn-delete' onclick='deleteCard(".$row['id'].")' title='Move to Trash'><ion-icon name='trash-outline'></ion-icon></button>";
            }

            echo "  </div>
                </td>
            </tr>";
        }
    } else {
        echo "<tr><td colspan='5' style='text-align:center;padding:30px;color:var(--gray-dark);'>No courses in database yet.</td></tr>";
    }
    exit;
}

// 1b. FETCH RANKING LIST (active courses only — homepage order)
if (isset($_GET['fetch_ranking'])) {
    pm_require_admin(false);
    header('Content-Type: application/json; charset=utf-8');
    $result = $conn->query("SELECT id, title, category, image, price, sort_order FROM courses WHERE is_deleted=0 ORDER BY sort_order ASC, id DESC");
    $rows = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) $rows[] = $row;
    }
    echo json_encode(['status' => 'success', 'courses' => $rows]);
    exit;
}

// 1c. SAVE HOMEPAGE RANKING ORDER
if (isset($_POST['ajax_save_ranking'])) {
    pm_require_admin(true);
    $raw = $_POST['order_ids'] ?? '[]';
    $ids = json_decode($raw, true);
    if (!is_array($ids) || count($ids) === 0) {
        echo json_encode(['status' => 'error', 'msg' => 'Invalid order list']);
        exit;
    }
    // Single UPDATE ... CASE instead of one query per course — reordering
    // ~25 courses was 25 separate round-trips to MySQL, which is what made
    // saving the ranking feel slow. Every value here is cast to int first.
    $rank = 1;
    $cases = '';
    $idList = [];
    foreach ($ids as $cid) {
        $cid = (int)$cid;
        if ($cid <= 0) continue;
        $cases .= " WHEN $cid THEN $rank";
        $idList[] = $cid;
        $rank++;
    }

    if (empty($idList)) {
        echo json_encode(['status' => 'error', 'msg' => 'Invalid order list']);
        exit;
    }

    $inList = implode(',', $idList);
    $ok = $conn->query("UPDATE courses SET sort_order = CASE id$cases END WHERE id IN ($inList) AND is_deleted = 0");
    echo $ok
        ? json_encode(['status' => 'success', 'ranked' => count($idList)])
        : json_encode(['status' => 'error', 'msg' => $conn->error]);
    exit;
}

// 2. FETCH STUDENTS
if (isset($_GET['fetch_students'])) {
    pm_require_admin(false);
    $search = isset($_GET['search']) ? $conn->real_escape_string(trim($_GET['search'])) : '';
    $where  = $search ? "WHERE u.name LIKE '%$search%' OR u.email LIKE '%$search%'" : '';

    $result = $conn->query("SELECT u.id, u.name, u.email, u.is_active, u.created_at, GROUP_CONCAT(uc.course_id) AS enrolled_ids FROM users u LEFT JOIN user_courses uc ON uc.user_email = u.email $where GROUP BY u.id ORDER BY u.id DESC LIMIT 50");
    
    $courses = $conn->query("SELECT id, title FROM courses WHERE is_deleted=0 ORDER BY id ASC");
    $courseList = [];
    // Same MYSQLI_REPORT_OFF caveat as fetch_table — guard against false.
    if ($courses) {
        while ($c = $courses->fetch_assoc()) $courseList[] = $c;
    }

    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $enrolled = $row['enrolled_ids'] ? explode(',', $row['enrolled_ids']) : [];
            $verified = $row['is_active'] ? "<span class='badge-success'>✓ Verified</span>" : "<span class='badge-warning'>⚠ Pending</span>";
            $created = $row['created_at'] ? date('d M Y', strtotime($row['created_at'])) : 'Google Auth';
            $enrolledCount = count(array_filter($enrolled));

            $toggles = '';
            foreach ($courseList as $c) {
                $chk = in_array($c['id'], $enrolled) ? 'checked' : '';
                $toggles .= "
                <div class='ct-item'>
                    <span class='ct-name'>".pm_h($c['title'])."</span>
                    <label class='switch'>
                        <input type='checkbox' class='course-chk' data-uemail='".pm_h($row['email'])."' data-cid='".$c['id']."' $chk>
                        <span class='slider'></span>
                    </label>
                </div>";
            }

            $accId = "acc_" . $row['id'];

            echo "
            <div class='user-card fade-in'>
                <div class='user-header'>
                    <div class='u-avatar'>".pm_h(strtoupper(substr($row['name'], 0, 1)))."</div>
                    <div class='u-details'>
                        <div class='u-name-row'>
                            <h4>".pm_h($row['name'])."</h4>
                            $verified
                        </div>
                        <p><ion-icon name='mail-outline'></ion-icon> ".pm_h($row['email'])."</p>
                        <p><ion-icon name='calendar-outline'></ion-icon> Joined: $created</p>
                    </div>
                </div>

                <div class='user-enroll-meta'>
                    <span>Enrolled Courses:</span> <span style='color:var(--success); font-size:1rem;'>$enrolledCount</span>
                </div>

                <button class='btn-outline' onclick='toggleAccordion(\"$accId\", this)'>Manage Access <ion-icon name='chevron-down-outline'></ion-icon></button>

                <div class='course-toggle-list' id='$accId'>
                    $toggles
                    <button class='btn-submit' style='margin-top:15px; padding:10px; font-size:0.85rem;' onclick='saveEnrollment(".pm_js_attr($row['email']).", this)'>💾 Save Changes</button>
                </div>
            </div>";
        }
    } else {
        echo "<div style='grid-column:1/-1; text-align:center; padding:40px; color:var(--gray-dark); background:white; border-radius:16px; border:1px dashed #cbd5e1;'>No students found matching your search.</div>";
    }
    exit;
}

// 3. FETCH REPORTS
if (isset($_GET['fetch_reports'])) {
    pm_require_admin(false);
    $sql = "SELECT r.*, c.title as course_title, u.name as user_name, u.phone as user_phone
            FROM reports r 
            LEFT JOIN courses c ON r.course_id = c.id 
            LEFT JOIN users u ON r.email = u.email
            WHERE r.status = 'pending' 
            ORDER BY r.id ASC";
            
    $result = $conn->query($sql);

    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $date = date('d M Y, h:i A', strtotime($row['created_at']));
            $screenshot = $row['screenshot'];
            $name = pm_h(!empty($row['user_name']) ? $row['user_name'] : 'Unknown User');
            $phone = pm_h(!empty($row['user_phone']) ? $row['user_phone'] : 'N/A');
            $msg = htmlspecialchars($row['message']);
            $subject_name = pm_h(!empty($row['course_title']) ? $row['course_title'] : 'Unknown Course (ID: ' . $row['course_id'] . ')');

            echo "
            <div class='user-card fade-in' style='border-color: #fca5a5;'>
                <div style='display:flex; gap:12px; align-items:center; margin-bottom:15px; background:#fef2f2; padding:12px; border-radius:10px; border: 1px dashed #fca5a5;'>
                    <div style='font-size:1.8rem;'>📚</div>
                    <div>
                        <div style='font-size:0.75rem; color:#be123c; font-weight:800;'>ISSUE IN SUBJECT:</div>
                        <div style='font-size:0.95rem; color:#111; font-weight:800; line-height:1.2;'>$subject_name</div>
                    </div>
                </div>

                <div class='u-details' style='margin-bottom:15px;'>
                    <h4 style='font-size:1.1rem; color:var(--dark); margin-bottom:5px;'><ion-icon name='person'></ion-icon> $name</h4>
                    <p style='color:var(--primary); font-weight:600;'><ion-icon name='mail'></ion-icon> ".pm_h($row['email'])."</p>
                    <p><ion-icon name='call'></ion-icon> $phone</p>

                    <div style='margin-top:12px; padding:12px; background:var(--gray-soft); border-radius:8px; font-size:0.9rem; color:var(--dark); border:1px solid var(--gray-border);'>
                        <strong style='color:#be123c;'>Problem Description:</strong><br>$msg
                    </div>
                    <small style='display:block; margin-top:8px; color:var(--gray-dark); font-weight:600;'><ion-icon name='time'></ion-icon> Reported on: $date</small>
                </div>";

                if(!empty($screenshot)) {
                    echo "<button class='btn-outline' style='border-color:#fda4af; color:#be123c; background:#fff1f2; margin-bottom:12px;' onclick='viewImage(".pm_js_attr($screenshot).")'><ion-icon name='image'></ion-icon> View Screenshot</button>";
                }
                
                echo "
                <div style='display:flex; flex-direction:column; gap:10px; border-top: 1px dashed var(--gray-border); padding-top: 15px;'>
                    <button class='btn-outline' style='border-color:#93c5fd; color:#1d4ed8; background:#eff6ff;' onclick='goToStudentAccess(".pm_js_attr($row['email']).")'><ion-icon name='open'></ion-icon> Open Student Profile</button>
                    <button class='btn-submit' style='background:var(--success);' onclick='resolveReport(".$row['id'].")'><ion-icon name='checkmark-circle'></ion-icon> Mark Issue as Resolved</button>
                </div>
            </div>";
        }
    } else {
        echo "<div style='grid-column:1/-1; text-align:center; padding:50px; color:var(--gray-dark); background:white; border-radius:16px; border:1px dashed #cbd5e1;'><ion-icon name='checkmark-done-circle' style='font-size:50px; color:var(--success);'></ion-icon><h3 style='margin-top:10px; color:var(--dark);'>All Caught Up!</h3><p>No pending reports.</p></div>";
    }
    exit;
}

// 3b. FETCH RECENT PURCHASES (live sales feed on the dashboard)
if (isset($_GET['fetch_recent_purchases'])) {
    pm_require_admin(true);
    header('Content-Type: application/json; charset=utf-8');

    // user_courses rows come from three places and not all of them fill
    // purchased_at (the webhook does; verify_payment.php and the admin's
    // manual enrolment don't), so sort real timestamps first and fall back
    // to insertion order for the rest.
    $hasPurchasedAt = false;
    $chk = @$conn->query("SHOW COLUMNS FROM user_courses LIKE 'purchased_at'");
    if ($chk && $chk->num_rows > 0) $hasPurchasedAt = true;

    $hasId = false;
    $chkId = @$conn->query("SHOW COLUMNS FROM user_courses LIKE 'id'");
    if ($chkId && $chkId->num_rows > 0) $hasId = true;

    $orderParts = [];
    if ($hasPurchasedAt) {
        $orderParts[] = 'uc.purchased_at IS NULL ASC';
        $orderParts[] = 'uc.purchased_at DESC';
    }
    if ($hasId) $orderParts[] = 'uc.id DESC';
    if (empty($orderParts)) $orderParts[] = 'uc.user_email DESC';
    $orderBy = implode(', ', $orderParts);

    $purchasedCol = $hasPurchasedAt ? 'uc.purchased_at' : 'NULL';
    $rowKey = $hasId ? 'uc.id' : "CONCAT(uc.user_email, '-', uc.course_id)";

    $sql = "SELECT $rowKey AS row_key,
                   uc.user_email,
                   uc.course_id,
                   $purchasedCol AS purchased_at,
                   u.name  AS user_name,
                   c.title AS course_title,
                   c.price AS course_price,
                   c.image AS course_image
            FROM user_courses uc
            LEFT JOIN users u   ON u.email = uc.user_email
            LEFT JOIN courses c ON c.id = uc.course_id
            ORDER BY $orderBy
            LIMIT 20";

    $result = $conn->query($sql);
    $rows = [];
    if ($result) {
        while ($r = $result->fetch_assoc()) $rows[] = $r;
    }

    echo json_encode(['status' => 'success', 'purchases' => $rows]);
    exit;
}

// 4. ADD OR EDIT COURSE
if (isset($_POST['ajax_add'])) {
    pm_require_admin(true);
    $edit_id   = $conn->real_escape_string($_POST['edit_id']);
    $title     = $conn->real_escape_string($_POST['title']);
    $category  = $conn->real_escape_string($_POST['category']); 
    $badge     = $conn->real_escape_string($_POST['badge']);
    $desc1     = $conn->real_escape_string($_POST['desc1']);
    $desc2     = $conn->real_escape_string($_POST['desc2']);
    $price     = $conn->real_escape_string($_POST['price']);
    $old_price = $conn->real_escape_string($_POST['old_price']);
    $link      = $conn->real_escape_string($_POST['link']);
    $demo_link = $conn->real_escape_string($_POST['demo_link']);
    $website_link = $conn->real_escape_string($_POST['website_link'] ?? '');
    $btn_text  = $conn->real_escape_string($_POST['btn_text']);
    $btn_type  = $conn->real_escape_string($_POST['btn_type']);
    $allow_dl  = isset($_POST['allow_download']) ? 1 : 0;
    $show_tnc  = isset($_POST['show_tnc']) ? 1 : 0;
    $show_report_btn = isset($_POST['show_report_btn']) ? 1 : 0;
    $app_only  = isset($_POST['app_only']) ? 1 : 0;
    $show_preview = isset($_POST['show_preview']) ? 1 : 0;
    $tnc_text = $conn->real_escape_string(trim((string)($_POST['tnc_text'] ?? '')));
    $download_msg = $conn->real_escape_string(trim((string)($_POST['download_msg'] ?? '')));

    // Empty (not just missing) means the admin cleared it with the X button,
    // so fall back to the placeholder rather than storing a blank image path.
    $existing_image_raw = trim((string)($_POST['existing_image'] ?? ''));
    if ($existing_image_raw === '') $existing_image_raw = 'small-logo.png';
    $image_path = $conn->real_escape_string($existing_image_raw);
    $pdf_path = $conn->real_escape_string(trim((string)($_POST['existing_pdf'] ?? '')));
    
    if (isset($_FILES['image_file']) && $_FILES['image_file']['error'] == 0) {
        $upload_dir_img = 'uploads/thumbnails/';
        if (!is_dir($upload_dir_img)) @mkdir($upload_dir_img, 0777, true);
        $img_filename = time() . '_' . preg_replace("/[^a-zA-Z0-9.]/", "_", basename($_FILES['image_file']['name']));
        if (move_uploaded_file($_FILES['image_file']['tmp_name'], $upload_dir_img . $img_filename)) {
            $image_path = "https://premind.diplomawallah.in/" . $upload_dir_img . $img_filename; 
        }
    }

    if (isset($_FILES['pdf_file']) && $_FILES['pdf_file']['error'] == 0) {
        $upload_dir_pdf = 'uploads/pdfs/';
        if (!is_dir($upload_dir_pdf)) @mkdir($upload_dir_pdf, 0777, true);
        $pdf_filename = time() . '_' . preg_replace("/[^a-zA-Z0-9.]/", "_", basename($_FILES['pdf_file']['name']));
        if (move_uploaded_file($_FILES['pdf_file']['tmp_name'], $upload_dir_pdf . $pdf_filename)) {
            $pdf_path = $conn->real_escape_string($upload_dir_pdf . $pdf_filename);
        }
    }

    if (!empty($edit_id)) {
        $sql = "UPDATE courses SET title='$title', category='$category', image='$image_path',badge='$badge',desc1='$desc1',desc2='$desc2',price='$price',old_price='$old_price',link='$link',demo_link='$demo_link', website_link='$website_link', pdf_file='$pdf_path', allow_download='$allow_dl', btn_text='$btn_text',btn_type='$btn_type', show_tnc='$show_tnc', show_report_btn='$show_report_btn', app_only='$app_only', show_preview='$show_preview', tnc_text='$tnc_text', download_msg='$download_msg' WHERE id='$edit_id'";
    } else {
        // New card → homepage pe pehle dikhe (rank 1), baaki shift
        @$conn->query("UPDATE courses SET sort_order = sort_order + 1");
        $sql = "INSERT INTO courses (title,category,image,badge,desc1,desc2,price,old_price,link,demo_link,website_link,pdf_file,allow_download,btn_text,btn_type,show_tnc,show_report_btn,app_only,show_preview,tnc_text,download_msg,sort_order) VALUES ('$title','$category','$image_path','$badge','$desc1','$desc2','$price','$old_price','$link','$demo_link','$website_link','$pdf_path','$allow_dl','$btn_text','$btn_type','$show_tnc','$show_report_btn','$app_only','$show_preview','$tnc_text','$download_msg',1)";
    }
    
    echo $conn->query($sql) ? json_encode(['status'=>'success']) : json_encode(['status'=>'error','msg'=>$conn->error]);
    exit;
}

// 5. SOFT DELETE COURSE (Move to Trash)
if (isset($_POST['ajax_delete'])) {
    pm_require_admin(true);
    $id = $conn->real_escape_string($_POST['delete_id']);
    echo $conn->query("UPDATE courses SET is_deleted=1 WHERE id='$id'") ? json_encode(['status'=>'success']) : json_encode(['status'=>'error']);
    exit;
}

// 5.5 RESTORE COURSE (Remove from Trash)
if (isset($_POST['ajax_restore'])) {
    pm_require_admin(true);
    $id = $conn->real_escape_string($_POST['restore_id']);
    echo $conn->query("UPDATE courses SET is_deleted=0 WHERE id='$id'") ? json_encode(['status'=>'success']) : json_encode(['status'=>'error']);
    exit;
}

// 5.6 PERMANENT HARD DELETE
if (isset($_POST['ajax_hard_delete'])) {
    pm_require_admin(true);
    $id = $conn->real_escape_string($_POST['delete_id']);
    echo $conn->query("DELETE FROM courses WHERE id='$id'") ? json_encode(['status'=>'success']) : json_encode(['status'=>'error']);
    exit;
}

// 5.7 BULK SAVE of the Download / App Only / Preview dropdowns in the list.
// The UI stages every change and posts them together, so one click can
// update many courses at once without opening the Edit form.
if (isset($_POST['ajax_bulk_update_flags'])) {
    pm_require_admin(true);
    $changes = json_decode((string)($_POST['changes'] ?? '[]'), true);
    if (!is_array($changes) || count($changes) === 0) {
        echo json_encode(['status' => 'error', 'msg' => 'No changes to save']);
        exit;
    }

    $allowedFlags = ['allow_download', 'app_only', 'show_preview'];
    $allowedBtnTypes = array_keys(pm_btn_type_options());
    // One prepared statement per column (field names can't be bound), then
    // reused for every row changing that column.
    $stmts = [];
    $updated = 0;
    $ok = true;

    foreach ($changes as $ch) {
        $id = (int)($ch['id'] ?? 0);
        $field = (string)($ch['field'] ?? '');
        if ($id <= 0) continue;

        if ($field === 'btn_type') {
            $strValue = (string)($ch['value'] ?? '');
            if (!in_array($strValue, $allowedBtnTypes, true)) continue;
            if (!isset($stmts[$field])) {
                $stmts[$field] = $conn->prepare("UPDATE courses SET btn_type = ? WHERE id = ?");
                if (!$stmts[$field]) { $ok = false; break; }
            }
            $stmts[$field]->bind_param('si', $strValue, $id);
        } elseif (in_array($field, $allowedFlags, true)) {
            $intValue = ((string)($ch['value'] ?? '0') === '1') ? 1 : 0;
            if (!isset($stmts[$field])) {
                $stmts[$field] = $conn->prepare("UPDATE courses SET $field = ? WHERE id = ?");
                if (!$stmts[$field]) { $ok = false; break; }
            }
            $stmts[$field]->bind_param('ii', $intValue, $id);
        } else {
            continue;
        }

        if (!$stmts[$field]->execute()) { $ok = false; break; }
        $updated++;
    }

    foreach ($stmts as $s) { $s->close(); }
    echo json_encode($ok
        ? ['status' => 'success', 'updated' => $updated]
        : ['status' => 'error', 'msg' => $conn->error ?: 'Update failed']);
    exit;
}

// 6. UPDATE ENROLLMENT
if (isset($_POST['ajax_enrollment'])) {
    pm_require_admin(true);
    $userEmail = $conn->real_escape_string($_POST['user_email']);
    $courses = json_decode($_POST['courses'], true);

    $conn->query("DELETE FROM user_courses WHERE user_email = '$userEmail'");

    if (!empty($courses)) {
        $stmt = $conn->prepare("INSERT INTO user_courses (user_email, course_id) VALUES (?, ?)");
        foreach ($courses as $cid) {
            $cid = (int)$cid;
            $stmt->bind_param("si", $userEmail, $cid);
            $stmt->execute();
        }
    }
    echo json_encode(['status' => 'success']);
    exit;
}

// 7. RESOLVE REPORT
if (isset($_POST['ajax_resolve_report'])) {
    pm_require_admin(true);
    $report_id = (int)$_POST['report_id'];
    echo $conn->query("UPDATE reports SET status='resolved' WHERE id=$report_id") ? json_encode(['status'=>'success']) : json_encode(['status'=>'error']);
    exit;
}

// --- STATS (only when authenticated; hide numbers from public page view) ---
if ($pmAdminSessionOk) {
    $totalStudents = $conn->query("SELECT COUNT(*) as c FROM users")->fetch_assoc()['c'];
    $totalCourses  = $conn->query("SELECT COUNT(*) as c FROM courses WHERE is_deleted=0")->fetch_assoc()['c'];
    $trashedCourses = $conn->query("SELECT COUNT(*) as c FROM courses WHERE is_deleted=1")->fetch_assoc()['c'];
    $verifiedCount = $conn->query("SELECT COUNT(*) as c FROM users WHERE is_active=1")->fetch_assoc()['c'];
    $pendingReportsResult = $conn->query("SELECT COUNT(*) as c FROM reports WHERE status='pending'");
    $pendingReports = $pendingReportsResult ? $pendingReportsResult->fetch_assoc()['c'] : 0;
} else {
    $totalStudents = $totalCourses = $trashedCourses = $verifiedCount = $pendingReports = 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>PREMium Mind – Secure Admin Dashboard</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<script type="module" src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.esm.js"></script>
<script nomodule src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.js"></script>

<style>
/* ==========================================
   CSS VARIABLES & GLOBAL RESETS
========================================== */
:root {
    --primary: #2563eb;
    --primary-dark: #1d4ed8;
    --primary-light: #eff6ff;
    --dark: #0f172a;
    --dark-muted: #334155;
    --gray-bg: #f8fafc;
    --gray-border: #e2e8f0;
    --gray-soft: #f1f5f9;
    --danger: #ef4444;
    --success: #10b981;
    --warning: #f59e0b;
    --white: #ffffff;
    
    --shadow-sm: 0 1px 3px rgba(0,0,0,0.05);
    --shadow-md: 0 4px 6px -1px rgba(0,0,0,0.1);
    --shadow-lg: 0 10px 25px -5px rgba(0,0,0,0.1);
    --smooth: 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

html { scroll-behavior: smooth; }
* { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Plus Jakarta Sans', sans-serif; }
body { background: var(--gray-bg); color: var(--dark); display: none; overflow-x: hidden; }

::-webkit-scrollbar { width: 6px; height: 6px; }
::-webkit-scrollbar-track { background: transparent; }
::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
::-webkit-scrollbar-thumb:hover { background: #94a3b8; }

.fade-in { animation: fadeIn 0.3s ease forwards; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }

/* ==========================================
   LAYOUT: SIDEBAR + MAIN CONTENT
========================================== */
.admin-layout { display: flex; height: 100vh; overflow: hidden; }

/* Sidebar */
.sidebar { 
    width: 280px; background: var(--white); border-right: 1px solid var(--gray-border); 
    display: flex; flex-direction: column; transition: var(--smooth); z-index: 100;
    flex-shrink: 0;
}
.sidebar-header { padding: 24px; border-bottom: 1px solid var(--gray-border); display: flex; align-items: center; justify-content: space-between; }
.nav-list { list-style: none; padding: 20px 12px; flex: 1; overflow-y: auto; }
.nav-link { 
    display: flex; align-items: center; gap: 12px; padding: 14px 16px; border-radius: 12px;
    color: var(--dark-muted); text-decoration: none; font-weight: 600; font-size: 0.95rem; transition: var(--smooth);
    margin-bottom: 8px;
}
.nav-link ion-icon { font-size: 1.4rem; }
.nav-link:hover, .nav-link.active { background: var(--primary-light); color: var(--primary); }
.nav-badge { background: var(--danger); color: white; padding: 2px 8px; border-radius: 20px; font-size: 0.75rem; font-weight: 700; margin-left: auto; }

/* Mobile Overlay for Sidebar */
.sidebar-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 90; backdrop-filter: blur(2px); transition: var(--smooth); opacity: 0;}
.sidebar-overlay.active { display: block; opacity: 1; }

/* Main Content Area */
.main-content { flex: 1; display: flex; flex-direction: column; overflow-y: auto; background: var(--gray-bg); height: 100vh; min-width: 0; }
.top-header { background: var(--white); padding: 15px 30px; border-bottom: 1px solid var(--gray-border); display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 50; gap: 10px; flex-wrap: wrap; }
.mobile-toggle { display: none; font-size: 28px; cursor: pointer; color: var(--dark); flex-shrink: 0; }

/* ==========================================
   TAB PANELS & FORMS
========================================== */
.tab-panel { display: none; padding: 30px; }
.tab-panel.active { display: block; animation: fadeIn 0.3s ease; }
.section-title { font-size: 1.5rem; font-weight: 800; color: var(--dark); margin-bottom: 25px; display: flex; align-items: center; gap: 10px; }

/* Centered Form Layout without Preview */
.main-layout { display: flex; justify-content: center; margin-bottom: 30px; }
.form-section { width: 100%; max-width: 800px; background: var(--white); padding: 30px; border-radius: 20px; border: 1px solid var(--gray-border); box-shadow: var(--shadow-sm); }

/* Forms */
.row { display: flex; gap: 15px; }
.row > div { flex: 1; min-width: 0; }
label { font-weight: 700; font-size: 0.85rem; display: block; margin-bottom: 8px; color: var(--dark-muted); }
input[type=text], input[type=number], select { width: 100%; padding: 12px 16px; margin-bottom: 20px; border: 1.5px solid #cbd5e1; border-radius: 12px; font-size: 0.95rem; outline: none; transition: var(--smooth); background: var(--gray-soft); }
input:focus, select:focus { border-color: var(--primary); background: white; box-shadow: 0 0 0 4px var(--primary-light); }
.file-upload-box { border: 2px dashed #cbd5e1; padding: 25px; border-radius: 16px; text-align: center; background: var(--gray-soft); margin-bottom: 20px; cursor: pointer; transition: var(--smooth); position: relative; overflow: hidden; }
.file-upload-box:hover { border-color: var(--primary); background: var(--primary-light); }
.file-upload-box input[type="file"] { position: absolute; inset: 0; opacity: 0; cursor: pointer; z-index: 10; width: 100%; height: 100%; }
.file-name-display { font-size: 0.85rem; color: var(--primary); font-weight: 700; margin-top: 10px; word-break: break-all; }
.file-hint { font-size: 0.75rem; color: var(--dark-muted); margin-top: 6px; font-weight: 500; }
.upload-error-text { font-size: 0.8rem; color: var(--danger); font-weight: 700; margin-top: 8px; display: none; }

/* Toggles */
.toggle-wrapper { display: flex; align-items: center; gap: 15px; margin-bottom: 20px; background: var(--gray-soft); padding: 15px; border-radius: 12px; border: 1px solid var(--gray-border); }
.switch { position: relative; display: inline-block; width: 44px; height: 24px; flex-shrink: 0; }
.switch input { opacity: 0; width: 0; height: 0; }
.slider { position: absolute; cursor: pointer; inset: 0; background: #cbd5e1; transition: .4s; border-radius: 34px; }
.slider:before { position: absolute; content: ""; height: 18px; width: 18px; left: 3px; bottom: 3px; background: white; transition: .4s; border-radius: 50%; box-shadow: var(--shadow-sm); }
input:checked + .slider { background: var(--success); }
input:checked + .slider:before { transform: translateX(20px); }
.toggle-info h4 { font-size: 0.95rem; font-weight: 700; margin-bottom: 2px; }
.toggle-info p { font-size: 0.8rem; color: var(--dark-muted); margin: 0; }

/* Buttons */
.btn-submit { width: 100%; padding: 14px 16px; background: var(--dark); color: white; font-size: 1rem; font-weight: 700; border: none; border-radius: 12px; cursor: pointer; transition: var(--smooth); display: flex; align-items: center; justify-content: center; gap: 8px; box-shadow: var(--shadow-sm); }
.btn-submit:hover { transform: translateY(-2px); box-shadow: var(--shadow-md); background: var(--primary); }
.btn-submit:disabled { opacity: 0.7; cursor: not-allowed; transform: none; }
.btn-cancel { background: white; color: var(--danger); border: 2px solid var(--danger); display: none; box-shadow: none; }
.btn-cancel:hover { background: #fef2f2; color: var(--danger); }
.btn-outline { width: 100%; background: transparent; border: 1.5px solid var(--gray-border); padding: 12px; border-radius: 12px; font-weight: 700; font-size: 0.9rem; cursor: pointer; transition: var(--smooth); display: flex; align-items: center; justify-content: center; gap: 8px; color: var(--dark); }
.btn-outline:hover { background: var(--gray-soft); border-color: var(--dark-muted); }

/* Badges */
.badge-success { background: #dcfce7; color: #166534; padding: 4px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 700; }
.badge-warning { background: #fef3c7; color: #92400e; padding: 4px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 700; }
.badge-danger { background: #fee2e2; color: #991b1b; padding: 4px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 700; }

/* ON/OFF pills (Download / App Only / Preview) — staged, saved in bulk */
.flag-btn {
    display: inline-flex; align-items: center; gap: 6px; margin-top: 6px;
    padding: 5px 10px; border-radius: 10px; border: 1.5px solid #e2e8f0;
    background: #f1f5f9; color: #64748b; font-size: 11px; font-weight: 800;
    white-space: nowrap; cursor: pointer; font-family: inherit;
    transition: var(--smooth); min-width: 148px;
}
.flag-btn:hover { transform: translateY(-1px); box-shadow: var(--shadow-sm); }
.flag-btn:active { transform: translateY(0); }
.flag-btn ion-icon { font-size: 14px; flex-shrink: 0; }
.flag-btn .flag-btn-label { flex: 1; text-align: left; }
.flag-btn .flag-btn-state {
    padding: 2px 8px; border-radius: 6px; background: #e2e8f0;
    color: #475569; font-size: 10px; letter-spacing: 0.03em;
}
.flag-btn.is-on { background: #eff6ff; border-color: #bfdbfe; color: #1d4ed8; }
.flag-btn.is-on .flag-btn-state { background: var(--success); color: #fff; }
.flag-btn.is-dirty { background: #fef3c7; border-color: #f59e0b; color: #92400e; box-shadow: 0 0 0 2px #fde68a; }

/* Button Style dropdown in the list */
.flag-select-row {
    display: flex; align-items: center; gap: 6px; margin-top: 6px;
    padding: 4px 8px; border-radius: 10px; background: #f1f5f9;
    color: #64748b; font-size: 11px; font-weight: 700; white-space: nowrap;
    transition: var(--smooth);
}
.flag-select-row.is-on { background: #eff6ff; color: #1d4ed8; }
.flag-select-row.is-dirty { background: #fef3c7; color: #92400e; box-shadow: 0 0 0 2px #fcd34d; }
.flag-select-row ion-icon { font-size: 14px; flex-shrink: 0; }
.flag-select-label { min-width: 46px; }
.flag-select {
    width: auto !important; margin: 0 !important; padding: 3px 22px 3px 8px !important;
    font-size: 11px !important; font-weight: 800; border-radius: 8px !important;
    border: 1.5px solid #cbd5e1 !important; background: #fff !important;
    color: inherit; cursor: pointer; font-family: inherit;
}
.flag-select:focus { border-color: var(--primary) !important; box-shadow: 0 0 0 3px var(--primary-light) !important; }

/* Sticky "save all staged changes" bar under the course table */
.asset-save-bar {
    position: sticky; bottom: 12px; z-index: 30; margin-top: 16px;
    display: flex; gap: 10px; align-items: center; flex-wrap: wrap;
    padding: 12px 14px; background: rgba(255,255,255,0.97);
    border: 2px solid #fcd34d; border-radius: 14px;
    box-shadow: 0 8px 24px rgba(15,23,42,0.15); backdrop-filter: blur(8px);
}
.asset-save-bar .dirty-count { font-weight: 800; color: #92400e; font-size: 0.9rem; flex: 1; min-width: 140px; }
.asset-save-bar .btn-submit, .asset-save-bar .btn-outline { width: auto; margin: 0; padding: 10px 18px; font-size: 0.88rem; }

/* Remove-file (X) button on the existing image / PDF chips in the form */
.file-chip {
    display: inline-flex; align-items: center; gap: 8px; margin-top: 10px;
    padding: 6px 6px 6px 12px; border-radius: 10px; background: #ecfdf5;
    border: 1px solid #a7f3d0; font-size: 0.8rem; font-weight: 700; color: #065f46;
    max-width: 100%; position: relative; z-index: 20;
}
.file-chip .chip-text { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.file-chip button {
    flex-shrink: 0; width: 24px; height: 24px; border-radius: 7px; border: none;
    background: #fee2e2; color: #991b1b; cursor: pointer; font-size: 15px;
    display: flex; align-items: center; justify-content: center; transition: var(--smooth);
}
.file-chip button:hover { background: var(--danger); color: #fff; }

/* Table */
.list-section { background: var(--white); padding: 25px; border-radius: 20px; margin-top: 30px; box-shadow: var(--shadow-sm); border: 1px solid var(--gray-border); }
.table-responsive { overflow-x: auto; -webkit-overflow-scrolling: touch; border-radius: 10px; }
.custom-table { width: 100%; border-collapse: collapse; min-width: 700px; }
.custom-table th { background: var(--gray-soft); padding: 15px; text-align: left; font-size: 0.8rem; font-weight: 700; color: var(--dark-muted); text-transform: uppercase; border-bottom: 1px solid var(--gray-border); white-space: nowrap; }
.custom-table td { padding: 15px; border-bottom: 1px solid var(--gray-border); font-size: 0.95rem; vertical-align: middle; transition: var(--smooth); }
.course-info-td { display: flex; align-items: center; gap: 15px; }
.course-info-td img { width: 60px; height: 60px; border-radius: 10px; object-fit: cover; flex-shrink: 0; }
.action-buttons { display: flex; gap: 10px; }
.btn-icon { padding: 10px; border-radius: 10px; border: none; font-size: 1.2rem; cursor: pointer; transition: var(--smooth); display: flex; align-items: center; justify-content: center; min-width: 42px; min-height: 42px; }
.btn-edit { background: var(--primary-light); color: var(--primary); }
.btn-edit:hover { background: var(--primary); color: white; transform: translateY(-2px); }
.btn-delete { background: #fef2f2; color: var(--danger); }
.btn-delete:hover { background: var(--danger); color: white; transform: translateY(-2px); }

/* Dashboard Stats Grid */
.stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; }
.stat-card { background: white; padding: 20px; border-radius: 20px; border: 1px solid var(--gray-border); display: flex; align-items: center; gap: 20px; box-shadow: var(--shadow-sm); transition: var(--smooth); }
.stat-card:hover { transform: translateY(-4px); box-shadow: var(--shadow-md); }
.stat-icon { width: 60px; height: 60px; border-radius: 16px; display: flex; justify-content: center; align-items: center; font-size: 1.8rem; flex-shrink: 0; }
.stat-info h3 { font-size: 1.8rem; font-weight: 800; color: var(--dark); line-height: 1.2; }
.stat-info p { font-size: 0.85rem; font-weight: 600; color: var(--dark-muted); }

/* ==========================================
   RECENT PURCHASES — live sales feed
========================================== */
.sales-feed-section {
    margin-top: 30px; background: var(--white); border: 1px solid var(--gray-border);
    border-radius: 20px; padding: 24px; box-shadow: var(--shadow-sm);
}
.sales-feed-header { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; }
.sales-feed-sub { font-size: 0.85rem; color: var(--dark-muted); margin: 6px 0 18px; font-weight: 500; }
.sales-refresh-btn { width: auto !important; padding: 8px 16px !important; font-size: 0.82rem !important; }

.live-dot {
    display: inline-block; width: 9px; height: 9px; border-radius: 50%;
    background: var(--success); margin-left: 4px; flex-shrink: 0;
    animation: livePulse 2s ease-in-out infinite;
}
@keyframes livePulse {
    0%, 100% { box-shadow: 0 0 0 0 rgba(16,185,129,0.55); opacity: 1; }
    70% { box-shadow: 0 0 0 8px rgba(16,185,129,0); opacity: 0.85; }
}

.sales-feed { display: flex; flex-direction: column; gap: 10px; max-height: 560px; overflow-y: auto; padding-right: 4px; }

.sale-item {
    display: flex; align-items: center; gap: 14px; padding: 14px 16px;
    background: var(--gray-bg); border: 1px solid var(--gray-border);
    border-radius: 14px; transition: var(--smooth);
    /* Each row pops in with its own stagger delay set inline from JS */
    opacity: 0; transform: translateY(14px) scale(0.96);
    animation: salePop 0.45s cubic-bezier(0.22, 1, 0.36, 1) forwards;
}
@keyframes salePop {
    from { opacity: 0; transform: translateY(14px) scale(0.96); }
    60%  { opacity: 1; transform: translateY(-3px) scale(1.012); }
    to   { opacity: 1; transform: translateY(0) scale(1); }
}
.sale-item:hover { background: #fff; border-color: #cbd5e1; box-shadow: var(--shadow-md); transform: translateY(-2px); }

/* A purchase that appeared since the last refresh */
.sale-item.is-new { border-color: #6ee7b7; background: #f0fdf4; }
.sale-item.is-new::after {
    content: 'NEW'; position: absolute; top: -7px; right: 12px;
    background: var(--success); color: #fff; font-size: 9px; font-weight: 800;
    padding: 2px 7px; border-radius: 20px; letter-spacing: 0.05em;
}
.sale-item.is-new { position: relative; }

.sale-avatar {
    width: 44px; height: 44px; border-radius: 12px; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center;
    background: var(--dark); color: #fff; font-weight: 800; font-size: 1.05rem;
    overflow: hidden;
}
.sale-avatar img { width: 100%; height: 100%; object-fit: cover; }

.sale-body { flex: 1; min-width: 0; }
.sale-name { font-weight: 800; color: var(--dark); font-size: 0.95rem; line-height: 1.25; margin-bottom: 3px;
             white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.sale-course { font-size: 0.82rem; color: var(--primary); font-weight: 700; display: flex; align-items: center; gap: 5px;
               white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.sale-course ion-icon { flex-shrink: 0; font-size: 13px; }
.sale-email { font-size: 0.75rem; color: var(--dark-muted); margin-top: 2px;
              white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

.sale-right { text-align: right; flex-shrink: 0; display: flex; flex-direction: column; align-items: flex-end; gap: 3px; }
.sale-amount { font-weight: 800; color: var(--success); font-size: 1.05rem; white-space: nowrap; }
.sale-time { font-size: 0.72rem; color: var(--dark-muted); font-weight: 600; white-space: nowrap; }

.sales-empty { text-align: center; padding: 40px 20px; color: var(--dark-muted);
               border: 1px dashed #cbd5e1; border-radius: 14px; background: var(--gray-bg); }

@media (max-width: 600px) {
    .sales-feed-section { padding: 16px; border-radius: 16px; }
    .sale-item { padding: 12px; gap: 10px; }
    .sale-avatar { width: 38px; height: 38px; font-size: 0.9rem; border-radius: 10px; }
    .sale-name { font-size: 0.88rem; }
    .sale-amount { font-size: 0.95rem; }
    .sale-email { display: none; }
}

/* Students Grid */
.toolbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; gap: 15px; flex-wrap: wrap; }
.search-box { position: relative; flex: 1; max-width: 400px; }
.search-box ion-icon { position: absolute; left: 18px; top: 50%; transform: translateY(-50%); color: var(--dark-muted); font-size: 1.2rem; }
.search-box input { width: 100%; padding: 14px 20px 14px 50px; border-radius: 50px; background: white; margin: 0; box-shadow: var(--shadow-sm); border: 1px solid var(--gray-border); }

.grid-cards { display: grid; grid-template-columns: repeat(auto-fill, minmax(min(100%, 300px), 1fr)); gap: 24px; width: 100%; }
.user-card { background: white; border: 1px solid var(--gray-border); border-radius: 20px; padding: 24px; box-shadow: var(--shadow-sm); transition: var(--smooth); display: flex; flex-direction: column; width: 100%; max-width: 100%; min-width: 0; box-sizing: border-box; overflow: hidden; }
.user-card:hover { border-color: #cbd5e1; box-shadow: var(--shadow-md); transform: translateY(-4px); }
.user-header { display: flex; gap: 12px; align-items: flex-start; margin-bottom: 15px; border-bottom: 1px dashed var(--gray-border); padding-bottom: 15px; min-width: 0; }
.u-avatar { width: 55px; height: 55px; border-radius: 50%; background: var(--dark); color: white; display: flex; justify-content: center; align-items: center; font-size: 1.4rem; font-weight: 800; flex-shrink: 0; }
.u-details { min-width: 0; flex: 1; overflow: hidden; }
.u-details .u-name-row { display: flex; justify-content: space-between; align-items: flex-start; gap: 8px; margin-bottom: 5px; flex-wrap: wrap; }
.u-details h4 { font-size: 1.1rem; font-weight: 800; color: var(--dark); margin: 0; line-height: 1.3; word-break: break-word; overflow-wrap: anywhere; flex: 1; min-width: 0; }
.u-details p { font-size: 0.85rem; color: var(--dark-muted); display: flex; align-items: flex-start; gap: 6px; margin-bottom: 2px; word-break: break-word; overflow-wrap: anywhere; }
.u-details p ion-icon { flex-shrink: 0; margin-top: 2px; }
.user-enroll-meta { background:#f8fafc; padding:10px 15px; border-radius:10px; margin-bottom:15px; font-size:0.85rem; font-weight:600; display:flex; justify-content:space-between; align-items:center; gap:10px; flex-wrap:wrap; }

.course-toggle-list { background: var(--gray-soft); border-radius: 12px; padding: 15px; margin-top: 15px; display: none; max-height: 250px; overflow-y: auto; border: 1px solid var(--gray-border); width: 100%; box-sizing: border-box; }
.ct-item { display: flex; justify-content: space-between; align-items: center; padding: 10px 0; border-bottom: 1px dashed #cbd5e1; gap: 12px; min-width: 0; }
.ct-item:last-child { border-bottom: none; }
.ct-name { font-size: 0.9rem; font-weight: 700; color: var(--dark); flex: 1; min-width: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

.form-actions-row { display: flex; gap: 14px; margin-top: 20px; align-items: stretch; }
.form-actions-row .btn-submit { width: auto; flex: 1; min-width: 0; }

/* Store Ranking — drag to reorder */
.rank-list { display: flex; flex-direction: column; gap: 10px; max-width: 720px; width: 100%; }
.rank-item {
    display: flex; align-items: center; gap: 10px; background: white; border: 1px solid var(--gray-border);
    border-radius: 14px; padding: 12px; box-shadow: var(--shadow-sm); user-select: none; -webkit-user-select: none;
    /* pan-y, not none: `none` here meant a touch anywhere on a row was
       swallowed instead of scrolling the page, so on phones the ranking
       list couldn't be scrolled at all. Dragging is handle-only anyway
       (Sortable is configured with handle: '.rank-handle'), so only the
       handle needs to opt out of native touch gestures. */
    touch-action: pan-y; width: 100%; box-sizing: border-box; min-width: 0;
}
.rank-item.rank-chosen { border-color: #818cf8; background: #f8fafc; }
.rank-item.rank-ghost { opacity: 0.35; background: #e0e7ff; border-style: dashed; }
.rank-item.rank-drag { opacity: 0.95; box-shadow: 0 12px 30px rgba(15,23,42,0.18); transform: scale(1.02); }
.rank-handle {
    width: 40px; height: 40px; border-radius: 10px; background: #f1f5f9; color: #64748b;
    display: flex; align-items: center; justify-content: center; flex-shrink: 0; cursor: grab;
    font-size: 1.35rem; border: 1px solid #e2e8f0;
    touch-action: none; /* the one spot that must not scroll — drag starts here */
}
.rank-handle:active { cursor: grabbing; background: #e0e7ff; color: #4338ca; }
.rank-num {
    width: 36px; height: 36px; border-radius: 10px; background: #eef2ff; color: #3730a3;
    display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 0.9rem; flex-shrink: 0;
}
.rank-thumb { width: 48px; height: 48px; border-radius: 10px; object-fit: cover; flex-shrink: 0; background: #f1f5f9; }
.rank-meta { flex: 1; min-width: 0; overflow: hidden; }
.rank-meta h4 {
    margin: 0 0 3px; font-size: 0.92rem; font-weight: 800; color: var(--dark); line-height: 1.25;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.rank-meta p { margin: 0; font-size: 0.75rem; color: var(--dark-muted); font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.rank-moves { display: flex; flex-direction: row; gap: 6px; flex-shrink: 0; }
.rank-moves button {
    width: 36px; height: 36px; border: none; border-radius: 10px; cursor: pointer; display: flex;
    align-items: center; justify-content: center; background: #f1f5f9; color: #0f172a; font-size: 1.05rem;
}
.rank-moves button:hover { background: #4f46e5; color: #fff; }
.rank-moves button:disabled { opacity: 0.3; cursor: not-allowed; }
.rank-save-bar {
    position: sticky; bottom: 12px; z-index: 20; margin-top: 16px; max-width: 720px;
    display: flex; gap: 10px; padding: 12px; background: rgba(255,255,255,0.95);
    border: 1px solid #c7d2fe; border-radius: 14px; box-shadow: 0 8px 24px rgba(15,23,42,0.12);
    backdrop-filter: blur(8px);
}
.rank-save-bar .btn-submit { flex: 1; margin: 0; }
.rank-help {
    background:#eef2ff; border:1px solid #c7d2fe; border-radius:14px; padding:12px 14px;
    margin-bottom:16px; font-size:0.88rem; color:#312e81; font-weight:600; max-width:720px;
}

/* Shimmer & Overlay */
.shimmer { background: #f6f7f8; background-image: linear-gradient(to right, #f6f7f8 0%, #edeef1 20%, #f6f7f8 40%, #f6f7f8 100%); background-repeat: no-repeat; background-size: 800px 100%; animation: shimmer 1.5s linear infinite; border-radius: 10px; }
@keyframes shimmer { 0% { background-position: -400px 0; } 100% { background-position: 400px 0; } }

#adminAuthOverlay { position: fixed; inset: 0; background: rgba(15, 23, 42, 0.95); z-index: 9999; display: flex; align-items: center; justify-content: center; backdrop-filter: blur(10px); padding: 20px; }
.auth-box { background: white; padding: 40px; border-radius: 24px; text-align: center; box-shadow: 0 20px 40px rgba(0,0,0,0.5); max-width: 420px; width: 90%; }
.google-login-btn { background: white; color: var(--dark); border: 2px solid var(--gray-border); padding: 14px 20px; border-radius: 12px; font-weight: 700; font-size: 1rem; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 12px; width: 100%; margin-top: 25px; transition: var(--smooth); }
.google-login-btn:hover { background: var(--gray-soft); border-color: var(--dark); transform: translateY(-2px); box-shadow: var(--shadow-sm); }

/* User Info Box */
.user-info { display: flex; align-items: center; gap: 12px; }
.user-avatar { width: 40px; height: 40px; border-radius: 50%; background: var(--primary); color: white; display: flex; justify-content: center; align-items: center; font-weight: 800; font-size: 1.2rem; flex-shrink: 0; }

/* Toast */
#toast { position: fixed; bottom: 30px; right: 30px; left: 30px; background: var(--dark); color: white; padding: 16px 25px; border-radius: 12px; font-weight: 700; font-size: 0.95rem; box-shadow: var(--shadow-lg); transform: translateY(150px); opacity: 0; transition: var(--smooth); z-index: 9999; display: flex; align-items: center; gap: 12px; margin: 0 auto; max-width: 420px;}
#toast.show { transform: translateY(0); opacity: 1; }
#toast ion-icon { font-size: 1.4rem; flex-shrink: 0; }

/* Image Viewer Modal */
#imageViewerModal { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.95); z-index:10005; align-items:center; justify-content:center; padding:20px; flex-direction:column; backdrop-filter: blur(10px); }
#viewerImage { max-width:100%; max-height:85vh; border-radius:12px; object-fit:contain; box-shadow:0 10px 40px rgba(0,0,0,0.5); }

/* ==========================================
   RESPONSIVE ADJUSTMENTS
========================================== */
@media (max-width: 1024px) {
    .sidebar { position: fixed; left: -100%; top: 0; bottom: 0; height: 100vh; box-shadow: 10px 0 30px rgba(0,0,0,0.1); }
    .sidebar.active { left: 0; }
    .main-content { margin-left: 0 !important; width: 100%; }
    .mobile-toggle { display: block; }
}

@media (max-width: 768px) {
    .row { flex-direction: column; gap: 0; }
    .form-actions-row { flex-direction: column; gap: 12px; }
    .form-actions-row .btn-submit { width: 100%; }
    .top-header { padding: 12px 16px; }
    .top-header h2 { font-size: 1.05rem; }
    .tab-panel { padding: 18px 14px; }
    .toolbar { flex-direction: column; align-items: stretch; }
    .search-box { max-width: 100%; }
    .list-section { padding: 15px; margin-top: 20px;}
    .form-section { padding: 20px; border-radius: 16px; }
    .user-info #admin-info-box { display: none !important; }
    .grid-cards { grid-template-columns: 1fr; gap: 16px; }
    .user-card { padding: 16px; border-radius: 16px; }
    .user-card:hover { transform: none; }
    .u-details h4 { font-size: 1rem; }
    .u-details p { font-size: 0.8rem; }
    .ct-name { white-space: normal; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; }
    .stats-grid { grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 12px; }
    .stat-card { padding: 15px; gap: 12px; border-radius: 16px; }
    .stat-icon { width: 46px; height: 46px; font-size: 1.4rem; border-radius: 12px; }
    .stat-info h3 { font-size: 1.4rem; }
    .action-buttons { flex-wrap: wrap; gap: 10px; }
    .rank-list { max-width: 100%; }
    .rank-item { padding: 10px; gap: 8px; }
    .rank-thumb { width: 42px; height: 42px; }
    .rank-meta h4 { font-size: 0.85rem; white-space: normal; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; }
    .rank-save-bar { bottom: 8px; left: 0; right: 0; }
    #toast { left: 15px; right: 15px; bottom: 15px; max-width: none; }
}

@media (max-width: 480px) {
    .top-header { padding: 10px 12px; }
    .top-header h2 { display: none; }
    .tab-panel { padding: 14px 10px; }
    .section-title { font-size: 1.15rem; margin-bottom: 18px; }
    .form-section { padding: 15px; }
    .form-actions-row { gap: 14px; }
    .file-upload-box { padding: 18px 12px; }
    .stats-grid { grid-template-columns: 1fr 1fr; }
    .auth-box { padding: 25px 20px; }
    .u-avatar { width: 44px; height: 44px; font-size: 1.1rem; }
    .user-card { padding: 14px; }
    .badge-success, .badge-warning, .badge-danger { font-size: 0.7rem; padding: 3px 8px; }
    .rank-handle { width: 36px; height: 36px; }
    .rank-num { width: 30px; height: 30px; font-size: 0.8rem; }
    .rank-moves button { width: 34px; height: 34px; }
}
</style>
</head>
<body>

    <div id="loading-screen" style="display: flex; height: 100vh; align-items: center; justify-content: center; flex-direction: column; gap: 15px;">
        <div class="shimmer" style="width: 50px; height: 50px; border-radius: 50%;"></div>
        <div style="font-weight: 800; color: var(--dark); font-size: 1.1rem; letter-spacing: 1px;">Authenticating Securely...</div>
    </div>

    <div id="adminAuthOverlay" style="display: none;">
        <div class="auth-box">
            <ion-icon name="lock-closed" style="font-size: 50px; color: var(--primary); margin-bottom: 15px;"></ion-icon>
            <h2 style="margin-bottom: 10px; font-weight: 800; font-size: 1.6rem;">Admin Access</h2>
            <p style="color: var(--dark-muted); font-size: 0.95rem; font-weight: 500;">Please login with your authorized administrative Google account to continue.</p>
            <button class="google-login-btn" id="adminGoogleLoginBtn">
                <img src="https://upload.wikimedia.org/wikipedia/commons/c/c1/Google_%22G%22_logo.svg" width="22" alt="Google">
                Sign in with Google
            </button>
            <p id="authErrorMsg" style="color: var(--danger); font-size: 0.85rem; font-weight: 700; margin-top: 15px; display: none; padding: 10px; background: #fee2e2; border-radius: 8px; border: 1px solid #fecaca;"></p>
        </div>
    </div>

    <div id="imageViewerModal">
        <span onclick="document.getElementById('imageViewerModal').style.display='none'" style="color:white; font-size:40px; position:absolute; top:20px; right:30px; cursor:pointer; background: rgba(255,255,255,0.1); width: 50px; height: 50px; display: flex; justify-content: center; align-items: center; border-radius: 50%;">&times;</span>
        <img id="viewerImage" src="">
    </div>

    <div class="admin-layout" id="admin-wrapper" style="display: none;">
        
        <div class="sidebar-overlay" id="sidebar-overlay"></div>

        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <img src="PM-LOGO-TEXT.png" style="height: 35px; object-fit: contain;" alt="Premium Mind" onerror="this.style.display='none'">
                </div>
                <ion-icon name="close" class="mobile-toggle" id="close-sidebar" style="color: var(--dark-muted);"></ion-icon>
            </div>

            <ul class="nav-list">
                <li class="nav-item">
                    <a href="https://premind.netlify.app/" target="_blank" rel="noopener" class="nav-link">
                        <ion-icon name="home-outline"></ion-icon> Home Page
                    </a>
                </li>
                <li class="nav-item">
                    <a href="javascript:void(0)" class="nav-link active" onclick="switchTab('dashboard', this)">
                        <ion-icon name="grid-outline"></ion-icon> Dashboard Overview
                    </a>
                </li>
                <li class="nav-item">
                    <a href="javascript:void(0)" class="nav-link" onclick="switchTab('courses', this)">
                        <ion-icon name="cube-outline"></ion-icon> Course Manager
                    </a>
                </li>
                <li class="nav-item">
                    <a href="javascript:void(0)" class="nav-link" onclick="switchTab('ranking', this)">
                        <ion-icon name="swap-vertical-outline"></ion-icon> Store Ranking
                    </a>
                </li>
                <li class="nav-item">
                    <a href="javascript:void(0)" class="nav-link" onclick="switchTab('students', this)">
                        <ion-icon name="people-outline"></ion-icon> Student Access
                    </a>
                </li>
                <li class="nav-item">
                    <a href="javascript:void(0)" class="nav-link" onclick="switchTab('reports', this)">
                        <ion-icon name="flag-outline"></ion-icon> Issue Reports
                        <?php if($pendingReports > 0) echo "<span class='nav-badge'>$pendingReports</span>"; ?>
                    </a>
                </li>
            </ul>

            <div class="sidebar-footer" style="padding: 20px;">
                <button class="btn-outline" id="adminLogoutBtn" style="color: var(--danger); border-color: var(--danger);">
                    <ion-icon name="log-out-outline"></ion-icon> Secure Logout
                </button>
            </div>
        </aside>

        <main class="main-content">
            <header class="top-header">
                <div style="display: flex; align-items: center; gap: 15px;">
                    <ion-icon name="menu-outline" class="mobile-toggle" id="open-sidebar"></ion-icon>
                    <h2 style="font-size: 1.25rem; font-weight: 800; color: var(--dark);">Admin Workspace</h2>
                </div>
                <div class="user-info">
                    <div style="text-align: right; display: none;" id="admin-info-box">
                        <div style="font-weight: 800; color: var(--dark);" id="admin-name">Admin</div>
                        <div style="color: var(--dark-muted); font-size: 0.75rem;" id="admin-email">admin@premiummind.com</div>
                    </div>
                    <div class="user-avatar" id="admin-avatar-letter">A</div>
                </div>
            </header>

            <div class="page-wrapper">

                <div id="tab-dashboard" class="tab-panel active">
                    <h2 class="section-title"><ion-icon name="stats-chart"></ion-icon> Platform Analytics</h2>
                    
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: #eff6ff; color: #2563eb;"><ion-icon name="cube"></ion-icon></div>
                            <div class="stat-info">
                                <h3><?= $totalCourses ?></h3>
                                <p>Active Live Courses</p>
                            </div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon" style="background: #ecfdf5; color: #10b981;"><ion-icon name="people"></ion-icon></div>
                            <div class="stat-info">
                                <h3><?= $totalStudents ?></h3>
                                <p>Registered Students</p>
                            </div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon" style="background: #f3e8ff; color: #a855f7;"><ion-icon name="trash"></ion-icon></div>
                            <div class="stat-info">
                                <h3><?= $trashedCourses ?></h3>
                                <p>Trashed Courses</p>
                            </div>
                        </div>
                        <div class="stat-card" style="border-color: #fca5a5;">
                            <div class="stat-icon" style="background: #fef2f2; color: #ef4444;"><ion-icon name="flag"></ion-icon></div>
                            <div class="stat-info">
                                <h3 style="color: #ef4444;"><?= $pendingReports ?></h3>
                                <p style="color: #991b1b;">Pending Issues</p>
                            </div>
                        </div>
                    </div>

                    <div class="sales-feed-section">
                        <div class="sales-feed-header">
                            <h3 class="section-title" style="margin:0;">
                                <ion-icon name="cart"></ion-icon> Recent Purchases
                                <span class="live-dot" title="Auto-refreshes every 30s"></span>
                            </h3>
                            <button type="button" class="btn-outline sales-refresh-btn" onclick="loadRecentPurchases(true)">
                                <ion-icon name="refresh-outline"></ion-icon> Refresh
                            </button>
                        </div>
                        <p class="sales-feed-sub">Latest 20 course purchases — newest first.</p>
                        <div id="salesFeed" class="sales-feed">
                            <div class="shimmer" style="height:74px;border-radius:14px;margin-bottom:10px;"></div>
                            <div class="shimmer" style="height:74px;border-radius:14px;margin-bottom:10px;"></div>
                            <div class="shimmer" style="height:74px;border-radius:14px;"></div>
                        </div>
                    </div>
                </div>

                <div id="tab-courses" class="tab-panel">
                    
                    <div class="main-layout">
                        <!-- Form Section Now Full Centered Without Preview -->
                        <div class="form-section">
                            <h3 id="form_title" class="section-title" style="margin-bottom: 25px;"><ion-icon name="add-circle"></ion-icon> Create New Course</h3>
                            
                            <form id="courseForm" enctype="multipart/form-data">
                                <input type="hidden" name="edit_id" id="edit_id" value="">

                                <div id="copyFromBox" style="background:#eef2ff; border:1px solid #c7d2fe; border-radius:14px; padding:14px 16px; margin-bottom:22px;">
                                    <div style="display:flex; align-items:center; gap:8px; margin-bottom:8px;">
                                        <ion-icon name="copy-outline" style="font-size:20px; color:#4338ca;"></ion-icon>
                                        <label style="margin:0; color:#312e81; font-weight:800;">Copy from existing card</label>
                                    </div>
                                    <select id="copyFromSelect" style="width:100%; margin-bottom:0; background:#fff; border:1.5px solid #a5b4fc;">
                                        <option value="">— Select a card to copy —</option>
                                    </select>
                                    <div class="file-hint" style="margin-top:8px; color:#4338ca;">Select karo → saara data form me aa jayega. Phir edit karke <strong>Publish</strong> dabao → naya card banega (purana same rahega).</div>
                                </div>
                                
                                <div class="form-group">
                                    <label>Course Title</label>
                                    <input type="text" name="title" id="inp_title" required placeholder="e.g. Complete Web Development">
                                </div>
                                
                                <label style="color: var(--primary);">📸 Thumbnail Image Upload</label>
                                <div class="file-upload-box" id="img-upload-box">
                                    <ion-icon name="image-outline" style="font-size: 32px; color: var(--primary); margin-bottom: 10px;"></ion-icon>
                                    <div style="font-weight: 700; color: var(--dark);">Click to Upload Thumbnail (JPG/PNG)</div>
                                    <div class="file-hint">Max size ~5 MB</div>
                                    <input type="file" name="image_file" id="inp_image_file" accept="image/*" onchange="showImgName(this)">
                                    <input type="hidden" name="existing_image" id="inp_existing_image">
                                    <div id="img-name" class="file-name-display"></div>
                                    <div id="img-chip" class="file-chip" style="display:none; background:#eff6ff; border-color:#bfdbfe; color:#1d4ed8;">
                                        <ion-icon name="image"></ion-icon>
                                        <span class="chip-text" id="img-chip-text"></span>
                                        <button type="button" onclick="removeExistingImage(event)" title="Remove this thumbnail">&times;</button>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="form-group">
                                        <label>Course Category</label>
                                        <select name="category" id="inp_category" required>
                                            <option value="notes">Notes</option>
                                            <option value="ebooks">E-Books</option>
                                            <option value="resume">Resume</option>
                                            <option value="report-writing">Report Writing</option>
                                            <option value="projects">Projects</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label>Badge Text (Optional)</label>
                                        <input type="text" name="badge" id="inp_badge" placeholder="e.g. NEW / Bestseller">
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label>Bullet Point 1</label>
                                    <input type="text" name="desc1" id="inp_desc1" placeholder="e.g. 🚧 Exam Oriented Notes">
                                </div>
                                <div class="form-group">
                                    <label>Bullet Point 2</label>
                                    <input type="text" name="desc2" id="inp_desc2" placeholder="e.g. 📄 Total Pages: 25">
                                </div>
                                
                                <div class="row">
                                    <div class="form-group">
                                        <label>Selling Price (₹)</label>
                                        <input type="number" name="price" id="inp_price" placeholder="99" required>
                                    </div>
                                    <div class="form-group">
                                        <label>Original Price (₹)</label>
                                        <input type="number" name="old_price" id="inp_old_price" placeholder="499">
                                    </div>
                                </div>

                                <label style="color: var(--success); margin-top: 10px;">📄 Course Content (PDF)</label>
                                <div class="file-upload-box" id="pdf-upload-box" style="border-color: #a7f3d0; background: #ecfdf5;">
                                    <ion-icon name="document-text-outline" style="font-size: 32px; color: var(--success); margin-bottom: 10px;"></ion-icon>
                                    <div style="font-weight: 700; color: #065f46;">Click to Upload PDF File</div>
                                    <div class="file-hint" style="color:#047857;">Max size ~<?= ini_get('upload_max_filesize') ?> (server limit)</div>
                                    <input type="file" name="pdf_file" id="inp_pdf_file" accept=".pdf,application/pdf" onchange="showFileName(this)">
                                    <input type="hidden" name="existing_pdf" id="inp_existing_pdf">
                                    <div id="file-name" class="file-name-display" style="color: var(--success);"></div>
                                    <div id="pdf-chip" class="file-chip" style="display:none;">
                                        <ion-icon name="document-text"></ion-icon>
                                        <span class="chip-text" id="pdf-chip-text"></span>
                                        <button type="button" onclick="removeExistingPdf(event)" title="Remove this PDF">&times;</button>
                                    </div>
                                    <div id="pdf-error" class="upload-error-text"></div>
                                </div>

                                <div class="toggle-wrapper" style="background:#f0fdf4; border-color:#bbf7d0; margin-bottom: 12px;">
                                    <label class="switch">
                                        <input type="checkbox" name="allow_download" id="inp_allow_download" value="1">
                                        <span class="slider"></span>
                                    </label>
                                    <div class="toggle-info">
                                        <h4 style="color: #065f46;">Show Download Button</h4>
                                        <p style="color: #047857;">ON = Download chalegi. OFF = neeche message box khulega (click pe student ko dikhega).</p>
                                    </div>
                                </div>

                                <div id="downloadMsgBox" class="pm-cond-box" style="display:none; background:#fff7ed; border:1px solid #fed7aa; border-radius:14px; padding:16px 18px; margin-bottom:18px;">
                                    <div style="display:flex; align-items:center; gap:8px; margin-bottom:10px;">
                                        <ion-icon name="lock-closed-outline" style="font-size:20px; color:#c2410c;"></ion-icon>
                                        <label style="margin:0; color:#9a3412; font-weight:800;">Download Locked Message</label>
                                    </div>
                                    <textarea name="download_msg" id="inp_download_msg" rows="3" placeholder="Example: Download exam se 1 din pehle subah 6 baje unlock hoga." style="width:100%; border:1px solid #fdba74; border-radius:10px; padding:12px; font-size:13px; line-height:1.5; resize:vertical; background:#fff;"></textarea>
                                    <div class="file-hint" style="margin-top:8px; color:#9a3412;">Download OFF hai — student Download pe click kare to ye message dikhega. Khali = default text.</div>
                                </div>

                                <div class="toggle-wrapper" style="background:#111; border-color:#334155; margin-bottom: 15px;">
                                    <label class="switch">
                                        <input type="checkbox" name="app_only" id="inp_app_only" value="1">
                                        <span class="slider"></span>
                                    </label>
                                    <div class="toggle-info">
                                        <h4 style="color: #fff;">App Only (Buy &amp; Access)</h4>
                                        <p style="color: #cbd5e1;">ON = website pe sirf list/badge; Buy aur Open sirf Android App me.</p>
                                    </div>
                                </div>

                                <div class="toggle-wrapper" style="background:#eff6ff; border-color:#bfdbfe; margin-bottom: 25px;">
                                    <label class="switch">
                                        <input type="checkbox" name="show_preview" id="inp_show_preview" value="1">
                                        <span class="slider"></span>
                                    </label>
                                    <div class="toggle-info">
                                        <h4 style="color: #1e3a8a;">Show Preview Card</h4>
                                        <p style="color: #1d4ed8;">ON = store card pe Preview dikhega — PDF ka sirf 1st page.</p>
                                    </div>
                                </div>

                                <h4 style="font-size: 1.1rem; font-weight: 800; border-bottom: 1px solid var(--gray-border); padding-bottom: 10px; margin-bottom: 15px;">Advanced Settings</h4>
                                
                                <div class="row">
                                    <div class="toggle-wrapper">
                                        <label class="switch">
                                            <input type="checkbox" name="show_tnc" id="inp_show_tnc" value="1">
                                            <span class="slider"></span>
                                        </label>
                                        <div class="toggle-info">
                                            <h4>Show T&amp;C Checkbox</h4>
                                            <p>ON = pehle T&amp;C, phir pay — neeche text box khulega</p>
                                        </div>
                                    </div>
                                    <div class="toggle-wrapper">
                                        <label class="switch">
                                            <input type="checkbox" name="show_report_btn" id="inp_show_report_btn" value="1">
                                            <span class="slider"></span>
                                        </label>
                                        <div class="toggle-info">
                                            <h4>Show Report Button</h4>
                                            <p>Allow users to report issues</p>
                                        </div>
                                    </div>
                                </div>

                                <div id="tncTextBox" class="pm-cond-box" style="display:none; background:#f8fafc; border:1px solid #cbd5e1; border-radius:14px; padding:16px 18px; margin:4px 0 22px;">
                                    <div style="display:flex; align-items:center; justify-content:space-between; gap:10px; flex-wrap:wrap; margin-bottom:10px;">
                                        <div style="display:flex; align-items:center; gap:8px;">
                                            <ion-icon name="document-text-outline" style="font-size:20px; color:#334155;"></ion-icon>
                                            <label style="margin:0; color:#0f172a; font-weight:800;">Custom Terms &amp; Conditions</label>
                                        </div>
                                        <button type="button" id="btnLoadDefaultTnc" class="btn-icon btn-edit" style="width:auto; padding:8px 12px; gap:6px; margin:0;">
                                            <ion-icon name="document-text-outline"></ion-icon> Load Default Sample
                                        </button>
                                    </div>
                                    <textarea name="tnc_text" id="inp_tnc_text" rows="10" placeholder="Har line: 1. Title: rest of text&#10;Title bold dikhega, baaki normal. Khali = default sample sab courses pe." style="width:100%; border:1px solid #94a3b8; border-radius:10px; padding:12px; font-size:13px; line-height:1.55; resize:vertical; background:#fff; font-family:inherit;"></textarea>
                                    <div class="file-hint" style="margin-top:8px;">Format: <code>1. Non-Refundable: All sales are final...</code> — colon se pehle bold. Blank = website default sample.</div>
                                </div>

                                <h4 style="font-size: 1.1rem; font-weight: 800; border-bottom: 1px solid var(--gray-border); padding-bottom: 10px; margin-top: 10px; margin-bottom: 15px;">Buttons & External Links</h4>
                                
                                <div class="form-group">
                                    <label style="color: var(--primary);">Project Website Link (Only for Projects)</label>
                                    <input type="text" name="website_link" id="inp_website_link" placeholder="https://project-demo.com">
                                </div>
                                <div class="form-group">
                                    <label>Direct Payment Link (Optional)</label>
                                    <input type="text" name="link" id="inp_link" placeholder="https://...">
                                </div>
                                <div class="form-group">
                                    <label>Demo Link (For Preview Button Only)</label>
                                    <input type="text" name="demo_link" id="inp_demo_link" placeholder="https://drive.google.com/...">
                                </div>
                                
                                <div class="row">
                                    <div class="form-group">
                                        <label>Button Text</label>
                                        <input type="text" name="btn_text" id="inp_btn_text" value="Buy Now" required>
                                    </div>
                                    <div class="form-group">
                                        <label>Button Style</label>
                                        <select name="btn_type" id="inp_btn_type">
                                            <option value="normal">Normal (Black clickable)</option>
                                            <option value="coming_soon">Coming Soon (Unclickable)</option>
                                            <option value="disabled_look">Disabled Look (Orange Unclickable)</option>
                                            <option value="preview_buy">Preview (Demo) + Buy</option>
                                            <option value="disabled">Completely Disabled (Unclickable)</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="form-actions-row">
                                    <button type="submit" id="submitBtn" class="btn-submit"><ion-icon name="cloud-upload"></ion-icon> Publish Course</button>
                                    <button type="button" id="cancelBtn" class="btn-submit btn-cancel" onclick="cancelEdit()"><ion-icon name="close-circle"></ion-icon> Cancel Edit</button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div class="list-section">
                        <h3 class="section-title"><ion-icon name="list"></ion-icon> Published & Trashed Inventory</h3>
                        <div class="table-responsive">
                            <table class="custom-table">
                                <thead>
                                    <tr>
                                        <th style="width: 100px;">ID / Rank</th>
                                        <th>Course Information</th>
                                        <th>Pricing</th>
                                        <th>Assets Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="table_body">
                                    <tr><td colspan="5" style="text-align:center;"><div class="shimmer" style="height: 40px; width: 100%;"></div></td></tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="asset-save-bar" id="assetSaveBar" style="display:none;">
                            <span class="dirty-count" id="assetDirtyCount">0 unsaved changes</span>
                            <button type="button" class="btn-outline" onclick="discardFlagChanges()"><ion-icon name="arrow-undo-outline"></ion-icon> Discard</button>
                            <button type="button" class="btn-submit" id="btnSaveFlags" onclick="saveAllFlagChanges()"><ion-icon name="save"></ion-icon> Update All Changes</button>
                        </div>
                    </div>
                </div>

                <div id="tab-ranking" class="tab-panel">
                    <div class="toolbar">
                        <div>
                            <h2 class="section-title" style="margin-bottom: 5px;"><ion-icon name="swap-vertical-outline"></ion-icon> Store Ranking</h2>
                            <p style="font-size: 0.9rem; color: var(--dark-muted); font-weight: 500;">Card ko slide / drag karke upar-neeche lao. Rank 1 = homepage pe pehle.</p>
                        </div>
                    </div>

                    <div class="rank-help">
                        ☰ left side se pakdo aur slide karo (phone pe bhi). Order set hone ke baad neeche <strong>Save Ranking</strong> dabao.
                    </div>

                    <div id="ranking_list" class="rank-list">
                        <div class="shimmer" style="height: 80px; border-radius: 14px;"></div>
                    </div>

                    <div class="rank-save-bar">
                        <button type="button" onclick="loadRanking()" class="btn-submit" style="background:#64748b; flex:0.7;"><ion-icon name="refresh"></ion-icon> Refresh</button>
                        <button type="button" id="btnSaveRanking" onclick="saveRanking()" class="btn-submit" style="background:#4f46e5;"><ion-icon name="save"></ion-icon> Save Ranking</button>
                    </div>
                </div>

                <div id="tab-students" class="tab-panel">
                    <div class="toolbar">
                        <div>
                            <h2 class="section-title" style="margin-bottom: 5px;"><ion-icon name="people"></ion-icon> Student Access Control</h2>
                            <p style="font-size: 0.9rem; color: var(--dark-muted); font-weight: 500;">Easily grant or revoke course access for any student instantly.</p>
                        </div>
                        <div class="search-box">
                            <ion-icon name="search"></ion-icon>
                            <input type="text" id="studentSearch" placeholder="Search by name or email..." oninput="debounceSearch()">
                        </div>
                    </div>
                    
                    <div id="students_body" class="grid-cards">
                        <div class="user-card"><div class="shimmer" style="height: 150px;"></div></div>
                        <div class="user-card"><div class="shimmer" style="height: 150px;"></div></div>
                    </div>
                </div>

                <div id="tab-reports" class="tab-panel">
                    <div class="toolbar">
                        <div>
                            <h2 class="section-title" style="margin-bottom: 5px; color: var(--danger);"><ion-icon name="warning"></ion-icon> User Problem Reports</h2>
                            <p style="font-size: 0.9rem; color: var(--dark-muted); font-weight: 500;">Resolve student issues like payment failures or PDF loading problems.</p>
                        </div>
                        <button onclick="loadReports()" class="btn-submit" style="width: auto; background: var(--danger);"><ion-icon name="refresh"></ion-icon> Refresh Reports</button>
                    </div>
                    
                    <div id="reports_body" class="grid-cards">
                        <div class="user-card"><div class="shimmer" style="height: 150px;"></div></div>
                    </div>
                </div>

            </div>
        </main>
    </div>

    <div id="toast"><ion-icon name="checkmark-circle"></ion-icon> <span id="toast-msg">Success</span></div>

    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"
        integrity="sha384-BSxuMLxX+FCbTdYec3TbXlnMGEEM2QXTFdtDaveen71o+jswm2J36+xFqp8k4VHM"
        crossorigin="anonymous"
        referrerpolicy="no-referrer"></script>

    <script type="module">
        import { initializeApp } from 'https://www.gstatic.com/firebasejs/10.7.1/firebase-app.js';
        import { getAuth, signInWithPopup, GoogleAuthProvider, onAuthStateChanged, signOut } from 'https://www.gstatic.com/firebasejs/10.7.1/firebase-auth.js';

        const firebaseConfig = {
            apiKey: "AIzaSyBMF_RAmopbnPC7OpNcJCo2CUGS6CDiMEY",
            authDomain: "premium-mind-fcb16.firebaseapp.com",
            projectId: "premium-mind-fcb16",
            storageBucket: "premium-mind-fcb16.firebasestorage.app",
            messagingSenderId: "864874199521",
            appId: "1:864874199521:web:5deb96a956dc404a077aa0"
        };

        const app = initializeApp(firebaseConfig);
        const auth = getAuth(app);
        const provider = new GoogleAuthProvider();

        const AUTHORIZED_ADMIN_EMAILS = ["premku0237@gmail.com", "ar0319515@gmail.com"];
        const SERVER_SESSION_OK = <?= $pmAdminSessionOk ? 'true' : 'false' ?>;
        const SERVER_SESSION_EMAIL = <?= json_encode($pmAdminSessionEmail, JSON_UNESCAPED_SLASHES) ?>;
        const SERVER_SESSION_NAME = <?= json_encode($pmAdminSessionName, JSON_UNESCAPED_SLASHES) ?>;

        const overlay = document.getElementById("adminAuthOverlay");
        const loading = document.getElementById("loading-screen");
        const appWrapper = document.getElementById("admin-wrapper");
        const errorMsg = document.getElementById("authErrorMsg");
        const loginBtn = document.getElementById("adminGoogleLoginBtn");
        const logoutBtn = document.getElementById("adminLogoutBtn");

        function enterAdminUI(email, name) {
            const displayName = name || 'Admin';
            loading.style.display = "none";
            document.body.style.display = "block";
            overlay.style.display = "none";
            appWrapper.style.display = "flex";

            if (window.innerWidth > 768) {
                document.getElementById('admin-info-box').style.display = 'block';
            }
            document.getElementById('admin-name').innerText = displayName;
            document.getElementById('admin-email').innerText = email || '';
            document.getElementById('admin-avatar-letter').innerText = displayName.charAt(0).toUpperCase();

            loadCourses();
            loadStudents();
            loadReports();
            loadRecentPurchases(false);
            startSalesAutoRefresh();
        }

        function showLoginScreen(msg) {
            loading.style.display = "none";
            document.body.style.display = "block";
            overlay.style.display = "flex";
            appWrapper.style.display = "none";
            if (msg) {
                errorMsg.innerText = msg;
                errorMsg.style.display = "block";
            }
        }

        async function establishServerSession(user) {
            const idToken = await user.getIdToken(true);
            const fd = new FormData();
            fd.append('ajax_admin_login', '1');
            fd.append('id_token', idToken);
            fd.append('name', user.displayName || 'Admin');
            const res = await fetch('admin_panel.php', { method: 'POST', body: fd, credentials: 'same-origin' });
            const data = await res.json().catch(() => ({}));
            if (!res.ok || data.status !== 'success') {
                const detail = data.reason ? (' ' + data.reason) : '';
                throw new Error((data.msg || 'Server login failed') + detail);
            }
            return data;
        }

        // Already have verified PHP session (from app Firebase token or prior login)
        if (SERVER_SESSION_OK && SERVER_SESSION_EMAIL) {
            enterAdminUI(SERVER_SESSION_EMAIL, SERVER_SESSION_NAME || 'Admin');
        } else {
            const params = new URLSearchParams(location.search);
            if (params.get('auth_error')) {
                const reason = params.get('reason') ? decodeURIComponent(params.get('reason')) : '';
                showLoginScreen('App login failed. Please Sign in with Google.' + (reason ? (' (' + reason + ')') : ''));
            }

            onAuthStateChanged(auth, async (user) => {
                if (SERVER_SESSION_OK) return;

                if (user && AUTHORIZED_ADMIN_EMAILS.includes(user.email)) {
                    try {
                        loading.style.display = "flex";
                        document.body.style.display = "block";
                        await establishServerSession(user);
                        enterAdminUI(user.email, user.displayName || 'Admin');
                    } catch (e) {
                        showLoginScreen(e.message || 'Could not create secure admin session.');
                        signOut(auth);
                    }
                } else if (user) {
                    showLoginScreen(`Access Denied. ${user.email} is not authorized.`);
                    signOut(auth);
                } else if (!params.get('auth_error')) {
                    showLoginScreen();
                }
            });
        }

        loginBtn.addEventListener("click", async () => {
            try {
                errorMsg.style.display = "none";
                const result = await signInWithPopup(auth, provider);
                const user = result.user;
                if (!user || !AUTHORIZED_ADMIN_EMAILS.includes(user.email)) {
                    showLoginScreen(user ? `Access Denied. ${user.email} is not authorized.` : 'Login failed.');
                    if (user) signOut(auth);
                    return;
                }
                loading.style.display = "flex";
                await establishServerSession(user);
                enterAdminUI(user.email, user.displayName || 'Admin');
            } catch (error) {
                errorMsg.innerText = "Login Failed: " + (error.message || error);
                errorMsg.style.display = "block";
            }
        });

        logoutBtn.addEventListener("click", () => {
            signOut(auth).finally(() => {
                window.location.href = 'admin_panel.php?logout=1';
            });
        });
    </script>

    <script>
        // Layout & Sidebar Toggle Optimization
        const sidebar = document.getElementById('sidebar');
        const overlayBg = document.getElementById('sidebar-overlay');
        
        function openSidebar() {
            sidebar.classList.add('active');
            if (window.innerWidth <= 1024) {
                overlayBg.classList.add('active');
            }
        }
        
        function closeSidebar() {
            sidebar.classList.remove('active');
            overlayBg.classList.remove('active');
        }

        document.getElementById('open-sidebar').addEventListener('click', openSidebar);
        document.getElementById('close-sidebar').addEventListener('click', closeSidebar);
        overlayBg.addEventListener('click', closeSidebar); 

        function adjustLayout() {
            if (window.innerWidth <= 1024) {
                sidebar.classList.remove('active');
                overlayBg.classList.remove('active');
            }
        }
        window.addEventListener('resize', adjustLayout);
        adjustLayout();

        // Tab Switching
        function switchTab(tabId, element) {
            document.querySelectorAll('.nav-link').forEach(a => a.classList.remove('active'));
            if(element) element.classList.add('active');

            document.querySelectorAll('.tab-panel').forEach(tab => tab.classList.remove('active'));
            document.getElementById('tab-' + tabId).classList.add('active');

            if (window.innerWidth <= 1024) closeSidebar();
            window.scrollTo({ top: 0, behavior: 'smooth' });
            
            if(tabId === 'courses') loadCourses();
            if(tabId === 'ranking') loadRanking();
            if(tabId === 'students') loadStudents();
            if(tabId === 'reports') loadReports();
            if(tabId === 'dashboard') loadRecentPurchases(false);
        }

        function showToast(message, type = 'success') {
            const toast = document.getElementById('toast');
            const icon = toast.querySelector('ion-icon');
            document.getElementById('toast-msg').innerText = message;
            
            if(type === 'error') {
                toast.style.background = 'var(--danger)';
                icon.setAttribute('name', 'alert-circle');
            } else {
                toast.style.background = 'var(--dark)';
                icon.setAttribute('name', 'checkmark-circle');
            }

            toast.classList.add('show');
            setTimeout(() => toast.classList.remove('show'), 3000);
        }

        function showFileName(input) {
            const display = document.getElementById('file-name');
            const errBox = document.getElementById('pdf-error');
            errBox.style.display = 'none';
            errBox.innerText = '';

            if (input.files && input.files[0]) {
                const file = input.files[0];
                const sizeMB = (file.size / (1024*1024)).toFixed(2);

                // Client-side sanity checks so the admin gets instant feedback
                // instead of a silent failure after submitting the form.
                const looksLikePdf = file.type === 'application/pdf' || /\.pdf$/i.test(file.name);
                if (!looksLikePdf) {
                    errBox.innerText = "⚠️ This doesn't look like a PDF file. Please choose a .pdf file.";
                    errBox.style.display = 'block';
                    input.value = '';
                    display.innerText = '';
                    return;
                }

                display.innerText = `Selected PDF: ${file.name} (${sizeMB} MB)`;
                // A newly picked file replaces whatever the card was using
                const chip = document.getElementById('pdf-chip');
                if (chip) chip.style.display = 'none';
            }
            else {
                display.innerText = "";
                renderExistingPdfChip();
            }
        }

        function showImgName(input) {
            const display = document.getElementById('img-name');
            if (input.files && input.files[0]) {
                display.innerText = "Selected Image: " + input.files[0].name;
                const chip = document.getElementById('img-chip');
                if (chip) chip.style.display = 'none';
            } else {
                display.innerText = "";
                renderExistingImageChip();
            }
        }

        // ==========================================
        // FAST DATA FETCHING (OPTIMIZED)
        // ==========================================

        async function loadCourses() {
            const tbody = document.getElementById('table_body');
            tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;"><div class="shimmer" style="height: 50px; width: 100%;"></div></td></tr>';
            try {
                const r = await fetch('?fetch_table=1', { credentials: 'same-origin' });
                if (r.status === 403) { showToast('Session expired. Please login again.', 'error'); return; }

                const html = await r.text();
                if (!r.ok) {
                    // A server-side error used to land here silently as row
                    // markup; surface the status instead of pretending the
                    // response was a table.
                    throw new Error('Server returned ' + r.status + (html ? (': ' + html.slice(0, 200)) : ''));
                }
                tbody.innerHTML = html;

                // Rows (and their controls) were just replaced with fresh
                // server values, so any staged edits no longer apply.
                window.pmDirtyFlags = {};
                // Don't let a post-render helper failure look like a failed
                // fetch — the rows are already on screen at this point.
                try { updateAssetSaveBar(); } catch (e) { console.error('updateAssetSaveBar failed', e); }
                try { refreshCopySelect(); } catch (e) { console.error('refreshCopySelect failed', e); }
            } catch(e) {
                console.error('loadCourses failed:', e);
                tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;padding:24px;color:#991b1b;">'
                    + 'Could not load courses.<br><span style="font-size:0.8rem;color:var(--dark-muted);">'
                    + String(e && e.message ? e.message : e).replace(/[<>]/g, '')
                    + '</span></td></tr>';
                showToast('Failed to load courses: ' + (e && e.message ? e.message : 'unknown error'), 'error');
            }
        }

        // ==========================================
        // RECENT PURCHASES — live sales feed
        // ==========================================
        window.pmSeenSaleKeys = null;   // null = first load, don't flag everything as new
        window.pmSalesTimer = null;

        function pmEscape(s) {
            return String(s == null ? '' : s)
                .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
        }

        function pmTimeAgo(ts) {
            if (!ts) return '';
            // MySQL DATETIME ("2026-08-18 14:12:05") — Safari/iOS won't parse
            // that with a space, so normalise to ISO-ish before Date().
            const d = new Date(String(ts).replace(' ', 'T'));
            if (isNaN(d.getTime())) return '';
            const secs = Math.floor((Date.now() - d.getTime()) / 1000);
            if (secs < 0) return 'just now';
            if (secs < 60) return 'just now';
            const mins = Math.floor(secs / 60);
            if (mins < 60) return mins + 'm ago';
            const hrs = Math.floor(mins / 60);
            if (hrs < 24) return hrs + 'h ago';
            const days = Math.floor(hrs / 24);
            if (days < 30) return days + 'd ago';
            return d.toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric' });
        }

        function renderSalesFeed(list) {
            const box = document.getElementById('salesFeed');
            if (!box) return;

            if (!list.length) {
                box.innerHTML = '<div class="sales-empty">'
                    + '<ion-icon name="cart-outline" style="font-size:42px;"></ion-icon>'
                    + '<h4 style="margin-top:8px;color:var(--dark);">No purchases yet</h4>'
                    + '<p style="font-size:0.85rem;">New sales will appear here automatically.</p></div>';
                return;
            }

            const firstLoad = window.pmSeenSaleKeys === null;
            const seen = window.pmSeenSaleKeys || {};

            box.innerHTML = list.map(function (p, i) {
                const key = String(p.row_key || (p.user_email + '-' + p.course_id));
                const isNew = !firstLoad && !seen[key];

                const name = (p.user_name && String(p.user_name).trim()) ? String(p.user_name).trim() : 'Unknown Student';
                const course = (p.course_title && String(p.course_title).trim()) ? String(p.course_title).trim() : ('Course #' + (p.course_id || '?'));
                const price = (p.course_price === null || p.course_price === undefined || p.course_price === '') ? '' : ('₹' + p.course_price);
                const img = p.course_image ? String(p.course_image) : '';
                const initial = name.charAt(0).toUpperCase();

                const avatar = img
                    ? '<div class="sale-avatar"><img src="' + pmEscape(img) + '" alt="" onerror="this.parentNode.textContent=\'' + pmEscape(initial) + '\'"></div>'
                    : '<div class="sale-avatar">' + pmEscape(initial) + '</div>';

                return '<div class="sale-item' + (isNew ? ' is-new' : '') + '" style="animation-delay:'
                        + Math.min(i * 45, 700) + 'ms;">'
                    + avatar
                    + '<div class="sale-body">'
                        + '<div class="sale-name">' + pmEscape(name) + '</div>'
                        + '<div class="sale-course"><ion-icon name="book-outline"></ion-icon>' + pmEscape(course) + '</div>'
                        + '<div class="sale-email">' + pmEscape(p.user_email || '') + '</div>'
                    + '</div>'
                    + '<div class="sale-right">'
                        + '<div class="sale-amount">' + pmEscape(price) + '</div>'
                        + '<div class="sale-time">' + pmEscape(pmTimeAgo(p.purchased_at)) + '</div>'
                    + '</div>'
                + '</div>';
            }).join('');

            const nextSeen = {};
            list.forEach(function (p) {
                nextSeen[String(p.row_key || (p.user_email + '-' + p.course_id))] = true;
            });
            window.pmSeenSaleKeys = nextSeen;
        }

        window.loadRecentPurchases = async function (manual) {
            const box = document.getElementById('salesFeed');
            if (!box) return;
            try {
                const r = await fetch('?fetch_recent_purchases=1', { credentials: 'same-origin' });
                if (r.status === 403) { if (manual) showToast('Session expired. Please login again.', 'error'); return; }
                const data = await r.json();
                renderSalesFeed(Array.isArray(data.purchases) ? data.purchases : []);
                if (manual) showToast('Purchases refreshed!');
            } catch (e) {
                if (manual) showToast('Failed to load purchases', 'error');
            }
        };

        function startSalesAutoRefresh() {
            if (window.pmSalesTimer) clearInterval(window.pmSalesTimer);
            window.pmSalesTimer = setInterval(function () {
                const tab = document.getElementById('tab-dashboard');
                // Only poll while the dashboard is actually on screen
                if (tab && tab.classList.contains('active') && !document.hidden) {
                    loadRecentPurchases(false);
                }
            }, 30000);
        }

        // Homepage / Index ranking — drag / slide to reorder
        window.pmRankOrder = [];
        window.pmRankSortable = null;

        function syncRankOrderFromDom() {
            const box = document.getElementById('ranking_list');
            if (!box) return;
            const ids = Array.from(box.querySelectorAll('.rank-item')).map(function (el) { return el.getAttribute('data-id'); });
            const byId = {};
            (window.pmRankOrder || []).forEach(function (c) { byId[String(c.id)] = c; });
            window.pmRankOrder = ids.map(function (id) { return byId[String(id)]; }).filter(Boolean);
            box.querySelectorAll('.rank-item').forEach(function (el, i) {
                const num = el.querySelector('.rank-num');
                if (num) num.textContent = String(i + 1);
                const up = el.querySelector('[data-dir="-1"]');
                const down = el.querySelector('[data-dir="1"]');
                if (up) up.disabled = i === 0;
                if (down) down.disabled = i === ids.length - 1;
            });
        }

        function bindRankSortable() {
            const box = document.getElementById('ranking_list');
            if (!box || typeof Sortable === 'undefined') return;
            if (window.pmRankSortable) {
                try { window.pmRankSortable.destroy(); } catch (e) {}
                window.pmRankSortable = null;
            }
            window.pmRankSortable = Sortable.create(box, {
                animation: 160,
                handle: '.rank-handle',
                draggable: '.rank-item',
                ghostClass: 'rank-ghost',
                chosenClass: 'rank-chosen',
                dragClass: 'rank-drag',
                forceFallback: true,
                fallbackOnBody: true,
                fallbackTolerance: 4,
                touchStartThreshold: 4,
                delayOnTouchOnly: true,
                delay: 120,
                onEnd: function () {
                    syncRankOrderFromDom();
                }
            });
        }

        function renderRankingList() {
            const box = document.getElementById('ranking_list');
            if (!box) return;
            const list = window.pmRankOrder || [];
            if (!list.length) {
                box.innerHTML = '<div style="padding:30px;text-align:center;color:var(--dark-muted);background:#fff;border-radius:14px;border:1px dashed #cbd5e1;">No active courses to rank.</div>';
                return;
            }
            box.innerHTML = list.map(function (c, i) {
                const img = String(c.image || 'small-logo.png').replace(/"/g, '&quot;');
                const title = String(c.title || 'Untitled').replace(/</g, '&lt;');
                const cat = String(c.category || '').toUpperCase();
                const isFirst = i === 0;
                const isLast = i === list.length - 1;
                return (
                    '<div class="rank-item" data-id="' + c.id + '">' +
                      '<div class="rank-handle" title="Drag to move"><ion-icon name="menu-outline"></ion-icon></div>' +
                      '<div class="rank-num">' + (i + 1) + '</div>' +
                      '<img class="rank-thumb" src="' + img + '" alt="" onerror="this.src=\'small-logo.png\'">' +
                      '<div class="rank-meta">' +
                        '<h4>' + title + '</h4>' +
                        '<p>#' + c.id + ' · ' + cat + ' · ₹' + (c.price || 0) + '</p>' +
                      '</div>' +
                      '<div class="rank-moves">' +
                        '<button type="button" data-dir="-1" ' + (isFirst ? 'disabled' : '') + ' onclick="moveRankById(\'' + c.id + '\',-1)" title="Up"><ion-icon name="chevron-up"></ion-icon></button>' +
                        '<button type="button" data-dir="1" ' + (isLast ? 'disabled' : '') + ' onclick="moveRankById(\'' + c.id + '\',1)" title="Down"><ion-icon name="chevron-down"></ion-icon></button>' +
                      '</div>' +
                    '</div>'
                );
            }).join('');
            bindRankSortable();
        }

        window.moveRankById = function (id, dir) {
            const box = document.getElementById('ranking_list');
            if (!box) return;
            const el = box.querySelector('.rank-item[data-id="' + id + '"]');
            if (!el) return;

            // Move just the two affected nodes instead of re-rendering (and
            // re-binding Sortable on) the whole list — that full rebuild is
            // what made each up/down tap feel sluggish.
            if (dir < 0) {
                const prev = el.previousElementSibling;
                if (!prev) return;
                box.insertBefore(el, prev);
            } else {
                const next = el.nextElementSibling;
                if (!next) return;
                box.insertBefore(next, el);
            }
            syncRankOrderFromDom();
        };

        window.loadRanking = async function () {
            const box = document.getElementById('ranking_list');
            if (box) box.innerHTML = '<div class="shimmer" style="height:80px;border-radius:14px;"></div>';
            try {
                const r = await fetch('?fetch_ranking=1', { credentials: 'same-origin' });
                if (r.status === 403) { showToast('Session expired. Please login again.', 'error'); return; }
                const data = await r.json();
                window.pmRankOrder = Array.isArray(data.courses) ? data.courses : [];
                renderRankingList();
            } catch (e) {
                showToast('Failed to load ranking', 'error');
            }
        };

        window.saveRanking = async function () {
            syncRankOrderFromDom();
            const btn = document.getElementById('btnSaveRanking');
            const ids = (window.pmRankOrder || []).map(function (c) { return c.id; });
            if (!ids.length) { showToast('Nothing to save', 'error'); return; }
            const original = btn ? btn.innerHTML : '';
            if (btn) { btn.disabled = true; btn.innerHTML = '<ion-icon name="sync" class="spinner"></ion-icon> Saving...'; }
            const fd = new FormData();
            fd.append('ajax_save_ranking', '1');
            fd.append('order_ids', JSON.stringify(ids));
            try {
                const res = await fetch(window.location.href, { method: 'POST', body: fd, credentials: 'same-origin' });
                const data = await res.json();
                if (data.status === 'success') {
                    showToast('Ranking saved! Homepage updated.');
                    loadRanking();
                    if (document.getElementById('table_body')) loadCourses();
                } else {
                    showToast('Save failed: ' + (data.msg || 'error'), 'error');
                }
            } catch (e) {
                showToast('Save failed', 'error');
            }
            if (btn) { btn.disabled = false; btn.innerHTML = original; }
        };

        let searchTimer;
        function debounceSearch() {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(() => { loadStudents(); }, 350); 
        }

        async function loadStudents() {
            const grid = document.getElementById('students_body');
            const searchVal = document.getElementById('studentSearch').value;
            grid.innerHTML = '<div class="user-card"><div class="shimmer" style="height: 150px;"></div></div>';
            try {
                const r = await fetch('?fetch_students=1&search=' + encodeURIComponent(searchVal), { credentials: 'same-origin' });
                if (r.status === 403) { showToast('Session expired. Please login again.', 'error'); return; }
                grid.innerHTML = await r.text();
            } catch(e) { showToast("Failed to load students", "error"); }
        }

        async function loadReports() {
            const grid = document.getElementById('reports_body');
            grid.innerHTML = '<div class="user-card"><div class="shimmer" style="height: 150px;"></div></div>';
            try {
                const r = await fetch('?fetch_reports=1', { credentials: 'same-origin' });
                if (r.status === 403) { showToast('Session expired. Please login again.', 'error'); return; }
                grid.innerHTML = await r.text();
            } catch(e) { showToast("Failed to load reports", "error"); }
        }

        // ==========================================
        // ACTIONS & SUBMISSIONS 
        // ==========================================

        document.getElementById('courseForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            const btn = document.getElementById('submitBtn');
            const originalText = btn.innerHTML;
            btn.innerHTML = '<ion-icon name="sync" class="spinner"></ion-icon> Saving...'; 
            btn.disabled = true;

            const fd = new FormData(this);
            fd.append('ajax_add', '1');

            try {
                const res = await fetch(window.location.href, {method:'POST', body:fd, credentials:'same-origin'});

                // Read as text first: if PHP printed a warning/notice before the
                // JSON, res.json() would throw with a vague error. Reading as
                // text lets us surface the real problem to the admin instead.
                const raw = await res.text();
                if (res.status === 403) {
                    showToast('Session expired. Please login again.', 'error');
                    btn.innerHTML = originalText; btn.disabled = false;
                    return;
                }
                let data;
                try {
                    data = JSON.parse(raw);
                } catch(parseErr) {
                    console.error('Non-JSON server response:', raw);
                    showToast("Server returned an unexpected response (check PHP error log)", "error");
                    btn.innerHTML = originalText; btn.disabled = false;
                    return;
                }

                if(data.status==='success') {
                    showToast('Course Saved Successfully!');
                    cancelEdit();
                    loadCourses();
                } else { showToast('Error: ' + data.msg, "error"); }
            } catch(e) { showToast("System Error or Server Timeout!", "error"); }
            
            btn.innerHTML = originalText; btn.disabled = false;
        });

        // Default T&C sample (same as website — used when course tnc_text is empty)
        const PM_DEFAULT_TNC =
`1. Non-Refundable: All sales are final. No refunds will be provided once the course access is granted.
2. No Sharing: Sharing your account details with others is strictly prohibited and will lead to an instant permanent ban without refund.
3. Personal Use: The study material is for your personal use only. Distribution or piracy will invite legal actions.
4. Download Policy: You can download the content only one day before the exam at 6:00 PM when the download button unlocks.
5. Payment Instruction: After successful payment, please wait for 10 seconds on the same page. Then check your profile for access to the purchased content.
6. Important Notice: This content is based on analysis and predictions; exact exam questions are not guaranteed.
7. Agreement: By proceeding, you agree to PREMium Mind's standard terms of service.`;

        function syncConditionalBoxes() {
            const dlOn = !!(document.getElementById('inp_allow_download') || {}).checked;
            const tncOn = !!(document.getElementById('inp_show_tnc') || {}).checked;
            const dlBox = document.getElementById('downloadMsgBox');
            const tncBox = document.getElementById('tncTextBox');
            if (dlBox) dlBox.style.display = dlOn ? 'none' : 'block';
            if (tncBox) tncBox.style.display = tncOn ? 'block' : 'none';
        }

        const btnLoadDefaultTnc = document.getElementById('btnLoadDefaultTnc');
        if (btnLoadDefaultTnc) {
            btnLoadDefaultTnc.addEventListener('click', function () {
                document.getElementById('inp_tnc_text').value = PM_DEFAULT_TNC;
                showToast('Default T&C sample loaded — edit karke Save karo', 'success');
            });
        }

        const inpAllowDl = document.getElementById('inp_allow_download');
        const inpShowTnc = document.getElementById('inp_show_tnc');
        if (inpAllowDl) inpAllowDl.addEventListener('change', syncConditionalBoxes);
        if (inpShowTnc) inpShowTnc.addEventListener('change', syncConditionalBoxes);
        syncConditionalBoxes();

        window.pmCourseCache = {};

        function refreshCopySelect() {
            const sel = document.getElementById('copyFromSelect');
            if (!sel) return;
            const prev = sel.value;
            window.pmCourseCache = {};
            sel.innerHTML = '<option value="">— Select a card to copy —</option>';
            document.querySelectorAll('#table_body button[data-course]').forEach(function (btn) {
                try {
                    const data = JSON.parse(btn.getAttribute('data-course'));
                    if (!data || !data.id) return;
                    if (data.is_deleted == 1 || data.is_deleted === '1') return;
                    window.pmCourseCache[String(data.id)] = data;
                    const opt = document.createElement('option');
                    opt.value = String(data.id);
                    opt.textContent = '#' + data.id + ' — ' + (data.title || 'Untitled');
                    sel.appendChild(opt);
                } catch (e) {}
            });
            if (prev && window.pmCourseCache[prev]) sel.value = prev;
        }

        function setCopyBoxVisible(show) {
            const box = document.getElementById('copyFromBox');
            if (box) box.style.display = show ? 'block' : 'none';
            if (show) {
                const sel = document.getElementById('copyFromSelect');
                if (sel) sel.value = '';
            }
        }

        // Existing-file chips: show what the card will keep using if no new
        // file is uploaded, with an X to drop it (needed when copying a card,
        // since the copy otherwise silently inherits the original's PDF).
        function renderExistingPdfChip() {
            const val = (document.getElementById('inp_existing_pdf') || {}).value || '';
            const chip = document.getElementById('pdf-chip');
            const text = document.getElementById('pdf-chip-text');
            if (!chip || !text) return;
            if (val) {
                text.textContent = val.split('/').pop();
                chip.style.display = 'inline-flex';
            } else {
                chip.style.display = 'none';
            }
        }

        function renderExistingImageChip() {
            const val = (document.getElementById('inp_existing_image') || {}).value || '';
            const chip = document.getElementById('img-chip');
            const text = document.getElementById('img-chip-text');
            if (!chip || !text) return;
            if (val) {
                text.textContent = val.split('/').pop();
                chip.style.display = 'inline-flex';
            } else {
                chip.style.display = 'none';
            }
        }

        window.removeExistingPdf = function (ev) {
            if (ev) { ev.preventDefault(); ev.stopPropagation(); }
            document.getElementById('inp_existing_pdf').value = '';
            document.getElementById('inp_pdf_file').value = '';
            document.getElementById('file-name').innerHTML = '';
            renderExistingPdfChip();
            showToast('PDF removed — upload a new one or save without it');
        };

        window.removeExistingImage = function (ev) {
            if (ev) { ev.preventDefault(); ev.stopPropagation(); }
            document.getElementById('inp_existing_image').value = '';
            document.getElementById('inp_image_file').value = '';
            document.getElementById('img-name').innerHTML = '';
            renderExistingImageChip();
            showToast('Thumbnail removed — upload a new one or save without it');
        };

        function fillCourseForm(data, opts) {
            opts = opts || {};
            const isCopy = !!opts.isCopy;

            ['title','category','badge','desc1','desc2','price','old_price','link','demo_link','website_link','btn_text','btn_type'].forEach(function (k) {
                if (document.getElementById('inp_' + k)) document.getElementById('inp_' + k).value = data[k] || '';
            });

            if (isCopy) {
                const t = (data.title || '').trim();
                document.getElementById('inp_title').value = t ? (t + ' (Copy)') : '';
            }

            document.getElementById('inp_existing_image').value = data.image || '';
            document.getElementById('img-name').innerText = '';
            document.getElementById('inp_image_file').value = '';
            renderExistingImageChip();

            document.getElementById('inp_existing_pdf').value = data.pdf_file || '';
            document.getElementById('file-name').innerHTML = '';
            document.getElementById('inp_pdf_file').value = '';
            renderExistingPdfChip();
            const pdfErr = document.getElementById('pdf-error');
            if (pdfErr) { pdfErr.style.display = 'none'; pdfErr.innerText = ''; }

            document.getElementById('inp_allow_download').checked = data.allow_download == 1 || data.allow_download === '1' || data.allow_download === true;
            document.getElementById('inp_show_tnc').checked = data.show_tnc == 1 || data.show_tnc === '1';
            document.getElementById('inp_show_report_btn').checked = data.show_report_btn == 1 || data.show_report_btn === '1';
            document.getElementById('inp_app_only').checked = data.app_only == 1 || data.app_only === '1' || data.app_only === true;
            document.getElementById('inp_show_preview').checked = data.show_preview == 1 || data.show_preview === '1' || data.show_preview === true;
            document.getElementById('inp_tnc_text').value = data.tnc_text || '';
            document.getElementById('inp_download_msg').value = data.download_msg || '';
            syncConditionalBoxes();
        }

        const copyFromSelect = document.getElementById('copyFromSelect');
        if (copyFromSelect) {
            copyFromSelect.addEventListener('change', function () {
                const id = this.value;
                if (!id) return;
                const data = window.pmCourseCache[id];
                if (!data) {
                    showToast('Card data not found — refresh page', 'error');
                    return;
                }
                // IMPORTANT: edit_id blank rahe → Publish = NEW card (INSERT), purana update nahi
                document.getElementById('edit_id').value = '';
                fillCourseForm(data, { isCopy: true });
                document.getElementById('form_title').innerHTML = '<ion-icon name="copy"></ion-icon> New Course (Copied)';
                document.getElementById('submitBtn').innerHTML = '<ion-icon name="cloud-upload"></ion-icon> Publish New Card';
                document.getElementById('cancelBtn').style.display = 'inline-flex';
                setCopyBoxVisible(true);
                showToast('Copied from #' + id + ' — edit karke Publish karo', 'success');
                document.getElementById('inp_title').focus();
            });
        }

        window.editCard = function(btn) {
            const data = JSON.parse(btn.getAttribute('data-course'));
            fillCourseForm(data, { isCopy: false });

            document.getElementById('edit_id').value = data.id;
            document.getElementById('form_title').innerHTML = '<ion-icon name="create"></ion-icon> Edit Course';
            document.getElementById('submitBtn').innerHTML  = '<ion-icon name="save"></ion-icon> Save Updates';
            document.getElementById('cancelBtn').style.display = 'inline-flex';
            setCopyBoxVisible(false);
            
            document.querySelector('.form-section').scrollIntoView({behavior:'smooth'});
        }

        window.cancelEdit = function() {
            document.getElementById('courseForm').reset();
            document.getElementById('edit_id').value = '';
            document.getElementById('file-name').innerHTML = '';
            document.getElementById('inp_existing_pdf').value = '';
            document.getElementById('img-name').innerHTML = '';
            document.getElementById('inp_existing_image').value = '';
            renderExistingPdfChip();
            renderExistingImageChip();

            document.getElementById('inp_show_tnc').checked = false;
            document.getElementById('inp_show_report_btn').checked = false;
            document.getElementById('inp_app_only').checked = false;
            document.getElementById('inp_show_preview').checked = false;
            document.getElementById('inp_allow_download').checked = false;
            document.getElementById('inp_tnc_text').value = '';
            document.getElementById('inp_download_msg').value = '';
            syncConditionalBoxes();

            document.getElementById('form_title').innerHTML = '<ion-icon name="add-circle"></ion-icon> Create New Course';
            document.getElementById('submitBtn').innerHTML  = '<ion-icon name="cloud-upload"></ion-icon> Publish Course';
            document.getElementById('cancelBtn').style.display = 'none';
            setCopyBoxVisible(true);
        }

        // SOFT DELETE (MOVE TO TRASH)
        window.deleteCard = function(id) {
            if(!confirm('⚠️ Are you sure you want to move this to Trash? Students won\'t see it anymore.')) return;
            const fd = new FormData();
            fd.append('ajax_delete','1'); fd.append('delete_id', id);
            
            fetch(window.location.href, {method:'POST',body:fd})
            .then(res => res.json())
            .then(d => { 
                if(d.status==='success') { 
                    showToast("Moved to Trash!", 'warning'); 
                    loadCourses(); 
                } else showToast('Trash failed!', 'error'); 
            });
        }

        // RESTORE FROM TRASH
        window.restoreCard = function(id) {
            if(!confirm('♻️ Restore this course to active list?')) return;
            const fd = new FormData();
            fd.append('ajax_restore','1'); fd.append('restore_id', id);
            
            fetch(window.location.href, {method:'POST',body:fd})
            .then(res => res.json())
            .then(d => { 
                if(d.status==='success') { 
                    showToast("Course Restored Successfully!"); 
                    loadCourses(); 
                } else showToast('Restore failed!', 'error'); 
            });
        }

        // HARD DELETE PERMANENTLY
        window.hardDeleteCard = function(id) {
            if(!confirm('🚨 WARNING: This will permanently delete the course from the database. It CANNOT be undone. Proceed?')) return;
            const fd = new FormData();
            fd.append('ajax_hard_delete','1'); fd.append('delete_id', id);
            
            fetch(window.location.href, {method:'POST',body:fd})
            .then(res => res.json())
            .then(d => { 
                if(d.status==='success') { 
                    showToast("Permanently Deleted!"); 
                    loadCourses(); 
                } else showToast('Delete failed!', 'error'); 
            });
        }

        // ==========================================
        // ASSET FLAG DROPDOWNS — stage locally, save in one batch
        // ==========================================
        window.pmDirtyFlags = {};

        function updateAssetSaveBar() {
            const bar = document.getElementById('assetSaveBar');
            const label = document.getElementById('assetDirtyCount');
            if (!bar || !label) return;
            const n = Object.keys(window.pmDirtyFlags).length;
            bar.style.display = n ? 'flex' : 'none';
            label.textContent = n === 1 ? '1 unsaved change' : n + ' unsaved changes';
        }

        // Shared by both control types: record/clear a staged change
        function stageFlagChange(el, id, field, value, original) {
            const key = id + ':' + field;
            if (String(value) === String(original)) {
                delete window.pmDirtyFlags[key];
                el.classList.remove('is-dirty');
            } else {
                window.pmDirtyFlags[key] = { id: id, field: field, value: String(value) };
                el.classList.add('is-dirty');
            }
            updateAssetSaveBar();
        }

        window.toggleFlagBtn = function (btn) {
            const next = btn.dataset.value === '1' ? '0' : '1';
            btn.dataset.value = next;
            const isOn = next === '1';
            btn.classList.toggle('is-on', isOn);
            const state = btn.querySelector('.flag-btn-state');
            if (state) state.textContent = isOn ? 'ON' : 'OFF';
            stageFlagChange(btn, btn.dataset.id, btn.dataset.field, next, btn.dataset.original);
        };

        window.markFlagDirty = function (sel) {
            const row = sel.closest('.flag-select-row') || sel;
            stageFlagChange(row, sel.dataset.id, sel.dataset.field, sel.value, sel.dataset.original);
        };

        window.discardFlagChanges = function () {
            window.pmDirtyFlags = {};
            updateAssetSaveBar();
            loadCourses();
        };

        window.saveAllFlagChanges = async function () {
            const changes = Object.values(window.pmDirtyFlags);
            if (!changes.length) { showToast('Nothing to save', 'error'); return; }

            const btn = document.getElementById('btnSaveFlags');
            const original = btn ? btn.innerHTML : '';
            if (btn) { btn.disabled = true; btn.innerHTML = '<ion-icon name="sync" class="spinner"></ion-icon> Saving...'; }

            const fd = new FormData();
            fd.append('ajax_bulk_update_flags', '1');
            fd.append('changes', JSON.stringify(changes));

            try {
                const res = await fetch(window.location.href, { method: 'POST', body: fd, credentials: 'same-origin' });
                const d = await res.json();
                if (d.status === 'success') {
                    showToast('Saved ' + (d.updated || changes.length) + ' change(s)!');
                    window.pmDirtyFlags = {};
                    updateAssetSaveBar();
                    loadCourses();
                } else {
                    showToast(d.msg || 'Update failed!', 'error');
                }
            } catch (e) {
                showToast('Update failed!', 'error');
            }
            if (btn) { btn.disabled = false; btn.innerHTML = original; }
        };

        // Warn before losing staged (unsaved) dropdown changes
        window.addEventListener('beforeunload', function (e) {
            if (Object.keys(window.pmDirtyFlags || {}).length) {
                e.preventDefault();
                e.returnValue = '';
            }
        });

        window.toggleAccordion = function(accId, btn) {
            const acc = document.getElementById(accId);
            const isHidden = acc.style.display === 'none' || acc.style.display === '';
            acc.style.display = isHidden ? 'block' : 'none';
            btn.innerHTML = isHidden ? 'Close Access Menu <ion-icon name="chevron-up-outline"></ion-icon>' : 'Manage Access <ion-icon name="chevron-down-outline"></ion-icon>';
        }

        window.saveEnrollment = async function(userEmail, btn) {
            const originalText = btn.innerHTML;
            btn.innerHTML = '⏳ Saving...'; btn.disabled = true;

            const checked = [];
            const card = btn.closest('.user-card');
            card.querySelectorAll('.course-chk:checked').forEach(cb => { checked.push(cb.dataset.cid); });

            const fd = new FormData();
            fd.append('ajax_enrollment', '1');
            fd.append('user_email', userEmail);
            fd.append('courses', JSON.stringify(checked));

            try {
                const res  = await fetch(window.location.href, {method:'POST', body:fd});
                const data = await res.json();
                if(data.status === 'success') {
                    showToast('Access Granted!');
                    loadStudents(); 
                } else { showToast('Failed to update', 'error'); }
            } catch(e) { showToast('Error', 'error'); }
            
            btn.disabled  = false; btn.innerHTML = originalText; 
        }

        window.resolveReport = async function(report_id) {
            if(!confirm("Mark this issue as Resolved? This will remove it from the pending list.")) return;
            const fd = new FormData();
            fd.append('ajax_resolve_report', '1');
            fd.append('report_id', report_id);
            try {
                const res = await fetch(window.location.href, {method:'POST', body:fd});
                const data = await res.json();
                if(data.status === 'success') {
                    showToast('Marked as Resolved!'); loadReports();
                }
            } catch(e) { showToast("Error connecting to server.", "error"); }
        }

        window.viewImage = function(imgSrc) {
            document.getElementById('viewerImage').src = imgSrc;
            document.getElementById('imageViewerModal').style.display = 'flex';
        }

        window.goToStudentAccess = function(email) {
            switchTab('students', document.querySelector('.nav-list a:nth-child(3)'));
            document.getElementById('studentSearch').value = email;
            loadStudents();
        };
    </script>
</body>
</html>