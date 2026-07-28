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

// ════════════════════════════════════════════════
//  AJAX HANDLERS (API ENDPOINTS) — auth required
// ════════════════════════════════════════════════

// 1. FETCH COURSES
if (isset($_GET['fetch_table'])) {
    pm_require_admin(false);
    $result = $conn->query("SELECT * FROM courses ORDER BY id DESC");
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $json = htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8');
            
            // Mobile optimized badges
            $pdf_status = !empty($row['pdf_file']) 
                ? "<span class='badge-success' style='display:inline-flex; align-items:center; gap:4px; margin-bottom:6px; white-space:nowrap;'>📄 PDF Uploaded</span>" 
                : "<span class='badge-danger' style='display:inline-flex; align-items:center; gap:4px; margin-bottom:6px; white-space:nowrap;'>No PDF</span>";
            
            $dl_status = (!empty($row['allow_download']) && (int)$row['allow_download'] === 1)
                ? "<span style='display:inline-flex; align-items:center; gap:4px; background:#eff6ff; color:#2563eb; padding:3px 8px; border-radius:10px; font-size:11px; font-weight:700; white-space:nowrap;'><ion-icon name='download-outline'></ion-icon> DOWNLOAD BTN: ON</span>"
                : "<span style='display:inline-flex; align-items:center; gap:4px; background:#f1f5f9; color:#64748b; padding:3px 8px; border-radius:10px; font-size:11px; font-weight:700; white-space:nowrap;'><ion-icon name='close-outline'></ion-icon> DOWNLOAD BTN: OFF</span>";

            $app_only_on = !empty($row['app_only']) && (int)$row['app_only'] === 1;
            $app_only_status = $app_only_on
                ? "<span style='display:inline-flex; align-items:center; gap:4px; background:#111; color:#fff; padding:3px 8px; border-radius:10px; font-size:11px; font-weight:700; white-space:nowrap; margin-top:6px;'><ion-icon name='phone-portrait-outline'></ion-icon> APP ONLY: ON</span>"
                : "<span style='display:inline-flex; align-items:center; gap:4px; background:#f1f5f9; color:#64748b; padding:3px 8px; border-radius:10px; font-size:11px; font-weight:700; white-space:nowrap; margin-top:6px;'><ion-icon name='globe-outline'></ion-icon> APP ONLY: OFF</span>";

            $preview_on = !empty($row['show_preview']) && (int)$row['show_preview'] === 1;
            $preview_status = $preview_on
                ? "<span style='display:inline-flex; align-items:center; gap:4px; background:#eff6ff; color:#1d4ed8; padding:3px 8px; border-radius:10px; font-size:11px; font-weight:700; white-space:nowrap; margin-top:6px;'><ion-icon name='eye-outline'></ion-icon> PREVIEW: ON</span>"
                : "<span style='display:inline-flex; align-items:center; gap:4px; background:#f1f5f9; color:#64748b; padding:3px 8px; border-radius:10px; font-size:11px; font-weight:700; white-space:nowrap; margin-top:6px;'><ion-icon name='eye-off-outline'></ion-icon> PREVIEW: OFF</span>";

            // Check if course is deleted
            $is_deleted = isset($row['is_deleted']) && $row['is_deleted'] == 1;
            $row_style = $is_deleted ? "opacity: 0.6; background: #fef2f2;" : "";
            $status_badge = $is_deleted ? "<div style='margin-top:6px;'><span class='badge-danger' style='white-space:nowrap;'>🗑️ Trashed</span></div>" : "<div style='margin-top:6px;'><span class='badge-success' style='white-space:nowrap;'>🟢 Active</span></div>";

            echo "<tr style='$row_style' class='fade-in'>
                <td style='font-weight:700;'>#".$row['id']."</td>
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

// 2. FETCH STUDENTS
if (isset($_GET['fetch_students'])) {
    pm_require_admin(false);
    $search = isset($_GET['search']) ? $conn->real_escape_string(trim($_GET['search'])) : '';
    $where  = $search ? "WHERE u.name LIKE '%$search%' OR u.email LIKE '%$search%'" : '';

    $result = $conn->query("SELECT u.id, u.name, u.email, u.is_active, u.created_at, GROUP_CONCAT(uc.course_id) AS enrolled_ids FROM users u LEFT JOIN user_courses uc ON uc.user_email = u.email $where GROUP BY u.id ORDER BY u.id DESC LIMIT 50");
    
    $courses = $conn->query("SELECT id, title FROM courses WHERE is_deleted=0 ORDER BY id ASC");
    $courseList = [];
    while ($c = $courses->fetch_assoc()) $courseList[] = $c;

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
                    <span class='ct-name'>".$c['title']."</span>
                    <label class='switch'>
                        <input type='checkbox' class='course-chk' data-uemail='".$row['email']."' data-cid='".$c['id']."' $chk>
                        <span class='slider'></span>
                    </label>
                </div>";
            }

            $accId = "acc_" . $row['id'];

            echo "
            <div class='user-card fade-in'>
                <div class='user-header'>
                    <div class='u-avatar'>".strtoupper(substr($row['name'], 0, 1))."</div>
                    <div class='u-details'>
                        <div style='display:flex; justify-content:space-between; align-items:center; margin-bottom:5px;'>
                            <h4>".$row['name']."</h4>
                            $verified
                        </div>
                        <p><ion-icon name='mail-outline'></ion-icon> ".$row['email']."</p>
                        <p><ion-icon name='calendar-outline'></ion-icon> Joined: $created</p>
                    </div>
                </div>
                
                <div style='background:#f8fafc; padding:10px 15px; border-radius:10px; margin-bottom:15px; font-size:0.85rem; font-weight:600; display:flex; justify-content:space-between;'>
                    <span>Enrolled Courses:</span> <span style='color:var(--success); font-size:1rem;'>$enrolledCount</span>
                </div>

                <button class='btn-outline' onclick='toggleAccordion(\"$accId\", this)'>Manage Access <ion-icon name='chevron-down-outline'></ion-icon></button>

                <div class='course-toggle-list' id='$accId'>
                    $toggles
                    <button class='btn-submit' style='margin-top:15px; padding:10px; font-size:0.85rem;' onclick='saveEnrollment(\"".$row['email']."\", this)'>💾 Save Changes</button>
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
            $name = !empty($row['user_name']) ? $row['user_name'] : 'Unknown User';
            $phone = !empty($row['user_phone']) ? $row['user_phone'] : 'N/A';
            $msg = htmlspecialchars($row['message']);
            $subject_name = !empty($row['course_title']) ? $row['course_title'] : 'Unknown Course (ID: ' . $row['course_id'] . ')';

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
                    <p style='color:var(--primary); font-weight:600;'><ion-icon name='mail'></ion-icon> ".$row['email']."</p>
                    <p><ion-icon name='call'></ion-icon> $phone</p>
                    
                    <div style='margin-top:12px; padding:12px; background:var(--gray-soft); border-radius:8px; font-size:0.9rem; color:var(--dark); border:1px solid var(--gray-border);'>
                        <strong style='color:#be123c;'>Problem Description:</strong><br>$msg
                    </div>
                    <small style='display:block; margin-top:8px; color:var(--gray-dark); font-weight:600;'><ion-icon name='time'></ion-icon> Reported on: $date</small>
                </div>";
                
                if(!empty($screenshot)) {
                    echo "<button class='btn-outline' style='border-color:#fda4af; color:#be123c; background:#fff1f2; margin-bottom:12px;' onclick='viewImage(\"$screenshot\")'><ion-icon name='image'></ion-icon> View Screenshot</button>";
                }
                
                echo "
                <div style='display:flex; flex-direction:column; gap:10px; border-top: 1px dashed var(--gray-border); padding-top: 15px;'>
                    <button class='btn-outline' style='border-color:#93c5fd; color:#1d4ed8; background:#eff6ff;' onclick='goToStudentAccess(\"".$row['email']."\")'><ion-icon name='open'></ion-icon> Open Student Profile</button>
                    <button class='btn-submit' style='background:var(--success);' onclick='resolveReport(".$row['id'].")'><ion-icon name='checkmark-circle'></ion-icon> Mark Issue as Resolved</button>
                </div>
            </div>";
        }
    } else {
        echo "<div style='grid-column:1/-1; text-align:center; padding:50px; color:var(--gray-dark); background:white; border-radius:16px; border:1px dashed #cbd5e1;'><ion-icon name='checkmark-done-circle' style='font-size:50px; color:var(--success);'></ion-icon><h3 style='margin-top:10px; color:var(--dark);'>All Caught Up!</h3><p>No pending reports.</p></div>";
    }
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

    $image_path = $conn->real_escape_string($_POST['existing_image'] ?? 'small-logo.png');
    $pdf_path = $conn->real_escape_string($_POST['existing_pdf'] ?? '');
    
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
        $sql = "INSERT INTO courses (title,category,image,badge,desc1,desc2,price,old_price,link,demo_link,website_link,pdf_file,allow_download,btn_text,btn_type,show_tnc,show_report_btn,app_only,show_preview,tnc_text,download_msg) VALUES ('$title','$category','$image_path','$badge','$desc1','$desc2','$price','$old_price','$link','$demo_link','$website_link','$pdf_path','$allow_dl','$btn_text','$btn_type','$show_tnc','$show_report_btn','$app_only','$show_preview','$tnc_text','$download_msg')";
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

/* Students Grid */
.toolbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; gap: 15px; flex-wrap: wrap; }
.search-box { position: relative; flex: 1; max-width: 400px; }
.search-box ion-icon { position: absolute; left: 18px; top: 50%; transform: translateY(-50%); color: var(--dark-muted); font-size: 1.2rem; }
.search-box input { width: 100%; padding: 14px 20px 14px 50px; border-radius: 50px; background: white; margin: 0; box-shadow: var(--shadow-sm); border: 1px solid var(--gray-border); }

.grid-cards { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 24px; }
.user-card { background: white; border: 1px solid var(--gray-border); border-radius: 20px; padding: 24px; box-shadow: var(--shadow-sm); transition: var(--smooth); display: flex; flex-direction: column; }
.user-card:hover { border-color: #cbd5e1; box-shadow: var(--shadow-md); transform: translateY(-4px); }
.user-header { display: flex; gap: 15px; align-items: center; margin-bottom: 15px; border-bottom: 1px dashed var(--gray-border); padding-bottom: 15px; }
.u-avatar { width: 55px; height: 55px; border-radius: 50%; background: var(--dark); color: white; display: flex; justify-content: center; align-items: center; font-size: 1.4rem; font-weight: 800; flex-shrink: 0; }
.u-details { min-width: 0; flex: 1; }
.u-details h4 { font-size: 1.1rem; font-weight: 800; color: var(--dark); margin-bottom: 4px; }
.u-details p { font-size: 0.85rem; color: var(--dark-muted); display: flex; align-items: center; gap: 6px; margin-bottom: 2px; word-break: break-all;}

.course-toggle-list { background: var(--gray-soft); border-radius: 12px; padding: 15px; margin-top: 15px; display: none; max-height: 250px; overflow-y: auto; border: 1px solid var(--gray-border); }
.ct-item { display: flex; justify-content: space-between; align-items: center; padding: 10px 0; border-bottom: 1px dashed #cbd5e1; gap: 10px; }
.ct-item:last-child { border-bottom: none; }
.ct-name { font-size: 0.9rem; font-weight: 700; color: var(--dark); width: 75%; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

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
    .top-header { padding: 12px 16px; }
    .top-header h2 { font-size: 1.05rem; }
    .tab-panel { padding: 18px 14px; }
    .toolbar { flex-direction: column; align-items: stretch; }
    .search-box { max-width: 100%; }
    .list-section { padding: 15px; margin-top: 20px;}
    .form-section { padding: 20px; border-radius: 16px; }
    .user-info #admin-info-box { display: none !important; }
    .grid-cards { grid-template-columns: 1fr; gap: 16px; }
    .stats-grid { grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 12px; }
    .stat-card { padding: 15px; gap: 12px; border-radius: 16px; }
    .stat-icon { width: 46px; height: 46px; font-size: 1.4rem; border-radius: 12px; }
    .stat-info h3 { font-size: 1.4rem; }
    .action-buttons { flex-wrap: wrap; }
    #toast { left: 15px; right: 15px; bottom: 15px; max-width: none; }
}

@media (max-width: 480px) {
    .top-header { padding: 10px 12px; }
    .top-header h2 { display: none; }
    .tab-panel { padding: 14px 10px; }
    .section-title { font-size: 1.15rem; margin-bottom: 18px; }
    .form-section { padding: 15px; }
    .file-upload-box { padding: 18px 12px; }
    .stats-grid { grid-template-columns: 1fr 1fr; }
    .auth-box { padding: 25px 20px; }
    .u-avatar { width: 44px; height: 44px; font-size: 1.1rem; }
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
                </div>

                <div id="tab-courses" class="tab-panel">
                    
                    <div class="main-layout">
                        <!-- Form Section Now Full Centered Without Preview -->
                        <div class="form-section">
                            <h3 id="form_title" class="section-title" style="margin-bottom: 25px;"><ion-icon name="add-circle"></ion-icon> Create New Course</h3>
                            
                            <form id="courseForm" enctype="multipart/form-data">
                                <input type="hidden" name="edit_id" id="edit_id" value="">
                                
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
                                            <option value="disabled_look">Disabled Look (Orange Unclickable)</option>
                                            <option value="preview_buy">Preview (Demo) + Buy</option>
                                            <option value="disabled">Completely Disabled (Unclickable)</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="row" style="margin-top:20px;">
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
                                        <th style="width: 80px;">ID</th>
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
            if(tabId === 'students') loadStudents();
            if(tabId === 'reports') loadReports();
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
            }
            else display.innerText = "";
        }

        function showImgName(input) {
            const display = document.getElementById('img-name');
            if (input.files && input.files[0]) {
                display.innerText = "Selected Image: " + input.files[0].name;
            } else {
                display.innerText = "";
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
                tbody.innerHTML = await r.text();
            } catch(e) { showToast("Failed to load courses", "error"); }
        }

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

        window.editCard = function(btn) {
            const data = JSON.parse(btn.getAttribute('data-course'));
            
            ['title','category','badge','desc1','desc2','price','old_price','link','demo_link','website_link','btn_text','btn_type'].forEach(k => {
                if(document.getElementById('inp_'+k)) document.getElementById('inp_'+k).value = data[k] || '';
            });
            
            document.getElementById('inp_existing_image').value = data.image || '';
            if(data.image) {
                document.getElementById('img-name').innerText = "Current: " + data.image;
            }

            document.getElementById('inp_existing_pdf').value = data.pdf_file || '';
            document.getElementById('file-name').innerHTML = data.pdf_file ? "Current PDF Attached" : "";
            
            document.getElementById('inp_allow_download').checked = data.allow_download == 1 || data.allow_download === '1' || data.allow_download === true;
            document.getElementById('inp_show_tnc').checked = data.show_tnc == 1;
            document.getElementById('inp_show_report_btn').checked = data.show_report_btn == 1;
            document.getElementById('inp_app_only').checked = data.app_only == 1 || data.app_only === '1' || data.app_only === true;
            document.getElementById('inp_show_preview').checked = data.show_preview == 1 || data.show_preview === '1' || data.show_preview === true;
            document.getElementById('inp_tnc_text').value = data.tnc_text || '';
            document.getElementById('inp_download_msg').value = data.download_msg || '';
            syncConditionalBoxes();

            document.getElementById('edit_id').value = data.id;
            document.getElementById('form_title').innerHTML = '<ion-icon name="create"></ion-icon> Edit Course';
            document.getElementById('submitBtn').innerHTML  = '<ion-icon name="save"></ion-icon> Save Updates';
            document.getElementById('cancelBtn').style.display = 'inline-flex';
            
            document.querySelector('.form-section').scrollIntoView({behavior:'smooth'});
        }

        window.cancelEdit = function() {
            document.getElementById('courseForm').reset();
            document.getElementById('edit_id').value = '';
            document.getElementById('file-name').innerHTML = '';
            document.getElementById('inp_existing_pdf').value = '';
            document.getElementById('img-name').innerHTML = '';
            document.getElementById('inp_existing_image').value = '';
            
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