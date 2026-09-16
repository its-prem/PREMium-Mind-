<?php
/**
 * D2D Notes — server storage for d2d_notes.php.
 * Upload to Hostinger premind/ as: d2d_notes_api.php
 *
 * All requests are same-origin from d2d_notes.php and require the admin
 * session established by admin_panel.php (see pm_admin_auth.php).
 *
 * Actions (?action=…, JSON body for POST):
 *   list                      → every non-deleted note, light fields, grouped by subject
 *   get      {id}             → full note + images
 *   save     {note, images}   → insert or update; optimistic-concurrency via `version`
 *   delete   {id}             → soft delete
 *   restore  {id}             → undo soft delete
 *   trash                     → list soft-deleted notes
 *   ping                      → cheap connectivity check for the client's online detector
 *
 * Images are stored in the DB as data URLs (the editor already downsizes
 * them to ≤1400px / jpeg 0.86 before upload), one row per image per note.
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

require_once __DIR__ . '/pm_admin_auth.php';
pm_auth_require_admin_json();

require_once __DIR__ . '/db_connect.php';
$conn->set_charset('utf8mb4');

function d2d_out(array $payload, int $status = 200): void {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}
function d2d_err(string $msg, int $status = 400, array $extra = []): void {
    d2d_out(array_merge(['status' => 'error', 'msg' => $msg], $extra), $status);
}

// ── Schema (idempotent, same pattern admin_panel.php uses for new columns) ──
$conn->query("CREATE TABLE IF NOT EXISTS d2d_notes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    subject VARCHAR(120) NOT NULL DEFAULT '',
    chapter_no VARCHAR(20) NOT NULL DEFAULT '',
    title VARCHAR(200) NOT NULL DEFAULT '',
    subtitle VARCHAR(200) NOT NULL DEFAULT '',
    tagline VARCHAR(200) NOT NULL DEFAULT '',
    badge VARCHAR(120) NOT NULL DEFAULT '',
    notes_text MEDIUMTEXT NULL,
    source_content MEDIUMTEXT NULL,
    settings_json TEXT NULL,
    version INT NOT NULL DEFAULT 1,
    is_deleted TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_subject (subject),
    INDEX idx_deleted (is_deleted),
    INDEX idx_updated (updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$conn->query("CREATE TABLE IF NOT EXISTS d2d_note_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    note_id INT NOT NULL,
    name VARCHAR(60) NOT NULL,
    data LONGTEXT NOT NULL,
    w INT NULL,
    h INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_note_name (note_id, name),
    INDEX idx_note (note_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// ── Input ──
$action = (string)($_GET['action'] ?? $_POST['action'] ?? '');
$raw = file_get_contents('php://input');
$body = json_decode($raw ?: '[]', true);
if (!is_array($body)) $body = [];

$s = function ($v, int $max) { return mb_substr(trim((string)($v ?? '')), 0, $max); };

// ── Actions ──
if ($action === 'ping') {
    d2d_out(['status' => 'success', 'ok' => true, 'ts' => time()]);
}

if ($action === 'list' || $action === 'trash') {
    $deleted = $action === 'trash' ? 1 : 0;
    $stmt = $conn->prepare("SELECT n.id, n.subject, n.chapter_no, n.title, n.subtitle, n.version, n.updated_at, n.created_at,
                                   CHAR_LENGTH(COALESCE(n.notes_text,''))   AS notes_len,
                                   CHAR_LENGTH(COALESCE(n.source_content,'')) AS source_len,
                                   (SELECT COUNT(*) FROM d2d_note_images i WHERE i.note_id = n.id) AS image_count
                            FROM d2d_notes n
                            WHERE n.is_deleted = ?
                            ORDER BY n.subject ASC, CAST(n.chapter_no AS UNSIGNED) ASC, n.chapter_no ASC, n.updated_at DESC");
    if (!$stmt) d2d_err('Query failed: ' . $conn->error, 500);
    $stmt->bind_param('i', $deleted);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    while ($r = $res->fetch_assoc()) {
        $r['id'] = (int)$r['id'];
        $r['version'] = (int)$r['version'];
        $r['notes_len'] = (int)$r['notes_len'];
        $r['source_len'] = (int)$r['source_len'];
        $r['image_count'] = (int)$r['image_count'];
        $rows[] = $r;
    }
    $stmt->close();
    d2d_out(['status' => 'success', 'notes' => $rows]);
}

if ($action === 'get') {
    $id = (int)($_GET['id'] ?? $body['id'] ?? 0);
    if ($id <= 0) d2d_err('Missing id');

    $stmt = $conn->prepare("SELECT * FROM d2d_notes WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $note = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$note) d2d_err('Note not found', 404);

    $note['id'] = (int)$note['id'];
    $note['version'] = (int)$note['version'];
    $note['is_deleted'] = (int)$note['is_deleted'];
    $note['settings'] = json_decode((string)($note['settings_json'] ?? ''), true) ?: null;
    unset($note['settings_json']);

    $imgs = [];
    $stmt = $conn->prepare("SELECT name, data, w, h FROM d2d_note_images WHERE note_id = ? ORDER BY id ASC");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) {
        $imgs[] = ['name' => $r['name'], 'data' => $r['data'], 'w' => (int)$r['w'], 'h' => (int)$r['h']];
    }
    $stmt->close();

    d2d_out(['status' => 'success', 'note' => $note, 'images' => $imgs]);
}

if ($action === 'save') {
    $n = is_array($body['note'] ?? null) ? $body['note'] : [];
    $images = is_array($body['images'] ?? null) ? $body['images'] : [];

    $id         = (int)($n['id'] ?? 0);
    $clientVer  = (int)($n['version'] ?? 0);
    $subject    = $s($n['subject'] ?? '', 120);
    $chapterNo  = $s($n['chapter_no'] ?? '', 20);
    $title      = $s($n['title'] ?? '', 200);
    $subtitle   = $s($n['subtitle'] ?? '', 200);
    $tagline    = $s($n['tagline'] ?? '', 200);
    $badge      = $s($n['badge'] ?? '', 120);
    $notesText  = (string)($n['notes_text'] ?? '');
    $sourceText = (string)($n['source_content'] ?? '');
    $settings   = json_encode(is_array($n['settings'] ?? null) ? $n['settings'] : new stdClass(), JSON_UNESCAPED_UNICODE);

    if ($subject === '') $subject = 'General';
    if ($title === '') $title = 'Untitled';

    // Sanity caps so a runaway client can't wedge the DB with one request
    if (strlen($notesText) > 8_000_000 || strlen($sourceText) > 8_000_000) d2d_err('Text too large', 413);
    if (count($images) > 60) d2d_err('Too many images (max 60 per chapter)', 413);
    $totalImg = 0;
    foreach ($images as $im) {
        if (!is_array($im)) continue;
        $totalImg += strlen((string)($im['data'] ?? ''));
    }
    if ($totalImg > 40_000_000) d2d_err('Images too large in total (max ~40MB per chapter)', 413);

    $conn->begin_transaction();
    try {
        if ($id > 0) {
            // Optimistic concurrency: only apply if the row is still at the
            // version the client last loaded. If another tab/device saved in
            // between, hand back the newer server copy instead of overwriting.
            $chk = $conn->prepare("SELECT version, is_deleted FROM d2d_notes WHERE id = ? FOR UPDATE");
            $chk->bind_param('i', $id);
            $chk->execute();
            $cur = $chk->get_result()->fetch_assoc();
            $chk->close();
            if (!$cur) { $conn->rollback(); d2d_err('Note not found', 404); }

            if ((int)$cur['version'] !== $clientVer) {
                $conn->rollback();
                d2d_err('Server has a newer version of this chapter.', 409, [
                    'code' => 'version_conflict',
                    'server_version' => (int)$cur['version'],
                ]);
            }

            $newVer = (int)$cur['version'] + 1;
            $upd = $conn->prepare("UPDATE d2d_notes
                                     SET subject=?, chapter_no=?, title=?, subtitle=?, tagline=?, badge=?,
                                         notes_text=?, source_content=?, settings_json=?, version=?, is_deleted=0
                                   WHERE id=?");
            $upd->bind_param('sssssssssii', $subject, $chapterNo, $title, $subtitle, $tagline, $badge,
                             $notesText, $sourceText, $settings, $newVer, $id);
            if (!$upd->execute()) throw new RuntimeException($upd->error);
            $upd->close();
        } else {
            $newVer = 1;
            $ins = $conn->prepare("INSERT INTO d2d_notes
                                     (subject, chapter_no, title, subtitle, tagline, badge, notes_text, source_content, settings_json, version)
                                   VALUES (?,?,?,?,?,?,?,?,?,?)");
            $ins->bind_param('sssssssssi', $subject, $chapterNo, $title, $subtitle, $tagline, $badge,
                             $notesText, $sourceText, $settings, $newVer);
            if (!$ins->execute()) throw new RuntimeException($ins->error);
            $id = (int)$conn->insert_id;
            $ins->close();
        }

        // Replace the image set wholesale — the client always sends the full
        // current list, so this keeps DB and editor exactly in step.
        $del = $conn->prepare("DELETE FROM d2d_note_images WHERE note_id = ?");
        $del->bind_param('i', $id);
        if (!$del->execute()) throw new RuntimeException($del->error);
        $del->close();

        if (!empty($images)) {
            $insImg = $conn->prepare("INSERT INTO d2d_note_images (note_id, name, data, w, h) VALUES (?,?,?,?,?)");
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
        error_log('d2d_notes save failed: ' . $e->getMessage());
        d2d_err('Save failed: ' . $e->getMessage(), 500);
    }

    $ts = $conn->query("SELECT updated_at FROM d2d_notes WHERE id = $id")->fetch_assoc();
    d2d_out(['status' => 'success', 'id' => $id, 'version' => $newVer, 'updated_at' => $ts['updated_at'] ?? null]);
}

if ($action === 'delete' || $action === 'restore') {
    $id = (int)($body['id'] ?? $_GET['id'] ?? 0);
    if ($id <= 0) d2d_err('Missing id');
    $flag = $action === 'delete' ? 1 : 0;
    $stmt = $conn->prepare("UPDATE d2d_notes SET is_deleted = ? WHERE id = ?");
    $stmt->bind_param('ii', $flag, $id);
    $ok = $stmt->execute();
    $stmt->close();
    if (!$ok) d2d_err('Update failed: ' . $conn->error, 500);
    d2d_out(['status' => 'success', 'id' => $id]);
}

d2d_err('Unknown action', 400);
