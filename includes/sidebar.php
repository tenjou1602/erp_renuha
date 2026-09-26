<?php
// Ensure auth functions are available
if (!function_exists('isAdmin')) {
    require_once __DIR__ . '/auth.php';
}

// Sidebar - Department Headers on Top with Resize (Original Colors)
$department = $_SESSION['department'] ?? 'admin';
$current_page = basename($_SERVER['PHP_SELF']);
$current_module = basename(dirname($_SERVER['PHP_SELF']));
$is_admin = isAdmin();
$is_manager = isManager();
$show_projects = canViewModule('projects');
$show_procurement = canViewModule('procurement');
$show_accounting = canViewModule('accounting');
$show_warehouse = canViewModule('warehouse');
$show_materials = canViewModule('materials');
$show_payroll = canViewModule('payroll');


// Determine which sections should be expanded
$expanded_sections = [];
if ($current_module === 'projects') {
    $expanded_sections[] = 'engineering';
    $expanded_sections[] = 'project-in-charge';
}
if ($current_module === 'procurement') $expanded_sections[] = 'procurement';
if ($current_module === 'accounting') $expanded_sections[] = 'accounting';
if ($current_module === 'warehouse') $expanded_sections[] = 'warehouse';
if ($current_module === 'admin') $expanded_sections[] = 'administration';
if ($department === 'engineering') {
    $expanded_sections[] = 'engineering';
    $expanded_sections[] = 'project-in-charge';
}
if ($department === 'procurement') {
    $expanded_sections[] = 'procurement';
}
if ($department === 'accounting') {
    $expanded_sections[] = 'accounting';
}
if ($department === 'warehouse') {
    $expanded_sections[] = 'warehouse';
}
if ($is_admin) {
    $expanded_sections[] = 'administration';
}

// If no section is expanded, expand the user's department
if (empty($expanded_sections)) {
    $expanded_sections[] = $department;
}

// Get saved sidebar width from cookie
$sidebar_width = isset($_COOKIE['sidebar_width']) ? (int)$_COOKIE['sidebar_width'] : 280;
if ($sidebar_width < 200) $sidebar_width = 200;
if ($sidebar_width > 400) $sidebar_width = 400;
?>
<div class="sidebar" id="sidebar" style="width: <?php echo $sidebar_width; ?>px;">
    <!-- Resize Handle -->
    <div class="sidebar-resize-handle" id="sidebarResizeHandle">
        <div class="resize-dots">
            <span></span><span></span><span></span>
        </div>
    </div>
    
    <!-- Brand -->
    <div class="sidebar-brand">
        <a href="<?php echo APP_URL; ?>index.php" class="brand-logo-link" aria-label="RUNEHA home">
            <img src="<?php echo APP_URL; ?>assets/images/logo.png" alt="RUNEHA INC. logo" class="brand-logo-full">
        </a>
        <button class="sidebar-toggle" onclick="toggleSidebar()">
            <i class="fas fa-times"></i>
        </button>
    </div>
    
    <!-- User Profile -->
    <div class="sidebar-user">
        <div class="avatar">
            <i class="fas fa-user-circle"></i>
        </div>
        <div class="user-info">
            <div class="name"><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'User'); ?></div>
            <div class="role">
                <span><?php echo htmlspecialchars(getDepartmentName($department)); ?></span>
                <span class="badge badge-<?php echo $is_admin ? 'admin' : ($is_manager ? 'manager' : 'staff'); ?>">
                    <?php echo $is_admin ? 'Admin' : ($is_manager ? 'Manager' : 'Staff'); ?>
                </span>
            </div>
        </div>
    </div>
    
    <!-- Navigation -->
    <nav class="sidebar-nav" id="sidebarNav">
        <!-- MAIN NAVIGATION -->
        <div class="nav-section">
            <div class="nav-label">Main Navigation</div>
            <a href="<?php echo APP_URL; ?>index.php" class="nav-item <?php echo $current_page === 'index.php' ? 'active' : ''; ?>">
                <i class="fas fa-tachometer-alt"></i>
                <span>Dashboard</span>
            </a>
        </div>
        
        <!-- ENGINEERING DEPARTMENT -->
        <?php if ($show_projects): ?>
        <div class="nav-section collapsible <?php echo in_array('engineering', $expanded_sections) ? 'expanded' : ''; ?>">
            <div class="nav-section-header" onclick="toggleSection(this)">
                <i class="fas fa-hard-hat"></i>
                <span>Engineering Department</span>
                <i class="fas fa-chevron-<?php echo in_array('engineering', $expanded_sections) ? 'down' : 'right'; ?> arrow"></i>
            </div>
            <div class="nav-section-content">
                <a href="<?php echo APP_URL; ?>modules/projects/projects.php" class="nav-item <?php echo $current_module === 'projects' && $current_page === 'projects.php' ? 'active' : ''; ?>">
                    <i class="fas fa-tasks"></i>
                    <span>Manage Projects</span>
                </a>
                <a href="<?php echo APP_URL; ?>modules/projects/dashboard.php" class="nav-item <?php echo $current_module === 'projects' && $current_page === 'dashboard.php' ? 'active' : ''; ?>">
                    <i class="fas fa-drafting-compass"></i>
                    <span>Plan Projects</span>
                </a>
                <a href="<?php echo APP_URL; ?>modules/projects/material_requirements.php" class="nav-item <?php echo $current_module === 'projects' && strpos($current_page, 'material_requirements') !== false ? 'active' : ''; ?>">
                    <i class="fas fa-boxes"></i>
                    <span>Material Requirements</span>
                </a>
                <a href="<?php echo APP_URL; ?>modules/projects/project_reports.php" class="nav-item <?php echo $current_module === 'projects' && strpos($current_page, 'project_reports') !== false ? 'active' : ''; ?>">
                    <i class="fas fa-chart-bar"></i>
                    <span>Project Reports</span>
                </a>
            </div>
        </div>

        <!-- PROJECT IN-CHARGE (same engineering department) -->
        <div class="nav-section collapsible <?php echo in_array('project-in-charge', $expanded_sections) ? 'expanded' : ''; ?>">
            <div class="nav-section-header" onclick="toggleSection(this)">
                <i class="fas fa-user-tie"></i>
                <span>Project In-Charge</span>
                <i class="fas fa-chevron-<?php echo in_array('project-in-charge', $expanded_sections) ? 'down' : 'right'; ?> arrow"></i>
            </div>
            <div class="nav-section-content">
                <a href="<?php echo APP_URL; ?>modules/projects/project_details.php" class="nav-item <?php echo $current_module === 'projects' && $current_page === 'project_details.php' ? 'active' : ''; ?>">
                    <i class="fas fa-folder-open"></i>
                    <span>Project Details</span>
                </a>
                <a href="<?php echo APP_URL; ?>modules/projects/locations.php" class="nav-item <?php echo $current_module === 'projects' && $current_page === 'locations.php' ? 'active' : ''; ?>">
                    <i class="fas fa-map-marker-alt"></i>
                    <span>Locations</span>
                </a>
                <a href="<?php echo APP_URL; ?>modules/projects/attachments.php" class="nav-item <?php echo $current_module === 'projects' && $current_page === 'attachments.php' ? 'active' : ''; ?>">
                    <i class="fas fa-paperclip"></i>
                    <span>Attachments</span>
                </a>
                <a href="<?php echo APP_URL; ?>modules/projects/accomplishments.php" class="nav-item <?php echo $current_module === 'projects' && $current_page === 'accomplishments.php' ? 'active' : ''; ?>">
                    <i class="fas fa-clipboard-check"></i>
                    <span>Accomplishments</span>
                </a>
                <a href="<?php echo APP_URL; ?>modules/projects/material_requirements.php" class="nav-item <?php echo $current_module === 'projects' && strpos($current_page, 'material_requirements') !== false ? 'active' : ''; ?>">
                    <i class="fas fa-boxes"></i>
                    <span>Material Requirements</span>
                </a>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- PROCUREMENT DEPARTMENT -->
        <?php if ($show_procurement): ?>
        <div class="nav-section collapsible <?php echo in_array('procurement', $expanded_sections) ? 'expanded' : ''; ?>">
            <div class="nav-section-header" onclick="toggleSection(this)">
                <i class="fas fa-shopping-cart"></i>
                <span>Procurement</span>
                <i class="fas fa-chevron-<?php echo in_array('procurement', $expanded_sections) ? 'down' : 'right'; ?> arrow"></i>
            </div>
            <div class="nav-section-content">
                <a href="<?php echo APP_URL; ?>modules/procurement/dashboard.php" class="nav-item <?php echo $current_module === 'procurement' && $current_page === 'dashboard.php' ? 'active' : ''; ?>">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
                <a href="<?php echo APP_URL; ?>modules/procurement/purchase_requests.php" class="nav-item <?php echo $current_module === 'procurement' && strpos($current_page, 'purchase_requests') !== false ? 'active' : ''; ?>">
                    <i class="fas fa-file-invoice"></i>
                    <span>Purchase Requests</span>
                </a>
                <a href="<?php echo APP_URL; ?>modules/procurement/purchase_orders.php" class="nav-item <?php echo $current_module === 'procurement' && strpos($current_page, 'purchase_orders') !== false ? 'active' : ''; ?>">
                    <i class="fas fa-shopping-cart"></i>
                    <span>Purchase Orders</span>
                </a>
                <a href="<?php echo APP_URL; ?>modules/procurement/purchase_history.php" class="nav-item <?php echo $current_module === 'procurement' && strpos($current_page, 'purchase_history') !== false ? 'active' : ''; ?>">
                    <i class="fas fa-history"></i>
                    <span>Purchase History</span>
                </a>
                <a href="<?php echo APP_URL; ?>modules/procurement/purchased_materials.php" class="nav-item <?php echo $current_module === 'procurement' && strpos($current_page, 'purchased_materials') !== false ? 'active' : ''; ?>">
                    <i class="fas fa-boxes"></i>
                    <span>Purchased Materials</span>
                </a>
                <a href="<?php echo APP_URL; ?>modules/procurement/quotations.php" class="nav-item <?php echo $current_module === 'procurement' && strpos($current_page, 'quotations') !== false ? 'active' : ''; ?>">
                    <i class="fas fa-file-signature"></i>
                    <span>Quotations</span>
                </a>
                <a href="<?php echo APP_URL; ?>modules/procurement/suppliers.php" class="nav-item <?php echo $current_module === 'procurement' && strpos($current_page, 'suppliers') !== false ? 'active' : ''; ?>">
                    <i class="fas fa-truck"></i>
                    <span>Suppliers</span>
                </a>
                <a href="<?php echo APP_URL; ?>modules/procurement/materials.php" class="nav-item <?php echo $current_module === 'procurement' && strpos($current_page, 'materials') !== false ? 'active' : ''; ?>">
                    <i class="fas fa-cubes"></i>
                    <span>Materials</span>
                </a>
                <a href="<?php echo APP_URL; ?>modules/procurement/reports.php" class="nav-item <?php echo $current_module === 'procurement' && strpos($current_page, 'reports') !== false ? 'active' : ''; ?>">
                    <i class="fas fa-chart-bar"></i>
                    <span>Reports</span>
                </a>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- ACCOUNTING DEPARTMENT -->
        <?php if ($show_accounting): ?>
        <div class="nav-section collapsible <?php echo in_array('accounting', $expanded_sections) ? 'expanded' : ''; ?>">
            <div class="nav-section-header" onclick="toggleSection(this)">
                <i class="fas fa-calculator"></i>
                <span>Accounting</span>
                <i class="fas fa-chevron-<?php echo in_array('accounting', $expanded_sections) ? 'down' : 'right'; ?> arrow"></i>
            </div>
            <div class="nav-section-content">
                <a href="<?php echo APP_URL; ?>modules/accounting/dashboard.php" class="nav-item <?php echo $current_module === 'accounting' && $current_page === 'dashboard.php' ? 'active' : ''; ?>">
                    <i class="fas fa-chart-line"></i>
                    <span>Financial Dashboard</span>
                </a>
                <a href="<?php echo APP_URL; ?>modules/accounting/project_financials.php" class="nav-item <?php echo $current_module === 'accounting' && strpos($current_page, 'project_financials') !== false ? 'active' : ''; ?>">
                    <i class="fas fa-project-diagram"></i>
                    <span>Project Financials</span>
                </a>
                <a href="<?php echo APP_URL; ?>modules/accounting/invoices.php" class="nav-item <?php echo $current_module === 'accounting' && strpos($current_page, 'invoices') !== false ? 'active' : ''; ?>">
                    <i class="fas fa-file-invoice-dollar"></i>
                    <span>Invoices</span>
                </a>
                <a href="<?php echo APP_URL; ?>modules/accounting/expenses.php" class="nav-item <?php echo $current_module === 'accounting' && strpos($current_page, 'expenses') !== false ? 'active' : ''; ?>">
                    <i class="fas fa-coins"></i>
                    <span>Expenses</span>
                </a>
                <a href="<?php echo APP_URL; ?>modules/accounting/payments.php" class="nav-item <?php echo $current_module === 'accounting' && strpos($current_page, 'payments') !== false ? 'active' : ''; ?>">
                    <i class="fas fa-credit-card"></i>
                    <span>Payments</span>
                </a>
                <a href="<?php echo APP_URL; ?>modules/accounting/funds_budget.php" class="nav-item <?php echo $current_module === 'accounting' && strpos($current_page, 'funds_budget') !== false ? 'active' : ''; ?>">
                    <i class="fas fa-wallet"></i>
                    <span>Funds & Budget</span>
                </a>
                <a href="<?php echo APP_URL; ?>modules/accounting/contracts.php" class="nav-item <?php echo $current_module === 'accounting' && strpos($current_page, 'contracts') !== false ? 'active' : ''; ?>">
                    <i class="fas fa-file-contract"></i>
                    <span>Contracts</span>
                </a>
                <a href="<?php echo APP_URL; ?>modules/accounting/labor_budget.php" class="nav-item <?php echo $current_module === 'accounting' && strpos($current_page, 'labor_budget') !== false ? 'active' : ''; ?>">
                    <i class="fas fa-users"></i>
                    <span>Labor/Salary Budget</span>
                </a>
                <a href="<?php echo APP_URL; ?>modules/accounting/purchase_costs.php" class="nav-item <?php echo $current_module === 'accounting' && strpos($current_page, 'purchase_costs') !== false ? 'active' : ''; ?>">
                    <i class="fas fa-shopping-cart"></i>
                    <span>Purchase Costs</span>
                </a>
                <?php if ($show_payroll): ?>
                <a href="<?php echo APP_URL; ?>modules/accounting/payroll.php" class="nav-item <?php echo $current_module === 'accounting' && $current_page === 'payroll.php' ? 'active' : ''; ?>">
                    <i class="fas fa-users-cog"></i>
                    <span>Payroll</span>
                </a>
                <a href="<?php echo APP_URL; ?>modules/accounting/payslips.php" class="nav-item <?php echo $current_module === 'accounting' && strpos($current_page, 'payslips') !== false ? 'active' : ''; ?>">
                    <i class="fas fa-file-alt"></i>
                    <span>Payslips</span>
                </a>
                <?php endif; ?>
                <a href="<?php echo APP_URL; ?>modules/accounting/financial_reports.php" class="nav-item <?php echo $current_module === 'accounting' && strpos($current_page, 'financial_reports') !== false ? 'active' : ''; ?>">
                    <i class="fas fa-chart-bar"></i>
                    <span>Financial Reports</span>
                </a>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- WAREHOUSE DEPARTMENT -->
        <?php if ($show_warehouse): ?>
        <div class="nav-section collapsible <?php echo in_array('warehouse', $expanded_sections) ? 'expanded' : ''; ?>">
            <div class="nav-section-header" onclick="toggleSection(this)">
                <i class="fas fa-warehouse"></i>
                <span>Warehouse</span>
                <i class="fas fa-chevron-<?php echo in_array('warehouse', $expanded_sections) ? 'down' : 'right'; ?> arrow"></i>
            </div>
            <div class="nav-section-content">
                <a href="<?php echo APP_URL; ?>modules/warehouse/dashboard.php" class="nav-item <?php echo $current_module === 'warehouse' && $current_page === 'dashboard.php' ? 'active' : ''; ?>">
                    <i class="fas fa-chart-pie"></i>
                    <span>Dashboard</span>
                </a>
                <a href="<?php echo APP_URL; ?>modules/warehouse/stock.php" class="nav-item <?php echo $current_module === 'warehouse' && strpos($current_page, 'stock') !== false ? 'active' : ''; ?>">
                    <i class="fas fa-warehouse"></i>
                    <span>Stock Management</span>
                </a>
                <a href="<?php echo APP_URL; ?>modules/warehouse/material_requests.php" class="nav-item <?php echo $current_module === 'warehouse' && strpos($current_page, 'material_requests') !== false ? 'active' : ''; ?>">
                    <i class="fas fa-clipboard-list"></i>
                    <span>Material Requests</span>
                </a>
                <a href="<?php echo APP_URL; ?>modules/warehouse/project_materials.php" class="nav-item <?php echo $current_module === 'warehouse' && strpos($current_page, 'project_materials') !== false ? 'active' : ''; ?>">
                    <i class="fas fa-project-diagram"></i>
                    <span>Project Materials</span>
                </a>
                <a href="<?php echo APP_URL; ?>modules/warehouse/inventory_history.php" class="nav-item <?php echo $current_module === 'warehouse' && strpos($current_page, 'inventory_history') !== false ? 'active' : ''; ?>">
                    <i class="fas fa-history"></i>
                    <span>Inventory History</span>
                </a>
                <a href="<?php echo APP_URL; ?>modules/warehouse/movements.php" class="nav-item <?php echo $current_module === 'warehouse' && strpos($current_page, 'movements') !== false ? 'active' : ''; ?>">
                    <i class="fas fa-exchange-alt"></i>
                    <span>Stock Movements</span>
                </a>
                <a href="<?php echo APP_URL; ?>modules/warehouse/inventory_reports.php" class="nav-item <?php echo $current_module === 'warehouse' && strpos($current_page, 'inventory_reports') !== false ? 'active' : ''; ?>">
                    <i class="fas fa-chart-bar"></i>
                    <span>Inventory Reports</span>
                </a>
                <?php if ($show_materials): ?>
                <a href="<?php echo APP_URL; ?>modules/procurement/materials.php" class="nav-item <?php echo $current_module === 'procurement' && strpos($current_page, 'materials') !== false ? 'active' : ''; ?>">
                    <i class="fas fa-cubes"></i>
                    <span>Materials</span>
                </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- ADMINISTRATION / EXECUTIVE ADMIN -->
        <?php if ($is_admin): ?>
        <div class="nav-section collapsible <?php echo in_array('administration', $expanded_sections) ? 'expanded' : ''; ?>">
            <div class="nav-section-header" onclick="toggleSection(this)">
                <i class="fas fa-crown"></i>
                <span>Executive Admin</span>
                <i class="fas fa-chevron-<?php echo in_array('administration', $expanded_sections) ? 'down' : 'right'; ?> arrow"></i>
            </div>
            <div class="nav-section-content">
                <a href="<?php echo APP_URL; ?>index.php" class="nav-item <?php echo $current_page === 'index.php' ? 'active' : ''; ?>">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
                <a href="<?php echo APP_URL; ?>modules/projects/projects.php" class="nav-item <?php echo $current_module === 'projects' && strpos($current_page, 'projects') !== false ? 'active' : ''; ?>">
                    <i class="fas fa-project-diagram"></i>
                    <span>Projects Overview</span>
                </a>
                <a href="<?php echo APP_URL; ?>modules/procurement/dashboard.php" class="nav-item <?php echo $current_module === 'procurement' && $current_page === 'dashboard.php' ? 'active' : ''; ?>">
                    <i class="fas fa-shopping-cart"></i>
                    <span>Procurement Monitoring</span>
                </a>
                <a href="<?php echo APP_URL; ?>modules/accounting/dashboard.php" class="nav-item <?php echo $current_module === 'accounting' && $current_page === 'dashboard.php' ? 'active' : ''; ?>">
                    <i class="fas fa-chart-line"></i>
                    <span>Accounting Monitoring</span>
                </a>
                <a href="<?php echo APP_URL; ?>modules/warehouse/dashboard.php" class="nav-item <?php echo $current_module === 'warehouse' && $current_page === 'dashboard.php' ? 'active' : ''; ?>">
                    <i class="fas fa-warehouse"></i>
                    <span>Warehouse Monitoring</span>
                </a>
                <a href="<?php echo APP_URL; ?>modules/admin/consolidated_reports.php" class="nav-item <?php echo $current_module === 'admin' && strpos($current_page, 'consolidated_reports') !== false ? 'active' : ''; ?>">
                    <i class="fas fa-chart-bar"></i>
                    <span>Consolidated Reports</span>
                </a>
                <a href="<?php echo APP_URL; ?>modules/admin/department_monitoring.php" class="nav-item <?php echo $current_module === 'admin' && strpos($current_page, 'department_monitoring') !== false ? 'active' : ''; ?>">
                    <i class="fas fa-history"></i>
                    <span>System Activity</span>
                </a>
                <a href="<?php echo APP_URL; ?>modules/admin/users.php" class="nav-item <?php echo $current_module === 'admin' && strpos($current_page, 'users') !== false ? 'active' : ''; ?>">
                    <i class="fas fa-users-cog"></i>
                    <span>User Management</span>
                </a>
                <a href="<?php echo APP_URL; ?>modules/admin/settings.php" class="nav-item <?php echo $current_module === 'admin' && strpos($current_page, 'settings') !== false ? 'active' : ''; ?>">
                    <i class="fas fa-cog"></i>
                    <span>System Settings</span>
                </a>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- ACCOUNT - Logout -->
        <div class="nav-section" style="margin-top: auto; border-top: 1px solid rgba(255,255,255,0.06); padding-top: 0.3rem;">
            <div class="nav-label">Account</div>
            <a href="<?php echo APP_URL; ?>logout.php" class="nav-item" style="color: #e74a3b;">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </div>
    </nav>
</div>

<!-- Styles - Original Colors Preserved -->
<style>
/* ============================================
   SIDEBAR - ORIGINAL COLORS WITH RESIZE
   ============================================ */

/* Sidebar - Original Dark Navy */
.sidebar {
    position: fixed;
    left: 0;
    top: 0;
    height: 100vh;
    background: linear-gradient(180deg, #1e2a3a 0%, #0f172a 100%);
    color: #fff;
    z-index: 1000;
    overflow-y: auto;
    overflow-x: hidden;
    transition: none;
    display: flex;
    flex-direction: column;
    scrollbar-width: thin;
    scrollbar-color: rgba(255,255,255,0.2) transparent;
    box-shadow: 2px 0 20px rgba(0,0,0,0.1);
}

.sidebar::-webkit-scrollbar {
    width: 4px;
}
.sidebar::-webkit-scrollbar-track {
    background: transparent;
}
.sidebar::-webkit-scrollbar-thumb {
    background: rgba(255,255,255,0.2);
    border-radius: 2px;
}

/* Resize Handle */
.sidebar-resize-handle {
    position: absolute;
    right: -6px;
    top: 50%;
    transform: translateY(-50%);
    width: 12px;
    height: 60px;
    cursor: col-resize;
    z-index: 1002;
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.sidebar:hover .sidebar-resize-handle {
    opacity: 1;
}

.sidebar-resize-handle .resize-dots {
    display: flex;
    flex-direction: column;
    gap: 4px;
    padding: 4px;
    background: rgba(255,255,255,0.1);
    border-radius: 4px;
}

.sidebar-resize-handle .resize-dots span {
    display: block;
    width: 3px;
    height: 3px;
    background: rgba(255,255,255,0.5);
    border-radius: 50%;
}

.sidebar-resize-handle:hover .resize-dots span,
.sidebar-resize-handle.active .resize-dots span {
    background: #fbbf24;
}

/* Brand - Original */
.sidebar-brand {
    padding: 1rem 1.4rem 0.9rem;
    border-bottom: 1px solid rgba(255,255,255,0.1);
    display: flex;
    align-items: center;
    gap: 12px;
}

.brand-logo-link {
    flex: 1;
    display: block;
    min-width: 0;
}

.brand-logo-full {
    width: 100%;
    max-width: 220px;
    height: auto;
    display: block;
    object-fit: contain;
    filter: drop-shadow(0 6px 16px rgba(0,0,0,0.25));
}

.sidebar-toggle {
    display: none;
    background: none;
    border: none;
    color: #fff;
    font-size: 1.2rem;
    cursor: pointer;
    padding: 4px;
    margin-left: auto;
}

/* Sidebar User Profile - Original */
.sidebar-user {
    padding: 0.8rem 1.8rem;
    border-bottom: 1px solid rgba(255,255,255,0.1);
    display: flex;
    align-items: center;
    gap: 12px;
}

.sidebar-user .avatar {
    width: 38px;
    height: 38px;
    border-radius: 50%;
    background: rgba(255,255,255,0.1);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    color: #facc15;
    flex-shrink: 0;
}

.sidebar-user .user-info .name {
    font-weight: 600;
    font-size: 0.9rem;
    color: #fff;
}

.sidebar-user .user-info .role {
    font-size: 0.65rem;
    color: rgba(255,255,255,0.5);
    display: flex;
    align-items: center;
    gap: 6px;
    flex-wrap: wrap;
}

.sidebar-user .user-info .role .badge {
    padding: 1px 8px;
    border-radius: 10px;
    font-size: 0.55rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}

.badge-admin { background: #e74a3b; color: #fff; }
.badge-manager { background: #f59e0b; color: #0f172a; }
.badge-staff { background: rgba(255,255,255,0.15); color: #fff; }

/* Navigation - Original */
.sidebar-nav {
    flex: 1;
    padding: 0.3rem 0 0.5rem;
    display: flex;
    flex-direction: column;
    overflow-y: auto;
}

.nav-section {
    margin-bottom: 0.1rem;
}

.nav-label {
    font-size: 0.6rem;
    text-transform: uppercase;
    letter-spacing: 0.8px;
    color: rgba(255,255,255,0.3);
    padding: 0.6rem 1.8rem 0.3rem;
    font-weight: 700;
}

/* Collapsible Section Header - Original */
.nav-section-header {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 0.45rem 1.8rem;
    color: rgba(255,255,255,0.7);
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 0.85rem;
    font-weight: 500;
    user-select: none;
}

.nav-section-header:hover {
    background: rgba(255,255,255,0.05);
    color: #fff;
}

.nav-section-header i:first-child {
    width: 20px;
    text-align: center;
    font-size: 0.9rem;
    color: rgba(255,255,255,0.4);
}

.nav-section-header .arrow {
    margin-left: auto;
    font-size: 0.65rem;
    transition: transform 0.3s ease;
    color: rgba(255,255,255,0.3);
}

.nav-section.expanded .nav-section-header .arrow {
    transform: rotate(0deg);
}

.nav-section:not(.expanded) .nav-section-header .arrow {
    transform: rotate(-90deg);
}

/* Collapsible Content */
.nav-section-content {
    overflow: hidden;
    max-height: 0;
    transition: max-height 0.3s ease;
    padding-left: 0.5rem;
}

.nav-section.expanded .nav-section-content {
    max-height: 500px;
}

.nav-section-content .nav-item {
    padding-left: 3.5rem;
    font-size: 0.78rem;
    padding-top: 0.3rem;
    padding-bottom: 0.3rem;
}

/* Nav Items - Original */
.nav-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 0.4rem 1.8rem;
    color: rgba(255,255,255,0.6);
    text-decoration: none;
    transition: all 0.3s ease;
    font-size: 0.85rem;
    border-left: 3px solid transparent;
    cursor: pointer;
}

.nav-item:hover {
    background: rgba(255,255,255,0.05);
    color: #fff;
}

.nav-item.active {
    background: rgba(251,191,36,0.12);
    color: #facc15;
    border-left-color: #fbbf24;
}

.nav-item i {
    width: 22px;
    text-align: center;
    font-size: 0.9rem;
    flex-shrink: 0;
}

.nav-item .nav-badge {
    margin-left: auto;
    background: #e74a3b;
    color: #fff;
    font-size: 0.55rem;
    padding: 1px 8px;
    border-radius: 10px;
    font-weight: 600;
}

.nav-item .nav-badge.warning {
    background: #f59e0b;
    color: #0f172a;
}

/* Push logout to bottom */
.nav-section:last-of-type {
    margin-top: auto;
    border-top: 1px solid rgba(255,255,255,0.06);
    padding-top: 0.3rem;
}

/* Mobile menu toggle */
.menu-toggle {
    display: none;
    position: fixed;
    top: 0.8rem;
    left: 0.8rem;
    z-index: 1001;
    background: #1a1a2e;
    color: #fff;
    border: none;
    border-radius: 8px;
    padding: 0.5rem 0.8rem;
    font-size: 1.1rem;
    cursor: pointer;
    box-shadow: 0 4px 12px rgba(0,0,0,0.2);
}

/* Main content margin */
.main-content {
    margin-left: <?php echo $sidebar_width; ?>px;
    transition: margin-left 0.3s ease;
}

@media (max-width: 768px) {
    .menu-toggle {
        display: block;
    }
    .sidebar {
        transform: translateX(-100%);
        width: 280px !important;
    }
    .sidebar.open {
        transform: translateX(0);
    }
    .sidebar-toggle {
        display: block;
    }
    .sidebar-brand .brand-text .runeha {
        font-size: 15px;
    }
    .sidebar-user .user-info .name {
        font-size: 0.8rem;
    }
    .main-content {
        margin-left: 0 !important;
    }
    .sidebar-resize-handle {
        display: none;
    }
}

@media (min-width: 769px) {
    .sidebar-toggle {
        display: none !important;
    }
}
</style>

<script>
// ============================================
// SIDEBAR RESIZE FUNCTIONALITY
// ============================================
(function() {
    const sidebar = document.getElementById('sidebar');
    const resizeHandle = document.getElementById('sidebarResizeHandle');
    const mainContent = document.querySelector('.main-content');
    let isResizing = false;
    let startX = 0;
    let startWidth = 0;

    if (resizeHandle) {
        resizeHandle.addEventListener('mousedown', function(e) {
            isResizing = true;
            startX = e.clientX;
            startWidth = sidebar.offsetWidth;
            document.body.style.cursor = 'col-resize';
            document.body.style.userSelect = 'none';
            resizeHandle.classList.add('active');
            e.preventDefault();
        });
    }

    document.addEventListener('mousemove', function(e) {
        if (!isResizing) return;
        
        let newWidth = startWidth + (e.clientX - startX);
        if (newWidth < 200) newWidth = 200;
        if (newWidth > 400) newWidth = 400;
        
        sidebar.style.width = newWidth + 'px';
        if (mainContent) {
            mainContent.style.marginLeft = newWidth + 'px';
        }
    });

    document.addEventListener('mouseup', function() {
        if (isResizing) {
            isResizing = false;
            document.body.style.cursor = '';
            document.body.style.userSelect = '';
            resizeHandle.classList.remove('active');
            
            const newWidth = sidebar.offsetWidth;
            document.cookie = 'sidebar_width=' + newWidth + '; path=/; max-age=31536000';
        }
    });

    document.addEventListener('selectstart', function(e) {
        if (isResizing) {
            e.preventDefault();
        }
    });
})();

// ============================================
// COLLAPSIBLE SECTIONS
// ============================================
function toggleSection(header) {
    const section = header.closest('.nav-section');
    if (section) {
        section.classList.toggle('expanded');
    }
}

// ============================================
// SIDEBAR TOGGLE (Mobile)
// ============================================
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('open');
}

// Close sidebar on mobile when clicking a link
document.querySelectorAll('.nav-item').forEach(link => {
    link.addEventListener('click', () => {
        if (window.innerWidth <= 768) {
            document.getElementById('sidebar').classList.remove('open');
        }
    });
});

// Close sidebar on outside click (mobile)
document.addEventListener('click', function(e) {
    const sidebar = document.getElementById('sidebar');
    const toggle = document.querySelector('.menu-toggle');
    if (window.innerWidth <= 768 && sidebar && sidebar.classList.contains('open')) {
        if (!sidebar.contains(e.target) && !toggle?.contains(e.target)) {
            sidebar.classList.remove('open');
        }
    }
});

// Auto-expand section based on current page
document.addEventListener('DOMContentLoaded', function() {
    const sections = document.querySelectorAll('.nav-section.collapsible');
    sections.forEach(section => {
        const links = section.querySelectorAll('.nav-item');
        links.forEach(link => {
            if (link.classList.contains('active')) {
                section.classList.add('expanded');
            }
        });
    });
});
</script>