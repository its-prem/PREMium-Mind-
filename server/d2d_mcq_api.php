<?php
/**
 * D2D MCQ — server storage for d2d_mcq.php (PYQ practice sheets).
 * Upload to Hostinger premind/ as: d2d_mcq_api.php
 *
 * Same shape as d2d_notes_api.php: same-origin only, admin session required
 * (pm_admin_auth.php), optimistic concurrency via `version`, images stored as
 * data URLs one row per image per sheet.
 *
 * Actions (?action=…, JSON body for POST):
 *   list                        → every non-deleted sheet, light fields
 *   get      {id}               → full sheet + images
 *   save     {sheet, images}    → insert or update
 *   delete   {id}               → soft delete
 *   restore  {id}               → undo soft delete
 *   trash                       → list soft-deleted sheets
 *   ping                        → cheap connectivity check
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

require_once __DIR__ . '/pm_admin_auth.php';
pm_auth_require_admin_json();

require_once __DIR__ . '/db_connect.php';
$conn->set_charset('utf8mb4');

require_once __DIR__ . '/d2d_folders.php';   // shared folder list (same folders in Notes + MCQ)

function mcq_out(array $payload, int $status = 200): void {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}
function mcq_err(string $msg, int $status = 400, array $extra = []): void {
    mcq_out(array_merge(['status' => 'error', 'msg' => $msg], $extra), $status);
}

// ── Schema (idempotent) ──
$conn->query("CREATE TABLE IF NOT EXISTS d2d_mcq_sheets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    subject VARCHAR(120) NOT NULL DEFAULT '',
    chapter_no VARCHAR(20) NOT NULL DEFAULT '',
    title VARCHAR(200) NOT NULL DEFAULT '',
    badge VARCHAR(120) NOT NULL DEFAULT '',
    tagline VARCHAR(200) NOT NULL DEFAULT '',
    mcq_text MEDIUMTEXT NULL,
    settings_json TEXT NULL,
    version INT NOT NULL DEFAULT 1,
    is_deleted TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_subject (subject),
    INDEX idx_deleted (is_deleted),
    INDEX idx_updated (updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$conn->query("CREATE TABLE IF NOT EXISTS d2d_mcq_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sheet_id INT NOT NULL,
    name VARCHAR(60) NOT NULL,
    data LONGTEXT NOT NULL,
    w INT NULL,
    h INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_sheet_name (sheet_id, name),
    INDEX idx_sheet (sheet_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

d2d_folders_init($conn);

// ── Input ──
$action = (string)($_GET['action'] ?? $_POST['action'] ?? '');
$raw = file_get_contents('php://input');
$body = json_decode($raw ?: '[]', true);
if (!is_array($body)) $body = [];

$s = function ($v, int $max) { return mb_substr(trim((string)($v ?? '')), 0, $max); };

// ── Actions ──
d2d_folders_dispatch($conn, $action, $body);   // handles folder_* and exits; falls through otherwise

if ($action === 'ping') {
    mcq_out(['status' => 'success', 'ok' => true, 'ts' => time()]);
}

if ($action === 'list' || $action === 'trash') {
    $deleted = $action === 'trash' ? 1 : 0;
    $stmt = $conn->prepare("SELECT n.id, n.subject, n.chapter_no, n.title, n.badge, n.version, n.updated_at, n.created_at,
                                   CHAR_LENGTH(COALESCE(n.mcq_text,'')) AS text_len,
                                   (SELECT COUNT(*) FROM d2d_mcq_images i WHERE i.sheet_id = n.id) AS image_count
                            FROM d2d_mcq_sheets n
                            WHERE n.is_deleted = ?
                            ORDER BY n.subject ASC, CAST(n.chapter_no AS UNSIGNED) ASC, n.chapter_no ASC, n.updated_at DESC");
    if (!$stmt) mcq_err('Query failed: ' . $conn->error, 500);
    $stmt->bind_param('i', $deleted);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    while ($r = $res->fetch_assoc()) {
        $r['id'] = (int)$r['id'];
        $r['version'] = (int)$r['version'];
        $r['text_len'] = (int)$r['text_len'];
        $r['image_count'] = (int)$r['image_count'];
        $rows[] = $r;
    }
    $stmt->close();
    mcq_out(['status' => 'success', 'sheets' => $rows, 'folders' => d2d_folders_list($conn)]);
}

if ($action === 'get') {
    $id = (int)($_GET['id'] ?? $body['id'] ?? 0);
    if ($id <= 0) mcq_err('Missing id');

    $stmt = $conn->prepare("SELECT * FROM d2d_mcq_sheets WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $sheet = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$sheet) mcq_err('Sheet not found', 404);

    $sheet['id'] = (int)$sheet['id'];
    $sheet['version'] = (int)$sheet['version'];
    $sheet['is_deleted'] = (int)$sheet['is_deleted'];
    $sheet['settings'] = json_decode((string)($sheet['settings_json'] ?? ''), true) ?: null;
    unset($sheet['settings_json']);

    $imgs = [];
    $stmt = $conn->prepare("SELECT name, data, w, h FROM d2d_mcq_images WHERE sheet_id = ? ORDER BY id ASC");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) {
        $imgs[] = ['name' => $r['name'], 'data' => $r['data'], 'w' => (int)$r['w'], 'h' => (int)$r['h']];
    }
    $stmt->close();

    mcq_out(['status' => 'success', 'sheet' => $sheet, 'images' => $imgs]);
}

if ($action === 'save') {
    $n = is_array($body['sheet'] ?? null) ? $body['sheet'] : [];
    // images omitted → leave the stored set untouched
    $imagesSent = array_key_exists('images', $body) && is_array($body['images']);
    $images = $imagesSent ? $body['images'] : [];

    $id        = (int)($n['id'] ?? 0);
    $clientVer = (int)($n['version'] ?? 0);
    $subject   = $s($n['subject'] ?? '', 120);
    $chapterNo = $s($n['chapter_no'] ?? '', 20);
    $title     = $s($n['title'] ?? '', 200);
    $badge     = $s($n['badge'] ?? '', 120);
    $tagline   = $s($n['tagline'] ?? '', 200);
    $mcqText   = (string)($n['mcq_text'] ?? '');
    $settings  = json_encode(is_array($n['settings'] ?? null) ? $n['settings'] : new stdClass(), JSON_UNESCAPED_UNICODE);

    if ($subject === '') $subject = 'General';
    if ($title === '') $title = 'Untitled';

    if (strlen($mcqText) > 8_000_000) mcq_err('Text too large', 413);
    if (count($images) > 60) mcq_err('Too many images (max 60 per sheet)', 413);
    $totalImg = 0;
    foreach ($images as $im) {
        if (!is_array($im)) continue;
        $totalImg += strlen((string)($im['data'] ?? ''));
    }
    if ($totalImg > 40_000_000) mcq_err('Images too large in total (max ~40MB per sheet)', 413);

    $conn->begin_transaction();
    try {
        if ($id > 0) {
            $chk = $conn->prepare("SELECT version FROM d2d_mcq_sheets WHERE id = ? FOR UPDATE");
            $chk->bind_param('i', $id);
            $chk->execute();
            $cur = $chk->get_result()->fetch_assoc();
            $chk->close();
            if (!$cur) { $conn->rollback(); mcq_err('Sheet not found', 404); }

            if ((int)$cur['version'] !== $clientVer) {
                $conn->rollback();
                mcq_err('Server has a newer version of this sheet.', 409, [
                    'code' => 'version_conflict',
                    'server_version' => (int)$cur['version'],
                ]);
            }

            $newVer = (int)$cur['version'] + 1;
            $upd = $conn->prepare("UPDATE d2d_mcq_sheets
                                     SET subject=?, chapter_no=?, title=?, badge=?, tagline=?,
                                         mcq_text=?, settings_json=?, version=?, is_deleted=0
                                   WHERE id=?");
            $upd->bind_param('sssssssii', $subject, $chapterNo, $title, $badge, $tagline, $mcqText, $settings, $newVer, $id);
            if (!$upd->execute()) throw new RuntimeException($upd->error);
            $upd->close();
        } else {
            $newVer = 1;
            $ins = $conn->prepare("INSERT INTO d2d_mcq_sheets
                                     (subject, chapter_no, title, badge, tagline, mcq_text, settings_json, version)
                                   VALUES (?,?,?,?,?,?,?,?)");
            $ins->bind_param('sssssssi', $subject, $chapterNo, $title, $badge, $tagline, $mcqText, $settings, $newVer);
            if (!$ins->execute()) throw new RuntimeException($ins->error);
            $id = (int)$conn->insert_id;
            $ins->close();
        }

        if ($imagesSent) {
            $del = $conn->prepare("DELETE FROM d2d_mcq_images WHERE sheet_id = ?");
            $del->bind_param('i', $id);
            if (!$del->execute()) throw new RuntimeException($del->error);
            $del->close();
        }

        if ($imagesSent && !empty($images)) {
            $insImg = $conn->prepare("INSERT INTO d2d_mcq_images (sheet_id, name, data, w, h) VALUES (?,?,?,?,?)");
            foreach ($images as $im) {
                if (!is_array($im)) continue;
                $name = $s($im['name'] ?? '', 60);
                $data = (string)($im['data'] ?? '');
                if ($name === '' || strpos($data, 'data:image/') !== 0) continue;
                $w = (int)($im['w'] ?? 0);
                $h = (int)($im['h'] ?? 0);
                $insImg->bind_param('issii', $id, $name, $data, $w, $h);
                if (!$insImg->execute()) throw new RuntimeException($insImg->error);
            }
            $insImg->close();
        }

        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        error_log('d2d_mcq save failed: ' . $e->getMessage());
        mcq_err('Save failed: ' . $e->getMessage(), 500);
    }

    $ts = $conn->query("SELECT updated_at FROM d2d_mcq_sheets WHERE id = $id")->fetch_assoc();
    mcq_out(['status' => 'success', 'id' => $id, 'version' => $newVer, 'updated_at' => $ts['updated_at'] ?? null]);
}

if ($action === 'delete' || $action === 'restore') {
    $id = (int)($body['id'] ?? $_GET['id'] ?? 0);
    if ($id <= 0) mcq_err('Missing id');
    $flag = $action === 'delete' ? 1 : 0;
    $stmt = $conn->prepare("UPDATE d2d_mcq_sheets SET is_deleted = ? WHERE id = ?");
    $stmt->bind_param('ii', $flag, $id);
    $ok = $stmt->execute();
    $stmt->close();
    if (!$ok) mcq_err('Update failed: ' . $conn->error, 500);
    mcq_out(['status' => 'success', 'id' => $id]);
}

mcq_err('Unknown action', 400);
