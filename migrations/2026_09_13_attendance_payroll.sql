-- Add the data needed for attendance-based payroll.

SET @monthly_salary_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'monthly_salary'
);
SET @add_monthly_salary = IF(
  @monthly_salary_exists = 0,
  'ALTER TABLE `users` ADD COLUMN `monthly_salary` DECIMAL(15,2) NOT NULL DEFAULT 0.00 AFTER `email`',
  'SELECT 1'
);
PREPARE add_monthly_salary_statement FROM @add_monthly_salary;
EXECUTE add_monthly_salary_statement;
DEALLOCATE PREPARE add_monthly_salary_statement;

SET @payroll_user_id_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payroll' AND COLUMN_NAME = 'user_id'
);
SET @add_payroll_user_id = IF(
  @payroll_user_id_exists = 0,
  'ALTER TABLE `payroll` ADD COLUMN `user_id` BIGINT UNSIGNED NULL AFTER `id`',
  'SELECT 1'
);
PREPARE add_payroll_user_id_statement FROM @add_payroll_user_id;
EXECUTE add_payroll_user_id_statement;
DEALLOCATE PREPARE add_payroll_user_id_statement;

CREATE TABLE IF NOT EXISTS `attendance` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `attendance_date` DATE NOT NULL,
  `status` ENUM('present','late','absent','half_day') NOT NULL DEFAULT 'present',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_attendance_user_date` (`user_id`, `attendance_date`),
  KEY `idx_attendance_date` (`attendance_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
