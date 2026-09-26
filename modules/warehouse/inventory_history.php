<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';

requireDepartment(['warehouse']);

$page_title = 'Inventory History';
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');
$material_id = (int)($_GET['material_id'] ?? 0);
$movement_type = $_GET['movement_type'] ?? '';

// Get stock movements
$stock_movements = [];
$materials = [];
try {
    // Get materials for dropdown
    $materials = $pdo->query("SELECT id, material_code, name FROM materials WHERE status = 'active' ORDER BY name")->fetchAll();
    
    // Build query
    $query = "
        SELECT sm.*, m.material_code, m.name as material_name, m.unit,
               u.full_name as user_name
        FROM stock_movements sm
        LEFT JOIN materials m ON sm.material_id = m.id
        LEFT JOIN users u ON sm.created_by = u.id
        WHERE sm.created_at BETWEEN '$start_date' AND '$end_date 23:59:59'
    ";
    
    if ($material_id > 0) {
        $query .= " AND sm.material_id = $material_id";
    }
    
    if ($movement_type !== '') {
        $query .= " AND sm.movement_type = '$movement_type'";
    }
    
    $query .= " ORDER BY sm.created_at DESC";
    
    $stock_movements = $pdo->query($query)->fetchAll();
    
} catch (PDOException $e) {
    $error = userDatabaseError($e);
    $stock_movements = [];
}

include '../../includes/header.php';
?>

<style>
.history-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 1.5rem;
}
.history-stats .card {
    border-left: 4px solid #4e73df;
}
</style>

<div class="page-header">
    <h1><i class="fas fa-history"></i> <?php echo $page_title; ?></h1>
    <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
        <a href="inventory_reports.php" class="btn btn-info"><i class="fas fa-chart-bar"></i> Inventory Reports</a>
        <a href="movements.php" class="btn btn-primary"><i class="fas fa-exchange-alt"></i> All Movements</a>
    </div>
</div>

<?php if (isset($error)): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<!-- Filters -->
<div class="card">
    <form method="GET" class="report-filters">
        <div class="form-group">
            <label>Start Date:</label>
            <input type="date" name="start_date" value="<?php echo $start_date; ?>">
        </div>
        <div class="form-group">
            <label>End Date:</label>
            <input type="date" name="end_date" value="<?php echo $end_date; ?>">
        </div>
        <div class="form-group">
            <label>Material:</label>
            <select name="material_id">
                <option value="">All Materials</option>
                <?php foreach ($materials as $material): ?>
                    <option value="<?php echo $material['id']; ?>" <?php echo $material_id == $material['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($material['material_code'] . ' - ' . $material['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Movement Type:</label>
            <select name="movement_type">
                <option value="">All Types</option>
                <option value="in" <?php echo $movement_type === 'in' ? 'selected' : ''; ?>>Stock-In</option>
                <option value="out" <?php echo $movement_type === 'out' ? 'selected' : ''; ?>>Stock-Out</option>
                <option value="adjustment" <?php echo $movement_type === 'adjustment' ? 'selected' : ''; ?>>Adjustment</option>
            </select>
        </div>
        <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Apply Filter</button>
        <a href="inventory_history.php" class="btn btn-secondary"><i class="fas fa-redo"></i> Reset</a>
    </form>
</div>

<!-- Quick Stats -->
<div class="history-stats">
    <div class="card" style="border-left-color:#4e73df;margin:0;">
        <div style="font-size:0.75rem;color:#64748b;">Total Movements</div>
        <div style="font-size:1.5rem;font-weight:700;"><?php echo count($stock_movements); ?></div>
    </div>
    <div class="card" style="border-left-color:#1cc88a;margin:0;">
        <div style="font-size:0.75rem;color:#64748b;">Stock-In</div>
        <div style="font-size:1.5rem;font-weight:700;">
            <?php 
            $stock_in = array_filter($stock_movements, function($m) { return $m['movement_type'] === 'in'; });
            echo count($stock_in);
            ?>
        </div>
    </div>
    <div class="card" style="border-left-color:#e74a3b;margin:0;">
        <div style="font-size:0.75rem;color:#64748b;">Stock-Out</div>
        <div style="font-size:1.5rem;font-weight:700;">
            <?php 
            $stock_out = array_filter($stock_movements, function($m) { return $m['movement_type'] === 'out'; });
            echo count($stock_out);
            ?>
        </div>
    </div>
    <div class="card" style="border-left-color:#f6c23e;margin:0;">
        <div style="font-size:0.75rem;color:#64748b;">Adjustments</div>
        <div style="font-size:1.5rem;font-weight:700;">
            <?php 
            $adjustments = array_filter($stock_movements, function($m) { return $m['movement_type'] === 'adjustment'; });
            echo count($adjustments);
            ?>
        </div>
    </div>
</div>

<!-- Inventory History -->
<div class="card">
    <h3 class="section-title"><i class="fas fa-list"></i> Stock Movement History</h3>
    <?php if (empty($stock_movements)): ?>
        <div class="empty-state">
            <i class="fas fa-history"></i>
            <p>No stock movements found for the selected criteria.</p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Date/Time</th>
                        <th>Material</th>
                        <th>Type</th>
                        <th>Quantity</th>
                        <th>Unit</th>
                        <th>Reference</th>
                        <th>Notes</th>
                        <th>User</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($stock_movements as $movement): ?>
                    <tr>
                        <td><?php echo date('M d, Y h:i A', strtotime($movement['created_at'])); ?></td>
                        <td><strong><?php echo htmlspecialchars($movement['material_name'] ?? 'N/A'); ?></strong></td>
                        <td>
                            <span class="badge badge-<?php echo $movement['movement_type'] === 'in' ? 'success' : ($movement['movement_type'] === 'out' ? 'danger' : 'warning'); ?>">
                                <?php echo ucfirst($movement['movement_type']); ?>
                            </span>
                        </td>
                        <td class="<?php echo $movement['movement_type'] === 'in' ? 'text-success' : ($movement['movement_type'] === 'out' ? 'text-danger' : 'text-warning'); ?>">
                            <?php echo $movement['movement_type'] === 'out' ? '-' : '+'; ?><?php echo number_format($movement['quantity']); ?>
                        </td>
                        <td><?php echo htmlspecialchars($movement['unit'] ?? 'pcs'); ?></td>
                        <td><?php echo htmlspecialchars($movement['reference_type'] ?? ''); ?> <?php echo $movement['reference_id'] ? '#' . $movement['reference_id'] : ''; ?></td>
                        <td><?php echo htmlspecialchars(substr($movement['notes'] ?? '', 0, 30)) . (strlen($movement['notes'] ?? '') > 30 ? '...' : ''); ?></td>
                        <td><?php echo htmlspecialchars($movement['user_name'] ?? 'N/A'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php include '../../includes/footer.php'; ?>
