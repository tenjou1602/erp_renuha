-- Runeha ERP migration
-- 1) Rename department engineering -> projects
-- 2) Remove payroll.allowances

ALTER TABLE `users`
  MODIFY COLUMN `department` ENUM('admin','procurement','engineering','projects','accounting','warehouse') NOT NULL;

UPDATE `users`
SET `department` = 'projects'
WHERE `department` = 'engineering';

UPDATE `activity_log`
SET `module` = 'Projects'
WHERE `module` IN ('Engineering', 'engineering');

ALTER TABLE `users`
  MODIFY COLUMN `department` ENUM('admin','procurement','projects','accounting','warehouse') NOT NULL;

-- Recalculate net pay without allowances, then drop the column
UPDATE `payroll`
SET `net_pay` = `basic_salary` - `deductions`;

SET @allowances_exists = (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'payroll'
    AND COLUMN_NAME = 'allowances'
);

SET @drop_allowances = IF(
  @allowances_exists > 0,
  'ALTER TABLE `payroll` DROP COLUMN `allowances`',
  'SELECT 1'
);

PREPARE drop_allowances_statement FROM @drop_allowances;
EXECUTE drop_allowances_statement;
DEALLOCATE PREPARE drop_allowances_statement;
