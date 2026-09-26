<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/mailer.php';

requireDepartment(['accounting']);

$page_title = 'Invoices';
$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);

// Generate invoice number
function generateInvoiceNumber() {
    global $pdo;
    return generateNumber('INV', 'invoices', 'invoice_number');
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ((isset($_POST['add_invoice']) || isset($_POST['update_invoice'])) && !canWriteDepartmentData('accounting')) {
        $_SESSION['error'] = 'Administrators have view-only access.';
        header('Location: ' . basename(__FILE__));
        exit();
    }
    if (isset($_POST['add_invoice']) || isset($_POST['update_invoice'])) {
        $invoice_number = $_POST['invoice_number'] ?? generateInvoiceNumber();
        $project_id = (int)($_POST['project_id'] ?? 0);
        $client = trim($_POST['client'] ?? '');
        $invoice_date = $_POST['invoice_date'] ?? date('Y-m-d');
        $due_date = $_POST['due_date'] ?? '';
        $amount = (float)($_POST['amount'] ?? 0);
        $notes = trim($_POST['notes'] ?? '');
        
        $errors = [];
        if (empty($client)) $errors[] = 'Client name is required.';
        if ($amount <= 0) $errors[] = 'Amount must be greater than 0.';
        
        if (empty($errors)) {
            try {
                if (isset($_POST['add_invoice'])) {
                    $stmt = $pdo->prepare("INSERT INTO invoices (invoice_number, project_id, client, invoice_date, due_date, amount, notes, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$invoice_number, $project_id, $client, $invoice_date, $due_date, $amount, $notes, $_SESSION['user_id']]);
                    try {
                        notifyInvoiceGenerated($invoice_number, $client, $amount);
                    } catch (Throwable $e) {
                        logActivity($_SESSION['user_id'] ?? null, 'Email failed', 'Mail', $e->getMessage());
                    }
                    
                    logActivity($_SESSION['user_id'], 'Created invoice', 'Accounting', "INV: $invoice_number");
                    $_SESSION['success'] = "Invoice created successfully!";
                } else {
                    $stmt = $pdo->prepare("UPDATE invoices SET project_id = ?, client = ?, invoice_date = ?, due_date = ?, amount = ?, notes = ? WHERE id = ?");
                    $stmt->execute([$project_id, $client, $invoice_date, $due_date, $amount, $notes, $id]);
                    
                    logActivity($_SESSION['user_id'], 'Updated invoice', 'Accounting', "INV: $invoice_number");
                    $_SESSION['success'] = "Invoice updated successfully!";
                }
                header('Location: invoices.php');
                exit();
            } catch (PDOException $e) {
                $error = userDatabaseError($e);
            }
        }
    }
    
    if (isset($_POST['record_payment'])) {
        $invoice_id = (int)($_POST['invoice_id'] ?? 0);
        $payment_number = generateNumber('PAY', 'payments', 'payment_number');
        $amount = (float)($_POST['payment_amount'] ?? 0);
        $payment_date = $_POST['payment_date'] ?? date('Y-m-d');
        $payment_method = $_POST['payment_method'] ?? '';
        $reference_number = trim($_POST['reference_number'] ?? '');
        
        $errors = [];
        if ($amount <= 0) $errors[] = 'Payment amount must be greater than 0.';
        
        if (empty($errors)) {
            try {
                $pdo->beginTransaction();
                
                // Get current invoice
                $stmt = $pdo->prepare("SELECT amount, paid_amount, status FROM invoices WHERE id = ?");
                $stmt->execute([$invoice_id]);
                $invoice = $stmt->fetch();
                
                $new_paid = ($invoice['paid_amount'] ?? 0) + $amount;
                $new_status = $new_paid >= $invoice['amount'] ? 'paid' : 'partial';
                
                // Insert payment
                $stmt = $pdo->prepare("INSERT INTO payments (payment_number, invoice_id, amount, payment_date, payment_method, reference_number, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$payment_number, $invoice_id, $amount, $payment_date, $payment_method, $reference_number, $_SESSION['user_id']]);
                
                // Update invoice
                $stmt = $pdo->prepare("UPDATE invoices SET paid_amount = ?, status = ? WHERE id = ?");
                $stmt->execute([$new_paid, $new_status, $invoice_id]);
                
                $pdo->commit();
                
                logActivity($_SESSION['user_id'], 'Recorded payment', 'Accounting', "Payment: $payment_number");
                $_SESSION['success'] = "Payment recorded successfully!";
                header('Location: invoices.php');
                exit();
            } catch (PDOException $e) {
                $pdo->rollBack();
                $error = userDatabaseError($e);
            }
        }
    }
}

// Get projects for dropdown
$projects = $pdo->query("SELECT id, name, project_code FROM projects ORDER BY name")->fetchAll();

// Get invoices
$invoices = [];
try {
    $query = "
        SELECT i.*, p.name as project_name, u.full_name as created_by_name
        FROM invoices i
        LEFT JOIN projects p ON i.project_id = p.id
        LEFT JOIN users u ON i.created_by = u.id
        ORDER BY i.created_at DESC
    ";
    $invoices = $pdo->query($query)->fetchAll();
} catch (PDOException $e) {
    $error = userDatabaseError($e);
}

// Get single invoice for edit/view
$invoice_details = null;
$invoice_payments = [];
if ($action === 'edit' || $action === 'view') {
    $stmt = $pdo->prepare("
        SELECT i.*, p.name as project_name, u.full_name as created_by_name
        FROM invoices i
        LEFT JOIN projects p ON i.project_id = p.id
        LEFT JOIN users u ON i.created_by = u.id
        WHERE i.id = ?
    ");
    $stmt->execute([$id]);
    $invoice_details = $stmt->fetch();
    
    if ($invoice_details) {
        $payments_stmt = $pdo->prepare("SELECT * FROM payments WHERE invoice_id = ? ORDER BY created_at DESC");
        $payments_stmt->execute([$id]);
        $invoice_payments = $payments_stmt->fetchAll();
    }
}


if (($action === 'add' || $action === 'edit') && !canWriteDepartmentData('accounting')) {
    $_SESSION['error'] = 'Administrators have view-only access.';
    header('Location: ' . basename(__FILE__) . ($id ? '?action=view&id=' . $id : ''));
    exit();
}

include '../../includes/header.php';
?>

<div class="page-header">
    <h1><i class="fas fa-file-invoice-dollar"></i> <?php echo $page_title; ?></h1>
    <?php if ($action === 'list'): ?>
        <?php if (canWriteDepartmentData('accounting')): ?><a href="?action=add" class="btn btn-primary"><i class="fas fa-plus"></i> New Invoice</a><?php endif; ?>
    <?php endif; ?>
</div>

<?php if (isset($_SESSION['success'])): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
<?php endif; ?>
<?php if (isset($error)): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<?php if ($action === 'add' || $action === 'edit'): ?>
<!-- Add/Edit Form -->
<div class="card">
    <h3><?php echo $action === 'add' ? 'Create New' : 'Edit'; ?> Invoice</h3>
    <form method="POST" class="form">
        <?php if ($action === 'edit'): ?>
            <input type="hidden" name="update_invoice" value="1">
            <input type="hidden" name="invoice_number" value="<?php echo htmlspecialchars($invoice_details['invoice_number']); ?>">
        <?php else: ?>
            <input type="hidden" name="add_invoice" value="1">
        <?php endif; ?>
        
        <div class="form-row">
            <div class="form-group">
                <label class="required">Invoice Number</label>
                <input type="text" value="<?php echo $action === 'edit' ? htmlspecialchars($invoice_details['invoice_number']) : generateInvoiceNumber(); ?>" disabled style="background:#f1f5f9;">
                <?php if ($action === 'add'): ?>
                    <input type="hidden" name="invoice_number" value="<?php echo generateInvoiceNumber(); ?>">
                <?php endif; ?>
            </div>
            <div class="form-group">
                <label>Project</label>
                <select name="project_id">
                    <option value="">No Project</option>
                    <?php foreach ($projects as $project): ?>
                        <option value="<?php echo $project['id']; ?>" <?php echo ($invoice_details['project_id'] ?? 0) == $project['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($project['project_code'] . ' - ' . $project['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label class="required">Client</label>
                <input type="text" name="client" required value="<?php echo htmlspecialchars($invoice_details['client'] ?? ''); ?>" placeholder="Client name">
            </div>
            <div class="form-group">
                <label>Invoice Date</label>
                <input type="date" name="invoice_date" value="<?php echo $invoice_details['invoice_date'] ?? date('Y-m-d'); ?>">
            </div>
            <div class="form-group">
                <label>Due Date</label>
                <input type="date" name="due_date" value="<?php echo $invoice_details['due_date'] ?? ''; ?>">
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label class="required">Amount (₱)</label>
                <input type="number" step="0.01" name="amount" required value="<?php echo $invoice_details['amount'] ?? 0; ?>" min="0">
            </div>
        </div>
        
        <div class="form-group">
            <label>Notes</label>
            <textarea name="notes" rows="3"><?php echo htmlspecialchars($invoice_details['notes'] ?? ''); ?></textarea>
        </div>
        
        <div style="margin-top:1.5rem;">
            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> <?php echo $action === 'add' ? 'Create' : 'Update'; ?> Invoice</button>
            <a href="invoices.php" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

<?php elseif ($action === 'view' && $invoice_details): ?>
<!-- View Invoice -->
<div class="card">
    <h3>Invoice Details</h3>
    <div class="view-details">
        <div class="detail-row"><span>Invoice #:</span> <strong><?php echo htmlspecialchars($invoice_details['invoice_number']); ?></strong></div>
        <div class="detail-row"><span>Client:</span> <?php echo htmlspecialchars($invoice_details['client']); ?></div>
        <div class="detail-row"><span>Project:</span> <?php echo htmlspecialchars($invoice_details['project_name'] ?? 'N/A'); ?></div>
        <div class="detail-row"><span>Amount:</span> <strong>₱<?php echo number_format($invoice_details['amount'], 2); ?></strong></div>
        <div class="detail-row"><span>Paid Amount:</span> ₱<?php echo number_format($invoice_details['paid_amount'] ?? 0, 2); ?></div>
        <div class="detail-row"><span>Balance:</span> <strong>₱<?php echo number_format(($invoice_details['amount'] - ($invoice_details['paid_amount'] ?? 0)), 2); ?></strong></div>
        <div class="detail-row"><span>Status:</span> <span class="badge badge-<?php echo $invoice_details['status']; ?>"><?php echo ucfirst($invoice_details['status']); ?></span></div>
        <div class="detail-row"><span>Invoice Date:</span> <?php echo date('M d, Y', strtotime($invoice_details['invoice_date'])); ?></div>
        <div class="detail-row"><span>Due Date:</span> <?php echo $invoice_details['due_date'] ? date('M d, Y', strtotime($invoice_details['due_date'])) : 'N/A'; ?></div>
        <div class="detail-row"><span>Created By:</span> <?php echo htmlspecialchars($invoice_details['created_by_name'] ?? 'N/A'); ?></div>
        <div class="detail-row"><span>Created:</span> <?php echo date('M d, Y h:i A', strtotime($invoice_details['created_at'])); ?></div>
        <?php if ($invoice_details['notes']): ?>
            <div class="detail-row"><span>Notes:</span> <?php echo nl2br(htmlspecialchars($invoice_details['notes'])); ?></div>
        <?php endif; ?>
    </div>
    
    <!-- Payments -->
    <h4 style="margin-top:1.5rem;">Payment History</h4>
    <?php if (empty($invoice_payments)): ?>
        <p style="color:#94a3b8;">No payments recorded yet.</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Payment #</th>
                        <th>Amount</th>
                        <th>Date</th>
                        <th>Method</th>
                        <th>Reference</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($invoice_payments as $payment): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($payment['payment_number']); ?></td>
                        <td>₱<?php echo number_format($payment['amount'], 2); ?></td>
                        <td><?php echo date('M d, Y', strtotime($payment['payment_date'])); ?></td>
                        <td><?php echo htmlspecialchars($payment['payment_method'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($payment['reference_number'] ?? 'N/A'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
    
    <!-- Record Payment -->
    <?php if ($invoice_details['status'] !== 'paid' && $invoice_details['status'] !== 'cancelled'): ?>
    <div style="margin-top:1.5rem; padding-top:1.5rem; border-top:1px solid #e2e8f0;">
        <h4>Record Payment</h4>
        <form method="POST" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:1rem;align-items:end;">
            <input type="hidden" name="invoice_id" value="<?php echo $invoice_details['id']; ?>">
            <input type="hidden" name="record_payment" value="1">
            <div class="form-group">
                <label>Amount</label>
                <input type="number" step="0.01" name="payment_amount" required min="0" max="<?php echo $invoice_details['amount'] - ($invoice_details['paid_amount'] ?? 0); ?>">
            </div>
            <div class="form-group">
                <label>Payment Date</label>
                <input type="date" name="payment_date" value="<?php echo date('Y-m-d'); ?>" required>
            </div>
            <div class="form-group">
                <label>Method</label>
                <select name="payment_method">
                    <option value="">Select</option>
                    <option value="Cash">Cash</option>
                    <option value="Bank Transfer">Bank Transfer</option>
                    <option value="Check">Check</option>
                    <option value="Credit Card">Credit Card</option>
                </select>
            </div>
            <div class="form-group">
                <label>Reference #</label>
                <input type="text" name="reference_number" placeholder="Reference number">
            </div>
            <button type="submit" class="btn btn-success"><i class="fas fa-check"></i> Record Payment</button>
        </form>
    </div>
    <?php endif; ?>
    
    <div style="margin-top:1.5rem;">
        <?php if ($invoice_details['status'] !== 'paid' && $invoice_details['status'] !== 'cancelled'): ?>
            <?php if (canWriteDepartmentData('accounting')): ?><a href="?action=edit&id=<?php echo $invoice_details['id']; ?>" class="btn btn-warning"><i class="fas fa-edit"></i> Edit</a><?php endif; ?>
        <?php endif; ?>
        <a href="invoices.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back</a>
    </div>
</div>

<?php else: ?>
<!-- List View -->
<div class="card">
    <div class="table-responsive">
        <table class="table" id="invoiceTable">
            <thead>
                <tr>
                    <th data-sort-type="string" onclick="sortTable('invoiceTable', 0)">Invoice #</th>
                    <th onclick="sortTable('invoiceTable', 1)">Client</th>
                    <th onclick="sortTable('invoiceTable', 2)">Project</th>
                    <th onclick="sortTable('invoiceTable', 3)">Amount</th>
                    <th onclick="sortTable('invoiceTable', 4)">Paid</th>
                    <th onclick="sortTable('invoiceTable', 5)">Status</th>
                    <th onclick="sortTable('invoiceTable', 6)">Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($invoices)): ?>
                    <tr>
                        <td colspan="8" style="text-align:center;color:#94a3b8;padding:2rem;">No invoices found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($invoices as $inv): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($inv['invoice_number']); ?></strong></td>
                        <td><?php echo htmlspecialchars($inv['client']); ?></td>
                        <td><?php echo htmlspecialchars($inv['project_name'] ?? 'N/A'); ?></td>
                        <td>₱<?php echo number_format($inv['amount'], 2); ?></td>
                        <td>₱<?php echo number_format($inv['paid_amount'] ?? 0, 2); ?></td>
                        <td><span class="badge badge-<?php echo $inv['status']; ?>"><?php echo ucfirst($inv['status']); ?></span></td>
                        <td><?php echo date('M d, Y', strtotime($inv['created_at'])); ?></td>
                        <td>
                            <a href="?action=view&id=<?php echo $inv['id']; ?>" class="btn btn-sm btn-info"><i class="fas fa-eye"></i></a>
                            <?php if ($inv['status'] !== 'paid' && $inv['status'] !== 'cancelled'): ?>
                                <?php if (canWriteDepartmentData('accounting')): ?><a href="?action=edit&id=<?php echo $inv['id']; ?>" class="btn btn-sm btn-warning"><i class="fas fa-edit"></i></a><?php endif; ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php include '../../includes/footer.php'; ?>