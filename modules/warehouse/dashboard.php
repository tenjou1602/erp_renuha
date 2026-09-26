<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';

requireDepartment(['warehouse']);

$page_title = 'Warehouse Dashboard';

$stats = [];
try {
    $stats['total_inventory'] = $pdo->query("SELECT COUNT(*) FROM materials WHERE status = 'active'")->fetchColumn() ?? 0;
    $stats['total_stock'] = $pdo->query("SELECT COALESCE(SUM(current_stock), 0) FROM materials WHERE status = 'active'")->fetchColumn() ?? 0;
    $stats['low_stock'] = $pdo->query("SELECT COUNT(*) FROM materials WHERE current_stock <= min_stock AND min_stock > 0 AND status = 'active'")->fetchColumn() ?? 0;
    
    $stats['incoming'] = $pdo->query("SELECT COUNT(*) FROM stock_movements WHERE movement_type = 'in' AND created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)")->fetchColumn() ?? 0;
    $stats['outgoing'] = $pdo->query("SELECT COUNT(*) FROM stock_movements WHERE movement_type = 'out' AND created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)")->fetchColumn() ?? 0;
    
    $stats['recent_received'] = $pdo->query("SELECT COALESCE(SUM(quantity), 0) FROM stock_movements WHERE movement_type = 'in' AND created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)")->fetchColumn() ?? 0;
    $stats['recent_released'] = $pdo->query("SELECT COALESCE(SUM(quantity), 0) FROM stock_movements WHERE movement_type = 'out' AND created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)")->fetchColumn() ?? 0;
    
    $stats['project_materials'] = $pdo->query("SELECT COUNT(DISTINCT pr.project_id) FROM purchase_request_items pri LEFT JOIN purchase_requests pr ON pri.purchase_request_id = pr.id WHERE pr.status IN ('approved', 'confirmed', 'ordered', 'received')")->fetchColumn() ?? 0;
    
    $recent_stock_movements = $pdo->query("
        SELECT sm.*, m.name as material_name, u.full_name as user_name
        FROM stock_movements sm
        LEFT JOIN materials m ON sm.material_id = m.id
        LEFT JOIN users u ON sm.created_by = u.id
        ORDER BY sm.created_at DESC
        LIMIT 10
    ")->fetchAll();
    
} catch (PDOException $e) {
    $error = userDatabaseError($e);
}

include '../../includes/header.php';
?>

<div class="page-header">
    <h1><i class="fas fa-warehouse"></i> <?php echo $page_title; ?></h1>
    <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
        <a href="stock.php?action=in" class="btn btn-primary"><i class="fas fa-plus"></i> Stock-In</a>
        <a href="stock.php?action=out" class="btn btn-success"><i class="fas fa-minus"></i> Stock-Out</a>
        <a href="material_requests.php" class="btn btn-info"><i class="fas fa-clipboard-list"></i> Material Requests</a>
    </div>
</div>

<?php if (isset($error)): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<!-- Stats -->
<div class="stats-grid">
    <div class="card" style="border-left:4px solid #1cc88a;">
        <div style="font-size:0.85rem;color:#64748b;">Total Inventory</div>
        <div style="font-size:1.8rem;font-weight:700;"><?php echo number_format($stats['total_inventory'] ?? 0); ?></div>
    </div>
    <div class="card" style="border-left:4px solid #16a085;">
        <div style="font-size:0.85rem;color:#64748b;">Incoming (30d)</div>
        <div style="font-size:1.8rem;font-weight:700;"><?php echo number_format($stats['incoming'] ?? 0); ?></div>
    </div>
    <div class="card" style="border-left-color:#e74a3b;">
        <div style="font-size:0.85rem;color:#64748b;">Outgoing (30d)</div>
        <div style="font-size:1.8rem;font-weight:700;"><?php echo number_format($stats['outgoing'] ?? 0); ?></div>
    </div>
    <div class="card" style="border-left-color:#f6c23e;">
        <div style="font-size:0.85rem;color:#64748b;">Low Stock</div>
        <div style="font-size:1.8rem;font-weight:700;"><?php echo number_format($stats['low_stock'] ?? 0); ?></div>
    </div>
    <div class="card" style="border-left-color:#4e73df;">
        <div style="font-size:0.85rem;color:#64748b;">Total Stock</div>
        <div style="font-size:1.8rem;font-weight:700;"><?php echo number_format($stats['total_stock'] ?? 0); ?></div>
    </div>
    <div class="card" style="border-left-color:#36b9cc;">
        <div style="font-size:0.85rem;color:#64748b;">Recently Received</div>
        <div style="font-size:1.8rem;font-weight:700;"><?php echo number_format($stats['recent_received'] ?? 0); ?></div>
    </div>
    <div class="card" style="border-left-color:#858796;">
        <div style="font-size:0.85rem;color:#64748b;">Recently Released</div>
        <div style="font-size:1.8rem;font-weight:700;"><?php echo number_format($stats['recent_released'] ?? 0); ?></div>
    </div>
    <div class="card" style="border-left-color:#f59e0b;">
        <div style="font-size:0.85rem;color:#64748b;">Project Materials</div>
        <div style="font-size:1.8rem;font-weight:700;"><?php echo number_format($stats['project_materials'] ?? 0); ?></div>
    </div>
</div>

<!-- Recent Stock Movements -->
<div class="card">
    <h3><i class="fas fa-exchange-alt"></i> Recent Stock Movements</h3>
    <?php if (empty($recent_stock_movements)): ?>
        <div class="empty-state">
            <i class="fas fa-exchange-alt"></i>
            <p>No recent stock movements.</p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Material</th>
                        <th>Type</th>
                        <th>Quantity</th>
                        <th>User</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_stock_movements as $movement): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($movement['material_name'] ?? 'N/A'); ?></td>
                        <td><span class="badge badge-<?php echo $movement['movement_type'] === 'in' ? 'success' : 'danger'; ?>"><?php echo ucfirst($movement['movement_type']); ?></span></td>
                        <td><?php echo number_format($movement['quantity']); ?></td>
                        <td><?php echo htmlspecialchars($movement['user_name'] ?? 'N/A'); ?></td>
                        <td><?php echo date('M d, Y h:i A', strtotime($movement['created_at'])); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once '../../includes/stock_alerts.php'; ?>
<?php renderLowStockBanner('../inventory.php'); ?>

<?php include '../../includes/footer.php'; ?>
