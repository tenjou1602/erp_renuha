<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';

requireDepartment(['procurement']);

$page_title = 'Procurement Dashboard';

$stats = [];
try {
    $stats['total_prs'] = $pdo->query("SELECT COUNT(*) FROM purchase_requests")->fetchColumn() ?? 0;
    $stats['pending_prs'] = $pdo->query("SELECT COUNT(*) FROM purchase_requests WHERE status = 'pending'")->fetchColumn() ?? 0;
    $stats['approved_prs'] = $pdo->query("SELECT COUNT(*) FROM purchase_requests WHERE status = 'approved'")->fetchColumn() ?? 0;
    $stats['total_value'] = $pdo->query("SELECT COALESCE(SUM(total_amount), 0) FROM purchase_requests WHERE status IN ('approved', 'confirmed', 'ordered', 'received')")->fetchColumn() ?? 0;
    $stats['suppliers'] = $pdo->query("SELECT COUNT(*) FROM suppliers WHERE status = 'active'")->fetchColumn() ?? 0;
    $stats['materials'] = $pdo->query("SELECT COUNT(*) FROM materials WHERE status = 'active'")->fetchColumn() ?? 0;
    
    $recent_purchases = $pdo->query("
        SELECT pr.*, p.project_code, u.full_name as requestor_name
        FROM purchase_requests pr
        LEFT JOIN projects p ON pr.project_id = p.id
        LEFT JOIN users u ON pr.requestor_id = u.id
        ORDER BY pr.created_at DESC
        LIMIT 5
    ")->fetchAll();
    
    $low_stock_materials = $pdo->query("
        SELECT m.material_code, m.name, m.current_stock, m.min_stock, m.unit
        FROM materials m
        WHERE m.current_stock <= m.min_stock AND m.min_stock > 0 AND m.status = 'active'
        ORDER BY (m.min_stock - m.current_stock) DESC
        LIMIT 5
    ")->fetchAll();
    
} catch (PDOException $e) {
    $error = userDatabaseError($e);
}

include '../../includes/header.php';
?>

<div class="page-header">
    <h1><i class="fas fa-shopping-cart"></i> <?php echo $page_title; ?></h1>
    <?php if (canWriteDepartmentData('procurement')): ?>
    <div class="button-row">
        <a href="purchase_requests.php?action=add" class="btn btn-primary"><i class="fas fa-file-invoice"></i> New Purchase Request</a>
        <a href="purchase_orders.php?action=add" class="btn btn-outline"><i class="fas fa-shopping-cart"></i> New Purchase Order</a>
        <a href="suppliers.php?action=add" class="btn btn-outline"><i class="fas fa-truck"></i> Add Supplier</a>
    </div>
    <?php endif; ?>
</div>

<?php if (isset($error)): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<!-- Stats -->
<div class="stats-grid">
    <div class="card" style="border-left:4px solid #f59e0b;">
        <div style="font-size:0.85rem;color:#64748b;">Total Purchase Requests</div>
        <div style="font-size:1.8rem;font-weight:700;"><?php echo number_format($stats['total_prs'] ?? 0); ?></div>
    </div>
    <div class="card" style="border-left:4px solid #f6c23e;">
        <div style="font-size:0.85rem;color:#64748b;">Pending</div>
        <div style="font-size:1.8rem;font-weight:700;"><?php echo number_format($stats['pending_prs'] ?? 0); ?></div>
    </div>
    <div class="card" style="border-left:4px solid #1cc88a;">
        <div style="font-size:0.85rem;color:#64748b;">Approved</div>
        <div style="font-size:1.8rem;font-weight:700;"><?php echo number_format($stats['approved_prs'] ?? 0); ?></div>
    </div>
    <div class="card" style="border-left:4px solid #36b9cc;">
        <div style="font-size:0.85rem;color:#64748b;">Total Value</div>
        <div style="font-size:1.8rem;font-weight:700;">₱<?php echo number_format($stats['total_value'] ?? 0, 2); ?></div>
    </div>
    <div class="card" style="border-left:4px solid #858796;">
        <div style="font-size:0.85rem;color:#64748b;">Suppliers</div>
        <div style="font-size:1.8rem;font-weight:700;"><?php echo number_format($stats['suppliers'] ?? 0); ?></div>
    </div>
    <div class="card" style="border-left:4px solid #e74a3b;">
        <div style="font-size:0.85rem;color:#64748b;">Materials</div>
        <div style="font-size:1.8rem;font-weight:700;"><?php echo number_format($stats['materials'] ?? 0); ?></div>
    </div>
</div>

<!-- Recent Purchases -->
<div class="card">
    <h3><i class="fas fa-clock"></i> Recent Purchases</h3>
    <?php if (empty($recent_purchases)): ?>
        <div class="empty-state">
            <i class="fas fa-shopping-cart"></i>
            <p>No recent purchases.</p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>PR #</th>
                        <th>Project</th>
                        <th>Requestor</th>
                        <th>Total Amount</th>
                        <th>Status</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_purchases as $pr): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($pr['pr_number']); ?></strong></td>
                        <td><?php echo htmlspecialchars($pr['project_code'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($pr['requestor_name'] ?? 'N/A'); ?></td>
                        <td>₱<?php echo number_format($pr['total_amount'], 2); ?></td>
                        <td><span class="badge badge-<?php echo $pr['status']; ?>"><?php echo ucfirst($pr['status']); ?></span></td>
                        <td><?php echo date('M d, Y', strtotime($pr['created_at'])); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Materials That Need to be Purchased -->
<div class="card">
    <h3><i class="fas fa-exclamation-triangle"></i> Materials That Need to be Purchased</h3>
    <?php if (empty($low_stock_materials)): ?>
        <div class="empty-state">
            <i class="fas fa-check-circle"></i>
            <p>No materials need purchasing.</p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Material Code</th>
                        <th>Name</th>
                        <th>Current Stock</th>
                        <th>Min Stock</th>
                        <th>Unit</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($low_stock_materials as $material): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($material['material_code']); ?></strong></td>
                        <td><?php echo htmlspecialchars($material['name']); ?></td>
                        <td class="text-danger"><?php echo number_format($material['current_stock']); ?></td>
                        <td><?php echo number_format($material['min_stock']); ?></td>
                        <td><?php echo htmlspecialchars($material['unit']); ?></td>
                        <td>
                            <?php if (canWriteDepartmentData('procurement')): ?>
                            <a href="purchase_requests.php?action=add" class="btn btn-sm btn-primary">Create PR</a>
                            <?php else: ?>
                            <a href="purchase_requests.php" class="btn btn-sm btn-outline">View PRs</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php include '../../includes/footer.php'; ?>
