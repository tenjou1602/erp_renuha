<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';

requireDepartment(['warehouse']);

$page_title = 'Material Requests';
$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);

// Check if material_requests table exists
$table_exists = false;
try {
    $result = $pdo->query("SHOW TABLES LIKE 'material_requests'");
    $table_exists = $result->fetch() !== false;
} catch (PDOException $e) {
    $table_exists = false;
}

// Create table if it doesn't exist
if (!$table_exists) {
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS material_requests (
                id INT AUTO_INCREMENT PRIMARY KEY,
                project_id INT NOT NULL,
                material_id INT NOT NULL,
                quantity INT NOT NULL,
                request_date DATE NOT NULL,
                status ENUM('pending', 'approved', 'rejected', 'partial') DEFAULT 'pending',
                requested_by INT NOT NULL,
                notes TEXT,
                released_quantity INT DEFAULT 0,
                released_by INT,
                released_at DATETIME,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (project_id) REFERENCES projects(id),
                FOREIGN KEY (material_id) REFERENCES materials(id),
                FOREIGN KEY (requested_by) REFERENCES users(id),
                FOREIGN KEY (released_by) REFERENCES users(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $table_exists = true;
    } catch (PDOException $e) {
        $error = userDatabaseError($e, 'Warehouse');
    }
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['approve_request']) && !canWriteDepartmentData('warehouse')) {
        $_SESSION['error'] = 'Administrators have view-only access.';
        header('Location: material_requests.php');
        exit();
    }
    
    if (isset($_POST['approve_request'])) {
        try {
            if (!$table_exists) {
                throw new Exception('Material requests table does not exist.');
            }
            
            $request_id = (int)$_POST['request_id'];
            $release_quantity = (int)$_POST['release_quantity'];
            $notes = trim($_POST['notes'] ?? '');
            
            $pdo->beginTransaction();
            
            // Get request details
            $stmt = $pdo->prepare("SELECT mr.*, m.current_stock, m.name as material_name FROM material_requests mr LEFT JOIN materials m ON mr.material_id = m.id WHERE mr.id = ?");
            $stmt->execute([$request_id]);
            $request = $stmt->fetch();
            
            if (!$request) {
                throw new Exception('Material request not found.');
            }
            
            if ($release_quantity > $request['current_stock']) {
                throw new Exception('Insufficient stock for release.');
            }
            
            if ($release_quantity > $request['quantity']) {
                throw new Exception('Release quantity cannot exceed requested quantity.');
            }
            
            // Update material request
            $stmt = $pdo->prepare("UPDATE material_requests SET status = 'approved', released_quantity = ?, released_by = ?, released_at = NOW(), notes = ? WHERE id = ?");
            $stmt->execute([$release_quantity, $_SESSION['user_id'], $notes, $request_id]);
            
            // Update material stock
            $stmt = $pdo->prepare("UPDATE materials SET current_stock = current_stock - ? WHERE id = ?");
            $stmt->execute([$release_quantity, $request['material_id']]);
            
            // Create stock movement record
            $stmt = $pdo->prepare("INSERT INTO stock_movements (material_id, movement_type, quantity, reference_type, reference_id, notes, created_by) VALUES (?, 'out', ?, 'material_request', ?, ?, ?)");
            $stmt->execute([$request['material_id'], $release_quantity, $request_id, $notes, $_SESSION['user_id']]);
            
            $pdo->commit();
            
            logActivity($_SESSION['user_id'], 'Approved material request', 'Warehouse', "Request: $request_id, Material: {$request['material_name']}, Quantity: $release_quantity");
            $_SESSION['success'] = "Material request approved and stock released successfully!";
            header('Location: material_requests.php');
            exit();
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = $e->getMessage();
        }
    }
}

// Get material requests
$material_requests = [];
$projects = [];
$materials = [];
try {
    if ($table_exists) {
        $material_requests = $pdo->query("
            SELECT mr.*, p.project_code, p.name as project_name, m.material_code, m.name as material_name, m.unit, m.current_stock,
                   u1.full_name as requested_by_name, u2.full_name as released_by_name
            FROM material_requests mr
            LEFT JOIN projects p ON mr.project_id = p.id
            LEFT JOIN materials m ON mr.material_id = m.id
            LEFT JOIN users u1 ON mr.requested_by = u1.id
            LEFT JOIN users u2 ON mr.released_by = u2.id
            ORDER BY mr.request_date DESC, mr.created_at DESC
        ")->fetchAll();
    }
    
    $projects = $pdo->query("SELECT id, project_code, name FROM projects WHERE status IN ('planning', 'ongoing') ORDER BY name")->fetchAll();
    $materials = $pdo->query("SELECT id, material_code, name, unit, current_stock FROM materials WHERE status = 'active' ORDER BY name")->fetchAll();
} catch (PDOException $e) {
    $error = userDatabaseError($e);
}

include '../../includes/header.php';
?>

<style>
.material-requests-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 1.5rem;
}
.material-requests-stats .card {
    border-left: 4px solid #4e73df;
}
</style>

<div class="page-header">
    <h1><i class="fas fa-clipboard-list"></i> <?php echo $page_title; ?></h1>
    <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
        <a href="stock.php?action=out" class="btn btn-primary"><i class="fas fa-minus"></i> Stock-Out</a>
        <a href="project_materials.php" class="btn btn-info"><i class="fas fa-project-diagram"></i> Project Materials</a>
    </div>
</div>

<?php if (isset($error)): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<?php if (isset($_SESSION['success'])): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
<?php endif; ?>

<?php if (isset($_SESSION['error'])): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
<?php endif; ?>

<!-- Quick Stats -->
<div class="material-requests-stats">
    <div class="card" style="border-left-color:#4e73df;margin:0;">
        <div style="font-size:0.75rem;color:#64748b;">Total Requests</div>
        <div style="font-size:1.5rem;font-weight:700;"><?php echo count($material_requests); ?></div>
    </div>
    <div class="card" style="border-left-color:#f6c23e;margin:0;">
        <div style="font-size:0.75rem;color:#64748b;">Pending</div>
        <div style="font-size:1.5rem;font-weight:700;">
            <?php 
            $pending = array_filter($material_requests, function($m) { return $m['status'] === 'pending'; });
            echo count($pending);
            ?>
        </div>
    </div>
    <div class="card" style="border-left-color:#1cc88a;margin:0;">
        <div style="font-size:0.75rem;color:#64748b;">Approved</div>
        <div style="font-size:1.5rem;font-weight:700;">
            <?php 
            $approved = array_filter($material_requests, function($m) { return $m['status'] === 'approved'; });
            echo count($approved);
            ?>
        </div>
    </div>
    <div class="card" style="border-left-color:#e74a3b;margin:0;">
        <div style="font-size:0.75rem;color:#64748b;">Rejected</div>
        <div style="font-size:1.5rem;font-weight:700;">
            <?php 
            $rejected = array_filter($material_requests, function($m) { return $m['status'] === 'rejected'; });
            echo count($rejected);
            ?>
        </div>
    </div>
</div>

<!-- Material Requests List -->
<div class="card">
    <h3 class="section-title"><i class="fas fa-list"></i> Material Requests</h3>
    <?php if (!$table_exists): ?>
        <div class="empty-state">
            <i class="fas fa-exclamation-triangle"></i>
            <p>Material requests table not available. Please check database configuration.</p>
        </div>
    <?php elseif (empty($material_requests)): ?>
        <div class="empty-state">
            <i class="fas fa-clipboard-list"></i>
            <p>No material requests found.</p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Request ID</th>
                        <th>Project</th>
                        <th>Material</th>
                        <th>Requested Qty</th>
                        <th>Released Qty</th>
                        <th>Current Stock</th>
                        <th>Request Date</th>
                        <th>Status</th>
                        <th>Requested By</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($material_requests as $request): ?>
                    <tr>
                        <td><strong>#<?php echo $request['id']; ?></strong></td>
                        <td><?php echo htmlspecialchars($request['project_code'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($request['material_name'] ?? 'N/A'); ?></td>
                        <td><?php echo number_format($request['quantity']); ?> <?php echo htmlspecialchars($request['unit'] ?? ''); ?></td>
                        <td><?php echo number_format($request['released_quantity']); ?> <?php echo htmlspecialchars($request['unit'] ?? ''); ?></td>
                        <td class="<?php echo $request['current_stock'] <= 10 ? 'text-danger' : 'text-success'; ?>"><?php echo number_format($request['current_stock']); ?> <?php echo htmlspecialchars($request['unit'] ?? ''); ?></td>
                        <td><?php echo date('M d, Y', strtotime($request['request_date'])); ?></td>
                        <td><span class="badge badge-<?php echo $request['status']; ?>"><?php echo ucfirst($request['status']); ?></span></td>
                        <td><?php echo htmlspecialchars($request['requested_by_name'] ?? 'N/A'); ?></td>
                        <td>
                            <?php if ($request['status'] === 'pending' && canWriteDepartmentData('warehouse')): ?>
                                <button onclick="showApproveModal(<?php echo $request['id']; ?>, <?php echo $request['quantity']; ?>, <?php echo $request['current_stock']; ?>)" class="btn btn-sm btn-primary"><i class="fas fa-check"></i> Approve</button>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Approve Modal -->
<div id="approveModal" class="modal" style="display:none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Approve Material Request</h3>
            <button onclick="closeApproveModal()" class="btn-close">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="request_id" id="request_id">
            <div class="form-group">
                <label>Release Quantity:</label>
                <input type="number" name="release_quantity" id="release_quantity" min="1" required>
                <small id="stock_info"></small>
            </div>
            <div class="form-group">
                <label>Notes:</label>
                <textarea name="notes" rows="3" placeholder="Optional notes for this release..."></textarea>
            </div>
            <div class="form-actions">
                <button type="submit" name="approve_request" class="btn btn-primary"><i class="fas fa-check"></i> Approve & Release</button>
                <button type="button" onclick="closeApproveModal()" class="btn btn-secondary">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
function showApproveModal(requestId, requestedQty, currentStock) {
    document.getElementById('request_id').value = requestId;
    document.getElementById('release_quantity').value = requestedQty;
    document.getElementById('release_quantity').max = Math.min(requestedQty, currentStock);
    document.getElementById('stock_info').textContent = 'Current stock: ' + currentStock + ', Requested: ' + requestedQty;
    document.getElementById('approveModal').style.display = 'block';
}

function closeApproveModal() {
    document.getElementById('approveModal').style.display = 'none';
}
</script>

<?php include '../../includes/footer.php'; ?>
