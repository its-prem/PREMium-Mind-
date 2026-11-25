<?php
/**
 * Upload to Hostinger premind/ as: pm_orders.php
 *
 * Shared helpers for the payment files (create_order.php, verify_payment.php, webhook.php):
 *   - pm_orders_ensure_tables($conn)  creates the `orders` table if it is missing
 *   - pm_grant_course(...)            gives a user a course, once (safe to call twice)
 *   - pm_cashfree_fetch_order(...)    asks Cashfree for the real status of an order
 */

require_once __DIR__ . '/pm_load_env.php';
pm_load_dotenv(__DIR__ . '/.env');

/** Our own record of every payment attempt. Cashfree stays the source of truth for "PAID". */
if (!function_exists('pm_orders_ensure_tables')) {
    function pm_orders_ensure_tables(mysqli $conn): void {
        static $done = false;
        if ($done) return;
        $done = true;
        $conn->query("CREATE TABLE IF NOT EXISTS orders (
            id INT AUTO_INCREMENT PRIMARY KEY,
            order_id VARCHAR(64) NOT NULL UNIQUE,
            user_email VARCHAR(190) NOT NULL,
            course_id INT NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'CREATED',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            paid_at DATETIME NULL,
            INDEX idx_orders_email (user_email),
            INDEX idx_orders_course (course_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }
}

/** Does user_courses already have this person + course? */
if (!function_exists('pm_user_owns')) {
    function pm_user_owns(mysqli $conn, string $email, int $courseId): bool {
        $stmt = $conn->prepare('SELECT 1 FROM user_courses WHERE user_email = ? AND course_id = ? LIMIT 1');
        if (!$stmt) return false;
        $stmt->bind_param('si', $email, $courseId);
        $stmt->execute();
        $stmt->store_result();
        $has = $stmt->num_rows > 0;
        $stmt->close();
        return $has;
    }
}

/** Gives the course to the user. Returns true when the user owns it afterwards. */
if (!function_exists('pm_grant_course')) {
    function pm_grant_course(mysqli $conn, string $email, int $courseId): bool {
        $email = strtolower(trim($email));
        if ($email === '' || $courseId <= 0) return false;
        if (pm_user_owns($conn, $email, $courseId)) return true;

        date_default_timezone_set('Asia/Kolkata');
        $hasPurchasedAt = false;
        $col = @$conn->query("SHOW COLUMNS FROM user_courses LIKE 'purchased_at'");
        if ($col && $col->num_rows > 0) $hasPurchasedAt = true;

        if ($hasPurchasedAt) {
            $now = date('Y-m-d H:i:s');
            $stmt = $conn->prepare('INSERT INTO user_courses (user_email, course_id, purchased_at) VALUES (?, ?, ?)');
            if (!$stmt) return false;
            $stmt->bind_param('sis', $email, $courseId, $now);
        } else {
            $stmt = $conn->prepare('INSERT INTO user_courses (user_email, course_id) VALUES (?, ?)');
            if (!$stmt) return false;
            $stmt->bind_param('si', $email, $courseId);
        }
        $ok = $stmt->execute();
        $stmt->close();
        return $ok || pm_user_owns($conn, $email, $courseId);
    }
}

/** Cashfree order details (array) or null if the call failed. */
if (!function_exists('pm_cashfree_fetch_order')) {
    function pm_cashfree_fetch_order(string $orderId): ?array {
        $appId     = pm_env('CASHFREE_APP_ID');
        $secretKey = pm_env('CASHFREE_SECRET_KEY');
        $env       = pm_env('CASHFREE_ENV', 'PROD');
        if ($appId === '' || $appId === 'CHANGE_ME' || $secretKey === '' || $secretKey === 'CHANGE_ME') return null;

        $url = ($env === 'PROD' ? 'https://api.cashfree.com/pg/orders/' : 'https://sandbox.cashfree.com/pg/orders/')
             . rawurlencode($orderId);
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'x-api-version: 2023-08-01',
            "x-client-id: $appId",
            "x-client-secret: $secretKey",
        ]);
        $resp = curl_exec($ch);
        curl_close($ch);
        if ($resp === false) return null;
        $data = json_decode($resp, true);
        return is_array($data) ? $data : null;
    }
}
