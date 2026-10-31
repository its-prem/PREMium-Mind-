<?php
/**
 * Upload to Hostinger premind/ as: d2d_pyq_check.php
 *
 * Single source of truth for "does this email have FULL D2D PYQ access?".
 * Used by d2d_pyq_access.php (UI lock/unlock) and d2d_pyq_data.php (the
 * actual gated data), so both always agree.
 *
 * FULL access is granted if EITHER:
 *   1) the email was granted manually by the admin (d2d_pyq_access table), OR
 *   2) the email has purchased a course flagged "D2D PYQ" in the admin panel
 *      (courses.grants_pyq = 1). Purchases land in user_courses via the
 *      Cashfree webhook / verify_payment, so a successful payment unlocks
 *      every year automatically — no manual step.
 *
 * Read-only: never creates/alters tables. If the grants_pyq column does not
 * exist yet (admin has not opened the panel since this update), that query
 * simply returns no rows and only the manual grant path applies.
 */

if (!function_exists('pm_d2d_pyq_has_full')) {
    function pm_d2d_pyq_has_full(mysqli $conn, string $email): bool {
        $email = strtolower(trim($email));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) return false;
        $e = $conn->real_escape_string($email);

        // 1) Manual admin grant.
        $r = @$conn->query("SELECT 1 FROM d2d_pyq_access WHERE email='$e' LIMIT 1");
        if ($r && $r->num_rows > 0) return true;

        // 2) Paid for a course flagged as granting D2D PYQ access.
        // NOTE: we intentionally do NOT require the course to be active
        // (is_deleted=0). A paid user keeps PYQ access even if the admin
        // later trashes/hides the card from the store — same as normal
        // courses. To actually strip access from everyone, use the admin's
        // "Revoke from all" (which deletes the user_courses rows).
        $r2 = @$conn->query(
            "SELECT 1 FROM user_courses uc
             JOIN courses c ON c.id = uc.course_id
             WHERE uc.user_email='$e' AND c.grants_pyq=1
             LIMIT 1"
        );
        if ($r2 && $r2->num_rows > 0) return true;

        return false;
    }
}
