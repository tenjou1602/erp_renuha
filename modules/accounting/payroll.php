<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/mailer.php';
require_once '../../includes/payroll_functions.php';

requireDepartment(['accounting']);

if (($_GET['action'] ?? '') === 'employee_data') {
    header('Content-Type: application/json');
    $user_id = (int)($_GET['user_id'] ?? 0);
    $period_start = $_GET['period_start'] ?? date('Y-m-01');
    $period_end = $_GET['period_end'] ?? date('Y-m-t');

    try {
        $employee_stmt = $pdo->prepare("SELECT id, full_name, monthly_salary FROM users WHERE id = ? AND status = 'active'");
        $employee_stmt->execute([$user_id]);
        $employee = $employee_stmt->fetch();
        if (!$employee) {
            throw new RuntimeException('Employee not found.');
        }

        $attendance_stmt = $pdo->prepare("SELECT
                SUM(status = 'present') AS days_present,
                SUM(status = 'late') AS days_late,
                SUM(status = 'absent') AS days_absent,
                SUM(status = 'half_day') AS half_days
            FROM attendance
            WHERE user_id = ? AND attendance_date BETWEEN ? AND ?");
        $attendance_stmt->execute([$user_id, $period_start, $period_end]);
        $attendance = $attendance_stmt->fetch() ?: [];
        $basic_salary = round((float)($employee['monthly_salary'] ?? 0), 2);
        $days_absent = (float)($attendance['days_absent'] ?? 0);
        $half_days = (float)($attendance['half_days'] ?? 0);
        $deductions = round(($basic_salary / 22) * ($days_absent + ($half_days * 0.5)), 2);

        echo json_encode([
            'success' => true,
            'employee_name' => $employee['full_name'],
            'basic_salary' => $basic_salary,
            'days_present' => (int)($attendance['days_present'] ?? 0),
            'days_late' => (int)($attendance['days_late'] ?? 0),
            'days_absent' => (int)$days_absent,
            'half_days' => (int)$half_days,
            'deductions' => $deductions,
        ]);
    } catch (Throwable $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit();
}

$page_title = 'Payroll';
$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);

function generatePayrollNumber() {
    return generateNumber('PRL', 'payroll', 'payroll_number');
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_payroll']) || isset($_POST['update_payroll'])) {
        $payroll_number = $_POST['payroll_number'] ?? generatePayrollNumber();
        $user_id = (int)($_POST['user_id'] ?? 0);
        $employee_name = trim($_POST['employee_name'] ?? '');
        $employee_position = trim($_POST['employee_position'] ?? '');
        $period_start = $_POST['period_start'] ?? '';
        $period_end = $_POST['period_end'] ?? '';
        $basic_salary = (float)($_POST['basic_salary'] ?? 0);
        $deductions = (float)($_POST['deductions'] ?? 0);
        $manual_deductions = isset($_POST['manual_deductions']);
        $errors = [];

        if ($user_id > 0) {
            try {
                $employee_stmt = $pdo->prepare("SELECT full_name, monthly_salary FROM users WHERE id = ? AND status = 'active'");
                $employee_stmt->execute([$user_id]);
                $employee = $employee_stmt->fetch();
                if ($employee) {
                    $employee_name = $employee['full_name'];
                    $basic_salary = (float)$employee['monthly_salary'];
                    if (!$manual_deductions) {
                        $attendance_stmt = $pdo->prepare("SELECT
                                SUM(status = 'absent') AS days_absent,
                                SUM(status = 'half_day') AS half_days
                            FROM attendance
                            WHERE user_id = ? AND attendance_date BETWEEN ? AND ?");
                        $attendance_stmt->execute([$user_id, $period_start, $period_end]);
                        $attendance = $attendance_stmt->fetch() ?: [];
                        $deductions = round(($basic_salary / 22) * ((float)($attendance['days_absent'] ?? 0) + ((float)($attendance['half_days'] ?? 0) * 0.5)), 2);
                    }
                } else {
                    $errors[] = 'Selected employee is not active.';
                }
            } catch (PDOException $e) {
                $errors[] = 'Employee payroll data is unavailable. Run the attendance payroll migration first.';
            }
        }
        $computed = computePayroll($basic_salary, $deductions);
        $basic_salary = $computed['basic_salary'];
        $deductions = $computed['deductions'];
        $net_pay = $computed['net_pay'];
        $status = $_POST['status'] ?? 'draft';
        
        if ($user_id <= 0) $errors[] = 'Employee is required.';
        if (empty($employee_name)) $errors[] = 'Employee name is required.';
        if ($basic_salary <= 0) $errors[] = 'Basic salary must be greater than 0.';
        
        if (empty($errors)) {
            try {
                if (isset($_POST['add_payroll'])) {
                    $stmt = $pdo->prepare("INSERT INTO payroll (user_id, payroll_number, employee_name, employee_position, period_start, period_end, basic_salary, deductions, net_pay, status, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$user_id, $payroll_number, $employee_name, $employee_position, $period_start, $period_end, $basic_salary, $deductions, $net_pay, $status, $_SESSION['user_id']]);
                    if (in_array($status, ['approved', 'paid'], true)) {
                        try {
                            notifyPayslipReady($employee_name, $payroll_number, $net_pay, $period_start, $period_end);
                        } catch (Throwable $e) {
                            logActivity($_SESSION['user_id'] ?? null, 'Email failed', 'Mail', $e->getMessage());
                        }
                    }
                    
                    logActivity($_SESSION['user_id'], 'Created payroll', 'Accounting', "PRL: $payroll_number");
                    $_SESSION['success'] = "Payroll created successfully!";
                } else {
                    $stmt = $pdo->prepare("UPDATE payroll SET user_id = ?, employee_name = ?, employee_position = ?, period_start = ?, period_end = ?, basic_salary = ?, deductions = ?, net_pay = ?, status = ? WHERE id = ?");
                    $stmt->execute([$user_id, $employee_name, $employee_position, $period_start, $period_end, $basic_salary, $deductions, $net_pay, $status, $id]);
                    if (in_array($status, ['approved', 'paid'], true)) {
                        try {
                            notifyPayslipReady($employee_name, $payroll_number, $net_pay, $period_start, $period_end);
                        } catch (Throwable $e) {
                            logActivity($_SESSION['user_id'] ?? null, 'Email failed', 'Mail', $e->getMessage());
                        }
                    }

                    logActivity($_SESSION['user_id'], 'Updated payroll', 'Accounting', "PRL: $payroll_number");
                    $_SESSION['success'] = "Payroll updated successfully!";
                }
                header('Location: payroll.php');
                exit();
            } catch (PDOException $e) {
                $error = 'Database error: ' . $e->getMessage();
            }
        }
    }
}

// Active employees available for attendance-based payroll.
$employees = [];
try {
    $employees = $pdo->query("SELECT id, full_name, monthly_salary FROM users WHERE status = 'active' AND department != 'admin' ORDER BY full_name")->fetchAll();
} catch (PDOException $e) {
    $error = 'Employee payroll data is unavailable. Run the attendance payroll migration first.';
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
    $error = 'Database error: ' . $e->getMessage();
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

include '../../includes/header.php';
?>

<div class="page-header">
    <h1><i class="fas fa-users"></i> <?php echo $page_title; ?></h1>
    <?php if ($action === 'list'): ?>
        <a href="?action=add" class="btn btn-primary"><i class="fas fa-plus"></i> New Payroll</a>
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
                <input type="text" value="<?php echo $action === 'edit' ? htmlspecialchars($payroll_details['payroll_number']) : generatePayrollNumber(); ?>" disabled class="readonly-field">
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
                <label class="required">Employee</label>
                <select name="user_id" id="employeeSelect" required>
                    <option value="">Select active employee</option>
                    <?php foreach ($employees as $employee): ?>
                        <option value="<?php echo (int)$employee['id']; ?>" <?php echo (int)($payroll_details['user_id'] ?? 0) === (int)$employee['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($employee['full_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <input type="hidden" name="employee_name" id="employeeName" value="<?php echo htmlspecialchars($payroll_details['employee_name'] ?? ''); ?>">
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
                <input type="number" step="0.01" name="basic_salary" id="basicSalary" required value="<?php echo $payroll_details['basic_salary'] ?? 0; ?>" min="0" readonly>
            </div>
            <div class="form-group">
                <label>Deductions (₱)</label>
                <input type="number" step="0.01" name="deductions" id="deductions" value="<?php echo $payroll_details['deductions'] ?? 0; ?>" min="0">
                <label><input type="checkbox" name="manual_deductions" id="manualDeductions"> Manual override</label>
            </div>
        </div>

        <div class="form-row payroll-attendance-summary">
            <div class="form-group"><label>Days Present</label><input type="text" id="daysPresent" value="0" readonly></div>
            <div class="form-group"><label>Days Late</label><input type="text" id="daysLate" value="0" readonly></div>
            <div class="form-group"><label>Days Absent</label><input type="text" id="daysAbsent" value="0" readonly></div>
            <div class="form-group"><label>Half Days</label><input type="text" id="halfDays" value="0" readonly></div>
        </div>
        
        <div class="form-group net-pay-box">
            <label>Net Pay</label>
            <input type="text" value="₱<?php echo number_format(($payroll_details['basic_salary'] ?? 0) - ($payroll_details['deductions'] ?? 0), 2); ?>" disabled>
            <small class="help-text">Calculated automatically: Basic Salary - Deductions</small>
        </div>
        
        <div class="form-actions">
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
        <div class="detail-row"><span>Deductions:</span> ₱<?php echo number_format($payroll_details['deductions'] ?? 0, 2); ?></div>
        <div class="detail-row"><span>Net Pay:</span> <strong>₱<?php echo number_format($payroll_details['net_pay'], 2); ?></strong></div>
        <div class="detail-row"><span>Status:</span> <span class="badge badge-<?php echo $payroll_details['status']; ?>"><?php echo ucfirst($payroll_details['status']); ?></span></div>
        <div class="detail-row"><span>Created By:</span> <?php echo htmlspecialchars($payroll_details['created_by_name'] ?? 'N/A'); ?></div>
        <div class="detail-row"><span>Created:</span> <?php echo date('M d, Y h:i A', strtotime($payroll_details['created_at'])); ?></div>
    </div>
    <div class="table-actions">
        <a href="?action=edit&id=<?php echo $payroll_details['id']; ?>" class="btn btn-warning"><i class="fas fa-edit"></i> Edit</a>
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
                    <th onclick="sortTable('payrollTable', 0)">Payroll #</th>
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
                        <td colspan="6" class="table-empty">No payroll records found.</td>
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
                            <a href="?action=edit&id=<?php echo $pay['id']; ?>" class="btn btn-sm btn-warning"><i class="fas fa-edit"></i></a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<script>
const employeeSelect = document.getElementById('employeeSelect');
const periodStart = document.querySelector('[name="period_start"]');
const periodEnd = document.querySelector('[name="period_end"]');
const employeeName = document.getElementById('employeeName');
const basicSalary = document.getElementById('basicSalary');
const deductions = document.getElementById('deductions');
const manualDeductions = document.getElementById('manualDeductions');

async function loadPayrollData() {
    if (!employeeSelect || !employeeSelect.value) return;
    const params = new URLSearchParams({
        action: 'employee_data',
        user_id: employeeSelect.value,
        period_start: periodStart.value,
        period_end: periodEnd.value
    });
    try {
        const response = await fetch('payroll.php?' + params.toString(), { headers: { Accept: 'application/json' } });
        const data = await response.json();
        if (!data.success) throw new Error(data.error || 'Unable to load employee data.');
        employeeName.value = data.employee_name;
        basicSalary.value = data.basic_salary.toFixed(2);
        document.getElementById('daysPresent').value = data.days_present;
        document.getElementById('daysLate').value = data.days_late;
        document.getElementById('daysAbsent').value = data.days_absent;
        document.getElementById('halfDays').value = data.half_days;
        if (!manualDeductions.checked) deductions.value = data.deductions.toFixed(2);
    } catch (error) {
        console.error(error);
    }
}

employeeSelect?.addEventListener('change', loadPayrollData);
periodStart?.addEventListener('change', loadPayrollData);
periodEnd?.addEventListener('change', loadPayrollData);
if (employeeSelect?.value) loadPayrollData();
</script>

<?php include '../../includes/footer.php'; ?>
<?php include '../../includes/footer.php'; ?>
<?php include '../../includes/footer.php'; ?>
<?php include '../../includes/footer.php'; ?>
<?php include '../../includes/footer.php'; ?>
<?php include '../../includes/footer.php'; ?>
<?php include '../../includes/footer.php'; ?>
<?php include '../../includes/footer.php'; ?>
<?php include '../../includes/footer.php'; ?>
<?php include '../../includes/footer.php'; ?>
<?php include '../../includes/footer.php'; ?>