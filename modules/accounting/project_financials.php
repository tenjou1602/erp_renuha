<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';

requireDepartment(['accounting']);

$page_title = 'Project Financial Records';
$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);

// Get project financial records
$project_records = [];
try {
    $query = "
        SELECT 
            p.id,
            p.project_code,
            p.name,
            p.description,
            p.estimated_budget,
            p.actual_cost,
            (p.estimated_budget - p.actual_cost) as remaining_budget,
            p.status,
            p.start_date,
            p.end_date,
            p.client,
            COUNT(DISTINCT inv.id) as invoice_count,
            COALESCE(SUM(CASE WHEN inv.status = 'paid' THEN inv.amount ELSE 0 END), 0) as paid_invoices,
            COALESCE(SUM(inv.amount), 0) as total_invoices,
            COUNT(DISTINCT exp.id) as expense_count,
            COALESCE(SUM(CASE WHEN exp.status = 'paid' THEN exp.amount ELSE 0 END), 0) as paid_expenses,
            COALESCE(SUM(exp.amount), 0) as total_expenses,
            COUNT(DISTINCT pr.id) as purchase_request_count,
            COALESCE(SUM(CASE WHEN pr.status IN ('approved', 'confirmed', 'ordered', 'received') THEN pr.total_amount ELSE 0 END), 0) as purchase_costs
        FROM projects p
        LEFT JOIN invoices inv ON p.id = inv.project_id
        LEFT JOIN expenses exp ON p.id = exp.project_id
        LEFT JOIN purchase_requests pr ON p.id = pr.project_id
        GROUP BY p.id, p.project_code, p.name, p.description, p.estimated_budget, p.actual_cost, p.status, p.start_date, p.end_date, p.client
        ORDER BY p.created_at DESC
    ";
    $project_records = $pdo->query($query)->fetchAll();
} catch (PDOException $e) {
    $error = userDatabaseError($e);
}

// Get single project details for view
$project_details = null;
$project_invoices = [];
$project_expenses = [];
$project_purchases = [];
if ($action === 'view' && $id > 0) {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                p.*,
                (p.estimated_budget - p.actual_cost) as remaining_budget,
                COUNT(DISTINCT inv.id) as invoice_count,
                COALESCE(SUM(CASE WHEN inv.status = 'paid' THEN inv.amount ELSE 0 END), 0) as paid_invoices,
                COALESCE(SUM(inv.amount), 0) as total_invoices,
                COUNT(DISTINCT exp.id) as expense_count,
                COALESCE(SUM(CASE WHEN exp.status = 'paid' THEN exp.amount ELSE 0 END), 0) as paid_expenses,
                COALESCE(SUM(exp.amount), 0) as total_expenses
            FROM projects p
            LEFT JOIN invoices inv ON p.id = inv.project_id
            LEFT JOIN expenses exp ON p.id = exp.project_id
            WHERE p.id = ?
            GROUP BY p.id
        ");
        $stmt->execute([$id]);
        $project_details = $stmt->fetch();
        
        if ($project_details) {
            // Get project invoices
            $inv_stmt = $pdo->prepare("SELECT * FROM invoices WHERE project_id = ? ORDER BY created_at DESC");
            $inv_stmt->execute([$id]);
            $project_invoices = $inv_stmt->fetchAll();
            
            // Get project expenses
            $exp_stmt = $pdo->prepare("SELECT * FROM expenses WHERE project_id = ? ORDER BY created_at DESC");
            $exp_stmt->execute([$id]);
            $project_expenses = $exp_stmt->fetchAll();
            
            // Get project purchase requests
            $pr_stmt = $pdo->prepare("
                SELECT pr.*, COUNT(pri.id) as item_count
                FROM purchase_requests pr
                LEFT JOIN purchase_request_items pri ON pr.id = pri.purchase_request_id
                WHERE pr.project_id = ?
                GROUP BY pr.id
                ORDER BY pr.created_at DESC
            ");
            $pr_stmt->execute([$id]);
            $project_purchases = $pr_stmt->fetchAll();
        }
    } catch (PDOException $e) {
        $error = userDatabaseError($e);
    }
}

include '../../includes/header.php';
?>

<style>
.project-financial-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 1.5rem;
}
.project-financial-stats .card {
    border-left: 4px solid #4e73df;
}
.budget-positive { color: #1cc88a; font-weight: 700; }
.budget-negative { color: #e74a3b; font-weight: 700; }
</style>

<div class="page-header">
    <h1><i class="fas fa-project-diagram"></i> <?php echo $page_title; ?></h1>
    <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
        <a href="financial_reports.php" class="btn btn-info"><i class="fas fa-chart-bar"></i> Financial Reports</a>
        <a href="funds_budget.php" class="btn btn-primary"><i class="fas fa-wallet"></i> Funds & Budget</a>
    </div>
</div>

<?php if (isset($error)): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<!-- Overall Statistics -->
<div class="project-financial-stats">
    <div class="card" style="border-left-color:#4e73df;margin:0;">
        <div style="font-size:0.75rem;color:#64748b;">Total Projects</div>
        <div style="font-size:1.5rem;font-weight:700;"><?php echo count($project_records); ?></div>
    </div>
    <div class="card" style="border-left-color:#1cc88a;margin:0;">
        <div style="font-size:0.75rem;color:#64748b;">Total Budget</div>
        <div style="font-size:1.5rem;font-weight:700;">₱<?php echo number_format(array_sum(array_column($project_records, 'estimated_budget')), 2); ?></div>
    </div>
    <div class="card" style="border-left-color:#e74a3b;margin:0;">
        <div style="font-size:0.75rem;color:#64748b;">Total Actual Cost</div>
        <div style="font-size:1.5rem;font-weight:700;">₱<?php echo number_format(array_sum(array_column($project_records, 'actual_cost')), 2); ?></div>
    </div>
    <div class="card" style="border-left-color:#36b9cc;margin:0;">
        <div style="font-size:0.75rem;color:#64748b;">Total Remaining</div>
        <div style="font-size:1.5rem;font-weight:700;">₱<?php echo number_format(array_sum(array_column($project_records, 'remaining_budget')), 2); ?></div>
    </div>
</div>

<?php if ($action === 'view' && $project_details): ?>
<!-- Project Details View -->
<div class="card">
    <h3>Project Financial Details</h3>
    <div class="view-details">
        <div class="detail-row"><span>Project Code:</span> <strong><?php echo htmlspecialchars($project_details['project_code']); ?></strong></div>
        <div class="detail-row"><span>Project Name:</span> <strong><?php echo htmlspecialchars($project_details['name']); ?></strong></div>
        <div class="detail-row"><span>Client:</span> <?php echo htmlspecialchars($project_details['client'] ?? 'N/A'); ?></div>
        <div class="detail-row"><span>Status:</span> <span class="badge badge-<?php echo $project_details['status']; ?>"><?php echo ucfirst($project_details['status']); ?></span></div>
        <div class="detail-row"><span>Start Date:</span> <?php echo $project_details['start_date'] ? date('M d, Y', strtotime($project_details['start_date'])) : 'N/A'; ?></div>
        <div class="detail-row"><span>End Date:</span> <?php echo $project_details['end_date'] ? date('M d, Y', strtotime($project_details['end_date'])) : 'N/A'; ?></div>
        <div class="detail-row"><span>Estimated Budget:</span> <strong>₱<?php echo number_format($project_details['estimated_budget'], 2); ?></strong></div>
        <div class="detail-row"><span>Actual Cost:</span> <strong>₱<?php echo number_format($project_details['actual_cost'], 2); ?></strong></div>
        <div class="detail-row"><span>Remaining Budget:</span> <strong class="<?php echo $project_details['remaining_budget'] >= 0 ? 'budget-positive' : 'budget-negative'; ?>">₱<?php echo number_format($project_details['remaining_budget'], 2); ?></strong></div>
        <div class="detail-row"><span>Total Invoices:</span> <?php echo number_format($project_details['total_invoices'], 2); ?> (<?php echo $project_details['invoice_count']; ?> invoices)</div>
        <div class="detail-row"><span>Paid Invoices:</span> ₱<?php echo number_format($project_details['paid_invoices'], 2); ?></div>
        <div class="detail-row"><span>Total Expenses:</span> ₱<?php echo number_format($project_details['total_expenses'], 2); ?> (<?php echo $project_details['expense_count']; ?> expenses)</div>
        <div class="detail-row"><span>Paid Expenses:</span> ₱<?php echo number_format($project_details['paid_expenses'], 2); ?></div>
    </div>
    
    <div style="margin-top:1.5rem;">
        <a href="project_financials.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back to List</a>
    </div>
</div>

<!-- Project Invoices -->
<div class="card">
    <h3 class="section-title"><i class="fas fa-file-invoice-dollar"></i> Project Invoices</h3>
    <?php if (empty($project_invoices)): ?>
        <div class="empty-state">
            <i class="fas fa-file-invoice-dollar"></i>
            <p>No invoices for this project.</p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Invoice #</th>
                        <th>Client</th>
                        <th>Amount</th>
                        <th>Paid</th>
                        <th>Status</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($project_invoices as $invoice): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($invoice['invoice_number']); ?></strong></td>
                        <td><?php echo htmlspecialchars($invoice['client']); ?></td>
                        <td>₱<?php echo number_format($invoice['amount'], 2); ?></td>
                        <td>₱<?php echo number_format($invoice['paid_amount'], 2); ?></td>
                        <td><span class="badge badge-<?php echo $invoice['status']; ?>"><?php echo ucfirst($invoice['status']); ?></span></td>
                        <td><?php echo date('M d, Y', strtotime($invoice['created_at'])); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Project Expenses -->
<div class="card">
    <h3 class="section-title"><i class="fas fa-coins"></i> Project Expenses</h3>
    <?php if (empty($project_expenses)): ?>
        <div class="empty-state">
            <i class="fas fa-coins"></i>
            <p>No expenses for this project.</p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Expense #</th>
                        <th>Category</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($project_expenses as $expense): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($expense['expense_number']); ?></strong></td>
                        <td><?php echo htmlspecialchars($expense['category']); ?></td>
                        <td>₱<?php echo number_format($expense['amount'], 2); ?></td>
                        <td><span class="badge badge-<?php echo $expense['status']; ?>"><?php echo ucfirst($expense['status']); ?></span></td>
                        <td><?php echo date('M d, Y', strtotime($expense['created_at'])); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Project Purchase Requests -->
<div class="card">
    <h3 class="section-title"><i class="fas fa-shopping-cart"></i> Project Purchase Requests</h3>
    <?php if (empty($project_purchases)): ?>
        <div class="empty-state">
            <i class="fas fa-shopping-cart"></i>
            <p>No purchase requests for this project.</p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>PR #</th>
                        <th>Purpose</th>
                        <th>Items</th>
                        <th>Total Amount</th>
                        <th>Status</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($project_purchases as $pr): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($pr['pr_number']); ?></strong></td>
                        <td><?php echo htmlspecialchars(substr($pr['purpose'], 0, 50)) . (strlen($pr['purpose']) > 50 ? '...' : ''); ?></td>
                        <td><?php echo $pr['item_count']; ?></td>
                        <td>₱<?php echo number_format($pr['total_amount'], 2); ?></td>
                        <td><span class="badge badge-<?php echo $pr['status']; ?>"><?php echo ucfirst($pr['status']); ?></span></td>
                        <td><?php echo date('M d, Y', strtotime($pr['created_at'])); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php else: ?>
<!-- List View -->
<div class="card">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem; flex-wrap:wrap; gap:0.5rem;">
        <h3 style="margin:0;"><i class="fas fa-list"></i> All Project Financial Records (<?php echo count($project_records); ?>)</h3>
        <div style="display:flex; gap:0.5rem;">
            <input type="text" id="searchProject" placeholder="Search projects..." style="padding:0.4rem 0.8rem; border:1px solid var(--border-color); border-radius:6px; font-size:0.85rem;">
            <select id="filterStatus" style="padding:0.4rem 0.8rem; border:1px solid var(--border-color); border-radius:6px; font-size:0.85rem;">
                <option value="">All Status</option>
                <option value="planning">Planning</option>
                <option value="ongoing">Ongoing</option>
                <option value="completed">Completed</option>
                <option value="on_hold">On Hold</option>
            </select>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table" id="projectTable">
            <thead>
                <tr>
                    <th onclick="sortTable('projectTable', 0)" style="cursor:pointer;">Project Code <i class="fas fa-sort"></i></th>
                    <th onclick="sortTable('projectTable', 1)" style="cursor:pointer;">Name <i class="fas fa-sort"></i></th>
                    <th onclick="sortTable('projectTable', 2)" style="cursor:pointer;">Budget <i class="fas fa-sort"></i></th>
                    <th onclick="sortTable('projectTable', 3)" style="cursor:pointer;">Actual Cost <i class="fas fa-sort"></i></th>
                    <th onclick="sortTable('projectTable', 4)" style="cursor:pointer;">Remaining <i class="fas fa-sort"></i></th>
                    <th onclick="sortTable('projectTable', 5)" style="cursor:pointer;">Invoices <i class="fas fa-sort"></i></th>
                    <th onclick="sortTable('projectTable', 6)" style="cursor:pointer;">Expenses <i class="fas fa-sort"></i></th>
                    <th onclick="sortTable('projectTable', 7)" style="cursor:pointer;">Purchase Costs <i class="fas fa-sort"></i></th>
                    <th onclick="sortTable('projectTable', 8)" style="cursor:pointer;">Status <i class="fas fa-sort"></i></th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($project_records)): ?>
                    <tr>
                        <td colspan="10" style="text-align:center;color:#94a3b8;padding:2rem;">
                            <i class="fas fa-project-diagram" style="font-size:2rem;display:block;margin-bottom:0.5rem;opacity:0.3;"></i>
                            No project financial records found.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($project_records as $project): ?>
                    <tr data-status="<?php echo htmlspecialchars($project['status']); ?>">
                        <td><strong><?php echo htmlspecialchars($project['project_code']); ?></strong></td>
                        <td><?php echo htmlspecialchars($project['name']); ?></td>
                        <td>₱<?php echo number_format($project['estimated_budget'], 2); ?></td>
                        <td>₱<?php echo number_format($project['actual_cost'], 2); ?></td>
                        <td class="<?php echo $project['remaining_budget'] >= 0 ? 'budget-positive' : 'budget-negative'; ?>">₱<?php echo number_format($project['remaining_budget'], 2); ?></td>
                        <td>₱<?php echo number_format($project['total_invoices'], 2); ?> (<?php echo $project['invoice_count']; ?>)</td>
                        <td>₱<?php echo number_format($project['total_expenses'], 2); ?> (<?php echo $project['expense_count']; ?>)</td>
                        <td>₱<?php echo number_format($project['purchase_costs'], 2); ?> (<?php echo $project['purchase_request_count']; ?>)</td>
                        <td><span class="badge badge-<?php echo $project['status']; ?>"><?php echo ucfirst($project['status']); ?></span></td>
                        <td>
                            <a href="?action=view&id=<?php echo $project['id']; ?>" class="btn btn-sm btn-info" title="View Details"><i class="fas fa-eye"></i></a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
// Search functionality
document.getElementById('searchProject')?.addEventListener('keyup', function() {
    const searchTerm = this.value.toLowerCase();
    const statusFilter = document.getElementById('filterStatus').value;
    const rows = document.querySelectorAll('#projectTable tbody tr');
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        const status = row.getAttribute('data-status') || '';
        const matchesSearch = text.includes(searchTerm);
        const matchesStatus = statusFilter === '' || status === statusFilter;
        row.style.display = matchesSearch && matchesStatus ? '' : 'none';
    });
});

// Status filter
document.getElementById('filterStatus')?.addEventListener('change', function() {
    const statusFilter = this.value;
    const searchTerm = document.getElementById('searchProject').value.toLowerCase();
    const rows = document.querySelectorAll('#projectTable tbody tr');
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        const status = row.getAttribute('data-status') || '';
        const matchesSearch = text.includes(searchTerm);
        const matchesStatus = statusFilter === '' || status === statusFilter;
        row.style.display = matchesSearch && matchesStatus ? '' : 'none';
    });
});
</script>
<?php endif; ?>

<?php include '../../includes/footer.php'; ?>
