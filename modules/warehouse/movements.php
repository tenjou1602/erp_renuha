<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';

requireDepartment(['warehouse']);

$page_title = 'Stock Movements';

// Get movements
$movements = [];
try {
    $query = "
        SELECT sm.*, m.name as material_name, m.material_code, m.unit, u.full_name as user_name
        FROM stock_movements sm
        LEFT JOIN materials m ON sm.material_id = m.id
        LEFT JOIN users u ON sm.created_by = u.id
        ORDER BY sm.created_at DESC
    ";
    $movements = $pdo->query($query)->fetchAll();
} catch (PDOException $e) {
    $error = 'Database error: ' . $e->getMessage();
}

include '../../includes/header.php';
?>

<div class="page-header">
    <h1><i class="fas fa-exchange-alt"></i> <?php echo $page_title; ?></h1>
</div>

<?php if (isset($error)): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<div class="card">
    <div class="table-responsive">
        <table class="table" id="movementTable">
            <thead>
                <tr>
                    <th onclick="sortTable('movementTable', 0)">Date</th>
                    <th onclick="sortTable('movementTable', 1)">Material</th>
                    <th onclick="sortTable('movementTable', 2)">Type</th>
                    <th onclick="sortTable('movementTable', 3)">Quantity</th>
                    <th onclick="sortTable('movementTable', 4)">Reason</th>
                    <th onclick="sortTable('movementTable', 5)">User</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($movements)): ?>
                    <tr>
                        <td colspan="6" style="text-align:center;color:#94a3b8;padding:2rem;">No stock movements recorded.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($movements as $movement): ?>
                    <tr>
                        <td><?php echo date('M d, Y h:i A', strtotime($movement['created_at'])); ?></td>
                        <td>
                            <strong><?php echo htmlspecialchars($movement['material_name'] ?? 'N/A'); ?></strong>
                            <br><small><?php echo htmlspecialchars($movement['material_code'] ?? ''); ?></small>
                        </td>
                        <td>
                            <span class="badge badge-<?php echo $movement['movement_type'] === 'in' ? 'success' : ($movement['movement_type'] === 'out' ? 'danger' : 'warning'); ?>">
                                <?php echo ucfirst($movement['movement_type']); ?>
                            </span>
                        </td>
                        <td><?php echo number_format($movement['quantity']) . ' ' . htmlspecialchars($movement['unit'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($movement['reason'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($movement['user_name'] ?? 'N/A'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>