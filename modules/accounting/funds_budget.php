<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';

requireDepartment(['accounting']);

$page_title = 'Funds & Budget Management';
$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ((isset($_POST['add_fund']) || isset($_POST['update_fund'])) && !canWriteDepartmentData('accounting')) {
        $_SESSION['error'] = 'Administrators have view-only access.';
        header('Location: funds_budget.php');
        exit();
    }
    
    if (isset($_POST['add_fund']) || isset($_POST['update_fund'])) {
        $fund_name = trim($_POST['fund_name'] ?? '');
        $fund_type = $_POST['fund_type'] ?? 'general';
        $amount = (float)($_POST['amount'] ?? 0);
        $project_id = (int)($_POST['project_id'] ?? 0);
        $description = trim($_POST['description'] ?? '');
        $status = $_POST['status'] ?? 'active';
        
        $errors = [];
        if (empty($fund_name)) $errors[] = 'Fund name is required.';
        if ($amount <= 0) $errors[] = 'Amount must be greater than 0.';
        
        if (empty($errors)) {
            try {
                if (isset($_POST['add_fund'])) {
                    $stmt = $pdo->prepare("INSERT INTO funds (fund_name, fund_type, amount, project_id, description, status, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$fund_name, $fund_type, $amount, $project_id, $description, $status, $_SESSION['user_id']]);
                    
                    logActivity($_SESSION['user_id'], 'Created fund', 'Accounting', "Fund: $fund_name");
                    $_SESSION['success'] = "Fund created successfully!";
                } else {
                    $stmt = $pdo->prepare("UPDATE funds SET fund_name = ?, fund_type = ?, amount = ?, project_id = ?, description = ?, status = ? WHERE id = ?");
                    $stmt->execute([$fund_name, $fund_type, $amount, $project_id, $description, $status, $id]);
                    
                    logActivity($_SESSION['user_id'], 'Updated fund', 'Accounting', "Fund: $fund_name");
                    $_SESSION['success'] = "Fund updated successfully!";
                }
                header('Location: funds_budget.php');
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
        $_SESSION['error'] = 'Administrators cannot delete fund records.';
        header('Location: funds_budget.php');
        exit();
    }
    $delete_id = (int)$_GET['delete_id'];
    try {
        $stmt = $pdo->prepare("DELETE FROM funds WHERE id = ?");
        $stmt->execute([$delete_id]);
        $_SESSION['success'] = "Fund deleted successfully!";
        header('Location: funds_budget.php');
        exit();
    } catch (PDOException $e) {
        $error = userDatabaseError($e);
    }
}

// Check if funds table exists, if not create it
try {
    $pdo->query("SELECT 1 FROM funds LIMIT 1");
} catch (PDOException $e) {
    // Create funds table if it doesn't exist
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS funds (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            fund_name VARCHAR(200) NOT NULL,
            fund_type ENUM('general','project','reserve','emergency') NOT NULL DEFAULT 'general',
            amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            allocated_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            project_id BIGINT UNSIGNED NULL,
            description TEXT NULL,
            status ENUM('active','inactive','frozen') NOT NULL DEFAULT 'active',
            created_by BIGINT UNSIGNED NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_funds_project (project_id),
            KEY idx_funds_creator (created_by),
            CONSTRAINT fk_funds_project FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE SET NULL ON UPDATE CASCADE,
            CONSTRAINT fk_funds_creator FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

// Get funds
$funds = [];
try {
    $query = "
        SELECT f.*, p.project_code, p.name as project_name, u.full_name as created_by_name
        FROM funds f
        LEFT JOIN projects p ON f.project_id = p.id
        LEFT JOIN users u ON f.created_by = u.id
        ORDER BY f.created_at DESC
    ";
    $funds = $pdo->query($query)->fetchAll();
} catch (PDOException $e) {
    $error = userDatabaseError($e);
}

// Get projects for dropdown
$projects = $pdo->query("SELECT id, project_code, name FROM projects WHERE status != 'completed' ORDER BY name")->fetchAll();

// Get single fund for edit/view
$fund_details = null;
if ($action === 'edit' || $action === 'view') {
    $stmt = $pdo->prepare("
        SELECT f.*, p.project_code, p.name as project_name, u.full_name as created_by_name
        FROM funds f
        LEFT JOIN projects p ON f.project_id = p.id
        LEFT JOIN users u ON f.created_by = u.id
        WHERE f.id = ?
    ");
    $stmt->execute([$id]);
    $fund_details = $stmt->fetch();
    
    if (!$fund_details) {
        header('Location: funds_budget.php');
        exit();
    }
}

if (($action === 'add' || $action === 'edit') && !canWriteDepartmentData('accounting')) {
    $_SESSION['error'] = 'Administrators have view-only access.';
    header('Location: funds_budget.php' . ($id ? '?action=view&id=' . $id : ''));
    exit();
}

include '../../includes/header.php';
?>

<style>
.funds-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 1.5rem;
}
.funds-stats .card {
    border-left: 4px solid #4e73df;
}
.fund-available { color: #1cc88a; font-weight: 700; }
.fund-allocated { color: #f6c23e; font-weight: 700; }
</style>

<div class="page-header">
    <h1><i class="fas fa-wallet"></i> <?php echo $page_title; ?></h1>
    <?php if ($action === 'list'): ?>
        <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
            <?php if (canWriteDepartmentData('accounting')): ?><a href="?action=add" class="btn btn-primary"><i class="fas fa-plus"></i> New Fund</a><?php endif; ?>
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
<div class="funds-stats">
    <div class="card" style="border-left-color:#4e73df;margin:0;">
        <div style="font-size:0.75rem;color:#64748b;">Total Funds</div>
        <div style="font-size:1.5rem;font-weight:700;">₱<?php echo number_format(array_sum(array_column($funds, 'amount')), 2); ?></div>
    </div>
    <div class="card" style="border-left-color:#f6c23e;margin:0;">
        <div style="font-size:0.75rem;color:#64748b;">Total Allocated</div>
        <div style="font-size:1.5rem;font-weight:700;">₱<?php echo number_format(array_sum(array_column($funds, 'allocated_amount')), 2); ?></div>
    </div>
    <div class="card" style="border-left-color:#1cc88a;margin:0;">
        <div style="font-size:0.75rem;color:#64748b;">Available Funds</div>
        <div style="font-size:1.5rem;font-weight:700;">₱<?php echo number_format(array_sum(array_column($funds, 'amount')) - array_sum(array_column($funds, 'allocated_amount')), 2); ?></div>
    </div>
    <div class="card" style="border-left-color:#36b9cc;margin:0;">
        <div style="font-size:0.75rem;color:#64748b;">Active Funds</div>
        <div style="font-size:1.5rem;font-weight:700;">
            <?php 
            $active = array_filter($funds, function($f) { return ($f['status'] ?? 'active') === 'active'; });
            echo count($active);
            ?>
        </div>
    </div>
</div>

<?php if ($action === 'add' || $action === 'edit'): ?>
<!-- Add/Edit Form -->
<div class="card">
    <h3><?php echo $action === 'add' ? 'Create New' : 'Edit'; ?> Fund</h3>
    <form method="POST">
        <?php if ($action === 'edit'): ?>
            <input type="hidden" name="update_fund" value="1">
        <?php else: ?>
            <input type="hidden" name="add_fund" value="1">
        <?php endif; ?>
        
        <div class="form-row">
            <div class="form-group">
                <label class="required">Fund Name</label>
                <input type="text" name="fund_name" required value="<?php echo htmlspecialchars($fund_details['fund_name'] ?? ''); ?>" placeholder="Enter fund name">
            </div>
            <div class="form-group">
                <label>Fund Type</label>
                <select name="fund_type">
                    <option value="general" <?php echo ($fund_details['fund_type'] ?? 'general') == 'general' ? 'selected' : ''; ?>>General</option>
                    <option value="project" <?php echo ($fund_details['fund_type'] ?? 'general') == 'project' ? 'selected' : ''; ?>>Project</option>
                    <option value="reserve" <?php echo ($fund_details['fund_type'] ?? 'general') == 'reserve' ? 'selected' : ''; ?>>Reserve</option>
                    <option value="emergency" <?php echo ($fund_details['fund_type'] ?? 'general') == 'emergency' ? 'selected' : ''; ?>>Emergency</option>
                </select>
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label class="required">Amount (₱)</label>
                <input type="number" step="0.01" name="amount" required value="<?php echo htmlspecialchars($fund_details['amount'] ?? 0); ?>" min="0" placeholder="0.00">
            </div>
            <div class="form-group">
                <label>Project (Optional)</label>
                <select name="project_id">
                    <option value="">No Project</option>
                    <?php foreach ($projects as $project): ?>
                        <option value="<?php echo $project['id']; ?>" <?php echo ($fund_details['project_id'] ?? 0) == $project['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($project['project_code'] . ' - ' . $project['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        
        <div class="form-group">
            <label>Description</label>
            <textarea name="description" rows="3" placeholder="Fund description"><?php echo htmlspecialchars($fund_details['description'] ?? ''); ?></textarea>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label>Status</label>
                <select name="status">
                    <option value="active" <?php echo ($fund_details['status'] ?? 'active') == 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="inactive" <?php echo ($fund_details['status'] ?? 'active') == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                    <option value="frozen" <?php echo ($fund_details['status'] ?? 'active') == 'frozen' ? 'selected' : ''; ?>>Frozen</option>
                </select>
            </div>
        </div>
        
        <div style="margin-top:1.5rem;">
            <button type="submit" name="<?php echo $action === 'add' ? 'add_fund' : 'update_fund'; ?>" class="btn btn-primary"><i class="fas fa-save"></i> <?php echo $action === 'add' ? 'Create' : 'Update'; ?> Fund</button>
            <a href="funds_budget.php" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

<?php elseif ($action === 'view' && $fund_details): ?>
<!-- View Fund -->
<div class="card">
    <h3>Fund Details</h3>
    <div class="view-details">
        <div class="detail-row"><span>Fund Name:</span> <strong><?php echo htmlspecialchars($fund_details['fund_name']); ?></strong></div>
        <div class="detail-row"><span>Fund Type:</span> <?php echo ucfirst($fund_details['fund_type']); ?></div>
        <div class="detail-row"><span>Total Amount:</span> <strong>₱<?php echo number_format($fund_details['amount'], 2); ?></strong></div>
        <div class="detail-row"><span>Allocated Amount:</span> <strong class="fund-allocated">₱<?php echo number_format($fund_details['allocated_amount'], 2); ?></strong></div>
        <div class="detail-row"><span>Available:</span> <strong class="fund-available">₱<?php echo number_format($fund_details['amount'] - $fund_details['allocated_amount'], 2); ?></strong></div>
        <div class="detail-row"><span>Project:</span> <?php echo htmlspecialchars($fund_details['project_name'] ?? 'N/A'); ?></div>
        <div class="detail-row"><span>Description:</span> <?php echo nl2br(htmlspecialchars($fund_details['description'] ?? '')); ?></div>
        <div class="detail-row"><span>Status:</span> <span class="badge badge-<?php echo $fund_details['status'] == 'active' ? 'success' : ($fund_details['status'] == 'frozen' ? 'warning' : 'secondary'); ?>"><?php echo ucfirst($fund_details['status']); ?></span></div>
        <div class="detail-row"><span>Created By:</span> <?php echo htmlspecialchars($fund_details['created_by_name'] ?? 'N/A'); ?></div>
        <div class="detail-row"><span>Created:</span> <?php echo date('M d, Y h:i A', strtotime($fund_details['created_at'])); ?></div>
    </div>
    <div style="margin-top:1.5rem; display:flex; gap:0.5rem; flex-wrap:wrap;">
        <?php if (canWriteDepartmentData('accounting')): ?><a href="?action=edit&id=<?php echo $fund_details['id']; ?>" class="btn btn-warning"><i class="fas fa-edit"></i> Edit</a><?php endif; ?>
        <?php if (canDelete('accounting')): ?><a href="?action=delete&delete_id=<?php echo $fund_details['id']; ?>" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this fund?')"><i class="fas fa-trash"></i> Delete</a><?php endif; ?>
        <a href="funds_budget.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back</a>
    </div>
</div>

<?php else: ?>
<!-- List View -->
<div class="card">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem; flex-wrap:wrap; gap:0.5rem;">
        <h3 style="margin:0;"><i class="fas fa-list"></i> All Funds (<?php echo count($funds); ?>)</h3>
        <div style="display:flex; gap:0.5rem;">
            <input type="text" id="searchFund" placeholder="Search funds..." style="padding:0.4rem 0.8rem; border:1px solid var(--border-color); border-radius:6px; font-size:0.85rem;">
            <select id="filterType" style="padding:0.4rem 0.8rem; border:1px solid var(--border-color); border-radius:6px; font-size:0.85rem;">
                <option value="">All Types</option>
                <option value="general">General</option>
                <option value="project">Project</option>
                <option value="reserve">Reserve</option>
                <option value="emergency">Emergency</option>
            </select>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table" id="fundTable">
            <thead>
                <tr>
                    <th onclick="sortTable('fundTable', 0)" style="cursor:pointer;">Fund Name <i class="fas fa-sort"></i></th>
                    <th onclick="sortTable('fundTable', 1)" style="cursor:pointer;">Type <i class="fas fa-sort"></i></th>
                    <th onclick="sortTable('fundTable', 2)" style="cursor:pointer;">Total Amount <i class="fas fa-sort"></i></th>
                    <th onclick="sortTable('fundTable', 3)" style="cursor:pointer;">Allocated <i class="fas fa-sort"></i></th>
                    <th onclick="sortTable('fundTable', 4)" style="cursor:pointer;">Available <i class="fas fa-sort"></i></th>
                    <th onclick="sortTable('fundTable', 5)" style="cursor:pointer;">Project <i class="fas fa-sort"></i></th>
                    <th onclick="sortTable('fundTable', 6)" style="cursor:pointer;">Status <i class="fas fa-sort"></i></th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($funds)): ?>
                    <tr>
                        <td colspan="8" style="text-align:center;color:#94a3b8;padding:2rem;">
                            <i class="fas fa-wallet" style="font-size:2rem;display:block;margin-bottom:0.5rem;opacity:0.3;"></i>
                            No funds found. Click "New Fund" to create one.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($funds as $fund): ?>
                    <tr data-type="<?php echo htmlspecialchars($fund['fund_type']); ?>">
                        <td><strong><?php echo htmlspecialchars($fund['fund_name']); ?></strong></td>
                        <td><?php echo ucfirst($fund['fund_type']); ?></td>
                        <td>₱<?php echo number_format($fund['amount'], 2); ?></td>
                        <td class="fund-allocated">₱<?php echo number_format($fund['allocated_amount'], 2); ?></td>
                        <td class="fund-available">₱<?php echo number_format($fund['amount'] - $fund['allocated_amount'], 2); ?></td>
                        <td><?php echo htmlspecialchars($fund['project_name'] ?? 'N/A'); ?></td>
                        <td><span class="badge badge-<?php echo $fund['status'] == 'active' ? 'success' : ($fund['status'] == 'frozen' ? 'warning' : 'secondary'); ?>"><?php echo ucfirst($fund['status']); ?></span></td>
                        <td>
                            <div style="display:flex; gap:0.3rem; flex-wrap:wrap;">
                                <a href="?action=view&id=<?php echo $fund['id']; ?>" class="btn btn-sm btn-info" title="View"><i class="fas fa-eye"></i></a>
                                <?php if (canWriteDepartmentData('accounting')): ?><a href="?action=edit&id=<?php echo $fund['id']; ?>" class="btn btn-sm btn-warning" title="Edit"><i class="fas fa-edit"></i></a><?php endif; ?>
                                <?php if (canDelete('accounting')): ?><a href="?action=delete&delete_id=<?php echo $fund['id']; ?>" class="btn btn-sm btn-danger" title="Delete" onclick="return confirm('Delete this fund?')"><i class="fas fa-trash"></i></a><?php endif; ?>
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
document.getElementById('searchFund')?.addEventListener('keyup', function() {
    const searchTerm = this.value.toLowerCase();
    const typeFilter = document.getElementById('filterType').value;
    const rows = document.querySelectorAll('#fundTable tbody tr');
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        const type = row.getAttribute('data-type') || '';
        const matchesSearch = text.includes(searchTerm);
        const matchesType = typeFilter === '' || type === typeFilter;
        row.style.display = matchesSearch && matchesType ? '' : 'none';
    });
});

// Type filter
document.getElementById('filterType')?.addEventListener('change', function() {
    const typeFilter = this.value;
    const searchTerm = document.getElementById('searchFund').value.toLowerCase();
    const rows = document.querySelectorAll('#fundTable tbody tr');
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        const type = row.getAttribute('data-type') || '';
        const matchesSearch = text.includes(searchTerm);
        const matchesType = typeFilter === '' || type === typeFilter;
        row.style.display = matchesSearch && matchesType ? '' : 'none';
    });
});
</script>
<?php endif; ?>

<?php include '../../includes/footer.php'; ?>
