<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';

requireAdmin();

$page_title = 'Department Monitoring';
$department = $_GET['department'] ?? 'all';

// Get department data
$department_data = [];
try {
    switch ($department) {
        case 'procurement':
            $department_data['title'] = 'Procurement Monitoring';
            $department_data['total_prs'] = $pdo->query("SELECT COUNT(*) FROM purchase_requests")->fetchColumn() ?? 0;
            $department_data['pending_prs'] = $pdo->query("SELECT COUNT(*) FROM purchase_requests WHERE status = 'pending'")->fetchColumn() ?? 0;
            $department_data['approved_prs'] = $pdo->query("SELECT COUNT(*) FROM purchase_requests WHERE status = 'approved'")->fetchColumn() ?? 0;
            $department_data['total_value'] = $pdo->query("SELECT COALESCE(SUM(total_amount), 0) FROM purchase_requests WHERE status IN ('approved', 'confirmed', 'ordered', 'received')")->fetchColumn() ?? 0;
            $department_data['suppliers'] = $pdo->query("SELECT COUNT(*) FROM suppliers WHERE status = 'active'")->fetchColumn() ?? 0;
            $department_data['materials'] = $pdo->query("SELECT COUNT(*) FROM materials WHERE status = 'active'")->fetchColumn() ?? 0;
            
            $department_data['recent_prs'] = $pdo->query("
                SELECT pr.*, p.project_code, u.full_name as requestor_name
                FROM purchase_requests pr
                LEFT JOIN projects p ON pr.project_id = p.id
                LEFT JOIN users u ON pr.requestor_id = u.id
                ORDER BY pr.created_at DESC
                LIMIT 10
            ")->fetchAll();
            break;
            
        case 'accounting':
            $department_data['title'] = 'Accounting Monitoring';
            $department_data['total_invoices'] = $pdo->query("SELECT COUNT(*) FROM invoices")->fetchColumn() ?? 0;
            $department_data['paid_invoices'] = $pdo->query("SELECT COUNT(*) FROM invoices WHERE status = 'paid'")->fetchColumn() ?? 0;
            $department_data['total_expenses'] = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE status = 'paid'")->fetchColumn() ?? 0;
            $department_data['funds'] = 0;
            try {
                $result = $pdo->query("SELECT COUNT(*) FROM funds WHERE status = 'active'");
                if ($result) {
                    $department_data['funds'] = $result->fetchColumn() ?? 0;
                }
            } catch (PDOException $e) {
                $department_data['funds'] = 0;
            }
            $department_data['contracts'] = 0;
            try {
                $result = $pdo->query("SELECT COUNT(*) FROM contracts WHERE status = 'active'");
                if ($result) {
                    $department_data['contracts'] = $result->fetchColumn() ?? 0;
                }
            } catch (PDOException $e) {
                $department_data['contracts'] = 0;
            }
            $department_data['labor_budgets'] = 0;
            try {
                $result = $pdo->query("SELECT COUNT(*) FROM labor_budgets WHERE status = 'active'");
                if ($result) {
                    $department_data['labor_budgets'] = $result->fetchColumn() ?? 0;
                }
            } catch (PDOException $e) {
                $department_data['labor_budgets'] = 0;
            }
            
            $department_data['recent_invoices'] = $pdo->query("
                SELECT inv.*, p.project_code
                FROM invoices inv
                LEFT JOIN projects p ON inv.project_id = p.id
                ORDER BY inv.created_at DESC
                LIMIT 10
            ")->fetchAll();
            break;
            
        case 'engineering':
            $department_data['title'] = 'Engineering Monitoring';
            $department_data['total_projects'] = $pdo->query("SELECT COUNT(*) FROM projects")->fetchColumn() ?? 0;
            $department_data['ongoing_projects'] = $pdo->query("SELECT COUNT(*) FROM projects WHERE status = 'ongoing'")->fetchColumn() ?? 0;
            $department_data['completed_projects'] = $pdo->query("SELECT COUNT(*) FROM projects WHERE status = 'completed'")->fetchColumn() ?? 0;
            $department_data['total_budget'] = $pdo->query("SELECT COALESCE(SUM(estimated_budget), 0) FROM projects")->fetchColumn() ?? 0;
            $department_data['accomplishments'] = 0;
            try {
                $result = $pdo->query("SELECT COUNT(*) FROM accomplishments");
                if ($result) {
                    $department_data['accomplishments'] = $result->fetchColumn() ?? 0;
                }
            } catch (PDOException $e) {
                $department_data['accomplishments'] = 0;
            }
            $department_data['attachments'] = 0;
            try {
                $result = $pdo->query("SELECT COUNT(*) FROM attachments");
                if ($result) {
                    $department_data['attachments'] = $result->fetchColumn() ?? 0;
                }
            } catch (PDOException $e) {
                $department_data['attachments'] = 0;
            }
            
            $department_data['recent_projects'] = $pdo->query("
                SELECT * FROM projects ORDER BY created_at DESC LIMIT 10
            ")->fetchAll();
            break;
            
        case 'warehouse':
            $department_data['title'] = 'Warehouse Monitoring';
            $department_data['total_items'] = $pdo->query("SELECT COUNT(*) FROM materials WHERE status = 'active'")->fetchColumn() ?? 0;
            $department_data['total_stock'] = $pdo->query("SELECT COALESCE(SUM(current_stock), 0) FROM materials WHERE status = 'active'")->fetchColumn() ?? 0;
            $department_data['total_value'] = $pdo->query("SELECT COALESCE(SUM(current_stock * cost_per_unit), 0) FROM materials WHERE status = 'active'")->fetchColumn() ?? 0;
            $department_data['low_stock'] = $pdo->query("SELECT COUNT(*) FROM materials WHERE current_stock <= min_stock AND min_stock > 0 AND status = 'active'")->fetchColumn() ?? 0;
            $department_data['movements'] = $pdo->query("SELECT COUNT(*) FROM stock_movements WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)")->fetchColumn() ?? 0;
            $department_data['material_requests'] = 0;
            try {
                $result = $pdo->query("SELECT COUNT(*) FROM material_requests WHERE status = 'pending'");
                if ($result) {
                    $department_data['material_requests'] = $result->fetchColumn() ?? 0;
                }
            } catch (PDOException $e) {
                $department_data['material_requests'] = 0;
            }
            
            $department_data['recent_movements'] = $pdo->query("
                SELECT sm.*, m.name as material_name, u.full_name as user_name
                FROM stock_movements sm
                LEFT JOIN materials m ON sm.material_id = m.id
                LEFT JOIN users u ON sm.created_by = u.id
                ORDER BY sm.created_at DESC
                LIMIT 10
            ")->fetchAll();
            break;
            
        default:
            $department_data['title'] = 'All Departments';
            $department_data['overview'] = true;
            break;
    }
} catch (PDOException $e) {
    $error = userDatabaseError($e);
}

include '../../includes/header.php';
?>

<style>
.dept-nav {
    display: flex;
    gap: 0.5rem;
    margin-bottom: 1.5rem;
    flex-wrap: wrap;
}
.dept-nav .btn {
    padding: 0.5rem 1rem;
    font-size: 0.85rem;
}
.dept-nav .btn.active {
    background: #f59e0b;
    color: #1a1a2e;
}
.dept-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 1.5rem;
}
.dept-stats .card {
    border-left: 4px solid #4e73df;
}
</style>

<div class="page-header">
    <h1><i class="fas fa-eye"></i> <?php echo $page_title; ?></h1>
    <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
        <a href="executive_dashboard.php" class="btn btn-primary"><i class="fas fa-tachometer-alt"></i> Executive Dashboard</a>
        <a href="consolidated_reports.php" class="btn btn-info"><i class="fas fa-chart-bar"></i> Consolidated Reports</a>
    </div>
</div>

<?php if (isset($error)): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<!-- Department Navigation -->
<div class="dept-nav">
    <a href="?department=all" class="btn <?php echo $department === 'all' ? 'active' : 'btn-secondary'; ?>"><i class="fas fa-building"></i> All Departments</a>
    <a href="?department=procurement" class="btn <?php echo $department === 'procurement' ? 'active' : 'btn-secondary'; ?>"><i class="fas fa-shopping-cart"></i> Procurement</a>
    <a href="?department=accounting" class="btn <?php echo $department === 'accounting' ? 'active' : 'btn-secondary'; ?>"><i class="fas fa-chart-line"></i> Accounting</a>
    <a href="?department=engineering" class="btn <?php echo $department === 'engineering' ? 'active' : 'btn-secondary'; ?>"><i class="fas fa-hard-hat"></i> Engineering</a>
    <a href="?department=warehouse" class="btn <?php echo $department === 'warehouse' ? 'active' : 'btn-secondary'; ?>"><i class="fas fa-warehouse"></i> Warehouse</a>
</div>

<?php if ($department === 'all'): ?>
<!-- All Departments Overview -->
<div class="card">
    <h3><i class="fas fa-building"></i> Department Overview</h3>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:1.5rem;">
        <div style="border:1px solid #e2e8f0;border-radius:8px;padding:1.5rem;">
            <h4 style="margin:0 0 1rem 0;color:#f59e0b;"><i class="fas fa-shopping-cart"></i> Procurement</h4>
            <div style="font-size:0.85rem;color:#64748b;">Total PRs: <strong><?php echo $pdo->query("SELECT COUNT(*) FROM purchase_requests")->fetchColumn() ?? 0; ?></strong></div>
            <div style="font-size:0.85rem;color:#64748b;">Pending: <strong><?php echo $pdo->query("SELECT COUNT(*) FROM purchase_requests WHERE status = 'pending'")->fetchColumn() ?? 0; ?></strong></div>
            <div style="margin-top:0.5rem;"><a href="?department=procurement" class="btn btn-sm btn-primary">View Details</a></div>
        </div>
        <div style="border:1px solid #e2e8f0;border-radius:8px;padding:1.5rem;">
            <h4 style="margin:0 0 1rem 0;color:#36b9cc;"><i class="fas fa-chart-line"></i> Accounting</h4>
            <div style="font-size:0.85rem;color:#64748b;">Total Invoices: <strong><?php echo $pdo->query("SELECT COUNT(*) FROM invoices")->fetchColumn() ?? 0; ?></strong></div>
            <div style="font-size:0.85rem;color:#64748b;">Active Funds: <strong><?php try { echo $pdo->query("SELECT COUNT(*) FROM funds WHERE status = 'active'")->fetchColumn() ?? 0; } catch (PDOException $e) { echo 0; } ?></strong></div>
            <div style="margin-top:0.5rem;"><a href="?department=accounting" class="btn btn-sm btn-primary">View Details</a></div>
        </div>
        <div style="border:1px solid #e2e8f0;border-radius:8px;padding:1.5rem;">
            <h4 style="margin:0 0 1rem 0;color:#4e73df;"><i class="fas fa-hard-hat"></i> Engineering</h4>
            <div style="font-size:0.85rem;color:#64748b;">Total Projects: <strong><?php echo $pdo->query("SELECT COUNT(*) FROM projects")->fetchColumn() ?? 0; ?></strong></div>
            <div style="font-size:0.85rem;color:#64748b;">Ongoing: <strong><?php echo $pdo->query("SELECT COUNT(*) FROM projects WHERE status = 'ongoing'")->fetchColumn() ?? 0; ?></strong></div>
            <div style="margin-top:0.5rem;"><a href="?department=engineering" class="btn btn-sm btn-primary">View Details</a></div>
        </div>
        <div style="border:1px solid #e2e8f0;border-radius:8px;padding:1.5rem;">
            <h4 style="margin:0 0 1rem 0;color:#1cc88a;"><i class="fas fa-warehouse"></i> Warehouse</h4>
            <div style="font-size:0.85rem;color:#64748b;">Total Stock: <strong><?php echo number_format($pdo->query("SELECT COALESCE(SUM(current_stock), 0) FROM materials WHERE status = 'active'")->fetchColumn() ?? 0); ?></strong></div>
            <div style="font-size:0.85rem;color:#64748b;">Low Stock: <strong><?php echo $pdo->query("SELECT COUNT(*) FROM materials WHERE current_stock <= min_stock AND min_stock > 0 AND status = 'active'")->fetchColumn() ?? 0; ?></strong></div>
            <div style="margin-top:0.5rem;"><a href="?department=warehouse" class="btn btn-sm btn-primary">View Details</a></div>
        </div>
    </div>
</div>

<?php else: ?>
<!-- Specific Department Monitoring -->
<div class="card">
    <h3><i class="fas fa-chart-line"></i> <?php echo $department_data['title']; ?></h3>
    
    <!-- Department Statistics -->
    <div class="dept-stats">
        <?php if ($department === 'procurement'): ?>
            <div class="card" style="border-left-color:#4e73df;margin:0;">
                <div style="font-size:0.75rem;color:#64748b;">Total PRs</div>
                <div style="font-size:1.5rem;font-weight:700;"><?php echo number_format($department_data['total_prs']); ?></div>
            </div>
            <div class="card" style="border-left-color:#f6c23e;margin:0;">
                <div style="font-size:0.75rem;color:#64748b;">Pending</div>
                <div style="font-size:1.5rem;font-weight:700;"><?php echo number_format($department_data['pending_prs']); ?></div>
            </div>
            <div class="card" style="border-left-color:#1cc88a;margin:0;">
                <div style="font-size:0.75rem;color:#64748b;">Approved</div>
                <div style="font-size:1.5rem;font-weight:700;"><?php echo number_format($department_data['approved_prs']); ?></div>
            </div>
            <div class="card" style="border-left-color:#36b9cc;margin:0;">
                <div style="font-size:0.75rem;color:#64748b;">Total Value</div>
                <div style="font-size:1.5rem;font-weight:700;">₱<?php echo number_format($department_data['total_value'], 2); ?></div>
            </div>
            <div class="card" style="border-left-color:#858796;margin:0;">
                <div style="font-size:0.75rem;color:#64748b;">Suppliers</div>
                <div style="font-size:1.5rem;font-weight:700;"><?php echo number_format($department_data['suppliers']); ?></div>
            </div>
            <div class="card" style="border-left-color:#e74a3b;margin:0;">
                <div style="font-size:0.75rem;color:#64748b;">Materials</div>
                <div style="font-size:1.5rem;font-weight:700;"><?php echo number_format($department_data['materials']); ?></div>
            </div>
        <?php elseif ($department === 'accounting'): ?>
            <div class="card" style="border-left-color:#4e73df;margin:0;">
                <div style="font-size:0.75rem;color:#64748b;">Total Invoices</div>
                <div style="font-size:1.5rem;font-weight:700;"><?php echo number_format($department_data['total_invoices']); ?></div>
            </div>
            <div class="card" style="border-left-color:#1cc88a;margin:0;">
                <div style="font-size:0.75rem;color:#64748b;">Paid Invoices</div>
                <div style="font-size:1.5rem;font-weight:700;"><?php echo number_format($department_data['paid_invoices']); ?></div>
            </div>
            <div class="card" style="border-left-color:#e74a3b;margin:0;">
                <div style="font-size:0.75rem;color:#64748b;">Total Expenses</div>
                <div style="font-size:1.5rem;font-weight:700;">₱<?php echo number_format($department_data['total_expenses'], 2); ?></div>
            </div>
            <div class="card" style="border-left-color:#36b9cc;margin:0;">
                <div style="font-size:0.75rem;color:#64748b;">Active Funds</div>
                <div style="font-size:1.5rem;font-weight:700;"><?php echo number_format($department_data['funds']); ?></div>
            </div>
            <div class="card" style="border-left-color:#f6c23e;margin:0;">
                <div style="font-size:0.75rem;color:#64748b;">Active Contracts</div>
                <div style="font-size:1.5rem;font-weight:700;"><?php echo number_format($department_data['contracts']); ?></div>
            </div>
            <div class="card" style="border-left-color:#858796;margin:0;">
                <div style="font-size:0.75rem;color:#64748b;">Labor Budgets</div>
                <div style="font-size:1.5rem;font-weight:700;"><?php echo number_format($department_data['labor_budgets']); ?></div>
            </div>
        <?php elseif ($department === 'engineering'): ?>
            <div class="card" style="border-left-color:#4e73df;margin:0;">
                <div style="font-size:0.75rem;color:#64748b;">Total Projects</div>
                <div style="font-size:1.5rem;font-weight:700;"><?php echo number_format($department_data['total_projects']); ?></div>
            </div>
            <div class="card" style="border-left-color:#f6c23e;margin:0;">
                <div style="font-size:0.75rem;color:#64748b;">Ongoing</div>
                <div style="font-size:1.5rem;font-weight:700;"><?php echo number_format($department_data['ongoing_projects']); ?></div>
            </div>
            <div class="card" style="border-left-color:#1cc88a;margin:0;">
                <div style="font-size:0.75rem;color:#64748b;">Completed</div>
                <div style="font-size:1.5rem;font-weight:700;"><?php echo number_format($department_data['completed_projects']); ?></div>
            </div>
            <div class="card" style="border-left-color:#36b9cc;margin:0;">
                <div style="font-size:0.75rem;color:#64748b;">Total Budget</div>
                <div style="font-size:1.5rem;font-weight:700;">₱<?php echo number_format($department_data['total_budget'], 2); ?></div>
            </div>
            <div class="card" style="border-left-color:#858796;margin:0;">
                <div style="font-size:0.75rem;color:#64748b;">Accomplishments</div>
                <div style="font-size:1.5rem;font-weight:700;"><?php echo number_format($department_data['accomplishments']); ?></div>
            </div>
            <div class="card" style="border-left-color:#e74a3b;margin:0;">
                <div style="font-size:0.75rem;color:#64748b;">Attachments</div>
                <div style="font-size:1.5rem;font-weight:700;"><?php echo number_format($department_data['attachments']); ?></div>
            </div>
        <?php elseif ($department === 'warehouse'): ?>
            <div class="card" style="border-left-color:#4e73df;margin:0;">
                <div style="font-size:0.75rem;color:#64748b;">Total Items</div>
                <div style="font-size:1.5rem;font-weight:700;"><?php echo number_format($department_data['total_items']); ?></div>
            </div>
            <div class="card" style="border-left-color:#36b9cc;margin:0;">
                <div style="font-size:0.75rem;color:#64748b;">Total Stock</div>
                <div style="font-size:1.5rem;font-weight:700;"><?php echo number_format($department_data['total_stock']); ?></div>
            </div>
            <div class="card" style="border-left-color:#1cc88a;margin:0;">
                <div style="font-size:0.75rem;color:#64748b;">Total Value</div>
                <div style="font-size:1.5rem;font-weight:700;">₱<?php echo number_format($department_data['total_value'], 2); ?></div>
            </div>
            <div class="card" style="border-left-color:#e74a3b;margin:0;">
                <div style="font-size:0.75rem;color:#64748b;">Low Stock</div>
                <div style="font-size:1.5rem;font-weight:700;"><?php echo number_format($department_data['low_stock']); ?></div>
            </div>
            <div class="card" style="border-left-color:#f6c23e;margin:0;">
                <div style="font-size:0.75rem;color:#64748b;">Movements (30d)</div>
                <div style="font-size:1.5rem;font-weight:700;"><?php echo number_format($department_data['movements']); ?></div>
            </div>
            <div class="card" style="border-left-color:#858796;margin:0;">
                <div style="font-size:0.75rem;color:#64748b;">Pending Requests</div>
                <div style="font-size:1.5rem;font-weight:700;"><?php echo number_format($department_data['material_requests']); ?></div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Recent Activity -->
<div class="card">
    <h3 style="margin-bottom:1rem;"><i class="fas fa-clock"></i> Recent Activity</h3>
    <?php if ($department === 'procurement' && !empty($department_data['recent_prs'])): ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>PR #</th>
                        <th>Project</th>
                        <th>Requestor</th>
                        <th>Total Amount</th>
                        <th>Status</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($department_data['recent_prs'] as $pr): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($pr['pr_number']); ?></strong></td>
                        <td><?php echo htmlspecialchars($pr['project_code'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($pr['requestor_name'] ?? 'N/A'); ?></td>
                        <td>₱<?php echo number_format($pr['total_amount'], 2); ?></td>
                        <td><span class="badge badge-<?php echo $pr['status']; ?>"><?php echo ucfirst($pr['status']); ?></span></td>
                        <td><?php echo date('M d, Y', strtotime($pr['created_at'])); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php elseif ($department === 'accounting' && !empty($department_data['recent_invoices'])): ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Invoice #</th>
                        <th>Project</th>
                        <th>Client</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($department_data['recent_invoices'] as $inv): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($inv['invoice_number']); ?></strong></td>
                        <td><?php echo htmlspecialchars($inv['project_code'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($inv['client']); ?></td>
                        <td>₱<?php echo number_format($inv['amount'], 2); ?></td>
                        <td><span class="badge badge-<?php echo $inv['status']; ?>"><?php echo ucfirst($inv['status']); ?></span></td>
                        <td><?php echo date('M d, Y', strtotime($inv['created_at'])); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php elseif ($department === 'engineering' && !empty($department_data['recent_projects'])): ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Project Code</th>
                        <th>Name</th>
                        <th>Location</th>
                        <th>Budget</th>
                        <th>Status</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($department_data['recent_projects'] as $project): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($project['project_code']); ?></strong></td>
                        <td><?php echo htmlspecialchars($project['name']); ?></td>
                        <td><?php echo htmlspecialchars($project['location'] ?? 'N/A'); ?></td>
                        <td>₱<?php echo number_format($project['estimated_budget'], 2); ?></td>
                        <td><span class="badge badge-<?php echo $project['status']; ?>"><?php echo ucfirst($project['status']); ?></span></td>
                        <td><?php echo date('M d, Y', strtotime($project['created_at'])); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php elseif ($department === 'warehouse' && !empty($department_data['recent_movements'])): ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Material</th>
                        <th>Type</th>
                        <th>Quantity</th>
                        <th>User</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($department_data['recent_movements'] as $movement): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($movement['material_name'] ?? 'N/A'); ?></td>
                        <td><span class="badge badge-<?php echo $movement['movement_type'] === 'in' ? 'success' : 'danger'; ?>"><?php echo ucfirst($movement['movement_type']); ?></span></td>
                        <td><?php echo number_format($movement['quantity']); ?></td>
                        <td><?php echo htmlspecialchars($movement['user_name'] ?? 'N/A'); ?></td>
                        <td><?php echo date('M d, Y h:i A', strtotime($movement['created_at'])); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-clock"></i>
            <p>No recent activity found.</p>
        </div>
    <?php endif; ?>
</div>

<?php endif; ?>

<?php include '../../includes/footer.php'; ?>
