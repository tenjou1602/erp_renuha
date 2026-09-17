<?php
/**
 * Authentication and Authorization Helper
 * This file should be included at the top of pages that need authentication
 */

// Check if already loaded to prevent duplicate declaration
if (!function_exists('checkAuth')) {

    require_once __DIR__ . '/../config/database.php';

    // ============================================
    // AUTHENTICATION FUNCTIONS
    // ============================================

    function checkAuth() {
        if (!isLoggedIn()) {
            header('Location: ' . APP_URL . 'login.php');
            exit();
        }
    }

    /**
     * Backend-enforced department gate.
     * Admins bypass. Everyone else must belong to $allowed_departments.
     * Denied attempts redirect to the dashboard and are written to activity_log.
     */
    function requireDepartment(array $allowed_departments) {
        checkAuth();

        if (isAdmin()) {
            return true;
        }

        $user_dept = $_SESSION['department'] ?? '';
        if (in_array($user_dept, $allowed_departments, true)) {
            return true;
        }

        $page = $_SERVER['REQUEST_URI'] ?? ($_SERVER['PHP_SELF'] ?? 'unknown');
        $allowed_label = empty($allowed_departments) ? '(none)' : implode(', ', $allowed_departments);

        logActivity(
            $_SESSION['user_id'] ?? null,
            'Access denied',
            'Security',
            'Attempted access to ' . $page . ' | allowed: ' . $allowed_label . ' | department: ' . $user_dept
        );

        $_SESSION['error'] = 'Access denied. You do not have permission to view that page.';
        header('Location: ' . APP_URL . 'index.php');
        exit();
    }

    function requireAdmin() {
        checkAuth();
        if (isAdmin()) {
            return true;
        }

        $page = $_SERVER['REQUEST_URI'] ?? ($_SERVER['PHP_SELF'] ?? 'unknown');
        logActivity(
            $_SESSION['user_id'] ?? null,
            'Access denied',
            'Security',
            'Attempted admin access to ' . $page . ' | department: ' . ($_SESSION['department'] ?? '') . ' | role: ' . ($_SESSION['role'] ?? '')
        );

        $_SESSION['error'] = 'Access denied. You do not have permission to view that page.';
        header('Location: ' . APP_URL . 'index.php');
        exit();
    }

    /** @deprecated Use requireDepartment(). Kept as an alias. */
    function checkDepartment($allowed_departments = []) {
        return requireDepartment((array) $allowed_departments);
    }

    function requireRole($role) {
        checkAuth();
        if ($_SESSION['role'] !== $role && !isAdmin()) {
            $page = $_SERVER['REQUEST_URI'] ?? ($_SERVER['PHP_SELF'] ?? 'unknown');
            logActivity(
                $_SESSION['user_id'] ?? null,
                'Access denied',
                'Security',
                'Attempted role-restricted access to ' . $page . ' | required: ' . $role . ' | role: ' . ($_SESSION['role'] ?? '')
            );
            $_SESSION['error'] = 'Access denied. You do not have permission to view that page.';
            header('Location: ' . APP_URL . 'index.php');
            exit();
        }
    }

    // ============================================
    // PERMISSION CHECK FUNCTIONS
    // ============================================

    function canViewModule($module) {
        if (!isLoggedIn()) return false;
        if (isAdmin()) return true;

        $allowed = [
            'procurement' => ['procurement'],
            'projects' => ['projects'],
            'accounting' => ['accounting'],
            'payroll' => ['accounting'],
            'payslips' => ['accounting'],
            'warehouse' => ['warehouse'],
            'materials' => ['warehouse'],
            'admin' => [],
        ];

        $user_dept = $_SESSION['department'] ?? '';
        return in_array($user_dept, $allowed[$module] ?? [], true);
    }

    function canEdit() {
        if (!isLoggedIn()) return false;
        if (isAdmin()) return true;
        return $_SESSION['role'] === 'manager';
    }

    function canDelete() {
        if (!isLoggedIn()) return false;
        if (isAdmin()) return true;
        return $_SESSION['role'] === 'manager';
    }

    // ============================================
    // USER INFO FUNCTIONS
    // ============================================

    function getCurrentUserFullName() {
        return $_SESSION['full_name'] ?? 'User';
    }

    function getCurrentUserDepartment() {
        return $_SESSION['department'] ?? 'unknown';
    }

    function getCurrentUserRole() {
        return $_SESSION['role'] ?? 'staff';
    }

    function isUserActive() {
        if (!isLoggedIn()) return false;
        global $pdo;
        try {
            $stmt = $pdo->prepare("SELECT status FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch();
            return $user && $user['status'] === 'active';
        } catch(PDOException $e) {
            return false;
        }
    }
}
