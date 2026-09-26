<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';

requireDepartment(['procurement']);

$page_title = 'Procurement Reports';
$report_type = $_GET['report'] ?? 'overview';
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');

// Report data
$report_data = [];
try {
    switch ($report_type) {
        case 'overview':
            // General overview statistics
            $report_data['total_prs'] = $pdo->query("SELECT COUNT(*) FROM purchase_requests WHERE created_at BETWEEN '$start_date' AND '$end_date 23:59:59'")->fetchColumn() ?? 0;
            $report_data['total_pos'] = $pdo->query("SELECT COUNT(*) FROM purchase_orders WHERE created_at BETWEEN '$start_date' AND '$end_date 23:59:59'")->fetchColumn() ?? 0;
            $report_data['total_spent'] = $pdo->query("SELECT COALESCE(SUM(pr.total_amount), 0) FROM purchase_requests pr WHERE pr.status IN ('approved', 'confirmed', 'ordered', 'received') AND pr.created_at BETWEEN '$start_date' AND '$end_date 23:59:59'")->fetchColumn() ?? 0;
            $report_data['avg_po_value'] = $pdo->query("SELECT COALESCE(AVG(pr.total_amount), 0) FROM purchase_requests pr WHERE pr.status IN ('approved', 'confirmed', 'ordered', 'received') AND pr.created_at BETWEEN '$start_date' AND '$end_date 23:59:59'")->fetchColumn() ?? 0;
            
            // Monthly trend
            $report_data['monthly_trend'] = $pdo->query("
                SELECT DATE_FORMAT(created_at, '%b %Y') as month, COUNT(*) as count, COALESCE(SUM(total_amount), 0) as amount
                FROM purchase_requests
                WHERE created_at BETWEEN DATE_SUB('$start_date', INTERVAL 5 MONTH) AND '$end_date 23:59:59'
                GROUP BY YEAR(created_at), MONTH(created_at), month
                ORDER BY YEAR(created_at), MONTH(created_at)
            ")->fetchAll();
            
            // Status breakdown
            $report_data['status_breakdown'] = $pdo->query("
                SELECT status, COUNT(*) as count, COALESCE(SUM(total_amount), 0) as amount
                FROM purchase_requests
                WHERE created_at BETWEEN '$start_date' AND '$end_date 23:59:59'
                GROUP BY status
            ")->fetchAll();
            break;
            
        case 'suppliers':
            // Supplier performance
            $report_data['supplier_performance'] = $pdo->query("
                SELECT s.id, s.name, s.status, COUNT(DISTINCT m.id) as material_count,
                       COUNT(DISTINCT po.id) as po_count,
                       COALESCE(SUM(pr.total_amount), 0) as total_value
                FROM suppliers s
                LEFT JOIN materials m ON s.id = m.supplier_id
                LEFT JOIN purchase_request_items pri ON m.id = pri.material_id
                LEFT JOIN purchase_requests pr ON pri.purchase_request_id = pr.id
                LEFT JOIN purchase_orders po ON pr.id = po.purchase_request_id
                WHERE s.status = 'active'
                GROUP BY s.id, s.name, s.status
                ORDER BY total_value DESC
            ")->fetchAll();
            
            // Supplier material distribution
            $report_data['supplier_materials'] = $pdo->query("
                SELECT s.name as supplier_name, m.name as material_name, m.material_code, m.current_stock, m.cost_per_unit
                FROM materials m
                JOIN suppliers s ON m.supplier_id = s.id
                WHERE s.status = 'active' AND m.status = 'active'
                ORDER BY s.name, m.name
                LIMIT 50
            ")->fetchAll();
            break;
            
        case 'materials':
            // Material consumption
            $report_data['material_consumption'] = $pdo->query("
                SELECT m.id, m.material_code, m.name, m.category, m.unit, m.current_stock, m.min_stock, m.max_stock, m.cost_per_unit,
                       COALESCE(SUM(CASE WHEN sm.movement_type = 'in' THEN sm.quantity ELSE 0 END), 0) as total_in,
                       COALESCE(SUM(CASE WHEN sm.movement_type = 'out' THEN sm.quantity ELSE 0 END), 0) as total_out,
                       COALESCE(SUM(CASE WHEN sm.movement_type = 'in' THEN sm.quantity ELSE 0 END), 0) - 
                       COALESCE(SUM(CASE WHEN sm.movement_type = 'out' THEN sm.quantity ELSE 0 END), 0) as net_movement
                FROM materials m
                LEFT JOIN stock_movements sm ON m.id = sm.material_id
                WHERE m.status = 'active'
                GROUP BY m.id, m.material_code, m.name, m.category, m.unit, m.current_stock, m.min_stock, m.max_stock, m.cost_per_unit
                HAVING total_out > 0
                ORDER BY total_out DESC
                LIMIT 20
            ")->fetchAll();
            
            // Low stock materials
            $report_data['low_stock'] = $pdo->query("
                SELECT m.*, s.name as supplier_name
                FROM materials m
                LEFT JOIN suppliers s ON m.supplier_id = s.id
                WHERE m.current_stock <= m.min_stock AND m.min_stock > 0 AND m.status = 'active'
                ORDER BY (m.current_stock / GREATEST(m.min_stock, 1)) ASC
            ")->fetchAll();
            
            // Material category breakdown
            $report_data['category_breakdown'] = $pdo->query("
                SELECT category, COUNT(*) as count, COALESCE(SUM(current_stock * cost_per_unit), 0) as total_value
                FROM materials
                WHERE status = 'active' AND category IS NOT NULL AND category != ''
                GROUP BY category
                ORDER BY total_value DESC
            ")->fetchAll();
            break;
            
        case 'costs':
            // Cost analysis
            $report_data['cost_by_category'] = $pdo->query("
                SELECT m.category, COALESCE(SUM(pri.quantity * pri.estimated_cost), 0) as total_cost
                FROM purchase_request_items pri
                JOIN materials m ON pri.material_id = m.id
                JOIN purchase_requests pr ON pri.purchase_request_id = pr.id
                WHERE pr.created_at BETWEEN '$start_date' AND '$end_date 23:59:59'
                GROUP BY m.category
                ORDER BY total_cost DESC
            ")->fetchAll();
            
            // Cost trend over time
            $report_data['cost_trend'] = $pdo->query("
                SELECT DATE_FORMAT(pr.created_at, '%b %Y') as month, COALESCE(SUM(pr.total_amount), 0) as cost
                FROM purchase_requests pr
                WHERE pr.created_at BETWEEN DATE_SUB('$start_date', INTERVAL 5 MONTH) AND '$end_date 23:59:59'
                GROUP BY YEAR(pr.created_at), MONTH(pr.created_at), month
                ORDER BY YEAR(pr.created_at), MONTH(pr.created_at)
            ")->fetchAll();
            
            // Top expensive materials
            $report_data['expensive_materials'] = $pdo->query("
                SELECT m.material_code, m.name, m.category, m.cost_per_unit, m.current_stock,
                       (m.cost_per_unit * m.current_stock) as inventory_value
                FROM materials m
                WHERE m.status = 'active'
                ORDER BY inventory_value DESC
                LIMIT 15
            ")->fetchAll();
            break;
            
        case 'performance':
            // Procurement performance metrics
            $report_data['approval_rate'] = $pdo->query("
                SELECT 
                    COUNT(CASE WHEN status = 'approved' THEN 1 END) as approved,
                    COUNT(CASE WHEN status = 'rejected' THEN 1 END) as rejected,
                    COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending,
                    COUNT(*) as total
                FROM purchase_requests
                WHERE created_at BETWEEN '$start_date' AND '$end_date 23:59:59'
            ")->fetch();
            
            $report_data['avg_processing_time'] = $pdo->query("
                SELECT AVG(DATEDIFF(COALESCE(approved_at, confirmed_at, created_at), created_at)) as avg_days
                FROM purchase_requests
                WHERE status IN ('approved', 'confirmed') AND created_at BETWEEN '$start_date' AND '$end_date 23:59:59'
            ")->fetchColumn() ?? 0;
            
            $report_data['po_conversion_rate'] = $pdo->query("
                SELECT 
                    (COUNT(DISTINCT po.id) * 100.0 / GREATEST(COUNT(DISTINCT pr.id), 1)) as conversion_rate
                FROM purchase_requests pr
                LEFT JOIN purchase_orders po ON pr.id = po.purchase_request_id
                WHERE pr.created_at BETWEEN '$start_date' AND '$end_date 23:59:59'
            ")->fetchColumn() ?? 0;
            
            // Priority distribution
            $report_data['priority_distribution'] = $pdo->query("
                SELECT priority, COUNT(*) as count
                FROM purchase_requests
                WHERE created_at BETWEEN '$start_date' AND '$end_date 23:59:59'
                GROUP BY priority
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
.chart-container {
    margin: 1.5rem 0;
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
</div>

<?php if (isset($error)): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<!-- Report Navigation -->
<div class="reports-nav">
    <a href="?report=overview&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" class="btn <?php echo $report_type === 'overview' ? 'active' : 'btn-secondary'; ?>"><i class="fas fa-tachometer-alt"></i> Overview</a>
    <a href="?report=suppliers&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" class="btn <?php echo $report_type === 'suppliers' ? 'active' : 'btn-secondary'; ?>"><i class="fas fa-truck"></i> Suppliers</a>
    <a href="?report=materials&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" class="btn <?php echo $report_type === 'materials' ? 'active' : 'btn-secondary'; ?>"><i class="fas fa-boxes"></i> Materials</a>
    <a href="?report=costs&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" class="btn <?php echo $report_type === 'costs' ? 'active' : 'btn-secondary'; ?>"><i class="fas fa-coins"></i> Costs</a>
    <a href="?report=performance&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" class="btn <?php echo $report_type === 'performance' ? 'active' : 'btn-secondary'; ?>"><i class="fas fa-chart-line"></i> Performance</a>
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
        <div class="value"><?php echo number_format($report_data['total_prs'] ?? 0); ?></div>
        <div class="label">Total Purchase Requests</div>
    </div>
    <div class="stat-card" style="border-left-color:#1cc88a;">
        <div class="value"><?php echo number_format($report_data['total_pos'] ?? 0); ?></div>
        <div class="label">Purchase Orders Created</div>
    </div>
    <div class="stat-card" style="border-left-color:#f6c23e;">
        <div class="value">₱<?php echo number_format($report_data['total_spent'] ?? 0, 2); ?></div>
        <div class="label">Total Amount Spent</div>
    </div>
    <div class="stat-card" style="border-left-color:#36b9cc;">
        <div class="value">₱<?php echo number_format($report_data['avg_po_value'] ?? 0, 2); ?></div>
        <div class="label">Average PO Value</div>
    </div>
</div>

<div class="dashboard-grid">
    <div class="card chart-card">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-chart-line"></i> Monthly Trend</h3>
        </div>
        <canvas id="monthlyTrendChart"></canvas>
    </div>
    <div class="card chart-card">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-chart-pie"></i> Status Breakdown</h3>
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
                label: 'Amount (₱)',
                data: <?php echo json_encode(array_map('floatval', array_column($report_data['monthly_trend'] ?? [], 'amount'))); ?>,
                borderColor: '#f59e0b',
                backgroundColor: 'rgba(245,158,11,0.1)',
                fill: true,
                tension: 0.35
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true } }
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
                backgroundColor: ['#4e73df', '#1cc88a', '#f6c23e', '#e74a3b', '#36b9cc', '#858796']
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { position: 'bottom' } }
        }
    });
}
</script>

<?php elseif ($report_type === 'suppliers'): ?>
<!-- Supplier Report -->
<div class="card">
    <h3 class="section-title"><i class="fas fa-truck"></i> Supplier Performance</h3>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Supplier Name</th>
                    <th>Status</th>
                    <th>Materials Count</th>
                    <th>PO Count</th>
                    <th>Total Value</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($report_data['supplier_performance'] ?? [] as $supplier): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($supplier['name']); ?></strong></td>
                    <td><span class="badge badge-<?php echo $supplier['status'] == 'active' ? 'success' : 'secondary'; ?>"><?php echo ucfirst($supplier['status']); ?></span></td>
                    <td><?php echo number_format($supplier['material_count']); ?></td>
                    <td><?php echo number_format($supplier['po_count']); ?></td>
                    <td>₱<?php echo number_format($supplier['total_value'], 2); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php elseif ($report_type === 'materials'): ?>
<!-- Materials Report -->
<div class="card">
    <h3 class="section-title"><i class="fas fa-exclamation-triangle"></i> Low Stock Materials</h3>
    <?php if (empty($report_data['low_stock'] ?? [])): ?>
        <div class="empty-state">
            <i class="fas fa-check-circle"></i>
            <p>No materials with low stock.</p>
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
                        <th>Supplier</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($report_data['low_stock'] ?? [] as $material): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($material['material_code']); ?></strong></td>
                        <td><?php echo htmlspecialchars($material['name']); ?></td>
                        <td style="color:#e74a3b;font-weight:700;"><?php echo number_format($material['current_stock']); ?></td>
                        <td><?php echo number_format($material['min_stock']); ?></td>
                        <td><?php echo htmlspecialchars($material['unit']); ?></td>
                        <td><?php echo htmlspecialchars($material['supplier_name'] ?? 'N/A'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<div class="card">
    <h3 class="section-title"><i class="fas fa-chart-bar"></i> Material Consumption (Top 20)</h3>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Material Code</th>
                    <th>Name</th>
                    <th>Category</th>
                    <th>Total In</th>
                    <th>Total Out</th>
                    <th>Net Movement</th>
                    <th>Current Stock</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($report_data['material_consumption'] ?? [] as $material): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($material['material_code']); ?></strong></td>
                    <td><?php echo htmlspecialchars($material['name']); ?></td>
                    <td><?php echo htmlspecialchars($material['category'] ?? 'N/A'); ?></td>
                    <td><?php echo number_format($material['total_in']); ?></td>
                    <td><?php echo number_format($material['total_out']); ?></td>
                    <td><?php echo number_format($material['net_movement']); ?></td>
                    <td><?php echo number_format($material['current_stock']); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php elseif ($report_type === 'costs'): ?>
<!-- Cost Report -->
<div class="card">
    <h3 class="section-title"><i class="fas fa-chart-pie"></i> Cost by Category</h3>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Category</th>
                    <th>Total Cost</th>
                    <th>Percentage</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $total_cost = array_sum(array_column($report_data['cost_by_category'] ?? [], 'total_cost'));
                foreach ($report_data['cost_by_category'] ?? [] as $category): 
                ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($category['category']); ?></strong></td>
                    <td>₱<?php echo number_format($category['total_cost'], 2); ?></td>
                    <td><?php echo $total_cost > 0 ? number_format(($category['total_cost'] / $total_cost) * 100, 1) : 0; ?>%</td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <h3 class="section-title"><i class="fas fa-dollar-sign"></i> Top Expensive Materials by Inventory Value</h3>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Material Code</th>
                    <th>Name</th>
                    <th>Category</th>
                    <th>Unit Cost</th>
                    <th>Current Stock</th>
                    <th>Inventory Value</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($report_data['expensive_materials'] ?? [] as $material): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($material['material_code']); ?></strong></td>
                    <td><?php echo htmlspecialchars($material['name']); ?></td>
                    <td><?php echo htmlspecialchars($material['category'] ?? 'N/A'); ?></td>
                    <td>₱<?php echo number_format($material['cost_per_unit'], 2); ?></td>
                    <td><?php echo number_format($material['current_stock']); ?></td>
                    <td style="color:#1cc88a;font-weight:700;">₱<?php echo number_format($material['inventory_value'], 2); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php elseif ($report_type === 'performance'): ?>
<!-- Performance Report -->
<div class="stat-cards">
    <div class="stat-card">
        <div class="value"><?php echo number_format($report_data['approval_rate']['total'] ?? 0); ?></div>
        <div class="label">Total Requests</div>
    </div>
    <div class="stat-card" style="border-left-color:#1cc88a;">
        <div class="value"><?php echo number_format($report_data['approval_rate']['approved'] ?? 0); ?></div>
        <div class="label">Approved</div>
    </div>
    <div class="stat-card" style="border-left-color:#e74a3b;">
        <div class="value"><?php echo number_format($report_data['approval_rate']['rejected'] ?? 0); ?></div>
        <div class="label">Rejected</div>
    </div>
    <div class="stat-card" style="border-left-color:#f6c23e;">
        <div class="value"><?php echo number_format($report_data['approval_rate']['pending'] ?? 0); ?></div>
        <div class="label">Pending</div>
    </div>
</div>

<div class="stat-cards">
    <div class="stat-card" style="border-left-color:#36b9cc;">
        <div class="value"><?php echo number_format($report_data['avg_processing_time'] ?? 0, 1); ?> days</div>
        <div class="label">Average Processing Time</div>
    </div>
    <div class="stat-card" style="border-left-color:#4e73df;">
        <div class="value"><?php echo number_format($report_data['po_conversion_rate'] ?? 0, 1); ?>%</div>
        <div class="label">PO Conversion Rate</div>
    </div>
</div>

<div class="card">
    <h3 class="section-title"><i class="fas fa-flag"></i> Priority Distribution</h3>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Priority</th>
                    <th>Count</th>
                    <th>Percentage</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $total_priority = array_sum(array_column($report_data['priority_distribution'] ?? [], 'count'));
                foreach ($report_data['priority_distribution'] ?? [] as $priority): 
                ?>
                <tr>
                    <td><span class="badge badge-<?php echo $priority['priority']; ?>"><?php echo ucfirst($priority['priority']); ?></span></td>
                    <td><?php echo number_format($priority['count']); ?></td>
                    <td><?php echo $total_priority > 0 ? number_format(($priority['count'] / $total_priority) * 100, 1) : 0; ?>%</td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php endif; ?>

<?php include '../../includes/footer.php'; ?>
