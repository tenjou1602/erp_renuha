<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';

requireDepartment(['warehouse']);

$page_title = 'Materials';
$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);

function generateMaterialCode() {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT MAX(CAST(SUBSTRING(material_code, 5) AS UNSIGNED)) as last_num FROM materials");
        $stmt->execute();
        $result = $stmt->fetch();
        $last_num = $result['last_num'] ?? 0;
        $new_num = str_pad($last_num + 1, 4, '0', STR_PAD_LEFT);
        return "MAT-$new_num";
    } catch (PDOException $e) {
        return "MAT-" . date('Ymd') . rand(100, 999);
    }
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_material']) || isset($_POST['update_material'])) {
        $material_code = $_POST['material_code'] ?? generateMaterialCode();
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $unit = trim($_POST['unit'] ?? 'pcs');
        $cost_per_unit = (float)($_POST['cost_per_unit'] ?? 0);
        $min_stock = (int)($_POST['min_stock'] ?? 0);
        $max_stock = (int)($_POST['max_stock'] ?? 0);
        $current_stock = (int)($_POST['current_stock'] ?? 0);
        $supplier = trim($_POST['supplier'] ?? '');
        $status = $_POST['status'] ?? 'active';
        
        $errors = [];
        if (empty($name)) $errors[] = 'Material name is required.';
        if ($cost_per_unit < 0) $errors[] = 'Cost per unit must be positive.';
        
        if (empty($errors)) {
            try {
                if (isset($_POST['add_material'])) {
                    $stmt = $pdo->prepare("INSERT INTO materials (material_code, name, description, category, unit, cost_per_unit, min_stock, max_stock, current_stock, supplier, status, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$material_code, $name, $description, $category, $unit, $cost_per_unit, $min_stock, $max_stock, $current_stock, $supplier, $status, $_SESSION['user_id']]);
                    
                    logActivity($_SESSION['user_id'], 'Created material', 'Procurement', "Material: $material_code");
                    $_SESSION['success'] = "Material created successfully!";
                } else {
                    $stmt = $pdo->prepare("UPDATE materials SET name = ?, description = ?, category = ?, unit = ?, cost_per_unit = ?, min_stock = ?, max_stock = ?, current_stock = ?, supplier = ?, status = ? WHERE id = ?");
                    $stmt->execute([$name, $description, $category, $unit, $cost_per_unit, $min_stock, $max_stock, $current_stock, $supplier, $status, $id]);
                    
                    logActivity($_SESSION['user_id'], 'Updated material', 'Procurement', "Material: $material_code");
                    $_SESSION['success'] = "Material updated successfully!";
                }
                header('Location: materials.php');
                exit();
            } catch (PDOException $e) {
                $error = 'Database error: ' . $e->getMessage();
            }
        }
    }
}

// Get materials
$materials = [];
try {
    $query = "SELECT * FROM materials ORDER BY created_at DESC";
    $materials = $pdo->query($query)->fetchAll();
} catch (PDOException $e) {
    $error = 'Database error: ' . $e->getMessage();
}

// Get single material
$material_details = null;
if ($action === 'edit' || $action === 'view') {
    $stmt = $pdo->prepare("SELECT * FROM materials WHERE id = ?");
    $stmt->execute([$id]);
    $material_details = $stmt->fetch();
}

include '../../includes/header.php';
?>

<div class="page-header">
    <h1><i class="fas fa-cubes"></i> <?php echo $page_title; ?></h1>
    <?php if ($action === 'list'): ?>
        <a href="?action=add" class="btn btn-primary"><i class="fas fa-plus"></i> New Material</a>
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
    <h3><?php echo $action === 'add' ? 'Create New' : 'Edit'; ?> Material</h3>
    <form method="POST">
        <?php if ($action === 'edit'): ?>
            <input type="hidden" name="update_material" value="1">
            <input type="hidden" name="material_code" value="<?php echo htmlspecialchars($material_details['material_code']); ?>">
        <?php else: ?>
            <input type="hidden" name="add_material" value="1">
        <?php endif; ?>
        
        <div class="form-row">
            <div class="form-group">
                <label>Material Code</label>
                <input type="text" value="<?php echo $action === 'edit' ? htmlspecialchars($material_details['material_code']) : generateMaterialCode(); ?>" disabled style="background:#f1f5f9;">
                <?php if ($action === 'add'): ?>
                    <input type="hidden" name="material_code" value="<?php echo generateMaterialCode(); ?>">
                <?php endif; ?>
            </div>
            <div class="form-group">
                <label class="required">Material Name</label>
                <input type="text" name="name" required value="<?php echo htmlspecialchars($material_details['name'] ?? ''); ?>">
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label>Category</label>
                <input type="text" name="category" value="<?php echo htmlspecialchars($material_details['category'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label>Unit</label>
                <input type="text" name="unit" value="<?php echo htmlspecialchars($material_details['unit'] ?? 'pcs'); ?>">
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label>Cost per Unit (₱)</label>
                <input type="number" step="0.01" name="cost_per_unit" value="<?php echo $material_details['cost_per_unit'] ?? 0; ?>" min="0">
            </div>
            <div class="form-group">
                <label>Current Stock</label>
                <input type="number" name="current_stock" value="<?php echo $material_details['current_stock'] ?? 0; ?>" min="0">
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label>Min Stock</label>
                <input type="number" name="min_stock" value="<?php echo $material_details['min_stock'] ?? 0; ?>" min="0">
            </div>
            <div class="form-group">
                <label>Max Stock</label>
                <input type="number" name="max_stock" value="<?php echo $material_details['max_stock'] ?? 0; ?>" min="0">
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label>Supplier</label>
                <input type="text" name="supplier" value="<?php echo htmlspecialchars($material_details['supplier'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label>Status</label>
                <select name="status">
                    <option value="active" <?php echo ($material_details['status'] ?? '') == 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="inactive" <?php echo ($material_details['status'] ?? '') == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                </select>
            </div>
        </div>
        
        <div class="form-group">
            <label>Description</label>
            <textarea name="description" rows="3"><?php echo htmlspecialchars($material_details['description'] ?? ''); ?></textarea>
        </div>
        
        <div style="margin-top:1.5rem;">
            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> <?php echo $action === 'add' ? 'Create' : 'Update'; ?> Material</button>
            <a href="materials.php" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

<?php elseif ($action === 'view' && $material_details): ?>
<!-- View Material -->
<div class="card">
    <h3>Material Details</h3>
    <div class="view-details">
        <div class="detail-row"><span>Material Code:</span> <strong><?php echo htmlspecialchars($material_details['material_code']); ?></strong></div>
        <div class="detail-row"><span>Name:</span> <?php echo htmlspecialchars($material_details['name']); ?></div>
        <div class="detail-row"><span>Category:</span> <?php echo htmlspecialchars($material_details['category'] ?? 'N/A'); ?></div>
        <div class="detail-row"><span>Unit:</span> <?php echo htmlspecialchars($material_details['unit']); ?></div>
        <div class="detail-row"><span>Cost per Unit:</span> ₱<?php echo number_format($material_details['cost_per_unit'] ?? 0, 2); ?></div>
        <div class="detail-row"><span>Current Stock:</span> <?php echo number_format($material_details['current_stock'] ?? 0); ?></div>
        <div class="detail-row"><span>Min Stock:</span> <?php echo number_format($material_details['min_stock'] ?? 0); ?></div>
        <div class="detail-row"><span>Max Stock:</span> <?php echo number_format($material_details['max_stock'] ?? 0); ?></div>
        <div class="detail-row"><span>Supplier:</span> <?php echo htmlspecialchars($material_details['supplier'] ?? 'N/A'); ?></div>
        <div class="detail-row"><span>Status:</span> <span class="badge badge-<?php echo $material_details['status']; ?>"><?php echo ucfirst($material_details['status']); ?></span></div>
        <div class="detail-row"><span>Description:</span> <?php echo nl2br(htmlspecialchars($material_details['description'] ?? '')); ?></div>
        <div class="detail-row"><span>Created:</span> <?php echo date('M d, Y h:i A', strtotime($material_details['created_at'])); ?></div>
    </div>
    <div style="margin-top:1.5rem;">
        <a href="?action=edit&id=<?php echo $material_details['id']; ?>" class="btn btn-warning"><i class="fas fa-edit"></i> Edit</a>
        <a href="materials.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back</a>
    </div>
</div>

<?php else: ?>
<!-- List View -->
<div class="card">
    <div class="table-responsive">
        <table class="table" id="materialTable">
            <thead>
                <tr>
                    <th onclick="sortTable('materialTable', 0)">Code</th>
                    <th onclick="sortTable('materialTable', 1)">Name</th>
                    <th onclick="sortTable('materialTable', 2)">Category</th>
                    <th onclick="sortTable('materialTable', 3)">Unit</th>
                    <th onclick="sortTable('materialTable', 4)">Cost</th>
                    <th onclick="sortTable('materialTable', 5)">Stock</th>
                    <th onclick="sortTable('materialTable', 6)">Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($materials)): ?>
                    <tr>
                        <td colspan="8" style="text-align:center;color:#94a3b8;padding:2rem;">No materials found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($materials as $material): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($material['material_code']); ?></strong></td>
                        <td><?php echo htmlspecialchars($material['name']); ?></td>
                        <td><?php echo htmlspecialchars($material['category'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($material['unit']); ?></td>
                        <td>₱<?php echo number_format($material['cost_per_unit'] ?? 0, 2); ?></td>
                        <td>
                            <?php echo number_format($material['current_stock'] ?? 0); ?>
                            <?php if (($material['current_stock'] ?? 0) <= ($material['min_stock'] ?? 0) && ($material['min_stock'] ?? 0) > 0): ?>
                                <span class="badge badge-danger" style="margin-left:0.3rem;">Low</span>
                            <?php endif; ?>
                        </td>
                        <td><span class="badge badge-<?php echo $material['status']; ?>"><?php echo ucfirst($material['status']); ?></span></td>
                        <td>
                            <a href="?action=view&id=<?php echo $material['id']; ?>" class="btn btn-sm btn-info"><i class="fas fa-eye"></i></a>
                            <a href="?action=edit&id=<?php echo $material['id']; ?>" class="btn btn-sm btn-warning"><i class="fas fa-edit"></i></a>
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