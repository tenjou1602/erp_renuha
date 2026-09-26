<?php
/**
 * Repair project codes stored in scientific notation (UINT64 overflow from CAST of '-001').
 * CLI: php migrations/repair_project_codes.php
 */
require_once __DIR__ . '/../config/database.php';

header('Content-Type: text/plain; charset=utf-8');

try {
    $rows = $pdo->query("SELECT id, project_code, created_at FROM projects ORDER BY id")->fetchAll();
    $fixed = 0;
    $stmt = $pdo->prepare("UPDATE projects SET project_code = ? WHERE id = ?");
    foreach ($rows as $row) {
        $code = (string) $row['project_code'];
        $bad = (bool) preg_match('/e\+/i', $code)
            || (bool) preg_match('/1\.84467/i', $code)
            || strlen($code) > 24;
        if (!$bad) {
            continue;
        }
        $year = !empty($row['created_at']) ? date('Y', strtotime($row['created_at'])) : date('Y');
        $new = 'PRJ-' . $year . '-' . str_pad((string) $row['id'], 3, '0', STR_PAD_LEFT);
        $stmt->execute([$new, $row['id']]);
        echo "Fixed id {$row['id']}: {$code} -> {$new}\n";
        $fixed++;
    }
    echo "Repaired {$fixed} project code(s).\n";
} catch (Throwable $e) {
    http_response_code(500);
    echo 'ERROR: ' . $e->getMessage() . "\n";
    exit(1);
}
