<?php
/**
 * Upload to Hostinger premind/ as: progress.php
 *
 * Saves small pieces of a student's work on the server so it follows them to another phone/laptop:
 *   kind = "resume"            the resume editor data
 *   kind = "syllabus-<id>"     ticked syllabus topics   (example: syllabus-chemistry)
 *   kind = "mock"              best mock-test scores
 * Only the logged-in user (signed token) can read or write their own data.
 *
 * GET  ?kind=resume                    -> { "status":"success", "data":{...}, "updated_at":"...", "ts":1764000000000 }  (data is null if nothing saved)
 * GET  (no kind)                       -> { "status":"success", "items": { "resume": {...}, ... } }  everything of this user
 * POST { "kind":"resume", "data":{...} } -> { "status":"success", "updated_at":"..." }
 * POST { "kind":"resume", "delete":true } -> removes it
 */

require_once __DIR__ . '/pm_cors.php';
pm_cors_json(['GET', 'POST']);

require_once __DIR__ . '/pm_session_token.php';
include __DIR__ . '/db_connect.php';

$MAX_BYTES = 200 * 1024; // one saved item may be at most 200 KB

$body  = pm_request_body();
$email = strtolower(trim(pm_session_email(pm_request_token($body))));
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Login required', 'code' => 'login_required']);
    exit;
}

$conn->query("CREATE TABLE IF NOT EXISTS user_progress (
    user_email VARCHAR(190) NOT NULL,
    kind VARCHAR(40) NOT NULL,
    data MEDIUMTEXT NOT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_email, kind)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$validKind = function ($k): bool {
    return is_string($k) && preg_match('/^[a-z0-9][a-z0-9_-]{0,39}$/', $k) === 1;
};

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $kind = $_GET['kind'] ?? '';
    if ($kind !== '') {
        if (!$validKind($kind)) { http_response_code(400); echo json_encode(['status' => 'error', 'message' => 'bad kind']); exit; }
        $stmt = $conn->prepare('SELECT data, updated_at, UNIX_TIMESTAMP(updated_at) * 1000 AS ts FROM user_progress WHERE user_email = ? AND kind = ? LIMIT 1');
        $stmt->bind_param('ss', $email, $kind);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        echo json_encode([
            'status'     => 'success',
            'data'       => $row ? json_decode($row['data'], true) : null,
            'updated_at' => $row['updated_at'] ?? null,
            'ts'         => $row ? (int)$row['ts'] : 0,
        ]);
        exit;
    }
    $stmt = $conn->prepare('SELECT kind, data FROM user_progress WHERE user_email = ?');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $res = $stmt->get_result();
    $items = [];
    while ($r = $res->fetch_assoc()) $items[$r['kind']] = json_decode($r['data'], true);
    $stmt->close();
    echo json_encode(['status' => 'success', 'items' => (object)$items]);
    exit;
}

// ---- POST ----
$kind = $body['kind'] ?? '';
if (!$validKind($kind)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'bad kind']);
    exit;
}

if (!empty($body['delete'])) {
    $stmt = $conn->prepare('DELETE FROM user_progress WHERE user_email = ? AND kind = ?');
    $stmt->bind_param('ss', $email, $kind);
    $stmt->execute();
    $stmt->close();
    echo json_encode(['status' => 'success']);
    exit;
}

if (!array_key_exists('data', $body)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'data missing']);
    exit;
}
$json = json_encode($body['data'], JSON_UNESCAPED_UNICODE);
if ($json === false || strlen($json) > $MAX_BYTES) {
    http_response_code(413);
    echo json_encode(['status' => 'error', 'message' => 'Too large (max 200 KB).']);
    exit;
}

$stmt = $conn->prepare('INSERT INTO user_progress (user_email, kind, data) VALUES (?, ?, ?)
                        ON DUPLICATE KEY UPDATE data = VALUES(data), updated_at = NOW()');
$stmt->bind_param('sss', $email, $kind, $json);
$ok = $stmt->execute();
$stmt->close();

if (!$ok) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Could not save.']);
    exit;
}
echo json_encode(['status' => 'success', 'updated_at' => date('Y-m-d H:i:s'), 'ts' => time() * 1000]);
