<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';

requireDepartment(['accounting']);

$page_title = 'Contracts Management';
$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ((isset($_POST['add_contract']) || isset($_POST['update_contract'])) && !canWriteDepartmentData('accounting')) {
        $_SESSION['error'] = 'Administrators have view-only access.';
        header('Location: contracts.php');
        exit();
    }
    
    if (isset($_POST['add_contract']) || isset($_POST['update_contract'])) {
        $contract_number = isset($_POST['contract_number']) ? $_POST['contract_number'] : generateNumber('CTR', 'contracts', 'contract_number');
        $contract_name = trim($_POST['contract_name'] ?? '');
        $project_id = (int)($_POST['project_id'] ?? 0);
        $client = trim($_POST['client'] ?? '');
        $contract_value = (float)($_POST['contract_value'] ?? 0);
        $start_date = $_POST['start_date'] ?? null;
        $end_date = $_POST['end_date'] ?? null;
        $description = trim($_POST['description'] ?? '');
        $status = $_POST['status'] ?? 'draft';
        
        $errors = [];
        if (empty($contract_name)) $errors[] = 'Contract name is required.';
        if (empty($client)) $errors[] = 'Client is required.';
        if ($contract_value <= 0) $errors[] = 'Contract value must be greater than 0.';
        
        if (empty($errors)) {
            try {
                if (isset($_POST['add_contract'])) {
                    $stmt = $pdo->prepare("INSERT INTO contracts (contract_number, contract_name, project_id, client, contract_value, start_date, end_date, description, status, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$contract_number, $contract_name, $project_id, $client, $contract_value, $start_date, $end_date, $description, $status, $_SESSION['user_id']]);
                    
                    logActivity($_SESSION['user_id'], 'Created contract', 'Accounting', "Contract: $contract_number");
                    $_SESSION['success'] = "Contract created successfully!";
                } else {
                    $stmt = $pdo->prepare("UPDATE contracts SET contract_number = ?, contract_name = ?, project_id = ?, client = ?, contract_value = ?, start_date = ?, end_date = ?, description = ?, status = ? WHERE id = ?");
                    $stmt->execute([$contract_number, $contract_name, $project_id, $client, $contract_value, $start_date, $end_date, $description, $status, $id]);
                    
                    logActivity($_SESSION['user_id'], 'Updated contract', 'Accounting', "Contract: $contract_number");
                    $_SESSION['success'] = "Contract updated successfully!";
                }
                header('Location: contracts.php');
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
        $_SESSION['error'] = 'Administrators cannot delete contract records.';
        header('Location: contracts.php');
        exit();
    }
    $delete_id = (int)$_GET['delete_id'];
    try {
        $stmt = $pdo->prepare("DELETE FROM contracts WHERE id = ?");
        $stmt->execute([$delete_id]);
        $_SESSION['success'] = "Contract deleted successfully!";
        header('Location: contracts.php');
        exit();
    } catch (PDOException $e) {
        $error = userDatabaseError($e);
    }
}

// Check if contracts table exists, if not create it
try {
    $pdo->query("SELECT 1 FROM contracts LIMIT 1");
} catch (PDOException $e) {
    // Create contracts table if it doesn't exist
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS contracts (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            contract_number VARCHAR(50) NOT NULL,
            contract_name VARCHAR(200) NOT NULL,
            project_id BIGINT UNSIGNED NULL,
            client VARCHAR(200) NOT NULL,
            contract_value DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            start_date DATE NULL,
            end_date DATE NULL,
            description TEXT NULL,
            status ENUM('draft','active','completed','cancelled','expired') NOT NULL DEFAULT 'draft',
            created_by BIGINT UNSIGNED NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_contract_number (contract_number),
            KEY idx_contracts_project (project_id),
            KEY idx_contracts_creator (created_by),
            CONSTRAINT fk_contracts_project FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE SET NULL ON UPDATE CASCADE,
            CONSTRAINT fk_contracts_creator FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

// Get contracts
$contracts = [];
try {
    $query = "
        SELECT c.*, p.project_code, p.name as project_name, u.full_name as created_by_name
        FROM contracts c
        LEFT JOIN projects p ON c.project_id = p.id
        LEFT JOIN users u ON c.created_by = u.id
        ORDER BY c.created_at DESC
    ";
    $contracts = $pdo->query($query)->fetchAll();
} catch (PDOException $e) {
    $error = userDatabaseError($e);
}

// Get projects for dropdown
$projects = $pdo->query("SELECT id, project_code, name FROM projects WHERE status != 'completed' ORDER BY name")->fetchAll();

// Get single contract for edit/view
$contract_details = null;
if ($action === 'edit' || $action === 'view') {
    $stmt = $pdo->prepare("
        SELECT c.*, p.project_code, p.name as project_name, u.full_name as created_by_name
        FROM contracts c
        LEFT JOIN projects p ON c.project_id = p.id
        LEFT JOIN users u ON c.created_by = u.id
        WHERE c.id = ?
    ");
    $stmt->execute([$id]);
    $contract_details = $stmt->fetch();
    
    if (!$contract_details) {
        header('Location: contracts.php');
        exit();
    }
}

if (($action === 'add' || $action === 'edit') && !canWriteDepartmentData('accounting')) {
    $_SESSION['error'] = 'Administrators have view-only access.';
    header('Location: contracts.php' . ($id ? '?action=view&id=' . $id : ''));
    exit();
}

include '../../includes/header.php';
?>

<style>
.contracts-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 1.5rem;
}
.contracts-stats .card {
    border-left: 4px solid #4e73df;
}
.contract-active { color: #1cc88a; font-weight: 700; }
.contract-expired { color: #e74a3b; font-weight: 700; }
</style>

<div class="page-header">
    <h1><i class="fas fa-file-contract"></i> <?php echo $page_title; ?></h1>
    <?php if ($action === 'list'): ?>
        <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
            <?php if (canWriteDepartmentData('accounting')): ?><a href="?action=add" class="btn btn-primary"><i class="fas fa-plus"></i> New Contract</a><?php endif; ?>
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
<div class="contracts-stats">
    <div class="card" style="border-left-color:#4e73df;margin:0;">
        <div style="font-size:0.75rem;color:#64748b;">Total Contracts</div>
        <div style="font-size:1.5rem;font-weight:700;"><?php echo count($contracts); ?></div>
    </div>
    <div class="card" style="border-left-color:#1cc88a;margin:0;">
        <div style="font-size:0.75rem;color:#64748b;">Active Contracts</div>
        <div style="font-size:1.5rem;font-weight:700;">
            <?php 
            $active = array_filter($contracts, function($c) { return ($c['status'] ?? 'draft') === 'active'; });
            echo count($active);
            ?>
        </div>
    </div>
    <div class="card" style="border-left-color:#f6c23e;margin:0;">
        <div style="font-size:0.75rem;color:#64748b;">Total Contract Value</div>
        <div style="font-size:1.5rem;font-weight:700;">₱<?php echo number_format(array_sum(array_column($contracts, 'contract_value')), 2); ?></div>
    </div>
    <div class="card" style="border-left-color:#e74a3b;margin:0;">
        <div style="font-size:0.75rem;color:#64748b;">Expired/Cancelled</div>
        <div style="font-size:1.5rem;font-weight:700;">
            <?php 
            $expired = array_filter($contracts, function($c) { return in_array($c['status'] ?? '', ['expired', 'cancelled']); });
            echo count($expired);
            ?>
        </div>
    </div>
</div>

<?php if ($action === 'add' || $action === 'edit'): ?>
<!-- Add/Edit Form -->
<div class="card">
    <h3><?php echo $action === 'add' ? 'Create New' : 'Edit'; ?> Contract</h3>
    <form method="POST">
        <?php if ($action === 'edit'): ?>
            <input type="hidden" name="update_contract" value="1">
        <?php else: ?>
            <input type="hidden" name="add_contract" value="1">
        <?php endif; ?>
        
        <div class="form-row">
            <div class="form-group">
                <label>Contract Number</label>
                <input type="text" name="contract_number" value="<?php echo htmlspecialchars($contract_details['contract_number'] ?? ''); ?>" placeholder="Auto-generated if empty">
            </div>
            <div class="form-group">
                <label class="required">Contract Name</label>
                <input type="text" name="contract_name" required value="<?php echo htmlspecialchars($contract_details['contract_name'] ?? ''); ?>" placeholder="Enter contract name">
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label>Project (Optional)</label>
                <select name="project_id">
                    <option value="">No Project</option>
                    <?php foreach ($projects as $project): ?>
                        <option value="<?php echo $project['id']; ?>" <?php echo ($contract_details['project_id'] ?? 0) == $project['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($project['project_code'] . ' - ' . $project['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="required">Client</label>
                <input type="text" name="client" required value="<?php echo htmlspecialchars($contract_details['client'] ?? ''); ?>" placeholder="Client name">
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label class="required">Contract Value (₱)</label>
                <input type="number" step="0.01" name="contract_value" required value="<?php echo htmlspecialchars($contract_details['contract_value'] ?? 0); ?>" min="0" placeholder="0.00">
            </div>
            <div class="form-group">
                <label>Start Date</label>
                <input type="date" name="start_date" value="<?php echo htmlspecialchars($contract_details['start_date'] ?? ''); ?>">
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label>End Date</label>
                <input type="date" name="end_date" value="<?php echo htmlspecialchars($contract_details['end_date'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label>Status</label>
                <select name="status">
                    <option value="draft" <?php echo ($contract_details['status'] ?? 'draft') == 'draft' ? 'selected' : ''; ?>>Draft</option>
                    <option value="active" <?php echo ($contract_details['status'] ?? 'draft') == 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="completed" <?php echo ($contract_details['status'] ?? 'draft') == 'completed' ? 'selected' : ''; ?>>Completed</option>
                    <option value="cancelled" <?php echo ($contract_details['status'] ?? 'draft') == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                    <option value="expired" <?php echo ($contract_details['status'] ?? 'draft') == 'expired' ? 'selected' : ''; ?>>Expired</option>
                </select>
            </div>
        </div>
        
        <div class="form-group">
            <label>Description</label>
            <textarea name="description" rows="3" placeholder="Contract description"><?php echo htmlspecialchars($contract_details['description'] ?? ''); ?></textarea>
        </div>
        
        <div style="margin-top:1.5rem;">
            <button type="submit" name="<?php echo $action === 'add' ? 'add_contract' : 'update_contract'; ?>" class="btn btn-primary"><i class="fas fa-save"></i> <?php echo $action === 'add' ? 'Create' : 'Update'; ?> Contract</button>
            <a href="contracts.php" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

<?php elseif ($action === 'view' && $contract_details): ?>
<!-- View Contract -->
<div class="card">
    <h3>Contract Details</h3>
    <div class="view-details">
        <div class="detail-row"><span>Contract Number:</span> <strong><?php echo htmlspecialchars($contract_details['contract_number']); ?></strong></div>
        <div class="detail-row"><span>Contract Name:</span> <strong><?php echo htmlspecialchars($contract_details['contract_name']); ?></strong></div>
        <div class="detail-row"><span>Project:</span> <?php echo htmlspecialchars($contract_details['project_name'] ?? 'N/A'); ?></div>
        <div class="detail-row"><span>Client:</span> <?php echo htmlspecialchars($contract_details['client']); ?></div>
        <div class="detail-row"><span>Contract Value:</span> <strong>₱<?php echo number_format($contract_details['contract_value'], 2); ?></strong></div>
        <div class="detail-row"><span>Start Date:</span> <?php echo $contract_details['start_date'] ? date('M d, Y', strtotime($contract_details['start_date'])) : 'N/A'; ?></div>
        <div class="detail-row"><span>End Date:</span> <?php echo $contract_details['end_date'] ? date('M d, Y', strtotime($contract_details['end_date'])) : 'N/A'; ?></div>
        <div class="detail-row"><span>Description:</span> <?php echo nl2br(htmlspecialchars($contract_details['description'] ?? '')); ?></div>
        <div class="detail-row"><span>Status:</span> <span class="badge badge-<?php echo $contract_details['status'] == 'active' ? 'success' : ($contract_details['status'] == 'expired' ? 'danger' : 'secondary'); ?>"><?php echo ucfirst($contract_details['status']); ?></span></div>
        <div class="detail-row"><span>Created By:</span> <?php echo htmlspecialchars($contract_details['created_by_name'] ?? 'N/A'); ?></div>
        <div class="detail-row"><span>Created:</span> <?php echo date('M d, Y h:i A', strtotime($contract_details['created_at'])); ?></div>
    </div>
    <div style="margin-top:1.5rem; display:flex; gap:0.5rem; flex-wrap:wrap;">
        <?php if (canWriteDepartmentData('accounting')): ?><a href="?action=edit&id=<?php echo $contract_details['id']; ?>" class="btn btn-warning"><i class="fas fa-edit"></i> Edit</a><?php endif; ?>
        <?php if (canDelete('accounting')): ?><a href="?action=delete&delete_id=<?php echo $contract_details['id']; ?>" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this contract?')"><i class="fas fa-trash"></i> Delete</a><?php endif; ?>
        <a href="contracts.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back</a>
    </div>
</div>

<?php else: ?>
<!-- List View -->
<div class="card">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem; flex-wrap:wrap; gap:0.5rem;">
        <h3 style="margin:0;"><i class="fas fa-list"></i> All Contracts (<?php echo count($contracts); ?>)</h3>
        <div style="display:flex; gap:0.5rem;">
            <input type="text" id="searchContract" placeholder="Search contracts..." style="padding:0.4rem 0.8rem; border:1px solid var(--border-color); border-radius:6px; font-size:0.85rem;">
            <select id="filterStatus" style="padding:0.4rem 0.8rem; border:1px solid var(--border-color); border-radius:6px; font-size:0.85rem;">
                <option value="">All Status</option>
                <option value="draft">Draft</option>
                <option value="active">Active</option>
                <option value="completed">Completed</option>
                <option value="cancelled">Cancelled</option>
                <option value="expired">Expired</option>
            </select>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table" id="contractTable">
            <thead>
                <tr>
                    <th onclick="sortTable('contractTable', 0)" style="cursor:pointer;">Contract # <i class="fas fa-sort"></i></th>
                    <th onclick="sortTable('contractTable', 1)" style="cursor:pointer;">Name <i class="fas fa-sort"></i></th>
                    <th onclick="sortTable('contractTable', 2)" style="cursor:pointer;">Client <i class="fas fa-sort"></i></th>
                    <th onclick="sortTable('contractTable', 3)" style="cursor:pointer;">Value <i class="fas fa-sort"></i></th>
                    <th onclick="sortTable('contractTable', 4)" style="cursor:pointer;">Start Date <i class="fas fa-sort"></i></th>
                    <th onclick="sortTable('contractTable', 5)" style="cursor:pointer;">End Date <i class="fas fa-sort"></i></th>
                    <th onclick="sortTable('contractTable', 6)" style="cursor:pointer;">Status <i class="fas fa-sort"></i></th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($contracts)): ?>
                    <tr>
                        <td colspan="8" style="text-align:center;color:#94a3b8;padding:2rem;">
                            <i class="fas fa-file-contract" style="font-size:2rem;display:block;margin-bottom:0.5rem;opacity:0.3;"></i>
                            No contracts found. Click "New Contract" to create one.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($contracts as $contract): ?>
                    <tr data-status="<?php echo htmlspecialchars($contract['status']); ?>">
                        <td><strong><?php echo htmlspecialchars($contract['contract_number']); ?></strong></td>
                        <td><?php echo htmlspecialchars($contract['contract_name']); ?></td>
                        <td><?php echo htmlspecialchars($contract['client']); ?></td>
                        <td>₱<?php echo number_format($contract['contract_value'], 2); ?></td>
                        <td><?php echo $contract['start_date'] ? date('M d, Y', strtotime($contract['start_date'])) : 'N/A'; ?></td>
                        <td><?php echo $contract['end_date'] ? date('M d, Y', strtotime($contract['end_date'])) : 'N/A'; ?></td>
                        <td><span class="badge badge-<?php echo $contract['status'] == 'active' ? 'success' : ($contract['status'] == 'expired' ? 'danger' : 'secondary'); ?>"><?php echo ucfirst($contract['status']); ?></span></td>
                        <td>
                            <div style="display:flex; gap:0.3rem; flex-wrap:wrap;">
                                <a href="?action=view&id=<?php echo $contract['id']; ?>" class="btn btn-sm btn-info" title="View"><i class="fas fa-eye"></i></a>
                                <?php if (canWriteDepartmentData('accounting')): ?><a href="?action=edit&id=<?php echo $contract['id']; ?>" class="btn btn-sm btn-warning" title="Edit"><i class="fas fa-edit"></i></a><?php endif; ?>
                                <?php if (canDelete('accounting')): ?><a href="?action=delete&delete_id=<?php echo $contract['id']; ?>" class="btn btn-sm btn-danger" title="Delete" onclick="return confirm('Delete this contract?')"><i class="fas fa-trash"></i></a><?php endif; ?>
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
document.getElementById('searchContract')?.addEventListener('keyup', function() {
    const searchTerm = this.value.toLowerCase();
    const statusFilter = document.getElementById('filterStatus').value;
    const rows = document.querySelectorAll('#contractTable tbody tr');
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
    const searchTerm = document.getElementById('searchContract').value.toLowerCase();
    const rows = document.querySelectorAll('#contractTable tbody tr');
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
