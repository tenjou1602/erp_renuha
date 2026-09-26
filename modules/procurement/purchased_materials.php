<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';

requireDepartment(['procurement']);

$page_title = 'Purchased Materials';
$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);

// Get purchased materials (materials from delivered purchase orders and stock movements)
$purchased_materials = [];
$material_stats = [];
try {
    // Get materials that have been received via stock movements or are part of delivered purchase orders
    $query = "
        SELECT 
            m.id,
            m.material_code,
            m.name,
            m.unit,
            m.current_stock,
            m.cost_per_unit,
            m.category,
            s.name as supplier_name,
            COALESCE(SUM(CASE WHEN sm.movement_type = 'in' THEN sm.quantity ELSE 0 END), 0) as total_received,
            COALESCE(SUM(CASE WHEN sm.movement_type = 'out' THEN sm.quantity ELSE 0 END), 0) as total_issued,
            COUNT(DISTINCT po.id) as purchase_count
        FROM materials m
        LEFT JOIN suppliers s ON m.supplier_id = s.id
        LEFT JOIN stock_movements sm ON m.id = sm.material_id
        LEFT JOIN purchase_request_items pri ON m.id = pri.material_id
        LEFT JOIN purchase_requests pr ON pri.purchase_request_id = pr.id
        LEFT JOIN purchase_orders po ON pr.id = po.purchase_request_id AND po.status = 'delivered'
        WHERE m.status = 'active'
        GROUP BY m.id, m.material_code, m.name, m.unit, m.current_stock, m.cost_per_unit, m.category, s.name
        HAVING total_received > 0 OR purchase_count > 0
        ORDER BY total_received DESC, m.name ASC
    ";
    $purchased_materials = $pdo->query($query)->fetchAll();
    
    // Get overall statistics
    $material_stats['total_materials'] = count($purchased_materials);
    $material_stats['total_received'] = array_sum(array_column($purchased_materials, 'total_received'));
    $material_stats['total_issued'] = array_sum(array_column($purchased_materials, 'total_issued'));
    $material_stats['total_value'] = array_sum(array_map(function($m) { return ($m['current_stock'] ?? 0) * ($m['cost_per_unit'] ?? 0); }, $purchased_materials));
    
} catch (PDOException $e) {
    $error = userDatabaseError($e);
    $purchased_materials = [];
}

// Get single material details for view
$material_details = null;
$material_history = [];
if ($action === 'view' && $id > 0) {
    try {
        $stmt = $pdo->prepare("
            SELECT m.*, s.name as supplier_name, s.contact_person, s.phone, s.email
            FROM materials m
            LEFT JOIN suppliers s ON m.supplier_id = s.id
            WHERE m.id = ?
        ");
        $stmt->execute([$id]);
        $material_details = $stmt->fetch();
        
        if ($material_details) {
            // Get stock movement history
            $history_stmt = $pdo->prepare("
                SELECT sm.*, u.full_name as user_name
                FROM stock_movements sm
                LEFT JOIN users u ON sm.created_by = u.id
                WHERE sm.material_id = ?
                ORDER BY sm.created_at DESC
                LIMIT 20
            ");
            $history_stmt->execute([$id]);
            $material_history = $history_stmt->fetchAll();
        }
    } catch (PDOException $e) {
        $error = userDatabaseError($e);
    }
}

include '../../includes/header.php';
?>

<style>
/* Purchased Materials specific styles */
.procurement-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 1.5rem;
}
.procurement-stats .card {
    border-left: 4px solid #4e73df;
}
.material-value {
    font-weight: 700;
    color: #1cc88a;
}
.stock-warning {
    color: #e74a3b;
    font-weight: 600;
}
</style>

<div class="page-header">
    <h1><i class="fas fa-boxes"></i> <?php echo $page_title; ?></h1>
    <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
        <a href="materials.php" class="btn btn-info"><i class="fas fa-cubes"></i> View All Materials</a>
        <a href="purchase_history.php" class="btn btn-primary"><i class="fas fa-history"></i> Purchase History</a>
    </div>
</div>

<?php if (isset($error)): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<!-- Statistics -->
<div class="procurement-stats">
    <div class="card" style="border-left-color:#4e73df;margin:0;">
        <div style="font-size:0.75rem;color:#64748b;">Total Purchased Materials</div>
        <div style="font-size:1.5rem;font-weight:700;"><?php echo number_format($material_stats['total_materials'] ?? 0); ?></div>
    </div>
    <div class="card" style="border-left-color:#1cc88a;margin:0;">
        <div style="font-size:0.75rem;color:#64748b;">Total Received</div>
        <div style="font-size:1.5rem;font-weight:700;"><?php echo number_format($material_stats['total_received'] ?? 0); ?></div>
    </div>
    <div class="card" style="border-left-color:#f6c23e;margin:0;">
        <div style="font-size:0.75rem;color:#64748b;">Total Issued</div>
        <div style="font-size:1.5rem;font-weight:700;"><?php echo number_format($material_stats['total_issued'] ?? 0); ?></div>
    </div>
    <div class="card" style="border-left-color:#36b9cc;margin:0;">
        <div style="font-size:0.75rem;color:#64748b;">Total Inventory Value</div>
        <div style="font-size:1.5rem;font-weight:700;">₱<?php echo number_format($material_stats['total_value'] ?? 0, 2); ?></div>
    </div>
</div>

<?php if ($action === 'view' && $material_details): ?>
<!-- Material Details View -->
<div class="card">
    <h3>Material Details</h3>
    <div class="view-details">
        <div class="detail-row"><span>Material Code:</span> <strong><?php echo htmlspecialchars($material_details['material_code']); ?></strong></div>
        <div class="detail-row"><span>Name:</span> <strong><?php echo htmlspecialchars($material_details['name']); ?></strong></div>
        <div class="detail-row"><span>Category:</span> <?php echo htmlspecialchars($material_details['category'] ?? 'N/A'); ?></div>
        <div class="detail-row"><span>Unit:</span> <?php echo htmlspecialchars($material_details['unit']); ?></div>
        <div class="detail-row"><span>Cost per Unit:</span> ₱<?php echo number_format($material_details['cost_per_unit'], 2); ?></div>
        <div class="detail-row"><span>Current Stock:</span> <strong class="<?php echo $material_details['current_stock'] <= $material_details['min_stock'] ? 'stock-warning' : ''; ?>"><?php echo number_format($material_details['current_stock']); ?> <?php echo htmlspecialchars($material_details['unit']); ?></strong></div>
        <div class="detail-row"><span>Min Stock:</span> <?php echo number_format($material_details['min_stock']); ?></div>
        <div class="detail-row"><span>Max Stock:</span> <?php echo number_format($material_details['max_stock']); ?></div>
        <div class="detail-row"><span>Supplier:</span> <?php echo htmlspecialchars($material_details['supplier_name'] ?? 'N/A'); ?></div>
        <div class="detail-row"><span>Supplier Contact:</span> <?php echo htmlspecialchars($material_details['contact_person'] ?? 'N/A'); ?></div>
        <div class="detail-row"><span>Supplier Phone:</span> <?php echo htmlspecialchars($material_details['phone'] ?? 'N/A'); ?></div>
        <div class="detail-row"><span>Supplier Email:</span> <?php echo htmlspecialchars($material_details['email'] ?? 'N/A'); ?></div>
        <div class="detail-row"><span>Status:</span> <span class="badge badge-<?php echo $material_details['status'] == 'active' ? 'success' : 'secondary'; ?>"><?php echo ucfirst($material_details['status']); ?></span></div>
        <div class="detail-row"><span>Inventory Value:</span> <strong class="material-value">₱<?php echo number_format(($material_details['current_stock'] ?? 0) * ($material_details['cost_per_unit'] ?? 0), 2); ?></strong></div>
    </div>
    
    <h4 style="margin-top: 1.5rem;">Stock Movement History</h4>
    <?php if (empty($material_history)): ?>
        <div class="empty-state">
            <i class="fas fa-exchange-alt"></i>
            <p>No stock movement history found.</p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Quantity</th>
                        <th>Reason</th>
                        <th>User</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($material_history as $movement): ?>
                    <tr>
                        <td><?php echo date('M d, Y h:i A', strtotime($movement['created_at'])); ?></td>
                        <td>
                            <span class="badge badge-<?php echo $movement['movement_type'] == 'in' ? 'success' : ($movement['movement_type'] == 'out' ? 'danger' : 'warning'); ?>">
                                <?php echo strtoupper($movement['movement_type']); ?>
                            </span>
                        </td>
                        <td><?php echo number_format($movement['quantity']); ?></td>
                        <td><?php echo htmlspecialchars($movement['reason'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($movement['user_name'] ?? 'N/A'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
    
    <div style="margin-top:1.5rem;">
        <a href="purchased_materials.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back to List</a>
    </div>
</div>

<?php else: ?>
<!-- List View -->
<div class="card">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem; flex-wrap:wrap; gap:0.5rem;">
        <h3 style="margin:0;"><i class="fas fa-list"></i> Purchased Materials (<?php echo count($purchased_materials); ?>)</h3>
        <div style="display:flex; gap:0.5rem;">
            <input type="text" id="searchMaterial" placeholder="Search materials..." style="padding:0.4rem 0.8rem; border:1px solid var(--border-color); border-radius:6px; font-size:0.85rem;">
            <select id="filterCategory" style="padding:0.4rem 0.8rem; border:1px solid var(--border-color); border-radius:6px; font-size:0.85rem;">
                <option value="">All Categories</option>
                <?php 
                $categories = array_unique(array_column($purchased_materials, 'category'));
                foreach ($categories as $cat): 
                    if (!empty($cat)):
                ?>
                    <option value="<?php echo htmlspecialchars($cat); ?>"><?php echo htmlspecialchars($cat); ?></option>
                <?php 
                    endif;
                endforeach; 
                ?>
            </select>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table" id="materialTable">
            <thead>
                <tr>
                    <th onclick="sortTable('materialTable', 0)" style="cursor:pointer;">Material Code <i class="fas fa-sort"></i></th>
                    <th onclick="sortTable('materialTable', 1)" style="cursor:pointer;">Name <i class="fas fa-sort"></i></th>
                    <th onclick="sortTable('materialTable', 2)" style="cursor:pointer;">Category <i class="fas fa-sort"></i></th>
                    <th onclick="sortTable('materialTable', 3)" style="cursor:pointer;">Supplier <i class="fas fa-sort"></i></th>
                    <th onclick="sortTable('materialTable', 4)" style="cursor:pointer;">Current Stock <i class="fas fa-sort"></i></th>
                    <th onclick="sortTable('materialTable', 5)" style="cursor:pointer;">Total Received <i class="fas fa-sort"></i></th>
                    <th onclick="sortTable('materialTable', 6)" style="cursor:pointer;">Total Issued <i class="fas fa-sort"></i></th>
                    <th onclick="sortTable('materialTable', 7)" style="cursor:pointer;">Unit Cost <i class="fas fa-sort"></i></th>
                    <th onclick="sortTable('materialTable', 8)" style="cursor:pointer;">Inventory Value <i class="fas fa-sort"></i></th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($purchased_materials)): ?>
                    <tr>
                        <td colspan="10" style="text-align:center;color:#94a3b8;padding:2rem;">
                            <i class="fas fa-boxes" style="font-size:2rem;display:block;margin-bottom:0.5rem;opacity:0.3;"></i>
                            No purchased materials found.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($purchased_materials as $material): ?>
                    <tr data-category="<?php echo htmlspecialchars($material['category'] ?? ''); ?>">
                        <td><strong><?php echo htmlspecialchars($material['material_code']); ?></strong></td>
                        <td><?php echo htmlspecialchars($material['name']); ?></td>
                        <td><?php echo htmlspecialchars($material['category'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($material['supplier_name'] ?? 'N/A'); ?></td>
                        <td class="<?php echo $material['current_stock'] <= 0 ? 'stock-warning' : ''; ?>">
                            <strong><?php echo number_format($material['current_stock']); ?></strong> <?php echo htmlspecialchars($material['unit']); ?>
                        </td>
                        <td><?php echo number_format($material['total_received']); ?></td>
                        <td><?php echo number_format($material['total_issued']); ?></td>
                        <td>₱<?php echo number_format($material['cost_per_unit'], 2); ?></td>
                        <td class="material-value">₱<?php echo number_format(($material['current_stock'] ?? 0) * ($material['cost_per_unit'] ?? 0), 2); ?></td>
                        <td>
                            <a href="?action=view&id=<?php echo $material['id']; ?>" class="btn btn-sm btn-info" title="View Details"><i class="fas fa-eye"></i></a>
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
document.getElementById('searchMaterial')?.addEventListener('keyup', function() {
    const searchTerm = this.value.toLowerCase();
    const categoryFilter = document.getElementById('filterCategory').value;
    const rows = document.querySelectorAll('#materialTable tbody tr');
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        const category = row.getAttribute('data-category') || '';
        const matchesSearch = text.includes(searchTerm);
        const matchesCategory = categoryFilter === '' || category === categoryFilter;
        row.style.display = matchesSearch && matchesCategory ? '' : 'none';
    });
});

// Category filter
document.getElementById('filterCategory')?.addEventListener('change', function() {
    const categoryFilter = this.value;
    const searchTerm = document.getElementById('searchMaterial').value.toLowerCase();
    const rows = document.querySelectorAll('#materialTable tbody tr');
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        const category = row.getAttribute('data-category') || '';
        const matchesSearch = text.includes(searchTerm);
        const matchesCategory = categoryFilter === '' || category === categoryFilter;
        row.style.display = matchesSearch && matchesCategory ? '' : 'none';
    });
});
</script>
<?php endif; ?>

<?php include '../../includes/footer.php'; ?>
