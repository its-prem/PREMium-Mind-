<?php
/**
 * D2D shared folders — one folder list for both d2d_notes.php and d2d_mcq.php.
 * Upload to Hostinger premind/ as: d2d_folders.php
 *
 * A "folder" is just a named bucket; a note/sheet belongs to it through its
 * own `subject` column. Both editors read the same list so a folder made on
 * the Notes side shows up on the MCQ side and the other way round.
 *
 * Included by d2d_notes_api.php and d2d_mcq_api.php — never called directly.
 *
 * Actions it answers (same ?action= dispatch as its callers):
 *   folder_add    {name}            → create
 *   folder_rename {id, name}        → rename + move every note/sheet in it
 *   folder_delete {id}              → delete; refuses while anything is inside
 */

if (!defined('D2D_FOLDERS')) {
    define('D2D_FOLDERS', 1);

    /** Output through whichever helper the including API defines. */
    function d2d_fold_out(array $payload, int $status = 200): void {
        if (function_exists('d2d_out')) { d2d_out($payload, $status); }
        if (function_exists('mcq_out')) { mcq_out($payload, $status); }
        http_response_code($status);
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit;
    }
    function d2d_fold_err(string $msg, int $status = 400, array $extra = []): void {
        d2d_fold_out(array_merge(['status' => 'error', 'msg' => $msg], $extra), $status);
    }

    /** Tables that store a `subject` pointing at a folder name. */
    function d2d_folder_owner_tables(): array {
        return ['d2d_notes', 'd2d_mcq_sheets'];
    }

    function d2d_table_exists(mysqli $conn, string $table): bool {
        $res = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($table) . "'");
        if (!$res) return false;
        $found = $res->num_rows > 0;
        $res->free();
        return $found;
    }

    function d2d_folders_init(mysqli $conn): void {
        $conn->query("CREATE TABLE IF NOT EXISTS d2d_folders (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(120) NOT NULL,
            sort INT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_name (name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    /**
     * Every folder, newest schema first: the rows in d2d_folders plus any
     * subject already in use that nobody has registered as a folder yet (so
     * chapters written before folders existed still have a home).
     */
    function d2d_folders_list(mysqli $conn): array {
        $out = [];
        $seen = [];
        $res = $conn->query("SELECT id, name, sort FROM d2d_folders ORDER BY sort ASC, name ASC");
        if ($res) {
            while ($r = $res->fetch_assoc()) {
                $out[] = ['id' => (int)$r['id'], 'name' => (string)$r['name'], 'sort' => (int)$r['sort']];
                $seen[mb_strtolower($r['name'])] = true;
            }
            $res->free();
        }
        foreach (d2d_folder_owner_tables() as $t) {
            if (!d2d_table_exists($conn, $t)) continue;
            $res = $conn->query("SELECT DISTINCT subject FROM `$t` WHERE is_deleted = 0 AND subject <> ''");
            if (!$res) continue;
            while ($r = $res->fetch_assoc()) {
                $name = (string)$r['subject'];
                $key = mb_strtolower($name);
                if ($name === '' || isset($seen[$key])) continue;
                $seen[$key] = true;
                $out[] = ['id' => 0, 'name' => $name, 'sort' => 0];
            }
            $res->free();
        }
        usort($out, function ($a, $b) {
            if ($a['sort'] !== $b['sort']) return $a['sort'] <=> $b['sort'];
            return strcasecmp($a['name'], $b['name']);
        });
        return $out;
    }

    /** How many non-deleted rows sit in a folder, across both editors. */
    function d2d_folder_usage(mysqli $conn, string $name): int {
        $n = 0;
        foreach (d2d_folder_owner_tables() as $t) {
            if (!d2d_table_exists($conn, $t)) continue;
            $stmt = $conn->prepare("SELECT COUNT(*) AS c FROM `$t` WHERE is_deleted = 0 AND subject = ?");
            if (!$stmt) continue;
            $stmt->bind_param('s', $name);
            if ($stmt->execute()) {
                $res = $stmt->get_result();
                if ($res && ($row = $res->fetch_assoc())) $n += (int)$row['c'];
                if ($res) $res->free();
            }
            $stmt->close();
        }
        return $n;
    }

    /**
     * Handles the folder_* actions. Returns false when $action is not one of
     * them, so callers can fall through to their own dispatch; otherwise it
     * answers via d2d_fold_out()/d2d_fold_err() and exits.
     */
    function d2d_folders_dispatch(mysqli $conn, string $action, array $body): bool {
        if (strpos($action, 'folder_') !== 0) return false;
        $name = mb_substr(trim((string)($body['name'] ?? '')), 0, 120);

        if ($action === 'folder_list') {
            d2d_fold_out(['status' => 'success', 'folders' => d2d_folders_list($conn)]);
        }

        if ($action === 'folder_add') {
            if ($name === '') d2d_fold_err('Folder ka naam likho');
            $stmt = $conn->prepare("INSERT IGNORE INTO d2d_folders (name, sort) VALUES (?, 0)");
            if (!$stmt) d2d_fold_err('Query failed: ' . $conn->error, 500);
            $stmt->bind_param('s', $name);
            if (!$stmt->execute()) d2d_fold_err('Folder ban nahi paya: ' . $stmt->error, 500);
            $stmt->close();
            d2d_fold_out(['status' => 'success', 'name' => $name, 'folders' => d2d_folders_list($conn)]);
        }

        if ($action === 'folder_rename') {
            $old = mb_substr(trim((string)($body['old'] ?? '')), 0, 120);
            if ($name === '' || $old === '') d2d_fold_err('Purana aur naya naam dono chahiye');
            if ($name === $old) d2d_fold_out(['status' => 'success', 'folders' => d2d_folders_list($conn)]);

            // renaming onto a folder that already exists = merge the two
            $exists = false;
            $stmt = $conn->prepare("SELECT COUNT(*) AS c FROM d2d_folders WHERE name = ?");
            if ($stmt) {
                $stmt->bind_param('s', $name);
                if ($stmt->execute()) {
                    $res = $stmt->get_result();
                    if ($res && ($row = $res->fetch_assoc())) $exists = (int)$row['c'] > 0;
                    if ($res) $res->free();
                }
                $stmt->close();
            }

            $conn->begin_transaction();
            try {
                $sql = $exists ? "DELETE FROM d2d_folders WHERE name = ?" : "UPDATE d2d_folders SET name = ? WHERE name = ?";
                $stmt = $conn->prepare($sql);
                if ($stmt) {
                    if ($exists) $stmt->bind_param('s', $old);
                    else $stmt->bind_param('ss', $name, $old);
                    $stmt->execute();
                    $stmt->close();
                }
                // no row in d2d_folders (legacy subject) → register the new name
                $stmt = $conn->prepare("INSERT IGNORE INTO d2d_folders (name, sort) VALUES (?, 0)");
                if ($stmt) { $stmt->bind_param('s', $name); $stmt->execute(); $stmt->close(); }

                foreach (d2d_folder_owner_tables() as $t) {
                    if (!d2d_table_exists($conn, $t)) continue;
                    $stmt = $conn->prepare("UPDATE `$t` SET subject = ? WHERE subject = ?");
                    if (!$stmt) continue;
                    $stmt->bind_param('ss', $name, $old);
                    if (!$stmt->execute()) throw new RuntimeException($stmt->error);
                    $stmt->close();
                }
                $conn->commit();
            } catch (Throwable $e) {
                $conn->rollback();
                d2d_fold_err('Rename fail: ' . $e->getMessage(), 500);
            }
            d2d_fold_out(['status' => 'success', 'name' => $name, 'folders' => d2d_folders_list($conn)]);
        }

        if ($action === 'folder_delete') {
            if ($name === '') d2d_fold_err('Folder ka naam chahiye');
            $used = d2d_folder_usage($conn, $name);
            if ($used > 0) d2d_fold_err('Folder khali nahi hai — ' . $used . ' item andar hai', 409, ['code' => 'folder_not_empty', 'used' => $used]);
            $stmt = $conn->prepare("DELETE FROM d2d_folders WHERE name = ?");
            if ($stmt) { $stmt->bind_param('s', $name); $stmt->execute(); $stmt->close(); }
            d2d_fold_out(['status' => 'success', 'folders' => d2d_folders_list($conn)]);
        }

        d2d_fold_err('Unknown folder action', 404);
        return true;
    }
}
