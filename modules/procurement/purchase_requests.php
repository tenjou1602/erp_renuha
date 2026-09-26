<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/mailer.php';

// Check department access
requireDepartment(['procurement']);

$page_title = 'Purchase Requests';
$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ((isset($_POST['add_request']) || isset($_POST['update_request'])) && !canWriteDepartmentData('procurement')) {
        $_SESSION['error'] = 'Access denied. Administrators have view-only access to procurement records.';
        header('Location: purchase_requests.php');
        exit();
    }

    if (isset($_POST['add_request']) || isset($_POST['update_request'])) {
        $pr_number = isset($_POST['pr_number']) ? $_POST['pr_number'] : generateNumber('PR', 'purchase_requests', 'pr_number');
        $project_id = (int)($_POST['project_id'] ?? 0);
        $purpose = $_POST['purpose'] ?? '';
        $priority = $_POST['priority'] ?? 'medium';
        $items = $_POST['items'] ?? [];
        
        $errors = [];
        if (empty($purpose)) $errors[] = 'Purpose is required.';
        if (empty($items)) $errors[] = 'At least one item is required.';
        
        if (empty($errors)) {
            try {
                $pdo->beginTransaction();
                
                if (isset($_POST['add_request'])) {
                    $stmt = $pdo->prepare("INSERT INTO purchase_requests (pr_number, project_id, requestor_id, purpose, priority, created_by) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$pr_number, $project_id, $_SESSION['user_id'], $purpose, $priority, $_SESSION['user_id']]);
                    $request_id = $pdo->lastInsertId();
                    
                    // Insert items
                    $item_stmt = $pdo->prepare("INSERT INTO purchase_request_items (purchase_request_id, material_id, quantity, unit, estimated_cost, remarks) VALUES (?, ?, ?, ?, ?, ?)");
                    $total_amount = 0;
                    
                    foreach ($items as $item) {
                        if (!empty($item['material_id']) && !empty($item['quantity'])) {
                            $unit = !empty($item['unit']) ? $item['unit'] : 'pcs';
                            $est_cost = (float)($item['estimated_cost'] ?? 0);
                            $total = $item['quantity'] * $est_cost;
                            $item_stmt->execute([$request_id, $item['material_id'], $item['quantity'], $unit, $est_cost, $item['remarks'] ?? '']);
                            $total_amount += $total;
                        }
                    }
                    
                    // Update total amount
                    $pdo->prepare("UPDATE purchase_requests SET total_amount = ? WHERE id = ?")->execute([$total_amount, $request_id]);
                    
                    logActivity($_SESSION['user_id'], 'Created purchase request', 'Procurement', "PR: $pr_number");
                    $_SESSION['success'] = "Purchase request created successfully!";
                    try {
                        notifyPurchaseRequestSubmitted($pr_number, $purpose, $_SESSION['full_name'] ?? 'User');
                    } catch (Throwable $e) {
                        logActivity($_SESSION['user_id'] ?? null, 'Email failed', 'Mail', $e->getMessage());
                    }
                } else {
                    // Update request
                    $stmt = $pdo->prepare("UPDATE purchase_requests SET project_id = ?, purpose = ?, priority = ? WHERE id = ?");
                    $stmt->execute([$project_id, $purpose, $priority, $id]);
                    
                    // Delete existing items and re-insert
                    $pdo->prepare("DELETE FROM purchase_request_items WHERE purchase_request_id = ?")->execute([$id]);
                    
                    $item_stmt = $pdo->prepare("INSERT INTO purchase_request_items (purchase_request_id, material_id, quantity, unit, estimated_cost, remarks) VALUES (?, ?, ?, ?, ?, ?)");
                    $total_amount = 0;
                    
                    foreach ($items as $item) {
                        if (!empty($item['material_id']) && !empty($item['quantity'])) {
                            $unit = !empty($item['unit']) ? $item['unit'] : 'pcs';
                            $est_cost = (float)($item['estimated_cost'] ?? 0);
                            $total = $item['quantity'] * $est_cost;
                            $item_stmt->execute([$id, $item['material_id'], $item['quantity'], $unit, $est_cost, $item['remarks'] ?? '']);
                            $total_amount += $total;
                        }
                    }
                    
                    $pdo->prepare("UPDATE purchase_requests SET total_amount = ? WHERE id = ?")->execute([$total_amount, $id]);
                    
                    logActivity($_SESSION['user_id'], 'Updated purchase request', 'Procurement', "PR: $pr_number");
                    $_SESSION['success'] = "Purchase request updated successfully!";
                }
                
                $pdo->commit();
                header('Location: purchase_requests.php');
                exit();
            } catch (PDOException $e) {
                $pdo->rollBack();
                $error = userDatabaseError($e);
            }
        }
    }
    
    if (isset($_POST['approve_request']) && isManager()) {
        $request_id = (int)$_POST['request_id'];
        $status = $_POST['status'] ?? 'approved';
        $remarks = $_POST['remarks'] ?? '';
        
        try {
            $stmt = $pdo->prepare("UPDATE purchase_requests SET status = ?, approved_by = ?, approved_at = NOW() WHERE id = ? AND status = 'pending'");
            $stmt->execute([$status, $_SESSION['user_id'], $request_id]);
            
            logActivity($_SESSION['user_id'], "Approved purchase request", 'Procurement', "ID: $request_id, Status: $status");
            $_SESSION['success'] = "Purchase request " . ($status === 'approved' ? 'approved' : 'rejected') . " successfully!";
            header('Location: purchase_requests.php');
            exit();
        } catch (PDOException $e) {
            $error = userDatabaseError($e);
        }
    }

    // Admin final confirmation (after manager approval, before PO)
    if (isset($_POST['confirm_request'])) {
        $request_id = (int)($_POST['request_id'] ?? 0);
        try {
            $stmt = $pdo->prepare("SELECT id, pr_number, purpose, status FROM purchase_requests WHERE id = ?");
            $stmt->execute([$request_id]);
            $pr = $stmt->fetch();

            if (!$pr || !canConfirmPurchaseRequest($pr['status'])) {
                $_SESSION['error'] = 'You can only confirm purchase requests that are already approved by a procurement manager.';
                header('Location: purchase_requests.php');
                exit();
            }

            $upd = $pdo->prepare("UPDATE purchase_requests SET status = 'confirmed', confirmed_by = ?, confirmed_at = NOW() WHERE id = ? AND status = 'approved'");
            $upd->execute([$_SESSION['user_id'], $request_id]);

            logActivity($_SESSION['user_id'], 'Confirmed purchase request', 'Procurement', 'PR: ' . $pr['pr_number']);
            try {
                notifyPurchaseRequestConfirmed($pr['pr_number'], $pr['purpose'] ?? '');
            } catch (Throwable $e) {
                logActivity($_SESSION['user_id'] ?? null, 'Email failed', 'Mail', $e->getMessage());
            }
            $_SESSION['success'] = 'Purchase request confirmed. Procurement can now convert it to a Purchase Order.';
            header('Location: purchase_requests.php?action=view&id=' . $request_id);
            exit();
        } catch (PDOException $e) {
            $error = userDatabaseError($e);
        }
    }
}

// Get projects for dropdown
$projects = $pdo->query("SELECT id, name, project_code FROM projects WHERE status != 'completed' ORDER BY name")->fetchAll();

// Get materials for dropdown
$materials = $pdo->query("SELECT id, name, material_code, unit, cost_per_unit FROM materials WHERE status = 'active' ORDER BY name")->fetchAll();

// Get purchase requests
$requests = [];
try {
    $query = "
        SELECT pr.*, u.full_name as requestor_name, u2.full_name as approved_by_name,
               u3.full_name as confirmed_by_name,
               p.name as project_name, p.project_code as project_code,
               (SELECT COUNT(*) FROM purchase_request_items WHERE purchase_request_id = pr.id) as item_count
        FROM purchase_requests pr
        LEFT JOIN users u ON pr.requestor_id = u.id
        LEFT JOIN users u2 ON pr.approved_by = u2.id
        LEFT JOIN users u3 ON pr.confirmed_by = u3.id
        LEFT JOIN projects p ON pr.project_id = p.id
        ORDER BY pr.created_at DESC
    ";
    $requests = $pdo->query($query)->fetchAll();
} catch (PDOException $e) {
    $error = userDatabaseError($e);
}

// Get single request for edit/view
$request_details = null;
$request_items = [];
if ($action === 'edit' || $action === 'view') {
    $stmt = $pdo->prepare("
        SELECT pr.*, u.full_name as requestor_name, u2.full_name as approved_by_name,
               u3.full_name as confirmed_by_name, p.name as project_name
        FROM purchase_requests pr
        LEFT JOIN users u ON pr.requestor_id = u.id
        LEFT JOIN users u2 ON pr.approved_by = u2.id
        LEFT JOIN users u3 ON pr.confirmed_by = u3.id
        LEFT JOIN projects p ON pr.project_id = p.id
        WHERE pr.id = ?
    ");
    $stmt->execute([$id]);
    $request_details = $stmt->fetch();
    
    if ($request_details) {
        $items_stmt = $pdo->prepare("SELECT pri.*, m.name as material_name, m.material_code, m.unit as default_unit
            FROM purchase_request_items pri
            LEFT JOIN materials m ON pri.material_id = m.id
            WHERE pri.purchase_request_id = ?
        ");
        $items_stmt->execute([$id]);
        $request_items = $items_stmt->fetchAll();
    }
}

// Admin may view only — block add/edit form routes
if (($action === 'add' || $action === 'edit') && !canWriteDepartmentData('procurement')) {
    $_SESSION['error'] = 'Administrators have view-only access. You can confirm approved purchase requests.';
    header('Location: purchase_requests.php' . ($id ? '?action=view&id=' . $id : ''));
    exit();
}

include '../../includes/header.php';
?>

<div class="page-header">
    <h1><i class="fas fa-file-invoice"></i> <?php echo $page_title; ?></h1>
    <?php if ($action === 'list' && canWriteDepartmentData('procurement')): ?>
    <a href="?action=add" class="btn btn-primary"><i class="fas fa-plus"></i> New Purchase Request</a>
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

<?php if ($action === 'add' || $action === 'edit'): ?>
<!-- Add/Edit Form -->
<div class="card">
    <h3><?php echo $action === 'add' ? 'Create New' : 'Edit'; ?> Purchase Request</h3>
    <form method="POST" class="form">
        <?php if ($action === 'edit'): ?>
            <input type="hidden" name="update_request" value="1">
            <input type="hidden" name="pr_number" value="<?php echo htmlspecialchars($request_details['pr_number']); ?>">
        <?php else: ?>
            <input type="hidden" name="add_request" value="1">
        <?php endif; ?>
        
        <div class="form-row">
            <div class="form-group">
                <label>Project</label>
                <select name="project_id">
                    <option value="">No Project</option>
                    <?php foreach ($projects as $project): ?>
                        <option value="<?php echo $project['id']; ?>" <?php echo ($request_details['project_id'] ?? 0) == $project['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($project['project_code'] . ' - ' . $project['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Priority *</label>
                <select name="priority" required>
                    <option value="low" <?php echo ($request_details['priority'] ?? '') == 'low' ? 'selected' : ''; ?>>Low</option>
                    <option value="medium" <?php echo ($request_details['priority'] ?? '') == 'medium' ? 'selected' : ''; ?>>Medium</option>
                    <option value="high" <?php echo ($request_details['priority'] ?? '') == 'high' ? 'selected' : ''; ?>>High</option>
                    <option value="urgent" <?php echo ($request_details['priority'] ?? '') == 'urgent' ? 'selected' : ''; ?>>Urgent</option>
                </select>
            </div>
        </div>
        <div class="form-group">
            <label>Purpose *</label>
            <textarea name="purpose" required rows="3"><?php echo htmlspecialchars($request_details['purpose'] ?? ''); ?></textarea>
        </div>
        
        <h4 style="margin: 1.5rem 0 1rem;">Requested Items</h4>
        <div id="items-container">
            <?php if (isset($request_items)): ?>
                <?php foreach ($request_items as $index => $item): ?>
                <div class="item-row">
                    <div class="form-row">
                        <div class="form-group" style="flex:2;">
                            <label>Material</label>
                            <select name="items[<?php echo $index; ?>][material_id]" required>
                                <option value="">Select Material</option>
                                <?php foreach ($materials as $material): ?>
                                    <option value="<?php echo $material['id']; ?>" <?php echo $item['material_id'] == $material['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($material['material_code'] . ' - ' . $material['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Quantity</label>
                            <input type="number" name="items[<?php echo $index; ?>][quantity]" value="<?php echo $item['quantity']; ?>" min="1" required>
                        </div>
                        <div class="form-group">
                            <label>Unit</label>
                            <input type="text" name="items[<?php echo $index; ?>][unit]" value="<?php echo htmlspecialchars($item['unit'] ?? 'pcs'); ?>" placeholder="pcs">
                        </div>
                        <div class="form-group">
                            <label>Est. Cost (₱)</label>
                            <input type="number" step="0.01" name="items[<?php echo $index; ?>][estimated_cost]" value="<?php echo $item['estimated_cost'] ?? 0; ?>" min="0">
                        </div>
                        <div class="form-group">
                            <label>Remarks</label>
                            <input type="text" name="items[<?php echo $index; ?>][remarks]" value="<?php echo htmlspecialchars($item['remarks'] ?? ''); ?>">
                        </div>
                        <div style="display:flex; align-items:flex-end; padding-bottom: 0.5rem;">
                            <button type="button" class="btn btn-danger btn-sm remove-item"><i class="fas fa-times"></i></button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="item-row">
                    <div class="form-row">
                        <div class="form-group" style="flex:2;">
                            <label>Material</label>
                            <select name="items[0][material_id]" required>
                                <option value="">Select Material</option>
                                <?php foreach ($materials as $material): ?>
                                    <option value="<?php echo $material['id']; ?>">
                                        <?php echo htmlspecialchars($material['material_code'] . ' - ' . $material['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Quantity</label>
                            <input type="number" name="items[0][quantity]" value="1" min="1" required>
                        </div>
                        <div class="form-group">
                            <label>Unit</label>
                            <input type="text" name="items[0][unit]" value="pcs" placeholder="pcs">
                        </div>
                        <div class="form-group">
                            <label>Est. Cost (₱)</label>
                            <input type="number" step="0.01" name="items[0][estimated_cost]" value="0" min="0">
                        </div>
                        <div class="form-group">
                            <label>Remarks</label>
                            <input type="text" name="items[0][remarks]">
                        </div>
                        <div style="display:flex; align-items:flex-end; padding-bottom: 0.5rem;">
                            <button type="button" class="btn btn-danger btn-sm remove-item"><i class="fas fa-times"></i></button>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <button type="button" class="btn btn-info" onclick="addItemRow()"><i class="fas fa-plus"></i> Add Item</button>
        
        <div style="margin-top: 1.5rem;">
            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> <?php echo $action === 'add' ? 'Create' : 'Update'; ?> Request</button>
            <a href="purchase_requests.php" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

<script>
let itemCount = <?php echo isset($request_items) ? count($request_items) : 1; ?>;

function addItemRow() {
    const container = document.getElementById('items-container');
    const row = document.createElement('div');
    row.className = 'item-row';
    row.innerHTML = `
        <div class="form-row">
            <div class="form-group" style="flex:2;">
                <label>Material</label>
                <select name="items[${itemCount}][material_id]" required>
                    <option value="">Select Material</option>
                    <?php foreach ($materials as $material): ?>
                        <option value="<?php echo $material['id']; ?>">
                            <?php echo htmlspecialchars($material['material_code'] . ' - ' . $material['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Quantity</label>
                <input type="number" name="items[${itemCount}][quantity]" value="1" min="1" required>
            </div>
            <div class="form-group">
                <label>Unit</label>
                <input type="text" name="items[${itemCount}][unit]" value="pcs" placeholder="pcs">
            </div>
            <div class="form-group">
                <label>Est. Cost (₱)</label>
                <input type="number" step="0.01" name="items[${itemCount}][estimated_cost]" value="0" min="0">
            </div>
            <div class="form-group">
                <label>Remarks</label>
                <input type="text" name="items[${itemCount}][remarks]">
            </div>
            <div style="display:flex; align-items:flex-end; padding-bottom: 0.5rem;">
                <button type="button" class="btn btn-danger btn-sm remove-item"><i class="fas fa-times"></i></button>
            </div>
        </div>
    `;
    container.appendChild(row);
    itemCount++;
    
    // Attach remove event
    row.querySelector('.remove-item').addEventListener('click', function() {
        row.remove();
    });
}

// Attach remove events to existing remove buttons
document.querySelectorAll('.remove-item').forEach(btn => {
    btn.addEventListener('click', function() {
        const row = this.closest('.item-row');
        if (document.querySelectorAll('.item-row').length > 1) {
            row.remove();
        } else {
            alert('You need at least one item.');
        }
    });
});
</script>

<?php elseif ($action === 'view' && $request_details): ?>
<!-- View Request -->
<div class="card">
    <h3>Purchase Request Details</h3>
    <div class="view-details">
        <div class="detail-row"><span>PR Number:</span> <strong><?php echo htmlspecialchars($request_details['pr_number']); ?></strong></div>
        <div class="detail-row"><span>Project:</span> <?php echo htmlspecialchars($request_details['project_name'] ?? 'N/A'); ?></div>
        <div class="detail-row"><span>Requestor:</span> <?php echo htmlspecialchars($request_details['requestor_name']); ?></div>
        <div class="detail-row"><span>Priority:</span> <span class="badge badge-<?php echo $request_details['priority']; ?>"><?php echo ucfirst($request_details['priority']); ?></span></div>
        <div class="detail-row"><span>Status:</span> <span class="badge badge-<?php echo $request_details['status']; ?>"><?php echo ucfirst($request_details['status']); ?></span></div>
        <div class="detail-row"><span>Total Amount:</span> <strong>₱<?php echo number_format($request_details['total_amount'] ?? 0, 2); ?></strong></div>
        <div class="detail-row"><span>Purpose:</span> <?php echo nl2br(htmlspecialchars($request_details['purpose'])); ?></div>
        <div class="detail-row"><span>Created:</span> <?php echo date('M d, Y h:i A', strtotime($request_details['created_at'])); ?></div>
        <?php if ($request_details['approved_at']): ?>
            <div class="detail-row"><span>Approved:</span> <?php echo date('M d, Y h:i A', strtotime($request_details['approved_at'])); ?> by <?php echo htmlspecialchars($request_details['approved_by_name'] ?? 'N/A'); ?></div>
        <?php endif; ?>
        <?php if (!empty($request_details['confirmed_at'])): ?>
            <div class="detail-row"><span>Confirmed:</span> <?php echo date('M d, Y h:i A', strtotime($request_details['confirmed_at'])); ?> by <?php echo htmlspecialchars($request_details['confirmed_by_name'] ?? 'N/A'); ?></div>
        <?php endif; ?>
    </div>
    
    <h4 style="margin-top: 1.5rem;">Requested Items</h4>
    <table class="table">
        <thead>
            <tr>
                <th>Material</th>
                <th>Quantity</th>
                <th>Unit</th>
                <th>Est. Cost</th>
                <th>Total</th>
                <th>Remarks</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($request_items as $item): ?>
            <tr>
                <td><?php echo htmlspecialchars($item['material_name'] ?? 'N/A'); ?></td>
                <td><?php echo number_format($item['quantity']); ?></td>
                <td><?php echo htmlspecialchars($item['unit'] ?? 'pcs'); ?></td>
                <td>₱<?php echo number_format($item['estimated_cost'] ?? 0, 2); ?></td>
                <td>₱<?php echo number_format(($item['quantity'] ?? 0) * ($item['estimated_cost'] ?? 0), 2); ?></td>
                <td><?php echo htmlspecialchars($item['remarks'] ?? ''); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr>
                <th colspan="4" style="text-align: right;">Total:</th>
                <th>₱<?php echo number_format($request_details['total_amount'] ?? 0, 2); ?></th>
                <th></th>
            </tr>
        </tfoot>
    </table>
    
    <div style="margin-top: 1.5rem;">
        <?php if ($request_details['status'] === 'pending' && isManager()): ?>
            <button class="btn btn-success" onclick="openApproveModal(<?php echo $request_details['id']; ?>, 'approved')"><i class="fas fa-check"></i> Approve</button>
            <button class="btn btn-danger" onclick="openApproveModal(<?php echo $request_details['id']; ?>, 'rejected')"><i class="fas fa-times"></i> Reject</button>
        <?php endif; ?>
        <?php if (canConfirmPurchaseRequest($request_details['status'])): ?>
            <form method="POST" style="display:inline;" onsubmit="return confirm('Confirm this purchase request for PO conversion?');">
                <input type="hidden" name="confirm_request" value="1">
                <input type="hidden" name="request_id" value="<?php echo (int)$request_details['id']; ?>">
                <button type="submit" class="btn btn-primary"><i class="fas fa-stamp"></i> Confirm (Admin)</button>
            </form>
        <?php endif; ?>
        <a href="purchase_requests.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back</a>
    </div>
</div>

<!-- Approve Modal -->
<div id="approveModal" class="modal" style="display:none;">
    <div class="modal-content">
        <h3>Confirm Approval</h3>
        <form method="POST">
            <input type="hidden" name="request_id" id="approve_request_id">
            <input type="hidden" name="status" id="approve_status">
            <input type="hidden" name="approve_request" value="1">
            <p>Are you sure you want to <span id="approve_action_text">approve</span> this purchase request?</p>
            <div class="form-group">
                <label>Remarks (Optional)</label>
                <textarea name="remarks" rows="2"></textarea>
            </div>
            <div style="display:flex; gap:1rem; margin-top:1rem;">
                <button type="submit" class="btn btn-primary">Confirm</button>
                <button type="button" class="btn btn-secondary" onclick="closeApproveModal()">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
function openApproveModal(id, status) {
    document.getElementById('approve_request_id').value = id;
    document.getElementById('approve_status').value = status;
    document.getElementById('approve_action_text').textContent = status === 'approved' ? 'approve' : 'reject';
    document.getElementById('approveModal').style.display = 'flex';
}

function closeApproveModal() {
    document.getElementById('approveModal').style.display = 'none';
}
</script>

<?php else: ?>
<!-- List View -->
<div class="card">
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>PR Number</th>
                    <th>Project</th>
                    <th>Requestor</th>
                    <th>Purpose</th>
                    <th>Items</th>
                    <th>Total</th>
                    <th>Priority</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($requests as $request): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($request['pr_number']); ?></strong></td>
                    <td><?php echo htmlspecialchars($request['project_name'] ?? 'N/A'); ?></td>
                    <td><?php echo htmlspecialchars($request['requestor_name'] ?? 'N/A'); ?></td>
                    <td><?php echo htmlspecialchars(substr($request['purpose'], 0, 50)) . (strlen($request['purpose']) > 50 ? '...' : ''); ?></td>
                    <td><?php echo $request['item_count']; ?></td>
                    <td>₱<?php echo number_format($request['total_amount'] ?? 0, 2); ?></td>
                    <td><span class="badge badge-<?php echo $request['priority']; ?>"><?php echo ucfirst($request['priority']); ?></span></td>
                    <td><span class="badge badge-<?php echo $request['status']; ?>"><?php echo ucfirst($request['status']); ?></span></td>
                    <td>
                        <a href="?action=view&id=<?php echo $request['id']; ?>" class="btn btn-sm btn-info"><i class="fas fa-eye"></i></a>
                        <?php if (canWriteDepartmentData('procurement') && $request['status'] === 'draft' && ($_SESSION['user_id'] == $request['requestor_id'] || isManager())): ?>
                            <a href="?action=edit&id=<?php echo $request['id']; ?>" class="btn btn-sm btn-warning"><i class="fas fa-edit"></i></a>
                        <?php endif; ?>
                        <?php if (isManager() && $request['status'] === 'pending'): ?>
                            <button class="btn btn-sm btn-success" onclick="openApproveModal(<?php echo $request['id']; ?>, 'approved')"><i class="fas fa-check"></i></button>
                            <button class="btn btn-sm btn-danger" onclick="openApproveModal(<?php echo $request['id']; ?>, 'rejected')"><i class="fas fa-times"></i></button>
                        <?php endif; ?>
                        <?php if (canConfirmPurchaseRequest($request['status'])): ?>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Confirm this PR for PO conversion?');">
                                <input type="hidden" name="confirm_request" value="1">
                                <input type="hidden" name="request_id" value="<?php echo (int)$request['id']; ?>">
                                <button type="submit" class="btn btn-sm btn-primary" title="Admin Confirm"><i class="fas fa-stamp"></i></button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<style>
/* Additional styles for this page */
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    flex-wrap: wrap;
    gap: 1rem;
}
.page-header h1 {
    font-size: 1.8rem;
    color: #1a1a2e;
    margin: 0;
}
.card {
    background: white;
    border-radius: 16px;
    padding: 1.5rem;
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    margin-bottom: 1.5rem;
}
.form-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    align-items: end;
}
.form-group {
    display: flex;
    flex-direction: column;
    gap: 0.3rem;
}
.form-group label {
    font-weight: 600;
    color: #1a1a2e;
    font-size: 0.85rem;
}
.form-group input, .form-group select, .form-group textarea {
    padding: 0.6rem 0.8rem;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    font-size: 0.9rem;
}
.form-group input:focus, .form-group select:focus, .form-group textarea:focus {
    outline: none;
    border-color: #f59e0b;
    box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.15);
}
.btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.6rem 1.2rem;
    border: none;
    border-radius: 8px;
    font-size: 0.9rem;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
    transition: all 0.2s;
}
.btn-sm { padding: 0.3rem 0.8rem; font-size: 0.8rem; }
.btn-primary { background: #4e73df; color: white; }
.btn-primary:hover { background: #2e59d9; transform: translateY(-2px); }
.btn-success { background: #1cc88a; color: white; }
.btn-success:hover { background: #169b6b; transform: translateY(-2px); }
.btn-warning { background: #f6c23e; color: #1a1a2e; }
.btn-warning:hover { background: #dda20a; transform: translateY(-2px); }
.btn-danger { background: #e74a3b; color: white; }
.btn-danger:hover { background: #be2617; transform: translateY(-2px); }
.btn-info { background: #36b9cc; color: white; }
.btn-info:hover { background: #2c9faf; transform: translateY(-2px); }
.btn-secondary { background: #858796; color: white; }
.btn-secondary:hover { background: #6b6d7a; transform: translateY(-2px); }
.alert { padding: 1rem; border-radius: 8px; margin-bottom: 1rem; }
.alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
.alert-danger { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
.table { width: 100%; border-collapse: collapse; }
.table th, .table td { padding: 0.8rem; text-align: left; border-bottom: 1px solid #e2e8f0; }
.table th { background: #f8fafc; font-weight: 600; }
.table tr:hover { background: #f8fafc; }
.table tfoot { background: #f1f5f9; font-weight: 600; }
.badge {
    display: inline-block;
    padding: 0.2rem 0.6rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
}
.badge-low { background: #dbeafe; color: #1e40af; }
.badge-medium { background: #fef3c7; color: #92400e; }
.badge-high { background: #fee2e2; color: #991b1b; }
.badge-urgent { background: #7f1d1d; color: white; }
.badge-draft { background: #e2e8f0; color: #334155; }
.badge-pending { background: #fef3c7; color: #92400e; }
.badge-approved { background: #d1fae5; color: #065f46; }
.badge-confirmed { background: #dbeafe; color: #1e40af; }
.badge-rejected { background: #fee2e2; color: #991b1b; }
.badge-ordered { background: #dbeafe; color: #1e40af; }
.badge-received { background: #d1fae5; color: #065f46; }
.view-details .detail-row {
    padding: 0.5rem 0;
    border-bottom: 1px solid #f1f5f9;
    display: flex;
    gap: 1rem;
}
.view-details .detail-row span:first-child {
    font-weight: 600;
    color: #64748b;
    min-width: 120px;
}
.item-row {
    border-bottom: 1px solid #f1f5f9;
    padding: 0.5rem 0;
}
.item-row:last-child { border-bottom: none; }
.modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    z-index: 2000;
    display: flex;
    align-items: center;
    justify-content: center;
}
.modal-content {
    background: white;
    border-radius: 16px;
    padding: 2rem;
    max-width: 500px;
    width: 90%;
}
@media (max-width: 768px) {
    .form-row { grid-template-columns: 1fr; }
    .page-header { flex-direction: column; align-items: stretch; }
    .page-header h1 { font-size: 1.3rem; }
}
</style>

<?php include '../../includes/footer.php'; ?>