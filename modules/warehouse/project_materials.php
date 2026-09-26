<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';

requireDepartment(['warehouse']);

$page_title = 'Project Materials';
$project_id = (int)($_GET['project_id'] ?? 0);

// Get projects for dropdown
$projects = $pdo->query("SELECT id, project_code, name, status FROM projects WHERE status != 'completed' ORDER BY name")->fetchAll();

// Get project materials
$project_materials = [];
try {
    if ($project_id > 0) {
        $project_materials = $pdo->query("
            SELECT 
                m.id, m.material_code, m.name, m.unit, m.current_stock, m.min_stock, m.cost_per_unit, m.category,
                s.name as supplier_name,
                COALESCE(SUM(pri.quantity), 0) as allocated_quantity,
                COUNT(DISTINCT pr.id) as pr_count
            FROM materials m
            LEFT JOIN suppliers s ON m.supplier_id = s.id
            LEFT JOIN purchase_request_items pri ON m.id = pri.material_id
            LEFT JOIN purchase_requests pr ON pri.purchase_request_id = pr.id AND pr.project_id = ?
            WHERE m.status = 'active'
            GROUP BY m.id, m.material_code, m.name, m.unit, m.current_stock, m.min_stock, m.cost_per_unit, m.category, s.name
            HAVING allocated_quantity > 0
            ORDER BY allocated_quantity DESC
        ", [$project_id])->fetchAll();
    } else {
        // Show all materials with project assignments
        $project_materials = $pdo->query("
            SELECT 
                m.id, m.material_code, m.name, m.unit, m.current_stock, m.min_stock, m.cost_per_unit, m.category,
                s.name as supplier_name,
                COUNT(DISTINCT pr.id) as project_count,
                COALESCE(SUM(pri.quantity), 0) as total_allocated
            FROM materials m
            LEFT JOIN suppliers s ON m.supplier_id = s.id
            LEFT JOIN purchase_request_items pri ON m.id = pri.material_id
            LEFT JOIN purchase_requests pr ON pri.purchase_request_id = pr.id AND pr.status IN ('approved', 'confirmed', 'ordered', 'received')
            WHERE m.status = 'active'
            GROUP BY m.id, m.material_code, m.name, m.unit, m.current_stock, m.min_stock, m.cost_per_unit, m.category, s.name
            HAVING project_count > 0
            ORDER BY total_allocated DESC
        ")->fetchAll();
    }
} catch (PDOException $e) {
    $error = userDatabaseError($e);
    $project_materials = [];
}

include '../../includes/header.php';
?>

<style>
.project-materials-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 1.5rem;
}
.project-materials-stats .card {
    border-left: 4px solid #4e73df;
}
</style>

<div class="page-header">
    <h1><i class="fas fa-project-diagram"></i> <?php echo $page_title; ?></h1>
    <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
        <a href="stock.php?action=out" class="btn btn-primary"><i class="fas fa-minus"></i> Stock-Out</a>
        <a href="material_requests.php" class="btn btn-info"><i class="fas fa-clipboard-list"></i> Material Requests</a>
    </div>
</div>

<?php if (isset($error)): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<!-- Project Filter -->
<div class="card">
    <form method="GET" class="report-filters">
        <div class="form-group">
            <label>Filter by Project:</label>
            <select name="project_id">
                <option value="">All Projects</option>
                <?php foreach ($projects as $project): ?>
                    <option value="<?php echo $project['id']; ?>" <?php echo $project_id == $project['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($project['project_code'] . ' - ' . $project['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Apply Filter</button>
        <a href="project_materials.php" class="btn btn-secondary"><i class="fas fa-redo"></i> Reset</a>
    </form>
</div>

<!-- Quick Stats -->
<div class="project-materials-stats">
    <div class="card" style="border-left-color:#4e73df;margin:0;">
        <div style="font-size:0.75rem;color:#64748b;">Total Materials</div>
        <div style="font-size:1.5rem;font-weight:700;"><?php echo count($project_materials); ?></div>
    </div>
    <div class="card" style="border-left-color:#f6c23e;margin:0;">
        <div style="font-size:0.75rem;color:#64748b;">Total Allocated</div>
        <div style="font-size:1.5rem;font-weight:700;"><?php echo number_format(array_sum(array_column($project_materials, $project_id > 0 ? 'allocated_quantity' : 'total_allocated'))); ?></div>
    </div>
    <div class="card" style="border-left-color:#36b9cc;margin:0;">
        <div style="font-size:0.75rem;color:#64748b;">Total Stock</div>
        <div style="font-size:1.5rem;font-weight:700;"><?php echo number_format(array_sum(array_column($project_materials, 'current_stock'))); ?></div>
    </div>
    <div class="card" style="border-left-color:#e74a3b;margin:0;">
        <div style="font-size:0.75rem;color:#64748b;">Low Stock</div>
        <div style="font-size:1.5rem;font-weight:700;">
            <?php 
            $low_stock = array_filter($project_materials, function($m) { return $m['current_stock'] <= $m['min_stock'] && $m['min_stock'] > 0; });
            echo count($low_stock);
            ?>
        </div>
    </div>
</div>

<!-- Project Materials List -->
<div class="card">
    <h3 class="section-title"><i class="fas fa-list"></i> Materials Assigned to Projects</h3>
    <?php if (empty($project_materials)): ?>
        <div class="empty-state">
            <i class="fas fa-project-diagram"></i>
            <p>No project materials found.</p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Material Code</th>
                        <th>Name</th>
                        <th>Category</th>
                        <th>Current Stock</th>
                        <th>Min Stock</th>
                        <th><?php echo $project_id > 0 ? 'Allocated' : 'Total Allocated'; ?></th>
                        <th>Unit</th>
                        <th>Cost per Unit</th>
                        <th>Supplier</th>
                        <th><?php echo $project_id > 0 ? 'PR Count' : 'Projects'; ?></th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($project_materials as $material): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($material['material_code']); ?></strong></td>
                        <td><?php echo htmlspecialchars($material['name']); ?></td>
                        <td><?php echo htmlspecialchars($material['category'] ?? 'N/A'); ?></td>
                        <td class="<?php echo $material['current_stock'] <= $material['min_stock'] && $material['min_stock'] > 0 ? 'text-danger' : 'text-success'; ?>"><?php echo number_format($material['current_stock']); ?></td>
                        <td><?php echo number_format($material['min_stock']); ?></td>
                        <td><?php echo number_format($project_id > 0 ? $material['allocated_quantity'] : $material['total_allocated']); ?></td>
                        <td><?php echo htmlspecialchars($material['unit']); ?></td>
                        <td>₱<?php echo number_format($material['cost_per_unit'], 2); ?></td>
                        <td><?php echo htmlspecialchars($material['supplier_name'] ?? 'N/A'); ?></td>
                        <td><?php echo $project_id > 0 ? $material['pr_count'] : $material['project_count']; ?></td>
                        <td>
                            <a href="stock.php?action=out&material_id=<?php echo $material['id']; ?>" class="btn btn-sm btn-primary"><i class="fas fa-minus"></i> Release</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php include '../../includes/footer.php'; ?>
