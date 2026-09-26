<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';

requireDepartment(['accounting']);

$page_title = 'Labor/Salary Budget';
$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ((isset($_POST['add_budget']) || isset($_POST['update_budget'])) && !canWriteDepartmentData('accounting')) {
        $_SESSION['error'] = 'Administrators have view-only access.';
        header('Location: labor_budget.php');
        exit();
    }
    
    if (isset($_POST['add_budget']) || isset($_POST['update_budget'])) {
        $budget_name = trim($_POST['budget_name'] ?? '');
        $project_id = (int)($_POST['project_id'] ?? 0);
        $user_id = (int)($_POST['user_id'] ?? 0);
        $position = trim($_POST['position'] ?? '');
        $allocated_amount = (float)($_POST['allocated_amount'] ?? 0);
        $period_start = $_POST['period_start'] ?? null;
        $period_end = $_POST['period_end'] ?? null;
        $description = trim($_POST['description'] ?? '');
        $status = $_POST['status'] ?? 'active';
        
        $errors = [];
        if (empty($budget_name)) $errors[] = 'Budget name is required.';
        if ($allocated_amount <= 0) $errors[] = 'Allocated amount must be greater than 0.';
        
        if (empty($errors)) {
            try {
                if (isset($_POST['add_budget'])) {
                    $stmt = $pdo->prepare("INSERT INTO labor_budgets (budget_name, project_id, user_id, position, allocated_amount, period_start, period_end, description, status, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$budget_name, $project_id, $user_id, $position, $allocated_amount, $period_start, $period_end, $description, $status, $_SESSION['user_id']]);
                    
                    logActivity($_SESSION['user_id'], 'Created labor budget', 'Accounting', "Budget: $budget_name");
                    $_SESSION['success'] = "Labor budget created successfully!";
                } else {
                    $stmt = $pdo->prepare("UPDATE labor_budgets SET budget_name = ?, project_id = ?, user_id = ?, position = ?, allocated_amount = ?, period_start = ?, period_end = ?, description = ?, status = ? WHERE id = ?");
                    $stmt->execute([$budget_name, $project_id, $user_id, $position, $allocated_amount, $period_start, $period_end, $description, $status, $id]);
                    
                    logActivity($_SESSION['user_id'], 'Updated labor budget', 'Accounting', "Budget: $budget_name");
                    $_SESSION['success'] = "Labor budget updated successfully!";
                }
                header('Location: labor_budget.php');
                exit();
            } catch (PDOException $e) {
                $error = userDatabaseError($e);
            }
        }
    }
}

// Handle Delete
if (isset($_GET['delete_id']) && $action === 'delete') {
    if (!canDelete('accounting')) {
        $_SESSION['error'] = 'Administrators cannot delete labor budget records.';
        header('Location: labor_budget.php');
        exit();
    }
    $delete_id = (int)$_GET['delete_id'];
    try {
        $stmt = $pdo->prepare("DELETE FROM labor_budgets WHERE id = ?");
        $stmt->execute([$delete_id]);
        $_SESSION['success'] = "Labor budget deleted successfully!";
        header('Location: labor_budget.php');
        exit();
    } catch (PDOException $e) {
        $error = userDatabaseError($e);
    }
}

// Check if labor_budgets table exists, if not create it
try {
    $pdo->query("SELECT 1 FROM labor_budgets LIMIT 1");
} catch (PDOException $e) {
    // Create labor_budgets table if it doesn't exist
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS labor_budgets (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            budget_name VARCHAR(200) NOT NULL,
            project_id BIGINT UNSIGNED NULL,
            user_id BIGINT UNSIGNED NULL,
            position VARCHAR(150) NULL,
            allocated_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            used_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            period_start DATE NULL,
            period_end DATE NULL,
            description TEXT NULL,
            status ENUM('active','inactive','completed') NOT NULL DEFAULT 'active',
            created_by BIGINT UNSIGNED NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_labor_budgets_project (project_id),
            KEY idx_labor_budgets_user (user_id),
            KEY idx_labor_budgets_creator (created_by),
            CONSTRAINT fk_labor_budgets_project FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE SET NULL ON UPDATE CASCADE,
            CONSTRAINT fk_labor_budgets_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE,
            CONSTRAINT fk_labor_budgets_creator FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

// Get labor budgets
$labor_budgets = [];
try {
    $query = "
        SELECT lb.*, p.project_code, p.name as project_name, u.full_name as user_name, u.department as user_department, 
               (lb.allocated_amount - lb.used_amount) as remaining_amount,
               ub.full_name as created_by_name
        FROM labor_budgets lb
        LEFT JOIN projects p ON lb.project_id = p.id
        LEFT JOIN users u ON lb.user_id = u.id
        LEFT JOIN users ub ON lb.created_by = ub.id
        ORDER BY lb.created_at DESC
    ";
    $labor_budgets = $pdo->query($query)->fetchAll();
} catch (PDOException $e) {
    $error = userDatabaseError($e);
}

// Get projects for dropdown
$projects = $pdo->query("SELECT id, project_code, name FROM projects WHERE status != 'completed' ORDER BY name")->fetchAll();

// Get users for dropdown
$users = $pdo->query("SELECT id, full_name, department FROM users WHERE status = 'active' ORDER BY full_name")->fetchAll();

// Get single budget for edit/view
$budget_details = null;
if ($action === 'edit' || $action === 'view') {
    $stmt = $pdo->prepare("
        SELECT lb.*, p.project_code, p.name as project_name, u.full_name as user_name, u.department as user_department,
               (lb.allocated_amount - lb.used_amount) as remaining_amount,
               ub.full_name as created_by_name
        FROM labor_budgets lb
        LEFT JOIN projects p ON lb.project_id = p.id
        LEFT JOIN users u ON lb.user_id = u.id
        LEFT JOIN users ub ON lb.created_by = ub.id
        WHERE lb.id = ?
    ");
    $stmt->execute([$id]);
    $budget_details = $stmt->fetch();
    
    if (!$budget_details) {
        header('Location: labor_budget.php');
        exit();
    }
}

if (($action === 'add' || $action === 'edit') && !canWriteDepartmentData('accounting')) {
    $_SESSION['error'] = 'Administrators have view-only access.';
    header('Location: labor_budget.php' . ($id ? '?action=view&id=' . $id : ''));
    exit();
}

include '../../includes/header.php';
?>

<style>
.labor-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 1.5rem;
}
.labor-stats .card {
    border-left: 4px solid #4e73df;
}
.budget-remaining { color: #1cc88a; font-weight: 700; }
.budget-used { color: #f6c23e; font-weight: 700; }
.budget-over { color: #e74a3b; font-weight: 700; }
</style>

<div class="page-header">
    <h1><i class="fas fa-users"></i> <?php echo $page_title; ?></h1>
    <?php if ($action === 'list'): ?>
        <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
            <?php if (canWriteDepartmentData('accounting')): ?><a href="?action=add" class="btn btn-primary"><i class="fas fa-plus"></i> New Labor Budget</a><?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php if (isset($_SESSION['success'])): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
<?php endif; ?>
<?php if (isset($_SESSION['error'])): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
<?php endif; ?>
<?php if (isset($error)): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<!-- Quick Stats -->
<div class="labor-stats">
    <div class="card" style="border-left-color:#4e73df;margin:0;">
        <div style="font-size:0.75rem;color:#64748b;">Total Budgets</div>
        <div style="font-size:1.5rem;font-weight:700;"><?php echo count($labor_budgets); ?></div>
    </div>
    <div class="card" style="border-left-color:#1cc88a;margin:0;">
        <div style="font-size:0.75rem;color:#64748b;">Total Allocated</div>
        <div style="font-size:1.5rem;font-weight:700;">₱<?php echo number_format(array_sum(array_column($labor_budgets, 'allocated_amount')), 2); ?></div>
    </div>
    <div class="card" style="border-left-color:#f6c23e;margin:0;">
        <div style="font-size:0.75rem;color:#64748b;">Total Used</div>
        <div style="font-size:1.5rem;font-weight:700;">₱<?php echo number_format(array_sum(array_column($labor_budgets, 'used_amount')), 2); ?></div>
    </div>
    <div class="card" style="border-left-color:#36b9cc;margin:0;">
        <div style="font-size:0.75rem;color:#64748b;">Total Remaining</div>
        <div style="font-size:1.5rem;font-weight:700;">₱<?php echo number_format(array_sum(array_column($labor_budgets, 'remaining_amount')), 2); ?></div>
    </div>
</div>

<?php if ($action === 'add' || $action === 'edit'): ?>
<!-- Add/Edit Form -->
<div class="card">
    <h3><?php echo $action === 'add' ? 'Create New' : 'Edit'; ?> Labor Budget</h3>
    <form method="POST">
        <?php if ($action === 'edit'): ?>
            <input type="hidden" name="update_budget" value="1">
        <?php else: ?>
            <input type="hidden" name="add_budget" value="1">
        <?php endif; ?>
        
        <div class="form-row">
            <div class="form-group">
                <label class="required">Budget Name</label>
                <input type="text" name="budget_name" required value="<?php echo htmlspecialchars($budget_details['budget_name'] ?? ''); ?>" placeholder="Enter budget name">
            </div>
            <div class="form-group">
                <label>Project (Optional)</label>
                <select name="project_id">
                    <option value="">No Project</option>
                    <?php foreach ($projects as $project): ?>
                        <option value="<?php echo $project['id']; ?>" <?php echo ($budget_details['project_id'] ?? 0) == $project['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($project['project_code'] . ' - ' . $project['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label>Employee (Optional)</label>
                <select name="user_id">
                    <option value="">No Specific Employee</option>
                    <?php foreach ($users as $user): ?>
                        <option value="<?php echo $user['id']; ?>" <?php echo ($budget_details['user_id'] ?? 0) == $user['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($user['full_name'] . ' - ' . ucfirst($user['department'])); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Position</label>
                <input type="text" name="position" value="<?php echo htmlspecialchars($budget_details['position'] ?? ''); ?>" placeholder="Job position">
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label class="required">Allocated Amount (₱)</label>
                <input type="number" step="0.01" name="allocated_amount" required value="<?php echo htmlspecialchars($budget_details['allocated_amount'] ?? 0); ?>" min="0" placeholder="0.00">
            </div>
            <div class="form-group">
                <label>Period Start</label>
                <input type="date" name="period_start" value="<?php echo htmlspecialchars($budget_details['period_start'] ?? ''); ?>">
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label>Period End</label>
                <input type="date" name="period_end" value="<?php echo htmlspecialchars($budget_details['period_end'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label>Status</label>
                <select name="status">
                    <option value="active" <?php echo ($budget_details['status'] ?? 'active') == 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="inactive" <?php echo ($budget_details['status'] ?? 'active') == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                    <option value="completed" <?php echo ($budget_details['status'] ?? 'active') == 'completed' ? 'selected' : ''; ?>>Completed</option>
                </select>
            </div>
        </div>
        
        <div class="form-group">
            <label>Description</label>
            <textarea name="description" rows="3" placeholder="Budget description"><?php echo htmlspecialchars($budget_details['description'] ?? ''); ?></textarea>
        </div>
        
        <div style="margin-top:1.5rem;">
            <button type="submit" name="<?php echo $action === 'add' ? 'add_budget' : 'update_budget'; ?>" class="btn btn-primary"><i class="fas fa-save"></i> <?php echo $action === 'add' ? 'Create' : 'Update'; ?> Budget</button>
            <a href="labor_budget.php" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

<?php elseif ($action === 'view' && $budget_details): ?>
<!-- View Budget -->
<div class="card">
    <h3>Labor Budget Details</h3>
    <div class="view-details">
        <div class="detail-row"><span>Budget Name:</span> <strong><?php echo htmlspecialchars($budget_details['budget_name']); ?></strong></div>
        <div class="detail-row"><span>Project:</span> <?php echo htmlspecialchars($budget_details['project_name'] ?? 'N/A'); ?></div>
        <div class="detail-row"><span>Employee:</span> <?php echo htmlspecialchars($budget_details['user_name'] ?? 'N/A'); ?></div>
        <div class="detail-row"><span>Position:</span> <?php echo htmlspecialchars($budget_details['position'] ?? 'N/A'); ?></div>
        <div class="detail-row"><span>Allocated Amount:</span> <strong>₱<?php echo number_format($budget_details['allocated_amount'], 2); ?></strong></div>
        <div class="detail-row"><span>Used Amount:</span> <strong class="budget-used">₱<?php echo number_format($budget_details['used_amount'], 2); ?></strong></div>
        <div class="detail-row"><span>Remaining:</span> <strong class="<?php echo $budget_details['remaining_amount'] >= 0 ? 'budget-remaining' : 'budget-over'; ?>">₱<?php echo number_format($budget_details['remaining_amount'], 2); ?></strong></div>
        <div class="detail-row"><span>Period Start:</span> <?php echo $budget_details['period_start'] ? date('M d, Y', strtotime($budget_details['period_start'])) : 'N/A'; ?></div>
        <div class="detail-row"><span>Period End:</span> <?php echo $budget_details['period_end'] ? date('M d, Y', strtotime($budget_details['period_end'])) : 'N/A'; ?></div>
        <div class="detail-row"><span>Description:</span> <?php echo nl2br(htmlspecialchars($budget_details['description'] ?? '')); ?></div>
        <div class="detail-row"><span>Status:</span> <span class="badge badge-<?php echo $budget_details['status'] == 'active' ? 'success' : 'secondary'; ?>"><?php echo ucfirst($budget_details['status']); ?></span></div>
        <div class="detail-row"><span>Created By:</span> <?php echo htmlspecialchars($budget_details['created_by_name'] ?? 'N/A'); ?></div>
        <div class="detail-row"><span>Created:</span> <?php echo date('M d, Y h:i A', strtotime($budget_details['created_at'])); ?></div>
    </div>
    <div style="margin-top:1.5rem; display:flex; gap:0.5rem; flex-wrap:wrap;">
        <?php if (canWriteDepartmentData('accounting')): ?><a href="?action=edit&id=<?php echo $budget_details['id']; ?>" class="btn btn-warning"><i class="fas fa-edit"></i> Edit</a><?php endif; ?>
        <?php if (canDelete('accounting')): ?><a href="?action=delete&delete_id=<?php echo $budget_details['id']; ?>" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this labor budget?')"><i class="fas fa-trash"></i> Delete</a><?php endif; ?>
        <a href="labor_budget.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back</a>
    </div>
</div>

<?php else: ?>
<!-- List View -->
<div class="card">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem; flex-wrap:wrap; gap:0.5rem;">
        <h3 style="margin:0;"><i class="fas fa-list"></i> All Labor Budgets (<?php echo count($labor_budgets); ?>)</h3>
        <div style="display:flex; gap:0.5rem;">
            <input type="text" id="searchBudget" placeholder="Search budgets..." style="padding:0.4rem 0.8rem; border:1px solid var(--border-color); border-radius:6px; font-size:0.85rem;">
            <select id="filterStatus" style="padding:0.4rem 0.8rem; border:1px solid var(--border-color); border-radius:6px; font-size:0.85rem;">
                <option value="">All Status</option>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
                <option value="completed">Completed</option>
            </select>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table" id="budgetTable">
            <thead>
                <tr>
                    <th onclick="sortTable('budgetTable', 0)" style="cursor:pointer;">Budget Name <i class="fas fa-sort"></i></th>
                    <th onclick="sortTable('budgetTable', 1)" style="cursor:pointer;">Project <i class="fas fa-sort"></i></th>
                    <th onclick="sortTable('budgetTable', 2)" style="cursor:pointer;">Employee <i class="fas fa-sort"></i></th>
                    <th onclick="sortTable('budgetTable', 3)" style="cursor:pointer;">Position <i class="fas fa-sort"></i></th>
                    <th onclick="sortTable('budgetTable', 4)" style="cursor:pointer;">Allocated <i class="fas fa-sort"></i></th>
                    <th onclick="sortTable('budgetTable', 5)" style="cursor:pointer;">Used <i class="fas fa-sort"></i></th>
                    <th onclick="sortTable('budgetTable', 6)" style="cursor:pointer;">Remaining <i class="fas fa-sort"></i></th>
                    <th onclick="sortTable('budgetTable', 7)" style="cursor:pointer;">Status <i class="fas fa-sort"></i></th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($labor_budgets)): ?>
                    <tr>
                        <td colspan="9" style="text-align:center;color:#94a3b8;padding:2rem;">
                            <i class="fas fa-users" style="font-size:2rem;display:block;margin-bottom:0.5rem;opacity:0.3;"></i>
                            No labor budgets found. Click "New Labor Budget" to create one.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($labor_budgets as $budget): ?>
                    <tr data-status="<?php echo htmlspecialchars($budget['status']); ?>">
                        <td><strong><?php echo htmlspecialchars($budget['budget_name']); ?></strong></td>
                        <td><?php echo htmlspecialchars($budget['project_name'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($budget['user_name'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($budget['position'] ?? 'N/A'); ?></td>
                        <td>₱<?php echo number_format($budget['allocated_amount'], 2); ?></td>
                        <td class="budget-used">₱<?php echo number_format($budget['used_amount'], 2); ?></td>
                        <td class="<?php echo $budget['remaining_amount'] >= 0 ? 'budget-remaining' : 'budget-over'; ?>">₱<?php echo number_format($budget['remaining_amount'], 2); ?></td>
                        <td><span class="badge badge-<?php echo $budget['status'] == 'active' ? 'success' : 'secondary'; ?>"><?php echo ucfirst($budget['status']); ?></span></td>
                        <td>
                            <div style="display:flex; gap:0.3rem; flex-wrap:wrap;">
                                <a href="?action=view&id=<?php echo $budget['id']; ?>" class="btn btn-sm btn-info" title="View"><i class="fas fa-eye"></i></a>
                                <?php if (canWriteDepartmentData('accounting')): ?><a href="?action=edit&id=<?php echo $budget['id']; ?>" class="btn btn-sm btn-warning" title="Edit"><i class="fas fa-edit"></i></a><?php endif; ?>
                                <?php if (canDelete('accounting')): ?><a href="?action=delete&delete_id=<?php echo $budget['id']; ?>" class="btn btn-sm btn-danger" title="Delete" onclick="return confirm('Delete this labor budget?')"><i class="fas fa-trash"></i></a><?php endif; ?>
                            </div>
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
document.getElementById('searchBudget')?.addEventListener('keyup', function() {
    const searchTerm = this.value.toLowerCase();
    const statusFilter = document.getElementById('filterStatus').value;
    const rows = document.querySelectorAll('#budgetTable tbody tr');
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
    const searchTerm = document.getElementById('searchBudget').value.toLowerCase();
    const rows = document.querySelectorAll('#budgetTable tbody tr');
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
