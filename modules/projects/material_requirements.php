<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';

requireDepartment(['engineering']);

$page_title = 'Material Requirements';
$action = $_GET['action'] ?? 'list';
$project_id = (int)($_GET['project_id'] ?? 0);

// Get projects for dropdown
$projects = $pdo->query("SELECT id, project_code, name, status FROM projects WHERE status != 'completed' ORDER BY name")->fetchAll();

// Get material requirements data
$material_requirements = [];
$project_materials = [];
$low_stock_materials = [];
try {
    // Low stock materials that need procurement
    $low_stock_materials = $pdo->query("
        SELECT m.id, m.material_code, m.name, m.unit, m.current_stock, m.min_stock, m.max_stock, m.cost_per_unit,
               s.name as supplier_name, s.phone as supplier_phone, s.email as supplier_email,
               COUNT(DISTINCT pr.id) as pending_pr_count
        FROM materials m
        LEFT JOIN suppliers s ON m.supplier_id = s.id
        LEFT JOIN purchase_request_items pri ON m.id = pri.material_id
        LEFT JOIN purchase_requests pr ON pri.purchase_request_id = pr.id AND pr.status IN ('pending', 'approved', 'confirmed')
        WHERE m.status = 'active' AND m.current_stock <= m.min_stock AND m.min_stock > 0
        GROUP BY m.id, m.material_code, m.name, m.unit, m.current_stock, m.min_stock, m.max_stock, m.cost_per_unit, s.name, s.phone, s.email
        ORDER BY m.current_stock ASC
    ")->fetchAll();
    
    // Material requirements by project (materials used in projects)
    if ($project_id > 0) {
        $project_materials = $pdo->query("
            SELECT m.id, m.material_code, m.name, m.unit, m.current_stock, m.min_stock, m.max_stock, m.cost_per_unit,
                   s.name as supplier_name,
                   COALESCE(SUM(pri.quantity), 0) as required_quantity,
                   COUNT(DISTINCT pr.id) as pr_count
            FROM materials m
            LEFT JOIN suppliers s ON m.supplier_id = s.id
            LEFT JOIN purchase_request_items pri ON m.id = pri.material_id
            LEFT JOIN purchase_requests pr ON pri.purchase_request_id = pr.id AND pr.project_id = ?
            WHERE m.status = 'active'
            GROUP BY m.id, m.material_code, m.name, m.unit, m.current_stock, m.min_stock, m.max_stock, m.cost_per_unit, s.name
            HAVING required_quantity > 0
            ORDER BY required_quantity DESC
        ", [$project_id])->fetchAll();
    }
    
    // Overall material requirements across all projects
    $material_requirements = $pdo->query("
        SELECT m.id, m.material_code, m.name, m.unit, m.current_stock, m.min_stock, m.max_stock, m.cost_per_unit,
               m.category,
               s.name as supplier_name,
               COUNT(DISTINCT pr.id) as project_count,
               COALESCE(SUM(pri.quantity), 0) as total_required,
               COALESCE(SUM(CASE WHEN pr.status IN ('pending', 'approved', 'confirmed') THEN pri.quantity ELSE 0 END), 0) as pending_quantity
        FROM materials m
        LEFT JOIN suppliers s ON m.supplier_id = s.id
        LEFT JOIN purchase_request_items pri ON m.id = pri.material_id
        LEFT JOIN purchase_requests pr ON pri.purchase_request_id = pr.id
        WHERE m.status = 'active'
        GROUP BY m.id, m.material_code, m.name, m.unit, m.current_stock, m.min_stock, m.max_stock, m.cost_per_unit, m.category, s.name
        HAVING total_required > 0 OR current_stock <= min_stock
        ORDER BY (total_required - current_stock) DESC
        LIMIT 20
    ")->fetchAll();
    
} catch (PDOException $e) {
    $error = userDatabaseError($e);
    $material_requirements = [];
    $project_materials = [];
    $low_stock_materials = [];
}

include '../../includes/header.php';
?>

<style>
.material-req-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 1.5rem;
}
.material-req-stats .card {
    border-left: 4px solid #4e73df;
}
.stock-critical { color: #e74a3b; font-weight: 700; }
.stock-warning { color: #f6c23e; font-weight: 700; }
.stock-ok { color: #1cc88a; font-weight: 700; }
</style>

<div class="page-header">
    <h1><i class="fas fa-boxes"></i> <?php echo $page_title; ?></h1>
    <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
        <a href="../procurement/purchase_requests.php?action=add" class="btn btn-primary"><i class="fas fa-plus"></i> Create Purchase Request</a>
        <a href="../procurement/materials.php" class="btn btn-info"><i class="fas fa-cubes"></i> View All Materials</a>
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
        <a href="material_requirements.php" class="btn btn-secondary"><i class="fas fa-redo"></i> Reset</a>
    </form>
</div>

<!-- Quick Stats -->
<div class="material-req-stats">
    <div class="card" style="border-left-color:#e74a3b;margin:0;">
        <div style="font-size:0.75rem;color:#64748b;">Critical Low Stock</div>
        <div style="font-size:1.5rem;font-weight:700;"><?php echo count($low_stock_materials); ?></div>
    </div>
    <div class="card" style="border-left-color:#f6c23e;margin:0;">
        <div style="font-size:0.75rem;color:#64748b;">Total Materials Required</div>
        <div style="font-size:1.5rem;font-weight:700;"><?php echo count($material_requirements); ?></div>
    </div>
    <div class="card" style="border-left-color:#4e73df;margin:0;">
        <div style="font-size:0.75rem;color:#64748b;">Total Required Quantity</div>
        <div style="font-size:1.5rem;font-weight:700;"><?php echo number_format(array_sum(array_column($material_requirements, 'total_required'))); ?></div>
    </div>
    <div class="card" style="border-left-color:#36b9cc;margin:0;">
        <div style="font-size:0.75rem;color:#64748b;">Pending Quantity</div>
        <div style="font-size:1.5rem;font-weight:700;"><?php echo number_format(array_sum(array_column($material_requirements, 'pending_quantity'))); ?></div>
    </div>
</div>

<?php if ($project_id > 0): ?>
<!-- Project-Specific Materials -->
<div class="card">
    <h3 class="section-title"><i class="fas fa-project-diagram"></i> Materials for Selected Project</h3>
    <?php if (empty($project_materials)): ?>
        <div class="empty-state">
            <i class="fas fa-boxes"></i>
            <p>No material requirements found for this project.</p>
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
                        <th>Required</th>
                        <th>Shortage</th>
                        <th>Unit</th>
                        <th>Supplier</th>
                        <th>PR Count</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($project_materials as $material): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($material['material_code']); ?></strong></td>
                        <td><?php echo htmlspecialchars($material['name']); ?></td>
                        <td><?php echo htmlspecialchars($material['category'] ?? 'N/A'); ?></td>
                        <td class="<?php echo $material['current_stock'] <= 0 ? 'stock-critical' : ($material['current_stock'] <= $material['min_stock'] ? 'stock-warning' : 'stock-ok'); ?>"><?php echo number_format($material['current_stock']); ?></td>
                        <td><?php echo number_format($material['required_quantity']); ?></td>
                        <td class="<?php echo ($material['required_quantity'] - $material['current_stock']) > 0 ? 'stock-critical' : 'stock-ok'; ?>"><?php echo number_format(max(0, $material['required_quantity'] - $material['current_stock'])); ?></td>
                        <td><?php echo htmlspecialchars($material['unit']); ?></td>
                        <td><?php echo htmlspecialchars($material['supplier_name'] ?? 'N/A'); ?></td>
                        <td><?php echo $material['pr_count']; ?></td>
                        <td>
                            <a href="../procurement/purchase_requests.php?action=add" class="btn btn-sm btn-primary"><i class="fas fa-plus"></i> Create PR</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- Critical Low Stock Materials -->
<?php if (!empty($low_stock_materials)): ?>
<div class="card">
    <h3 class="section-title"><i class="fas fa-exclamation-triangle"></i> Critical Low Stock (Immediate Action Required)</h3>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Material Code</th>
                    <th>Name</th>
                    <th>Current Stock</th>
                    <th>Min Stock</th>
                    <th>Unit</th>
                    <th>Cost per Unit</th>
                    <th>Supplier</th>
                    <th>Supplier Contact</th>
                    <th>Pending PRs</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($low_stock_materials as $material): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($material['material_code']); ?></strong></td>
                    <td><?php echo htmlspecialchars($material['name']); ?></td>
                    <td class="stock-critical"><?php echo number_format($material['current_stock']); ?></td>
                    <td><?php echo number_format($material['min_stock']); ?></td>
                    <td><?php echo htmlspecialchars($material['unit']); ?></td>
                    <td>₱<?php echo number_format($material['cost_per_unit'], 2); ?></td>
                    <td><?php echo htmlspecialchars($material['supplier_name'] ?? 'N/A'); ?></td>
                    <td><?php echo htmlspecialchars($material['supplier_phone'] ?? 'N/A'); ?></td>
                    <td><?php echo $material['pending_pr_count']; ?></td>
                    <td>
                        <a href="../procurement/purchase_requests.php?action=add" class="btn btn-sm btn-primary"><i class="fas fa-plus"></i> Create PR</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Overall Material Requirements -->
<div class="card">
    <h3 class="section-title"><i class="fas fa-list"></i> Overall Material Requirements</h3>
    <?php if (empty($material_requirements)): ?>
        <div class="empty-state">
            <i class="fas fa-boxes"></i>
            <p>No material requirements found.</p>
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
                        <th>Total Required</th>
                        <th>Pending</th>
                        <th>Shortage</th>
                        <th>Unit</th>
                        <th>Cost per Unit</th>
                        <th>Supplier</th>
                        <th>Projects</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($material_requirements as $material): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($material['material_code']); ?></strong></td>
                        <td><?php echo htmlspecialchars($material['name']); ?></td>
                        <td><?php echo htmlspecialchars($material['category'] ?? 'N/A'); ?></td>
                        <td class="<?php echo $material['current_stock'] <= 0 ? 'stock-critical' : ($material['current_stock'] <= $material['min_stock'] ? 'stock-warning' : 'stock-ok'); ?>"><?php echo number_format($material['current_stock']); ?></td>
                        <td><?php echo number_format($material['total_required']); ?></td>
                        <td><?php echo number_format($material['pending_quantity']); ?></td>
                        <td class="<?php echo ($material['total_required'] - $material['current_stock']) > 0 ? 'stock-warning' : 'stock-ok'; ?>"><?php echo number_format(max(0, $material['total_required'] - $material['current_stock'])); ?></td>
                        <td><?php echo htmlspecialchars($material['unit']); ?></td>
                        <td>₱<?php echo number_format($material['cost_per_unit'], 2); ?></td>
                        <td><?php echo htmlspecialchars($material['supplier_name'] ?? 'N/A'); ?></td>
                        <td><?php echo $material['project_count']; ?></td>
                        <td>
                            <a href="../procurement/purchase_requests.php?action=add" class="btn btn-sm btn-primary"><i class="fas fa-plus"></i> Create PR</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php include '../../includes/footer.php'; ?>
