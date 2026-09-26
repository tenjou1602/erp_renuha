<?php
require_once 'config/database.php';
require_once 'includes/auth.php';
requireLogin();

$user = getCurrentUser();
$department = $_SESSION['department'] ?? 'admin';
$role = $_SESSION['role'] ?? 'staff';
$is_manager = isManager();
$is_admin = isAdmin();

// Redirect non-admin users to their department dashboard
if (!$is_admin) {
    switch ($department) {
        case 'procurement':
            header('Location: modules/procurement/dashboard.php');
            exit();
        case 'accounting':
            header('Location: modules/accounting/dashboard.php');
            exit();
        case 'engineering':
            header('Location: modules/projects/dashboard.php');
            exit();
        case 'warehouse':
            header('Location: modules/warehouse/dashboard.php');
            exit();
        default:
            header('Location: modules/projects/dashboard.php');
            exit();
    }
}

// Executive Admin Dashboard only for admin users
$page_title = 'Executive Dashboard - ' . APP_NAME;

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
    
    // Financial Statistics
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
    try {
        $result = $pdo->query("SELECT COUNT(*) FROM funds WHERE status = 'active'");
        if ($result) {
            $department_summaries['accounting']['funds_count'] = $result->fetchColumn() ?? 0;
        }
    } catch (PDOException $e) {
        $department_summaries['accounting']['funds_count'] = 0;
    }
    
    $department_summaries['engineering'] = [
        'name' => 'Engineering',
        'icon' => 'hard-hat',
        'color' => '#4e73df',
        'total_projects' => $pdo->query("SELECT COUNT(*) FROM projects")->fetchColumn() ?? 0,
        'ongoing_projects' => $pdo->query("SELECT COUNT(*) FROM projects WHERE status = 'ongoing'")->fetchColumn() ?? 0,
        'accomplishments' => 0
    ];
    try {
        $result = $pdo->query("SELECT COUNT(*) FROM accomplishments");
        if ($result) {
            $department_summaries['engineering']['accomplishments'] = $result->fetchColumn() ?? 0;
        }
    } catch (PDOException $e) {
        $department_summaries['engineering']['accomplishments'] = 0;
    }
    
    $department_summaries['warehouse'] = [
        'name' => 'Warehouse',
        'icon' => 'warehouse',
        'color' => '#1cc88a',
        'total_stock' => $pdo->query("SELECT COALESCE(SUM(current_stock), 0) FROM materials WHERE status = 'active'")->fetchColumn() ?? 0,
        'stock_movements' => $pdo->query("SELECT COUNT(*) FROM stock_movements WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)")->fetchColumn() ?? 0,
        'material_requests' => 0
    ];
    try {
        $result = $pdo->query("SELECT COUNT(*) FROM material_requests WHERE status = 'pending'");
        if ($result) {
            $department_summaries['warehouse']['material_requests'] = $result->fetchColumn() ?? 0;
        }
    } catch (PDOException $e) {
        $department_summaries['warehouse']['material_requests'] = 0;
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
        $items_needing_attention['pending_material_requests'] = 0;
    }
    $items_needing_attention['unassigned_pic'] = 0;
    try {
        $items_needing_attention['unassigned_pic'] = $pdo->query("SELECT COUNT(*) FROM projects WHERE in_charge_id IS NULL AND status IN ('planning','ongoing')")->fetchColumn() ?? 0;
    } catch (PDOException $e) {
        $items_needing_attention['unassigned_pic'] = 0;
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
    
    $stats['planning_projects'] = $pdo->query("SELECT COUNT(*) FROM projects WHERE status = 'planning'")->fetchColumn() ?? 0;
    $stats['on_hold_projects'] = $pdo->query("SELECT COUNT(*) FROM projects WHERE status = 'on_hold'")->fetchColumn() ?? 0;
    $stats['ongoing_projects'] = $pdo->query("SELECT COUNT(*) FROM projects WHERE status = 'ongoing'")->fetchColumn() ?? 0;
    $stats['completed_projects'] = $pdo->query("SELECT COUNT(*) FROM projects WHERE status = 'completed'")->fetchColumn() ?? 0;
    $stats['pending_pos'] = $pdo->query("SELECT COUNT(*) FROM purchase_orders WHERE status IN ('draft', 'sent', 'confirmed')")->fetchColumn() ?? 0;
    $stats['pending_invoices'] = $pdo->query("SELECT COUNT(*) FROM invoices WHERE status IN ('sent', 'partial')")->fetchColumn() ?? 0;

    try {
        $pdo->exec("UPDATE projects
            SET project_code = CONCAT('PRJ-', YEAR(IFNULL(created_at, NOW())), '-', LPAD(id, 3, '0'))
            WHERE project_code LIKE '%E+%' OR project_code LIKE '%e+%' OR CHAR_LENGTH(project_code) > 24");
    } catch (PDOException $e) {
        // non-fatal
    }

    // Project Overview
    try {
        $project_overview = $pdo->query("
            SELECT id, project_code, name, status, location, start_date, end_date, estimated_budget, actual_cost, progress
            FROM projects
            ORDER BY created_at DESC
            LIMIT 10
        ")->fetchAll();
    } catch (PDOException $e) {
        $project_overview = $pdo->query("
            SELECT id, project_code, name, status, location, start_date, end_date, estimated_budget, actual_cost
            FROM projects
            ORDER BY created_at DESC
            LIMIT 10
        ")->fetchAll();
    }
    
    // Consolidated Reports Summary
    $consolidated_reports = [
        'total_projects' => $pdo->query("SELECT COUNT(*) FROM projects")->fetchColumn() ?? 0,
        'total_budget' => $pdo->query("SELECT COALESCE(SUM(estimated_budget), 0) FROM projects")->fetchColumn() ?? 0,
        'total_actual_cost' => $pdo->query("SELECT COALESCE(SUM(actual_cost), 0) FROM projects")->fetchColumn() ?? 0,
        'total_invoices' => $pdo->query("SELECT COUNT(*) FROM invoices")->fetchColumn() ?? 0,
        'total_invoice_amount' => $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM invoices")->fetchColumn() ?? 0,
        'total_expenses' => $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM expenses")->fetchColumn() ?? 0,
        'total_stock_movements' => $pdo->query("SELECT COUNT(*) FROM stock_movements")->fetchColumn() ?? 0,
        'activity_count' => $pdo->query("SELECT COUNT(*) FROM activity_log")->fetchColumn() ?? 0,
    ];
    
} catch (PDOException $e) {
    $error = userDatabaseError($e);
}

if (!isset($project_overview)) {
    $project_overview = [];
}

if (!function_exists('dashboardTimeProgress')) {
function dashboardTimeProgress(array $project) {
    $status = $project['status'] ?? '';
    if ($status === 'completed') {
        return 100.0;
    }
    $manual = isset($project['progress']) && $project['progress'] !== null && $project['progress'] !== ''
        ? (float) $project['progress']
        : 0.0;
    if ($manual > 0) {
        return max(0.0, min(100.0, $manual));
    }
    $start = !empty($project['start_date']) ? strtotime($project['start_date']) : false;
    $end = !empty($project['end_date']) ? strtotime($project['end_date']) : false;
    if (!$start || !$end || $end <= $start) {
        return 0.0;
    }
    return max(0.0, min(100.0, ((time() - $start) / ($end - $start)) * 100));
}
}

include 'includes/header.php';
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

<div class="page-header dashboard-greeting">
    <div>
        <h1>Hi, <?php echo htmlspecialchars($_SESSION['full_name'] ?? 'User'); ?> <i class="fas fa-crown"></i></h1>
<p class="greeting-copy">Executive Admin Dashboard — monitor all departments after encoding the approved project.</p>
    </div>
    <div class="dept-badge">
        <i class="fas fa-crown"></i>
        Executive Admin
    </div>
</div>

<?php if (isset($error)): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<div class="workflow-strip">
    <span>1 Admin encodes project</span>
    <span>2 Accounting budget + assign In-Charge</span>
    <span>3 Engineering plans &amp; requests materials</span>
    <span>4 Procurement purchases</span>
    <span>5 Warehouse stock-in / release</span>
</div>

<!-- Quick actions grouped by department -->
<div class="quick-action-groups">
    <div class="qa-group">
        <div class="qa-group-label">Engineering</div>
        <div class="qa-group-items">
            <a href="<?php echo APP_URL; ?>modules/projects/projects.php?action=add" class="quick-action accent-projects">
                <i class="fas fa-file-signature"></i>
                <span>Encode Approved Project</span>
            </a>
            <a href="<?php echo APP_URL; ?>modules/projects/dashboard.php" class="quick-action accent-projects">
                <i class="fas fa-hard-hat"></i>
                <span>Projects Dashboard</span>
            </a>
            <a href="<?php echo APP_URL; ?>modules/projects/projects.php" class="quick-action accent-projects">
                <i class="fas fa-tasks"></i>
                <span>Manage Projects</span>
            </a>
        </div>
    </div>
    <div class="qa-group">
        <div class="qa-group-label">Procurement</div>
        <div class="qa-group-items">
            <a href="<?php echo APP_URL; ?>modules/procurement/dashboard.php" class="quick-action accent-procurement">
                <i class="fas fa-shopping-cart"></i>
                <span>Procurement Dashboard</span>
            </a>
            <a href="<?php echo APP_URL; ?>modules/procurement/purchase_requests.php" class="quick-action accent-procurement">
                <i class="fas fa-file-invoice"></i>
                <span>Purchase Requests</span>
            </a>
        </div>
    </div>
    <div class="qa-group">
        <div class="qa-group-label">Accounting</div>
        <div class="qa-group-items">
            <a href="<?php echo APP_URL; ?>modules/accounting/assign_in_charge.php" class="quick-action accent-accounting">
                <i class="fas fa-user-tie"></i>
                <span>Assign In-Charge</span>
            </a>
            <a href="<?php echo APP_URL; ?>modules/accounting/dashboard.php" class="quick-action accent-accounting">
                <i class="fas fa-chart-line"></i>
                <span>Accounting</span>
            </a>
        </div>
    </div>
    <div class="qa-group">
        <div class="qa-group-label">Warehouse</div>
        <div class="qa-group-items">
            <a href="<?php echo APP_URL; ?>modules/warehouse/receive_purchases.php" class="quick-action accent-warehouse">
                <i class="fas fa-truck-loading"></i>
                <span>Receive Purchases</span>
            </a>
            <a href="<?php echo APP_URL; ?>modules/warehouse/inventory.php" class="quick-action accent-warehouse">
                <i class="fas fa-warehouse"></i>
                <span>Inventory</span>
            </a>
        </div>
    </div>
</div>

<!-- Executive Overview Stats -->
<div class="stats-grid dashboard-stats">
    <div class="stat-card accent-procurement">
        <div class="stat-icon"><i class="fas fa-file-invoice"></i></div>
        <div class="stat-value"><?php echo number_format($stats['pending_purchases'] ?? 0); ?></div>
        <div class="stat-label">Pending Purchase Requests</div>
    </div>
    <div class="stat-card accent-procurement">
        <div class="stat-icon"><i class="fas fa-shopping-cart"></i></div>
        <div class="stat-value"><?php echo number_format($stats['pending_pos'] ?? 0); ?></div>
        <div class="stat-label">Active Purchase Orders</div>
    </div>
    <div class="stat-card accent-projects">
        <div class="stat-icon"><i class="fas fa-play-circle"></i></div>
        <div class="stat-value"><?php echo number_format($stats['ongoing_projects'] ?? 0); ?></div>
        <div class="stat-label">Ongoing Projects</div>
    </div>
    <div class="stat-card accent-success">
        <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
        <div class="stat-value"><?php echo number_format($stats['completed_projects'] ?? 0); ?></div>
        <div class="stat-label">Completed Projects</div>
    </div>
    <div class="stat-card accent-accounting">
        <div class="stat-icon"><i class="fas fa-file-invoice-dollar"></i></div>
        <div class="stat-value"><?php echo number_format($stats['pending_invoices'] ?? 0); ?></div>
        <div class="stat-label">Pending Invoices</div>
    </div>
</div>

<?php
$status_counts = [
    'Completed' => (int) ($stats['completed_projects'] ?? 0),
    'Ongoing' => (int) ($stats['ongoing_projects'] ?? 0),
    'Planning' => (int) ($stats['planning_projects'] ?? 0),
    'On hold' => (int) ($stats['on_hold_projects'] ?? 0),
];
$status_colors = ['#16a085', '#f59e0b', '#94a3b8', '#e76f51'];
$chart_total = array_sum($status_counts);
?>

<div class="dashboard-grid">
    <div class="card project-summary-card">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-list"></i> Project Summary</h3>
        </div>
        <?php if (empty($project_overview)): ?>
            <div class="empty-state compact">
                <i class="fas fa-project-diagram"></i>
                <p>No projects yet.</p>
            </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table project-summary-table" id="projectSummaryTable">
                <thead>
                    <tr>
                        <th data-sort-type="string">Code</th>
                        <th data-sort-type="string">Project</th>
                        <th data-sort-type="string">Status</th>
                        <th data-sort-type="number">Time elapsed</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($project_overview as $project):
                        $pct = dashboardTimeProgress($project);
                        $bar_class = $project['status'] === 'completed' ? 'progress-complete' : ($project['status'] === 'planning' ? 'progress-planning' : 'progress-ongoing');
                    ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars(displayDocumentCode($project['project_code'])); ?></strong></td>
                        <td>
                            <div class="project-name"><?php echo htmlspecialchars($project['name']); ?></div>
                            <div class="project-meta"><?php echo htmlspecialchars($project['location'] ?? 'N/A'); ?></div>
                        </td>
                        <td><span class="status-dot status-<?php echo htmlspecialchars($project['status']); ?>"></span><?php echo ucfirst(str_replace('_', ' ', $project['status'])); ?></td>
                        <td>
                            <div class="progress-cell">
                                <div class="progress-track"><span class="<?php echo $bar_class; ?>" style="width:<?php echo number_format($pct, 1); ?>%"></span></div>
                                <span class="progress-pct"><?php echo (int) round($pct); ?>%</span>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <div class="card chart-card">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-chart-pie"></i> Overall Progress</h3>
        </div>
        <?php if ($chart_total <= 0): ?>
            <div class="empty-state compact">
                <i class="fas fa-chart-pie"></i>
                <p>No project status data yet.</p>
            </div>
        <?php elseif ($chart_total < 3): ?>
            <div class="sparse-progress">
                <?php foreach ($status_counts as $label => $count): ?>
                    <?php if ($count <= 0) continue; ?>
                    <div class="sparse-progress-row">
                        <span><?php echo htmlspecialchars($label); ?></span>
                        <strong><?php echo (int) $count; ?></strong>
                    </div>
                <?php endforeach; ?>
                <p class="sparse-progress-note">Too few projects for a breakdown chart. Counts are shown instead.</p>
            </div>
        <?php else: ?>
            <div class="gauge-wrap">
                <canvas id="overallProgressChart" width="210" height="210"></canvas>
            </div>
            <div class="gauge-legend">
                <?php $i = 0; foreach ($status_counts as $label => $count): ?>
                <div class="gauge-legend-item">
                    <span class="legend-swatch" style="background:<?php echo $status_colors[$i]; ?>"></span>
                    <?php echo htmlspecialchars($label); ?>
                    <strong><?php echo (int) $count; ?></strong>
                </div>
                <?php $i++; endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($chart_total >= 3): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>
(function () {
    const el = document.getElementById('overallProgressChart');
    if (!el || typeof Chart === 'undefined') return;
    const labels = <?php echo json_encode(array_keys($status_counts)); ?>;
    const values = <?php echo json_encode(array_values($status_counts)); ?>;
    const colors = <?php echo json_encode($status_colors); ?>;
    const filteredLabels = [];
    const filteredValues = [];
    const filteredColors = [];
    values.forEach(function (v, i) {
        if (Number(v) > 0) {
            filteredLabels.push(labels[i]);
            filteredValues.push(Number(v));
            filteredColors.push(colors[i]);
        }
    });
    new Chart(el, {
        type: 'doughnut',
        data: {
            labels: filteredLabels,
            datasets: [{
                data: filteredValues,
                backgroundColor: filteredColors,
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            cutout: '68%',
            plugins: { legend: { display: false } }
        }
    });
})();
</script>
<?php endif; ?>

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

<!-- Project Overview -->
<div class="card">
    <h3 style="margin-bottom:1rem;"><i class="fas fa-project-diagram"></i> Project Overview</h3>
    <?php if (empty($project_overview)): ?>
        <div class="empty-state">
            <i class="fas fa-project-diagram"></i>
            <p>No projects yet.</p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Project Code</th>
                        <th>Name</th>
                        <th>Location</th>
                        <th>Status</th>
                        <th>Budget</th>
                        <th>Actual Cost</th>
                        <th>Progress</th>
                        <th>End Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($project_overview as $project): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars(displayDocumentCode($project['project_code'])); ?></strong></td>
                        <td><?php echo htmlspecialchars($project['name']); ?></td>
                        <td><?php echo htmlspecialchars($project['location'] ?? 'N/A'); ?></td>
                        <td><span class="status-dot status-<?php echo $project['status']; ?>"></span><?php echo ucfirst($project['status']); ?></td>
                        <td>₱<?php echo number_format($project['estimated_budget'], 2); ?></td>
                        <td>₱<?php echo number_format($project['actual_cost'], 2); ?></td>
                        <td><?php echo number_format($project['progress'] ?? 0, 1); ?>%</td>
                        <td><?php echo $project['end_date'] ? date('M d, Y', strtotime($project['end_date'])) : 'N/A'; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Consolidated Reports Summary -->
<div class="card">
    <h3 style="margin-bottom:1rem;"><i class="fas fa-chart-bar"></i> Consolidated Reports Summary</h3>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1rem;">
        <div style="background:#f8f9fa;padding:1rem;border-radius:8px;">
            <div style="font-size:0.75rem;color:#64748b;">Total Projects</div>
            <div style="font-size:1.5rem;font-weight:700;color:#4e73df;"><?php echo number_format($consolidated_reports['total_projects'] ?? 0); ?></div>
        </div>
        <div style="background:#f8f9fa;padding:1rem;border-radius:8px;">
            <div style="font-size:0.75rem;color:#64748b;">Total Budget</div>
            <div style="font-size:1.5rem;font-weight:700;color:#f6c23e;">₱<?php echo number_format($consolidated_reports['total_budget'] ?? 0, 2); ?></div>
        </div>
        <div style="background:#f8f9fa;padding:1rem;border-radius:8px;">
            <div style="font-size:0.75rem;color:#64748b;">Total Actual Cost</div>
            <div style="font-size:1.5rem;font-weight:700;color:#e74a3b;">₱<?php echo number_format($consolidated_reports['total_actual_cost'] ?? 0, 2); ?></div>
        </div>
        <div style="background:#f8f9fa;padding:1rem;border-radius:8px;">
            <div style="font-size:0.75rem;color:#64748b;">Total Invoices</div>
            <div style="font-size:1.5rem;font-weight:700;color:#36b9cc;"><?php echo number_format($consolidated_reports['total_invoices'] ?? 0); ?></div>
        </div>
        <div style="background:#f8f9fa;padding:1rem;border-radius:8px;">
            <div style="font-size:0.75rem;color:#64748b;">Total Invoice Amount</div>
            <div style="font-size:1.5rem;font-weight:700;color:#1cc88a;">₱<?php echo number_format($consolidated_reports['total_invoice_amount'] ?? 0, 2); ?></div>
        </div>
        <div style="background:#f8f9fa;padding:1rem;border-radius:8px;">
            <div style="font-size:0.75rem;color:#64748b;">Total Expenses</div>
            <div style="font-size:1.5rem;font-weight:700;color:#f59e0b;">₱<?php echo number_format($consolidated_reports['total_expenses'] ?? 0, 2); ?></div>
        </div>
        <div style="background:#f8f9fa;padding:1rem;border-radius:8px;">
            <div style="font-size:0.75rem;color:#64748b;">Stock Movements</div>
            <div style="font-size:1.5rem;font-weight:700;color:#858796;"><?php echo number_format($consolidated_reports['total_stock_movements'] ?? 0); ?></div>
        </div>
        <div style="background:#f8f9fa;padding:1rem;border-radius:8px;">
            <div style="font-size:0.75rem;color:#64748b;">Activity Log</div>
            <div style="font-size:1.5rem;font-weight:700;color:#6f42c1;"><?php echo number_format($consolidated_reports['activity_count'] ?? 0); ?></div>
        </div>
    </div>
    <div style="margin-top:1rem;text-align:center;">
        <a href="modules/admin/consolidated_reports.php" class="btn btn-primary"><i class="fas fa-chart-bar"></i> View Detailed Consolidated Reports</a>
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
        <?php if (($items_needing_attention['unassigned_pic'] ?? 0) > 0): ?>
            <a href="<?php echo APP_URL; ?>modules/accounting/assign_in_charge.php" class="attention-item" style="background:#fef3c7;padding:0.8rem;border-radius:8px;text-decoration:none;color:inherit;">
                <i class="fas fa-user-tie" style="color:#d97706;"></i>
                <strong><?php echo number_format($items_needing_attention['unassigned_pic']); ?></strong> Projects without In-Charge
            </a>
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
                    <td><strong><?php echo htmlspecialchars(displayDocumentCode($accomplishment['project_code'] ?? 'N/A')); ?></strong></td>
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

<?php include 'includes/footer.php'; ?>
