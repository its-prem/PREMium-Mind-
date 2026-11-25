-- PREMium Mind (new React site) - database additions.
-- You do NOT have to run this by hand: create_order.php, verify_payment.php, webhook.php and
-- progress.php create these two tables themselves the first time they run.
-- Run it in phpMyAdmin only if you want the tables to exist before the first payment.

CREATE TABLE IF NOT EXISTS orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id VARCHAR(64) NOT NULL UNIQUE,
    user_email VARCHAR(190) NOT NULL,
    course_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'CREATED',   -- CREATED / PAID / EXPIRED / FAILED ...
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    paid_at DATETIME NULL,
    INDEX idx_orders_email (user_email),
    INDEX idx_orders_course (course_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS user_progress (
    user_email VARCHAR(190) NOT NULL,
    kind VARCHAR(40) NOT NULL,                       -- resume / syllabus-chemistry / mock ...
    data MEDIUMTEXT NOT NULL,                        -- JSON
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_email, kind)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- OPTIONAL: stop the same user getting the same course twice at the database level.
-- First check that no duplicates exist (this must return 0 rows):
--   SELECT user_email, course_id, COUNT(*) c FROM user_courses GROUP BY user_email, course_id HAVING c > 1;
-- If it returns rows, delete the extra ones, then run:
--   ALTER TABLE user_courses ADD UNIQUE KEY uniq_user_course (user_email, course_id);
