<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';

requireAdmin();

$page_title = 'Executive Dashboard';

// Get consolidated statistics
$stats = [];
$department_summaries = [];
$items_needing_attention = [];
$recent_accomplishments = [];
$recent_department_activity = [];
try {
    // Project Statistics
    $stats['active_projects'] = $pdo->query("SELECT COUNT(*) FROM projects WHERE status IN ('planning', 'ongoing')")->fetchColumn() ?? 0;
    $stats['total_project_amount'] = $pdo->query("SELECT COALESCE(SUM(estimated_budget), 0) FROM projects")->fetchColumn() ?? 0;
    $stats['total_project_expenses'] = $pdo->query("SELECT COALESCE(SUM(actual_cost), 0) FROM projects")->fetchColumn() ?? 0;
    
    // Financial Statistics - with table existence check
    try {
        $stats['available_funds'] = $pdo->query("SELECT COALESCE(SUM(amount - allocated_amount), 0) FROM funds WHERE status = 'active'")->fetchColumn() ?? 0;
    } catch (PDOException $e) {
        $stats['available_funds'] = 0;
    }
    
    $stats['pending_purchases'] = $pdo->query("SELECT COUNT(*) FROM purchase_requests WHERE status IN ('pending', 'approved')")->fetchColumn() ?? 0;
    
    // Warehouse Statistics
    $stats['inventory_items'] = $pdo->query("SELECT COUNT(*) FROM materials WHERE status = 'active'")->fetchColumn() ?? 0;
    $stats['low_stock_items'] = $pdo->query("SELECT COUNT(*) FROM materials WHERE current_stock <= min_stock AND min_stock > 0 AND status = 'active'")->fetchColumn() ?? 0;
    
    // Department Summaries
    $department_summaries['procurement'] = [
        'name' => 'Procurement',
        'icon' => 'shopping-cart',
        'color' => '#f59e0b',
        'total_prs' => $pdo->query("SELECT COUNT(*) FROM purchase_requests")->fetchColumn() ?? 0,
        'pending_prs' => $pdo->query("SELECT COUNT(*) FROM purchase_requests WHERE status = 'pending'")->fetchColumn() ?? 0,
        'total_value' => $pdo->query("SELECT COALESCE(SUM(total_amount), 0) FROM purchase_requests WHERE status IN ('approved', 'confirmed', 'ordered', 'received')")->fetchColumn() ?? 0
    ];
    
    $department_summaries['accounting'] = [
        'name' => 'Accounting',
        'icon' => 'chart-line',
        'color' => '#36b9cc',
        'total_invoices' => $pdo->query("SELECT COUNT(*) FROM invoices")->fetchColumn() ?? 0,
        'total_expenses' => $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE status = 'paid'")->fetchColumn() ?? 0,
        'funds_count' => 0
    ];
    // Get funds count with error handling
    try {
        $result = $pdo->query("SELECT COUNT(*) FROM funds WHERE status = 'active'");
        if ($result) {
            $department_summaries['accounting']['funds_count'] = $result->fetchColumn() ?? 0;
        }
    } catch (PDOException $e) {
        // funds table may not exist, keep default 0
    }
    
    $department_summaries['engineering'] = [
        'name' => 'Engineering',
        'icon' => 'hard-hat',
        'color' => '#4e73df',
        'total_projects' => $pdo->query("SELECT COUNT(*) FROM projects")->fetchColumn() ?? 0,
        'ongoing_projects' => $pdo->query("SELECT COUNT(*) FROM projects WHERE status = 'ongoing'")->fetchColumn() ?? 0,
        'accomplishments' => 0
    ];
    // Get accomplishments count with error handling
    try {
        $result = $pdo->query("SELECT COUNT(*) FROM accomplishments");
        if ($result) {
            $department_summaries['engineering']['accomplishments'] = $result->fetchColumn() ?? 0;
        }
    } catch (PDOException $e) {
        // accomplishments table may not exist, keep default 0
    }
    
    $department_summaries['warehouse'] = [
        'name' => 'Warehouse',
        'icon' => 'warehouse',
        'color' => '#1cc88a',
        'total_stock' => $pdo->query("SELECT COALESCE(SUM(current_stock), 0) FROM materials WHERE status = 'active'")->fetchColumn() ?? 0,
        'stock_movements' => $pdo->query("SELECT COUNT(*) FROM stock_movements WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)")->fetchColumn() ?? 0,
        'material_requests' => 0
    ];
    // Get material requests count with error handling
    try {
        $result = $pdo->query("SELECT COUNT(*) FROM material_requests WHERE status = 'pending'");
        if ($result) {
            $department_summaries['warehouse']['material_requests'] = $result->fetchColumn() ?? 0;
        }
    } catch (PDOException $e) {
        // material_requests table may not exist, keep default 0
    }
    
    // Items Needing Attention
    $items_needing_attention['low_stock'] = $pdo->query("SELECT COUNT(*) FROM materials WHERE current_stock <= min_stock AND min_stock > 0 AND status = 'active'")->fetchColumn() ?? 0;
    $items_needing_attention['pending_prs'] = $pdo->query("SELECT COUNT(*) FROM purchase_requests WHERE status = 'pending'")->fetchColumn() ?? 0;
    $items_needing_attention['pending_material_requests'] = 0;
    try {
        $result = $pdo->query("SELECT COUNT(*) FROM material_requests WHERE status = 'pending'");
        if ($result) {
            $items_needing_attention['pending_material_requests'] = $result->fetchColumn() ?? 0;
        }
    } catch (PDOException $e) {
        // material_requests table may not exist, keep default 0
    }
    $items_needing_attention['overdue_projects'] = $pdo->query("SELECT COUNT(*) FROM projects WHERE end_date < CURDATE() AND status IN ('planning', 'ongoing')")->fetchColumn() ?? 0;
    
    // Recent Accomplishments
    try {
        $recent_accomplishments = $pdo->query("
            SELECT a.*, p.project_code, p.name as project_name, u.full_name as created_by_name
            FROM accomplishments a
            LEFT JOIN projects p ON a.project_id = p.id
            LEFT JOIN users u ON a.created_by = u.id
            ORDER BY a.created_at DESC
            LIMIT 5
        ")->fetchAll();
    } catch (PDOException $e) {
        $recent_accomplishments = [];
    }
    
    // Recent Department Activity
    $recent_department_activity = $pdo->query("
        SELECT al.*, u.full_name, u.department
        FROM activity_log al
        LEFT JOIN users u ON al.user_id = u.id
        ORDER BY al.created_at DESC
        LIMIT 15
    ")->fetchAll();
    
} catch (PDOException $e) {
    $error = userDatabaseError($e);
}

include '../../includes/header.php';
?>

<style>
.executive-dashboard {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 1.5rem;
    margin-bottom: 1.5rem;
}
.department-card {
    background: white;
    padding: 1.5rem;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    border-top: 4px solid #4e73df;
}
.department-card .dept-icon {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: white;
    margin-bottom: 1rem;
}
.attention-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem;
    background: #fff5f5;
    border-left: 3px solid #e74a3b;
    border-radius: 4px;
    margin-bottom: 0.5rem;
}
.attention-item i {
    color: #e74a3b;
}
.overview-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 1.5rem;
}
.overview-stats .card {
    border-left: 4px solid #4e73df;
}
</style>

<div class="page-header">
    <h1><i class="fas fa-crown"></i> Executive Dashboard</h1>
    <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
        <a href="consolidated_reports.php" class="btn btn-primary"><i class="fas fa-chart-bar"></i> Consolidated Reports</a>
        <a href="department_monitoring.php" class="btn btn-info"><i class="fas fa-eye"></i> Department Monitoring</a>
    </div>
</div>

<?php if (isset($error)): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<!-- Executive Overview Stats -->
<div class="overview-stats">
    <div class="card" style="border-left-color:#4e73df;margin:0;">
        <div style="font-size:0.75rem;color:#64748b;">Active Projects</div>
        <div style="font-size:1.8rem;font-weight:700;"><?php echo number_format($stats['active_projects'] ?? 0); ?></div>
    </div>
    <div class="card" style="border-left-color:#f6c23e;margin:0;">
        <div style="font-size:0.75rem;color:#64748b;">Total Project Amount</div>
        <div style="font-size:1.8rem;font-weight:700;">₱<?php echo number_format($stats['total_project_amount'] ?? 0, 2); ?></div>
    </div>
    <div class="card" style="border-left-color:#e74a3b;margin:0;">
        <div style="font-size:0.75rem;color:#64748b;">Total Project Expenses</div>
        <div style="font-size:1.8rem;font-weight:700;">₱<?php echo number_format($stats['total_project_expenses'] ?? 0, 2); ?></div>
    </div>
    <div class="card" style="border-left-color:#1cc88a;margin:0;">
        <div style="font-size:0.75rem;color:#64748b;">Available Funds</div>
        <div style="font-size:1.8rem;font-weight:700;">₱<?php echo number_format($stats['available_funds'] ?? 0, 2); ?></div>
    </div>
    <div class="card" style="border-left-color:#36b9cc;margin:0;">
        <div style="font-size:0.75rem;color:#64748b;">Pending Purchases</div>
        <div style="font-size:1.8rem;font-weight:700;"><?php echo number_format($stats['pending_purchases'] ?? 0); ?></div>
    </div>
    <div class="card" style="border-left-color:#858796;margin:0;">
        <div style="font-size:0.75rem;color:#64748b;">Inventory Items</div>
        <div style="font-size:1.8rem;font-weight:700;"><?php echo number_format($stats['inventory_items'] ?? 0); ?></div>
    </div>
    <div class="card" style="border-left-color:#e74a3b;margin:0;">
        <div style="font-size:0.75rem;color:#64748b;">Low-Stock Items</div>
        <div style="font-size:1.8rem;font-weight:700;"><?php echo number_format($stats['low_stock_items'] ?? 0); ?></div>
    </div>
</div>

<!-- Department Summaries -->
<div class="card">
    <h3 style="margin-bottom:1.5rem;"><i class="fas fa-building"></i> Department Summaries</h3>
    <div class="executive-dashboard">
        <?php foreach ($department_summaries as $key => $dept): ?>
        <div class="department-card" style="border-top-color:<?php echo $dept['color']; ?>;">
            <div class="dept-icon" style="background:<?php echo $dept['color']; ?>;">
                <i class="fas fa-<?php echo $dept['icon']; ?>"></i>
            </div>
            <h4 style="margin:0 0 1rem 0;"><?php echo $dept['name']; ?></h4>
            <?php if ($key === 'procurement'): ?>
                <div style="font-size:0.85rem;color:#64748b;">Total PRs: <strong><?php echo number_format($dept['total_prs']); ?></strong></div>
                <div style="font-size:0.85rem;color:#64748b;">Pending: <strong><?php echo number_format($dept['pending_prs']); ?></strong></div>
                <div style="font-size:0.85rem;color:#64748b;">Total Value: <strong>₱<?php echo number_format($dept['total_value'], 2); ?></strong></div>
            <?php elseif ($key === 'accounting'): ?>
                <div style="font-size:0.85rem;color:#64748b;">Total Invoices: <strong><?php echo number_format($dept['total_invoices']); ?></strong></div>
                <div style="font-size:0.85rem;color:#64748b;">Total Expenses: <strong>₱<?php echo number_format($dept['total_expenses'], 2); ?></strong></div>
                <div style="font-size:0.85rem;color:#64748b;">Active Funds: <strong><?php echo number_format($dept['funds_count']); ?></strong></div>
            <?php elseif ($key === 'engineering'): ?>
                <div style="font-size:0.85rem;color:#64748b;">Total Projects: <strong><?php echo number_format($dept['total_projects']); ?></strong></div>
                <div style="font-size:0.85rem;color:#64748b;">Ongoing: <strong><?php echo number_format($dept['ongoing_projects']); ?></strong></div>
                <div style="font-size:0.85rem;color:#64748b;">Accomplishments: <strong><?php echo number_format($dept['accomplishments']); ?></strong></div>
            <?php elseif ($key === 'warehouse'): ?>
                <div style="font-size:0.85rem;color:#64748b;">Total Stock: <strong><?php echo number_format($dept['total_stock']); ?></strong></div>
                <div style="font-size:0.85rem;color:#64748b;">Movements (30d): <strong><?php echo number_format($dept['stock_movements']); ?></strong></div>
                <div style="font-size:0.85rem;color:#64748b;">Pending Requests: <strong><?php echo number_format($dept['material_requests']); ?></strong></div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Items Needing Attention -->
<div class="card">
    <h3 style="margin-bottom:1rem;"><i class="fas fa-exclamation-triangle"></i> Items Needing Attention</h3>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:1rem;">
        <?php if ($items_needing_attention['low_stock'] > 0): ?>
        <div class="attention-item">
            <i class="fas fa-boxes"></i>
            <div>
                <strong><?php echo number_format($items_needing_attention['low_stock']); ?></strong> Low Stock Materials
                <div style="font-size:0.75rem;color:#64748b;">Require immediate replenishment</div>
            </div>
        </div>
        <?php endif; ?>
        <?php if ($items_needing_attention['pending_prs'] > 0): ?>
        <div class="attention-item">
            <i class="fas fa-file-invoice"></i>
            <div>
                <strong><?php echo number_format($items_needing_attention['pending_prs']); ?></strong> Pending Purchase Requests
                <div style="font-size:0.75rem;color:#64748b;">Awaiting approval</div>
            </div>
        </div>
        <?php endif; ?>
        <?php if ($items_needing_attention['pending_material_requests'] > 0): ?>
        <div class="attention-item">
            <i class="fas fa-clipboard-list"></i>
            <div>
                <strong><?php echo number_format($items_needing_attention['pending_material_requests']); ?></strong> Pending Material Requests
                <div style="font-size:0.75rem;color:#64748b;">Awaiting warehouse release</div>
            </div>
        </div>
        <?php endif; ?>
        <?php if ($items_needing_attention['overdue_projects'] > 0): ?>
        <div class="attention-item">
            <i class="fas fa-clock"></i>
            <div>
                <strong><?php echo number_format($items_needing_attention['overdue_projects']); ?></strong> Overdue Projects
                <div style="font-size:0.75rem;color:#64748b;">Past end date, need attention</div>
            </div>
        </div>
        <?php endif; ?>
        <?php if (array_sum($items_needing_attention) === 0): ?>
        <div style="grid-column:1/-1;text-align:center;color:#1cc88a;padding:1rem;">
            <i class="fas fa-check-circle" style="font-size:2rem;margin-bottom:0.5rem;"></i>
            <div>All systems operating normally</div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Recent Accomplishments -->
<?php if (!empty($recent_accomplishments)): ?>
<div class="card">
    <h3 style="margin-bottom:1rem;"><i class="fas fa-clipboard-check"></i> Recent Project Accomplishments</h3>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Project</th>
                    <th>Description</th>
                    <th>Completion %</th>
                    <th>Date</th>
                    <th>By</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recent_accomplishments as $accomplishment): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($accomplishment['project_code'] ?? 'N/A'); ?></strong></td>
                    <td><?php echo htmlspecialchars(substr($accomplishment['description'], 0, 50)) . (strlen($accomplishment['description']) > 50 ? '...' : ''); ?></td>
                    <td><?php echo number_format($accomplishment['completion_percentage'], 1); ?>%</td>
                    <td><?php echo date('M d, Y', strtotime($accomplishment['created_at'])); ?></td>
                    <td><?php echo htmlspecialchars($accomplishment['created_by_name'] ?? 'N/A'); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Recent Department Activity -->
<div class="card">
    <h3 style="margin-bottom:1rem;"><i class="fas fa-history"></i> Recent Department Activity</h3>
    <?php if (empty($recent_department_activity)): ?>
        <div class="empty-state">
            <i class="fas fa-history"></i>
            <p>No recent activity recorded.</p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Department</th>
                        <th>Action</th>
                        <th>Module</th>
                        <th>Details</th>
                        <th>Time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_department_activity as $activity): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($activity['full_name'] ?? 'System'); ?></td>
                        <td><span class="badge badge-secondary"><?php echo ucfirst($activity['department'] ?? 'N/A'); ?></span></td>
                        <td><?php echo htmlspecialchars($activity['action']); ?></td>
                        <td><?php echo htmlspecialchars($activity['module']); ?></td>
                        <td><?php echo htmlspecialchars(substr($activity['details'] ?? '', 0, 40)) . (strlen($activity['details'] ?? '') > 40 ? '...' : ''); ?></td>
                        <td><?php echo date('M d, Y h:i A', strtotime($activity['created_at'])); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php include '../../includes/footer.php'; ?>
