<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';

requireDepartment(['accounting']);

$page_title = 'Accounting Dashboard';

$stats = [];
try {
    $stats['total_project_amount'] = $pdo->query("SELECT COALESCE(SUM(estimated_budget), 0) FROM projects")->fetchColumn() ?? 0;
    $stats['total_project_expenses'] = $pdo->query("SELECT COALESCE(SUM(actual_cost), 0) FROM projects")->fetchColumn() ?? 0;
    $stats['total_invoices'] = $pdo->query("SELECT COUNT(*) FROM invoices")->fetchColumn() ?? 0;
    $stats['total_expenses'] = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE status = 'paid'")->fetchColumn() ?? 0;
    
    try {
        $stats['available_funds'] = $pdo->query("SELECT COALESCE(SUM(amount - allocated_amount), 0) FROM funds WHERE status = 'active'")->fetchColumn() ?? 0;
    } catch (PDOException $e) {
        $stats['available_funds'] = 0;
    }
    
    try {
        $stats['contracts'] = $pdo->query("SELECT COUNT(*) FROM contracts WHERE status = 'active'")->fetchColumn() ?? 0;
    } catch (PDOException $e) {
        $stats['contracts'] = 0;
    }
    
    try {
        $stats['labor_budgets'] = $pdo->query("SELECT COUNT(*) FROM labor_budgets WHERE status = 'active'")->fetchColumn() ?? 0;
    } catch (PDOException $e) {
        $stats['labor_budgets'] = 0;
    }
    
    $stats['purchase_costs'] = $pdo->query("SELECT COALESCE(SUM(total_amount), 0) FROM purchase_requests WHERE status IN ('approved', 'confirmed', 'ordered', 'received')")->fetchColumn() ?? 0;
    
    $recent_financial_records = $pdo->query("
        (SELECT 'Invoice' as type, invoice_number as reference, amount, client as entity, status, created_at FROM invoices ORDER BY created_at DESC LIMIT 3)
        UNION ALL
        (SELECT 'Expense' as type, CONCAT('EXP-', id) as reference, amount, description as entity, status, created_at FROM expenses ORDER BY created_at DESC LIMIT 3)
        ORDER BY created_at DESC
        LIMIT 6
    ")->fetchAll();
    
} catch (PDOException $e) {
    $error = userDatabaseError($e);
}

include '../../includes/header.php';
?>

<div class="page-header">
    <h1><i class="fas fa-chart-line"></i> <?php echo $page_title; ?></h1>
    <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
        <a href="invoices.php?action=add" class="btn btn-primary"><i class="fas fa-file-invoice-dollar"></i> New Invoice</a>
        <a href="expenses.php?action=add" class="btn btn-success"><i class="fas fa-receipt"></i> New Expense</a>
        <a href="project_financials.php" class="btn btn-info"><i class="fas fa-project-diagram"></i> Project Financials</a>
    </div>
</div>

<?php if (isset($error)): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<!-- Stats -->
<div class="stats-grid">
    <div class="card" style="border-left:4px solid #4e73df;">
        <div style="font-size:0.85rem;color:#64748b;">Total Project Amount</div>
        <div style="font-size:1.8rem;font-weight:700;">₱<?php echo number_format($stats['total_project_amount'] ?? 0, 2); ?></div>
    </div>
    <div class="card" style="border-left:4px solid #e74a3b;">
        <div style="font-size:0.85rem;color:#64748b;">Project Expenses</div>
        <div style="font-size:1.8rem;font-weight:700;">₱<?php echo number_format($stats['total_project_expenses'] ?? 0, 2); ?></div>
    </div>
    <div class="card" style="border-left:4px solid #1cc88a;">
        <div style="font-size:0.85rem;color:#64748b;">Available Funds</div>
        <div style="font-size:1.8rem;font-weight:700;">₱<?php echo number_format($stats['available_funds'] ?? 0, 2); ?></div>
    </div>
    <div class="card" style="border-left:4px solid:#36b9cc;">
        <div style="font-size:0.85rem;color:#64748b;">Active Contracts</div>
        <div style="font-size:1.8rem;font-weight:700;"><?php echo number_format($stats['contracts'] ?? 0); ?></div>
    </div>
    <div class="card" style="border-left:4px solid:#f6c23e;">
        <div style="font-size:0.85rem;color:#64748b;">Labor Budgets</div>
        <div style="font-size:1.8rem;font-weight:700;"><?php echo number_format($stats['labor_budgets'] ?? 0); ?></div>
    </div>
    <div class="card" style="border-left:4px solid:#f59e0b;">
        <div style="font-size:0.85rem;color:#64748b;">Purchase Costs</div>
        <div style="font-size:1.8rem;font-weight:700;">₱<?php echo number_format($stats['purchase_costs'] ?? 0, 2); ?></div>
    </div>
</div>

<!-- Recent Financial Records -->
<div class="card">
    <h3><i class="fas fa-clock"></i> Recent Financial Records</h3>
    <?php if (empty($recent_financial_records)): ?>
        <div class="empty-state">
            <i class="fas fa-chart-line"></i>
            <p>No recent financial records.</p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Type</th>
                        <th>Reference</th>
                        <th>Entity</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_financial_records as $record): ?>
                    <tr>
                        <td><span class="badge badge-secondary"><?php echo htmlspecialchars($record['type']); ?></span></td>
                        <td><strong><?php echo htmlspecialchars($record['reference']); ?></strong></td>
                        <td><?php echo htmlspecialchars($record['entity']); ?></td>
                        <td>₱<?php echo number_format($record['amount'], 2); ?></td>
                        <td><span class="badge badge-<?php echo $record['status']; ?>"><?php echo ucfirst($record['status']); ?></span></td>
                        <td><?php echo date('M d, Y', strtotime($record['created_at'])); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php include '../../includes/footer.php'; ?>
