<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';

checkDepartment(['accounting', 'admin']);

$page_title = 'Payroll';
$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);

function generatePayrollNumber() {
    return generateNumber('PRL', 'payroll', 'payroll_number');
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ((isset($_POST['add_payroll']) || isset($_POST['update_payroll'])) && !canWriteDepartmentData('payroll')) {
        $_SESSION['error'] = 'Administrators have view-only access.';
        header('Location: ' . basename(__FILE__));
        exit();
    }
    if (isset($_POST['add_payroll']) || isset($_POST['update_payroll'])) {
        $payroll_number = $_POST['payroll_number'] ?? generatePayrollNumber();
        $employee_name = trim($_POST['employee_name'] ?? '');
        $employee_position = trim($_POST['employee_position'] ?? '');
        $period_start = $_POST['period_start'] ?? '';
        $period_end = $_POST['period_end'] ?? '';
        $basic_salary = (float)($_POST['basic_salary'] ?? 0);
        $allowances = (float)($_POST['allowances'] ?? 0);
        $deductions = (float)($_POST['deductions'] ?? 0);
        $net_pay = $basic_salary + $allowances - $deductions;
        $status = $_POST['status'] ?? 'draft';
        
        $errors = [];
        if (empty($employee_name)) $errors[] = 'Employee name is required.';
        if ($basic_salary <= 0) $errors[] = 'Basic salary must be greater than 0.';
        
        if (empty($errors)) {
            try {
                if (isset($_POST['add_payroll'])) {
                    $stmt = $pdo->prepare("INSERT INTO payroll (payroll_number, employee_name, employee_position, period_start, period_end, basic_salary, allowances, deductions, net_pay, status, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$payroll_number, $employee_name, $employee_position, $period_start, $period_end, $basic_salary, $allowances, $deductions, $net_pay, $status, $_SESSION['user_id']]);
                    
                    logActivity($_SESSION['user_id'], 'Created payroll', 'Accounting', "PRL: $payroll_number");
                    $_SESSION['success'] = "Payroll created successfully!";
                } else {
                    $stmt = $pdo->prepare("UPDATE payroll SET employee_name = ?, employee_position = ?, period_start = ?, period_end = ?, basic_salary = ?, allowances = ?, deductions = ?, net_pay = ?, status = ? WHERE id = ?");
                    $stmt->execute([$employee_name, $employee_position, $period_start, $period_end, $basic_salary, $allowances, $deductions, $net_pay, $status, $id]);
                    
                    logActivity($_SESSION['user_id'], 'Updated payroll', 'Accounting', "PRL: $payroll_number");
                    $_SESSION['success'] = "Payroll updated successfully!";
                }
                header('Location: payroll.php');
                exit();
            } catch (PDOException $e) {
                $error = userDatabaseError($e);
            }
        }
    }
}

// Get payroll records
$payrolls = [];
try {
    $query = "
        SELECT p.*, u.full_name as created_by_name
        FROM payroll p
        LEFT JOIN users u ON p.created_by = u.id
        ORDER BY p.created_at DESC
    ";
    $payrolls = $pdo->query($query)->fetchAll();
} catch (PDOException $e) {
    $error = userDatabaseError($e);
}

// Get single payroll
$payroll_details = null;
if ($action === 'edit' || $action === 'view') {
    $stmt = $pdo->prepare("
        SELECT p.*, u.full_name as created_by_name
        FROM payroll p
        LEFT JOIN users u ON p.created_by = u.id
        WHERE p.id = ?
    ");
    $stmt->execute([$id]);
    $payroll_details = $stmt->fetch();
}


if (($action === 'add' || $action === 'edit') && !canWriteDepartmentData('payroll')) {
    $_SESSION['error'] = 'Administrators have view-only access.';
    header('Location: ' . basename(__FILE__) . ($id ? '?action=view&id=' . $id : ''));
    exit();
}

include '../../includes/header.php';
?>

<div class="page-header">
    <h1><i class="fas fa-users"></i> <?php echo $page_title; ?></h1>
    <?php if ($action === 'list'): ?>
        <?php if (canWriteDepartmentData('payroll')): ?><a href="?action=add" class="btn btn-primary"><i class="fas fa-plus"></i> New Payroll</a><?php endif; ?>
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
    <h3><?php echo $action === 'add' ? 'Create New' : 'Edit'; ?> Payroll</h3>
    <form method="POST">
        <?php if ($action === 'edit'): ?>
            <input type="hidden" name="update_payroll" value="1">
            <input type="hidden" name="payroll_number" value="<?php echo htmlspecialchars($payroll_details['payroll_number']); ?>">
        <?php else: ?>
            <input type="hidden" name="add_payroll" value="1">
        <?php endif; ?>
        
        <div class="form-row">
            <div class="form-group">
                <label>Payroll Number</label>
                <input type="text" value="<?php echo $action === 'edit' ? htmlspecialchars($payroll_details['payroll_number']) : generatePayrollNumber(); ?>" disabled style="background:#f1f5f9;">
                <?php if ($action === 'add'): ?>
                    <input type="hidden" name="payroll_number" value="<?php echo generatePayrollNumber(); ?>">
                <?php endif; ?>
            </div>
            <div class="form-group">
                <label>Status</label>
                <select name="status">
                    <option value="draft" <?php echo ($payroll_details['status'] ?? '') == 'draft' ? 'selected' : ''; ?>>Draft</option>
                    <option value="approved" <?php echo ($payroll_details['status'] ?? '') == 'approved' ? 'selected' : ''; ?>>Approved</option>
                    <option value="paid" <?php echo ($payroll_details['status'] ?? '') == 'paid' ? 'selected' : ''; ?>>Paid</option>
                </select>
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label class="required">Employee Name</label>
                <input type="text" name="employee_name" required value="<?php echo htmlspecialchars($payroll_details['employee_name'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label>Position</label>
                <input type="text" name="employee_position" value="<?php echo htmlspecialchars($payroll_details['employee_position'] ?? ''); ?>">
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label>Period Start</label>
                <input type="date" name="period_start" value="<?php echo $payroll_details['period_start'] ?? date('Y-m-d', strtotime('first day of this month')); ?>">
            </div>
            <div class="form-group">
                <label>Period End</label>
                <input type="date" name="period_end" value="<?php echo $payroll_details['period_end'] ?? date('Y-m-d', strtotime('last day of this month')); ?>">
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label class="required">Basic Salary (₱)</label>
                <input type="number" step="0.01" name="basic_salary" required value="<?php echo $payroll_details['basic_salary'] ?? 0; ?>" min="0">
            </div>
            <div class="form-group">
                <label>Allowances (₱)</label>
                <input type="number" step="0.01" name="allowances" value="<?php echo $payroll_details['allowances'] ?? 0; ?>" min="0">
            </div>
            <div class="form-group">
                <label>Deductions (₱)</label>
                <input type="number" step="0.01" name="deductions" value="<?php echo $payroll_details['deductions'] ?? 0; ?>" min="0">
            </div>

        </div>
        
        <div class="form-group" style="background:#f1f5f9; padding:1rem; border-radius:8px;">
            <label>Net Pay</label>
            <input type="text" value="₱<?php echo number_format(($payroll_details['basic_salary'] ?? 0) + ($payroll_details['allowances'] ?? 0) - ($payroll_details['deductions'] ?? 0), 2); ?>" disabled style="background:white; font-weight:bold;">
            <small class="help-text">Calculated automatically: Basic Salary + Allowances - Deductions</small>
        </div>
        
        <div style="margin-top:1.5rem;">
            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> <?php echo $action === 'add' ? 'Create' : 'Update'; ?> Payroll</button>
            <a href="payroll.php" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

<?php elseif ($action === 'view' && $payroll_details): ?>
<!-- View Payroll -->
<div class="card">
    <h3>Payroll Details</h3>
    <div class="view-details">
        <div class="detail-row"><span>Payroll #:</span> <strong><?php echo htmlspecialchars($payroll_details['payroll_number']); ?></strong></div>
        <div class="detail-row"><span>Employee:</span> <?php echo htmlspecialchars($payroll_details['employee_name']); ?></div>
        <div class="detail-row"><span>Position:</span> <?php echo htmlspecialchars($payroll_details['employee_position'] ?? 'N/A'); ?></div>
        <div class="detail-row"><span>Period:</span> <?php echo date('M d, Y', strtotime($payroll_details['period_start'])) . ' - ' . date('M d, Y', strtotime($payroll_details['period_end'])); ?></div>
        <div class="detail-row"><span>Basic Salary:</span> ₱<?php echo number_format($payroll_details['basic_salary'], 2); ?></div>
        <div class="detail-row"><span>Allowances:</span> ₱<?php echo number_format($payroll_details['allowances'] ?? 0, 2); ?></div>
        <div class="detail-row"><span>Deductions:</span> ₱<?php echo number_format($payroll_details['deductions'] ?? 0, 2); ?></div>
        <div class="detail-row"><span>Net Pay:</span> <strong>₱<?php echo number_format($payroll_details['net_pay'], 2); ?></strong></div>
        <div class="detail-row"><span>Status:</span> <span class="badge badge-<?php echo $payroll_details['status']; ?>"><?php echo ucfirst($payroll_details['status']); ?></span></div>
        <div class="detail-row"><span>Created By:</span> <?php echo htmlspecialchars($payroll_details['created_by_name'] ?? 'N/A'); ?></div>
        <div class="detail-row"><span>Created:</span> <?php echo date('M d, Y h:i A', strtotime($payroll_details['created_at'])); ?></div>
    </div>
    <div style="margin-top:1.5rem;">
        <?php if (canWriteDepartmentData('payroll')): ?><a href="?action=edit&id=<?php echo $payroll_details['id']; ?>" class="btn btn-warning"><i class="fas fa-edit"></i> Edit</a><?php endif; ?>
        <a href="payroll.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back</a>
    </div>
</div>

<?php else: ?>
<!-- List View -->
<div class="card">
    <div class="table-responsive">
        <table class="table" id="payrollTable">
            <thead>
                <tr>
                    <th data-sort-type="string" onclick="sortTable('payrollTable', 0)">Payroll #</th>
                    <th onclick="sortTable('payrollTable', 1)">Employee</th>
                    <th onclick="sortTable('payrollTable', 2)">Period</th>
                    <th onclick="sortTable('payrollTable', 3)">Net Pay</th>
                    <th onclick="sortTable('payrollTable', 4)">Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($payrolls)): ?>
                    <tr>
                        <td colspan="6" style="text-align:center;color:#94a3b8;padding:2rem;">No payroll records found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($payrolls as $pay): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($pay['payroll_number']); ?></strong></td>
                        <td><?php echo htmlspecialchars($pay['employee_name']); ?></td>
                        <td><?php echo date('M d', strtotime($pay['period_start'])) . ' - ' . date('M d', strtotime($pay['period_end'])); ?></td>
                        <td>₱<?php echo number_format($pay['net_pay'], 2); ?></td>
                        <td><span class="badge badge-<?php echo $pay['status']; ?>"><?php echo ucfirst($pay['status']); ?></span></td>
                        <td>
                            <a href="?action=view&id=<?php echo $pay['id']; ?>" class="btn btn-sm btn-info"><i class="fas fa-eye"></i></a>
                            <?php if (canWriteDepartmentData('payroll')): ?><a href="?action=edit&id=<?php echo $pay['id']; ?>" class="btn btn-sm btn-warning"><i class="fas fa-edit"></i></a><?php endif; ?>
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