<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';

requireDepartment(['projects']);

$page_title = 'Project Details';
$id = (int)($_GET['id'] ?? 0);

$project = null;
$purchase_requests = [];
$invoices = [];
$expenses = [];
$quotations = [];
$personnel = [];
$all_projects = [];

try {
    if ($id <= 0) {
        $all_projects = $pdo->query("SELECT id, project_code, name, location, status, estimated_budget, start_date, end_date FROM projects ORDER BY created_at DESC")->fetchAll();
    } else {
        $stmt = $pdo->prepare("SELECT * FROM projects WHERE id = ?");
        $stmt->execute([$id]);
        $project = $stmt->fetch();

        if (!$project) {
            $_SESSION['error'] = 'Project not found.';
            header('Location: project_details.php');
            exit();
        }

        $stmt = $pdo->prepare("
            SELECT pr.*, u.full_name as requestor_name
            FROM purchase_requests pr
            LEFT JOIN users u ON pr.requestor_id = u.id
            WHERE pr.project_id = ?
            ORDER BY pr.created_at DESC
        ");
        $stmt->execute([$id]);
        $purchase_requests = $stmt->fetchAll();

        $stmt = $pdo->prepare("SELECT * FROM invoices WHERE project_id = ? ORDER BY created_at DESC");
        $stmt->execute([$id]);
        $invoices = $stmt->fetchAll();

        $stmt = $pdo->prepare("SELECT * FROM expenses WHERE project_id = ? ORDER BY created_at DESC");
        $stmt->execute([$id]);
        $expenses = $stmt->fetchAll();

        $q = $pdo->prepare("SELECT * FROM quotations WHERE project_id = ? ORDER BY created_at DESC");
        $q->execute([$id]);
        $quotations = $q->fetchAll();

        $p = $pdo->prepare("SELECT * FROM personnel WHERE project_id = ? ORDER BY created_at DESC");
        $p->execute([$id]);
        $personnel = $p->fetchAll();
    }
} catch (PDOException $e) {
    $error = 'Database error: ' . $e->getMessage();
}

include '../../includes/header.php';
?>

<style>
.detail-summary {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
}
.detail-summary .detail-item {
    padding: 0.5rem;
    border-bottom: 1px solid #f1f5f9;
}
.detail-summary .detail-item .label {
    color: #64748b;
    font-size: 0.8rem;
}
.detail-summary .detail-item .value {
    font-weight: 600;
    font-size: 1rem;
}
</style>

<?php if (isset($_SESSION['error'])): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
<?php endif; ?>
<?php if (isset($error)): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<?php if ($id <= 0 || !$project): ?>
<div class="page-header">
    <h1><i class="fas fa-folder-open"></i> Project Details</h1>
    <a href="projects.php" class="btn btn-secondary"><i class="fas fa-list"></i> All Projects</a>
</div>

<div class="card">
    <p class="muted-note">Select a project to view full details, related requests, invoices, and personnel.</p>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Name</th>
                    <th>Location</th>
                    <th>Status</th>
                    <th>Budget</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($all_projects)): ?>
                    <tr><td colspan="6" class="table-empty">No projects found.</td></tr>
                <?php else: ?>
                    <?php foreach ($all_projects as $row): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($row['project_code']); ?></strong></td>
                        <td><?php echo htmlspecialchars($row['name']); ?></td>
                        <td><?php echo htmlspecialchars($row['location'] ?? 'N/A'); ?></td>
                        <td><span class="badge badge-<?php echo htmlspecialchars($row['status']); ?>"><?php echo ucfirst(str_replace('_', ' ', $row['status'])); ?></span></td>
                        <td>₱<?php echo number_format((float)$row['estimated_budget'], 2); ?></td>
                        <td>
                            <a href="project_details.php?id=<?php echo (int)$row['id']; ?>" class="btn btn-sm btn-info"><i class="fas fa-eye"></i> View</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php else: ?>

<div class="page-header">
    <h1><i class="fas fa-chart-bar"></i> Project Details</h1>
    <div class="button-row">
        <a href="projects.php?action=edit&id=<?php echo $project['id']; ?>" class="btn btn-warning"><i class="fas fa-edit"></i> Edit</a>
        <a href="project_pdf.php?id=<?php echo (int)$project['id']; ?>" class="btn btn-outline"><i class="fas fa-download"></i> Download PDF</a>
        <a href="project_details.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> All Details</a>
        <a href="projects.php" class="btn btn-secondary"><i class="fas fa-list"></i> Projects</a>
    </div>
</div>

<!-- Project Summary -->
<div class="card">
    <h3><i class="fas fa-info-circle"></i> Project Summary</h3>
    <div class="detail-summary">
        <div class="detail-item">
            <div class="label">Project Code</div>
            <div class="value"><?php echo htmlspecialchars($project['project_code']); ?></div>
        </div>
        <div class="detail-item">
            <div class="label">Project Name</div>
            <div class="value"><?php echo htmlspecialchars($project['name']); ?></div>
        </div>
        <div class="detail-item">
            <div class="label">Status</div>
            <div class="value"><span class="badge badge-<?php echo $project['status']; ?>"><?php echo ucfirst($project['status']); ?></span></div>
        </div>
        <div class="detail-item">
            <div class="label">Client</div>
            <div class="value"><?php echo htmlspecialchars($project['client'] ?? 'N/A'); ?></div>
        </div>
        <div class="detail-item">
            <div class="label">Location</div>
            <div class="value"><?php echo htmlspecialchars($project['location'] ?? 'N/A'); ?></div>
        </div>
        <div class="detail-item">
            <div class="label">Start Date</div>
            <div class="value"><?php echo $project['start_date'] ? date('M d, Y', strtotime($project['start_date'])) : 'N/A'; ?></div>
        </div>
        <div class="detail-item">
            <div class="label">End Date</div>
            <div class="value"><?php echo $project['end_date'] ? date('M d, Y', strtotime($project['end_date'])) : 'N/A'; ?></div>
        </div>
        <div class="detail-item">
            <div class="label">Estimated Budget</div>
            <div class="value" style="color:#4e73df;">₱<?php echo number_format($project['estimated_budget'], 2); ?></div>
        </div>
        <div class="detail-item">
            <div class="label">Actual Cost</div>
            <div class="value" style="color:#e74a3b;">₱<?php echo number_format($project['actual_cost'] ?? 0, 2); ?></div>
        </div>
        <div class="detail-item">
            <div class="label">Budget Variance</div>
            <div class="value" style="color:<?php echo (($project['estimated_budget'] - ($project['actual_cost'] ?? 0)) < 0) ? '#e74a3b' : '#1cc88a'; ?>;">
                ₱<?php echo number_format(($project['estimated_budget'] - ($project['actual_cost'] ?? 0)), 2); ?>
            </div>
        </div>
    </div>
    <?php if ($project['description']): ?>
        <div style="margin-top:1rem;padding-top:1rem;border-top:1px solid #e2e8f0;">
            <div style="color:#64748b;font-size:0.85rem;">Description</div>
            <div style="margin-top:0.3rem;"><?php echo nl2br(htmlspecialchars($project['description'])); ?></div>
        </div>
    <?php endif; ?>
</div>

<!-- Project Stats -->
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:1rem;margin-bottom:1.5rem;">
    <div class="card" style="margin:0;border-left:4px solid #4e73df;">
        <div style="font-size:0.7rem;color:#64748b;">Purchase Requests</div>
        <div style="font-size:1.5rem;font-weight:700;"><?php echo count($purchase_requests); ?></div>
    </div>
    <div class="card" style="margin:0;border-left:4px solid #1cc88a;">
        <div style="font-size:0.7rem;color:#64748b;">Invoices</div>
        <div style="font-size:1.5rem;font-weight:700;"><?php echo count($invoices); ?></div>
    </div>
    <div class="card" style="margin:0;border-left:4px solid #f6c23e;">
        <div style="font-size:0.7rem;color:#64748b;">Expenses</div>
        <div style="font-size:1.5rem;font-weight:700;"><?php echo count($expenses); ?></div>
    </div>
    <div class="card" style="margin:0;border-left:4px solid #6f42c1;">
        <div style="font-size:0.7rem;color:#64748b;">Quotations</div>
        <div style="font-size:1.5rem;font-weight:700;"><?php echo count($quotations ?? []); ?></div>
    </div>
    <div class="card" style="margin:0;border-left:4px solid #36b9cc;">
        <div style="font-size:0.7rem;color:#64748b;">Personnel</div>
        <div style="font-size:1.5rem;font-weight:700;"><?php echo count($personnel ?? []); ?></div>
    </div>
</div>

<!-- Purchase Requests -->
<div class="card">
    <h3><i class="fas fa-file-invoice"></i> Purchase Requests</h3>
    <?php if (empty($purchase_requests)): ?>
        <div class="empty-state">
            <i class="fas fa-file-invoice"></i>
            <p>No purchase requests for this project.</p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>PR #</th>
                        <th>Requestor</th>
                        <th>Purpose</th>
                        <th>Total</th>
                        <th>Status</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($purchase_requests as $pr): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($pr['pr_number']); ?></strong></td>
                        <td><?php echo htmlspecialchars($pr['requestor_name'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars(substr($pr['purpose'], 0, 30)) . (strlen($pr['purpose']) > 30 ? '...' : ''); ?></td>
                        <td>₱<?php echo number_format($pr['total_amount'] ?? 0, 2); ?></td>
                        <td><span class="badge badge-<?php echo $pr['status']; ?>"><?php echo ucfirst($pr['status']); ?></span></td>
                        <td><?php echo date('M d, Y', strtotime($pr['created_at'])); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Invoices -->
<div class="card">
    <h3><i class="fas fa-file-invoice-dollar"></i> Invoices</h3>
    <?php if (empty($invoices)): ?>
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
                        <th>Status</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($invoices as $inv): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($inv['invoice_number']); ?></strong></td>
                        <td><?php echo htmlspecialchars($inv['client']); ?></td>
                        <td>₱<?php echo number_format($inv['amount'], 2); ?></td>
                        <td><span class="badge badge-<?php echo $inv['status']; ?>"><?php echo ucfirst($inv['status']); ?></span></td>
                        <td><?php echo date('M d, Y', strtotime($inv['created_at'])); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Expenses -->
<div class="card">
    <h3><i class="fas fa-coins"></i> Expenses</h3>
    <?php if (empty($expenses)): ?>
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
                    <?php foreach ($expenses as $exp): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($exp['expense_number']); ?></strong></td>
                        <td><?php echo htmlspecialchars($exp['category']); ?></td>
                        <td>₱<?php echo number_format($exp['amount'], 2); ?></td>
                        <td><span class="badge badge-<?php echo $exp['status']; ?>"><?php echo ucfirst($exp['status']); ?></span></td>
                        <td><?php echo date('M d, Y', strtotime($exp['created_at'])); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Quotations -->
<div class="card">
    <h3><i class="fas fa-file-signature"></i> Quotations</h3>
    <?php if (empty($quotations ?? [])): ?>
        <div class="empty-state">
            <i class="fas fa-file-signature"></i>
            <p>No quotations for this project.</p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Quotation #</th>
                        <th>Supplier</th>
                        <th>Total</th>
                        <th>Status</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($quotations as $qt): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($qt['quotation_number']); ?></strong></td>
                        <td><?php echo htmlspecialchars($qt['supplier']); ?></td>
                        <td>₱<?php echo number_format($qt['total_amount'] ?? 0, 2); ?></td>
                        <td><span class="badge badge-<?php echo $qt['status']; ?>"><?php echo ucfirst($qt['status']); ?></span></td>
                        <td><?php echo date('M d, Y', strtotime($qt['created_at'])); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Personnel -->
<div class="card">
    <h3><i class="fas fa-users"></i> Personnel</h3>
    <?php if (empty($personnel ?? [])): ?>
        <div class="empty-state">
            <i class="fas fa-users"></i>
            <p>No personnel assigned to this project.</p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Position</th>
                        <th>Department</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($personnel as $emp): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($emp['name']); ?></strong></td>
                        <td><?php echo htmlspecialchars($emp['position']); ?></td>
                        <td><?php echo htmlspecialchars($emp['department'] ?? 'N/A'); ?></td>
                        <td><span class="badge badge-<?php echo strtolower($emp['status'] ?? 'active'); ?>"><?php echo ucfirst($emp['status'] ?? 'Active'); ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php endif; ?>

<?php include '../../includes/footer.php'; ?>