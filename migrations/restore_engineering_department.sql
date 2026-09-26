-- Revert users.department from 'projects' back to 'engineering'.
-- ENUM must include both values during the UPDATE, then drop 'projects'.

ALTER TABLE `users`
  MODIFY COLUMN `department` ENUM('admin','procurement','engineering','projects','accounting','warehouse') NOT NULL;

UPDATE `users`
SET `department` = 'engineering'
WHERE `department` = 'projects';

UPDATE `users`
SET `username` = 'engineering_mgr',
    `full_name` = 'Engineering Manager',
    `email` = 'engineering@runeha.com'
WHERE `username` = 'projects_mgr';

UPDATE `users`
SET `username` = 'engineering_staff',
    `full_name` = 'Engineering Staff',
    `email` = 'engineering.staff@runeha.com'
WHERE `username` = 'projects_staff';

ALTER TABLE `users`
  MODIFY COLUMN `department` ENUM('admin','procurement','engineering','accounting','warehouse') NOT NULL;
