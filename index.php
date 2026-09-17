<?php
require_once 'config/database.php';
require_once 'includes/auth.php';
require_once 'includes/stock_alerts.php';
requireLogin();

$user = getCurrentUser();
$department = $_SESSION['department'] ?? 'admin';
$role = $_SESSION['role'] ?? 'staff';
$is_manager = isManager();
$is_admin = isAdmin();

$show_projects = canViewModule('projects');
$show_procurement = canViewModule('procurement');
$show_accounting = canViewModule('accounting');
$show_warehouse = canViewModule('warehouse');
$show_payroll = canViewModule('payroll');

$stats = [];
$recent_activity = [];

try {
    if ($show_projects) {
        $stats['ongoing_projects'] = $pdo->query("SELECT COUNT(*) FROM projects WHERE status = 'ongoing'")->fetchColumn() ?? 0;
        $stats['completed_projects'] = $pdo->query("SELECT COUNT(*) FROM projects WHERE status = 'completed'")->fetchColumn() ?? 0;
        $stats['projects'] = $pdo->query("SELECT COUNT(*) FROM projects WHERE status != 'completed'")->fetchColumn() ?? 0;
    }

    if ($show_procurement) {
        $stats['purchase_requests'] = $pdo->query("SELECT COUNT(*) FROM purchase_requests WHERE status IN ('pending', 'approved')")->fetchColumn() ?? 0;
        $stats['purchase_orders'] = $pdo->query("SELECT COUNT(*) FROM purchase_orders WHERE status IN ('draft', 'sent', 'confirmed')")->fetchColumn() ?? 0;
        $stats['quotations'] = $pdo->query("SELECT COUNT(*) FROM quotations WHERE status = 'received'")->fetchColumn() ?? 0;
    }

    if ($show_accounting) {
        $stats['pending_invoices'] = $pdo->query("SELECT COUNT(*) FROM invoices WHERE status IN ('sent', 'partial')")->fetchColumn() ?? 0;
        $stats['total_receivables'] = $pdo->query("SELECT COALESCE(SUM(amount - paid_amount), 0) FROM invoices WHERE status IN ('sent', 'partial')")->fetchColumn() ?? 0;
        if ($show_payroll) {
            $stats['pending_payroll'] = $pdo->query("SELECT COUNT(*) FROM payroll WHERE status IN ('draft', 'approved')")->fetchColumn() ?? 0;
        }
    }

    if ($show_warehouse) {
        $stats['total_items'] = $pdo->query("SELECT COUNT(*) FROM materials WHERE status = 'active'")->fetchColumn() ?? 0;
        $stats['low_stock'] = $pdo->query("SELECT COUNT(*) FROM materials WHERE current_stock <= min_stock AND status = 'active'")->fetchColumn() ?? 0;
        $stats['materials'] = $stats['total_items'];
    }

    $activity_sql = "
        SELECT al.*, u.full_name
        FROM activity_log al
        LEFT JOIN users u ON al.user_id = u.id
    ";

    if ($is_admin) {
        $activity_sql .= " ORDER BY al.created_at DESC LIMIT 10";
        $recent_activity = $pdo->query($activity_sql)->fetchAll();
    } else {
        $module_filter = [
            'projects' => ['Projects', 'Security'],
            'procurement' => ['Procurement', 'Security'],
            'accounting' => ['Accounting', 'Security'],
            'warehouse' => ['Warehouse', 'Security'],
        ];
        $modules = $module_filter[$department] ?? ['Security'];
        $placeholders = implode(',', array_fill(0, count($modules), '?'));
        $stmt = $pdo->prepare($activity_sql . " WHERE al.module IN ($placeholders) OR al.user_id = ? ORDER BY al.created_at DESC LIMIT 10");
        $stmt->execute(array_merge($modules, [$_SESSION['user_id']]));
        $recent_activity = $stmt->fetchAll();
    }

} catch(PDOException $e) {
    $stats = [];
    $recent_activity = [];
}

$page_title = 'Dashboard - ' . APP_NAME;
include 'includes/header.php';
?>

<div class="page-header">
    <h1><i class="fas fa-tachometer-alt"></i> Dashboard</h1>
    <div class="dept-badge">
        <i class="fas <?php echo getDepartmentIcon($department); ?>"></i>
        <?php echo getDepartmentName($department); ?>
        <?php if ($is_manager): ?>
            <span class="role-chip">MANAGER</span>
        <?php endif; ?>
    </div>
</div>

<?php if (isset($_SESSION['error'])): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
<?php endif; ?>
<?php if (isset($_SESSION['success'])): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
<?php endif; ?>

<div class="card welcome-card">
    <h2>Welcome back, <?php echo htmlspecialchars($_SESSION['full_name'] ?? 'User'); ?>!</h2>
    <p>
        <?php if ($show_projects && !$is_admin): ?>
            Your project workspace is ready.
        <?php elseif ($show_procurement && !$is_admin): ?>
            Here's the latest on purchase requests and orders.
        <?php elseif ($show_accounting && !$is_admin): ?>
            Here's a snapshot of invoices, payments, and payroll.
        <?php elseif ($show_warehouse && !$is_admin): ?>
            Here's the current warehouse stock position.
        <?php else: ?>
            Here's an overview across all departments.
        <?php endif; ?>
    </p>
</div>

<div class="quick-actions">
    <?php if ($show_procurement): ?>
    <a href="<?php echo APP_URL; ?>modules/procurement/purchase_requests.php?action=add" class="quick-action accent-procurement">
        <i class="fas fa-file-invoice"></i>
        <span>New Purchase Request</span>
    </a>
    <a href="<?php echo APP_URL; ?>modules/procurement/purchase_orders.php?action=add" class="quick-action accent-procurement">
        <i class="fas fa-shopping-cart"></i>
        <span>New Purchase Order</span>
    </a>
    <?php endif; ?>

    <?php if ($show_projects): ?>
    <a href="<?php echo APP_URL; ?>modules/projects/dashboard.php" class="quick-action accent-projects">
        <i class="fas fa-chart-bar"></i>
        <span>Projects Dashboard</span>
    </a>
    <a href="<?php echo APP_URL; ?>modules/projects/projects.php?action=add" class="quick-action accent-projects">
        <i class="fas fa-building"></i>
        <span>New Project</span>
    </a>
    <?php endif; ?>

    <?php if ($show_accounting): ?>
    <a href="<?php echo APP_URL; ?>modules/accounting/invoices.php?action=add" class="quick-action accent-accounting">
        <i class="fas fa-file-invoice-dollar"></i>
        <span>New Invoice</span>
    </a>
    <?php if ($show_payroll): ?>
    <a href="<?php echo APP_URL; ?>modules/accounting/payroll.php?action=add" class="quick-action accent-accounting">
        <i class="fas fa-users"></i>
        <span>New Payroll</span>
    </a>
    <?php endif; ?>
    <?php endif; ?>

    <?php if ($show_warehouse): ?>
    <a href="<?php echo APP_URL; ?>modules/warehouse/stock.php?action=add" class="quick-action accent-warehouse">
        <i class="fas fa-warehouse"></i>
        <span>Receive Stock</span>
    </a>
    <?php endif; ?>

</div>

<?php renderLowStockBanner(APP_URL . 'modules/warehouse/inventory.php'); ?>

<div class="stats-grid">
    <?php if ($show_procurement): ?>
    <div class="stat-card accent-procurement">
        <div class="stat-icon"><i class="fas fa-file-invoice"></i></div>
        <div class="stat-value"><?php echo $stats['purchase_requests'] ?? 0; ?></div>
        <div class="stat-label">Pending Purchase Requests</div>
    </div>
    <div class="stat-card accent-procurement">
        <div class="stat-icon"><i class="fas fa-shopping-cart"></i></div>
        <div class="stat-value"><?php echo $stats['purchase_orders'] ?? 0; ?></div>
        <div class="stat-label">Active Purchase Orders</div>
    </div>
    <?php endif; ?>

    <?php if ($show_projects): ?>
    <div class="stat-card accent-projects">
        <div class="stat-icon"><i class="fas fa-play-circle"></i></div>
        <div class="stat-value"><?php echo $stats['ongoing_projects'] ?? 0; ?></div>
        <div class="stat-label">Ongoing Projects</div>
    </div>
    <div class="stat-card accent-projects">
        <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
        <div class="stat-value"><?php echo $stats['completed_projects'] ?? 0; ?></div>
        <div class="stat-label">Completed Projects</div>
    </div>
    <?php endif; ?>

    <?php if ($show_accounting): ?>
    <div class="stat-card accent-accounting">
        <div class="stat-icon"><i class="fas fa-file-invoice-dollar"></i></div>
        <div class="stat-value"><?php echo $stats['pending_invoices'] ?? 0; ?></div>
        <div class="stat-label">Pending Invoices</div>
    </div>
    <div class="stat-card accent-accounting">
        <div class="stat-icon"><i class="fas fa-money-bill-wave"></i></div>
        <div class="stat-value">₱<?php echo number_format($stats['total_receivables'] ?? 0, 2); ?></div>
        <div class="stat-label">Total Receivables</div>
    </div>
    <?php if ($show_payroll): ?>
    <div class="stat-card accent-warning">
        <div class="stat-icon"><i class="fas fa-users"></i></div>
        <div class="stat-value"><?php echo $stats['pending_payroll'] ?? 0; ?></div>
        <div class="stat-label">Pending Payroll</div>
    </div>
    <?php endif; ?>
    <?php endif; ?>

    <?php if ($show_warehouse): ?>
    <div class="stat-card accent-warehouse">
        <div class="stat-icon"><i class="fas fa-boxes"></i></div>
        <div class="stat-value"><?php echo $stats['total_items'] ?? 0; ?></div>
        <div class="stat-label">Items in Catalog</div>
    </div>
    <div class="stat-card accent-warning">
        <div class="stat-icon"><i class="fas fa-exclamation-triangle"></i></div>
        <div class="stat-value"><?php echo $stats['low_stock'] ?? 0; ?></div>
        <div class="stat-label">Low Stock Items</div>
    </div>
    <?php endif; ?>
</div>

<div class="card">
    <h3 class="section-title"><i class="fas fa-clock"></i> Recent Activity</h3>
    <?php if (empty($recent_activity)): ?>
        <p class="empty-copy">No recent activity</p>
    <?php else: ?>
        <div class="activity-list">
        <?php foreach ($recent_activity as $activity): ?>
        <div class="activity-item">
            <div>
                <span class="activity-action"><?php echo htmlspecialchars($activity['action']); ?></span>
                <span class="activity-module"> — <?php echo htmlspecialchars($activity['module']); ?></span>
                <?php if ($activity['details']): ?>
                    <div class="activity-details"><?php echo htmlspecialchars($activity['details']); ?></div>
                <?php endif; ?>
            </div>
            <div class="activity-meta">
                <?php echo htmlspecialchars($activity['full_name'] ?? 'System'); ?>
                <span>•</span>
                <?php echo date('M d, Y h:i A', strtotime($activity['created_at'])); ?>
            </div>
        </div>
        <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
