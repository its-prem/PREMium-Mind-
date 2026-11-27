<?php
/**
 * Upload to Hostinger premind/ as: qa_text.php
 *
 * The questions & answers text of an Important Questions product, for the people who bought it.
 *   GET/POST qa_text.php?course_id=12      (login token in the X-PM-Session header)
 *
 * Answer: { status, title, text, micro: { enabled, price, owned, showTnc, tncText, allowDownload, downloadAt } }
 *   - the text is sent only to a buyer of the product (or an admin);
 *   - micro.owned says whether this buyer also bought the micro format (the A4 boxes);
 *   - micro.allowDownload / downloadAt = the rule the admin set for downloading the micro format.
 */
require_once __DIR__ . '/pm_cors.php';
pm_cors_json(['GET', 'POST']);

require_once __DIR__ . '/pm_session_token.php';
require_once __DIR__ . '/pm_orders.php';
require_once __DIR__ . '/pm_admin_emails.php';
include __DIR__ . '/db_connect.php';

function pm_qa_fail(string $msg, int $http, string $code): void {
    http_response_code($http);
    echo json_encode(['status' => 'error', 'message' => $msg, 'code' => $code]);
    exit;
}

$body  = pm_request_body();
$email = strtolower(trim(pm_session_email(pm_request_token($body))));
if ($email === '') pm_qa_fail('Please log in first.', 401, 'login_required');
require_once __DIR__ . '/pm_rate_limit.php';
if (!pm_rate_limit($conn, 'qatext:' . $email, 120, 600)) pm_qa_fail('Too many requests. Please wait a few minutes.', 429, 'rate_limited');

$courseId = (int)($_GET['course_id'] ?? $body['course_id'] ?? 0);
if ($courseId <= 0) pm_qa_fail('Bad product id.', 400, 'bad_course');

$stmt = $conn->prepare('SELECT * FROM courses WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $courseId);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$row) pm_qa_fail('Product not found.', 404, 'not_found');

$isAdmin = in_array($email, pm_admin_email_list(), true);
if (!$isAdmin && !pm_user_owns($conn, $email, $courseId)) {
    pm_qa_fail('Buy this product to read it.', 403, 'not_owned');
}

$text = trim((string)($row['qa_text'] ?? ''));
$pdf  = trim((string)($row['pdf_file'] ?? ''));   // the card may also have a PDF (sent to the buyer only)
if ($text === '') pm_qa_fail('The questions for this product are not added yet.', 404, 'no_text');

$microOwned = $isAdmin || pm_user_owns_micro($conn, $email, $courseId);

echo json_encode([
    'status' => 'success',
    'title'  => (string)($row['title'] ?? ''),
    'text'   => $text,
    'pdf'    => $pdf,
    'micro'  => [
        'enabled'       => !empty($row['micro_enabled']),
        'price'         => ($row['micro_price'] ?? null) === null ? 0 : (float)$row['micro_price'],
        'owned'         => $microOwned,
        'adminPreview'  => $isAdmin && !pm_user_owns_micro($conn, $email, $courseId),   // an admin sees it without paying
        'showTnc'       => !empty($row['micro_show_tnc']),
        'tncText'       => (string)($row['micro_tnc_text'] ?? ''),
        'allowDownload' => !isset($row['micro_allow_download']) || (int)$row['micro_allow_download'] === 1,
        'downloadAt'    => (string)($row['micro_download_at'] ?? ''),
    ],
]);
