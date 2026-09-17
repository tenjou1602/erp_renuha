<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/stock_alerts.php';

requireDepartment(['warehouse']);

$page_title = 'Warehouse Dashboard';

// Get statistics
try {
    $stats = [];
    $stats['total_items'] = $pdo->query("SELECT COUNT(*) FROM materials WHERE status = 'active'")->fetchColumn() ?? 0;
    $stats['total_stock'] = $pdo->query("SELECT COALESCE(SUM(current_stock), 0) FROM materials WHERE status = 'active'")->fetchColumn() ?? 0;
    $stats['low_stock'] = $pdo->query("SELECT COUNT(*) FROM materials WHERE current_stock <= min_stock AND min_stock > 0 AND status = 'active'")->fetchColumn() ?? 0;
    $stats['total_value'] = $pdo->query("SELECT COALESCE(SUM(current_stock * cost_per_unit), 0) FROM materials WHERE status = 'active'")->fetchColumn() ?? 0;
    $stats['total_movements'] = $pdo->query("SELECT COUNT(*) FROM stock_movements")->fetchColumn() ?? 0;
    $stats['recent_movements'] = $pdo->query("
        SELECT sm.*, m.name as material_name, u.full_name as user_name
        FROM stock_movements sm
        LEFT JOIN materials m ON sm.material_id = m.id
        LEFT JOIN users u ON sm.created_by = u.id
        ORDER BY sm.created_at DESC
        LIMIT 10
    ")->fetchAll();
    
} catch (PDOException $e) {
    $error = 'Database error: ' . $e->getMessage();
}

include '../../includes/header.php';
?>

<div class="page-header">
    <h1><i class="fas fa-warehouse"></i> Warehouse Dashboard</h1>
</div>

<?php if (isset($error)): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<?php renderLowStockBanner('inventory.php'); ?>

<!-- Stats Cards -->
<div class="stats-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:1rem;margin-bottom:1.5rem;">
    <div class="card" style="border-left:4px solid #4e73df;">
        <div style="font-size:0.85rem;color:#64748b;">Total Items</div>
        <div style="font-size:1.8rem;font-weight:700;"><?php echo number_format($stats['total_items'] ?? 0); ?></div>
    </div>
    <div class="card" style="border-left:4px solid #36b9cc;">
        <div style="font-size:0.85rem;color:#64748b;">Total Stock</div>
        <div style="font-size:1.8rem;font-weight:700;"><?php echo number_format($stats['total_stock'] ?? 0); ?></div>
    </div>
    <div class="card" style="border-left:4px solid #e74a3b;">
        <div style="font-size:0.85rem;color:#64748b;">Low Stock Items</div>
        <div style="font-size:1.8rem;font-weight:700;"><?php echo number_format($stats['low_stock'] ?? 0); ?></div>
    </div>
    <div class="card" style="border-left:4px solid #1cc88a;">
        <div style="font-size:0.85rem;color:#64748b;">Total Value</div>
        <div style="font-size:1.8rem;font-weight:700;">₱<?php echo number_format($stats['total_value'] ?? 0, 2); ?></div>
    </div>
</div>

<!-- Quick Actions -->
<div class="card">
    <h3 style="margin-bottom:1rem;">Quick Actions</h3>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1rem;">
        <a href="stock.php?action=add" class="btn btn-primary" style="justify-content:center;"><i class="fas fa-plus"></i> Receive Stock</a>
        <a href="movements.php" class="btn btn-info" style="justify-content:center;"><i class="fas fa-exchange-alt"></i> View Movements</a>
        <a href="inventory.php" class="btn btn-warning" style="justify-content:center;"><i class="fas fa-clipboard-list"></i> Inventory Report</a>
    </div>
</div>

<!-- Recent Movements -->
<div class="card">
    <h3 style="margin-bottom:1rem;"><i class="fas fa-clock"></i> Recent Stock Movements</h3>
    <?php if (empty($stats['recent_movements'])): ?>
        <div class="empty-state">
            <i class="fas fa-exchange-alt"></i>
            <p>No stock movements recorded yet.</p>
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
                    <?php foreach ($stats['recent_movements'] as $movement): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($movement['material_name'] ?? 'N/A'); ?></td>
                        <td><span class="badge badge-<?php echo $movement['movement_type'] === 'in' ? 'success' : ($movement['movement_type'] === 'out' ? 'danger' : 'warning'); ?>">
                            <?php echo ucfirst($movement['movement_type']); ?>
                        </span></td>
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

<?php include '../../includes/footer.php'; ?>