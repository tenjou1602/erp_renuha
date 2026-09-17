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
    return $_SESSION['role'] === 'manager' || $_SESSION['department'] === 'admin';
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

function generateNumber($prefix, $table, $column) {
    global $pdo;
    $year = date('Y');
    $month = date('m');
    try {
        $stmt = $pdo->prepare("SELECT MAX(CAST(SUBSTRING($column, 9) AS UNSIGNED)) as last_num FROM $table WHERE $column LIKE ?");
        $stmt->execute(["$prefix-$year$month-%"]);
        $result = $stmt->fetch();
        $last_num = $result['last_num'] ?? 0;
        $new_num = str_pad($last_num + 1, 4, '0', STR_PAD_LEFT);
        return "$prefix-$year$month-$new_num";
    } catch(PDOException $e) {
        return "$prefix-" . date('Ymd') . "-0001";
    }
}

// ============================================
// DEPARTMENT HELPER FUNCTIONS (ONLY HERE)
// ============================================

function getDepartmentName($dept) {
    $departments = [
        'procurement' => 'Procurement Department',
        'projects' => 'Projects Department',
        'accounting' => 'Accounting Department',
        'warehouse' => 'Warehouse Department',
        'admin' => 'Administrator'
    ];
    return $departments[$dept] ?? ucfirst($dept);
}

function getDepartmentIcon($dept) {
    $icons = [
        'procurement' => 'fa-shopping-cart',
        'projects' => 'fa-hard-hat',
        'accounting' => 'fa-calculator',
        'warehouse' => 'fa-warehouse',
        'admin' => 'fa-crown'
    ];
    return $icons[$dept] ?? 'fa-user';
}

function getDepartmentColor($dept) {
    $colors = [
        'procurement' => '#4e73df',
        'projects' => '#f59e0b',
        'accounting' => '#1cc88a',
        'warehouse' => '#36b9cc',
        'admin' => '#e74a3b'
    ];
    return $colors[$dept] ?? '#858796';
}

function getDepartmentBadge($dept) {
    $badges = [
        'procurement' => 'badge-procurement',
        'projects' => 'badge-projects',
        'accounting' => 'badge-accounting',
        'warehouse' => 'badge-warehouse',
        'admin' => 'badge-admin'
    ];
    return $badges[$dept] ?? 'badge-secondary';
}