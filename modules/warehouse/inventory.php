<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';

requireDepartment(['warehouse']);

$page_title = 'Inventory Report';

// Get inventory with stock status
$inventory = [];
try {
    $query = "
        SELECT 
            m.*,
            CASE 
                WHEN m.current_stock <= 0 THEN 'Out of Stock'
                WHEN m.current_stock <= m.min_stock THEN 'Low Stock'
                ELSE 'In Stock'
            END as stock_status,
            (SELECT COUNT(*) FROM stock_movements WHERE material_id = m.id AND movement_type = 'in') as total_in,
            (SELECT COUNT(*) FROM stock_movements WHERE material_id = m.id AND movement_type = 'out') as total_out
        FROM materials m
        WHERE m.status = 'active'
        ORDER BY 
            CASE 
                WHEN m.current_stock <= 0 THEN 1
                WHEN m.current_stock <= m.min_stock THEN 2
                ELSE 3
            END,
            m.name ASC
    ";
    $inventory = $pdo->query($query)->fetchAll();
} catch (PDOException $e) {
    $error = 'Database error: ' . $e->getMessage();
}

include '../../includes/header.php';
?>

<div class="page-header">
    <h1><i class="fas fa-clipboard-list"></i> <?php echo $page_title; ?></h1>
    <button onclick="window.print()" class="btn btn-info"><i class="fas fa-print"></i> Print Report</button>
</div>

<?php if (isset($error)): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<!-- Summary Stats -->
<div class="stats-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:1rem;margin-bottom:1.5rem;">
    <div class="card" style="border-left:4px solid #4e73df;">
        <div style="font-size:0.85rem;color:#64748b;">Total Items</div>
        <div style="font-size:1.8rem;font-weight:700;"><?php echo count($inventory); ?></div>
    </div>
    <div class="card" style="border-left:4px solid #1cc88a;">
        <div style="font-size:0.85rem;color:#64748b;">In Stock</div>
        <div style="font-size:1.8rem;font-weight:700;">
            <?php echo count(array_filter($inventory, fn($item) => $item['stock_status'] === 'In Stock')); ?>
        </div>
    </div>
    <div class="card" style="border-left:4px solid #f6c23e;">
        <div style="font-size:0.85rem;color:#64748b;">Low Stock</div>
        <div style="font-size:1.8rem;font-weight:700;">
            <?php echo count(array_filter($inventory, fn($item) => $item['stock_status'] === 'Low Stock')); ?>
        </div>
    </div>
    <div class="card" style="border-left:4px solid #e74a3b;">
        <div style="font-size:0.85rem;color:#64748b;">Out of Stock</div>
        <div style="font-size:1.8rem;font-weight:700;">
            <?php echo count(array_filter($inventory, fn($item) => $item['stock_status'] === 'Out of Stock')); ?>
        </div>
    </div>
</div>

<!-- Inventory Table -->
<div class="card">
    <div class="table-responsive">
        <table class="table" id="inventoryTable">
            <thead>
                <tr>
                    <th onclick="sortTable('inventoryTable', 0)">Code</th>
                    <th onclick="sortTable('inventoryTable', 1)">Name</th>
                    <th onclick="sortTable('inventoryTable', 2)">Category</th>
                    <th onclick="sortTable('inventoryTable', 3)">Unit</th>
                    <th onclick="sortTable('inventoryTable', 4)">Current Stock</th>
                    <th onclick="sortTable('inventoryTable', 5)">Min Stock</th>
                    <th onclick="sortTable('inventoryTable', 6)">Status</th>
                    <th onclick="sortTable('inventoryTable', 7)">Value</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($inventory)): ?>
                    <tr>
                        <td colspan="8" style="text-align:center;color:#94a3b8;padding:2rem;">No inventory items found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($inventory as $item):
                        $is_low = ((float)$item['current_stock'] <= (float)$item['min_stock'] && (float)$item['min_stock'] > 0)
                            || (float)$item['current_stock'] <= 0;
                        $row_class = $is_low ? 'row-low-stock' : '';
                        if (!empty($_GET['highlight']) && (int)$_GET['highlight'] === (int)$item['id']) {
                            $row_class .= ($row_class ? ' ' : '') . 'row-highlight';
                        }
                    ?>
                    <tr class="<?php echo trim($row_class); ?>" id="material-<?php echo (int)$item['id']; ?>">
                        <td><strong><?php echo htmlspecialchars($item['material_code']); ?></strong></td>
                        <td><?php echo htmlspecialchars($item['name']); ?></td>
                        <td><?php echo htmlspecialchars($item['category'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($item['unit']); ?></td>
                        <td>
                            <strong><?php echo number_format($item['current_stock']); ?></strong>
                        </td>
                        <td><?php echo number_format($item['min_stock']); ?></td>
                        <td>
                            <?php if ($item['stock_status'] === 'Out of Stock'): ?>
                                <span class="badge badge-danger">Out of Stock</span>
                            <?php elseif ($item['stock_status'] === 'Low Stock'): ?>
                                <span class="badge badge-warning">Low Stock</span>
                            <?php else: ?>
                                <span class="badge badge-success">In Stock</span>
                            <?php endif; ?>
                        </td>
                        <td>₱<?php echo number_format($item['current_stock'] * $item['cost_per_unit'], 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            <tfoot>
                <tr>
                    <th colspan="7" style="text-align:right;">Total Inventory Value:</th>
                    <th>
                        <?php 
                        $total_value = array_sum(array_map(fn($item) => $item['current_stock'] * $item['cost_per_unit'], $inventory));
                        echo '₱' . number_format($total_value, 2);
                        ?>
                    </th>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>