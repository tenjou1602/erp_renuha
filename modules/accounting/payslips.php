<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';

requireDepartment(['accounting']);

$page_title = 'Payslips';
$id = (int)($_GET['id'] ?? 0);

$payslip = null;
$payslips = [];

try {
    if ($id > 0) {
        $stmt = $pdo->prepare("
            SELECT p.*, u.full_name as created_by_name
            FROM payroll p
            LEFT JOIN users u ON p.created_by = u.id
            WHERE p.id = ?
        ");
        $stmt->execute([$id]);
        $payslip = $stmt->fetch();
    }

    $payslips = $pdo->query("
        SELECT * FROM payroll
        WHERE status IN ('approved', 'paid')
        ORDER BY created_at DESC
    ")->fetchAll();
} catch (PDOException $e) {
    $error = 'Unable to load payslips.';
}

include '../../includes/header.php';
?>

<div class="page-header">
    <h1><i class="fas fa-file-alt"></i> Payslips</h1>
    <a href="payroll.php" class="btn btn-secondary"><i class="fas fa-users"></i> Payroll</a>
</div>

<?php if (isset($error)): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<?php if ($payslip): ?>
<div class="card">
    <div class="page-header">
        <h3>Payslip — <?php echo htmlspecialchars($payslip['payroll_number']); ?></h3>
        <button type="button" class="btn btn-outline no-print" onclick="window.print()"><i class="fas fa-print"></i> Print</button>
    </div>
    <div class="view-details">
        <div class="detail-row"><span>Employee:</span> <?php echo htmlspecialchars($payslip['employee_name']); ?></div>
        <div class="detail-row"><span>Position:</span> <?php echo htmlspecialchars($payslip['employee_position'] ?? 'N/A'); ?></div>
        <div class="detail-row"><span>Period:</span> <?php echo date('M d, Y', strtotime($payslip['period_start'])) . ' — ' . date('M d, Y', strtotime($payslip['period_end'])); ?></div>
        <div class="detail-row"><span>Basic Salary:</span> ₱<?php echo number_format($payslip['basic_salary'], 2); ?></div>
        <div class="detail-row"><span>Deductions:</span> ₱<?php echo number_format($payslip['deductions'] ?? 0, 2); ?></div>
        <div class="detail-row"><span>Net Pay:</span> <strong>₱<?php echo number_format($payslip['net_pay'], 2); ?></strong></div>
        <div class="detail-row"><span>Status:</span> <span class="badge badge-<?php echo $payslip['status']; ?>"><?php echo ucfirst($payslip['status']); ?></span></div>
    </div>
    <div class="table-actions no-print">
        <a href="payslips.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back</a>
    </div>
</div>
<?php else: ?>
<div class="card">
    <p class="muted-note">Approved and paid payroll records. Draft payroll is managed under Payroll.</p>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Payroll #</th>
                    <th>Employee</th>
                    <th>Period</th>
                    <th>Net Pay</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($payslips)): ?>
                    <tr><td colspan="6" class="table-empty">No payslips available yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($payslips as $row): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($row['payroll_number']); ?></strong></td>
                        <td><?php echo htmlspecialchars($row['employee_name']); ?></td>
                        <td><?php echo date('M d', strtotime($row['period_start'])) . ' — ' . date('M d', strtotime($row['period_end'])); ?></td>
                        <td>₱<?php echo number_format($row['net_pay'], 2); ?></td>
                        <td><span class="badge badge-<?php echo $row['status']; ?>"><?php echo ucfirst($row['status']); ?></span></td>
                        <td><a href="?id=<?php echo (int)$row['id']; ?>" class="btn btn-sm btn-info"><i class="fas fa-eye"></i></a></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php include '../../includes/footer.php'; ?>
