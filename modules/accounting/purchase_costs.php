<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';

requireDepartment(['accounting']);

$page_title = 'Purchase Costs';
$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');

// Get purchase costs data
$purchase_costs = [];
$project_costs = [];
$supplier_costs = [];
$category_costs = [];
$cost_summary = [];
try {
    // Overall purchase costs summary
    $cost_summary['total_requests'] = $pdo->query("SELECT COUNT(*) FROM purchase_requests WHERE created_at BETWEEN '$start_date' AND '$end_date 23:59:59'")->fetchColumn() ?? 0;
    $cost_summary['total_amount'] = $pdo->query("SELECT COALESCE(SUM(total_amount), 0) FROM purchase_requests WHERE status IN ('approved', 'confirmed', 'ordered', 'received') AND created_at BETWEEN '$start_date' AND '$end_date 23:59:59'")->fetchColumn() ?? 0;
    $cost_summary['paid_amount'] = $pdo->query("SELECT COALESCE(SUM(total_amount), 0) FROM purchase_requests WHERE status = 'received' AND created_at BETWEEN '$start_date' AND '$end_date 23:59:59'")->fetchColumn() ?? 0;
    $cost_summary['pending_amount'] = $cost_summary['total_amount'] - $cost_summary['paid_amount'];
    
    // Purchase costs by project
    $project_costs = $pdo->query("
        SELECT 
            p.id,
            p.project_code,
            p.name as project_name,
            COUNT(DISTINCT pr.id) as request_count,
            COALESCE(SUM(CASE WHEN pr.status IN ('approved', 'confirmed', 'ordered', 'received') THEN pr.total_amount ELSE 0 END), 0) as total_cost,
            COALESCE(SUM(CASE WHEN pr.status = 'received' THEN pr.total_amount ELSE 0 END), 0) as paid_cost,
            COALESCE(SUM(CASE WHEN pr.status IN ('approved', 'confirmed', 'ordered') THEN pr.total_amount ELSE 0 END), 0) as pending_cost
        FROM projects p
        LEFT JOIN purchase_requests pr ON p.id = pr.project_id AND pr.created_at BETWEEN '$start_date' AND '$end_date 23:59:59'
        GROUP BY p.id, p.project_code, p.name
        HAVING request_count > 0
        ORDER BY total_cost DESC
    ")->fetchAll();
    
    // Purchase costs by supplier (through materials)
    $supplier_costs = $pdo->query("
        SELECT 
            s.id,
            s.name as supplier_name,
            s.status,
            COUNT(DISTINCT m.id) as material_count,
            COALESCE(SUM(m.cost_per_unit * m.current_stock), 0) as inventory_value,
            COALESCE(SUM(pri.quantity * pri.estimated_cost), 0) as purchase_value
        FROM suppliers s
        LEFT JOIN materials m ON s.id = m.supplier_id
        LEFT JOIN purchase_request_items pri ON m.id = pri.material_id
        LEFT JOIN purchase_requests pr ON pri.purchase_request_id = pr.id AND pr.created_at BETWEEN '$start_date' AND '$end_date 23:59:59'
        WHERE s.status = 'active'
        GROUP BY s.id, s.name, s.status
        HAVING purchase_value > 0 OR inventory_value > 0
        ORDER BY purchase_value DESC
    ")->fetchAll();
    
    // Purchase costs by material category
    $category_costs = $pdo->query("
        SELECT 
            m.category,
            COUNT(DISTINCT m.id) as material_count,
            COALESCE(SUM(pri.quantity * pri.estimated_cost), 0) as total_cost
        FROM materials m
        LEFT JOIN purchase_request_items pri ON m.id = pri.material_id
        LEFT JOIN purchase_requests pr ON pri.purchase_request_id = pr.id AND pr.created_at BETWEEN '$start_date' AND '$end_date 23:59:59'
        WHERE m.category IS NOT NULL AND m.category != ''
        GROUP BY m.category
        HAVING total_cost > 0
        ORDER BY total_cost DESC
    ")->fetchAll();
    
    // Detailed purchase costs list
    $purchase_costs = $pdo->query("
        SELECT 
            pr.id,
            pr.pr_number,
            pr.purpose,
            pr.total_amount,
            pr.status,
            pr.priority,
            pr.created_at,
            p.project_code,
            p.name as project_name,
            u.full_name as requestor_name,
            COUNT(DISTINCT pri.id) as item_count
        FROM purchase_requests pr
        LEFT JOIN projects p ON pr.project_id = p.id
        LEFT JOIN users u ON pr.requestor_id = u.id
        LEFT JOIN purchase_request_items pri ON pr.id = pri.purchase_request_id
        WHERE pr.created_at BETWEEN '$start_date' AND '$end_date 23:59:59'
        GROUP BY pr.id, pr.pr_number, pr.purpose, pr.total_amount, pr.status, pr.priority, pr.created_at, p.project_code, p.name, u.full_name
        ORDER BY pr.created_at DESC
    ")->fetchAll();
    
} catch (PDOException $e) {
    $error = userDatabaseError($e);
    $purchase_costs = [];
    $project_costs = [];
    $supplier_costs = [];
    $category_costs = [];
}

// Get single purchase request details for view
$pr_details = null;
$pr_items = [];
if ($action === 'view' && $id > 0) {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                pr.*,
                p.project_code,
                p.name as project_name,
                u.full_name as requestor_name,
                u2.full_name as approved_by_name,
                u3.full_name as confirmed_by_name
            FROM purchase_requests pr
            LEFT JOIN projects p ON pr.project_id = p.id
            LEFT JOIN users u ON pr.requestor_id = u.id
            LEFT JOIN users u2 ON pr.approved_by = u2.id
            LEFT JOIN users u3 ON pr.confirmed_by = u3.id
            WHERE pr.id = ?
        ");
        $stmt->execute([$id]);
        $pr_details = $stmt->fetch();
        
        if ($pr_details) {
            $items_stmt = $pdo->prepare("
                SELECT pri.*, m.name as material_name, m.material_code, m.unit as default_unit
                FROM purchase_request_items pri
                LEFT JOIN materials m ON pri.material_id = m.id
                WHERE pri.purchase_request_id = ?
            ");
            $items_stmt->execute([$id]);
            $pr_items = $items_stmt->fetchAll();
        }
    } catch (PDOException $e) {
        $error = userDatabaseError($e);
    }
}

include '../../includes/header.php';
?>

<style>
.purchase-costs-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 1.5rem;
}
.purchase-costs-stats .card {
    border-left: 4px solid #4e73df;
}
.cost-paid { color: #1cc88a; font-weight: 700; }
.cost-pending { color: #f6c23e; font-weight: 700; }
</style>

<div class="page-header">
    <h1><i class="fas fa-shopping-cart"></i> <?php echo $page_title; ?></h1>
    <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
        <a href="financial_reports.php" class="btn btn-info"><i class="fas fa-chart-bar"></i> Financial Reports</a>
        <a href="project_financials.php" class="btn btn-primary"><i class="fas fa-project-diagram"></i> Project Financials</a>
    </div>
</div>

<?php if (isset($error)): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<!-- Date Filters -->
<div class="card">
    <form method="GET" class="report-filters">
        <div class="form-group">
            <label>Start Date:</label>
            <input type="date" name="start_date" value="<?php echo $start_date; ?>">
        </div>
        <div class="form-group">
            <label>End Date:</label>
            <input type="date" name="end_date" value="<?php echo $end_date; ?>">
        </div>
        <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Apply Filter</button>
        <a href="purchase_costs.php" class="btn btn-secondary"><i class="fas fa-redo"></i> Reset</a>
    </form>
</div>

<!-- Summary Statistics -->
<div class="purchase-costs-stats">
    <div class="card" style="border-left-color:#4e73df;margin:0;">
        <div style="font-size:0.75rem;color:#64748b;">Total Requests</div>
        <div style="font-size:1.5rem;font-weight:700;"><?php echo number_format($cost_summary['total_requests'] ?? 0); ?></div>
    </div>
    <div class="card" style="border-left-color:#1cc88a;margin:0;">
        <div style="font-size:0.75rem;color:#64748b;">Total Amount</div>
        <div style="font-size:1.5rem;font-weight:700;">₱<?php echo number_format($cost_summary['total_amount'] ?? 0, 2); ?></div>
    </div>
    <div class="card" style="border-left-color:#f6c23e;margin:0;">
        <div style="font-size:0.75rem;color:#64748b;">Paid Amount</div>
        <div style="font-size:1.5rem;font-weight:700;">₱<?php echo number_format($cost_summary['paid_amount'] ?? 0, 2); ?></div>
    </div>
    <div class="card" style="border-left-color:#e74a3b;margin:0;">
        <div style="font-size:0.75rem;color:#64748b;">Pending Amount</div>
        <div style="font-size:1.5rem;font-weight:700;">₱<?php echo number_format($cost_summary['pending_amount'] ?? 0, 2); ?></div>
    </div>
</div>

<?php if ($action === 'view' && $pr_details): ?>
<!-- Purchase Request Details View -->
<div class="card">
    <h3>Purchase Request Details</h3>
    <div class="view-details">
        <div class="detail-row"><span>PR Number:</span> <strong><?php echo htmlspecialchars($pr_details['pr_number']); ?></strong></div>
        <div class="detail-row"><span>Project:</span> <?php echo htmlspecialchars($pr_details['project_name'] ?? 'N/A'); ?></div>
        <div class="detail-row"><span>Requestor:</span> <?php echo htmlspecialchars($pr_details['requestor_name']); ?></div>
        <div class="detail-row"><span>Priority:</span> <span class="badge badge-<?php echo $pr_details['priority']; ?>"><?php echo ucfirst($pr_details['priority']); ?></span></div>
        <div class="detail-row"><span>Status:</span> <span class="badge badge-<?php echo $pr_details['status']; ?>"><?php echo ucfirst($pr_details['status']); ?></span></div>
        <div class="detail-row"><span>Total Amount:</span> <strong>₱<?php echo number_format($pr_details['total_amount'], 2); ?></strong></div>
        <div class="detail-row"><span>Purpose:</span> <?php echo nl2br(htmlspecialchars($pr_details['purpose'])); ?></div>
        <div class="detail-row"><span>Created:</span> <?php echo date('M d, Y h:i A', strtotime($pr_details['created_at'])); ?></div>
        <?php if ($pr_details['approved_at']): ?>
            <div class="detail-row"><span>Approved:</span> <?php echo date('M d, Y h:i A', strtotime($pr_details['approved_at'])); ?> by <?php echo htmlspecialchars($pr_details['approved_by_name'] ?? 'N/A'); ?></div>
        <?php endif; ?>
    </div>
    
    <h4 style="margin-top: 1.5rem;">Requested Items</h4>
    <table class="table">
        <thead>
            <tr>
                <th>Material</th>
                <th>Quantity</th>
                <th>Unit</th>
                <th>Est. Cost</th>
                <th>Total</th>
                <th>Remarks</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($pr_items as $item): ?>
            <tr>
                <td><?php echo htmlspecialchars($item['material_name'] ?? 'N/A'); ?></td>
                <td><?php echo number_format($item['quantity']); ?></td>
                <td><?php echo htmlspecialchars($item['unit'] ?? 'pcs'); ?></td>
                <td>₱<?php echo number_format($item['estimated_cost'] ?? 0, 2); ?></td>
                <td>₱<?php echo number_format(($item['quantity'] ?? 0) * ($item['estimated_cost'] ?? 0), 2); ?></td>
                <td><?php echo htmlspecialchars($item['remarks'] ?? ''); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr>
                <th colspan="4" style="text-align: right;">Total:</th>
                <th>₱<?php echo number_format($pr_details['total_amount'] ?? 0, 2); ?></th>
                <th></th>
            </tr>
        </tfoot>
    </table>
    
    <div style="margin-top:1.5rem;">
        <a href="purchase_costs.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back to List</a>
    </div>
</div>

<?php else: ?>
<!-- Costs by Project -->
<div class="card">
    <h3 class="section-title"><i class="fas fa-project-diagram"></i> Purchase Costs by Project</h3>
    <?php if (empty($project_costs)): ?>
        <div class="empty-state">
            <i class="fas fa-project-diagram"></i>
            <p>No project purchase costs found for the selected period.</p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Project Code</th>
                        <th>Project Name</th>
                        <th>Requests</th>
                        <th>Total Cost</th>
                        <th>Paid</th>
                        <th>Pending</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($project_costs as $project): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($project['project_code']); ?></strong></td>
                        <td><?php echo htmlspecialchars($project['project_name']); ?></td>
                        <td><?php echo $project['request_count']; ?></td>
                        <td>₱<?php echo number_format($project['total_cost'], 2); ?></td>
                        <td class="cost-paid">₱<?php echo number_format($project['paid_cost'], 2); ?></td>
                        <td class="cost-pending">₱<?php echo number_format($project['pending_cost'], 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Costs by Supplier -->
<div class="card">
    <h3 class="section-title"><i class="fas fa-truck"></i> Purchase Costs by Supplier</h3>
    <?php if (empty($supplier_costs)): ?>
        <div class="empty-state">
            <i class="fas fa-truck"></i>
            <p>No supplier purchase costs found.</p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Supplier Name</th>
                        <th>Materials</th>
                        <th>Inventory Value</th>
                        <th>Purchase Value</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($supplier_costs as $supplier): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($supplier['supplier_name']); ?></strong></td>
                        <td><?php echo $supplier['material_count']; ?></td>
                        <td>₱<?php echo number_format($supplier['inventory_value'], 2); ?></td>
                        <td>₱<?php echo number_format($supplier['purchase_value'], 2); ?></td>
                        <td><span class="badge badge-<?php echo $supplier['status'] == 'active' ? 'success' : 'secondary'; ?>"><?php echo ucfirst($supplier['status']); ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Costs by Category -->
<div class="card">
    <h3 class="section-title"><i class="fas fa-tags"></i> Purchase Costs by Category</h3>
    <?php if (empty($category_costs)): ?>
        <div class="empty-state">
            <i class="fas fa-tags"></i>
            <p>No category purchase costs found.</p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Category</th>
                        <th>Materials</th>
                        <th>Total Cost</th>
                        <th>Percentage</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $total_category_cost = array_sum(array_column($category_costs, 'total_cost'));
                    foreach ($category_costs as $category): 
                    ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($category['category']); ?></strong></td>
                        <td><?php echo $category['material_count']; ?></td>
                        <td>₱<?php echo number_format($category['total_cost'], 2); ?></td>
                        <td><?php echo $total_category_cost > 0 ? number_format(($category['total_cost'] / $total_category_cost) * 100, 1) : 0; ?>%</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Detailed Purchase Costs List -->
<div class="card">
    <h3 class="section-title"><i class="fas fa-list"></i> Detailed Purchase Costs</h3>
    <?php if (empty($purchase_costs)): ?>
        <div class="empty-state">
            <i class="fas fa-receipt"></i>
            <p>No purchase costs found for the selected period.</p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>PR #</th>
                        <th>Project</th>
                        <th>Requestor</th>
                        <th>Purpose</th>
                        <th>Items</th>
                        <th>Total</th>
                        <th>Priority</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($purchase_costs as $cost): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($cost['pr_number']); ?></strong></td>
                        <td><?php echo htmlspecialchars($cost['project_name'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($cost['requestor_name']); ?></td>
                        <td><?php echo htmlspecialchars(substr($cost['purpose'], 0, 40)) . (strlen($cost['purpose']) > 40 ? '...' : ''); ?></td>
                        <td><?php echo $cost['item_count']; ?></td>
                        <td>₱<?php echo number_format($cost['total_amount'], 2); ?></td>
                        <td><span class="badge badge-<?php echo $cost['priority']; ?>"><?php echo ucfirst($cost['priority']); ?></span></td>
                        <td><span class="badge badge-<?php echo $cost['status']; ?>"><?php echo ucfirst($cost['status']); ?></span></td>
                        <td><?php echo date('M d, Y', strtotime($cost['created_at'])); ?></td>
                        <td>
                            <a href="?action=view&id=<?php echo $cost['id']; ?>" class="btn btn-sm btn-info" title="View Details"><i class="fas fa-eye"></i></a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php endif; ?>

<?php include '../../includes/footer.php'; ?>
