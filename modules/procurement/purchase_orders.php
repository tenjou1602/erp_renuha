<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/mailer.php';

requireDepartment(['procurement']);

$page_title = 'Purchase Orders';
$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);

function generatePONumber() {
    return generateNumber('PO', 'purchase_orders', 'po_number');
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ((isset($_POST['add_order']) || isset($_POST['update_order'])) && !canWriteDepartmentData('procurement')) {
        $_SESSION['error'] = 'Access denied. Administrators have view-only access to procurement records.';
        header('Location: purchase_orders.php');
        exit();
    }

    if (isset($_POST['add_order']) || isset($_POST['update_order'])) {
        $po_number = $_POST['po_number'] ?? generatePONumber();
        $purchase_request_id = (int)($_POST['purchase_request_id'] ?? 0);
        $supplier = trim($_POST['supplier'] ?? '');
        $supplier_contact = trim($_POST['supplier_contact'] ?? '');
        $order_date = $_POST['order_date'] ?? date('Y-m-d');
        $delivery_date = $_POST['delivery_date'] ?? '';
        $payment_terms = trim($_POST['payment_terms'] ?? '');
        $status = $_POST['status'] ?? 'draft';
        
        $errors = [];
        if (empty($supplier)) $errors[] = 'Supplier is required.';
        
        if (empty($errors)) {
            try {
                if (isset($_POST['add_order'])) {
                    $stmt = $pdo->prepare("INSERT INTO purchase_orders (po_number, purchase_request_id, supplier, supplier_contact, order_date, delivery_date, payment_terms, status, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$po_number, $purchase_request_id, $supplier, $supplier_contact, $order_date, $delivery_date, $payment_terms, $status, $_SESSION['user_id']]);
                    
                    logActivity($_SESSION['user_id'], 'Created purchase order', 'Procurement', "PO: $po_number");
                    $_SESSION['success'] = "Purchase order created successfully!";
                } else {
                    $stmt = $pdo->prepare("UPDATE purchase_orders SET purchase_request_id = ?, supplier = ?, supplier_contact = ?, order_date = ?, delivery_date = ?, payment_terms = ?, status = ? WHERE id = ?");
                    $stmt->execute([$purchase_request_id, $supplier, $supplier_contact, $order_date, $delivery_date, $payment_terms, $status, $id]);
                    
                    logActivity($_SESSION['user_id'], 'Updated purchase order', 'Procurement', "PO: $po_number");
                    $_SESSION['success'] = "Purchase order updated successfully!";
                }
                if ($status === 'confirmed' || $status === 'ready_for_warehouse') {
                    try {
                        notifyPurchaseOrderApproved($po_number, $supplier, lookupSupplierEmail($supplier));
                    } catch (Throwable $e) {
                        logActivity($_SESSION['user_id'] ?? null, 'Email failed', 'Mail', $e->getMessage());
                    }
                }
                header('Location: purchase_orders.php');
                exit();
            } catch (PDOException $e) {
                $error = userDatabaseError($e);
            }
        }
    }
}

if (isset($_POST['mark_ready_warehouse']) && canMarkReadyForWarehouse()) {
    $po_id = (int)($_POST['po_id'] ?? 0);
    try {
        $stmt = $pdo->prepare("UPDATE purchase_orders SET status = 'ready_for_warehouse' WHERE id = ? AND status IN ('confirmed', 'sent')");
        $stmt->execute([$po_id]);
        logActivity($_SESSION['user_id'], 'Marked PO ready for warehouse', 'Procurement', 'PO id ' . $po_id);
        $_SESSION['success'] = 'Purchase marked ready for Warehouse receiving.';
    } catch (PDOException $e) {
        $_SESSION['error'] = userDatabaseError($e);
    }
    header('Location: purchase_orders.php');
    exit();
}

// Get purchase requests for dropdown
// Only admin-confirmed PRs are eligible for PO conversion
$purchase_requests = $pdo->query("SELECT pr.*, u.full_name as requestor_name FROM purchase_requests pr LEFT JOIN users u ON pr.requestor_id = u.id WHERE pr.status = 'confirmed' ORDER BY pr.created_at DESC")->fetchAll();

// Get purchase orders
$orders = [];
try {
    $query = "
        SELECT po.*, pr.pr_number, u.full_name as created_by_name
        FROM purchase_orders po
        LEFT JOIN purchase_requests pr ON po.purchase_request_id = pr.id
        LEFT JOIN users u ON po.created_by = u.id
        ORDER BY po.created_at DESC
    ";
    $orders = $pdo->query($query)->fetchAll();
} catch (PDOException $e) {
    $error = userDatabaseError($e);
}

// Get single order
$order_details = null;
if ($action === 'edit' || $action === 'view') {
    $stmt = $pdo->prepare("
        SELECT po.*, pr.pr_number, u.full_name as created_by_name
        FROM purchase_orders po
        LEFT JOIN purchase_requests pr ON po.purchase_request_id = pr.id
        LEFT JOIN users u ON po.created_by = u.id
        WHERE po.id = ?
    ");
    $stmt->execute([$id]);
    $order_details = $stmt->fetch();
}

if (($action === 'add' || $action === 'edit') && !canWriteDepartmentData('procurement')) {
    $_SESSION['error'] = 'Administrators have view-only access to purchase orders.';
    header('Location: purchase_orders.php' . ($id ? '?action=view&id=' . $id : ''));
    exit();
}

include '../../includes/header.php';
?>

<div class="page-header">
    <h1><i class="fas fa-shopping-cart"></i> <?php echo $page_title; ?></h1>
    <?php if ($action === 'list' && canWriteDepartmentData('procurement')): ?>
        <a href="?action=add" class="btn btn-primary"><i class="fas fa-plus"></i> New Purchase Order</a>
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
    <h3><?php echo $action === 'add' ? 'Create New' : 'Edit'; ?> Purchase Order</h3>
    <form method="POST">
        <?php if ($action === 'edit'): ?>
            <input type="hidden" name="update_order" value="1">
            <input type="hidden" name="po_number" value="<?php echo htmlspecialchars($order_details['po_number']); ?>">
        <?php else: ?>
            <input type="hidden" name="add_order" value="1">
        <?php endif; ?>
        
        <div class="form-row">
            <div class="form-group">
                <label>PO Number</label>
                <input type="text" value="<?php echo $action === 'edit' ? htmlspecialchars($order_details['po_number']) : generatePONumber(); ?>" disabled style="background:#f1f5f9;">
                <?php if ($action === 'add'): ?>
                    <input type="hidden" name="po_number" value="<?php echo generatePONumber(); ?>">
                <?php endif; ?>
            </div>
            <div class="form-group">
                <label>Purchase Request</label>
                <select name="purchase_request_id">
                    <option value="">No PR</option>
                    <?php foreach ($purchase_requests as $pr): ?>
                        <option value="<?php echo $pr['id']; ?>" <?php echo ($order_details['purchase_request_id'] ?? 0) == $pr['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($pr['pr_number'] . ' - ' . $pr['requestor_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label class="required">Supplier</label>
                <input type="text" name="supplier" required value="<?php echo htmlspecialchars($order_details['supplier'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label>Supplier Contact</label>
                <input type="text" name="supplier_contact" value="<?php echo htmlspecialchars($order_details['supplier_contact'] ?? ''); ?>">
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label>Order Date</label>
                <input type="date" name="order_date" value="<?php echo $order_details['order_date'] ?? date('Y-m-d'); ?>">
            </div>
            <div class="form-group">
                <label>Delivery Date</label>
                <input type="date" name="delivery_date" value="<?php echo $order_details['delivery_date'] ?? ''; ?>">
            </div>
            <div class="form-group">
                <label>Status</label>
                <select name="status">
                    <option value="draft" <?php echo ($order_details['status'] ?? '') == 'draft' ? 'selected' : ''; ?>>Draft</option>
                    <option value="sent" <?php echo ($order_details['status'] ?? '') == 'sent' ? 'selected' : ''; ?>>Sent</option>
                    <option value="confirmed" <?php echo ($order_details['status'] ?? '') == 'confirmed' ? 'selected' : ''; ?>>Confirmed / Purchased</option>
                    <option value="ready_for_warehouse" <?php echo ($order_details['status'] ?? '') == 'ready_for_warehouse' ? 'selected' : ''; ?>>Ready for Warehouse</option>
                    <option value="delivered" <?php echo ($order_details['status'] ?? '') == 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                    <option value="cancelled" <?php echo ($order_details['status'] ?? '') == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                </select>
            </div>
        </div>
        
        <div class="form-group">
            <label>Payment Terms</label>
            <input type="text" name="payment_terms" value="<?php echo htmlspecialchars($order_details['payment_terms'] ?? ''); ?>" placeholder="e.g., Net 30, COD">
        </div>
        
        <div style="margin-top:1.5rem;">
            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> <?php echo $action === 'add' ? 'Create' : 'Update'; ?> Order</button>
            <a href="purchase_orders.php" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

<?php elseif ($action === 'view' && $order_details): ?>
<!-- View Order -->
<div class="card">
    <h3>Purchase Order Details</h3>
    <div class="view-details">
        <div class="detail-row"><span>PO Number:</span> <strong><?php echo htmlspecialchars($order_details['po_number']); ?></strong></div>
        <div class="detail-row"><span>PR Number:</span> <?php echo htmlspecialchars($order_details['pr_number'] ?? 'N/A'); ?></div>
        <div class="detail-row"><span>Supplier:</span> <?php echo htmlspecialchars($order_details['supplier']); ?></div>
        <div class="detail-row"><span>Supplier Contact:</span> <?php echo htmlspecialchars($order_details['supplier_contact'] ?? 'N/A'); ?></div>
        <div class="detail-row"><span>Status:</span> <span class="badge badge-<?php echo $order_details['status']; ?>"><?php echo ucfirst($order_details['status']); ?></span></div>
        <div class="detail-row"><span>Order Date:</span> <?php echo date('M d, Y', strtotime($order_details['order_date'])); ?></div>
        <div class="detail-row"><span>Delivery Date:</span> <?php echo $order_details['delivery_date'] ? date('M d, Y', strtotime($order_details['delivery_date'])) : 'N/A'; ?></div>
        <div class="detail-row"><span>Payment Terms:</span> <?php echo htmlspecialchars($order_details['payment_terms'] ?? 'N/A'); ?></div>
        <div class="detail-row"><span>Created By:</span> <?php echo htmlspecialchars($order_details['created_by_name'] ?? 'N/A'); ?></div>
        <div class="detail-row"><span>Created:</span> <?php echo date('M d, Y h:i A', strtotime($order_details['created_at'])); ?></div>
    </div>
    <div style="margin-top:1.5rem;">
        <?php if (canWriteDepartmentData('procurement')): ?>
        <a href="?action=edit&id=<?php echo $order_details['id']; ?>" class="btn btn-warning"><i class="fas fa-edit"></i> Edit</a>
        <?php endif; ?>
        <a href="purchase_orders.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back</a>
    </div>
</div>

<?php else: ?>
<!-- List View -->
<div class="card">
    <div class="table-responsive">
        <table class="table" id="orderTable">
            <thead>
                <tr>
                    <th data-sort-type="string" onclick="sortTable('orderTable', 0)">PO #</th>
                    <th data-sort-type="string" onclick="sortTable('orderTable', 1)">PR #</th>
                    <th onclick="sortTable('orderTable', 2)">Supplier</th>
                    <th onclick="sortTable('orderTable', 3)">Status</th>
                    <th onclick="sortTable('orderTable', 4)">Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($orders)): ?>
                    <tr>
                        <td colspan="6" style="text-align:center;color:#94a3b8;padding:2rem;">No purchase orders found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($orders as $order): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($order['po_number']); ?></strong></td>
                        <td><?php echo htmlspecialchars($order['pr_number'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($order['supplier']); ?></td>
                        <td><span class="badge badge-<?php echo $order['status']; ?>"><?php echo ucfirst($order['status']); ?></span></td>
                        <td><?php echo date('M d, Y', strtotime($order['created_at'])); ?></td>
                        <td>
                            <a href="?action=view&id=<?php echo $order['id']; ?>" class="btn btn-sm btn-info"><i class="fas fa-eye"></i></a>
                            <?php if (canWriteDepartmentData('procurement')): ?>
                            <a href="?action=edit&id=<?php echo $order['id']; ?>" class="btn btn-sm btn-warning"><i class="fas fa-edit"></i></a>
                            <?php if (in_array($order['status'], ['confirmed', 'sent'], true)): ?>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="po_id" value="<?php echo (int)$order['id']; ?>">
                                <button type="submit" name="mark_ready_warehouse" class="btn btn-sm btn-success" title="Send to Warehouse">Warehouse</button>
                            </form>
                            <?php endif; ?>
                            <?php endif; ?>
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