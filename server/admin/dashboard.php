<?php
/**
 * GET admin/dashboard.php
 *   -> stats, last 14 days of sales (from orders) and the latest purchases.
 * Add a new number: add one line to $stats below; the React page shows every key
 * listed in src/admin/tabs/Dashboard.jsx (STAT_CARDS).
 */
require_once __DIR__ . '/_boot.php';
pm_admin_require();
pm_orders_ensure_tables($conn);

$stats = [
    'students'      => (int)pm_scalar($conn, 'SELECT COUNT(*) FROM users'),
    'verified'      => (int)pm_scalar($conn, 'SELECT COUNT(*) FROM users WHERE is_active = 1'),
    'courses'       => (int)pm_scalar($conn, 'SELECT COUNT(*) FROM courses WHERE is_deleted = 0'),
    'trashed'       => (int)pm_scalar($conn, 'SELECT COUNT(*) FROM courses WHERE is_deleted = 1'),
    'pendingReports' => (int)pm_scalar($conn, "SELECT COUNT(*) FROM reports WHERE status = 'pending'"),
    'paidOrders'    => (int)pm_scalar($conn, "SELECT COUNT(*) FROM orders WHERE status = 'PAID'"),
    'revenue'       => pm_scalar($conn, "SELECT COALESCE(SUM(amount),0) FROM orders WHERE status = 'PAID'"),
    'revenueToday'  => pm_scalar($conn, "SELECT COALESCE(SUM(amount),0) FROM orders WHERE status = 'PAID' AND DATE(paid_at) = CURDATE()"),
];

// sales per day, last 14 days (days with no sale are filled with 0)
$byDay = [];
foreach (pm_rows($conn->query(
    "SELECT DATE(paid_at) AS d, COUNT(*) AS n, COALESCE(SUM(amount),0) AS total
       FROM orders WHERE status = 'PAID' AND paid_at >= (CURDATE() - INTERVAL 13 DAY)
      GROUP BY DATE(paid_at)"
)) as $r) {
    $byDay[$r['d']] = ['orders' => (int)$r['n'], 'amount' => (float)$r['total']];
}
$chart = [];
for ($i = 13; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i day"));
    $chart[] = ['date' => $d, 'orders' => $byDay[$d]['orders'] ?? 0, 'amount' => $byDay[$d]['amount'] ?? 0];
}

// latest purchases (everything that reached user_courses, paid by gateway or given by hand)
$hasAt = pm_has_column($conn, 'user_courses', 'purchased_at');
$hasId = pm_has_column($conn, 'user_courses', 'id');
$order = [];
if ($hasAt) { $order[] = 'uc.purchased_at IS NULL ASC'; $order[] = 'uc.purchased_at DESC'; }
if ($hasId) $order[] = 'uc.id DESC';
if (!$order) $order[] = 'uc.user_email DESC';
$purchases = pm_rows($conn->query(
    'SELECT uc.user_email, uc.course_id, ' . ($hasAt ? 'uc.purchased_at' : 'NULL') . ' AS purchased_at,
            u.name AS user_name, c.title AS course_title, c.price AS course_price, c.image AS course_image
       FROM user_courses uc
       LEFT JOIN users u ON CONVERT(u.email USING utf8mb4) COLLATE utf8mb4_general_ci = CONVERT(uc.user_email USING utf8mb4) COLLATE utf8mb4_general_ci
       LEFT JOIN courses c ON c.id = uc.course_id
      ORDER BY ' . implode(', ', $order) . ' LIMIT 15'
));

pm_admin_ok(['stats' => $stats, 'chart' => $chart, 'purchases' => $purchases]);
