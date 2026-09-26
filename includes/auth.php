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
            'projects' => ['engineering'],
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

    /**
     * Operational create/edit in department modules.
     * Admin is view-only across departments (except PR confirm via canConfirmPurchaseRequest).
     * Department managers retain edit rights on their module's data.
     */
    function canEdit($module = null) {
        if (!isLoggedIn()) return false;
        if (isAdmin()) return false;
        if ($_SESSION['role'] !== 'manager') return false;
        if ($module === null) return true;
        return canViewModule($module);
    }

    /**
     * Operational delete in department modules.
     * Admin cannot delete other departments' records via this helper.
     */
    function canDelete($module = null) {
        if (!isLoggedIn()) return false;
        if (isAdmin()) return false;
        if ($_SESSION['role'] !== 'manager') return false;
        if ($module === null) return true;
        return canViewModule($module);
    }

    /**
     * Whether the current user may perform operational writes (add/edit/delete/stock)
     * in a department module. Admin is view-only; department members may write.
     */
    function canWriteDepartmentData($module = null) {
        if (!isLoggedIn()) return false;
        if (isAdmin()) return false;
        if ($module === null) return true;
        return canViewModule($module);
    }

    /**
     * Admin-only final confirmation of a manager-approved purchase request.
     * $status is the PR's current status (must be 'approved').
     */
    function canConfirmPurchaseRequest($status = null) {
        if (!isAdmin()) return false;
        if ($status === null) return true;
        return $status === 'approved';
    }

    /** Executive Admin encodes the approved client project. */
    function canEncodeApprovedProject() {
        return isLoggedIn() && isAdmin();
    }

    function canSaveProjectRecord() {
        return canEncodeApprovedProject() || canWriteDepartmentData('projects');
    }

    /** Accounting assigns Engineering / Project In-Charge. */
    function canAssignProjectInCharge() {
        return canWriteDepartmentData('accounting');
    }

    /** Engineering submits needs to Procurement; Procurement can also encode PRs. */
    function canSubmitMaterialRequirement() {
        if (!isLoggedIn() || isAdmin()) return false;
        return canViewModule('projects') || canWriteDepartmentData('procurement');
    }

    function canMarkReadyForWarehouse() {
        return canWriteDepartmentData('procurement');
    }

    function canReceivePurchasedMaterials() {
        return canWriteDepartmentData('warehouse');
    }

    function canRequestWarehouseRelease() {
        return canWriteDepartmentData('projects') || canWriteDepartmentData('warehouse');
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
