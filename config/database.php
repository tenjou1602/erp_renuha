<?php
session_start();

// Database configuration
define('DB_HOST', 'localhost');
define('DB_PORT', '3307');
define('DB_NAME', 'runeha_erp');
define('DB_USER', 'root');
define('DB_PASS', '');

// Application configuration
define('APP_NAME', 'RUNEHA INC. ERP System');
define('APP_URL', 'http://localhost/runeha_erp/');
define('TIMEZONE', 'Asia/Manila');

// Set timezone
date_default_timezone_set(TIMEZONE);

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
} catch(PDOException $e) {
    die("Database Connection Failed: " . $e->getMessage());
}

// ============================================
// AUTHENTICATION FUNCTIONS
// ============================================

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ' . APP_URL . 'login.php');
        exit();
    }
}

function isAdmin() {
    if (!isLoggedIn()) return false;
    return $_SESSION['department'] === 'admin' && $_SESSION['role'] === 'admin';
}

function isManager() {
    if (!isLoggedIn()) return false;
    // Admin is view-only across departments — not a department manager.
    if (isAdmin()) return false;
    return $_SESSION['role'] === 'manager';
}

function hasDepartment($department) {
    if (!isLoggedIn()) return false;
    return $_SESSION['department'] === $department || isAdmin();
}

function getCurrentUser() {
    if (!isLoggedIn()) return null;
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT id, username, full_name, email, department, role, status FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetch();
    } catch(PDOException $e) {
        return null;
    }
}

// ============================================
// HELPER FUNCTIONS
// ============================================

function logActivity($user_id, $action, $module, $details = null) {
    global $pdo;
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $stmt = $pdo->prepare("INSERT INTO activity_log (user_id, action, module, details, ip_address) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $action, $module, $details, $ip]);
    } catch(PDOException $e) {
        // Silent fail for logging
    }
}

/** Log the technical SQL error; return a safe message for the UI. */
function userDatabaseError(Throwable $e, $module = 'System') {
    error_log('[Runeha ERP] ' . $e->getMessage());
    logActivity($_SESSION['user_id'] ?? null, 'Database error', $module, $e->getMessage());
    return "We couldn't load this data right now. Please try again or contact support.";
}

function generateNumber($prefix, $table, $column) {
    global $pdo;
    $year = date('Y');
    $month = date('m');
    $seq = nextHyphenSequence($table, $column, $prefix . '-' . $year . $month . '-%', 9999);
    $new_num = str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    return "$prefix-$year$month-$new_num";
}

/**
 * Next integer after the last hyphen in codes like PRJ-2026-001 / PO-202609-0001.
 * Never CAST a leading hyphen (which becomes UNSIGNED 18446744073709551615).
 */
function nextHyphenSequence($table, $column, $like, $max_sane = 999999) {
    global $pdo;
    $allowed_tables = [
        'purchase_requests' => 'pr_number',
        'purchase_orders' => 'po_number',
        'invoices' => 'invoice_number',
        'expenses' => 'expense_number',
        'payroll' => 'payroll_number',
        'payments' => 'payment_number',
        'quotations' => 'quotation_number',
        'projects' => 'project_code',
        'contracts' => 'contract_number',
        'materials' => 'material_code',
    ];
    if (!isset($allowed_tables[$table]) || $allowed_tables[$table] !== $column) {
        return 1;
    }
    try {
        $sql = "SELECT MAX(CAST(SUBSTRING_INDEX(`$column`, '-', -1) AS UNSIGNED)) AS last_num
                FROM `$table`
                WHERE `$column` LIKE ?
                  AND `$column` NOT LIKE '%E+%'
                  AND `$column` NOT LIKE '%e+%'
                  AND SUBSTRING_INDEX(`$column`, '-', -1) REGEXP '^[0-9]+$'";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$like]);
        $last = (int) ($stmt->fetchColumn() ?: 0);
        if ($last < 0 || $last > $max_sane) {
            $last = 0;
        }
        return $last + 1;
    } catch (PDOException $e) {
        return 1;
    }
}

function generateProjectCode() {
    $year = date('Y');
    $seq = nextHyphenSequence('projects', 'project_code', "PRJ-$year-%", 999);
    return 'PRJ-' . $year . '-' . str_pad((string) $seq, 3, '0', STR_PAD_LEFT);
}

/** Render document codes as plain text; never number-format them. */
function displayDocumentCode($code) {
    $code = trim((string) $code);
    if ($code === '') {
        return 'N/A';
    }
    if (preg_match('/e\+/i', $code)) {
        return preg_replace('/\d+\.?\d*e\+\d+/i', 'invalid', $code);
    }
    return $code;
}

// ============================================
// DEPARTMENT HELPER FUNCTIONS (ONLY HERE)
// ============================================

function getDepartmentName($dept) {
    $departments = [
        'procurement' => 'Procurement Department',
        'engineering' => 'Engineering Department',
        'accounting' => 'Accounting Department',
        'warehouse' => 'Warehouse Department',
        'admin' => 'Administrator'
    ];
    return $departments[$dept] ?? ucfirst($dept);
}

function getDepartmentIcon($dept) {
    $icons = [
        'procurement' => 'fa-shopping-cart',
        'engineering' => 'fa-hard-hat',
        'accounting' => 'fa-calculator',
        'warehouse' => 'fa-warehouse',
        'admin' => 'fa-crown'
    ];
    return $icons[$dept] ?? 'fa-user';
}

function getDepartmentColor($dept) {
    $colors = [
        'procurement' => '#4e73df',
        'engineering' => '#f59e0b',
        'accounting' => '#1cc88a',
        'warehouse' => '#36b9cc',
        'admin' => '#e74a3b'
    ];
    return $colors[$dept] ?? '#858796';
}

function getDepartmentBadge($dept) {
    $badges = [
        'procurement' => 'badge-procurement',
        'engineering' => 'badge-engineering',
        'accounting' => 'badge-accounting',
        'warehouse' => 'badge-warehouse',
        'admin' => 'badge-admin'
    ];
    return $badges[$dept] ?? 'badge-secondary';
}