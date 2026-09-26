<?php
/**
 * One-time migration: add 'confirmed' status + confirmed_by/confirmed_at to purchase_requests.
 * Run via browser (admin) or CLI: php migrations/run_pr_confirmed_migration.php
 */
require_once __DIR__ . '/../config/database.php';

header('Content-Type: text/plain; charset=utf-8');

try {
    $pdo->exec("ALTER TABLE `purchase_requests`
        MODIFY COLUMN `status` ENUM(
            'draft','pending','approved','confirmed','rejected','ordered','received'
        ) NOT NULL DEFAULT 'pending'");
    echo "OK: status ENUM updated (includes 'confirmed')\n";

    $cols = $pdo->query("SHOW COLUMNS FROM purchase_requests")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('confirmed_by', $cols, true)) {
        $pdo->exec("ALTER TABLE `purchase_requests` ADD COLUMN `confirmed_by` INT NULL DEFAULT NULL AFTER `approved_at`");
        echo "OK: added confirmed_by\n";
    } else {
        echo "SKIP: confirmed_by already exists\n";
    }
    if (!in_array('confirmed_at', $cols, true)) {
        $pdo->exec("ALTER TABLE `purchase_requests` ADD COLUMN `confirmed_at` DATETIME NULL DEFAULT NULL AFTER `confirmed_by`");
        echo "OK: added confirmed_at\n";
    } else {
        echo "SKIP: confirmed_at already exists\n";
    }

    echo "Migration complete.\n";
} catch (Throwable $e) {
    http_response_code(500);
    echo 'ERROR: ' . $e->getMessage() . "\n";
    exit(1);
}
