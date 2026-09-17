<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';

requireDepartment(['procurement']);

$page_title = 'Suppliers';
$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);

// Handle Delete
if (isset($_GET['delete_id']) && $action === 'delete') {
    $delete_id = (int)$_GET['delete_id'];
    try {
        // Check if supplier has materials
        $check = $pdo->prepare("SELECT COUNT(*) FROM materials WHERE supplier_id = ?");
        $check->execute([$delete_id]);
        $material_count = $check->fetchColumn();
        
        if ($material_count > 0) {
            $_SESSION['error'] = "Cannot delete supplier. It has $material_count material(s) associated with it.";
        } else {
            $stmt = $pdo->prepare("DELETE FROM suppliers WHERE id = ?");
            $stmt->execute([$delete_id]);
            $_SESSION['success'] = "Supplier deleted successfully!";
        }
        header('Location: suppliers.php');
        exit();
    } catch (PDOException $e) {
        $error = 'Database error: ' . $e->getMessage();
    }
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_supplier']) || isset($_POST['update_supplier'])) {
        $name = trim($_POST['name'] ?? '');
        $contact_person = trim($_POST['contact_person'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $tin_number = trim($_POST['tin_number'] ?? '');
        $status = $_POST['status'] ?? 'active';
        
        $errors = [];
        if (empty($name)) $errors[] = 'Supplier name is required.';
        if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email format.';
        
        if (empty($errors)) {
            try {
                if (isset($_POST['add_supplier'])) {
                    $stmt = $pdo->prepare("INSERT INTO suppliers (name, contact_person, phone, email, address, tin_number, status, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$name, $contact_person, $phone, $email, $address, $tin_number, $status, $_SESSION['user_id']]);
                    
                    logActivity($_SESSION['user_id'], 'Created supplier', 'Procurement', "Supplier: $name");
                    $_SESSION['success'] = "Supplier created successfully!";
                } else {
                    $stmt = $pdo->prepare("UPDATE suppliers SET name = ?, contact_person = ?, phone = ?, email = ?, address = ?, tin_number = ?, status = ? WHERE id = ?");
                    $stmt->execute([$name, $contact_person, $phone, $email, $address, $tin_number, $status, $id]);
                    
                    logActivity($_SESSION['user_id'], 'Updated supplier', 'Procurement', "Supplier: $name");
                    $_SESSION['success'] = "Supplier updated successfully!";
                }
                header('Location: suppliers.php');
                exit();
            } catch (PDOException $e) {
                $error = 'Database error: ' . $e->getMessage();
            }
        }
    }
}

// Get suppliers
$suppliers = [];
try {
    $query = "SELECT * FROM suppliers ORDER BY created_at DESC";
    $suppliers = $pdo->query($query)->fetchAll();
} catch (PDOException $e) {
    $error = 'Database error: ' . $e->getMessage();
}

// Get single supplier
$supplier_details = null;
if ($action === 'edit' || $action === 'view') {
    $stmt = $pdo->prepare("SELECT * FROM suppliers WHERE id = ?");
    $stmt->execute([$id]);
    $supplier_details = $stmt->fetch();
    
    if (!$supplier_details) {
        header('Location: suppliers.php');
        exit();
    }
}

include '../../includes/header.php';
?>

<style>
/* Supplier specific styles */
.supplier-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 1rem;
    margin-bottom: 1.5rem;
}
.supplier-stats .stat-box {
    background: white;
    border-radius: var(--radius);
    padding: 1rem;
    box-shadow: var(--shadow);
    border-left: 4px solid #4e73df;
}
.supplier-stats .stat-box .number {
    font-size: 1.5rem;
    font-weight: 700;
}
.supplier-stats .stat-box .label {
    font-size: 0.75rem;
    color: #64748b;
}
</style>

<div class="page-header">
    <h1><i class="fas fa-truck"></i> <?php echo $page_title; ?></h1>
    <?php if ($action === 'list'): ?>
        <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
            <a href="?action=add" class="btn btn-primary"><i class="fas fa-plus"></i> New Supplier</a>
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

<!-- Statistics -->
<div class="supplier-stats">
    <div class="stat-box">
        <div class="number"><?php echo count($suppliers); ?></div>
        <div class="label">Total Suppliers</div>
    </div>
    <div class="stat-box" style="border-left-color:#1cc88a;">
        <div class="number">
            <?php 
            $active = array_filter($suppliers, function($s) { return ($s['status'] ?? 'active') === 'active'; });
            echo count($active);
            ?>
        </div>
        <div class="label">Active Suppliers</div>
    </div>
    <div class="stat-box" style="border-left-color:#f6c23e;">
        <div class="number">
            <?php 
            $inactive = array_filter($suppliers, function($s) { return ($s['status'] ?? 'active') === 'inactive'; });
            echo count($inactive);
            ?>
        </div>
        <div class="label">Inactive Suppliers</div>
    </div>
</div>

<?php if ($action === 'add' || $action === 'edit'): ?>
<!-- Add/Edit Form -->
<div class="card">
    <h3><?php echo $action === 'add' ? 'Create New' : 'Edit'; ?> Supplier</h3>
    <form method="POST">
        <div class="form-row">
            <div class="form-group">
                <label class="required">Supplier Name</label>
                <input type="text" name="name" required value="<?php echo htmlspecialchars($supplier_details['name'] ?? ''); ?>" placeholder="Enter supplier name">
            </div>
            <div class="form-group">
                <label>Contact Person</label>
                <input type="text" name="contact_person" value="<?php echo htmlspecialchars($supplier_details['contact_person'] ?? ''); ?>" placeholder="Contact person name">
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label>Phone</label>
                <input type="text" name="phone" value="<?php echo htmlspecialchars($supplier_details['phone'] ?? ''); ?>" placeholder="Phone number">
            </div>
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" value="<?php echo htmlspecialchars($supplier_details['email'] ?? ''); ?>" placeholder="Email address">
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label>Address</label>
                <textarea name="address" rows="2" placeholder="Complete address"><?php echo htmlspecialchars($supplier_details['address'] ?? ''); ?></textarea>
            </div>
            <div class="form-group">
                <label>TIN Number</label>
                <input type="text" name="tin_number" value="<?php echo htmlspecialchars($supplier_details['tin_number'] ?? ''); ?>" placeholder="Tax Identification Number">
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label>Status</label>
                <select name="status">
                    <option value="active" <?php echo ($supplier_details['status'] ?? 'active') == 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="inactive" <?php echo ($supplier_details['status'] ?? 'active') == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                </select>
            </div>
        </div>
        
        <div style="margin-top:1.5rem;">
            <button type="submit" name="<?php echo $action === 'add' ? 'add_supplier' : 'update_supplier'; ?>" class="btn btn-primary"><i class="fas fa-save"></i> <?php echo $action === 'add' ? 'Create' : 'Update'; ?> Supplier</button>
            <a href="suppliers.php" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

<?php elseif ($action === 'view' && $supplier_details): ?>
<!-- View Supplier -->
<div class="card">
    <h3>Supplier Details</h3>
    <div class="view-details">
        <div class="detail-row"><span>Name:</span> <strong><?php echo htmlspecialchars($supplier_details['name']); ?></strong></div>
        <div class="detail-row"><span>Contact Person:</span> <?php echo htmlspecialchars($supplier_details['contact_person'] ?? 'N/A'); ?></div>
        <div class="detail-row"><span>Phone:</span> <?php echo htmlspecialchars($supplier_details['phone'] ?? 'N/A'); ?></div>
        <div class="detail-row"><span>Email:</span> <?php echo htmlspecialchars($supplier_details['email'] ?? 'N/A'); ?></div>
        <div class="detail-row"><span>Address:</span> <?php echo nl2br(htmlspecialchars($supplier_details['address'] ?? '')); ?></div>
        <div class="detail-row"><span>TIN Number:</span> <?php echo htmlspecialchars($supplier_details['tin_number'] ?? 'N/A'); ?></div>
        <div class="detail-row"><span>Status:</span> <span class="badge badge-<?php echo ($supplier_details['status'] ?? 'active') == 'active' ? 'success' : 'secondary'; ?>"><?php echo ucfirst($supplier_details['status'] ?? 'Active'); ?></span></div>
        <div class="detail-row"><span>Created:</span> <?php echo date('M d, Y h:i A', strtotime($supplier_details['created_at'])); ?></div>
    </div>
    <div style="margin-top:1.5rem; display:flex; gap:0.5rem; flex-wrap:wrap;">
        <a href="?action=edit&id=<?php echo $supplier_details['id']; ?>" class="btn btn-warning"><i class="fas fa-edit"></i> Edit</a>
        <a href="?action=delete&delete_id=<?php echo $supplier_details['id']; ?>" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this supplier?')"><i class="fas fa-trash"></i> Delete</a>
        <a href="suppliers.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back</a>
    </div>
</div>

<?php else: ?>
<!-- List View -->
<div class="card">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem; flex-wrap:wrap; gap:0.5rem;">
        <h3 style="margin:0;"><i class="fas fa-list"></i> All Suppliers (<?php echo count($suppliers); ?>)</h3>
        <div style="display:flex; gap:0.5rem;">
            <input type="text" id="searchSupplier" placeholder="Search suppliers..." style="padding:0.4rem 0.8rem; border:1px solid var(--border-color); border-radius:6px; font-size:0.85rem;">
        </div>
    </div>
    <div class="table-responsive">
        <table class="table" id="supplierTable">
            <thead>
                <tr>
                    <th onclick="sortTable('supplierTable', 0)" style="cursor:pointer;">Name <i class="fas fa-sort"></i></th>
                    <th onclick="sortTable('supplierTable', 1)" style="cursor:pointer;">Contact Person <i class="fas fa-sort"></i></th>
                    <th onclick="sortTable('supplierTable', 2)" style="cursor:pointer;">Phone <i class="fas fa-sort"></i></th>
                    <th onclick="sortTable('supplierTable', 3)" style="cursor:pointer;">Email <i class="fas fa-sort"></i></th>
                    <th onclick="sortTable('supplierTable', 4)" style="cursor:pointer;">Status <i class="fas fa-sort"></i></th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($suppliers)): ?>
                    <tr>
                        <td colspan="6" style="text-align:center;color:#94a3b8;padding:2rem;">
                            <i class="fas fa-truck" style="font-size:2rem;display:block;margin-bottom:0.5rem;opacity:0.3;"></i>
                            No suppliers found. Click "New Supplier" to create one.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($suppliers as $supplier): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($supplier['name']); ?></strong></td>
                        <td><?php echo htmlspecialchars($supplier['contact_person'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($supplier['phone'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($supplier['email'] ?? 'N/A'); ?></td>
                        <td><span class="badge badge-<?php echo ($supplier['status'] ?? 'active') == 'active' ? 'success' : 'secondary'; ?>"><?php echo ucfirst($supplier['status'] ?? 'Active'); ?></span></td>
                        <td>
                            <div style="display:flex; gap:0.3rem; flex-wrap:wrap;">
                                <a href="?action=view&id=<?php echo $supplier['id']; ?>" class="btn btn-sm btn-info" title="View"><i class="fas fa-eye"></i></a>
                                <a href="?action=edit&id=<?php echo $supplier['id']; ?>" class="btn btn-sm btn-warning" title="Edit"><i class="fas fa-edit"></i></a>
                                <a href="?action=delete&delete_id=<?php echo $supplier['id']; ?>" class="btn btn-sm btn-danger" title="Delete" onclick="return confirm('Delete this supplier?')"><i class="fas fa-trash"></i></a>
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
document.getElementById('searchSupplier')?.addEventListener('keyup', function() {
    const searchTerm = this.value.toLowerCase();
    const rows = document.querySelectorAll('#supplierTable tbody tr');
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(searchTerm) ? '' : 'none';
    });
});
</script>
<?php endif; ?>

<?php include '../../includes/footer.php'; ?>