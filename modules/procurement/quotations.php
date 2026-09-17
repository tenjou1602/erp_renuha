<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';

requireDepartment(['procurement']);

$page_title = 'Quotations';
$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);

function generateQuotationNumber() {
    return generateNumber('QT', 'quotations', 'quotation_number');
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_quotation']) || isset($_POST['update_quotation'])) {
        $quotation_number = $_POST['quotation_number'] ?? generateQuotationNumber();
        $project_id = (int)($_POST['project_id'] ?? 0);
        $supplier = trim($_POST['supplier'] ?? '');
        $supplier_contact = trim($_POST['supplier_contact'] ?? '');
        $valid_until = $_POST['valid_until'] ?? '';
        $status = $_POST['status'] ?? 'requested';
        $notes = trim($_POST['notes'] ?? '');
        
        $errors = [];
        if (empty($supplier)) $errors[] = 'Supplier is required.';
        
        if (empty($errors)) {
            try {
                if (isset($_POST['add_quotation'])) {
                    $stmt = $pdo->prepare("INSERT INTO quotations (quotation_number, project_id, supplier, supplier_contact, valid_until, status, notes, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$quotation_number, $project_id, $supplier, $supplier_contact, $valid_until, $status, $notes, $_SESSION['user_id']]);
                    
                    logActivity($_SESSION['user_id'], 'Created quotation', 'Procurement', "QT: $quotation_number");
                    $_SESSION['success'] = "Quotation created successfully!";
                } else {
                    $stmt = $pdo->prepare("UPDATE quotations SET project_id = ?, supplier = ?, supplier_contact = ?, valid_until = ?, status = ?, notes = ? WHERE id = ?");
                    $stmt->execute([$project_id, $supplier, $supplier_contact, $valid_until, $status, $notes, $id]);
                    
                    logActivity($_SESSION['user_id'], 'Updated quotation', 'Procurement', "QT: $quotation_number");
                    $_SESSION['success'] = "Quotation updated successfully!";
                }
                header('Location: quotations.php');
                exit();
            } catch (PDOException $e) {
                $error = 'Database error: ' . $e->getMessage();
            }
        }
    }
}

// Get projects for dropdown
$projects = $pdo->query("SELECT id, name, project_code FROM projects ORDER BY name")->fetchAll();

// Get quotations
$quotations = [];
try {
    $query = "
        SELECT q.*, p.name as project_name, p.project_code, u.full_name as created_by_name
        FROM quotations q
        LEFT JOIN projects p ON q.project_id = p.id
        LEFT JOIN users u ON q.created_by = u.id
        ORDER BY q.created_at DESC
    ";
    $quotations = $pdo->query($query)->fetchAll();
} catch (PDOException $e) {
    $error = 'Database error: ' . $e->getMessage();
}

// Get single quotation
$quotation_details = null;
if ($action === 'edit' || $action === 'view') {
    $stmt = $pdo->prepare("
        SELECT q.*, p.name as project_name, p.project_code, u.full_name as created_by_name
        FROM quotations q
        LEFT JOIN projects p ON q.project_id = p.id
        LEFT JOIN users u ON q.created_by = u.id
        WHERE q.id = ?
    ");
    $stmt->execute([$id]);
    $quotation_details = $stmt->fetch();
}

include '../../includes/header.php';
?>

<div class="page-header">
    <h1><i class="fas fa-file-signature"></i> <?php echo $page_title; ?></h1>
    <?php if ($action === 'list'): ?>
        <a href="?action=add" class="btn btn-primary"><i class="fas fa-plus"></i> New Quotation</a>
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
    <h3><?php echo $action === 'add' ? 'Create New' : 'Edit'; ?> Quotation</h3>
    <form method="POST">
        <?php if ($action === 'edit'): ?>
            <input type="hidden" name="update_quotation" value="1">
            <input type="hidden" name="quotation_number" value="<?php echo htmlspecialchars($quotation_details['quotation_number']); ?>">
        <?php else: ?>
            <input type="hidden" name="add_quotation" value="1">
        <?php endif; ?>
        
        <div class="form-row">
            <div class="form-group">
                <label>Quotation Number</label>
                <input type="text" value="<?php echo $action === 'edit' ? htmlspecialchars($quotation_details['quotation_number']) : generateQuotationNumber(); ?>" disabled style="background:#f1f5f9;">
                <?php if ($action === 'add'): ?>
                    <input type="hidden" name="quotation_number" value="<?php echo generateQuotationNumber(); ?>">
                <?php endif; ?>
            </div>
            <div class="form-group">
                <label>Project</label>
                <select name="project_id">
                    <option value="">No Project</option>
                    <?php foreach ($projects as $project): ?>
                        <option value="<?php echo $project['id']; ?>" <?php echo ($quotation_details['project_id'] ?? 0) == $project['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($project['project_code'] . ' - ' . $project['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label class="required">Supplier</label>
                <input type="text" name="supplier" required value="<?php echo htmlspecialchars($quotation_details['supplier'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label>Supplier Contact</label>
                <input type="text" name="supplier_contact" value="<?php echo htmlspecialchars($quotation_details['supplier_contact'] ?? ''); ?>">
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label>Valid Until</label>
                <input type="date" name="valid_until" value="<?php echo $quotation_details['valid_until'] ?? ''; ?>">
            </div>
            <div class="form-group">
                <label>Status</label>
                <select name="status">
                    <option value="requested" <?php echo ($quotation_details['status'] ?? '') == 'requested' ? 'selected' : ''; ?>>Requested</option>
                    <option value="received" <?php echo ($quotation_details['status'] ?? '') == 'received' ? 'selected' : ''; ?>>Received</option>
                    <option value="accepted" <?php echo ($quotation_details['status'] ?? '') == 'accepted' ? 'selected' : ''; ?>>Accepted</option>
                    <option value="rejected" <?php echo ($quotation_details['status'] ?? '') == 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                </select>
            </div>
        </div>
        
        <div class="form-group">
            <label>Notes</label>
            <textarea name="notes" rows="3"><?php echo htmlspecialchars($quotation_details['notes'] ?? ''); ?></textarea>
        </div>
        
        <div style="margin-top:1.5rem;">
            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> <?php echo $action === 'add' ? 'Create' : 'Update'; ?> Quotation</button>
            <a href="quotations.php" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

<?php elseif ($action === 'view' && $quotation_details): ?>
<!-- View Quotation -->
<div class="card">
    <h3>Quotation Details</h3>
    <div class="view-details">
        <div class="detail-row"><span>Quotation #:</span> <strong><?php echo htmlspecialchars($quotation_details['quotation_number']); ?></strong></div>
        <div class="detail-row"><span>Project:</span> <?php echo htmlspecialchars($quotation_details['project_name'] ?? 'N/A'); ?></div>
        <div class="detail-row"><span>Supplier:</span> <?php echo htmlspecialchars($quotation_details['supplier']); ?></div>
        <div class="detail-row"><span>Supplier Contact:</span> <?php echo htmlspecialchars($quotation_details['supplier_contact'] ?? 'N/A'); ?></div>
        <div class="detail-row"><span>Status:</span> <span class="badge badge-<?php echo $quotation_details['status']; ?>"><?php echo ucfirst($quotation_details['status']); ?></span></div>
        <div class="detail-row"><span>Valid Until:</span> <?php echo $quotation_details['valid_until'] ? date('M d, Y', strtotime($quotation_details['valid_until'])) : 'N/A'; ?></div>
        <div class="detail-row"><span>Notes:</span> <?php echo nl2br(htmlspecialchars($quotation_details['notes'] ?? '')); ?></div>
        <div class="detail-row"><span>Created By:</span> <?php echo htmlspecialchars($quotation_details['created_by_name'] ?? 'N/A'); ?></div>
        <div class="detail-row"><span>Created:</span> <?php echo date('M d, Y h:i A', strtotime($quotation_details['created_at'])); ?></div>
    </div>
    <div style="margin-top:1.5rem;">
        <a href="?action=edit&id=<?php echo $quotation_details['id']; ?>" class="btn btn-warning"><i class="fas fa-edit"></i> Edit</a>
        <a href="quotations.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back</a>
    </div>
</div>

<?php else: ?>
<!-- List View -->
<div class="card">
    <div class="table-responsive">
        <table class="table" id="quotationTable">
            <thead>
                <tr>
                    <th onclick="sortTable('quotationTable', 0)">QT #</th>
                    <th onclick="sortTable('quotationTable', 1)">Project</th>
                    <th onclick="sortTable('quotationTable', 2)">Supplier</th>
                    <th onclick="sortTable('quotationTable', 3)">Status</th>
                    <th onclick="sortTable('quotationTable', 4)">Valid Until</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($quotations)): ?>
                    <tr>
                        <td colspan="6" style="text-align:center;color:#94a3b8;padding:2rem;">No quotations found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($quotations as $qt): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($qt['quotation_number']); ?></strong></td>
                        <td><?php echo htmlspecialchars($qt['project_name'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($qt['supplier']); ?></td>
                        <td><span class="badge badge-<?php echo $qt['status']; ?>"><?php echo ucfirst($qt['status']); ?></span></td>
                        <td><?php echo $qt['valid_until'] ? date('M d, Y', strtotime($qt['valid_until'])) : 'N/A'; ?></td>
                        <td>
                            <a href="?action=view&id=<?php echo $qt['id']; ?>" class="btn btn-sm btn-info"><i class="fas fa-eye"></i></a>
                            <a href="?action=edit&id=<?php echo $qt['id']; ?>" class="btn btn-sm btn-warning"><i class="fas fa-edit"></i></a>
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