<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';

requireDepartment(['warehouse']);

$page_title = 'Inventory Reports';
$report_type = $_GET['report'] ?? 'overview';
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');

// Report data
$report_data = [];
try {
    switch ($report_type) {
        case 'overview':
            // General inventory overview
            $report_data['total_items'] = $pdo->query("SELECT COUNT(*) FROM materials WHERE status = 'active'")->fetchColumn() ?? 0;
            $report_data['total_stock'] = $pdo->query("SELECT COALESCE(SUM(current_stock), 0) FROM materials WHERE status = 'active'")->fetchColumn() ?? 0;
            $report_data['total_value'] = $pdo->query("SELECT COALESCE(SUM(current_stock * cost_per_unit), 0) FROM materials WHERE status = 'active'")->fetchColumn() ?? 0;
            $report_data['low_stock'] = $pdo->query("SELECT COUNT(*) FROM materials WHERE current_stock <= min_stock AND min_stock > 0 AND status = 'active'")->fetchColumn() ?? 0;
            
            // Stock movement trends
            $report_data['movement_trend'] = $pdo->query("
                SELECT DATE_FORMAT(created_at, '%b %Y') as month, 
                       movement_type,
                       COUNT(*) as count,
                       COALESCE(SUM(quantity), 0) as total_quantity
                FROM stock_movements
                WHERE created_at BETWEEN DATE_SUB('$start_date', INTERVAL 5 MONTH) AND '$end_date 23:59:59'
                GROUP BY YEAR(created_at), MONTH(created_at), month, movement_type
                ORDER BY YEAR(created_at), MONTH(created_at)
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
            
        case 'movements':
            // Stock movement analysis
            $report_data['stock_in'] = $pdo->query("
                SELECT DATE_FORMAT(created_at, '%b %Y') as month, COUNT(*) as count, COALESCE(SUM(quantity), 0) as total_quantity
                FROM stock_movements
                WHERE movement_type = 'in' AND created_at BETWEEN DATE_SUB('$start_date', INTERVAL 5 MONTH) AND '$end_date 23:59:59'
                GROUP BY YEAR(created_at), MONTH(created_at), month
                ORDER BY YEAR(created_at), MONTH(created_at)
            ")->fetchAll();
            
            $report_data['stock_out'] = $pdo->query("
                SELECT DATE_FORMAT(created_at, '%b %Y') as month, COUNT(*) as count, COALESCE(SUM(quantity), 0) as total_quantity
                FROM stock_movements
                WHERE movement_type = 'out' AND created_at BETWEEN DATE_SUB('$start_date', INTERVAL 5 MONTH) AND '$end_date 23:59:59'
                GROUP BY YEAR(created_at), MONTH(created_at), month
                ORDER BY YEAR(created_at), MONTH(created_at)
            ")->fetchAll();
            
            // Top materials by movement
            $report_data['top_materials'] = $pdo->query("
                SELECT m.material_code, m.name, m.unit,
                       COUNT(sm.id) as movement_count,
                       COALESCE(SUM(CASE WHEN sm.movement_type = 'in' THEN sm.quantity ELSE 0 END), 0) as total_in,
                       COALESCE(SUM(CASE WHEN sm.movement_type = 'out' THEN sm.quantity ELSE 0 END), 0) as total_out
                FROM materials m
                LEFT JOIN stock_movements sm ON m.id = sm.material_id AND sm.created_at BETWEEN '$start_date' AND '$end_date 23:59:59'
                WHERE m.status = 'active'
                GROUP BY m.id, m.material_code, m.name, m.unit
                HAVING movement_count > 0
                ORDER BY movement_count DESC
                LIMIT 10
            ")->fetchAll();
            break;
            
        case 'low_stock':
            // Low stock analysis
            $report_data['low_stock_materials'] = $pdo->query("
                SELECT m.material_code, m.name, m.unit, m.current_stock, m.min_stock, m.max_stock, m.cost_per_unit,
                       s.name as supplier_name,
                       (m.min_stock - m.current_stock) as shortage,
                       COUNT(DISTINCT pr.id) as pr_count
                FROM materials m
                LEFT JOIN suppliers s ON m.supplier_id = s.id
                LEFT JOIN purchase_request_items pri ON m.id = pri.material_id
                LEFT JOIN purchase_requests pr ON pri.purchase_request_id = pr.id AND pr.status IN ('pending', 'approved', 'confirmed')
                WHERE m.status = 'active' AND m.current_stock <= m.min_stock AND m.min_stock > 0
                GROUP BY m.id, m.material_code, m.name, m.unit, m.current_stock, m.min_stock, m.max_stock, m.cost_per_unit, s.name
                ORDER BY shortage DESC
            ")->fetchAll();
            
            // Reorder priority
            $report_data['reorder_priority'] = $pdo->query("
                SELECT m.material_code, m.name, m.unit, m.current_stock, m.min_stock, m.cost_per_unit,
                       COUNT(DISTINCT pr.id) as demand_count
                FROM materials m
                LEFT JOIN purchase_request_items pri ON m.id = pri.material_id
                LEFT JOIN purchase_requests pr ON pri.purchase_request_id = pr.id AND pr.status IN ('approved', 'confirmed', 'ordered')
                WHERE m.status = 'active'
                GROUP BY m.id, m.material_code, m.name, m.unit, m.current_stock, m.min_stock, m.cost_per_unit
                HAVING current_stock <= min_stock OR demand_count > 0
                ORDER BY (demand_count * cost_per_unit) DESC
                LIMIT 15
            ")->fetchAll();
            break;
            
        case 'valuation':
            // Inventory valuation
            $report_data['material_valuation'] = $pdo->query("
                SELECT m.material_code, m.name, m.unit, m.current_stock, m.cost_per_unit,
                       (m.current_stock * m.cost_per_unit) as total_value,
                       m.category
                FROM materials m
                WHERE m.status = 'active' AND m.current_stock > 0
                ORDER BY total_value DESC
                LIMIT 20
            ")->fetchAll();
            
            $report_data['category_valuation'] = $pdo->query("
                SELECT category, COUNT(*) as count, COALESCE(SUM(current_stock * cost_per_unit), 0) as total_value
                FROM materials
                WHERE status = 'active' AND category IS NOT NULL AND category != ''
                GROUP BY category
                ORDER BY total_value DESC
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
</div>

<?php if (isset($error)): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<!-- Report Navigation -->
<div class="reports-nav">
    <a href="?report=overview&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" class="btn <?php echo $report_type === 'overview' ? 'active' : 'btn-secondary'; ?>"><i class="fas fa-tachometer-alt"></i> Overview</a>
    <a href="?report=movements&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" class="btn <?php echo $report_type === 'movements' ? 'active' : 'btn-secondary'; ?>"><i class="fas fa-exchange-alt"></i> Movements</a>
    <a href="?report=low_stock&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" class="btn <?php echo $report_type === 'low_stock' ? 'active' : 'btn-secondary'; ?>"><i class="fas fa-exclamation-triangle"></i> Low Stock</a>
    <a href="?report=valuation&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" class="btn <?php echo $report_type === 'valuation' ? 'active' : 'btn-secondary'; ?>"><i class="fas fa-coins"></i> Valuation</a>
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
        <div class="value"><?php echo number_format($report_data['total_items'] ?? 0); ?></div>
        <div class="label">Total Items</div>
    </div>
    <div class="stat-card" style="border-left-color:#36b9cc;">
        <div class="value"><?php echo number_format($report_data['total_stock'] ?? 0); ?></div>
        <div class="label">Total Stock</div>
    </div>
    <div class="stat-card" style="border-left-color:#1cc88a;">
        <div class="value">₱<?php echo number_format($report_data['total_value'] ?? 0, 2); ?></div>
        <div class="label">Total Value</div>
    </div>
    <div class="stat-card" style="border-left-color:#e74a3b;">
        <div class="value"><?php echo number_format($report_data['low_stock'] ?? 0); ?></div>
        <div class="label">Low Stock Items</div>
    </div>
</div>

<div class="dashboard-grid">
    <div class="card chart-card">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-chart-line"></i> Movement Trend</h3>
        </div>
        <canvas id="movementTrendChart"></canvas>
    </div>
    <div class="card chart-card">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-chart-pie"></i> Category Breakdown</h3>
        </div>
        <canvas id="categoryBreakdownChart"></canvas>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>
const movementTrendCtx = document.getElementById('movementTrendChart');
if (movementTrendCtx) {
    const months = [...new Set(<?php echo json_encode(array_column($report_data['movement_trend'] ?? [], 'month')); ?>)];
    const stockInData = months.map(m => <?php echo json_encode(array_column($report_data['movement_trend'] ?? [], 'total_quantity')); ?>.reduce((sum, val, i) => {
        const trend = <?php echo json_encode($report_data['movement_trend'] ?? []); ?>;
        return trend[i]?.movement_type === 'in' ? sum + val : sum;
    }, 0));
    const stockOutData = months.map(m => <?php echo json_encode(array_column($report_data['movement_trend'] ?? [], 'total_quantity')); ?>.reduce((sum, val, i) => {
        const trend = <?php echo json_encode($report_data['movement_trend'] ?? []); ?>;
        return trend[i]?.movement_type === 'out' ? sum + val : sum;
    }, 0));
    
    new Chart(movementTrendCtx, {
        type: 'line',
        data: {
            labels: months,
            datasets: [
                { label: 'Stock-In', data: stockInData, borderColor: '#1cc88a', backgroundColor: 'rgba(28,200,138,0.1)', fill: true, tension: 0.35 },
                { label: 'Stock-Out', data: stockOutData, borderColor: '#e74a3b', backgroundColor: 'rgba(231,74,59,0.1)', fill: true, tension: 0.35 }
            ]
        },
        options: { responsive: true, plugins: { legend: { position: 'bottom' } }, scales: { y: { beginAtZero: true } } }
    });
}

const categoryBreakdownCtx = document.getElementById('categoryBreakdownChart');
if (categoryBreakdownCtx) {
    new Chart(categoryBreakdownCtx, {
        type: 'doughnut',
        data: {
            labels: <?php echo json_encode(array_column($report_data['category_breakdown'] ?? [], 'category')); ?>,
            datasets: [{
                data: <?php echo json_encode(array_map('floatval', array_column($report_data['category_breakdown'] ?? [], 'total_value'))); ?>,
                backgroundColor: ['#4e73df', '#1cc88a', '#f6c23e', '#e74a3b', '#36b9cc', '#858796']
            }]
        },
        options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
    });
}
</script>

<?php elseif ($report_type === 'movements'): ?>
<!-- Movements Report -->
<div class="card">
    <h3 class="section-title"><i class="fas fa-chart-line"></i> Stock-In Trend</h3>
    <canvas id="stockInChart"></canvas>
</div>

<div class="card">
    <h3 class="section-title"><i class="fas fa-chart-line"></i> Stock-Out Trend</h3>
    <canvas id="stockOutChart"></canvas>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>
const stockInCtx = document.getElementById('stockInChart');
if (stockInCtx) {
    new Chart(stockInCtx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode(array_column($report_data['stock_in'] ?? [], 'month')); ?>,
            datasets: [{
                label: 'Stock-In Quantity',
                data: <?php echo json_encode(array_map('floatval', array_column($report_data['stock_in'] ?? [], 'total_quantity'))); ?>,
                borderColor: '#1cc88a',
                backgroundColor: 'rgba(28,200,138,0.1)',
                fill: true,
                tension: 0.35
            }]
        },
        options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
    });
}

const stockOutCtx = document.getElementById('stockOutChart');
if (stockOutCtx) {
    new Chart(stockOutCtx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode(array_column($report_data['stock_out'] ?? [], 'month')); ?>,
            datasets: [{
                label: 'Stock-Out Quantity',
                data: <?php echo json_encode(array_map('floatval', array_column($report_data['stock_out'] ?? [], 'total_quantity'))); ?>,
                borderColor: '#e74a3b',
                backgroundColor: 'rgba(231,74,59,0.1)',
                fill: true,
                tension: 0.35
            }]
        },
        options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
    });
}
</script>

<div class="card">
    <h3 class="section-title"><i class="fas fa-trophy"></i> Top Materials by Movement</h3>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Material Code</th>
                    <th>Name</th>
                    <th>Movement Count</th>
                    <th>Total In</th>
                    <th>Total Out</th>
                    <th>Net</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($report_data['top_materials'] ?? [] as $material): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($material['material_code']); ?></strong></td>
                    <td><?php echo htmlspecialchars($material['name']); ?></td>
                    <td><?php echo $material['movement_count']; ?></td>
                    <td class="text-success"><?php echo number_format($material['total_in']); ?></td>
                    <td class="text-danger"><?php echo number_format($material['total_out']); ?></td>
                    <td class="<?php echo ($material['total_in'] - $material['total_out']) >= 0 ? 'text-success' : 'text-danger'; ?>"><?php echo number_format($material['total_in'] - $material['total_out']); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php elseif ($report_type === 'low_stock'): ?>
<!-- Low Stock Report -->
<div class="card">
    <h3 class="section-title"><i class="fas fa-exclamation-triangle"></i> Low Stock Materials</h3>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Material Code</th>
                    <th>Name</th>
                    <th>Current Stock</th>
                    <th>Min Stock</th>
                    <th>Shortage</th>
                    <th>Unit</th>
                    <th>Cost per Unit</th>
                    <th>Supplier</th>
                    <th>Pending PRs</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($report_data['low_stock_materials'] ?? [] as $material): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($material['material_code']); ?></strong></td>
                    <td><?php echo htmlspecialchars($material['name']); ?></td>
                    <td class="text-danger"><?php echo number_format($material['current_stock']); ?></td>
                    <td><?php echo number_format($material['min_stock']); ?></td>
                    <td class="text-danger"><?php echo number_format($material['shortage']); ?></td>
                    <td><?php echo htmlspecialchars($material['unit']); ?></td>
                    <td>₱<?php echo number_format($material['cost_per_unit'], 2); ?></td>
                    <td><?php echo htmlspecialchars($material['supplier_name'] ?? 'N/A'); ?></td>
                    <td><?php echo $material['pr_count']; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <h3 class="section-title"><i class="fas fa-sort-amount-down"></i> Reorder Priority</h3>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Material Code</th>
                    <th>Name</th>
                    <th>Current Stock</th>
                    <th>Min Stock</th>
                    <th>Cost per Unit</th>
                    <th>Demand Count</th>
                    <th>Priority Score</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($report_data['reorder_priority'] ?? [] as $material): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($material['material_code']); ?></strong></td>
                    <td><?php echo htmlspecialchars($material['name']); ?></td>
                    <td class="<?php echo $material['current_stock'] <= $material['min_stock'] ? 'text-danger' : 'text-success'; ?>"><?php echo number_format($material['current_stock']); ?></td>
                    <td><?php echo number_format($material['min_stock']); ?></td>
                    <td>₱<?php echo number_format($material['cost_per_unit'], 2); ?></td>
                    <td><?php echo $material['demand_count']; ?></td>
                    <td class="text-warning"><?php echo number_format($material['demand_count'] * $material['cost_per_unit'], 2); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php elseif ($report_type === 'valuation'): ?>
<!-- Valuation Report -->
<div class="card">
    <h3 class="section-title"><i class="fas fa-coins"></i> Material Valuation (Top 20)</h3>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Material Code</th>
                    <th>Name</th>
                    <th>Category</th>
                    <th>Current Stock</th>
                    <th>Cost per Unit</th>
                    <th>Total Value</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($report_data['material_valuation'] ?? [] as $material): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($material['material_code']); ?></strong></td>
                    <td><?php echo htmlspecialchars($material['name']); ?></td>
                    <td><?php echo htmlspecialchars($material['category'] ?? 'N/A'); ?></td>
                    <td><?php echo number_format($material['current_stock']); ?></td>
                    <td>₱<?php echo number_format($material['cost_per_unit'], 2); ?></td>
                    <td>₱<?php echo number_format($material['total_value'], 2); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <h3 class="section-title"><i class="fas fa-tags"></i> Category Valuation</h3>
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
                $total_category_value = array_sum(array_column($report_data['category_valuation'] ?? [], 'total_value'));
                foreach ($report_data['category_valuation'] ?? [] as $category): 
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

<?php endif; ?>

<?php include '../../includes/footer.php'; ?>
