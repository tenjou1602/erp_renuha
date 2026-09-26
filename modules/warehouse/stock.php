<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/mailer.php';

requireDepartment(['warehouse']);

$page_title = 'Stock Management';
$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ((isset($_POST['receive_stock']) || isset($_POST['issue_stock'])) && !canWriteDepartmentData('warehouse')) {
        $_SESSION['error'] = 'Administrators have view-only access to warehouse stock.';
        header('Location: stock.php');
        exit();
    }

    if (isset($_POST['receive_stock'])) {
        $material_id = (int)($_POST['material_id'] ?? 0);
        $quantity = (int)($_POST['quantity'] ?? 0);
        $location = trim($_POST['location'] ?? '');
        $batch_number = trim($_POST['batch_number'] ?? '');
        $expiration_date = $_POST['expiration_date'] ?? null;
        $notes = trim($_POST['notes'] ?? '');
        
        $errors = [];
        if ($material_id <= 0) $errors[] = 'Please select a material.';
        if ($quantity <= 0) $errors[] = 'Quantity must be greater than 0.';
        
        if (empty($errors)) {
            try {
                $pdo->beginTransaction();
                
                // Update material stock
                $stmt = $pdo->prepare("UPDATE materials SET current_stock = current_stock + ? WHERE id = ?");
                $stmt->execute([$quantity, $material_id]);
                
                // Add to warehouse stock
                $stmt = $pdo->prepare("INSERT INTO warehouse_stock (material_id, quantity, location, batch_number, expiration_date, notes, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$material_id, $quantity, $location, $batch_number, $expiration_date, $notes, $_SESSION['user_id']]);
                
                // Log movement
                $stmt = $pdo->prepare("INSERT INTO stock_movements (material_id, movement_type, quantity, reason, created_by) VALUES (?, 'in', ?, ?, ?)");
                $stmt->execute([$material_id, $quantity, 'Stock received: ' . ($notes ?: 'New delivery'), $_SESSION['user_id']]);
                
                $pdo->commit();
                
                logActivity($_SESSION['user_id'], 'Received stock', 'Warehouse', "Material ID: $material_id, Qty: $quantity");
                $_SESSION['success'] = "Stock received successfully!";
                header('Location: stock.php');
                exit();
            } catch (PDOException $e) {
                $pdo->rollBack();
                $error = userDatabaseError($e);
            }
        }
    }
    
    if (isset($_POST['issue_stock'])) {
        $material_id = (int)($_POST['material_id'] ?? 0);
        $quantity = (int)($_POST['quantity'] ?? 0);
        $project_id = (int)($_POST['project_id'] ?? 0);
        $reason = trim($_POST['reason'] ?? '');
        
        $errors = [];
        if ($material_id <= 0) $errors[] = 'Please select a material.';
        if ($quantity <= 0) $errors[] = 'Quantity must be greater than 0.';
        
        // Check stock availability and capture pre-issue levels for threshold crossing
        $material = null;
        if (empty($errors)) {
            $stmt = $pdo->prepare("SELECT id, name, unit, current_stock, min_stock FROM materials WHERE id = ?");
            $stmt->execute([$material_id]);
            $material = $stmt->fetch();
            if (!$material) {
                $errors[] = 'Material not found.';
            } elseif ($material['current_stock'] < $quantity) {
                $errors[] = 'Insufficient stock. Available: ' . $material['current_stock'];
            }
        }
        
        if (empty($errors) && $material) {
            try {
                $pdo->beginTransaction();

                $previous_stock = (float) $material['current_stock'];
                $min_stock = (float) $material['min_stock'];
                
                // Update material stock
                $stmt = $pdo->prepare("UPDATE materials SET current_stock = current_stock - ? WHERE id = ?");
                $stmt->execute([$quantity, $material_id]);
                
                // Log movement
                $reason_text = 'Issued to ' . ($project_id > 0 ? 'Project: ' . $project_id : 'General use');
                if ($reason) $reason_text .= ' - ' . $reason;
                
                $stmt = $pdo->prepare("INSERT INTO stock_movements (material_id, movement_type, quantity, reason, created_by) VALUES (?, 'out', ?, ?, ?)");
                $stmt->execute([$material_id, $quantity, $reason_text, $_SESSION['user_id']]);
                
                $pdo->commit();

                $new_stock = $previous_stock - $quantity;
                // Fire alert only when crossing from above-min to at-or-below-min
                if ($min_stock > 0 && $previous_stock > $min_stock && $new_stock <= $min_stock) {
                    logActivity(
                        $_SESSION['user_id'],
                        'Low stock alert',
                        'Warehouse',
                        $material['name'] . ' at ' . $new_stock . ' (min ' . $min_stock . ')'
                    );
                    try {
                        notifyLowStock($material['name'], $new_stock, $min_stock, $material['unit'] ?? 'pcs');
                    } catch (Throwable $e) {
                        logActivity($_SESSION['user_id'], 'Email failed', 'Mail', $e->getMessage());
                    }
                }
                
                logActivity($_SESSION['user_id'], 'Issued stock', 'Warehouse', "Material ID: $material_id, Qty: $quantity");
                $_SESSION['success'] = "Stock issued successfully!";
                header('Location: stock.php');
                exit();
            } catch (PDOException $e) {
                $pdo->rollBack();
                $error = userDatabaseError($e);
            }
        }
    }
}

// Get materials for dropdown
$materials = $pdo->query("SELECT id, name, material_code, current_stock, unit FROM materials WHERE status = 'active' ORDER BY name")->fetchAll();

// Get projects for dropdown
$projects = $pdo->query("SELECT id, name, project_code FROM projects WHERE status != 'completed' ORDER BY name")->fetchAll();

// Get warehouse stock
$warehouse_stock = [];
try {
    $query = "
        SELECT ws.*, m.name as material_name, m.material_code, m.unit, m.current_stock as total_stock
        FROM warehouse_stock ws
        LEFT JOIN materials m ON ws.material_id = m.id
        ORDER BY ws.created_at DESC
    ";
    $warehouse_stock = $pdo->query($query)->fetchAll();
} catch (PDOException $e) {
    $error = userDatabaseError($e);
}

include '../../includes/header.php';
?>

<div class="page-header">
    <h1><i class="fas fa-warehouse"></i> <?php echo $page_title; ?></h1>
</div>

<?php if (isset($_SESSION['success'])): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
<?php endif; ?>
<?php if (isset($error)): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>
<?php if (isset($_SESSION['error'])): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
<?php endif; ?>

<?php if (canWriteDepartmentData('warehouse')): ?>
<!-- Receive Stock -->
<div class="card">
    <h3><i class="fas fa-arrow-down"></i> Receive Stock</h3>
    <form method="POST">
        <input type="hidden" name="receive_stock" value="1">
        <div class="form-row">
            <div class="form-group">
                <label class="required">Material</label>
                <select name="material_id" required>
                    <option value="">Select Material</option>
                    <?php foreach ($materials as $material): ?>
                        <option value="<?php echo $material['id']; ?>">
                            <?php echo htmlspecialchars($material['material_code'] . ' - ' . $material['name'] . ' (Stock: ' . $material['current_stock'] . ' ' . $material['unit'] . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="required">Quantity</label>
                <input type="number" name="quantity" required min="1">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Location</label>
                <input type="text" name="location" placeholder="e.g., Aisle 2, Shelf 3">
            </div>
            <div class="form-group">
                <label>Batch Number</label>
                <input type="text" name="batch_number" placeholder="Batch/Lot number">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Expiration Date</label>
                <input type="date" name="expiration_date">
            </div>
            <div class="form-group">
                <label>Notes</label>
                <input type="text" name="notes" placeholder="Delivery notes">
            </div>
        </div>
        <button type="submit" class="btn btn-success"><i class="fas fa-check"></i> Receive Stock</button>
    </form>
</div>

<!-- Issue Stock -->
<div class="card">
    <h3><i class="fas fa-arrow-up"></i> Issue Stock</h3>
    <form method="POST">
        <input type="hidden" name="issue_stock" value="1">
        <div class="form-row">
            <div class="form-group">
                <label class="required">Material</label>
                <select name="material_id" required>
                    <option value="">Select Material</option>
                    <?php foreach ($materials as $material): ?>
                        <option value="<?php echo $material['id']; ?>">
                            <?php echo htmlspecialchars($material['material_code'] . ' - ' . $material['name'] . ' (Stock: ' . $material['current_stock'] . ' ' . $material['unit'] . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="required">Quantity</label>
                <input type="number" name="quantity" required min="1">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Project</label>
                <select name="project_id">
                    <option value="">No Project</option>
                    <?php foreach ($projects as $project): ?>
                        <option value="<?php echo $project['id']; ?>">
                            <?php echo htmlspecialchars($project['project_code'] . ' - ' . $project['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Reason</label>
                <input type="text" name="reason" placeholder="Reason for issuance">
            </div>
        </div>
        <button type="submit" class="btn btn-warning"><i class="fas fa-arrow-up"></i> Issue Stock</button>
    </form>
</div>
<?php endif; ?>

<!-- Warehouse Stock List -->
<div class="card">
    <h3><i class="fas fa-list"></i> Warehouse Stock</h3>
    <?php if (empty($warehouse_stock)): ?>
        <div class="empty-state">
            <i class="fas fa-warehouse"></i>
            <p>No stock in warehouse.</p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table" id="stockTable">
                <thead>
                    <tr>
                        <th>Material</th>
                        <th>Quantity</th>
                        <th>Location</th>
                        <th>Batch</th>
                        <th>Expiration</th>
                        <th>Received</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($warehouse_stock as $item): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($item['material_name'] ?? 'N/A'); ?></strong><br>
                            <small><?php echo htmlspecialchars($item['material_code'] ?? ''); ?></small>
                        </td>
                        <td><?php echo number_format($item['quantity']) . ' ' . htmlspecialchars($item['unit'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($item['location'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($item['batch_number'] ?? 'N/A'); ?></td>
                        <td><?php echo $item['expiration_date'] ? date('M d, Y', strtotime($item['expiration_date'])) : 'N/A'; ?></td>
                        <td><?php echo date('M d, Y', strtotime($item['created_at'])); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php include '../../includes/footer.php'; ?>