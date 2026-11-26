<?php
/**
 * Payments (the orders table filled by create_order.php).
 *   GET  admin/orders.php?status=ALL|PAID|CREATED|FAILED&page=1
 *   POST admin/orders.php action=verify  {order_id}   ask Cashfree and, if paid, give the product
 *   POST admin/orders.php action=grant   {order_id}   give the product by hand and mark PAID
 *   POST admin/orders.php action=delete  {order_id}   remove an unpaid order from the list
 */
require_once __DIR__ . '/_boot.php';
pm_admin_require();
pm_orders_ensure_tables($conn);

function pm_find_order(mysqli $conn): array {
    $oid = trim((string)pm_in('order_id'));
    if ($oid === '') pm_admin_fail('Order id is missing.');
    $stmt = $conn->prepare('SELECT order_id, user_email, course_id, amount, status, kind FROM orders WHERE order_id = ? LIMIT 1');
    $stmt->bind_param('s', $oid);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) pm_admin_fail('Order not found.', 404);
    return $row;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)pm_in('action');
    $o = pm_find_order($conn);

    if ($action === 'verify') {
        $cf = pm_cashfree_fetch_order($o['order_id']);
        if ($cf === null) pm_admin_fail('Could not reach Cashfree. Try again.', 502);
        $cfStatus = strtoupper((string)($cf['order_status'] ?? ''));
        if ($cfStatus === 'PAID') {
            if (!pm_grant_for_order($conn, $o)) pm_admin_fail('Paid, but access could not be saved.', 500);
            $stmt = $conn->prepare("UPDATE orders SET status = 'PAID', paid_at = COALESCE(paid_at, NOW()) WHERE order_id = ?");
            $stmt->bind_param('s', $o['order_id']);
            $stmt->execute();
            pm_admin_ok(['gateway' => 'PAID']);
        }
        if ($cfStatus !== '' && $o['status'] !== 'PAID') {
            $stmt = $conn->prepare('UPDATE orders SET status = ? WHERE order_id = ?');
            $stmt->bind_param('ss', $cfStatus, $o['order_id']);
            $stmt->execute();
        }
        pm_admin_ok(['gateway' => $cfStatus ?: 'UNKNOWN']);
    }

    if ($action === 'grant') {
        if (!pm_grant_for_order($conn, $o)) pm_admin_fail('Could not give access.', 500);
        $stmt = $conn->prepare("UPDATE orders SET status = 'PAID', paid_at = COALESCE(paid_at, NOW()) WHERE order_id = ?");
        $stmt->bind_param('s', $o['order_id']);
        $stmt->execute();
        pm_admin_ok();
    }

    if ($action === 'delete') {
        if ($o['status'] === 'PAID') pm_admin_fail('A paid order cannot be deleted.');
        $stmt = $conn->prepare('DELETE FROM orders WHERE order_id = ?');
        $stmt->bind_param('s', $o['order_id']);
        if (!$stmt->execute()) pm_admin_fail('Failed: ' . $stmt->error, 500);
        pm_admin_ok();
    }

    pm_admin_fail('Unknown action.');
}

$status = strtoupper((string)pm_in('status', 'ALL'));
$page = max(1, pm_in_int('page', 1));
$per = 30;
$offset = ($page - 1) * $per;
$where = '';
if ($status === 'PAID') $where = "WHERE o.status = 'PAID'";
elseif ($status === 'CREATED') $where = "WHERE o.status = 'CREATED'";
elseif ($status === 'FAILED') $where = "WHERE o.status NOT IN ('PAID','CREATED')";

$total = (int)pm_scalar($conn, "SELECT COUNT(*) FROM orders o $where");
$stmt = $conn->prepare(
    "SELECT o.order_id, o.user_email, o.course_id, o.amount, o.status, o.kind, o.created_at, o.paid_at,
            c.title AS course_title, u.name AS user_name
       FROM orders o
       LEFT JOIN courses c ON c.id = o.course_id
       LEFT JOIN users u ON u.email = o.user_email
       $where ORDER BY o.id DESC LIMIT ? OFFSET ?"
);
$stmt->bind_param('ii', $per, $offset);
$stmt->execute();
pm_admin_ok(['orders' => pm_rows($stmt->get_result()), 'total' => $total, 'page' => $page, 'perPage' => $per]);
