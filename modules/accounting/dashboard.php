<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';

requireDepartment(['accounting']);

$page_title = 'Financial Dashboard';

// Get statistics
try {
    $stats = [];
    
    // Invoices
    $stats['total_invoices'] = $pdo->query("SELECT COUNT(*) FROM invoices")->fetchColumn() ?? 0;
    $stats['pending_invoices'] = $pdo->query("SELECT COUNT(*) FROM invoices WHERE status IN ('sent', 'partial')")->fetchColumn() ?? 0;
    $stats['paid_invoices'] = $pdo->query("SELECT COUNT(*) FROM invoices WHERE status = 'paid'")->fetchColumn() ?? 0;
    $stats['overdue_invoices'] = $pdo->query("SELECT COUNT(*) FROM invoices WHERE status = 'overdue'")->fetchColumn() ?? 0;
    
    // Receivables
    $stats['total_receivables'] = $pdo->query("SELECT COALESCE(SUM(amount - paid_amount), 0) FROM invoices WHERE status IN ('sent', 'partial')")->fetchColumn() ?? 0;
    $stats['total_revenue'] = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM invoices WHERE status = 'paid'")->fetchColumn() ?? 0;
    
    // Expenses
    $stats['total_expenses'] = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE status = 'paid'")->fetchColumn() ?? 0;
    $stats['pending_expenses'] = $pdo->query("SELECT COUNT(*) FROM expenses WHERE status = 'pending'")->fetchColumn() ?? 0;
    
    // Payroll
    $stats['total_payroll'] = $pdo->query("SELECT COALESCE(SUM(net_pay), 0) FROM payroll WHERE status = 'paid'")->fetchColumn() ?? 0;
    $stats['pending_payroll'] = $pdo->query("SELECT COUNT(*) FROM payroll WHERE status IN ('draft', 'approved')")->fetchColumn() ?? 0;
    
    // Recent transactions
    $recent_invoices = $pdo->query("SELECT * FROM invoices ORDER BY created_at DESC LIMIT 5")->fetchAll();
    $recent_expenses = $pdo->query("SELECT * FROM expenses ORDER BY created_at DESC LIMIT 5")->fetchAll();
    
} catch (PDOException $e) {
    $error = 'Database error: ' . $e->getMessage();
    $recent_invoices = [];
    $recent_expenses = [];
}

include '../../includes/header.php';
?>

<div class="page-header">
    <h1><i class="fas fa-calculator"></i> Financial Dashboard</h1>
    <div class="button-row">
        <a href="invoices.php?action=add" class="btn btn-success"><i class="fas fa-plus"></i> New Invoice</a>
        <a href="expenses.php?action=add" class="btn btn-warning"><i class="fas fa-plus"></i> New Expense</a>
    </div>
</div>

<?php if (isset($error)): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<!-- Stats Cards -->
<div class="stats-grid accounting-stats">
    <div class="card" style="border-left:4px solid #4e73df;margin:0;">
        <div style="font-size:0.75rem;color:#64748b;">Total Invoices</div>
        <div style="font-size:1.6rem;font-weight:700;"><?php echo number_format($stats['total_invoices'] ?? 0); ?></div>
    </div>
    <div class="card" style="border-left:4px solid #f6c23e;margin:0;">
        <div style="font-size:0.75rem;color:#64748b;">Pending Invoices</div>
        <div style="font-size:1.6rem;font-weight:700;"><?php echo number_format($stats['pending_invoices'] ?? 0); ?></div>
    </div>
    <div class="card" style="border-left:4px solid #1cc88a;margin:0;">
        <div style="font-size:0.75rem;color:#64748b;">Total Revenue</div>
        <div style="font-size:1.6rem;font-weight:700;">₱<?php echo number_format($stats['total_revenue'] ?? 0, 2); ?></div>
    </div>
    <div class="card" style="border-left:4px solid #e74a3b;margin:0;">
        <div style="font-size:0.75rem;color:#64748b;">Receivables</div>
        <div style="font-size:1.6rem;font-weight:700;">₱<?php echo number_format($stats['total_receivables'] ?? 0, 2); ?></div>
    </div>
</div>

<div class="stats-grid accounting-stats">
    <div class="card" style="border-left:4px solid #e74a3b;margin:0;">
        <div style="font-size:0.75rem;color:#64748b;">Total Expenses</div>
        <div style="font-size:1.6rem;font-weight:700;">₱<?php echo number_format($stats['total_expenses'] ?? 0, 2); ?></div>
    </div>
    <div class="card" style="border-left:4px solid #f6c23e;margin:0;">
        <div style="font-size:0.75rem;color:#64748b;">Pending Expenses</div>
        <div style="font-size:1.6rem;font-weight:700;"><?php echo number_format($stats['pending_expenses'] ?? 0); ?></div>
    </div>
    <div class="card" style="border-left:4px solid #36b9cc;margin:0;">
        <div style="font-size:0.75rem;color:#64748b;">Total Payroll</div>
        <div style="font-size:1.6rem;font-weight:700;">₱<?php echo number_format($stats['total_payroll'] ?? 0, 2); ?></div>
    </div>
    <div class="card" style="border-left:4px solid #f6c23e;margin:0;">
        <div style="font-size:0.75rem;color:#64748b;">Pending Payroll</div>
        <div style="font-size:1.6rem;font-weight:700;"><?php echo number_format($stats['pending_payroll'] ?? 0); ?></div>
    </div>
</div>

<!-- Recent Invoices -->
<div class="card">
    <h3 class="section-title"><i class="fas fa-file-invoice-dollar"></i> Recent Invoices</h3>
    <?php if (empty($recent_invoices)): ?>
        <div class="empty-state">
            <i class="fas fa-file-invoice-dollar"></i>
            <p>No invoices yet.</p>
            <a href="invoices.php?action=add" class="btn btn-primary btn-sm" style="margin-top:0.5rem;">Create First Invoice</a>
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
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_invoices as $inv): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($inv['invoice_number']); ?></strong></td>
                        <td><?php echo htmlspecialchars($inv['client']); ?></td>
                        <td>₱<?php echo number_format($inv['amount'], 2); ?></td>
                        <td><span class="badge badge-<?php echo $inv['status']; ?>"><?php echo ucfirst($inv['status']); ?></span></td>
                        <td><?php echo date('M d, Y', strtotime($inv['created_at'])); ?></td>
                        <td><a href="invoices.php?action=view&id=<?php echo $inv['id']; ?>" class="btn btn-sm btn-info"><i class="fas fa-eye"></i></a></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
    <div class="table-actions">
        <a href="invoices.php" class="btn btn-primary btn-sm"><i class="fas fa-list"></i> View All Invoices</a>
    </div>
</div>

<!-- Recent Expenses -->
<div class="card">
    <h3 class="section-title"><i class="fas fa-coins"></i> Recent Expenses</h3>
    <?php if (empty($recent_expenses)): ?>
        <div class="empty-state">
            <i class="fas fa-coins"></i>
            <p>No expenses yet.</p>
            <a href="expenses.php?action=add" class="btn btn-primary btn-sm" style="margin-top:0.5rem;">Create First Expense</a>
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
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_expenses as $exp): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($exp['expense_number']); ?></strong></td>
                        <td><?php echo htmlspecialchars($exp['category']); ?></td>
                        <td>₱<?php echo number_format($exp['amount'], 2); ?></td>
                        <td><span class="badge badge-<?php echo $exp['status']; ?>"><?php echo ucfirst($exp['status']); ?></span></td>
                        <td><?php echo date('M d, Y', strtotime($exp['created_at'])); ?></td>
                        <td><a href="expenses.php?action=view&id=<?php echo $exp['id']; ?>" class="btn btn-sm btn-info"><i class="fas fa-eye"></i></a></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
    <div class="table-actions">
        <a href="expenses.php" class="btn btn-primary btn-sm"><i class="fas fa-list"></i> View All Expenses</a>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>