<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';

requireDepartment(['accounting']);

$page_title = 'Payments';
$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);

// Get payments
$payments = [];
try {
    $query = "
        SELECT p.*, i.invoice_number, i.client as invoice_client, i.project_id,
               pr.name as project_name, u.full_name as created_by_name
        FROM payments p
        LEFT JOIN invoices i ON p.invoice_id = i.id
        LEFT JOIN projects pr ON i.project_id = pr.id
        LEFT JOIN users u ON p.created_by = u.id
        ORDER BY p.created_at DESC
    ";
    $payments = $pdo->query($query)->fetchAll();
} catch (PDOException $e) {
    $error = 'Database error: ' . $e->getMessage();
}

include '../../includes/header.php';
?>

<div class="page-header">
    <h1><i class="fas fa-credit-card"></i> <?php echo $page_title; ?></h1>
</div>

<?php if (isset($_SESSION['success'])): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
<?php endif; ?>
<?php if (isset($error)): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<div class="card">
    <div class="table-responsive">
        <table class="table" id="paymentTable">
            <thead>
                <tr>
                    <th onclick="sortTable('paymentTable', 0)">Payment #</th>
                    <th onclick="sortTable('paymentTable', 1)">Invoice</th>
                    <th onclick="sortTable('paymentTable', 2)">Client</th>
                    <th onclick="sortTable('paymentTable', 3)">Amount</th>
                    <th onclick="sortTable('paymentTable', 4)">Method</th>
                    <th onclick="sortTable('paymentTable', 5)">Date</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($payments)): ?>
                    <tr>
                        <td colspan="6" class="table-empty">No payments recorded.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($payments as $payment): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($payment['payment_number']); ?></strong></td>
                        <td><?php echo htmlspecialchars($payment['invoice_number'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($payment['invoice_client'] ?? 'N/A'); ?></td>
                        <td>₱<?php echo number_format($payment['amount'], 2); ?></td>
                        <td><?php echo htmlspecialchars($payment['payment_method'] ?? 'N/A'); ?></td>
                        <td><?php echo date('M d, Y', strtotime($payment['payment_date'])); ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>