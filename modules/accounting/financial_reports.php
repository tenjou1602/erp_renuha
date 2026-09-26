<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';

requireDepartment(['accounting']);

$page_title = 'Financial Reports';
$report_type = $_GET['report'] ?? 'overview';
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');

// Report data
$report_data = [];
try {
    switch ($report_type) {
        case 'overview':
            // General overview statistics
            $report_data['total_revenue'] = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM invoices WHERE status = 'paid' AND created_at BETWEEN '$start_date' AND '$end_date 23:59:59'")->fetchColumn() ?? 0;
            $report_data['total_expenses'] = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE status = 'paid' AND created_at BETWEEN '$start_date' AND '$end_date 23:59:59'")->fetchColumn() ?? 0;
            $report_data['total_payroll'] = $pdo->query("SELECT COALESCE(SUM(net_pay), 0) FROM payroll WHERE status = 'paid' AND created_at BETWEEN '$start_date' AND '$end_date 23:59:59'")->fetchColumn() ?? 0;
            $report_data['purchase_costs'] = $pdo->query("SELECT COALESCE(SUM(total_amount), 0) FROM purchase_requests WHERE status IN ('approved', 'confirmed', 'ordered', 'received') AND created_at BETWEEN '$start_date' AND '$end_date 23:59:59'")->fetchColumn() ?? 0;
            $report_data['net_profit'] = $report_data['total_revenue'] - $report_data['total_expenses'] - $report_data['total_payroll'] - $report_data['purchase_costs'];
            
            // Monthly trend
            $report_data['monthly_trend'] = $pdo->query("
                SELECT DATE_FORMAT(created_at, '%b %Y') as month, 
                       COALESCE(SUM(CASE WHEN status = 'paid' THEN amount ELSE 0 END), 0) as revenue,
                       COALESCE(SUM(CASE WHEN status = 'paid' THEN amount ELSE 0 END), 0) as expenses
                FROM invoices
                WHERE created_at BETWEEN DATE_SUB('$start_date', INTERVAL 5 MONTH) AND '$end_date 23:59:59'
                GROUP BY YEAR(created_at), MONTH(created_at), month
                ORDER BY YEAR(created_at), MONTH(created_at)
            ")->fetchAll();
            
            // Expense breakdown
            $report_data['expense_breakdown'] = $pdo->query("
                SELECT category, COUNT(*) as count, COALESCE(SUM(amount), 0) as total
                FROM expenses
                WHERE created_at BETWEEN '$start_date' AND '$end_date 23:59:59'
                GROUP BY category
                ORDER BY total DESC
            ")->fetchAll();
            break;
            
        case 'projects':
            // Project financial performance
            $report_data['project_performance'] = $pdo->query("
                SELECT 
                    p.id,
                    p.project_code,
                    p.name,
                    p.estimated_budget,
                    p.actual_cost,
                    (p.estimated_budget - p.actual_cost) as remaining_budget,
                    p.status,
                    COUNT(DISTINCT inv.id) as invoice_count,
                    COALESCE(SUM(CASE WHEN inv.status = 'paid' THEN inv.amount ELSE 0 END), 0) as paid_invoices,
                    COALESCE(SUM(inv.amount), 0) as total_invoices,
                    COUNT(DISTINCT exp.id) as expense_count,
                    COALESCE(SUM(CASE WHEN exp.status = 'paid' THEN exp.amount ELSE 0 END), 0) as paid_expenses,
                    COALESCE(SUM(exp.amount), 0) as total_expenses
                FROM projects p
                LEFT JOIN invoices inv ON p.id = inv.project_id
                LEFT JOIN expenses exp ON p.id = exp.project_id
                GROUP BY p.id, p.project_code, p.name, p.estimated_budget, p.actual_cost, p.status
                ORDER BY p.created_at DESC
            ")->fetchAll();
            
            // Project budget utilization
            $report_data['budget_utilization'] = $pdo->query("
                SELECT 
                    p.project_code,
                    p.name,
                    p.estimated_budget,
                    p.actual_cost,
                    (p.actual_cost / GREATEST(p.estimated_budget, 1)) * 100 as utilization_percentage
                FROM projects p
                WHERE p.estimated_budget > 0
                ORDER BY utilization_percentage DESC
                LIMIT 10
            ")->fetchAll();
            break;
            
        case 'cashflow':
            // Cash flow analysis
            $report_data['cash_inflow'] = $pdo->query("
                SELECT DATE_FORMAT(created_at, '%b %Y') as month, COALESCE(SUM(amount), 0) as total
                FROM invoices
                WHERE status = 'paid' AND created_at BETWEEN DATE_SUB('$start_date', INTERVAL 5 MONTH) AND '$end_date 23:59:59'
                GROUP BY YEAR(created_at), MONTH(created_at), month
                ORDER BY YEAR(created_at), MONTH(created_at)
            ")->fetchAll();
            
            $report_data['cash_outflow'] = $pdo->query("
                SELECT DATE_FORMAT(created_at, '%b %Y') as month, COALESCE(SUM(amount), 0) as total
                FROM expenses
                WHERE status = 'paid' AND created_at BETWEEN DATE_SUB('$start_date', INTERVAL 5 MONTH) AND '$end_date 23:59:59'
                GROUP BY YEAR(created_at), MONTH(created_at), month
                ORDER BY YEAR(created_at), MONTH(created_at)
            ")->fetchAll();
            
            $report_data['payroll_outflow'] = $pdo->query("
                SELECT DATE_FORMAT(created_at, '%b %Y') as month, COALESCE(SUM(net_pay), 0) as total
                FROM payroll
                WHERE status = 'paid' AND created_at BETWEEN DATE_SUB('$start_date', INTERVAL 5 MONTH) AND '$end_date 23:59:59'
                GROUP BY YEAR(created_at), MONTH(created_at), month
                ORDER BY YEAR(created_at), MONTH(created_at)
            ")->fetchAll();
            break;
            
        case 'budgets':
            // Budget vs actual analysis
            $report_data['funds_status'] = $pdo->query("
                SELECT 
                    fund_name,
                    fund_type,
                    amount,
                    allocated_amount,
                    (amount - allocated_amount) as available,
                    project_id,
                    status
                FROM funds
                WHERE status = 'active'
                ORDER BY available DESC
            ")->fetchAll();
            
            $report_data['labor_budgets'] = $pdo->query("
                SELECT 
                    budget_name,
                    project_id,
                    allocated_amount,
                    used_amount,
                    (allocated_amount - used_amount) as remaining,
                    status
                FROM labor_budgets
                WHERE status = 'active'
                ORDER BY remaining ASC
            ")->fetchAll();
            
            $report_data['contract_status'] = $pdo->query("
                SELECT 
                    contract_name,
                    contract_value,
                    start_date,
                    end_date,
                    status
                FROM contracts
                ORDER BY end_date ASC
            ")->fetchAll();
            break;
            
        case 'procurement':
            // Procurement cost analysis
            $report_data['procurement_costs'] = $pdo->query("
                SELECT 
                    DATE_FORMAT(created_at, '%b %Y') as month,
                    COALESCE(SUM(total_amount), 0) as total_cost,
                    COUNT(*) as request_count
                FROM purchase_requests
                WHERE status IN ('approved', 'confirmed', 'ordered', 'received') 
                AND created_at BETWEEN DATE_SUB('$start_date', INTERVAL 5 MONTH) AND '$end_date 23:59:59'
                GROUP BY YEAR(created_at), MONTH(created_at), month
                ORDER BY YEAR(created_at), MONTH(created_at)
            ")->fetchAll();
            
            $report_data['supplier_performance'] = $pdo->query("
                SELECT 
                    s.name as supplier_name,
                    COUNT(DISTINCT pr.id) as request_count,
                    COALESCE(SUM(pr.total_amount), 0) as total_value
                FROM suppliers s
                LEFT JOIN materials m ON s.id = m.supplier_id
                LEFT JOIN purchase_request_items pri ON m.id = pri.material_id
                LEFT JOIN purchase_requests pr ON pri.purchase_request_id = pr.id AND pr.status IN ('approved', 'confirmed', 'ordered', 'received')
                WHERE s.status = 'active'
                GROUP BY s.id, s.name
                HAVING total_value > 0
                ORDER BY total_value DESC
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
.profit-positive { color: #1cc88a; }
.profit-negative { color: #e74a3b; }
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
    <a href="?report=projects&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" class="btn <?php echo $report_type === 'projects' ? 'active' : 'btn-secondary'; ?>"><i class="fas fa-project-diagram"></i> Projects</a>
    <a href="?report=cashflow&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" class="btn <?php echo $report_type === 'cashflow' ? 'active' : 'btn-secondary'; ?>"><i class="fas fa-money-bill-wave"></i> Cash Flow</a>
    <a href="?report=budgets&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" class="btn <?php echo $report_type === 'budgets' ? 'active' : 'btn-secondary'; ?>"><i class="fas fa-wallet"></i> Budgets</a>
    <a href="?report=procurement&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" class="btn <?php echo $report_type === 'procurement' ? 'active' : 'btn-secondary'; ?>"><i class="fas fa-shopping-cart"></i> Procurement</a>
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
        <div class="value">₱<?php echo number_format($report_data['total_revenue'] ?? 0, 2); ?></div>
        <div class="label">Total Revenue</div>
    </div>
    <div class="stat-card" style="border-left-color:#e74a3b;">
        <div class="value">₱<?php echo number_format($report_data['total_expenses'] ?? 0, 2); ?></div>
        <div class="label">Total Expenses</div>
    </div>
    <div class="stat-card" style="border-left-color:#f6c23e;">
        <div class="value">₱<?php echo number_format($report_data['total_payroll'] ?? 0, 2); ?></div>
        <div class="label">Total Payroll</div>
    </div>
    <div class="stat-card" style="border-left-color:#36b9cc;">
        <div class="value">₱<?php echo number_format($report_data['purchase_costs'] ?? 0, 2); ?></div>
        <div class="label">Purchase Costs</div>
    </div>
    <div class="stat-card" style="border-left-color:#1cc88a;">
        <div class="value <?php echo ($report_data['net_profit'] ?? 0) >= 0 ? 'profit-positive' : 'profit-negative'; ?>">₱<?php echo number_format($report_data['net_profit'] ?? 0, 2); ?></div>
        <div class="label">Net Profit</div>
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
            <h3 class="card-title"><i class="fas fa-chart-pie"></i> Expense Breakdown</h3>
        </div>
        <canvas id="expenseBreakdownChart"></canvas>
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
                label: 'Revenue',
                data: <?php echo json_encode(array_map('floatval', array_column($report_data['monthly_trend'] ?? [], 'revenue'))); ?>,
                borderColor: '#1cc88a',
                backgroundColor: 'rgba(28,200,138,0.1)',
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

const expenseBreakdownCtx = document.getElementById('expenseBreakdownChart');
if (expenseBreakdownCtx) {
    new Chart(expenseBreakdownCtx, {
        type: 'doughnut',
        data: {
            labels: <?php echo json_encode(array_column($report_data['expense_breakdown'] ?? [], 'category')); ?>,
            datasets: [{
                data: <?php echo json_encode(array_map('floatval', array_column($report_data['expense_breakdown'] ?? [], 'total'))); ?>,
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

<?php elseif ($report_type === 'projects'): ?>
<!-- Project Report -->
<div class="card">
    <h3 class="section-title"><i class="fas fa-project-diagram"></i> Project Financial Performance</h3>
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
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($report_data['project_performance'] ?? [] as $project): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($project['project_code']); ?></strong></td>
                    <td><?php echo htmlspecialchars($project['name']); ?></td>
                    <td>₱<?php echo number_format($project['estimated_budget'], 2); ?></td>
                    <td>₱<?php echo number_format($project['actual_cost'], 2); ?></td>
                    <td class="<?php echo $project['remaining_budget'] >= 0 ? 'profit-positive' : 'profit-negative'; ?>">₱<?php echo number_format($project['remaining_budget'], 2); ?></td>
                    <td>₱<?php echo number_format($project['total_invoices'], 2); ?> (<?php echo $project['invoice_count']; ?>)</td>
                    <td>₱<?php echo number_format($project['total_expenses'], 2); ?> (<?php echo $project['expense_count']; ?>)</td>
                    <td><span class="badge badge-<?php echo $project['status']; ?>"><?php echo ucfirst($project['status']); ?></span></td>
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
                    <td><?php echo number_format($project['utilization_percentage'], 1); ?>%</td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php elseif ($report_type === 'cashflow'): ?>
<!-- Cash Flow Report -->
<div class="stat-cards">
    <div class="stat-card">
        <div class="value">₱<?php echo number_format(array_sum(array_column($report_data['cash_inflow'] ?? [], 'total')), 2); ?></div>
        <div class="label">Total Cash Inflow</div>
    </div>
    <div class="stat-card" style="border-left-color:#e74a3b;">
        <div class="value">₱<?php echo number_format(array_sum(array_column($report_data['cash_outflow'] ?? [], 'total')), 2); ?></div>
        <div class="label">Total Cash Outflow (Expenses)</div>
    </div>
    <div class="stat-card" style="border-left-color:#f6c23e;">
        <div class="value">₱<?php echo number_format(array_sum(array_column($report_data['payroll_outflow'] ?? [], 'total')), 2); ?></div>
        <div class="label">Total Payroll Outflow</div>
    </div>
    <div class="stat-card" style="border-left-color:#1cc88a;">
        <div class="value profit-positive">₱<?php echo number_format(array_sum(array_column($report_data['cash_inflow'] ?? [], 'total')) - array_sum(array_column($report_data['cash_outflow'] ?? [], 'total')) - array_sum(array_column($report_data['payroll_outflow'] ?? [], 'total')), 2); ?></div>
        <div class="label">Net Cash Flow</div>
    </div>
</div>

<div class="card">
    <h3 class="section-title"><i class="fas fa-chart-line"></i> Cash Flow Trend</h3>
    <canvas id="cashFlowChart"></canvas>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>
const cashFlowCtx = document.getElementById('cashFlowChart');
if (cashFlowCtx) {
    new Chart(cashFlowCtx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode(array_column($report_data['cash_inflow'] ?? [], 'month')); ?>,
            datasets: [
                {
                    label: 'Cash Inflow',
                    data: <?php echo json_encode(array_map('floatval', array_column($report_data['cash_inflow'] ?? [], 'total'))); ?>,
                    borderColor: '#1cc88a',
                    backgroundColor: 'rgba(28,200,138,0.1)',
                    fill: true,
                    tension: 0.35
                },
                {
                    label: 'Expenses Outflow',
                    data: <?php echo json_encode(array_map('floatval', array_column($report_data['cash_outflow'] ?? [], 'total'))); ?>,
                    borderColor: '#e74a3b',
                    backgroundColor: 'rgba(231,74,59,0.1)',
                    fill: true,
                    tension: 0.35
                },
                {
                    label: 'Payroll Outflow',
                    data: <?php echo json_encode(array_map('floatval', array_column($report_data['payroll_outflow'] ?? [], 'total'))); ?>,
                    borderColor: '#f6c23e',
                    backgroundColor: 'rgba(246,194,62,0.1)',
                    fill: true,
                    tension: 0.35
                }
            ]
        },
        options: {
            responsive: true,
            plugins: { legend: { position: 'bottom' } },
            scales: { y: { beginAtZero: true } }
        }
    });
}
</script>

<?php elseif ($report_type === 'budgets'): ?>
<!-- Budget Report -->
<div class="card">
    <h3 class="section-title"><i class="fas fa-wallet"></i> Funds Status</h3>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Fund Name</th>
                    <th>Type</th>
                    <th>Total Amount</th>
                    <th>Allocated</th>
                    <th>Available</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($report_data['funds_status'] ?? [] as $fund): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($fund['fund_name']); ?></strong></td>
                    <td><?php echo ucfirst($fund['fund_type']); ?></td>
                    <td>₱<?php echo number_format($fund['amount'], 2); ?></td>
                    <td>₱<?php echo number_format($fund['allocated_amount'], 2); ?></td>
                    <td class="profit-positive">₱<?php echo number_format($fund['available'], 2); ?></td>
                    <td><span class="badge badge-<?php echo $fund['status'] == 'active' ? 'success' : 'secondary'; ?>"><?php echo ucfirst($fund['status']); ?></span></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <h3 class="section-title"><i class="fas fa-users"></i> Labor Budget Status</h3>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Budget Name</th>
                    <th>Allocated</th>
                    <th>Used</th>
                    <th>Remaining</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($report_data['labor_budgets'] ?? [] as $budget): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($budget['budget_name']); ?></strong></td>
                    <td>₱<?php echo number_format($budget['allocated_amount'], 2); ?></td>
                    <td>₱<?php echo number_format($budget['used_amount'], 2); ?></td>
                    <td class="<?php echo $budget['remaining'] >= 0 ? 'profit-positive' : 'profit-negative'; ?>">₱<?php echo number_format($budget['remaining'], 2); ?></td>
                    <td><span class="badge badge-<?php echo $budget['status'] == 'active' ? 'success' : 'secondary'; ?>"><?php echo ucfirst($budget['status']); ?></span></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <h3 class="section-title"><i class="fas fa-file-contract"></i> Contract Status</h3>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Contract Name</th>
                    <th>Value</th>
                    <th>Start Date</th>
                    <th>End Date</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($report_data['contract_status'] ?? [] as $contract): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($contract['contract_name']); ?></strong></td>
                    <td>₱<?php echo number_format($contract['contract_value'], 2); ?></td>
                    <td><?php echo $contract['start_date'] ? date('M d, Y', strtotime($contract['start_date'])) : 'N/A'; ?></td>
                    <td><?php echo $contract['end_date'] ? date('M d, Y', strtotime($contract['end_date'])) : 'N/A'; ?></td>
                    <td><span class="badge badge-<?php echo $contract['status'] == 'active' ? 'success' : 'secondary'; ?>"><?php echo ucfirst($contract['status']); ?></span></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php elseif ($report_type === 'procurement'): ?>
<!-- Procurement Report -->
<div class="card">
    <h3 class="section-title"><i class="fas fa-chart-line"></i> Procurement Cost Trend</h3>
    <canvas id="procurementTrendChart"></canvas>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>
const procurementTrendCtx = document.getElementById('procurementTrendChart');
if (procurementTrendCtx) {
    new Chart(procurementTrendCtx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode(array_column($report_data['procurement_costs'] ?? [], 'month')); ?>,
            datasets: [{
                label: 'Procurement Costs',
                data: <?php echo json_encode(array_map('floatval', array_column($report_data['procurement_costs'] ?? [], 'total_cost'))); ?>,
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
</script>

<div class="card">
    <h3 class="section-title"><i class="fas fa-truck"></i> Supplier Performance</h3>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Supplier Name</th>
                    <th>Request Count</th>
                    <th>Total Value</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($report_data['supplier_performance'] ?? [] as $supplier): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($supplier['supplier_name']); ?></strong></td>
                    <td><?php echo $supplier['request_count']; ?></td>
                    <td>₱<?php echo number_format($supplier['total_value'], 2); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php endif; ?>

<?php include '../../includes/footer.php'; ?>
