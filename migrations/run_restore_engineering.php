<?php
/**
 * Restore users.department 'projects' -> 'engineering'.
 * CLI: php migrations/run_restore_engineering.php
 */
require_once __DIR__ . '/../config/database.php';

header('Content-Type: text/plain; charset=utf-8');

try {
    $col = $pdo->query("SHOW COLUMNS FROM users LIKE 'department'")->fetch();
    $type = strtolower((string) ($col['Type'] ?? ''));
    echo 'department column: ' . $type . "\n";

    if (strpos($type, 'enum') !== false && strpos($type, 'engineering') === false) {
        $pdo->exec("ALTER TABLE `users`
            MODIFY COLUMN `department` ENUM('admin','procurement','engineering','projects','accounting','warehouse') NOT NULL");
        echo "OK: ENUM now includes engineering\n";
    }

    $updated = $pdo->exec("UPDATE `users` SET `department` = 'engineering' WHERE `department` = 'projects'");
    echo 'OK: updated ' . (int) $updated . " user row(s)\n";

    $pdo->exec("UPDATE `users` SET username = 'engineering_mgr', full_name = 'Engineering Manager', email = 'engineering@runeha.com' WHERE username = 'projects_mgr'");
    $pdo->exec("UPDATE `users` SET username = 'engineering_staff', full_name = 'Engineering Staff', email = 'engineering.staff@runeha.com' WHERE username = 'projects_staff'");
    echo "OK: renamed seed usernames if present\n";

    if (strpos($type, 'enum') !== false) {
        $pdo->exec("ALTER TABLE `users`
            MODIFY COLUMN `department` ENUM('admin','procurement','engineering','accounting','warehouse') NOT NULL");
        echo "OK: ENUM finalized without 'projects'\n";
    }

    echo "Migration complete.\n";
} catch (Throwable $e) {
    http_response_code(500);
    echo 'ERROR: ' . $e->getMessage() . "\n";
    exit(1);
}
