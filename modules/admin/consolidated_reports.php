<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';

requireAdmin();

$page_title = 'Consolidated Reports';
$report_type = $_GET['report'] ?? 'executive';
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');

// Report data
$report_data = [];
try {
    switch ($report_type) {
        case 'executive':
            // Executive summary
            $report_data['total_projects'] = $pdo->query("SELECT COUNT(*) FROM projects")->fetchColumn() ?? 0;
            $report_data['total_budget'] = $pdo->query("SELECT COALESCE(SUM(estimated_budget), 0) FROM projects")->fetchColumn() ?? 0;
            $report_data['total_actual_cost'] = $pdo->query("SELECT COALESCE(SUM(actual_cost), 0) FROM projects")->fetchColumn() ?? 0;
            $report_data['total_revenue'] = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM invoices WHERE status = 'paid' AND created_at BETWEEN '$start_date' AND '$end_date 23:59:59'")->fetchColumn() ?? 0;
            $report_data['total_expenses'] = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE status = 'paid' AND created_at BETWEEN '$start_date' AND '$end_date 23:59:59'")->fetchColumn() ?? 0;
            $report_data['procurement_value'] = $pdo->query("SELECT COALESCE(SUM(total_amount), 0) FROM purchase_requests WHERE status IN ('approved', 'confirmed', 'ordered', 'received') AND created_at BETWEEN '$start_date' AND '$end_date 23:59:59'")->fetchColumn() ?? 0;
            $report_data['inventory_value'] = $pdo->query("SELECT COALESCE(SUM(current_stock * cost_per_unit), 0) FROM materials WHERE status = 'active'")->fetchColumn() ?? 0;
            
            // Department performance
            $report_data['dept_performance'] = [
                'procurement' => $pdo->query("SELECT COUNT(*) FROM purchase_requests WHERE created_at BETWEEN '$start_date' AND '$end_date 23:59:59'")->fetchColumn() ?? 0,
                'accounting' => $pdo->query("SELECT COUNT(*) FROM invoices WHERE created_at BETWEEN '$start_date' AND '$end_date 23:59:59'")->fetchColumn() ?? 0,
                'engineering' => 0,
                'warehouse' => $pdo->query("SELECT COUNT(*) FROM stock_movements WHERE created_at BETWEEN '$start_date' AND '$end_date 23:59:59'")->fetchColumn() ?? 0
            ];
            // Get engineering accomplishments with error handling
            try {
                $result = $pdo->query("SELECT COUNT(*) FROM accomplishments WHERE created_at BETWEEN '$start_date' AND '$end_date 23:59:59'");
                if ($result) {
                    $report_data['dept_performance']['engineering'] = $result->fetchColumn() ?? 0;
                }
            } catch (PDOException $e) {
                $report_data['dept_performance']['engineering'] = 0;
            }
            break;
            
        case 'financial':
            // Financial consolidation
            $report_data['project_financials'] = $pdo->query("
                SELECT p.project_code, p.name, p.estimated_budget, p.actual_cost, (p.estimated_budget - p.actual_cost) as remaining,
                       COALESCE(SUM(inv.amount), 0) as invoices, COALESCE(SUM(exp.amount), 0) as expenses
                FROM projects p
                LEFT JOIN invoices inv ON p.id = inv.project_id
                LEFT JOIN expenses exp ON p.id = exp.project_id
                GROUP BY p.id, p.project_code, p.name, p.estimated_budget, p.actual_cost
                ORDER BY p.created_at DESC
            ")->fetchAll();
            
            $report_data['fund_status'] = [];
            try {
                $report_data['fund_status'] = $pdo->query("SELECT fund_type, COUNT(*) as count, COALESCE(SUM(amount), 0) as total FROM funds WHERE status = 'active' GROUP BY fund_type")->fetchAll();
            } catch (PDOException $e) {
                $report_data['fund_status'] = [];
            }
            
            $report_data['contract_value'] = 0;
            try {
                $result = $pdo->query("SELECT COALESCE(SUM(contract_value), 0) as total FROM contracts WHERE status = 'active'");
                if ($result) {
                    $report_data['contract_value'] = $result->fetchColumn() ?? 0;
                }
            } catch (PDOException $e) {
                $report_data['contract_value'] = 0;
            }
            break;
            
        case 'operational':
            // Operational consolidation
            $report_data['procurement_summary'] = $pdo->query("
                SELECT COUNT(*) as total, COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending,
                       COUNT(CASE WHEN status = 'approved' THEN 1 END) as approved,
                       COALESCE(SUM(total_amount), 0) as total_value
                FROM purchase_requests WHERE created_at BETWEEN '$start_date' AND '$end_date 23:59:59'
            ")->fetch();
            
            $report_data['warehouse_summary'] = $pdo->query("
                SELECT COUNT(*) as movements,
                       COUNT(CASE WHEN movement_type = 'in' THEN 1 END) as stock_in,
                       COUNT(CASE WHEN movement_type = 'out' THEN 1 END) as stock_out
                FROM stock_movements WHERE created_at BETWEEN '$start_date' AND '$end_date 23:59:59'
            ")->fetch();
            
            $report_data['project_summary'] = $pdo->query("
                SELECT COUNT(*) as total, COUNT(CASE WHEN status = 'ongoing' THEN 1 END) as ongoing,
                       COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed
                FROM projects
            ")->fetch();
            break;
            
        case 'activity':
            // System activity
            $report_data['activity_by_department'] = $pdo->query("
                SELECT u.department, COUNT(*) as count
                FROM activity_log al
                LEFT JOIN users u ON al.user_id = u.id
                WHERE al.created_at BETWEEN '$start_date' AND '$end_date 23:59:59'
                GROUP BY u.department
                ORDER BY count DESC
            ")->fetchAll();
            
            $report_data['activity_by_module'] = $pdo->query("
                SELECT module, COUNT(*) as count
                FROM activity_log
                WHERE created_at BETWEEN '$start_date' AND '$end_date 23:59:59'
                GROUP BY module
                ORDER BY count DESC
            ")->fetchAll();
            
            $report_data['top_users'] = $pdo->query("
                SELECT u.full_name, u.department, COUNT(*) as activity_count
                FROM activity_log al
                LEFT JOIN users u ON al.user_id = u.id
                WHERE al.created_at BETWEEN '$start_date' AND '$end_date 23:59:59'
                GROUP BY u.id, u.full_name, u.department
                ORDER BY activity_count DESC
                LIMIT 10
            ")->fetchAll();
            break;
    }
} catch (PDOException $e) {
    $error = userDatabaseError($e);
}

include '../../includes/header.php';
?>

<style>
.reports-nav {
    display: flex;
    gap: 0.5rem;
    margin-bottom: 1.5rem;
    flex-wrap: wrap;
}
.reports-nav .btn {
    padding: 0.5rem 1rem;
    font-size: 0.85rem;
}
.reports-nav .btn.active {
    background: #f59e0b;
    color: #1a1a2e;
}
.report-filters {
    display: flex;
    gap: 1rem;
    margin-bottom: 1.5rem;
    flex-wrap: wrap;
    align-items: center;
}
.report-filters .form-group {
    margin: 0;
}
.report-filters input[type="date"] {
    padding: 0.5rem;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
}
.stat-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 1.5rem;
}
.stat-card {
    background: white;
    padding: 1.5rem;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    border-left: 4px solid #4e73df;
}
.stat-card .value {
    font-size: 1.8rem;
    font-weight: 700;
    color: #1a1a2e;
}
.stat-card .label {
    font-size: 0.85rem;
    color: #64748b;
    margin-top: 0.5rem;
}
</style>

<div class="page-header">
    <h1><i class="fas fa-chart-bar"></i> <?php echo $page_title; ?></h1>
    <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
        <a href="executive_dashboard.php" class="btn btn-primary"><i class="fas fa-tachometer-alt"></i> Executive Dashboard</a>
        <a href="department_monitoring.php" class="btn btn-info"><i class="fas fa-eye"></i> Department Monitoring</a>
    </div>
</div>

<?php if (isset($error)): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<!-- Report Navigation -->
<div class="reports-nav">
    <a href="?report=executive&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" class="btn <?php echo $report_type === 'executive' ? 'active' : 'btn-secondary'; ?>"><i class="fas fa-crown"></i> Executive Summary</a>
    <a href="?report=financial&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" class="btn <?php echo $report_type === 'financial' ? 'active' : 'btn-secondary'; ?>"><i class="fas fa-coins"></i> Financial</a>
    <a href="?report=operational&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" class="btn <?php echo $report_type === 'operational' ? 'active' : 'btn-secondary'; ?>"><i class="fas fa-cogs"></i> Operational</a>
    <a href="?report=activity&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" class="btn <?php echo $report_type === 'activity' ? 'active' : 'btn-secondary'; ?>"><i class="fas fa-users"></i> Activity</a>
</div>

<!-- Date Filters -->
<div class="card">
    <form method="GET" class="report-filters">
        <input type="hidden" name="report" value="<?php echo $report_type; ?>">
        <div class="form-group">
            <label>Start Date:</label>
            <input type="date" name="start_date" value="<?php echo $start_date; ?>">
        </div>
        <div class="form-group">
            <label>End Date:</label>
            <input type="date" name="end_date" value="<?php echo $end_date; ?>">
        </div>
        <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Apply Filter</button>
        <a href="?report=<?php echo $report_type; ?>" class="btn btn-secondary"><i class="fas fa-redo"></i> Reset</a>
    </form>
</div>

<?php if ($report_type === 'executive'): ?>
<!-- Executive Summary -->
<div class="stat-cards">
    <div class="stat-card">
        <div class="value"><?php echo number_format($report_data['total_projects'] ?? 0); ?></div>
        <div class="label">Total Projects</div>
    </div>
    <div class="stat-card" style="border-left-color:#f6c23e;">
        <div class="value">₱<?php echo number_format($report_data['total_budget'] ?? 0, 2); ?></div>
        <div class="label">Total Budget</div>
    </div>
    <div class="stat-card" style="border-left-color:#e74a3b;">
        <div class="value">₱<?php echo number_format($report_data['total_actual_cost'] ?? 0, 2); ?></div>
        <div class="label">Total Actual Cost</div>
    </div>
    <div class="stat-card" style="border-left-color:#1cc88a;">
        <div class="value">₱<?php echo number_format($report_data['total_revenue'] ?? 0, 2); ?></div>
        <div class="label">Total Revenue</div>
    </div>
    <div class="stat-card" style="border-left-color:#36b9cc;">
        <div class="value">₱<?php echo number_format($report_data['total_expenses'] ?? 0, 2); ?></div>
        <div class="label">Total Expenses</div>
    </div>
    <div class="stat-card" style="border-left-color:#858796;">
        <div class="value">₱<?php echo number_format($report_data['procurement_value'] ?? 0, 2); ?></div>
        <div class="label">Procurement Value</div>
    </div>
    <div class="stat-card" style="border-left-color:#4e73df;">
        <div class="value">₱<?php echo number_format($report_data['inventory_value'] ?? 0, 2); ?></div>
        <div class="label">Inventory Value</div>
    </div>
</div>

<div class="card">
    <h3><i class="fas fa-building"></i> Department Performance</h3>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1rem;">
        <div style="border:1px solid #e2e8f0;border-radius:8px;padding:1rem;">
            <h4 style="margin:0 0 0.5rem 0;color:#f59e0b;"><i class="fas fa-shopping-cart"></i> Procurement</h4>
            <div style="font-size:1.5rem;font-weight:700;"><?php echo number_format($report_data['dept_performance']['procurement'] ?? 0); ?></div>
            <div style="font-size:0.75rem;color:#64748b;">Transactions</div>
        </div>
        <div style="border:1px solid #e2e8f0;border-radius:8px;padding:1rem;">
            <h4 style="margin:0 0 0.5rem 0;color:#36b9cc;"><i class="fas fa-chart-line"></i> Accounting</h4>
            <div style="font-size:1.5rem;font-weight:700;"><?php echo number_format($report_data['dept_performance']['accounting'] ?? 0); ?></div>
            <div style="font-size:0.75rem;color:#64748b;">Invoices</div>
        </div>
        <div style="border:1px solid #e2e8f0;border-radius:8px;padding:1rem;">
            <h4 style="margin:0 0 0.5rem 0;color:#4e73df;"><i class="fas fa-hard-hat"></i> Engineering</h4>
            <div style="font-size:1.5rem;font-weight:700;"><?php echo number_format($report_data['dept_performance']['engineering'] ?? 0); ?></div>
            <div style="font-size:0.75rem;color:#64748b;">Accomplishments</div>
        </div>
        <div style="border:1px solid #e2e8f0;border-radius:8px;padding:1rem;">
            <h4 style="margin:0 0 0.5rem 0;color:#1cc88a;"><i class="fas fa-warehouse"></i> Warehouse</h4>
            <div style="font-size:1.5rem;font-weight:700;"><?php echo number_format($report_data['dept_performance']['warehouse'] ?? 0); ?></div>
            <div style="font-size:0.75rem;color:#64748b;">Movements</div>
        </div>
    </div>
</div>

<?php elseif ($report_type === 'financial'): ?>
<!-- Financial Report -->
<div class="card">
    <h3><i class="fas fa-coins"></i> Project Financial Overview</h3>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Project Code</th>
                    <th>Name</th>
                    <th>Budget</th>
                    <th>Actual Cost</th>
                    <th>Remaining</th>
                    <th>Invoices</th>
                    <th>Expenses</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($report_data['project_financials'] ?? [] as $project): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($project['project_code']); ?></strong></td>
                    <td><?php echo htmlspecialchars($project['name']); ?></td>
                    <td>₱<?php echo number_format($project['estimated_budget'], 2); ?></td>
                    <td>₱<?php echo number_format($project['actual_cost'], 2); ?></td>
                    <td class="<?php echo $project['remaining'] >= 0 ? 'text-success' : 'text-danger'; ?>">₱<?php echo number_format($project['remaining'], 2); ?></td>
                    <td>₱<?php echo number_format($project['invoices'], 2); ?></td>
                    <td>₱<?php echo number_format($project['expenses'], 2); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="dashboard-grid">
    <div class="card">
        <h3>Fund Status</h3>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Fund Type</th>
                        <th>Count</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($report_data['fund_status'] ?? [] as $fund): ?>
                    <tr>
                        <td><?php echo ucfirst($fund['fund_type']); ?></td>
                        <td><?php echo $fund['count']; ?></td>
                        <td>₱<?php echo number_format($fund['total'], 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="card">
        <h3>Contract Value</h3>
        <div style="text-align:center;padding:2rem;">
            <div style="font-size:2.5rem;font-weight:700;color:#1cc88a;">₱<?php echo number_format($report_data['contract_value'] ?? 0, 2); ?></div>
            <div style="font-size:0.85rem;color:#64748b;">Total Active Contract Value</div>
        </div>
    </div>
</div>

<?php elseif ($report_type === 'operational'): ?>
<!-- Operational Report -->
<div class="stat-cards">
    <div class="stat-card" style="border-left-color:#f59e0b;">
        <div class="value"><?php echo number_format($report_data['procurement_summary']['total'] ?? 0); ?></div>
        <div class="label">Procurement Transactions</div>
    </div>
    <div class="stat-card" style="border-left-color:#1cc88a;">
        <div class="value"><?php echo number_format($report_data['warehouse_summary']['movements'] ?? 0); ?></div>
        <div class="label">Warehouse Movements</div>
    </div>
    <div class="stat-card" style="border-left-color:#4e73df;">
        <div class="value"><?php echo number_format($report_data['project_summary']['total'] ?? 0); ?></div>
        <div class="label">Total Projects</div>
    </div>
</div>

<div class="dashboard-grid">
    <div class="card">
        <h3>Procurement Summary</h3>
        <div class="view-details">
            <div class="detail-row"><span>Total:</span> <strong><?php echo number_format($report_data['procurement_summary']['total'] ?? 0); ?></strong></div>
            <div class="detail-row"><span>Pending:</span> <strong><?php echo number_format($report_data['procurement_summary']['pending'] ?? 0); ?></strong></div>
            <div class="detail-row"><span>Approved:</span> <strong><?php echo number_format($report_data['procurement_summary']['approved'] ?? 0); ?></strong></div>
            <div class="detail-row"><span>Total Value:</span> <strong>₱<?php echo number_format($report_data['procurement_summary']['total_value'] ?? 0, 2); ?></strong></div>
        </div>
    </div>
    <div class="card">
        <h3>Warehouse Summary</h3>
        <div class="view-details">
            <div class="detail-row"><span>Total Movements:</span> <strong><?php echo number_format($report_data['warehouse_summary']['movements'] ?? 0); ?></strong></div>
            <div class="detail-row"><span>Stock-In:</span> <strong><?php echo number_format($report_data['warehouse_summary']['stock_in'] ?? 0); ?></strong></div>
            <div class="detail-row"><span>Stock-Out:</span> <strong><?php echo number_format($report_data['warehouse_summary']['stock_out'] ?? 0); ?></strong></div>
        </div>
    </div>
    <div class="card">
        <h3>Project Summary</h3>
        <div class="view-details">
            <div class="detail-row"><span>Total Projects:</span> <strong><?php echo number_format($report_data['project_summary']['total'] ?? 0); ?></strong></div>
            <div class="detail-row"><span>Ongoing:</span> <strong><?php echo number_format($report_data['project_summary']['ongoing'] ?? 0); ?></strong></div>
            <div class="detail-row"><span>Completed:</span> <strong><?php echo number_format($report_data['project_summary']['completed'] ?? 0); ?></strong></div>
        </div>
    </div>
</div>

<?php elseif ($report_type === 'activity'): ?>
<!-- Activity Report -->
<div class="card">
    <h3><i class="fas fa-building"></i> Activity by Department</h3>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Department</th>
                    <th>Activity Count</th>
                    <th>Percentage</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $total_activity = array_sum(array_column($report_data['activity_by_department'] ?? [], 'count'));
                foreach ($report_data['activity_by_department'] ?? [] as $dept): 
                ?>
                <tr>
                    <td><strong><?php echo ucfirst($dept['department'] ?? 'Unknown'); ?></strong></td>
                    <td><?php echo $dept['count']; ?></td>
                    <td><?php echo $total_activity > 0 ? number_format(($dept['count'] / $total_activity) * 100, 1) : 0; ?>%</td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <h3><i class="fas fa-cube"></i> Activity by Module</h3>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Module</th>
                    <th>Activity Count</th>
                    <th>Percentage</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $total_module_activity = array_sum(array_column($report_data['activity_by_module'] ?? [], 'count'));
                foreach ($report_data['activity_by_module'] ?? [] as $module): 
                ?>
                <tr>
                    <td><strong><?php echo ucfirst($module['module']); ?></strong></td>
                    <td><?php echo $module['count']; ?></td>
                    <td><?php echo $total_module_activity > 0 ? number_format(($module['count'] / $total_module_activity) * 100, 1) : 0; ?>%</td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <h3><i class="fas fa-trophy"></i> Top Users by Activity</h3>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>User</th>
                    <th>Department</th>
                    <th>Activity Count</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($report_data['top_users'] ?? [] as $user): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($user['full_name']); ?></strong></td>
                    <td><?php echo ucfirst($user['department'] ?? 'N/A'); ?></td>
                    <td><?php echo $user['activity_count']; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php endif; ?>

<?php include '../../includes/footer.php'; ?>
