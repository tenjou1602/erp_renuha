<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';

requireDepartment(['engineering']);

$page_title = 'Project Reports';
$report_type = $_GET['report'] ?? 'overview';
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');

// Report data
$report_data = [];
try {
    switch ($report_type) {
        case 'overview':
            // General project overview
            $report_data['total_projects'] = $pdo->query("SELECT COUNT(*) FROM projects")->fetchColumn() ?? 0;
            $report_data['active_projects'] = $pdo->query("SELECT COUNT(*) FROM projects WHERE status IN ('planning', 'ongoing')")->fetchColumn() ?? 0;
            $report_data['completed_projects'] = $pdo->query("SELECT COUNT(*) FROM projects WHERE status = 'completed'")->fetchColumn() ?? 0;
            $report_data['total_budget'] = $pdo->query("SELECT COALESCE(SUM(estimated_budget), 0) FROM projects")->fetchColumn() ?? 0;
            $report_data['total_actual_cost'] = $pdo->query("SELECT COALESCE(SUM(actual_cost), 0) FROM projects")->fetchColumn() ?? 0;
            
            // Project status breakdown
            $report_data['status_breakdown'] = $pdo->query("
                SELECT status, COUNT(*) as count, COALESCE(SUM(estimated_budget), 0) as total_budget
                FROM projects
                GROUP BY status
            ")->fetchAll();
            
            // Monthly project creation trend
            $report_data['monthly_trend'] = $pdo->query("
                SELECT DATE_FORMAT(created_at, '%b %Y') as month, COUNT(*) as count, COALESCE(SUM(estimated_budget), 0) as budget
                FROM projects
                WHERE created_at BETWEEN DATE_SUB('$start_date', INTERVAL 5 MONTH) AND '$end_date 23:59:59'
                GROUP BY YEAR(created_at), MONTH(created_at), month
                ORDER BY YEAR(created_at), MONTH(created_at)
            ")->fetchAll();
            break;
            
        case 'progress':
            // Project progress and accomplishments
            try {
                $report_data['project_progress'] = $pdo->query("
                    SELECT 
                        p.id,
                        p.project_code,
                        p.name,
                        p.status,
                        p.estimated_budget,
                        p.actual_cost,
                        p.start_date,
                        p.end_date,
                        (p.actual_cost / GREATEST(p.estimated_budget, 1)) * 100 as budget_utilization,
                        COUNT(DISTINCT a.id) as accomplishment_count,
                        COUNT(DISTINCT att.id) as attachment_count
                    FROM projects p
                    LEFT JOIN accomplishments a ON p.id = a.project_id
                    LEFT JOIN attachments att ON p.id = att.project_id
                    GROUP BY p.id, p.project_code, p.name, p.status, p.estimated_budget, p.actual_cost, p.start_date, p.end_date
                    ORDER BY p.created_at DESC
                ")->fetchAll();
            } catch (PDOException $e) {
                $report_data['project_progress'] = $pdo->query("
                    SELECT 
                        p.id,
                        p.project_code,
                        p.name,
                        p.status,
                        p.estimated_budget,
                        p.actual_cost,
                        p.start_date,
                        p.end_date,
                        (p.actual_cost / GREATEST(p.estimated_budget, 1)) * 100 as budget_utilization,
                        COUNT(DISTINCT a.id) as accomplishment_count,
                        0 as attachment_count
                    FROM projects p
                    LEFT JOIN accomplishments a ON p.id = a.project_id
                    GROUP BY p.id, p.project_code, p.name, p.status, p.estimated_budget, p.actual_cost, p.start_date, p.end_date
                    ORDER BY p.created_at DESC
                ")->fetchAll();
            }
            
            // Recent accomplishments
            $report_data['recent_accomplishments'] = $pdo->query("
                SELECT a.*, p.project_code, p.name as project_name, u.full_name as created_by_name
                FROM accomplishments a
                LEFT JOIN projects p ON a.project_id = p.id
                LEFT JOIN users u ON a.created_by = u.id
                ORDER BY a.created_at DESC
                LIMIT 10
            ")->fetchAll();
            break;
            
        case 'materials':
            // Material usage and requirements
            $report_data['material_usage'] = $pdo->query("
                SELECT 
                    m.material_code,
                    m.name,
                    m.unit,
                    m.current_stock,
                    m.min_stock,
                    m.cost_per_unit,
                    COUNT(DISTINCT pr.id) as project_count,
                    COALESCE(SUM(pri.quantity), 0) as total_used,
                    (m.current_stock * m.cost_per_unit) as inventory_value
                FROM materials m
                LEFT JOIN purchase_request_items pri ON m.id = pri.material_id
                LEFT JOIN purchase_requests pr ON pri.purchase_request_id = pr.id
                WHERE m.status = 'active'
                GROUP BY m.id, m.material_code, m.name, m.unit, m.current_stock, m.min_stock, m.cost_per_unit
                HAVING total_used > 0
                ORDER BY total_used DESC
                LIMIT 15
            ")->fetchAll();
            
            // Low stock materials
            $report_data['low_stock'] = $pdo->query("
                SELECT m.material_code, m.name, m.unit, m.current_stock, m.min_stock, m.cost_per_unit,
                       s.name as supplier_name
                FROM materials m
                LEFT JOIN suppliers s ON m.supplier_id = s.id
                WHERE m.status = 'active' AND m.current_stock <= m.min_stock AND m.min_stock > 0
                ORDER BY m.current_stock ASC
                LIMIT 10
            ")->fetchAll();
            
            // Material category breakdown
            $report_data['category_breakdown'] = $pdo->query("
                SELECT m.category, COUNT(*) as count, COALESCE(SUM(m.current_stock * m.cost_per_unit), 0) as total_value
                FROM materials m
                WHERE m.status = 'active' AND m.category IS NOT NULL AND m.category != ''
                GROUP BY m.category
                ORDER BY total_value DESC
            ")->fetchAll();
            break;
            
        case 'locations':
            // Project location analysis
            $report_data['location_analysis'] = $pdo->query("
                SELECT 
                    location,
                    COUNT(*) as project_count,
                    COALESCE(SUM(estimated_budget), 0) as total_budget,
                    COALESCE(SUM(actual_cost), 0) as total_cost,
                    COUNT(CASE WHEN status = 'ongoing' THEN 1 END) as ongoing_count,
                    COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_count
                FROM projects
                WHERE location IS NOT NULL AND location != ''
                GROUP BY location
                ORDER BY project_count DESC
            ")->fetchAll();
            
            // Location distribution
            $report_data['location_distribution'] = $pdo->query("
                SELECT location, status, COUNT(*) as count
                FROM projects
                WHERE location IS NOT NULL AND location != ''
                GROUP BY location, status
                ORDER BY location, status
            ")->fetchAll();
            break;
            
        case 'financial':
            // Project financial overview
            $report_data['project_financials'] = $pdo->query("
                SELECT 
                    p.project_code,
                    p.name,
                    p.estimated_budget,
                    p.actual_cost,
                    (p.estimated_budget - p.actual_cost) as remaining_budget,
                    p.status,
                    COUNT(DISTINCT inv.id) as invoice_count,
                    COALESCE(SUM(inv.amount), 0) as invoice_total,
                    COUNT(DISTINCT exp.id) as expense_count,
                    COALESCE(SUM(exp.amount), 0) as expense_total
                FROM projects p
                LEFT JOIN invoices inv ON p.id = inv.project_id
                LEFT JOIN expenses exp ON p.id = exp.project_id
                GROUP BY p.id, p.project_code, p.name, p.estimated_budget, p.actual_cost, p.status
                ORDER BY p.created_at DESC
            ")->fetchAll();
            
            // Budget utilization by project
            $report_data['budget_utilization'] = $pdo->query("
                SELECT 
                    project_code,
                    name,
                    estimated_budget,
                    actual_cost,
                    (actual_cost / GREATEST(estimated_budget, 1)) * 100 as utilization_percentage
                FROM projects
                WHERE estimated_budget > 0
                ORDER BY utilization_percentage DESC
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
.progress-high { color: #e74a3b; }
.progress-medium { color: #f6c23e; }
.progress-good { color: #1cc88a; }
</style>

<div class="page-header">
    <h1><i class="fas fa-chart-bar"></i> <?php echo $page_title; ?></h1>
</div>

<?php if (isset($error)): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<!-- Report Navigation -->
<div class="reports-nav">
    <a href="?report=overview&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" class="btn <?php echo $report_type === 'overview' ? 'active' : 'btn-secondary'; ?>"><i class="fas fa-tachometer-alt"></i> Overview</a>
    <a href="?report=progress&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" class="btn <?php echo $report_type === 'progress' ? 'active' : 'btn-secondary'; ?>"><i class="fas fa-tasks"></i> Progress</a>
    <a href="?report=materials&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" class="btn <?php echo $report_type === 'materials' ? 'active' : 'btn-secondary'; ?>"><i class="fas fa-boxes"></i> Materials</a>
    <a href="?report=locations&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" class="btn <?php echo $report_type === 'locations' ? 'active' : 'btn-secondary'; ?>"><i class="fas fa-map-marker-alt"></i> Locations</a>
    <a href="?report=financial&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" class="btn <?php echo $report_type === 'financial' ? 'active' : 'btn-secondary'; ?>"><i class="fas fa-coins"></i> Financial</a>
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

<?php if ($report_type === 'overview'): ?>
<!-- Overview Report -->
<div class="stat-cards">
    <div class="stat-card">
        <div class="value"><?php echo number_format($report_data['total_projects'] ?? 0); ?></div>
        <div class="label">Total Projects</div>
    </div>
    <div class="stat-card" style="border-left-color:#1cc88a;">
        <div class="value"><?php echo number_format($report_data['active_projects'] ?? 0); ?></div>
        <div class="label">Active Projects</div>
    </div>
    <div class="stat-card" style="border-left-color:#36b9cc;">
        <div class="value"><?php echo number_format($report_data['completed_projects'] ?? 0); ?></div>
        <div class="label">Completed Projects</div>
    </div>
    <div class="stat-card" style="border-left-color:#f6c23e;">
        <div class="value">₱<?php echo number_format($report_data['total_budget'] ?? 0, 2); ?></div>
        <div class="label">Total Budget</div>
    </div>
    <div class="stat-card" style="border-left-color:#e74a3b;">
        <div class="value">₱<?php echo number_format($report_data['total_actual_cost'] ?? 0, 2); ?></div>
        <div class="label">Total Actual Cost</div>
    </div>
</div>

<div class="dashboard-grid">
    <div class="card chart-card">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-chart-line"></i> Monthly Project Trend</h3>
        </div>
        <canvas id="monthlyTrendChart"></canvas>
    </div>
    <div class="card chart-card">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-chart-pie"></i> Project Status Breakdown</h3>
        </div>
        <canvas id="statusBreakdownChart"></canvas>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>
const monthlyTrendCtx = document.getElementById('monthlyTrendChart');
if (monthlyTrendCtx) {
    new Chart(monthlyTrendCtx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode(array_column($report_data['monthly_trend'] ?? [], 'month')); ?>,
            datasets: [{
                label: 'Projects Created',
                data: <?php echo json_encode(array_map('intval', array_column($report_data['monthly_trend'] ?? [], 'count'))); ?>,
                borderColor: '#f59e0b',
                backgroundColor: 'rgba(245,158,11,0.1)',
                fill: true,
                tension: 0.35
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
        }
    });
}

const statusBreakdownCtx = document.getElementById('statusBreakdownChart');
if (statusBreakdownCtx) {
    new Chart(statusBreakdownCtx, {
        type: 'doughnut',
        data: {
            labels: <?php echo json_encode(array_column($report_data['status_breakdown'] ?? [], 'status')); ?>,
            datasets: [{
                data: <?php echo json_encode(array_map('intval', array_column($report_data['status_breakdown'] ?? [], 'count'))); ?>,
                backgroundColor: ['#4e73df', '#1cc88a', '#f6c23e', '#e74a3b', '#36b9cc']
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { position: 'bottom' } }
        }
    });
}
</script>

<?php elseif ($report_type === 'progress'): ?>
<!-- Progress Report -->
<div class="card">
    <h3 class="section-title"><i class="fas fa-tasks"></i> Project Progress Overview</h3>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Project Code</th>
                    <th>Name</th>
                    <th>Status</th>
                    <th>Budget Utilization</th>
                    <th>Accomplishments</th>
                    <th>Attachments</th>
                    <th>Start Date</th>
                    <th>End Date</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($report_data['project_progress'] ?? [] as $project): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($project['project_code']); ?></strong></td>
                    <td><?php echo htmlspecialchars($project['name']); ?></td>
                    <td><span class="badge badge-<?php echo $project['status']; ?>"><?php echo ucfirst($project['status']); ?></span></td>
                    <td>
                        <div style="display:flex;align-items:center;gap:0.5rem;">
                            <div style="flex:1;height:8px;background:#e2e8f0;border-radius:4px;overflow:hidden;">
                                <div style="height:100%;background:<?php echo $project['budget_utilization'] > 90 ? '#e74a3b' : ($project['budget_utilization'] > 70 ? '#f6c23e' : '#1cc88a'); ?>;width:<?php echo min(100, $project['budget_utilization']); ?>%;"></div>
                            </div>
                            <span style="font-size:0.8rem;min-width:45px;"><?php echo number_format($project['budget_utilization'], 1); ?>%</span>
                        </div>
                    </td>
                    <td><?php echo $project['accomplishment_count']; ?></td>
                    <td><?php echo $project['attachment_count']; ?></td>
                    <td><?php echo $project['start_date'] ? date('M d, Y', strtotime($project['start_date'])) : 'N/A'; ?></td>
                    <td><?php echo $project['end_date'] ? date('M d, Y', strtotime($project['end_date'])) : 'N/A'; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <h3 class="section-title"><i class="fas fa-clipboard-check"></i> Recent Accomplishments</h3>
    <?php if (empty($report_data['recent_accomplishments'])): ?>
        <div class="empty-state">
            <i class="fas fa-clipboard-check"></i>
            <p>No recent accomplishments found.</p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Project</th>
                        <th>Description</th>
                        <th>Completion %</th>
                        <th>Date</th>
                        <th>Created By</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($report_data['recent_accomplishments'] as $accomplishment): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($accomplishment['project_code'] ?? 'N/A'); ?></strong></td>
                        <td><?php echo htmlspecialchars(substr($accomplishment['description'], 0, 60)) . (strlen($accomplishment['description']) > 60 ? '...' : ''); ?></td>
                        <td><?php echo number_format($accomplishment['completion_percentage'], 1); ?>%</td>
                        <td><?php echo date('M d, Y', strtotime($accomplishment['created_at'])); ?></td>
                        <td><?php echo htmlspecialchars($accomplishment['created_by_name'] ?? 'N/A'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php elseif ($report_type === 'materials'): ?>
<!-- Materials Report -->
<div class="card">
    <h3 class="section-title"><i class="fas fa-boxes"></i> Material Usage Analysis</h3>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Material Code</th>
                    <th>Name</th>
                    <th>Unit</th>
                    <th>Current Stock</th>
                    <th>Total Used</th>
                    <th>Inventory Value</th>
                    <th>Projects</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($report_data['material_usage'] ?? [] as $material): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($material['material_code']); ?></strong></td>
                    <td><?php echo htmlspecialchars($material['name']); ?></td>
                    <td><?php echo htmlspecialchars($material['unit']); ?></td>
                    <td><?php echo number_format($material['current_stock']); ?></td>
                    <td><?php echo number_format($material['total_used']); ?></td>
                    <td>₱<?php echo number_format($material['inventory_value'], 2); ?></td>
                    <td><?php echo $material['project_count']; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <h3 class="section-title"><i class="fas fa-exclamation-triangle"></i> Low Stock Materials</h3>
    <?php if (empty($report_data['low_stock'])): ?>
        <div class="empty-state">
            <i class="fas fa-check-circle"></i>
            <p>No low stock materials.</p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Material Code</th>
                        <th>Name</th>
                        <th>Current Stock</th>
                        <th>Min Stock</th>
                        <th>Unit</th>
                        <th>Cost per Unit</th>
                        <th>Supplier</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($report_data['low_stock'] ?? [] as $material): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($material['material_code']); ?></strong></td>
                        <td><?php echo htmlspecialchars($material['name']); ?></td>
                        <td class="progress-high"><?php echo number_format($material['current_stock']); ?></td>
                        <td><?php echo number_format($material['min_stock']); ?></td>
                        <td><?php echo htmlspecialchars($material['unit']); ?></td>
                        <td>₱<?php echo number_format($material['cost_per_unit'], 2); ?></td>
                        <td><?php echo htmlspecialchars($material['supplier_name'] ?? 'N/A'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<div class="card">
    <h3 class="section-title"><i class="fas fa-tags"></i> Material Category Breakdown</h3>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Category</th>
                    <th>Count</th>
                    <th>Total Value</th>
                    <th>Percentage</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $total_category_value = array_sum(array_column($report_data['category_breakdown'] ?? [], 'total_value'));
                foreach ($report_data['category_breakdown'] ?? [] as $category): 
                ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($category['category']); ?></strong></td>
                    <td><?php echo $category['count']; ?></td>
                    <td>₱<?php echo number_format($category['total_value'], 2); ?></td>
                    <td><?php echo $total_category_value > 0 ? number_format(($category['total_value'] / $total_category_value) * 100, 1) : 0; ?>%</td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php elseif ($report_type === 'locations'): ?>
<!-- Locations Report -->
<div class="card">
    <h3 class="section-title"><i class="fas fa-map-marker-alt"></i> Project Location Analysis</h3>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Location</th>
                    <th>Project Count</th>
                    <th>Total Budget</th>
                    <th>Total Cost</th>
                    <th>Ongoing</th>
                    <th>Completed</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($report_data['location_analysis'] ?? [] as $location): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($location['location']); ?></strong></td>
                    <td><?php echo $location['project_count']; ?></td>
                    <td>₱<?php echo number_format($location['total_budget'], 2); ?></td>
                    <td>₱<?php echo number_format($location['total_cost'], 2); ?></td>
                    <td><?php echo $location['ongoing_count']; ?></td>
                    <td><?php echo $location['completed_count']; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php elseif ($report_type === 'financial'): ?>
<!-- Financial Report -->
<div class="card">
    <h3 class="section-title"><i class="fas fa-coins"></i> Project Financial Overview</h3>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Project Code</th>
                    <th>Name</th>
                    <th>Budget</th>
                    <th>Actual Cost</th>
                    <th>Remaining</th>
                    <th>Status</th>
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
                    <td class="<?php echo $project['remaining_budget'] >= 0 ? 'progress-good' : 'progress-high'; ?>">₱<?php echo number_format($project['remaining_budget'], 2); ?></td>
                    <td><span class="badge badge-<?php echo $project['status']; ?>"><?php echo ucfirst($project['status']); ?></span></td>
                    <td><?php echo $project['invoice_count']; ?> (₱<?php echo number_format($project['invoice_total'], 2); ?>)</td>
                    <td><?php echo $project['expense_count']; ?> (₱<?php echo number_format($project['expense_total'], 2); ?>)</td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <h3 class="section-title"><i class="fas fa-chart-bar"></i> Budget Utilization (Top 10)</h3>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Project Code</th>
                    <th>Name</th>
                    <th>Budget</th>
                    <th>Actual Cost</th>
                    <th>Utilization %</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($report_data['budget_utilization'] ?? [] as $project): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($project['project_code']); ?></strong></td>
                    <td><?php echo htmlspecialchars($project['name']); ?></td>
                    <td>₱<?php echo number_format($project['estimated_budget'], 2); ?></td>
                    <td>₱<?php echo number_format($project['actual_cost'], 2); ?></td>
                    <td>
                        <div style="display:flex;align-items:center;gap:0.5rem;">
                            <div style="flex:1;height:8px;background:#e2e8f0;border-radius:4px;overflow:hidden;">
                                <div style="height:100%;background:<?php echo $project['utilization_percentage'] > 90 ? '#e74a3b' : ($project['utilization_percentage'] > 70 ? '#f6c23e' : '#1cc88a'); ?>;width:<?php echo min(100, $project['utilization_percentage']); ?>%;"></div>
                            </div>
                            <span style="font-size:0.8rem;min-width:45px;"><?php echo number_format($project['utilization_percentage'], 1); ?>%</span>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php endif; ?>

<?php include '../../includes/footer.php'; ?>
