<?php
// Error reporting on for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

date_default_timezone_set('Asia/Kolkata');
session_start();

// 🔥 DIRECT DB CONNECTION AS REQUESTED 🔥
include '../db.php'; 
if (!isset($conn) || $conn->connect_error) { 
    die("❌ DB Connection Failed: " . $conn->connect_error); 
}
$conn->set_charset('utf8mb4');

$admin_name = isset($_SESSION['username']) ? $_SESSION['username'] : 'Admin';
$view = isset($_GET['view']) ? $_GET['view'] : 'list';

// ====================================================
// 🚀 ACTIONS (CREATE TEST, ADD QUESTIONS, UPLOAD JSON)
// (Only runs if Admin is logged in and making changes)
// ====================================================
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    
    // --- 1. CREATE / UPDATE TEST ---
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_test'])) {
        $title = mysqli_real_escape_string($conn, $_POST['title']);
        $raw_slug = !empty($_POST['slug']) ? trim($_POST['slug']) : strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title)));
        $slug = mysqli_real_escape_string($conn, trim($raw_slug, '-'));
        $subtitle = mysqli_real_escape_string($conn, $_POST['subtitle']);
        $tags = mysqli_real_escape_string($conn, $_POST['tags']);
        $total_minutes = (int)$_POST['total_minutes'];
        $per_question_sec = (int)$_POST['per_question_sec'];
        $mark = (float)$_POST['mark'];
        $negative = (float)$_POST['negative'];
        $is_active = (int)$_POST['is_active'];

        $is_new = false;
        if (isset($_POST['test_id']) && !empty($_POST['test_id'])) {
            $id = (int)$_POST['test_id'];
            $sql = "UPDATE web_test_series SET title='$title', slug='$slug', subtitle='$subtitle', tags='$tags', total_minutes=$total_minutes, per_question_sec=$per_question_sec, mark=$mark, negative=$negative, is_active=$is_active WHERE id=$id";
            $msg = "✅ Test Updated Successfully!";
        } else {
            $sql = "INSERT INTO web_test_series (title, slug, subtitle, tags, total_minutes, per_question_sec, mark, negative, is_active) VALUES ('$title', '$slug', '$subtitle', '$tags', $total_minutes, $per_question_sec, $mark, $negative, $is_active)";
            $msg = "✅ New Test Created! Now Add Questions Below.";
            $is_new = true;
        }

        if ($conn->query($sql)) {
            $_SESSION['msg'] = $msg;
            if ($is_new) {
                $new_id = $conn->insert_id;
                header("Location: index.php?view=questions&test_id=$new_id");
            } else {
                header("Location: index.php?view=list");
            }
            exit();
        } else {
            $_SESSION['error'] = "Database Error: " . $conn->error;
        }
    }

    // --- 2. DELETE TEST ---
    if (isset($_GET['delete_test'])) {
        $id = (int)$_GET['delete_test'];
        $conn->query("DELETE FROM web_test_series WHERE id = $id");
        $_SESSION['msg'] = "🗑️ Test Deleted Successfully.";
        header("Location: index.php?view=list");
        exit();
    }

    // --- 3. UPLOAD BULK JSON QUESTIONS (REPLACE ALL) ---
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['upload_json_qs'])) {
        $test_id = (int)$_POST['test_id'];
        $json_data = trim($_POST['json_data']);
        $questions = json_decode($json_data, true);
        
        if(empty($json_data) || $json_data === '[]') {
            $conn->query("DELETE FROM web_test_questions WHERE test_id = $test_id");
            $_SESSION['msg'] = "✅ All questions cleared successfully!";
        }
        elseif (json_last_error() === JSON_ERROR_NONE && is_array($questions)) {
            $conn->query("DELETE FROM web_test_questions WHERE test_id = $test_id");
            $count = 0;
            foreach ($questions as $q) {
                if (isset($q['q']) && isset($q['o']) && is_array($q['o']) && isset($q['a'])) {
                    $q_text = mysqli_real_escape_string($conn, $q['q']);
                    $o0 = mysqli_real_escape_string($conn, $q['o'][0]);
                    $o1 = mysqli_real_escape_string($conn, $q['o'][1]);
                    $o2 = mysqli_real_escape_string($conn, $q['o'][2] ?? '');
                    $o3 = mysqli_real_escape_string($conn, $q['o'][3] ?? '');
                    $ans = (int)$q['a'];
                    $exp = isset($q['e']) ? mysqli_real_escape_string($conn, $q['e']) : '';
                    
                    $sql = "INSERT INTO web_test_questions (test_id, question_text, opt_0, opt_1, opt_2, opt_3, correct_opt_index, explanation) VALUES ($test_id, '$q_text', '$o0', '$o1', '$o2', '$o3', $ans, '$exp')";
                    if ($conn->query($sql)) $count++;
                }
            }
            $_SESSION['msg'] = "✅ $count Questions synced & saved successfully via JSON!";
        } else {
            $_SESSION['error'] = "❌ Invalid JSON format.";
        }
        header("Location: index.php?view=questions&test_id=$test_id");
        exit();
    }

    // --- 4. ADD SINGLE QUESTION MANUAL ---
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_single_q'])) {
        $test_id = (int)$_POST['test_id'];
        $q_text = mysqli_real_escape_string($conn, $_POST['q_text']);
        $o0 = mysqli_real_escape_string($conn, $_POST['o0']);
        $o1 = mysqli_real_escape_string($conn, $_POST['o1']);
        $o2 = mysqli_real_escape_string($conn, $_POST['o2']);
        $o3 = mysqli_real_escape_string($conn, $_POST['o3']);
        $ans = (int)$_POST['ans'];
        $exp = mysqli_real_escape_string($conn, $_POST['exp']);

        $sql = "INSERT INTO web_test_questions (test_id, question_text, opt_0, opt_1, opt_2, opt_3, correct_opt_index, explanation) VALUES ($test_id, '$q_text', '$o0', '$o1', '$o2', '$o3', $ans, '$exp')";
        if ($conn->query($sql)) {
            $_SESSION['msg'] = "✅ Question added successfully!";
        } else {
            $_SESSION['error'] = "❌ Error adding question: " . $conn->error;
        }
        header("Location: index.php?view=questions&test_id=$test_id");
        exit();
    }

    // --- 5. DELETE QUESTION ---
    if (isset($_GET['delete_q']) && isset($_GET['test_id'])) {
        $q_id = (int)$_GET['delete_q'];
        $test_id = (int)$_GET['test_id'];
        $conn->query("DELETE FROM web_test_questions WHERE id = $q_id");
        $_SESSION['msg'] = "🗑️️ Question Deleted.";
        header("Location: index.php?view=questions&test_id=$test_id");
        exit();
    }
}

// ======================================================================
// TEST ENGINE UTILS
// ======================================================================
function d2d_json(array $payload, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
function d2d_slug(string $title): string {
    return trim(strtolower((string) preg_replace('/[^A-Za-z0-9]+/', '-', $title)), '-');
}
function d2d_student_value(array $keys, string $fallback = ''): string {
    foreach ($keys as $key) {
        if (!empty($_SESSION[$key])) return trim((string) $_SESSION[$key]);
    }
    return $fallback;
}

if (empty($_SESSION['d2d_test_csrf'])) {
    $_SESSION['d2d_test_csrf'] = bin2hex(random_bytes(32));
}

// ======================================================================
// HANDLE AJAX SUBMISSION (SAVES RESULT TO DATABASE) - FIX FOR SHUFFLE
// ======================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'submit_attempt') {
    $csrf = (string) ($_POST['csrf'] ?? '');
    if (!hash_equals($_SESSION['d2d_test_csrf'], $csrf)) {
        d2d_json(['status' => 'error', 'msg' => 'Your session has expired. Refresh the test and try again.'], 419);
    }

    $testId = filter_var($_POST['test_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $answers = json_decode((string) ($_POST['answers'] ?? ''), true); // Mapping of question_id => answer_index
    if (!$testId || !is_array($answers)) {
        d2d_json(['status' => 'error', 'msg' => 'Invalid test submission.'], 422);
    }

    $testStmt = $conn->prepare('SELECT id, total_minutes, mark, negative FROM web_test_series WHERE id = ? AND is_active = 1 LIMIT 1');
    $testStmt->bind_param('i', $testId);
    $testStmt->execute();
    $test = $testStmt->get_result()->fetch_assoc();
    $testStmt->close();
    
    if (!$test) {
        d2d_json(['status' => 'error', 'msg' => 'This test is no longer available.'], 404);
    }

    // Always fetch in consistent order from DB to verify
    $questionStmt = $conn->prepare('SELECT id, correct_opt_index, explanation FROM web_test_questions WHERE test_id = ?');
    $questionStmt->bind_param('i', $testId);
    $questionStmt->execute();
    $questionRows = $questionStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $questionStmt->close();

    $correct = 0; $wrong = 0; $unanswered = 0; $review = [];
    
    // Evaluate mapped by Question ID (Fixes the Shuffle bug perfectly)
    foreach ($questionRows as $question) {
        $qId = $question['id'];
        $picked = $answers[$qId] ?? null;
        $picked = is_numeric($picked) ? (int) $picked : null;
        
        if ($picked === null || $picked < 0 || $picked > 3) {
            $unanswered++;
        } elseif ($picked === (int) $question['correct_opt_index']) {
            $correct++;
        } else {
            $wrong++;
        }
        
        $review[$qId] = [
            'a' => (int) $question['correct_opt_index'],
            'e' => (string) ($question['explanation'] ?? ''),
        ];
    }

    $score = $correct * (float) $test['mark'] - $wrong * (float) $test['negative'];
    $total = count($questionRows) * (float) $test['mark'];
    $timeTaken = max(0, min((int) $test['total_minutes'] * 60, (int) ($_POST['time_taken_sec'] ?? 0)));

    $studentName = d2d_student_value(['student_name', 'name', 'user_name'], 'Guest');
    $studentEmail = d2d_student_value(['student_email', 'email', 'user_email']);
    $studentPhone = d2d_student_value(['student_phone', 'phone', 'user_phone']);
    
    if ($studentName === 'Guest' && !empty($_POST['student_name'])) {
        $studentName = mysqli_real_escape_string($conn, $_POST['student_name']);
        $studentEmail = mysqli_real_escape_string($conn, $_POST['student_email'] ?? '');
        $studentPhone = mysqli_real_escape_string($conn, $_POST['student_phone'] ?? '');
    }

    $saved = false;
    $insert = $conn->prepare('INSERT INTO web_test_results (test_id, student_name, student_email, student_phone, score, correct_answers, wrong_answers, unanswered, time_taken_sec) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
    if ($insert) {
        $insert->bind_param('isssdiiii', $testId, $studentName, $studentEmail, $studentPhone, $score, $correct, $wrong, $unanswered, $timeTaken);
        $saved = $insert->execute();
        $insert->close();
    }

    d2d_json([
        'status' => 'success',
        'saved' => $saved,
        'result' => [
            'correct' => $correct,
            'wrong' => $wrong,
            'unanswered' => $unanswered,
            'score' => round($score, 2),
            'total' => $total,
            'percentage' => $total > 0 ? ($score / $total) * 100 : 0,
            'review' => $review,
        ],
    ]);
}

// ======================================================================
// SHOW ADMIN PANEL IF REQUESTED (AND LOGGED IN)
// ======================================================================
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true && isset($_GET['view'])) {
    // ---- ADMIN HTML STARTS HERE ----
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Engine Admin | Diploma Wallah</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
    <style>
        :root { --primary: #6F2056; --primary-dark: #4a153a; --bg-body: #f3f4f6; --bg-card: #ffffff; --text-main: #111827; --text-light: #6b7280; --border: #e5e7eb; --sidebar-w: 280px; --radius: 16px; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Outfit', sans-serif; background-color: var(--bg-body); color: var(--text-main); display: flex; min-height: 100vh; overflow-x: hidden; }

        .sidebar { width: var(--sidebar-w); background: var(--bg-card); border-right: 1px solid var(--border); position: fixed; height: 100vh; z-index: 1000; padding: 24px; display: flex; flex-direction: column; overflow-y: auto;}
        .brand { font-size: 1.5rem; font-weight: 800; color: var(--primary); margin-bottom: 40px; display: flex; align-items: center; gap: 12px; }
        .nav-label { font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: var(--text-light); margin-bottom: 12px; letter-spacing: 1px; }
        .nav-links { list-style: none; margin-bottom: 30px; }
        .nav-item { display: flex; align-items: center; gap: 14px; padding: 14px; border-radius: 12px; text-decoration: none; color: var(--text-light); font-weight: 500; transition: 0.2s; margin-bottom: 8px;}
        .nav-item:hover, .nav-item.active { background: #fdf2f8; color: var(--primary); font-weight: 600; }

        .overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 900; display: none; backdrop-filter: blur(4px); }

        .main-content { margin-left: var(--sidebar-w); flex: 1; padding: 30px; width: calc(100% - var(--sidebar-w)); }
        .top-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
        .user-welcome h2 { font-size: 1.6rem; font-weight: 700; color: var(--text-main); }
        .mobile-toggle { display: none; background: white; border: 1px solid var(--border); width: 45px; height: 45px; border-radius: 10px; font-size: 1.2rem; cursor: pointer; color: var(--primary); align-items: center; justify-content: center; }
        
        .card { background: var(--bg-card); border-radius: var(--radius); padding: 25px; box-shadow: 0 4px 20px rgba(0,0,0,0.03); border: 1px solid var(--border); margin-bottom: 30px; }
        .card-header { font-size: 1.1rem; font-weight: 700; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; color: var(--primary);}
        
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-size: 0.9rem; font-weight: 600; margin-bottom: 8px; color: #374151; }
        .styled-input { width: 100%; padding: 12px 16px; font-size: 0.95rem; border: 2px solid #e5e7eb; border-radius: 10px; background: #f9fafb; outline: none; transition: 0.2s; font-family: inherit;}
        .styled-input:focus { border-color: var(--primary); background: white; }

        .btn-primary { padding: 12px 20px; font-size: 0.95rem; font-weight: 700; background: var(--primary); color: white; border: none; border-radius: 10px; cursor: pointer; transition: 0.3s; display: inline-flex; align-items: center; gap: 8px; text-decoration: none;}
        .btn-primary:hover { background: var(--primary-dark); }
        .btn-secondary { padding: 8px 15px; font-size: 0.85rem; background: #e5e7eb; color: #374151; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 6px;}
        .btn-secondary:hover { background: #d1d5db; }
        
        .btn-action-main { display: block; width: 100%; padding: 10px; margin-bottom: 8px; font-weight: 700; text-align: center; border-radius: 8px; text-decoration: none; font-size: 0.9rem; transition: 0.2s;}
        .btn-action-questions { background: #fdf2f8; color: var(--primary); border: 1px solid #fbcfe8; }
        .btn-action-questions:hover { background: var(--primary); color: white; }
        
        .action-row-small { display: flex; gap: 8px; margin-bottom: 8px; }
        .btn-action-small { flex: 1; text-align: center; padding: 6px; font-size: 0.8rem; font-weight: 600; border-radius: 6px; text-decoration: none; background: #f3f4f6; color: #4b5563; transition: 0.2s; border:none; cursor:pointer;}
        .btn-action-small:hover { background: #e5e7eb; }
        .btn-action-delete { background: #fef2f2; color: #ef4444; }
        .btn-action-delete:hover { background: #fee2e2; }

        .table-container { overflow-x: auto; background: white; border-radius: 12px; border: 1px solid var(--border); }
        table { width: 100%; border-collapse: collapse; min-width: 800px; }
        th { background: #f9fafb; padding: 16px; text-align: left; font-size: 0.8rem; font-weight: 700; text-transform: uppercase; color: var(--text-light); }
        td { padding: 16px; border-top: 1px solid var(--border); font-size: 0.9rem; font-weight: 500; vertical-align: middle; }
        .badge { padding: 5px 10px; border-radius: 30px; font-size: 0.7rem; font-weight: 700; display: inline-block; }
        .badge-green { background: #dcfce7; color: #166534; }
        .badge-red { background: #fee2e2; color: #991b1b; }
        .badge-gray { background: #f3f4f6; color: #4b5563; }

        .search-box { display: flex; gap: 10px; margin-bottom: 20px; }
        .search-box input { flex: 1; max-width: 400px; }

        .q-box { border: 1px solid var(--border); padding: 15px; border-radius: 10px; margin-bottom: 15px; background: #fdfdfd; position: relative; }
        .q-box .del-btn { position: absolute; top: 15px; right: 15px; color: #ef4444; font-size: 1.2rem; cursor: pointer; }
        .q-opts { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 10px; font-size: 0.85rem; color: #4b5563;}
        .q-ans { margin-top: 10px; font-weight: bold; color: #16a34a; font-size: 0.85rem;}

        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.active { transform: translateX(0); }
            .main-content { margin-left: 0; width: 100%; padding: 20px; }
            .mobile-toggle { display: flex; }
            .form-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

    <div class="overlay" id="overlay" onclick="toggleSidebar()"></div>

    <!-- SIDEBAR -->
    <aside class="sidebar" id="sidebar">
        <div class="brand"><i class="fa-solid fa-graduation-cap"></i> DW_JUT</div>
        
        <div class="nav-label">Academics</div>
        <ul class="nav-links">
            <li><a href="dashboard.php" class="nav-item"><i class="fa-solid fa-cloud-arrow-up"></i> Academic Uploads</a></li>
        </ul>

        <div class="nav-label">Updates & Placement</div>
        <ul class="nav-links">
            <li><a href="placement_create.php" class="nav-item"><i class="fa-solid fa-pen-to-square"></i> Create Post</a></li>
        </ul>

        <div class="nav-label">Test Engine</div>
        <ul class="nav-links">
            <li><a href="index.php?view=list" class="nav-item <?php echo ($view == 'list') ? 'active' : ''; ?>"><i class="fa-solid fa-list-check"></i> Manage Tests</a></li>
            <li><a href="index.php?view=add_test" class="nav-item <?php echo ($view == 'add_test') ? 'active' : ''; ?>"><i class="fa-solid fa-plus-circle"></i> Create New Test</a></li>
        </ul>
        
        <div style="margin-top:auto;">
            <a href="logout.php" class="nav-item" style="color:#ef4444; background: #fef2f2;"><i class="fa-solid fa-power-off"></i> Logout</a>
        </div>
    </aside>

    <!-- MAIN CONTENT -->
    <main class="main-content">
        <div class="top-bar">
            <button class="mobile-toggle" onclick="toggleSidebar()"><i class="fa-solid fa-bars"></i></button>
            <div class="user-welcome"><h2>Test Engine <span>Dashboard</span> 🎯</h2></div>
        </div>

        <?php if(isset($_SESSION['msg'])): ?>
            <div style="background:#dcfce7; color:#166534; padding:15px; border-radius:10px; margin-bottom:20px; font-weight:600;">
                <?php echo $_SESSION['msg']; unset($_SESSION['msg']); ?>
            </div>
        <?php endif; ?>
        <?php if(isset($_SESSION['error'])): ?>
            <div style="background:#fee2e2; color:#991b1b; padding:15px; border-radius:10px; margin-bottom:20px; font-weight:600;">
                <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>

        <!-- ============================================== -->
        <!-- VIEW: LIST TESTS                               -->
        <!-- ============================================== -->
        <?php if ($view == 'list'): ?>
            <div class="card">
                <div class="card-header">
                    <div><i class="fa-solid fa-layer-group"></i> All Test Series</div>
                    <a href="index.php?view=add_test" class="btn-primary"><i class="fa-solid fa-plus"></i> New Test</a>
                </div>

                <!-- Search/Filter -->
                <form method="GET" class="search-box">
                    <input type="hidden" name="view" value="list">
                    <input type="text" name="search" class="styled-input" placeholder="Search by Test Title or Tags..." value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                    <button type="submit" class="btn-primary"><i class="fa-solid fa-magnifying-glass"></i></button>
                </form>

                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Test Title & Tags</th>
                                <th>URL Slug</th>
                                <th>Timing / Marks</th>
                                <th style="text-align:center;">Questions Added</th>
                                <th>Status</th>
                                <th width="220px">Actions (Upload/Edit)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
                            $sql = "SELECT s.*, (SELECT COUNT(*) FROM web_test_questions q WHERE q.test_id = s.id) as q_count FROM web_test_series s ";
                            if ($search) {
                                $sql .= " WHERE title LIKE '%$search%' OR tags LIKE '%$search%' ";
                            }
                            $sql .= " ORDER BY id DESC";
                            $result = $conn->query($sql);
                            
                            if ($result->num_rows > 0): 
                                while($row = $result->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <div style="font-weight:700; color:#1f2937;"><?php echo htmlspecialchars($row['title']); ?></div>
                                        <div style="font-size:0.75rem; color:#6b7280;"><?php echo htmlspecialchars($row['subtitle']); ?></div>
                                        <div style="margin-top:5px;">
                                            <?php foreach(explode(',', $row['tags']) as $tag): ?>
                                                <span class="badge badge-gray" style="font-size:0.6rem;"><?php echo trim($tag); ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div style="font-size:0.85rem; color:#0284c7;">/<?php echo htmlspecialchars($row['slug']); ?></div>
                                    </td>
                                    <td>
                                        <div style="font-size:0.85rem;"><i class="fa-regular fa-clock"></i> <?php echo $row['total_minutes']; ?> Mins (<?php echo $row['per_question_sec']; ?>s/Q)</div>
                                        <div style="font-size:0.85rem; color:#16a34a;"><i class="fa-solid fa-check"></i> +<?php echo $row['mark']; ?> <span style="color:#ef4444;"><i class="fa-solid fa-xmark"></i> -<?php echo $row['negative']; ?></span></div>
                                    </td>
                                    <td style="text-align:center;">
                                        <span class="badge" style="background:#eef2ff; color:#4338ca; font-size:1rem; padding:8px 12px;"><?php echo $row['q_count']; ?></span>
                                    </td>
                                    <td>
                                        <?php if($row['is_active']): ?>
                                            <span class="badge badge-green">Live</span>
                                        <?php else: ?>
                                            <span class="badge badge-red">Draft</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="index.php?view=questions&test_id=<?php echo $row['id']; ?>" class="btn-action-main btn-action-questions">
                                            <i class="fa-solid fa-plus-circle"></i> Add / View Questions
                                        </a>
                                        <div class="action-row-small">
                                            <a href="https://d2d.diplomawallah.in/<?php echo htmlspecialchars($row['slug']); ?>" target="_blank" class="btn-action-small" style="background:#dcfce7; color:#166534;" title="View Live Test">
                                                <i class="fa-solid fa-eye"></i> View
                                            </a>
                                            <a href="index.php?view=results&test_id=<?php echo $row['id']; ?>" class="btn-action-small" style="color:#0284c7; background:#e0f2fe;" title="View Reports">
                                                <i class="fa-solid fa-chart-pie"></i> Reports
                                            </a>
                                        </div>
                                        <div class="action-row-small">
                                            <a href="index.php?view=edit_test&id=<?php echo $row['id']; ?>" class="btn-action-small" title="Edit Metadata">
                                                <i class="fa-solid fa-pen"></i> Edit
                                            </a>
                                            <a href="index.php?delete_test=<?php echo $row['id']; ?>" onclick="return confirm('Are you sure? This deletes the test and ALL its questions & results!');" class="btn-action-small btn-action-delete" title="Delete Test">
                                                <i class="fa-solid fa-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; else: ?>
                                <tr><td colspan="6" style="text-align:center; padding:20px;">No tests found. Create one!</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>


        <!-- ============================================== -->
        <!-- VIEW: ADD / EDIT TEST                          -->
        <!-- ============================================== -->
        <?php 
        if ($view == 'add_test' || $view == 'edit_test'): 
            $editData = null;
            if ($view == 'edit_test' && isset($_GET['id'])) {
                $id = (int)$_GET['id'];
                $res = $conn->query("SELECT * FROM web_test_series WHERE id=$id");
                $editData = $res->fetch_assoc();
            }
        ?>
            <div class="card">
                <div class="card-header">
                    <div><i class="fa-solid <?php echo $editData ? 'fa-pen' : 'fa-plus'; ?>"></i> <?php echo $editData ? 'Edit Test Series' : 'Step 1: Create New Test'; ?></div>
                    <a href="index.php?view=list" class="btn-secondary"><i class="fa-solid fa-arrow-left"></i> Back to List</a>
                </div>
                
                <form action="" method="POST">
                    <?php if($editData): ?>
                        <input type="hidden" name="test_id" value="<?php echo $editData['id']; ?>">
                    <?php endif; ?>

                    <div class="form-grid">
                        <div class="form-group">
                            <label>Test Title *</label>
                            <input type="text" name="title" class="styled-input" required placeholder="e.g. Atomic Structure Chapter Test" value="<?php echo $editData['title'] ?? ''; ?>">
                        </div>
                        <div class="form-group">
                            <label>Custom URL Slug (Optional)</label>
                            <input type="text" name="slug" class="styled-input" placeholder="e.g. atomic-structure" value="<?php echo $editData['slug'] ?? ''; ?>">
                            <small style="color:#6b7280;">Leave empty to auto-generate from title.</small>
                        </div>
                        
                        <div class="form-group">
                            <label>Subtitle</label>
                            <input type="text" name="subtitle" class="styled-input" placeholder="e.g. Chapter-wise Practice Test" value="<?php echo $editData['subtitle'] ?? 'Chapter-wise Practice Test'; ?>">
                        </div>
                        <div class="form-group">
                            <label>Tags (Comma separated)</label>
                            <input type="text" name="tags" class="styled-input" placeholder="e.g. Physics, D2D, Diploma" value="<?php echo $editData['tags'] ?? ''; ?>">
                        </div>

                        <!-- Timing & Marks -->
                        <div class="form-group">
                            <label>Total Time (in Minutes) *</label>
                            <input type="number" name="total_minutes" class="styled-input" required value="<?php echo $editData['total_minutes'] ?? '20'; ?>">
                        </div>
                        <div class="form-group">
                            <label>Time Per Question (Seconds) *</label>
                            <input type="number" name="per_question_sec" class="styled-input" required value="<?php echo $editData['per_question_sec'] ?? '60'; ?>" placeholder="0 for no limit">
                        </div>
                        <div class="form-group">
                            <label>Marks per Correct Answer *</label>
                            <input type="number" step="0.1" name="mark" class="styled-input" required value="<?php echo $editData['mark'] ?? '1.0'; ?>">
                        </div>
                        <div class="form-group">
                            <label>Negative Marks (Penalty) *</label>
                            <input type="number" step="0.05" name="negative" class="styled-input" required value="<?php echo $editData['negative'] ?? '0.25'; ?>" placeholder="0 for no penalty">
                        </div>
                        <div class="form-group" style="grid-column: span 2;">
                            <label>Status *</label>
                            <select name="is_active" class="styled-input">
                                <option value="1" <?php echo (isset($editData['is_active']) && $editData['is_active']==1) ? 'selected' : ''; ?>>Live (Published)</option>
                                <option value="0" <?php echo (isset($editData['is_active']) && $editData['is_active']==0) ? 'selected' : ''; ?>>Draft (Hidden)</option>
                            </select>
                        </div>
                    </div>
                    <hr style="border:0; border-top:1px solid #e5e7eb; margin:20px 0;">
                    <button type="submit" name="save_test" class="btn-primary" style="width:100%; justify-content:center;"><i class="fa-solid fa-floppy-disk"></i> <?php echo $editData ? 'SAVE TEST META DATA' : 'SAVE TEST & GO TO QUESTIONS UPLOAD'; ?></button>
                </form>
            </div>
        <?php endif; ?>


        <!-- ============================================== -->
        <!-- VIEW: MANAGE QUESTIONS                         -->
        <!-- ============================================== -->
        <?php 
        if ($view == 'questions' && isset($_GET['test_id'])): 
            $t_id = (int)$_GET['test_id'];
            $t_meta = $conn->query("SELECT title FROM web_test_series WHERE id = $t_id")->fetch_assoc();
            
            // Fetch Existing Questions for JSON Box
            $existing_qs_json = '';
            $qs_raw = $conn->query("SELECT * FROM web_test_questions WHERE test_id = $t_id ORDER BY id ASC");
            if ($qs_raw && $qs_raw->num_rows > 0) {
                $arr = [];
                while($row = $qs_raw->fetch_assoc()) {
                    $arr[] = [
                        'q' => $row['question_text'],
                        'o' => [$row['opt_0'], $row['opt_1'], $row['opt_2'], $row['opt_3']],
                        'a' => (int)$row['correct_opt_index'],
                        'e' => $row['explanation']
                    ];
                }
                $existing_qs_json = json_encode($arr, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            }
        ?>
            <div class="card" style="background:#fdf2f8; border-color:var(--primary);">
                <div class="card-header" style="border-bottom:none; padding-bottom:0;">
                    <div><i class="fa-solid fa-database"></i> Step 2: Upload Questions for <span style="color:#111827;">"<?php echo $t_meta['title']; ?>"</span></div>
                    <a href="index.php?view=list" class="btn-secondary"><i class="fa-solid fa-arrow-left"></i> Back to List</a>
                </div>
            </div>

            <div class="form-grid">
                <!-- Bulk JSON Upload (EDIT ALL) -->
                <div class="card" style="border: 2px dashed var(--primary);">
                    <div class="card-header">
                        <div><i class="fa-solid fa-code"></i> JSON Bulk Editor</div>
                        <button type="button" onclick="loadExample()" class="btn-secondary btn-action-small" style="font-size:0.7rem;"><i class="fa-solid fa-clipboard"></i> Format</button>
                    </div>
                    <form method="POST">
                        <input type="hidden" name="test_id" value="<?php echo $t_id; ?>">
                        <p style="font-size:0.8rem; color:#ef4444; font-weight:600; margin-bottom:10px;">
                            ⚠️ Warning: Saving this box will REPLACE all existing questions.
                        </p>
                        <textarea id="jsonBox" name="json_data" class="styled-input" rows="18" placeholder="Empty..." style="font-family:monospace; font-size:0.85rem; background:#1f2937; color:#10b981; line-height:1.4;"><?php echo htmlspecialchars($existing_qs_json); ?></textarea>
                        <button type="submit" name="upload_json_qs" class="btn-primary" style="margin-top:15px; width:100%; justify-content:center;"><i class="fa-solid fa-rotate"></i> UPDATE & REPLACE ALL</button>
                    </form>
                    <script>
                        function loadExample() {
                            const ex = '[\n  {\n    "q": "What is the atomic number of carbon?",\n    "o": ["4", "6", "8", "12"],\n    "a": 1,\n    "e": "Carbon has 6 protons."\n  }\n]';
                            if(document.getElementById('jsonBox').value.trim() === '' || confirm("This will overwrite the current box with an example. Continue?")) {
                                document.getElementById('jsonBox').value = ex;
                            }
                        }
                    </script>
                </div>

                <!-- Manual Single Question Add -->
                <div class="card">
                    <div class="card-header"><i class="fa-solid fa-pen-nib"></i> Add Single Question</div>
                    <form method="POST">
                        <input type="hidden" name="test_id" value="<?php echo $t_id; ?>">
                        <p style="font-size:0.8rem; color:#6b7280; margin-bottom:10px;">
                            This will append one question to the existing list.
                        </p>
                        <div class="form-group">
                            <label>Question Text (Use $ for math)</label>
                            <textarea name="q_text" class="styled-input" rows="3" required></textarea>
                        </div>
                        <div class="form-grid" style="gap:10px;">
                            <div class="form-group" style="margin-bottom:0;"><input type="text" name="o0" class="styled-input" placeholder="Opt A" required></div>
                            <div class="form-group" style="margin-bottom:0;"><input type="text" name="o1" class="styled-input" placeholder="Opt B" required></div>
                            <div class="form-group" style="margin-bottom:0;"><input type="text" name="o2" class="styled-input" placeholder="Opt C" required></div>
                            <div class="form-group" style="margin-bottom:0;"><input type="text" name="o3" class="styled-input" placeholder="Opt D" required></div>
                        </div>
                        <div class="form-group" style="margin-top:10px;">
                            <label>Correct Answer Index (0=A, 1=B, 2=C, 3=D)</label>
                            <select name="ans" class="styled-input" required>
                                <option value="0">Option A (0)</option>
                                <option value="1">Option B (1)</option>
                                <option value="2">Option C (2)</option>
                                <option value="3">Option D (3)</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Explanation (Optional)</label>
                            <input type="text" name="exp" class="styled-input" placeholder="Explanation for review...">
                        </div>
                        <button type="submit" name="add_single_q" class="btn-primary" style="width:100%; justify-content:center;">ADD QUESTION</button>
                    </form>
                </div>
            </div>

            <!-- List Existing Questions -->
            <div class="card">
                <div class="card-header"><i class="fa-solid fa-list"></i> Saved Questions Overview</div>
                <?php 
                $qs = $conn->query("SELECT * FROM web_test_questions WHERE test_id = $t_id ORDER BY id ASC");
                $i = 1;
                if ($qs->num_rows > 0):
                    while($q = $qs->fetch_assoc()):
                ?>
                    <div class="q-box">
                        <a href="index.php?delete_q=<?php echo $q['id']; ?>&test_id=<?php echo $t_id; ?>" onclick="return confirm('Delete this question?')" class="del-btn"><i class="fa-solid fa-trash-can"></i></a>
                        <strong>Q<?php echo $i++; ?>.</strong> <?php echo htmlspecialchars($q['question_text']); ?>
                        <div class="q-opts">
                            <div><strong>A)</strong> <?php echo htmlspecialchars($q['opt_0']); ?></div>
                            <div><strong>B)</strong> <?php echo htmlspecialchars($q['opt_1']); ?></div>
                            <div><strong>C)</strong> <?php echo htmlspecialchars($q['opt_2']); ?></div>
                            <div><strong>D)</strong> <?php echo htmlspecialchars($q['opt_3']); ?></div>
                        </div>
                        <div class="q-ans">Correct Answer: <?php $letters=['A','B','C','D']; echo $letters[$q['correct_opt_index']]; ?></div>
                        <?php if(!empty($q['explanation'])): ?>
                            <div style="margin-top:5px; font-size:0.8rem; color:#6b7280;"><em>Exp: <?php echo htmlspecialchars($q['explanation']); ?></em></div>
                        <?php endif; ?>
                    </div>
                <?php endwhile; else: ?>
                    <p style="color:#6b7280;">No questions added yet. Use the JSON bulk import or manual form above.</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- ============================================== -->
        <!-- VIEW: RESULTS REPORT                           -->
        <!-- ============================================== -->
        <?php 
        if ($view == 'results' && isset($_GET['test_id'])): 
            $t_id = (int)$_GET['test_id'];
            $t_meta = $conn->query("SELECT title FROM web_test_series WHERE id = $t_id")->fetch_assoc();
        ?>
            <div class="card">
                <div class="card-header">
                    <div><i class="fa-solid fa-chart-bar"></i> Results & Leaderboard: <span style="color:var(--text-main);"><?php echo $t_meta['title']; ?></span></div>
                    <a href="index.php?view=list" class="btn-secondary"><i class="fa-solid fa-arrow-left"></i> Back</a>
                </div>
                
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Student Details</th>
                                <th>Score</th>
                                <th>Accuracy (C / W / U)</th>
                                <th>Time Taken</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $res = $conn->query("SELECT * FROM web_test_results WHERE test_id = $t_id ORDER BY score DESC, time_taken_sec ASC");
                            if ($res && $res->num_rows > 0):
                                $rank = 1;
                                while($r = $res->fetch_assoc()):
                            ?>
                                <tr>
                                    <td>
                                        <div style="font-weight:700;">#<?php echo $rank++; ?> <?php echo htmlspecialchars($r['student_name']); ?></div>
                                        <div style="font-size:0.75rem; color:#6b7280;"><?php echo htmlspecialchars($r['student_email'] ?? 'No Email'); ?> | <?php echo htmlspecialchars($r['student_phone'] ?? ''); ?></div>
                                    </td>
                                    <td><span class="badge badge-green" style="font-size:1rem;"><?php echo $r['score']; ?></span></td>
                                    <td style="font-size:0.85rem;">
                                        <span style="color:#16a34a; font-weight:bold;"><?php echo $r['correct_answers']; ?></span> / 
                                        <span style="color:#ef4444; font-weight:bold;"><?php echo $r['wrong_answers']; ?></span> / 
                                        <span style="color:#6b7280; font-weight:bold;"><?php echo $r['unanswered']; ?></span>
                                    </td>
                                    <td><?php echo floor($r['time_taken_sec'] / 60); ?>m <?php echo $r['time_taken_sec'] % 60; ?>s</td>
                                    <td style="font-size:0.8rem; color:#6b7280;"><?php echo date('d M Y, h:i A', strtotime($r['submitted_at'])); ?></td>
                                </tr>
                            <?php endwhile; else: ?>
                                <tr><td colspan="5" style="text-align:center; padding:20px;">No attempts yet for this test.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

    </main>

    <script>
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('active');
            document.getElementById('overlay').style.display = document.getElementById('sidebar').classList.contains('active') ? 'block' : 'none';
        }
    </script>
</body>
</html>
<?php 
    // ADMIN ENDS HERE
    exit(); 
} 
?>

<?php
// ======================================================================
// 3. URL SLUG LOGIC & FETCH TEST DATA (STUDENT FRONTEND)
// ======================================================================
$slug = trim((string) ($_GET['slug'] ?? ''));
if (empty($slug) && isset($_GET['id'])) {
    $slug = trim((string)$_GET['id']);
}

$testData = null;
$activeTests = $conn->query('SELECT * FROM web_test_series WHERE is_active = 1');
if ($activeTests) {
    while ($row = $activeTests->fetch_assoc()) {
        if ($row['slug'] === $slug || (string)$row['id'] === $slug || d2d_slug($row['title']) === strtolower($slug)) {
            $testData = $row;
            break;
        }
    }
}

if (!$testData) {
    http_response_code(404);
    exit("<div style='font-family:sans-serif;text-align:center;margin-top:50px;color:#8e1b2a'><h2>404 - Test Not Found</h2><p>The test link is invalid or the test has been hidden by admin.</p></div>");
}

$dbTestId = (int) $testData['id'];
$questionStmt = $conn->prepare('SELECT id, question_text, opt_0, opt_1, opt_2, opt_3, correct_opt_index, explanation FROM web_test_questions WHERE test_id = ? ORDER BY id ASC');
$questionStmt->bind_param('i', $dbTestId);
$questionStmt->execute();
$questionResult = $questionStmt->get_result();
$questions = [];
while ($question = $questionResult->fetch_assoc()) {
    $questions[] = [
        'id' => (int) $question['id'],
        'q' => (string) $question['question_text'],
        'o' => [(string) $question['opt_0'], (string) $question['opt_1'], (string) $question['opt_2'], (string) $question['opt_3']],
        'a' => (int) $question['correct_opt_index'],
        'e' => (string) ($question['explanation'] ?? '')
    ];
}
$questionStmt->close();

if (!$questions) {
    exit("<div style='font-family:sans-serif;text-align:center;margin-top:50px;color:#8e1b2a'><h2>Questions Not Uploaded</h2><p>Admin has not added any questions to this test yet.</p></div>");
}

// 🔥 RANDOMIZE QUESTIONS 🔥
shuffle($questions);

$TEST = [
    'test_id' => $dbTestId,
    'title' => (string) $testData['title'],
    'subtitle' => (string) $testData['subtitle'],
    'tags' => array_values(array_filter(array_map('trim', explode(',', (string) $testData['tags'])))),
    'total_minutes' => (int) $testData['total_minutes'],
    'per_question_sec' => (int) $testData['per_question_sec'],
    'mark' => (float) $testData['mark'],
    'negative' => (float) $testData['negative'],
    'home_url' => (string) (($testData['home_url'] ?? '') ?: 'https://diplomawallah.in/'),
    'questions' => $questions,
];
$QN = count($TEST['questions']);
$CLIENT = [
    'testId' => $TEST['test_id'],
    'title' => $TEST['title'],
    'totalSec' => (int) $TEST['total_minutes'] * 60,
    'perQSec' => (int) $TEST['per_question_sec'],
    'mark' => (float) $TEST['mark'],
    'negative' => (float) $TEST['negative'],
    'count' => $QN,
    'csrf' => $_SESSION['d2d_test_csrf'],
    /* sirf sawaal aur options bhejo — correct answer submit ke baad server se aata hai */
    'questions' => array_map(function ($q) {
        return ['id' => $q['id'], 'q' => $q['q'], 'o' => $q['o']];
    }, $TEST['questions']),
];
$totalMarks = $QN * $TEST['mark'];
$num = function ($v) { return rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.'); };
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#8e1b2a">
<title><?= htmlspecialchars($TEST['title'], ENT_QUOTES) ?> - <?= htmlspecialchars($TEST['subtitle'], ENT_QUOTES) ?> | Diploma Wallah</title>
<link rel="icon" href="diplomawallah-logo.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Oswald:wght@400;500;700&family=Poppins:wght@400;500;600;700&family=Tinos:wght@400;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
  :root {
    --purple: #8e1b2a;          
    --purple-2: #6f1220;        
    --crimson: #8e1b2a;         
    --grad: linear-gradient(180deg, #9a1f2f 0%, #8e1b2a 55%, #7d1524 100%);
    --ink: #0f172a;
    --muted: #64748b;
    --line: #e2e8f0;
    --line-2: #cbd5e1;
    --bg: #f1f5f9;
    --card: #ffffff;
    --tint: #fbecef;            
    --green: #16a34a;
    --green-bg: #e9f9ef;
    --red: #e11d48;
    --ease: cubic-bezier(.4, 0, .2, 1);
    --shadow: 0 4px 16px rgba(15, 23, 42, .06);
  }

  * { margin: 0; padding: 0; box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
  html { -webkit-text-size-adjust: 100%; }
  body {
    font-family: 'Poppins', system-ui, sans-serif;
    background: var(--bg); color: var(--ink); line-height: 1.55; min-height: 100dvh;
  }
  body::before {
    content: ""; position: fixed; inset: 0; z-index: -1; pointer-events: none;
    background:
      radial-gradient(60vw 40vw at 85% -5%, rgba(142, 27, 42, .07), transparent 65%),
      radial-gradient(55vw 40vw at -10% 8%, rgba(142, 27, 42, .06), transparent 65%);
  }
  body.mode-test { overflow: hidden; height: 100dvh; }
  .wrap { width: 100%; max-width: 520px; margin: 0 auto; padding: 0 16px 34px; }
  button { font-family: inherit; cursor: pointer; border: none; background: none; color: inherit; }
  a { color: inherit; text-decoration: none; }
  .screen { display: none; }
  .screen.show { display: block; animation: fade .28s var(--ease); }
  @keyframes fade { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: none; } }

  .brand-logo { width: 100%; height: 100%; object-fit: contain; display: block; }
  .logo-fallback { width: 100%; height: 100%; border-radius: 50%; background: var(--grad); display: grid; place-items: center; color: #fff; font-size: .9em; }
  .btn-primary {
    display: flex; align-items: center; justify-content: center; gap: 10px; width: 100%;
    background: var(--grad); color: #fff; font-weight: 700; font-size: 1.02rem;
    padding: 16px 20px; border-radius: 14px; letter-spacing: .4px;
    box-shadow: 0 8px 20px rgba(142, 27, 42, .28); transition: transform .15s var(--ease);
  }
  .btn-primary:active { transform: scale(.985); }
  .btn-ghost {
    display: flex; align-items: center; justify-content: center; gap: 8px; width: 100%;
    background: #fff; color: var(--ink); font-weight: 600; font-size: .92rem;
    padding: 13px 16px; border-radius: 12px; border: 1px solid var(--line-2); text-decoration: none;
  }
  .btn-ghost:active { background: #f8fafc; }
  .powered { text-align: center; color: var(--muted); font-size: .76rem; margin-top: 18px; display: flex; align-items: center; gap: 10px; justify-content: center; }
  .powered::before, .powered::after { content: ""; height: 1px; width: 26px; background: var(--line-2); }

  /* ============ START ============ */
  .start-head { text-align: center; padding: 26px 0 6px; }
  .start-logo { width: 122px; height: 122px; margin: 0 auto 10px; }
  .start-name { font-family: 'Oswald', sans-serif; font-weight: 700; letter-spacing: 3px; font-size: 1rem; color: var(--purple); text-transform: uppercase; }
  .start-rule { width: 54px; height: 3px; border-radius: 3px; background: var(--grad); margin: 8px auto 16px; }
  .start-title { font-family: 'Oswald', sans-serif; font-size: 2.6rem; font-weight: 700; letter-spacing: 1px; line-height: 1.05; text-transform: uppercase; }
  .start-title span { color: var(--crimson); }

  .chip { background: rgba(255, 255, 255, .18); border: 1px solid rgba(255, 255, 255, .3); color: #fff; font-size: .7rem; font-weight: 600; padding: 3px 11px; border-radius: 99px; }
  .chip-row { display: flex; gap: 7px; flex-wrap: wrap; margin-top: 7px; }
  .chapter-card { background: var(--grad); color: #fff; border-radius: 16px; padding: 16px 18px; margin-top: 16px; display: flex; align-items: center; gap: 14px; box-shadow: 0 10px 24px rgba(110, 18, 32, .26); }
  .chapter-card .ic { width: 42px; height: 42px; flex: none; opacity: .92; }
  .chapter-card .tx { min-width: 0; flex: 1; }
  .chapter-card h2 { font-family: 'Oswald', sans-serif; font-size: 1.3rem; font-weight: 700; letter-spacing: .4px; line-height: 1.2; text-transform: uppercase; }
  .sub-line { display: flex; align-items: center; gap: 10px; justify-content: center; color: var(--muted); font-size: .85rem; margin: 14px 0 16px; }
  .sub-line::before, .sub-line::after { content: ""; height: 1px; width: 24px; background: var(--line-2); }

  .stat-card { background: var(--card); border: 1px solid var(--line); border-radius: 16px; box-shadow: var(--shadow); overflow: hidden; }
  .stat-row { display: grid; grid-template-columns: 1fr 1fr; }
  .stat-row + .stat-row { border-top: 1px solid var(--line); }
  .stat { display: flex; align-items: center; gap: 11px; padding: 13px 14px; min-width: 0; }
  .stat + .stat { border-left: 1px solid var(--line); }
  .stat.full { grid-column: 1 / -1; }
  .stat .si { width: 38px; height: 38px; flex: none; border-radius: 11px; display: grid; place-items: center; font-size: .95rem; background: var(--tint); color: var(--purple); }
  .stat.rose .si { background: var(--tint); color: var(--crimson); }
  .stat .sl { font-size: .74rem; color: var(--muted); line-height: 1.3; }
  .stat .sv { font-size: 1.05rem; font-weight: 700; color: var(--purple); line-height: 1.25; }
  .stat.rose .sv { color: var(--crimson); }

  .rules { margin-top: 16px; border-radius: 16px; overflow: hidden; box-shadow: var(--shadow); border: 1px solid var(--line); }
  .rules-head { background: var(--grad); color: #fff; padding: 13px 16px; font-family: 'Oswald', sans-serif; font-weight: 500; text-transform: uppercase; font-weight: 700; letter-spacing: 1px; display: flex; align-items: center; gap: 10px; font-size: .95rem; }
  .rules ol { list-style: none; background: var(--card); padding: 6px 14px 12px; }
  .rules li { display: flex; gap: 12px; align-items: flex-start; padding: 9px 0; font-size: .87rem; color: #40474f; }
  .rules li + li { border-top: 1px solid var(--line); }
  .rules li .n { width: 24px; height: 24px; flex: none; border-radius: 50%; background: var(--tint); color: var(--purple); font-size: .74rem; font-weight: 700; display: grid; place-items: center; margin-top: 1px; }
  .rules li b { color: var(--crimson); font-weight: 700; }
  .resume-note { margin-top: 14px; background: #fff8ea; border: 1px solid #f6e2bb; color: #8a5a00; border-radius: 12px; padding: 11px 14px; font-size: .82rem; display: flex; gap: 10px; align-items: flex-start; }

  /* Input fields for user details */
  .student-input {
      width: 100%; padding: 10px 14px; margin-bottom: 12px; border: 1px solid var(--line-2);
      border-radius: 8px; font-size: 0.95rem; font-family: inherit; outline: none; transition: border-color 0.2s;
  }
  .student-input:focus { border-color: var(--purple); }

  /* ============ TEST (fixed shell — only the question area moves) ============ */
  /* 🔥 FIX FOR HEIGHT SPACING: align-items stretch forces qcard to fill height, leaving bot fixed at bottom 🔥 */
  .test-shell { height: 100dvh; display: flex; flex-direction: column; overflow: hidden; }
  .topbar { flex: none; background: var(--grad); color: #fff; box-shadow: 0 2px 14px rgba(110, 18, 32, .22); }
  .topbar-in { max-width: 520px; margin: 0 auto; padding: 10px 14px; display: flex; align-items: center; gap: 12px; }
  .icon-btn { width: 38px; height: 38px; flex: none; border-radius: 11px; display: grid; place-items: center; color: #fff; font-size: 1.05rem; background: rgba(255,255,255,.12); }
  .icon-btn:active { background: rgba(255,255,255,.22); }
  .topbar .bl { width: 36px; height: 36px; flex: none; border-radius: 50%; background: #fff; padding: 2px; overflow: hidden; }
  .topbar .tt { flex: 1; min-width: 0; }
  .topbar .tt b { display: block; font-family: 'Oswald', sans-serif; font-size: 1rem; font-weight: 700; letter-spacing: 1.2px; line-height: 1.2; text-transform: uppercase; }
  .topbar .tt small { display: block; font-size: .6rem; letter-spacing: 1.6px; opacity: .82; text-transform: uppercase; }

  .test-main { flex: 1 1 auto; min-height: 0; display: flex; justify-content: center; padding: 12px 16px 14px; align-items: stretch;}
  .qcard { width: 100%; max-width: 520px; display: flex; flex-direction: column; max-height: 100%;
           background: var(--card); border: 1px solid var(--line); border-radius: 16px; box-shadow: var(--shadow); overflow: hidden; }
  .qfix { flex: none; padding: 13px 14px 0; }
  .qmid { flex: 1 1 auto; min-height: 0; overflow-y: auto; overscroll-behavior: contain; padding: 0 14px; }
  .qmid-in { width: 100%; padding: 12px 0 14px; font-size: 1rem; }   
  .qbot { flex: none; padding: 10px 14px 13px; border-top: 1px solid var(--line); background: #f8fafc; }

  .qtop { display: flex; align-items: center; gap: 9px; }
  .qnum { background: var(--grad); color: #fff; font-family: 'Oswald', sans-serif; font-weight: 500; font-size: .86rem; padding: 6px 12px; border-radius: 9px; flex: none; letter-spacing: 1px; }
  .qtop .ch { flex: 1; min-width: 0; font-weight: 600; font-size: .9rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
  .qtop .cnt { font-weight: 700; color: var(--purple); font-size: .9rem; flex: none; font-variant-numeric: tabular-nums; }
  .qtop .cnt span { color: var(--muted); font-weight: 500; }
  .bmk { width: 34px; height: 34px; flex: none; border-radius: 9px; display: grid; place-items: center; color: var(--muted); background: #f1f5f9; font-size: .9rem; }
  .bmk.on { color: var(--crimson); background: var(--tint); }

  .timers { margin-top: 9px; }
  .timer.q { position: relative; display: flex; align-items: center; gap: 9px; padding: 7px 11px 10px; border-radius: 10px; overflow: hidden; }
  .tq-ic { flex: none; color: var(--crimson); font-size: .88rem; }
  .tq-l { flex: 1; min-width: 0; font-size: .72rem; color: var(--muted); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
  .timer.q .tv { flex: none; font-size: 1.02rem; }
  .tq-bar { position: absolute; left: 0; right: 0; bottom: 0; height: 3px; background: #f3dde1; }
  .tq-bar i { display: block; height: 100%; width: 100%; background: var(--grad); transition: width 1s linear; }
  .timer.q.warn .tq-bar i { background: #e11d48; }
  .nav-time { flex: none; display: flex; align-items: center; gap: 7px; background: rgba(255,255,255,.16); border: 1px solid rgba(255,255,255,.2); padding: 6px 12px; border-radius: 99px; font-weight: 700; font-size: .92rem; font-variant-numeric: tabular-nums; letter-spacing: .5px; }
  .nav-time i { font-size: .82rem; opacity: .9; }
  .nav-time.warn { background: #fff; color: #8e1b2a; border-color: #fff; animation: pulse 1s infinite; }
  .timer { display: flex; align-items: center; gap: 10px; border-radius: 13px; padding: 9px 11px; border: 1px solid var(--line); background: #f8fafc; }
  .timer .ti { width: 32px; height: 32px; flex: none; border-radius: 50%; display: grid; place-items: center; font-size: .9rem; background: var(--tint); color: var(--purple); }
  .timer .tl { font-size: .68rem; color: var(--muted); line-height: 1.2; }
  .timer .tv { font-size: 1.1rem; font-weight: 700; color: var(--purple); font-variant-numeric: tabular-nums; letter-spacing: .5px; }
  .timer.q { background: #fff8f9; border-color: #f3dde1; }
  .timer.q .ti { background: var(--tint); color: var(--crimson); }
  .timer.q .tv { color: var(--crimson); }
  .timer.warn { animation: pulse 1s infinite; }
  @keyframes pulse { 50% { opacity: .5; } }
  .timer.off { opacity: .45; }

  .qtext { font-size: 1.02em; font-weight: 600; line-height: 1.5; margin: 0 0 2px; overflow-wrap: break-word; word-break: break-word; }
  /* lamba question ho to niche fade dikhe taki pata chale aur content hai */
  .qmid { position: relative; scrollbar-width: thin; }
  .qmid.more::after { content: ""; position: sticky; bottom: 0; left: 0; display: block; height: 26px; margin-top: -26px; pointer-events: none;
    background: linear-gradient(180deg, rgba(255,255,255,0), #fff 85%); }
  .timeover { display: inline-flex; align-items: center; gap: 7px; background: #fdeef2; color: var(--red); font-size: .76rem; font-weight: 600; padding: 5px 11px; border-radius: 99px; margin-bottom: 12px; }
  .opts { display: flex; flex-direction: column; gap: 9px; margin: 11px 0 2px; }
  .opt { display: flex; align-items: center; gap: 13px; width: 100%; text-align: left; background: #fff; border: 1.5px solid var(--line-2); border-radius: 13px; padding: 12px 13px; transition: border-color .15s var(--ease), background .15s var(--ease); }
  .opt .k { width: 33px; height: 33px; flex: none; border-radius: 10px; background: #f1f5f9; color: #475569; font-weight: 700; display: grid; place-items: center; font-size: .88rem; }
  .opt .v { flex: 1; min-width: 0; font-size: .94em; font-weight: 500; overflow-wrap: break-word; word-break: break-word; }
  .opt.sel { border-color: var(--purple-2); background: var(--tint); }
  .opt.sel .k { background: var(--grad); color: #fff; }
  .opt.sel .v { font-weight: 600; }
  .opt.dead { opacity: .62; pointer-events: none; }

  .act-row { display: grid; grid-template-columns: auto 1fr 1.55fr auto; gap: 8px; align-items: stretch; }
  .act-row.two { grid-template-columns: 1fr 1fr; margin-top: 9px; }
  .btn-ico { border-radius: 12px; border: 1px solid var(--line-2); background: #fff; display: grid; place-items: center; color: var(--ink); width: 44px; }
  .btn-ico:active { background: #f1f5f9; }
  .btn-ico:disabled { opacity: .4; pointer-events: none; }
  .btn-sm { display: flex; align-items: center; justify-content: center; gap: 8px; padding: 12px 10px; border-radius: 12px; font-weight: 600; font-size: .89rem; border: 1px solid var(--line-2); background: #fff; color: var(--ink); cursor:pointer; }
  .btn-sm:active { background: #f1f5f9; }
  .btn-sm.grad { background: var(--grad); color: #fff; border-color: transparent; box-shadow: 0 5px 14px rgba(142, 27, 42, .22); }
  .btn-sm:disabled { opacity: .45; pointer-events: none; }

  .statusbar { display: flex; align-items: center; gap: 9px; margin-top: 9px; padding-top: 9px; border-top: 1px dashed var(--line-2); flex-wrap: wrap; }
  .statusbar span { display: flex; align-items: center; gap: 5px; font-size: .71rem; color: #475569; white-space: nowrap; }
  .statusbar span b { font-weight: 700; color: var(--ink); }
  .dot { width: 10px; height: 10px; border-radius: 50%; flex: none; }
  .dot.ans { background: var(--green); }
  .dot.not { background: #fff; border: 1.5px solid var(--line-2); }
  .dot.mark { background: #f472b6; }
  .btn-submit { margin-left: auto; background: var(--grad); color: #fff; font-weight: 700; font-size: .76rem; letter-spacing: .4px; padding: 8px 15px; border-radius: 10px; display: flex; align-items: center; gap: 6px; box-shadow: 0 5px 14px rgba(142, 27, 42, .22); white-space: nowrap; cursor: pointer; border: none;}

  /* ============ PALETTE SHEET ============ */
  .scrim { position: fixed; inset: 0; background: rgba(15, 23, 42, .55); backdrop-filter: blur(2px); z-index: 60; opacity: 0; visibility: hidden; transition: opacity .25s, visibility .25s; }
  .scrim.show { opacity: 1; visibility: visible; }
  .sheet { position: fixed; left: 0; right: 0; top: 0; z-index: 70; transform: translateY(-104%); transition: transform .3s var(--ease); }
  .sheet.show { transform: none; }
  .sheet-in { max-width: 520px; margin: 0 auto; padding: 0 12px; }
  .sheet-box { background: #fff; border-radius: 0 0 20px 20px; box-shadow: 0 16px 40px rgba(15, 23, 42, .25); overflow: hidden; max-height: 92dvh; display: flex; flex-direction: column; }
  .sheet-bar { background: var(--grad); color: #fff; padding: 11px 14px; display: flex; align-items: center; gap: 12px; flex: none; }
  .sheet-bar .bl { width: 34px; height: 34px; border-radius: 50%; background: #fff; padding: 2px; flex: none; overflow: hidden; }
  .sheet-bar .tt { flex: 1; min-width: 0; }
  .sheet-bar .tt b { display: block; font-family: 'Oswald', sans-serif; font-size: .98rem; font-weight: 700; letter-spacing: 1.2px; text-transform: uppercase; }
  .sheet-bar .tt small { font-size: .58rem; letter-spacing: 1.6px; opacity: .82; text-transform: uppercase; }
  .tabs { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; padding: 12px 14px 0; flex: none; }
  .tab { padding: 10px; border-radius: 10px; font-weight: 600; font-size: .86rem; background: #f1f5f9; color: var(--muted); }
  .tab.on { background: var(--grad); color: #fff; box-shadow: 0 5px 14px rgba(142, 27, 42, .2); }
  .pal-body { padding: 14px; overflow-y: auto; }
  
  /* 🔥 FIXED PALETTE GRID: RESTORED TO 5 COLUMNS SO NO EMPTY SPACE ON RIGHT 🔥 */
  .pal-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 10px; }
  .pal-btn { height: 42px; border-radius: 8px; font-weight: 700; font-size: .95rem; border: 1px solid var(--line-2); background: #fff; color: #475569; display: grid; place-items: center; cursor: pointer; }
  .pal-btn.ans { background: #dcfce7; border-color: #86efac; color: #15803d; }
  .pal-btn.mark { background: #fce7f3; border-color: #f9a8d4; color: #be185d; }
  .pal-btn.cur { border-color: var(--purple-2); border-width: 2px; color: var(--purple); background: var(--tint); }
  
  .pal-note { display: flex; gap: 10px; align-items: flex-start; background: #f8fafc; border: 1px solid var(--line); border-radius: 11px; padding: 11px 12px; margin-top: 14px; font-size: .78rem; color: var(--muted); line-height: 1.55; }
  .pal-note i { color: var(--purple-2); margin-top: 2px; }
  .tab-pane { display: none; }
  .tab-pane.show { display: block; }
  .ins-list { list-style: none; }
  .ins-list li { display: flex; gap: 10px; padding: 9px 0; font-size: .85rem; color: #40474f; }
  .ins-list li + li { border-top: 1px solid var(--line); }
  .ins-list i { color: var(--purple-2); margin-top: 3px; }

  /* 🔥 CUSTOM MODAL FOR SUBMIT CONFIRMATION 🔥 */
  .custom-modal { position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%) scale(0.95); z-index: 200; background: #fff; width: 90%; max-width: 340px; border-radius: 16px; padding: 20px; box-shadow: 0 20px 40px rgba(0,0,0,0.2); opacity: 0; visibility: hidden; transition: all 0.2s var(--ease); text-align: center; }
  .custom-modal.show { opacity: 1; visibility: visible; transform: translate(-50%, -50%) scale(1); }
  .custom-modal .icon { width: 50px; height: 50px; background: #fbecef; color: var(--crimson); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; margin: 0 auto 15px; }
  .custom-modal h3 { font-family: 'Oswald', sans-serif; font-size: 1.3rem; margin-bottom: 8px; color: var(--ink); text-transform: uppercase; letter-spacing: 1px;}
  .custom-modal p { font-size: 0.9rem; color: var(--muted); margin-bottom: 20px; line-height: 1.5; }
  .custom-modal .stats { display: flex; justify-content: center; gap: 15px; margin-bottom: 20px; }
  .custom-modal .stat-box { background: #f8fafc; border: 1px solid var(--line); padding: 10px; border-radius: 10px; flex: 1; }
  .custom-modal .stat-box b { display: block; font-size: 1.2rem; color: var(--ink); }
  .custom-modal .stat-box span { font-size: 0.7rem; color: var(--muted); text-transform: uppercase; font-weight: 600; }
  .modal-actions { display: flex; gap: 10px; }
  .modal-btn { flex: 1; padding: 12px; border-radius: 10px; font-weight: 600; font-size: 0.9rem; cursor: pointer; transition: 0.2s; border: none; }
  .btn-cancel { background: #f1f5f9; color: var(--ink); }
  .btn-cancel:hover { background: #e2e8f0; }
  .btn-confirm { background: var(--grad); color: #fff; box-shadow: 0 4px 12px rgba(142, 27, 42, .2); }
  .btn-confirm:hover { opacity: 0.9; }

  /* ============ RESULT ============ */
  .res-hero { background: var(--grad); color: #fff; border-radius: 18px; padding: 26px 18px 22px; text-align: center; margin-top: 16px; box-shadow: 0 12px 30px rgba(110, 18, 32, .28); position: relative; overflow: hidden; }
  .res-hero::after { content: ""; position: absolute; inset: 0; background: radial-gradient(60% 50% at 50% 0%, rgba(255,255,255,.18), transparent 70%); }
  .res-hero .tro { font-size: 2.9rem; position: relative; }
  .res-hero h2 { font-family: 'Oswald', sans-serif; font-size: 1.6rem; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; margin-top: 6px; position: relative; }
  .res-hero p { font-size: .83rem; opacity: .9; position: relative; }
  .res-hero p b { display: block; font-size: .96rem; margin-top: 2px; }
  .res-cards { display: grid; grid-template-columns: repeat(3, 1fr); gap: 9px; margin-top: 14px; }
  .res-c { background: var(--card); border: 1px solid var(--line); border-radius: 14px; padding: 12px 8px; text-align: center; box-shadow: var(--shadow); }
  .res-c i { font-size: 1.1rem; }
  .res-c .lb { font-size: .74rem; color: var(--muted); margin-top: 3px; }
  .res-c .vl { font-size: 1.4rem; font-weight: 800; line-height: 1.2; }
  .res-c .mk { font-size: .72rem; font-weight: 600; }
  .res-c.ok i, .res-c.ok .vl, .res-c.ok .mk { color: var(--green); }
  .res-c.no i, .res-c.no .vl, .res-c.no .mk { color: var(--red); }
  .res-c.sk i, .res-c.sk .vl, .res-c.sk .mk { color: #94a3b8; }
  .score-card { background: var(--card); border: 1px solid var(--line); border-radius: 16px; padding: 18px; margin-top: 12px; text-align: center; box-shadow: var(--shadow); }
  .score-card .md { font-size: 2rem; }
  .score-card .lb { font-size: .84rem; color: var(--muted); }
  .score-card .sc { font-family: 'Oswald', sans-serif; font-size: 2.6rem; font-weight: 700; color: var(--crimson); line-height: 1.1; }
  .score-card .sc small { font-size: 1.1rem; color: var(--muted); font-weight: 600; }
  .score-card .pc { font-size: .9rem; color: var(--muted); font-weight: 600; }
  .bar { height: 9px; border-radius: 99px; background: #e9eef4; margin-top: 12px; overflow: hidden; }
  .bar i { display: block; height: 100%; border-radius: 99px; background: var(--grad); transition: width .8s var(--ease); }
  .sum-card { background: var(--card); border: 1px solid var(--line); border-radius: 16px; padding: 14px; margin-top: 12px; box-shadow: var(--shadow); }
  .sum-card h3 { font-size: .88rem; font-weight: 600; color: var(--muted); display: flex; align-items: center; gap: 8px; margin-bottom: 11px; }
  .sum-grid { display: grid; grid-template-columns: repeat(10, 1fr); gap: 6px; }
  .sum-grid b { aspect-ratio: 1 / 1; display: grid; place-items: center; border-radius: 7px; font-size: .74rem; font-weight: 700; }
  .sum-grid b.ok { background: #dcfce7; color: #15803d; }
  .sum-grid b.no { background: #fee2e2; color: #b91c1c; }
  .sum-grid b.sk { background: #f1f5f9; color: #64748b; }
  .rev { margin-top: 12px; display: none; }
  .rev.show { display: block; }
  .rev-q { background: var(--card); border: 1px solid var(--line); border-left: 4px solid var(--line-2); border-radius: 13px; padding: 13px 14px; margin-bottom: 10px; box-shadow: var(--shadow); }
  .rev-q.ok { border-left-color: var(--green); }
  .rev-q.no { border-left-color: var(--red); }
  .rev-q.sk { border-left-color: #94a3b8; }
  .rev-q .rq { font-size: .92rem; font-weight: 600; line-height: 1.45; overflow-wrap: break-word; word-break: break-word; }
  .rev-q .rq span { color: var(--purple); }
  .rev-a { display: grid; grid-template-columns: auto 1fr; gap: 4px 8px; align-items: start; font-size: .83rem; margin-top: 8px; }
  .rev-a .tag { grid-column: 1; align-self: start; font-size: .66rem; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; padding: 2px 8px; border-radius: 99px; margin-top: 2px; white-space: nowrap; }
  .rev-a > span:last-child { min-width: 0; overflow-wrap: break-word; word-break: break-word; }
  @media (max-width: 420px) {
    .rev-a { grid-template-columns: 1fr; gap: 3px; }
    .rev-a .tag { justify-self: start; }
    .rev-q { padding: 12px 12px; }
  }
  .rev-a.you .tag { background: #fdeef2; color: var(--red); }
  .rev-a.you.ok .tag, .rev-a.cor .tag { background: var(--green-bg); color: var(--green); }
  .rev-exp { margin-top: 9px; padding-top: 9px; border-top: 1px dashed var(--line-2); font-size: .8rem; color: var(--muted); line-height: 1.55; overflow-wrap: break-word; word-break: break-word; }

  @media (max-height: 760px), (max-width: 400px) {
    .qfix { padding: 10px 12px 0; }
    .timers { margin-top: 9px; gap: 8px; }
    .timer { padding: 7px 9px; gap: 8px; }
    .timer .ti { width: 28px; height: 28px; font-size: .82rem; }
    .timer .tv { font-size: 1rem; }
    .timer .tl { font-size: .64rem; }
    .qtext { font-size: .95em; }
    .opts { gap: 7px; margin: 9px 0 10px; }
    .opt { padding: 9px 11px; gap: 11px; }
    .opt .k { width: 29px; height: 29px; font-size: .82rem; }
    .opt .v { font-size: .9em; }
    /* 🔥 HEIGHT FIX FOR MOBILE 🔥 */
    .qmid-in { padding: 10px 0 12px; margin: 0; }
    .qbot { padding: 8px 12px 10px; }
    .act-row { grid-template-columns: auto 1fr 1.7fr auto; gap: 7px; }
    .btn-sm { padding: 10px 7px; font-size: .83rem; white-space: nowrap; }
    .btn-ico { width: 40px; }
    .statusbar { margin-top: 7px; padding-top: 7px; }
    .btn-submit { padding: 7px 13px; }
  }

  /* ============ ALL QUESTIONS — paper view ============ */
  .btn-paper { display: flex; align-items: center; justify-content: center; gap: 9px; width: 100%; margin-top: 12px; padding: 12px; border-radius: 11px; background: var(--grad); color: #fff; font-weight: 600; font-size: .88rem; box-shadow: 0 5px 14px rgba(118,24,78,.26); cursor: pointer; border: none; }
  .paper { position: fixed; inset: 0; z-index: 120; background: #eef2f6; display: none; flex-direction: column; }
  .paper.show { display: flex; }
  .paper-bar { flex: none; background: var(--grad); color: #fff; display: flex; align-items: center; gap: 10px; padding: 10px 14px; box-shadow: 0 2px 12px rgba(76,21,101,.25); }
  .paper-bar b { flex: 1; min-width: 0; font-family: 'Oswald', sans-serif; font-size: 1rem; font-weight: 500; letter-spacing: 1.2px; text-transform: uppercase; text-align: center; }
  .paper-bar button { color: #fff; background: rgba(255,255,255,.14); border-radius: 8px; padding: 8px 12px; font-size: .8rem; font-weight: 600; display: flex; align-items: center; gap: 7px; cursor: pointer; border: none; }
  .paper-bar button:active { background: rgba(255,255,255,.26); }
  .paper-bar b { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
  .paper-bar .paper-time { font-size: .86rem; padding: 6px 11px; }
  .paper-bar .pb-icon { background: #15803d; border: 1px solid #16a34a; width: 36px; height: 36px; padding: 0; display: grid; place-items: center; font-size: .95rem; flex: none; }
  @media (max-width: 680px) {
    .paper-bar { gap: 6px; }
    .paper-bar .paper-time { font-size: .76rem; padding: 5px 8px; }
    .paper-bar .paper-time i { display: none; }
    .paper-bar .pb-icon { width: 32px; height: 32px; font-size: .86rem; }
    .paper-bar button { padding: 7px 9px; }
  }
  @media (max-width: 400px) { .paper-bar b { font-size: .76rem; letter-spacing: .4px; } }
  
  .paper-scroll { flex: 1 1 auto; min-height: 0; overflow-y: auto; padding: 14px 10px 30px; }

  .p-sheet { background: #fff; max-width: 860px; margin: 0 auto; border-radius: 10px; box-shadow: 0 8px 26px rgba(31,20,48,.12); padding: 0; font-family: Tinos, "Times New Roman", serif; color: #1a1a1a; font-size: 16px; }
  .p-hdr { background: linear-gradient(180deg, #9a1f2f 0%, #8e1b2a 55%, #7d1524 100%); color: #fff; border-radius: 8px 8px 0 0; padding: 13px 16px; display: flex; align-items: center; gap: 14px; box-shadow: 0 4px 12px rgba(110,18,32,.28); }
  .p-hdr .pl { width: 46px; height: 46px; flex: none; background: #fff; border-radius: 50%; padding: 3px; overflow: hidden; }
  .p-hdr .pt { flex: 1; min-width: 0; }
  .p-hdr h2 { font-size: 1.35rem; font-weight: 700; letter-spacing: .5px; text-transform: uppercase; line-height: 1.15; }
  .p-hdr small { display: block; font-size: .7rem; letter-spacing: 2px; text-transform: uppercase; opacity: .9; margin-top: 2px; }
  
  /* 🔥 HEADER RIGHT: MCQ + TIMING 🔥 */
  .p-hdr .pb { flex: none; background: rgba(0,0,0,.26); border: 1px solid rgba(255,255,255,.2); border-radius: 6px; padding: 6px 12px; font-size: .8rem; font-weight: 700; letter-spacing: .4px; text-transform: uppercase; white-space: nowrap; }
  
  /* 🔥 REDUCED QUESTION SPACING FOR PAPER VIEW 🔥 */
  .p-qs { padding: 12px 14px 0; }
  .p-q { page-break-inside: avoid; break-inside: avoid; margin: 0 0 15px; border-bottom: 1px dashed #cbd5e1; padding-bottom: 10px; }
  .p-qh { display: flex; gap: 8px; align-items: flex-start; }
  .p-badge { flex: none; background: #8e1b2a; color: #fff; font-weight: 700; font-size: .8rem; border-radius: 5px; padding: 3px 6px; min-width: 32px; text-align: center; line-height: 1.2; }
  .p-text { flex: 1; min-width: 0; background: #fbecef; border-radius: 5px; padding: 3px 8px; font-weight: 700; font-size: .9rem; line-height: 1.35; overflow-wrap: break-word; word-break: break-word; }
  
  /* Reduced gap and margin for options */
  .p-opts { display: grid; grid-template-columns: 1fr 1fr; gap: 2px 10px; margin: 4px 0 0 38px; }
  .p-o { font-size: .85rem; line-height: 1.3; text-align: left; border: 1px solid transparent; border-radius: 4px; padding: 2px 5px; display: flex; gap: 6px; align-items: flex-start; font-family: inherit; color: inherit; cursor: pointer; background: none; }
  .p-o b { color: #8e1b2a; flex: none; }
  .p-o > span { min-width: 0; overflow-wrap: break-word; word-break: break-word; }
  .p-o:hover { background: #fbecef; }
  .p-o.sel { background: #fbecef; border-color: #8e1b2a; font-weight: 700; }
  .p-o.sel b { color: #7d1524; }
  .p-q.answered .p-badge { background: #15803d; }
  .p-mark { font-size: .72rem; color: #15803d; font-weight: 700; margin-left: 38px; display: none; }
  .p-q.answered .p-mark { display: block; }
  
  .p-key { margin: 0 14px 14px; border: 1px solid #8e1b2a; border-radius: 7px; padding: 10px 12px; }
  .p-key h3 { text-align: center; text-transform: uppercase; color: #8e1b2a; font-size: .95rem; letter-spacing: 1px; margin-bottom: 7px; }
  .p-key-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 3px 10px; font-size: .86rem; }
  .p-key-grid b { color: #8e1b2a; }
  
  /* 🔥 FOOTER UPDATES 🔥 */
  .p-foot { padding: 10px 13px; border-top: 2px solid #8e1b2a; display: flex; justify-content: space-between; align-items: center; gap: 10px; color: #1a1a1a; flex-wrap: nowrap; background: #fffafb; border-radius: 0 0 8px 8px; }
  .p-foot .pf-brand { display: flex; align-items: center; gap: 8px; min-width: 0; }
  .p-foot .pf-brand .pf-logo { width: 28px; height: 28px; flex: none; border-radius: 50%; overflow: hidden; background: #fff; }
  .p-foot .pf-tx { min-width: 0; line-height: 1.2; }
  .p-foot .pf-tx b { display: block; font-family: 'Oswald', sans-serif; letter-spacing: 1px; color: #8e1b2a; font-size: .84rem; text-transform: uppercase; white-space: nowrap; }
  .p-foot .pf-tx small { display: block; font-size: .58rem; letter-spacing: 1.3px; text-transform: uppercase; color: #8a7d80; font-family: 'Poppins', sans-serif; white-space: nowrap; }
  .p-foot .pf-wa { flex: none; display: inline-flex; align-items: center; gap: 8px; background: #e8f7ee; border: 1px solid #bfe6cc; border-radius: 99px; padding: 5px 13px 5px 10px; font-family: 'Poppins', sans-serif; text-decoration: none; }
  .p-foot .pf-wa i { font-size: 1.15rem; color: #25D366; flex: none; }
  .p-foot .pf-wa .pf-wa-tx { line-height: 1.2; text-align: left; }
  .p-foot .pf-wa small { display: block; font-size: .56rem; letter-spacing: 1.1px; text-transform: uppercase; color: #15803d; font-weight: 700; white-space: nowrap; }
  .p-foot .pf-wa b { display: block; font-size: .8rem; color: #0f172a; letter-spacing: .4px; white-space: nowrap; }
  @media (max-width: 440px) {
    .p-foot { padding: 9px 10px; gap: 8px; }
    .p-foot .pf-brand .pf-logo { width: 24px; height: 24px; }
    .p-foot .pf-tx b { font-size: .74rem; letter-spacing: .6px; }
    .p-foot .pf-tx small { display: none; }
    .p-foot .pf-wa { padding: 4px 11px 4px 8px; gap: 6px; }
    .p-foot .pf-wa i { font-size: 1rem; }
    .p-foot .pf-wa b { font-size: .74rem; }
  }
  
  /* ---- paper view: phone ---- */
  @media (max-width: 680px) {
    .paper-scroll { padding: 10px 8px 26px; }
    .p-sheet { font-size: 17px; border-radius: 8px; }
    .p-hdr { flex-wrap: wrap; gap: 10px; padding: 12px 13px; }
    .p-hdr .pl { width: 40px; height: 40px; }
    .p-hdr h2 { font-size: 1.1rem; }
    .p-hdr small { font-size: .62rem; letter-spacing: 1.4px; }
    .p-qs { padding: 12px 11px 0; }
    .p-q { margin-bottom: 13px; padding-bottom: 9px; }
    .p-badge { font-size: .78rem; min-width: 30px; padding: 3px 5px; }
    .p-text { font-size: .95rem; padding: 4px 8px; }
    .p-opts { grid-template-columns: 1fr; gap: 3px; margin: 6px 0 0 10px; }
    .p-o { font-size: .92rem; padding: 4px 7px; }
    .p-mark { margin-left: 10px; }
    .p-key { margin: 0 11px 12px; }
    .p-key-grid { grid-template-columns: repeat(4, 1fr); font-size: .9rem; }
    .paper-bar { padding: 9px 10px; gap: 7px; }
    .paper-bar button { padding: 8px 10px; font-size: .76rem; }
    .paper-bar b { font-size: .85rem; letter-spacing: .8px; }
  }
  @media (max-width: 400px) { .p-key-grid { grid-template-columns: repeat(3, 1fr); } }

  /* 🔥 CSS FOR PDF PRINTING (FIXED BLANK WHITE PAGES & REPEATING FOOTER) 🔥 */
  @media print {
      * {
          -webkit-print-color-adjust: exact !important; 
          print-color-adjust: exact !important; 
      }
      @page {
          margin: 10mm 10mm 15mm 10mm !important; /* Top, Right, Bottom, Left */
      }
      html, body {
          background: #fff !important;
          margin: 0 !important;
          padding: 0 !important;
          height: auto !important;
          min-height: auto !important;
          overflow: visible !important;
      }
      body.mode-test {
          overflow: visible !important;
          height: auto !important;
      }
      body::before, .paper-bar, .screen, .scrim, .sheet, .toast { 
          display: none !important; 
      }
      .paper, .paper-scroll, .p-sheet {
          display: block !important;
          position: static !important;
          background: #fff !important;
          height: auto !important;
          overflow: visible !important;
          box-shadow: none !important;
          margin: 0 !important;
          padding: 0 !important;
          border: none !important;
      }
      
      /* Make sure print table breaks correctly */
      .print-table { width: 100%; border: none; border-collapse: collapse; }
      /* questions do column me (notes sheet jaise) */
      /* do column, beech me divider line (notes sheet jaisi) */
      .p-qs { column-count: 2; column-gap: 9mm; column-rule: .35mm solid #b9b0b2; padding: 10px 0 0 !important; }
      .p-q { break-inside: avoid; page-break-inside: avoid; margin-bottom: 4mm; }
      .p-opts { grid-template-columns: 1fr !important; margin-left: 10mm !important; }
      .p-o { border: none !important; background: none !important; }
      .p-o.sel { background: none !important; border: none !important; }
      .p-mark { display: none !important; }
      /* answer key hamesha naye page par */
      .p-key { break-before: page; page-break-before: always; margin: 0 !important; }
      .p-hdr { position: static !important; border-radius: 0 !important; }
      .p-foot { border-top: 2px solid #8e1b2a !important; padding: 10px 0 !important; border-radius: 0 !important; }
  }

  /* math bits */
  /* math ab bilkul baki text jaisa — sirf structure (fraction, sup/sub) alag */
  .mi { font-family: inherit; font-size: 1em; font-style: normal; }
  /* fraction/root jaise tukde beech se na toote, baaki expression wrap ho jaye */
  .frac, .rad, .vec, .nuc { white-space: nowrap; }
  .mi .fn, .mi b { font-style: normal; }
  .frac { display: inline-flex; flex-direction: column; align-items: center; vertical-align: middle; line-height: 1.05; margin: 0 .12em; font-size: .92em; font-style: normal; }
  .frac > span { padding: 0 .22em; }
  .frac > span:first-child { border-bottom: 1px solid currentColor; }
  .rad { border-top: 1px solid currentColor; padding: 0 .12em; }
  .vec { position: relative; display: inline-block; font-style: italic; }
  .vec::before { content: "→"; position: absolute; left: 0; right: 0; top: -.72em; font-size: .64em; text-align: center; font-style: normal; }
  sup, sub { font-size: .72em; line-height: 0; }

  .toast {
    position: fixed; left: 50%; bottom: 24px; transform: translateX(-50%); z-index: 200;
    background: #0f172a; color: #fff; padding: 11px 18px; border-radius: 10px;
    font-size: .84rem; box-shadow: 0 10px 24px rgba(0,0,0,.25); pointer-events: none;
  }

  @media (prefers-reduced-motion: reduce) { * { transition-duration: .01ms !important; animation: none !important; } }
</style>
</head>
<body>

<!-- ══════════════════ CUSTOM SUBMIT MODAL ══════════════════ -->
<div class="scrim" id="modalScrim"></div>
<div class="custom-modal" id="submitModal">
  <div class="icon"><i class="fa-solid fa-paper-plane"></i></div>
  <h3>Submit Test?</h3>
  <p>Are you sure you want to submit your test? You cannot change your answers after submission.</p>
  <div class="stats">
    <div class="stat-box"><b id="modAtt">0</b><span>Attempted</span></div>
    <div class="stat-box"><b id="modUnatt">0</b><span>Left</span></div>
  </div>
  <div class="modal-actions">
    <button class="modal-btn btn-cancel" id="btnCancelSubmit">Cancel</button>
    <button class="modal-btn btn-confirm" id="btnConfirmSubmit">Yes, Submit</button>
  </div>
</div>

<!-- ══════════════════ 1. START ══════════════════ -->
<section class="screen show" id="scrStart">
  <div class="wrap">
    <div class="start-head">
      <div class="start-logo" data-logo></div>
      <div class="start-name">DIPLOMA WALLAH</div>
      <div class="start-rule"></div>
      <h1 class="start-title">MCQ <span>TEST</span></h1>
    </div>

    <div class="chapter-card">
      <svg class="ic" viewBox="-2 -2 68 68" fill="none" stroke="#fff" stroke-width="2.5" stroke-linecap="round" aria-hidden="true">
        <circle cx="32" cy="32" r="4.6" fill="#fff" stroke="none"/>
        <ellipse cx="32" cy="32" rx="25" ry="10"/>
        <ellipse cx="32" cy="32" rx="25" ry="10" transform="rotate(60 32 32)"/>
        <ellipse cx="32" cy="32" rx="25" ry="10" transform="rotate(120 32 32)"/>
        <circle cx="54" cy="21" r="3.1" fill="#fff" stroke="none"/>
        <circle cx="12" cy="40" r="3.1" fill="#fff" stroke="none"/>
      </svg>
      <div class="tx">
        <h2><?= htmlspecialchars($TEST['title'], ENT_QUOTES) ?></h2>
        <div class="chip-row">
          <?php foreach ($TEST['tags'] as $t): ?><span class="chip"><?= htmlspecialchars($t, ENT_QUOTES) ?></span><?php endforeach; ?>
        </div>
      </div>
    </div>

    <div class="sub-line"><?= htmlspecialchars($TEST['subtitle'], ENT_QUOTES) ?></div>

    <div class="stat-card">
      <div class="stat-row">
        <div class="stat"><div class="si"><i class="fa fa-file-lines"></i></div><div><div class="sl">Questions</div><div class="sv"><?= $QN ?></div></div></div>
        <div class="stat rose"><div class="si"><i class="fa fa-trophy"></i></div><div><div class="sl">Total Marks</div><div class="sv"><?= $num($totalMarks) ?></div></div></div>
      </div>
      <div class="stat-row">
        <div class="stat"><div class="si"><i class="fa fa-clock"></i></div><div><div class="sl">Total Time</div><div class="sv"><?= (int)$TEST['total_minutes'] ?> Minutes</div></div></div>
        <div class="stat rose"><div class="si"><i class="fa fa-star"></i></div><div><div class="sl">Per Question</div><div class="sv"><?= $num($TEST['mark']) ?> Mark</div></div></div>
      </div>
      <?php if ((int)$TEST['per_question_sec'] > 0): ?>
      <div class="stat-row">
        <div class="stat full"><div class="si"><i class="fa fa-stopwatch"></i></div><div><div class="sl">Time per Question</div><div class="sv"><?= (int)$TEST['per_question_sec'] ?> Seconds</div></div></div>
      </div>
      <?php endif; ?>
      <?php if ($TEST['negative'] > 0): ?>
      <div class="stat-row">
        <div class="stat rose full"><div class="si"><i class="fa fa-circle-minus"></i></div><div><div class="sl">Negative Marking</div><div class="sv"><?= $num($TEST['negative']) ?> Mark per wrong answer</div></div></div>
      </div>
      <?php endif; ?>
    </div>

    <div class="rules">
      <div class="rules-head"><i class="fa fa-list-check"></i> TEST RULES</div>
      <ol>
        <?php $r = 0; ?>
        <li><span class="n"><?= ++$r ?></span><span>Each correct answer carries <b><?= $num($TEST['mark']) ?> mark</b>.</span></li>
        <?php if ($TEST['negative'] > 0): ?>
        <li><span class="n"><?= ++$r ?></span><span><b><?= $num($TEST['negative']) ?> mark</b> is deducted for every wrong answer.</span></li>
        <?php endif; ?>
        <li><span class="n"><?= ++$r ?></span><span>Unanswered questions carry no marks and no penalty.</span></li>
        <?php if ((int)$TEST['per_question_sec'] > 0): ?>
        <li><span class="n"><?= ++$r ?></span><span>Each question has a time limit of <b><?= (int)$TEST['per_question_sec'] ?> seconds</b>. Once it ends, the question is closed.</span></li>
        <?php endif; ?>
        <li><span class="n"><?= ++$r ?></span><span>You may revisit and change any answered question before submitting.</span></li>
        <li><span class="n"><?= ++$r ?></span><span>The test is submitted automatically when the overall timer reaches zero.</span></li>
      </ol>
    </div>

    <!-- 🔥 STUDENT DETAILS FORM BEFORE START 🔥 -->
    <div class="rules" id="studentFormBox" style="margin-top:16px;">
        <div class="rules-head"><i class="fa fa-user"></i> STUDENT DETAILS</div>
        <div style="padding:15px; background:var(--card);">
            <input type="text" id="stuName" class="student-input" placeholder="Your Full Name *" required>
            <input type="email" id="stuEmail" class="student-input" placeholder="Email Address (Optional)">
            <input type="tel" id="stuPhone" class="student-input" placeholder="Phone Number (Optional)" style="margin-bottom:0;">
        </div>
    </div>

    <div class="resume-note" id="resumeNote" style="display:none">
      <i class="fa fa-rotate-left" style="margin-top:3px"></i>
      <span>You have an unfinished attempt. <b id="resumeInfo"></b></span>
    </div>

    <div style="margin-top:16px">
      <button class="btn-primary" id="btnStart"><i class="fa fa-play"></i> START TEST <i class="fa fa-arrow-right"></i></button>
      <button class="btn-ghost" id="btnResume" style="display:none;margin-top:10px"><i class="fa fa-rotate-left"></i> Resume previous attempt</button>
    </div>

    <div class="powered">Powered by DIPLOMA WALLAH</div>
  </div>
</section>

<!-- ══════════════════ 2. TEST ══════════════════ -->
<section class="screen" id="scrTest">
  <div class="test-shell">
    <header class="topbar">
      <div class="topbar-in">
        <button class="icon-btn" id="btnPalette" aria-label="Question palette"><i class="fa fa-bars"></i></button>
        <div class="bl" data-logo></div>
        <div class="tt"><b>DIPLOMA WALLAH</b><small>D2D PLATFORM</small></div>
        <div class="nav-time" id="navTime" title="Overall time left"><i class="fa fa-hourglass-half"></i> <span id="navTimeVal">00:00</span></div>
      </div>
    </header>

    <div class="test-main">
      <div class="qcard">
        <div class="qfix">
          <div class="qtop">
            <div class="qnum" id="qLabel">Q1</div>
            <div class="ch"><?= htmlspecialchars($TEST['title'], ENT_QUOTES) ?></div>
            <div class="cnt"><span id="qNow">1</span><span> / <?= $QN ?></span></div>
            <button class="bmk" id="btnMark" title="Bookmark this question" aria-label="Bookmark"><i class="fa-regular fa-bookmark"></i></button>
          </div>
          <div class="timers" id="timerRow">
            <div class="timer q" id="tQuestion">
              <i class="fa fa-stopwatch tq-ic"></i>
              <span class="tq-l">Question Time</span>
              <span class="tv" id="tQuestionVal">01:00</span>
              <span class="tq-bar"><i id="qBar"></i></span>
            </div>
          </div>
        </div>

        <div class="qmid" id="qScroll">
          <div class="qmid-in">
            <!-- 🔥 TOP PLACEMENT FOR TIME'S UP MESSAGE 🔥 -->
            <div id="qDead"></div>
            <div class="qtext" id="qText">—</div>
            <div class="opts" id="qOpts"></div>
          </div>
        </div>

        <div class="qbot">
          <div class="act-row">
            <button class="btn-ico" id="btnPrev" title="Previous question" aria-label="Previous"><i class="fa fa-arrow-left"></i></button>
            <button class="btn-sm" id="btnClear"><i class="fa fa-eraser"></i> Clear</button>
            <button class="btn-sm grad" id="btnSaveNext">Save &amp; Next <i class="fa fa-arrow-right"></i></button>
            <button class="btn-ico" id="btnNext" title="Next question" aria-label="Next"><i class="fa fa-arrow-right"></i></button>
          </div>
          <div class="statusbar">
            <span><i class="dot ans"></i> Answered <b id="cAns">0</b></span>
            <span><i class="dot not"></i> Left <b id="cNot"><?= $QN ?></b></span>
            <span><i class="dot mark"></i> Marked <b id="cMark">0</b></span>
            <button class="btn-submit" id="btnSubmit"><i class="fa fa-paper-plane"></i> SUBMIT</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- palette sheet -->
<div class="scrim" id="scrim"></div>
<div class="sheet" id="sheet">
  <div class="sheet-in">
    <div class="sheet-box">
      <div class="sheet-bar">
        <div class="bl" data-logo></div>
        <div class="tt"><b>DIPLOMA WALLAH</b><small>D2D PLATFORM</small></div>
        <button class="icon-btn" id="btnSheetClose" aria-label="Close"><i class="fa fa-xmark"></i></button>
      </div>
      <div class="tabs">
        <button class="tab on" data-tab="pal">Question Palette</button>
        <button class="tab" data-tab="ins">Instructions</button>
      </div>
      <div class="pal-body">
        <div class="tab-pane show" id="paneP">
          <div class="pal-grid" id="palGrid"></div>
          <button class="btn-paper" id="btnPaper"><i class="fa fa-list-ul"></i> View all questions</button>
          <div class="pal-note"><i class="fa fa-circle-info"></i><span>Select any question number to go directly to that question. Answered questions can be reviewed and changed at any time before you submit the test.</span></div>
        </div>
        <div class="tab-pane" id="paneI">
          <ul class="ins-list">
            <li><i class="fa fa-circle-check"></i><span>Every correct answer carries <b><?= $num($TEST['mark']) ?> mark</b>.</span></li>
            <?php if ($TEST['negative'] > 0): ?>
            <li><i class="fa fa-circle-minus"></i><span><b><?= $num($TEST['negative']) ?> mark</b> is deducted for each wrong answer. Unanswered questions carry no penalty.</span></li>
            <?php endif; ?>
            <?php if ((int)$TEST['per_question_sec'] > 0): ?>
            <li><i class="fa fa-stopwatch"></i><span>Each question is open for <b><?= (int)$TEST['per_question_sec'] ?> seconds</b>. When that time ends the test moves to the next question and the previous one is closed.</span></li>
            <?php endif; ?>
            <li><i class="fa fa-bookmark"></i><span>Use Bookmark to flag a question for a second look. Bookmarked questions appear in pink in the palette.</span></li>
            <li><i class="fa fa-paper-plane"></i><span>The test is submitted automatically once the overall timer reaches zero.</span></li>
          </ul>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ══════════════════ ALL QUESTIONS (paper view) ══════════════════ -->
<div class="paper" id="paperView">
  <div class="paper-bar">
    <button id="paperClose"><i class="fa fa-arrow-left"></i> Back</button>
    <b id="paperTitle" style="text-align:center;">Questions</b>
    <div class="nav-time paper-time" id="paperTime" title="Overall time left"><i class="fa fa-hourglass-half"></i> <span id="paperTimeVal">00:00</span></div>
    <!-- 🔥 NEW PRINT BUTTON 🔥 -->
    <button id="paperPrint" class="pb-icon" title="Print / Save PDF" aria-label="Print or save as PDF" style="display:none;"><i class="fa-solid fa-print"></i></button>
  </div>
  <div class="paper-scroll" id="paperScroll">
    <div class="p-sheet">
      
      <!-- PRINT TABLE FOR REPEATING FOOTER -->
      <table class="print-table">
        <tbody>
          <tr>
            <td style="padding: 0;">
              
              <div class="p-hdr">
                <div class="pl" data-logo></div>
                <div class="pt">
                  <h2><?= htmlspecialchars($TEST['title'], ENT_QUOTES) ?></h2>
                  <small><?= htmlspecialchars($TEST['subtitle'], ENT_QUOTES) ?></small>
                </div>
              </div>
              
              <div class="p-qs" id="paperQs"></div>
              
              <div class="p-key" id="paperKey" style="display:none">
                <h3>Answer Key</h3>
                <div class="p-key-grid" id="paperKeyGrid"></div>
              </div>

            </td>
          </tr>
        </tbody>
        <tfoot>
          <tr>
            <td style="padding: 0;">
              <!-- 🔥 REPEATING FOOTER UPDATES 🔥 -->
              <div class="p-foot">
                <span class="pf-brand">
                  <span class="pf-logo" data-logo></span>
                  <span class="pf-tx"><b>Diploma Wallah</b><small>Learn &bull; Practice &bull; Grow</small></span>
                </span>
                <a class="pf-wa" href="https://wa.me/919153950552" target="_blank" rel="noopener">
                  <i class="fa-brands fa-whatsapp"></i>
                  <span class="pf-wa-tx"><small>Join D2D Batch</small><b>9153950552</b></span>
                </a>
              </div>
            </td>
          </tr>
        </tfoot>
      </table>

    </div>
  </div>
</div>

<!-- ══════════════════ 3. RESULT ══════════════════ -->
<section class="screen" id="scrResult">
  <header class="topbar">
    <div class="topbar-in">
      <div class="bl" data-logo></div>
      <div class="tt"><b>DIPLOMA WALLAH</b><small>D2D PLATFORM</small></div>
    </div>
  </header>

  <div class="wrap">
    <div class="res-hero">
      <div class="tro">🏆</div>
      <h2>Test Completed!</h2>
      <p>Here is your result for <b><?= htmlspecialchars($TEST['title'], ENT_QUOTES) ?></b></p>
    </div>

    <div class="res-cards">
      <div class="res-c ok"><i class="fa fa-circle-check"></i><div class="lb">Correct</div><div class="vl" id="rOk">0</div><div class="mk" id="rOkM">+0</div></div>
      <div class="res-c no"><i class="fa fa-circle-xmark"></i><div class="lb">Wrong</div><div class="vl" id="rNo">0</div><div class="mk" id="rNoM">-0</div></div>
      <div class="res-c sk"><i class="fa fa-circle-minus"></i><div class="lb">Unanswered</div><div class="vl" id="rSk">0</div><div class="mk">0 Marks</div></div>
    </div>

    <div class="score-card">
      <div class="md">🏅</div>
      <div class="lb">Your Score</div>
      <div class="sc"><span id="rScore">0</span><small> / <?= $num($totalMarks) ?></small></div>
      <div class="pc" id="rPct">(0%)</div>
      <div class="bar"><i id="rBar" style="width:0"></i></div>
      <div class="pc" style="margin-top:10px" id="rTime"></div>
    </div>

    <div class="sum-card">
      <h3><i class="fa fa-circle-info"></i> Question Summary</h3>
      <div class="sum-grid" id="sumGrid"></div>
    </div>

    <div class="act-row two" style="margin-top:14px">
      <button class="btn-sm" id="btnReview"><i class="fa fa-book-open"></i> Review Answers</button>
      <button class="btn-sm grad" id="btnAgain"><i class="fa fa-rotate-right"></i> Try Again</button>
    </div>
    <button class="btn-ghost" style="margin-top:9px" id="btnPaper2"><i class="fa fa-list-ul"></i> All questions with answer key</button>
    <a class="btn-ghost" style="margin-top:9px" href="<?= htmlspecialchars($TEST['home_url'], ENT_QUOTES) ?>"><i class="fa fa-house"></i> Back to Home</a>

    <div class="rev" id="revBox"></div>
    <div class="powered">Powered by DIPLOMA WALLAH</div>
  </div>
</section>

<script>
(function () {
  "use strict";
  var T = <?= json_encode($CLIENT, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
  var $ = function (id) { return document.getElementById(id); };
  var KEY = "d2d_mcqtest_" + T.testId;
  var LETTERS = ["A", "B", "C", "D", "E", "F"];

  /* ---------- logo ---------- */
  (function () {
      [].forEach.call(document.querySelectorAll("[data-logo]"), function (h) {
        h.innerHTML = '<img class="brand-logo" src="diplomawallah-logo.png" alt="Diploma Wallah">';
      });
  })();

  var PER_Q = T.perQSec > 0;

  /* ---------- state ---------- */
  var st = null;
  function freshState() {
    var a = [], m = [], t = [];
    for (var i = 0; i < T.count; i++) { a.push(null); m.push(false); t.push(T.perQSec); }
    return { i: 0, ans: a, mark: m, qt: t, left: T.totalSec, done: false, submitting: false, name: '', email: '', phone: '' };
  }
  function save() { try { localStorage.setItem(KEY, JSON.stringify(st)); } catch (e) {} }
  function clearSaved() { try { localStorage.removeItem(KEY); } catch (e) {} }
  function loadSaved() {
    try {
      var s = JSON.parse(localStorage.getItem(KEY) || "null");
      if (!s || s.done || !s.ans || s.ans.length !== T.count) return null;
      return s;
    } catch (e) { return null; }
  }

  var curScreen = "scrStart";
  function paint(id) {
    curScreen = id;
    [].forEach.call(document.querySelectorAll(".screen"), function (s) { s.classList.toggle("show", s.id === id); });
    document.body.classList.toggle("mode-test", id === "scrTest");
    if (id !== "scrTest") window.scrollTo(0, 0);
  }
  function show(id, replace) {
    paint(id);
    var st2 = { scr: id, ov: null };
    try { replace ? history.replaceState(st2, "") : history.pushState(st2, ""); } catch (e) {}
  }
  function pushOverlay(name, replace) {
    try {
      var st2 = { scr: curScreen, ov: name };
      replace ? history.replaceState(st2, "") : history.pushState(st2, "");
    } catch (e) {}
  }
  window.addEventListener("popstate", function (e) {
    var s2 = e.state || { scr: "scrStart", ov: null };
    if (s2.scr !== curScreen) paint(s2.scr);
    
    // Close modal if open on back press
    var ms = $("modalScrim"); if(ms) ms.classList.remove("show");
    var sm = $("submitModal"); if(sm) sm.classList.remove("show");
    
    setSheet(s2.ov === "sheet");
    setPaper(s2.ov === "paper");
  });
  try { history.replaceState({ scr: "scrStart", ov: null }, ""); } catch (e) {}

  /* ---------- start ---------- */
  (function () {
    var s = loadSaved();
    if (!s) return;
    var answered = s.ans.filter(function (x) { return x !== null; }).length;
    $("resumeNote").style.display = "flex";
    $("resumeInfo").textContent = answered + " of " + T.count + " answered, " + fmt(s.left) + " remaining.";
    $("btnResume").style.display = "flex";
    $("btnResume").addEventListener("click", function () { st = s; show("scrTest"); startTimer(); renderQ(); });
  })();
  $("btnStart").addEventListener("click", function () {
    var n = $("stuName").value.trim();
    if (!n) { alert("Please enter your name to start the test."); $("stuName").focus(); return; }
    
    clearSaved(); 
    st = freshState(); 
    st.name = n;
    st.email = $("stuEmail").value.trim();
    st.phone = $("stuPhone").value.trim();
    
    show("scrTest"); 
    startTimer(); 
    renderQ();
  });

  /* ---------- timers ---------- */
  var tick = null;
  function fmt(s) {
    s = Math.max(0, Math.round(s));
    var m = Math.floor(s / 60), r = s % 60;
    return (m < 10 ? "0" : "") + m + ":" + (r < 10 ? "0" : "") + r;
  }
  function startTimer() {
    if (tick) clearInterval(tick);
    tick = setInterval(function () {
      if (!st || st.done) return;
      st.left--;
      var wasRunning = PER_Q && st.qt[st.i] > 0;
      if (wasRunning) st.qt[st.i]--;
      paintTimers();
      if (st.left <= 0) { finish("Time over — the test was submitted automatically."); return; }
      if (wasRunning && st.qt[st.i] <= 0) { renderQ(); toast("Time is up for this question."); }
      if (st.left % 5 === 0) save();
    }, 1000);
    paintTimers();
  }
  function paintTimers(snap) {
    /* overall countdown ab navbar ke right me chalta hai */
    $("navTimeVal").textContent = fmt(st.left);
    $("navTime").classList.toggle("warn", st.left <= 60);
    $("paperTimeVal").textContent = fmt(st.left);
    $("paperTime").classList.toggle("warn", st.left <= 60);
    if (!PER_Q) { $("timerRow").style.display = "none"; return; }
    var box = $("tQuestion");
    $("tQuestionVal").textContent = fmt(st.qt[st.i]);
    box.classList.toggle("warn", st.qt[st.i] <= 10);
    /* bar smooth girta hai; question badalne par turant set ho (animate na kare) */
    var bar = $("qBar"), pct = Math.max(0, Math.min(100, (st.qt[st.i] / T.perQSec) * 100));
    if (snap) { bar.style.transition = "none"; bar.style.width = pct + "%"; void bar.offsetWidth; bar.style.transition = ""; }
    else bar.style.width = pct + "%";
  }

  /* ---------- question ---------- */
  function renderQ() {
    var i = st.i, q = T.questions[i];
    $("qNow").textContent = i + 1;
    $("qLabel").textContent = "Q" + (i + 1);
    $("qText").innerHTML = mt(q.q);

    var over = PER_Q && st.qt[i] <= 0;
    $("qDead").innerHTML = over ? '<div class="timeover"><i class="fa fa-hourglass-end"></i> Time is up for this question — you can still answer it</div>' : "";

    var html = "";
    for (var k = 0; k < q.o.length; k++) {
      html += '<button class="opt' + (st.ans[i] === k ? " sel" : "") + '" data-k="' + k + '">' +
              '<span class="k">' + LETTERS[k] + '</span><span class="v"></span></button>';
    }
    $("qOpts").innerHTML = html;
    [].forEach.call($("qOpts").children, function (b, k) { b.querySelector(".v").innerHTML = mt(q.o[k]); });

    var mk = $("btnMark");
    mk.classList.toggle("on", !!st.mark[i]);
    mk.innerHTML = st.mark[i] ? '<i class="fa-solid fa-bookmark"></i>' : '<i class="fa-regular fa-bookmark"></i>';
    mk.title = st.mark[i] ? "Remove bookmark" : "Bookmark this question";

    $("btnPrev").disabled = i === 0;
    $("btnNext").disabled = i === T.count - 1;
    $("btnSaveNext").innerHTML = (i === T.count - 1)
      ? 'Save &amp; Finish <i class="fa fa-flag-checkered"></i>'
      : 'Save &amp; Next <i class="fa fa-arrow-right"></i>';

    paintTimers(true); paintCounts(); paintPalette();
    setTimeout(function () { fitQuestion(); markScrollable(); }, 0);
  }
  function paintCounts() {
    var a = 0, m = 0;
    for (var i = 0; i < T.count; i++) { if (st.ans[i] !== null) a++; if (st.mark[i]) m++; }
    $("cAns").textContent = a; $("cNot").textContent = T.count - a; $("cMark").textContent = m;
  }
  function go(i) {
    if (i < 0 || i >= T.count) return;
    st.i = i; save(); renderQ();
    $("qScroll").scrollTop = 0;
  }
  /* lamba question: pehle font thoda chhota karke fit karne ki koshish, phir bhi na ho to scroll */
  function fitQuestion() {
    var m = $("qScroll"), inner = m.firstElementChild;
    if (!inner) return;
    var sc = 1;
    inner.style.fontSize = "";
    while (sc > 0.82 && m.scrollHeight > m.clientHeight + 2) {
      sc -= 0.04;
      inner.style.fontSize = sc.toFixed(2) + "rem";
    }
  }
  /* question area me aur content bacha ho to niche fade dikhao */
  function markScrollable() {
    var m = $("qScroll");
    m.classList.toggle("more", m.scrollHeight > m.clientHeight + 4 && m.scrollTop + m.clientHeight < m.scrollHeight - 4);
  }
  $("qScroll").addEventListener("scroll", markScrollable, { passive: true });
  window.addEventListener("resize", function () { fitQuestion(); markScrollable(); });

  $("qOpts").addEventListener("click", function (e) {
    var b = e.target.closest(".opt"); if (!b) return;
    st.ans[st.i] = parseInt(b.getAttribute("data-k"), 10);
    save(); renderQ();
  });
  $("btnClear").addEventListener("click", function () { st.ans[st.i] = null; save(); renderQ(); });
  $("btnMark").addEventListener("click", function () { st.mark[st.i] = !st.mark[st.i]; save(); renderQ(); });
  $("btnPrev").addEventListener("click", function () { go(st.i - 1); });
  $("btnNext").addEventListener("click", function () { go(st.i + 1); });
  $("btnSaveNext").addEventListener("click", function () {
    if (st.i === T.count - 1) { askSubmit(); return; }
    go(st.i + 1);
  });
  
  // 🔥 CUSTOM ALERT LOGIC 🔥
  $("btnSubmit").addEventListener("click", askSubmit);
  function askSubmit() {
    var att = 0, left = 0;
    for (var i = 0; i < T.count; i++) {
        if (st.ans[i] !== null) att++;
        else left++;
    }
    $("modAtt").textContent = att;
    $("modUnatt").textContent = left;
    
    $("modalScrim").classList.add("show");
    $("submitModal").classList.add("show");
  }

  $("btnCancelSubmit").addEventListener("click", function() {
      $("modalScrim").classList.remove("show");
      $("submitModal").classList.remove("show");
  });

  $("btnConfirmSubmit").addEventListener("click", function() {
      $("modalScrim").classList.remove("show");
      $("submitModal").classList.remove("show");
      finish("");
  });

  /* ---------- palette ---------- */
  function paintPalette() {
    var h = "";
    for (var i = 0; i < T.count; i++) {
      var cls = st.ans[i] !== null ? "ans" : "";
      if (st.mark[i]) cls = "mark";
      if (i === st.i) cls += " cur";
      h += '<button class="pal-btn ' + cls + '" data-i="' + i + '">' + (i + 1) + "</button>";
    }
    $("palGrid").innerHTML = h;
  }
  function setSheet(on) { $("sheet").classList.toggle("show", !!on); 
    var scr = $("scrim"); if(scr) scr.classList.toggle("show", !!on); 
  }
  function openSheet(tab) { paintPalette(); switchTab(tab); setSheet(true); pushOverlay("sheet"); }
  function closeSheet() {
    if (!$("sheet").classList.contains("show")) return;
    setSheet(false);
    if (history.state && history.state.ov === "sheet") history.back();
  }
  function switchTab(which) {
    [].forEach.call(document.querySelectorAll(".tab"), function (t) { t.classList.toggle("on", t.getAttribute("data-tab") === which); });
    $("paneP").classList.toggle("show", which === "pal");
    $("paneI").classList.toggle("show", which === "ins");
  }
  $("btnPalette").addEventListener("click", function () { openSheet("pal"); });
  $("btnSheetClose").addEventListener("click", closeSheet);
  
  // Close Modals on Scrim Click
  var scrimEl = $("scrim"); if(scrimEl) scrimEl.addEventListener("click", closeSheet);
  
  var modalScrimEl = $("modalScrim"); 
  if(modalScrimEl) {
      modalScrimEl.addEventListener("click", function() {
          $("modalScrim").classList.remove("show");
          $("submitModal").classList.remove("show");
      });
  }
  
  document.addEventListener("keydown", function (e) { 
      if (e.key === "Escape") { 
          closePaper(); 
          closeSheet(); 
          if($("modalScrim")) $("modalScrim").classList.remove("show"); 
          if($("submitModal")) $("submitModal").classList.remove("show");
      } 
  });
  [].forEach.call(document.querySelectorAll(".tab"), function (t) {
    t.addEventListener("click", function () { switchTab(t.getAttribute("data-tab")); });
  });
  $("palGrid").addEventListener("click", function (e) {
    var b = e.target.closest(".pal-btn"); if (!b) return;
    closeSheet(); go(parseInt(b.getAttribute("data-i"), 10));
  });

  /* ---------- all questions (paper view) ---------- */
  function buildPaper() {
    var h = "", i, k;
    for (i = 0; i < T.count; i++) {
      var q = T.questions[i];
      var picked = st && st.ans ? st.ans[i] : null;
      h += '<div class="p-q' + (picked !== null && picked !== undefined ? " answered" : "") + '" data-q="' + i + '">' +
           '<div class="p-qh"><span class="p-badge">Q' + (i + 1) + '</span>' +
           '<span class="p-text">' + mt(q.q) + "</span></div><div class=\"p-opts\">";
      for (k = 0; k < q.o.length; k++) {
        h += '<button type="button" class="p-o' + (picked === k ? " sel" : "") + '" data-q="' + i + '" data-k="' + k + '">' +
              "<b>(" + LETTERS[k].toLowerCase() + ")</b> <span>" + mt(q.o[k]) + "</span></button>";
      }
      h += '</div><div class="p-mark"><i class="fa fa-circle-check"></i> Answered</div></div>';
    }
    $("paperQs").innerHTML = h;

    /* answer key sirf test khatam hone ke baad */
    var done = !!(st && st.done);
    $("paperKey").style.display = done ? "" : "none";
    if (done) {
      var g = "";
      for (i = 0; i < T.count; i++) g += "<span>" + (i + 1) + " &ndash; <b>" + LETTERS[T.questions[i].a] + "</b></span>";
      $("paperKeyGrid").innerHTML = g;
    }
  }
  function setPaper(on) { $("paperView").classList.toggle("show", !!on); }
  function openPaper() {
    buildPaper(); setPaper(true); $("paperScroll").scrollTop = 0;
    pushOverlay("paper", !!(history.state && history.state.ov === "sheet"));
    
    // 🔥 DYNAMIC HEADING & PRINT BUTTON LOGIC 🔥
    if (st && st.done) {
      $("paperTitle").innerHTML = "Questions with Answer Key";
      $("paperTime").style.display = "none";
      $("paperPrint").style.display = "flex";
      $("btnPaper").innerHTML = '<i class="fa fa-list-ul"></i> View all questions & answer here';
    } else {
      $("paperTitle").innerHTML = "Questions";
      $("paperTime").style.display = st ? "flex" : "none";
      $("paperPrint").style.display = "none";
      $("btnPaper").innerHTML = '<i class="fa fa-list-ul"></i> View all questions';
    }
  }
  function closePaper() {
    if (!$("paperView").classList.contains("show")) return;
    setPaper(false);
    if (st && !st.done) renderQ();
    if (history.state && history.state.ov === "paper") history.back();
  }
  $("btnPaper").addEventListener("click", function () { setSheet(false); openPaper(); });
  $("btnPaper2").addEventListener("click", function () { openPaper(); });
  $("paperClose").addEventListener("click", closePaper);
  
  // 🔥 PRINT BUTTON LOGIC 🔥
  $("paperPrint").addEventListener("click", function () { window.print(); });

  $("paperQs").addEventListener("click", function (e) {
    var b = e.target.closest(".p-o"); if (!b || !st || st.done) return;
    var qi = parseInt(b.getAttribute("data-q"), 10), k = parseInt(b.getAttribute("data-k"), 10);
    st.ans[qi] = k; save();
    var box = b.closest(".p-q");
    [].forEach.call(box.querySelectorAll(".p-o"), function (o) { o.classList.toggle("sel", o === b); });
    box.classList.add("answered");
    renderQ();
  });


  var toastT = null;
  function toast(msg) {
    var d = document.querySelector(".toast");
    if (!d) { d = document.createElement("div"); d.className = "toast"; document.body.appendChild(d); }
    d.textContent = msg;
    if (toastT) clearTimeout(toastT);
    toastT = setTimeout(function () { d.remove(); }, 1900);
  }

  /* ---------- result ---------- */
  function finish(note) {
    if (st.done || st.submitting) return;
    st.submitting = true;
    if (tick) clearInterval(tick);
    toast("Submitting your result...");

    // 🌟 CREATE MAPPED ANSWERS OBJECT (Question ID => Answer) 🌟
    var mappedAns = {};
    for (var i = 0; i < T.count; i++) {
        mappedAns[T.questions[i].id] = st.ans[i];
    }

    var body = new URLSearchParams();
    body.append("action", "submit_attempt");
    body.append("csrf", T.csrf);
    body.append("test_id", T.testId);
    body.append("answers", JSON.stringify(mappedAns));
    
    body.append("student_name", st.name || "");
    body.append("student_email", st.email || "");
    body.append("student_phone", st.phone || "");
    
    body.append("time_taken_sec", Math.max(0, T.totalSec - Math.max(0, st.left)));

    fetch(window.location.href, {
      method: "POST",
      credentials: "same-origin",
      headers: { "Content-Type": "application/x-www-form-urlencoded;charset=UTF-8" },
      body: body.toString()
    })
    .then(function (response) { return response.json(); })
    .then(function (reply) {
      if (!reply || reply.status !== "success" || !reply.result || !reply.result.review) {
        throw new Error((reply && reply.msg) || "Could not submit the test.");
      }

      // 🌟 MAP REVIEW DATA BY QUESTION ID 🌟
      for (var i = 0; i < T.count; i++) {
        var qId = T.questions[i].id;
        if (reply.result.review[qId]) {
            T.questions[i].a = reply.result.review[qId].a;
            T.questions[i].e = reply.result.review[qId].e || "";
        }
      }

      st.done = true;
      st.submitting = false;
      setSheet(false); setPaper(false); clearSaved();

      var ok = Number(reply.result.correct) || 0;
      var no = Number(reply.result.wrong) || 0;
      var sk = Number(reply.result.unanswered) || 0;
      var score = Number(reply.result.score) || 0;
      var total = Number(reply.result.total) || 0;
      var pct = Number(reply.result.percentage) || 0;
      var n = function (x) { return (Math.round(x * 100) / 100).toString(); };

      $("rOk").textContent = ok;  $("rOkM").textContent = "+" + n(ok * T.mark) + " Marks";
      $("rNo").textContent = no;  $("rNoM").textContent = "-" + n(no * T.negative) + " Marks";
      $("rSk").textContent = sk;
      $("rScore").textContent = n(score);
      $("rPct").textContent = "(" + n(Math.max(0, pct)) + "%)";
      $("rBar").style.width = Math.max(0, Math.min(100, pct)) + "%";
      $("rTime").textContent = (note ? note + " " : "") + "Time used: " + fmt(T.totalSec - Math.max(0, st.left)) + " of " + fmt(T.totalSec) + ".";

      var g = "";
      for (var j = 0; j < T.count; j++) {
        var c = st.ans[j] === null ? "sk" : (st.ans[j] === T.questions[j].a ? "ok" : "no");
        g += "<b class='" + c + "'>" + (j + 1) + "</b>";
      }
      $("sumGrid").innerHTML = g;

      buildReview();
      show("scrResult", true);
      if (!reply.saved) toast("Result is shown, but it could not be saved to the database.");
    })
    .catch(function (error) {
      st.submitting = false;
      if (st.left > 0) startTimer();
      save();
      alert(error.message || "Could not submit the test. Check your connection and try again.");
    });
  }
  function buildReview() {
    var h = "";
    for (var i = 0; i < T.count; i++) {
      var q = T.questions[i], you = st.ans[i];
      var cls = you === null ? "sk" : (you === q.a ? "ok" : "no");
      h += '<div class="rev-q ' + cls + '">' +
             '<div class="rq"><span>Q' + (i + 1) + '.</span> ' + mt(q.q) + "</div>" +
             '<div class="rev-a you' + (you !== null && you === q.a ? " ok" : "") + '"><span class="tag">' +
               (you === null ? "Not answered" : "Your answer") + '</span><span>' +
               (you === null ? "—" : LETTERS[you] + ") " + mt(q.o[you])) + "</span></div>" +
             (you === q.a ? "" :
               '<div class="rev-a cor"><span class="tag">Correct</span><span>' + LETTERS[q.a] + ") " + mt(q.o[q.a]) + "</span></div>") +
             (q.e ? '<div class="rev-exp"><b>Explanation:</b> ' + mt(q.e) + "</div>" : "") +
           "</div>";
    }
    $("revBox").innerHTML = h;
  }
  function esc(s) {
    return String(s == null ? "" : s).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;");
  }

  /* =====================================================================
     MATH — $...$ ke andar LaTeX-lite, bahar simple ^ / _ aur chemistry
  ===================================================================== */
  var GREEK = { alpha:"α", beta:"β", gamma:"γ", Gamma:"Γ", delta:"δ", Delta:"Δ", epsilon:"ε", varepsilon:"ε",
    zeta:"ζ", eta:"η", theta:"θ", Theta:"Θ", iota:"ι", kappa:"κ", lambda:"λ", Lambda:"Λ", mu:"μ", nu:"ν",
    xi:"ξ", pi:"π", Pi:"Π", rho:"ρ", sigma:"σ", Sigma:"Σ", tau:"τ", upsilon:"υ", phi:"φ", varphi:"φ", Phi:"Φ",
    chi:"χ", psi:"ψ", Psi:"Ψ", omega:"ω", Omega:"Ω", hbar:"ħ", infty:"∞", angstrom:"Å" };
  var TEX = { times:"×", cdot:"·", div:"÷", pm:"±", mp:"∓", ge:"≥", geq:"≥", le:"≤", leq:"≤", ne:"≠", neq:"≠",
    approx:"≈", propto:"∝", equiv:"≡", sim:"∼", to:"→", rightarrow:"→", leftarrow:"←", leftrightarrow:"↔",
    Rightarrow:"⇒", Leftrightarrow:"⇔", degree:"°", circ:"°", partial:"∂", nabla:"∇", sum:"Σ", prod:"∏",
    int:"∫", oint:"∮", sqrtsym:"√", therefore:"∴", because:"∵", ldots:"…", cdots:"⋯", in:"∈", notin:"∉",
    perp:"⊥", parallel:"∥", angle:"∠", ell:"ℓ", prime:"′", AA:"Å", 
    uparrow:"↑", downarrow:"↓", uparrowdownarrow:"↕", Uparrow:"⇑", Downarrow:"⇓" };
  var FUNCS = "sin|cos|tan|cot|sec|cosec|csc|log|ln|exp|lim|max|min|arcsin|arccos|arctan|sinh|cosh|tanh";

  function mathify(t) {
    var x = t;
    x = x.replace(/\\left\s*|\\right\s*/g, "").replace(/\\,|\\;|\\!|\\ /g, " ");
    x = x.replace(/\\(?:text|mathrm|operatorname)\{([^{}]*)\}/g, "$1")
         .replace(/\\(?:mathbf|boldsymbol)\{([^{}]*)\}/g, "<b>$1</b>");
    for (var i = 0; i < 4; i++) x = x.replace(/\\frac\{([^{}]*)\}\{([^{}]*)\}/g, '<span class="frac"><span>$1</span><span>$2</span></span>');
    x = x.replace(/\\sqrt\{([^{}]*)\}/g, '√<span class="rad">$1</span>').replace(/\\sqrt\s*([A-Za-z0-9]+)/g, '√<span class="rad">$1</span>');
    x = x.replace(new RegExp("\\\\(" + FUNCS + ")(?![A-Za-z])", "g"), '<span class="fn">$1</span>&thinsp;');
    x = x.replace(/\\vec\{([^{}]*)\}/g, '<span class="vec">$1</span>').replace(/\\vec\s*([A-Za-z])/g, '<span class="vec">$1</span>');
    x = x.replace(/\\([A-Za-z]+)/g, function (m, w) {
      return TEX.hasOwnProperty(w) ? TEX[w] : (GREEK.hasOwnProperty(w) ? GREEK[w] : m);
    });
    x = x.replace(/\^\{([^{}]*)\}/g, "<sup>$1</sup>").replace(/_\{([^{}]*)\}/g, "<sub>$1</sub>");
    x = x.replace(/\^\(([^)]+)\)/g, "<sup>$1</sup>").replace(/\^(-?[0-9A-Za-z]+)/g, "<sup>$1</sup>");
    x = x.replace(/([^\s_^{}<>;&])_(-?[0-9A-Za-z]+)/g, "$1<sub>$2</sub>");
    x = x.replace(/\{|\}/g, "");
    return x;
  }
  /** plain text ke liye halka pass: H2O, 10^-27, Ca^2+ */
  function smartText(t) {
    return t.replace(/\^\(([^)]+)\)/g, "<sup>$1</sup>")
            .replace(/\^(-?\d+(?:\.\d+)?|[+-]|\d*[+-])/g, "<sup>$1</sup>")
            .replace(/([A-Za-z\)])_(\d+)/g, "$1<sub>$2</sub>")
            .replace(/\b(\d+(?:\.\d+)?)\s*x\s*10/g, "$1 × 10");
  }
  /** ye sab jagah use hota hai: question, option, review, paper view */
  function mt(raw) {
    var t = esc(raw), out = "", i = 0;
    while (true) {
      var a = t.indexOf("$", i);
      if (a < 0) { out += smartText(t.slice(i)); break; }
      var b = t.indexOf("$", a + 1);
      if (b < 0) { out += smartText(t.slice(i)); break; }
      out += smartText(t.slice(i, a)) + '<span class="mi">' + mathify(t.slice(a + 1, b)) + "</span>";
      i = b + 1;
    }
    return out;
  }
  $("btnReview").addEventListener("click", function () {
    var box = $("revBox"), on = box.classList.toggle("show");
    this.innerHTML = on ? '<i class="fa fa-eye-slash"></i> Hide Review' : '<i class="fa fa-book-open"></i> Review Answers';
    if (on) box.scrollIntoView({ behavior: "smooth", block: "start" });
  });
  $("btnAgain").addEventListener("click", function () {
    clearSaved(); st = null;
    $("revBox").classList.remove("show");
    $("btnReview").innerHTML = '<i class="fa fa-book-open"></i> Review Answers';
    $("resumeNote").style.display = "none"; $("btnResume").style.display = "none";
    show("scrStart");
  });

  window.addEventListener("beforeunload", function (e) {
    if (st && !st.done) { save(); e.preventDefault(); e.returnValue = ""; }
  });
})();
</script>
</body>
</html>
