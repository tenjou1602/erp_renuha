<?php
/**
 * Create accomplishments + attachments tables if missing.
 * CLI: php migrations/run_engineering_tables.php
 */
require_once __DIR__ . '/../config/database.php';

header('Content-Type: text/plain; charset=utf-8');

$sql = file_get_contents(__DIR__ . '/2026_09_25_engineering_tables.sql');
if ($sql === false) {
    echo "ERROR: migration SQL not found\n";
    exit(1);
}

try {
    $pdo->exec($sql);
    echo "OK: accomplishments and attachments tables are in place.\n";
    foreach (['accomplishments', 'attachments'] as $table) {
        $n = (int) $pdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
        echo "  $table exists (" . $n . " rows)\n";
    }
} catch (Throwable $e) {
    echo 'ERROR: ' . $e->getMessage() . "\n";
    exit(1);
}
