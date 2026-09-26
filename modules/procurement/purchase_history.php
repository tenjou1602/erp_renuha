<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';

requireDepartment(['procurement']);

$page_title = 'Purchase History';
$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);

// Handle Delete
if (isset($_GET['delete_id']) && $action === 'delete') {
    if (!canDelete('procurement')) {
        $_SESSION['error'] = 'Administrators cannot delete purchase order records.';
        header('Location: purchase_history.php');
        exit();
    }
    $delete_id = (int)$_GET['delete_id'];
    try {
        $stmt = $pdo->prepare("DELETE FROM purchase_orders WHERE id = ?");
        $stmt->execute([$delete_id]);
        $_SESSION['success'] = "Purchase order deleted successfully!";
        header('Location: purchase_history.php');
        exit();
    } catch (PDOException $e) {
        $error = userDatabaseError($e);
    }
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ((isset($_POST['add_po']) || isset($_POST['update_po'])) && !canWriteDepartmentData('procurement')) {
        $_SESSION['error'] = 'Administrators have view-only access.';
        header('Location: purchase_history.php');
        exit();
    }
    
    if (isset($_POST['add_po']) || isset($_POST['update_po'])) {
        $po_number = isset($_POST['po_number']) ? $_POST['po_number'] : generateNumber('PO', 'purchase_orders', 'po_number');
        $purchase_request_id = (int)($_POST['purchase_request_id'] ?? 0);
        $supplier = trim($_POST['supplier'] ?? '');
        $supplier_contact = trim($_POST['supplier_contact'] ?? '');
        $order_date = $_POST['order_date'] ?? null;
        $delivery_date = $_POST['delivery_date'] ?? null;
        $payment_terms = trim($_POST['payment_terms'] ?? '');
        $status = $_POST['status'] ?? 'draft';
        
        $errors = [];
        if (empty($supplier)) $errors[] = 'Supplier is required.';
        
        if (empty($errors)) {
            try {
                if (isset($_POST['add_po'])) {
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
                header('Location: purchase_history.php');
                exit();
            } catch (PDOException $e) {
                $error = userDatabaseError($e);
            }
        }
    }
}

// Get purchase requests for dropdown
$purchase_requests = $pdo->query("SELECT id, pr_number, purpose FROM purchase_requests WHERE status IN ('confirmed', 'ordered') ORDER BY created_at DESC")->fetchAll();

// Get purchase orders
$orders = [];
try {
    $query = "
        SELECT po.*, pr.pr_number, pr.purpose as pr_purpose, pr.total_amount as pr_amount,
               u.full_name as created_by_name
        FROM purchase_orders po
        LEFT JOIN purchase_requests pr ON po.purchase_request_id = pr.id
        LEFT JOIN users u ON po.created_by = u.id
        ORDER BY po.created_at DESC
    ";
    $orders = $pdo->query($query)->fetchAll();
} catch (PDOException $e) {
    $error = userDatabaseError($e);
}

// Get single order for edit/view
$order_details = null;
if ($action === 'edit' || $action === 'view') {
    $stmt = $pdo->prepare("
        SELECT po.*, pr.pr_number, pr.purpose as pr_purpose, pr.total_amount as pr_amount,
               u.full_name as created_by_name
        FROM purchase_orders po
        LEFT JOIN purchase_requests pr ON po.purchase_request_id = pr.id
        LEFT JOIN users u ON po.created_by = u.id
        WHERE po.id = ?
    ");
    $stmt->execute([$id]);
    $order_details = $stmt->fetch();
    
    if (!$order_details) {
        header('Location: purchase_history.php');
        exit();
    }
}

if (($action === 'add' || $action === 'edit') && !canWriteDepartmentData('procurement')) {
    $_SESSION['error'] = 'Administrators have view-only access.';
    header('Location: purchase_history.php' . ($id ? '?action=view&id=' . $id : ''));
    exit();
}

include '../../includes/header.php';
?>

<style>
/* Purchase History specific styles */
.procurement-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 1.5rem;
}
.procurement-stats .card {
    border-left: 4px solid #4e73df;
}
</style>

<div class="page-header">
    <h1><i class="fas fa-history"></i> <?php echo $page_title; ?></h1>
    <?php if ($action === 'list'): ?>
        <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
            <?php if (canWriteDepartmentData('procurement')): ?><a href="?action=add" class="btn btn-primary"><i class="fas fa-plus"></i> New Purchase Order</a><?php endif; ?>
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
<div class="procurement-stats">
    <div class="card" style="border-left-color:#4e73df;margin:0;">
        <div style="font-size:0.75rem;color:#64748b;">Total Orders</div>
        <div style="font-size:1.5rem;font-weight:700;"><?php echo count($orders); ?></div>
    </div>
    <div class="card" style="border-left-color:#f6c23e;margin:0;">
        <div style="font-size:0.75rem;color:#64748b;">Pending</div>
        <div style="font-size:1.5rem;font-weight:700;">
            <?php 
            $pending = array_filter($orders, function($o) { return in_array($o['status'] ?? '', ['draft', 'sent']); });
            echo count($pending);
            ?>
        </div>
    </div>
    <div class="card" style="border-left-color:#1cc88a;margin:0;">
        <div style="font-size:0.75rem;color:#64748b;">Delivered</div>
        <div style="font-size:1.5rem;font-weight:700;">
            <?php 
            $delivered = array_filter($orders, function($o) { return ($o['status'] ?? '') === 'delivered'; });
            echo count($delivered);
            ?>
        </div>
    </div>
    <div class="card" style="border-left-color:#e74a3b;margin:0;">
        <div style="font-size:0.75rem;color:#64748b;">Cancelled</div>
        <div style="font-size:1.5rem;font-weight:700;">
            <?php 
            $cancelled = array_filter($orders, function($o) { return ($o['status'] ?? '') === 'cancelled'; });
            echo count($cancelled);
            ?>
        </div>
    </div>
</div>

<?php if ($action === 'add' || $action === 'edit'): ?>
<!-- Add/Edit Form -->
<div class="card">
    <h3><?php echo $action === 'add' ? 'Create New' : 'Edit'; ?> Purchase Order</h3>
    <form method="POST">
        <?php if ($action === 'edit'): ?>
            <input type="hidden" name="update_po" value="1">
            <input type="hidden" name="po_number" value="<?php echo htmlspecialchars($order_details['po_number']); ?>">
        <?php else: ?>
            <input type="hidden" name="add_po" value="1">
        <?php endif; ?>
        
        <div class="form-row">
            <div class="form-group">
                <label>Purchase Request (Optional)</label>
                <select name="purchase_request_id">
                    <option value="">No Purchase Request</option>
                    <?php foreach ($purchase_requests as $pr): ?>
                        <option value="<?php echo $pr['id']; ?>" <?php echo ($order_details['purchase_request_id'] ?? 0) == $pr['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($pr['pr_number'] . ' - ' . substr($pr['purpose'], 0, 40)); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="required">Supplier</label>
                <input type="text" name="supplier" required value="<?php echo htmlspecialchars($order_details['supplier'] ?? ''); ?>" placeholder="Supplier name">
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label>Supplier Contact</label>
                <input type="text" name="supplier_contact" value="<?php echo htmlspecialchars($order_details['supplier_contact'] ?? ''); ?>" placeholder="Contact person">
            </div>
            <div class="form-group">
                <label>Order Date</label>
                <input type="date" name="order_date" value="<?php echo htmlspecialchars($order_details['order_date'] ?? ''); ?>">
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label>Delivery Date</label>
                <input type="date" name="delivery_date" value="<?php echo htmlspecialchars($order_details['delivery_date'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label>Payment Terms</label>
                <input type="text" name="payment_terms" value="<?php echo htmlspecialchars($order_details['payment_terms'] ?? ''); ?>" placeholder="e.g., Net 30">
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label>Status</label>
                <select name="status">
                    <option value="draft" <?php echo ($order_details['status'] ?? 'draft') == 'draft' ? 'selected' : ''; ?>>Draft</option>
                    <option value="sent" <?php echo ($order_details['status'] ?? 'draft') == 'sent' ? 'selected' : ''; ?>>Sent</option>
                    <option value="confirmed" <?php echo ($order_details['status'] ?? 'draft') == 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                    <option value="delivered" <?php echo ($order_details['status'] ?? 'draft') == 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                    <option value="cancelled" <?php echo ($order_details['status'] ?? 'draft') == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                </select>
            </div>
        </div>
        
        <div style="margin-top:1.5rem;">
            <button type="submit" name="<?php echo $action === 'add' ? 'add_po' : 'update_po'; ?>" class="btn btn-primary"><i class="fas fa-save"></i> <?php echo $action === 'add' ? 'Create' : 'Update'; ?> Order</button>
            <a href="purchase_history.php" class="btn btn-secondary">Cancel</a>
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
        <div class="detail-row"><span>Order Date:</span> <?php echo $order_details['order_date'] ? date('M d, Y', strtotime($order_details['order_date'])) : 'N/A'; ?></div>
        <div class="detail-row"><span>Delivery Date:</span> <?php echo $order_details['delivery_date'] ? date('M d, Y', strtotime($order_details['delivery_date'])) : 'N/A'; ?></div>
        <div class="detail-row"><span>Payment Terms:</span> <?php echo htmlspecialchars($order_details['payment_terms'] ?? 'N/A'); ?></div>
        <div class="detail-row"><span>Status:</span> <span class="badge badge-<?php echo $order_details['status']; ?>"><?php echo ucfirst($order_details['status']); ?></span></div>
        <div class="detail-row"><span>PR Amount:</span> ₱<?php echo number_format($order_details['pr_amount'] ?? 0, 2); ?></div>
        <div class="detail-row"><span>Created By:</span> <?php echo htmlspecialchars($order_details['created_by_name'] ?? 'N/A'); ?></div>
        <div class="detail-row"><span>Created:</span> <?php echo date('M d, Y h:i A', strtotime($order_details['created_at'])); ?></div>
    </div>
    <div style="margin-top:1.5rem; display:flex; gap:0.5rem; flex-wrap:wrap;">
        <?php if (canWriteDepartmentData('procurement')): ?><a href="?action=edit&id=<?php echo $order_details['id']; ?>" class="btn btn-warning"><i class="fas fa-edit"></i> Edit</a><?php endif; ?>
        <?php if (canDelete('procurement')): ?><a href="?action=delete&delete_id=<?php echo $order_details['id']; ?>" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this purchase order?')"><i class="fas fa-trash"></i> Delete</a><?php endif; ?>
        <a href="purchase_history.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back</a>
    </div>
</div>

<?php else: ?>
<!-- List View -->
<div class="card">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem; flex-wrap:wrap; gap:0.5rem;">
        <h3 style="margin:0;"><i class="fas fa-list"></i> All Purchase Orders (<?php echo count($orders); ?>)</h3>
        <div style="display:flex; gap:0.5rem;">
            <input type="text" id="searchPO" placeholder="Search orders..." style="padding:0.4rem 0.8rem; border:1px solid var(--border-color); border-radius:6px; font-size:0.85rem;">
            <select id="filterStatus" style="padding:0.4rem 0.8rem; border:1px solid var(--border-color); border-radius:6px; font-size:0.85rem;">
                <option value="">All Status</option>
                <option value="draft">Draft</option>
                <option value="sent">Sent</option>
                <option value="confirmed">Confirmed</option>
                <option value="delivered">Delivered</option>
                <option value="cancelled">Cancelled</option>
            </select>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table" id="poTable">
            <thead>
                <tr>
                    <th onclick="sortTable('poTable', 0)" style="cursor:pointer;">PO # <i class="fas fa-sort"></i></th>
                    <th onclick="sortTable('poTable', 1)" style="cursor:pointer;">PR # <i class="fas fa-sort"></i></th>
                    <th onclick="sortTable('poTable', 2)" style="cursor:pointer;">Supplier <i class="fas fa-sort"></i></th>
                    <th onclick="sortTable('poTable', 3)" style="cursor:pointer;">Order Date <i class="fas fa-sort"></i></th>
                    <th onclick="sortTable('poTable', 4)" style="cursor:pointer;">Delivery Date <i class="fas fa-sort"></i></th>
                    <th onclick="sortTable('poTable', 5)" style="cursor:pointer;">Status <i class="fas fa-sort"></i></th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($orders)): ?>
                    <tr>
                        <td colspan="7" style="text-align:center;color:#94a3b8;padding:2rem;">
                            <i class="fas fa-shopping-cart" style="font-size:2rem;display:block;margin-bottom:0.5rem;opacity:0.3;"></i>
                            No purchase orders found. Click "New Purchase Order" to create one.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($orders as $order): ?>
                    <tr data-status="<?php echo htmlspecialchars($order['status'] ?? ''); ?>">
                        <td><strong><?php echo htmlspecialchars($order['po_number']); ?></strong></td>
                        <td><?php echo htmlspecialchars($order['pr_number'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($order['supplier']); ?></td>
                        <td><?php echo $order['order_date'] ? date('M d, Y', strtotime($order['order_date'])) : 'N/A'; ?></td>
                        <td><?php echo $order['delivery_date'] ? date('M d, Y', strtotime($order['delivery_date'])) : 'N/A'; ?></td>
                        <td><span class="badge badge-<?php echo $order['status']; ?>"><?php echo ucfirst($order['status']); ?></span></td>
                        <td>
                            <div style="display:flex; gap:0.3rem; flex-wrap:wrap;">
                                <a href="?action=view&id=<?php echo $order['id']; ?>" class="btn btn-sm btn-info" title="View"><i class="fas fa-eye"></i></a>
                                <?php if (canWriteDepartmentData('procurement')): ?><a href="?action=edit&id=<?php echo $order['id']; ?>" class="btn btn-sm btn-warning" title="Edit"><i class="fas fa-edit"></i></a><?php endif; ?>
                                <?php if (canDelete('procurement')): ?><a href="?action=delete&delete_id=<?php echo $order['id']; ?>" class="btn btn-sm btn-danger" title="Delete" onclick="return confirm('Delete this purchase order?')"><i class="fas fa-trash"></i></a><?php endif; ?>
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
document.getElementById('searchPO')?.addEventListener('keyup', function() {
    const searchTerm = this.value.toLowerCase();
    const statusFilter = document.getElementById('filterStatus').value;
    const rows = document.querySelectorAll('#poTable tbody tr');
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
    const searchTerm = document.getElementById('searchPO').value.toLowerCase();
    const rows = document.querySelectorAll('#poTable tbody tr');
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
