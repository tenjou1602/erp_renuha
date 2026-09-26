<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';

requireDepartment(['engineering']);

$page_title = 'Projects';
$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ((isset($_POST['add_project']) || isset($_POST['update_project'])) && !canWriteDepartmentData('projects')) {
        $_SESSION['error'] = 'Administrators have view-only access to projects.';
        header('Location: projects.php');
        exit();
    }
    if (isset($_POST['add_project']) || isset($_POST['update_project'])) {
        $project_code = $_POST['project_code'] ?? generateProjectCode();
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $location = trim($_POST['location'] ?? '');
        $client = trim($_POST['client'] ?? '');
        $start_date = $_POST['start_date'] ?? null;
        $end_date = $_POST['end_date'] ?? null;
        $status = $_POST['status'] ?? 'planning';
        $estimated_budget = (float)($_POST['estimated_budget'] ?? 0);
        
        $errors = [];
        if (empty($name)) $errors[] = 'Project name is required.';
        if ($estimated_budget <= 0) $errors[] = 'Estimated budget must be greater than 0.';
        
        if (empty($errors)) {
            try {
                if (isset($_POST['add_project'])) {
                    $stmt = $pdo->prepare("INSERT INTO projects (project_code, name, description, location, client, start_date, end_date, status, estimated_budget, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$project_code, $name, $description, $location, $client, $start_date, $end_date, $status, $estimated_budget, $_SESSION['user_id']]);
                    
                    logActivity($_SESSION['user_id'], 'Created project', 'Projects', "Project: $project_code");
                    $_SESSION['success'] = "Project created successfully!";
                    header('Location: projects.php');
                    exit();
                } else {
                    $stmt = $pdo->prepare("UPDATE projects SET name = ?, description = ?, location = ?, client = ?, start_date = ?, end_date = ?, status = ?, estimated_budget = ? WHERE id = ?");
                    $stmt->execute([$name, $description, $location, $client, $start_date, $end_date, $status, $estimated_budget, $id]);
                    
                    logActivity($_SESSION['user_id'], 'Updated project', 'Projects', "Project: $project_code");
                    $_SESSION['success'] = "Project updated successfully!";
                    header('Location: projects.php');
                    exit();
                }
            } catch (PDOException $e) {
                $error = userDatabaseError($e);
            }
        }
    }
}

// Handle Delete
if (isset($_GET['delete_id']) && $action === 'delete') {
    if (!canDelete('projects')) {
        $_SESSION['error'] = 'Administrators cannot delete project records.';
        header('Location: projects.php');
        exit();
    }
    $delete_id = (int)$_GET['delete_id'];
    try {
        // Check if project has related records
        $check = $pdo->prepare("SELECT COUNT(*) FROM quotations WHERE project_id = ?");
        $check->execute([$delete_id]);
        $quotation_count = $check->fetchColumn();
        
        if ($quotation_count > 0) {
            $_SESSION['error'] = "Cannot delete project. It has $quotation_count quotation(s) associated with it.";
        } else {
            $stmt = $pdo->prepare("DELETE FROM projects WHERE id = ?");
            $stmt->execute([$delete_id]);
            $_SESSION['success'] = "Project deleted successfully!";
        }
        header('Location: projects.php');
        exit();
    } catch (PDOException $e) {
        $error = userDatabaseError($e);
    }
}

// Get projects - FIXED: Removed archived column reference
$projects = [];
try {
    $query = "SELECT * FROM projects ORDER BY created_at DESC";
    $projects = $pdo->query($query)->fetchAll();
} catch (PDOException $e) {
    $error = userDatabaseError($e);
}

// Get single project
$project_details = null;
if ($action === 'edit' || $action === 'view') {
    $stmt = $pdo->prepare("SELECT * FROM projects WHERE id = ?");
    $stmt->execute([$id]);
    $project_details = $stmt->fetch();
    
    if (!$project_details) {
        header('Location: projects.php');
        exit();
    }
}

if (($action === 'add' || $action === 'edit') && !canWriteDepartmentData('projects')) {
    $_SESSION['error'] = 'Administrators have view-only access to projects.';
    header('Location: projects.php' . ($id ? '?action=view&id=' . $id : ''));
    exit();
}

include '../../includes/header.php';
?>

<div class="page-header">
    <h1><i class="fas fa-building"></i> <?php echo $page_title; ?></h1>
    <?php if ($action === 'list'): ?>
        <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
            <?php if (canWriteDepartmentData('projects')): ?>
            <a href="?action=add" class="btn btn-primary"><i class="fas fa-plus"></i> New Project</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php if (isset($_SESSION['success'])): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
<?php endif; ?>
<?php if (isset($_SESSION['error'])): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
<?php endif; ?>
<?php if (isset($error)): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<?php if ($action === 'add' || $action === 'edit'): ?>
<!-- Add/Edit Form -->
<div class="card">
    <h3><?php echo $action === 'add' ? 'Create New' : 'Edit'; ?> Project</h3>
    <form method="POST">
        <?php if ($action === 'edit'): ?>
            <input type="hidden" name="update_project" value="1">
            <input type="hidden" name="project_code" value="<?php echo htmlspecialchars($project_details['project_code']); ?>">
        <?php else: ?>
            <input type="hidden" name="add_project" value="1">
        <?php endif; ?>
        
        <div class="form-row">
            <div class="form-group">
                <label>Project Code</label>
                <input type="text" value="<?php echo $action === 'edit' ? htmlspecialchars($project_details['project_code']) : generateProjectCode(); ?>" disabled style="background:#f1f5f9;">
                <?php if ($action === 'add'): ?>
                    <input type="hidden" name="project_code" value="<?php echo generateProjectCode(); ?>">
                <?php endif; ?>
            </div>
            <div class="form-group">
                <label class="required">Project Name</label>
                <input type="text" name="name" required value="<?php echo htmlspecialchars($project_details['name'] ?? ''); ?>" placeholder="Enter project name">
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label>Location</label>
                <input type="text" name="location" value="<?php echo htmlspecialchars($project_details['location'] ?? ''); ?>" placeholder="Project location">
            </div>
            <div class="form-group">
                <label>Client</label>
                <input type="text" name="client" value="<?php echo htmlspecialchars($project_details['client'] ?? ''); ?>" placeholder="Client name">
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label>Start Date</label>
                <input type="date" name="start_date" value="<?php echo $project_details['start_date'] ?? ''; ?>">
            </div>
            <div class="form-group">
                <label>End Date</label>
                <input type="date" name="end_date" value="<?php echo $project_details['end_date'] ?? ''; ?>">
            </div>
            <div class="form-group">
                <label>Status</label>
                <select name="status">
                    <option value="planning" <?php echo ($project_details['status'] ?? '') == 'planning' ? 'selected' : ''; ?>>Planning</option>
                    <option value="ongoing" <?php echo ($project_details['status'] ?? '') == 'ongoing' ? 'selected' : ''; ?>>Ongoing</option>
                    <option value="completed" <?php echo ($project_details['status'] ?? '') == 'completed' ? 'selected' : ''; ?>>Completed</option>
                    <option value="on_hold" <?php echo ($project_details['status'] ?? '') == 'on_hold' ? 'selected' : ''; ?>>On Hold</option>
                </select>
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label class="required">Estimated Budget (₱)</label>
                <input type="number" step="0.01" name="estimated_budget" required value="<?php echo $project_details['estimated_budget'] ?? 0; ?>" min="0" placeholder="0.00">
            </div>
        </div>
        
        <div class="form-group">
            <label>Description</label>
            <textarea name="description" rows="3" placeholder="Project description"><?php echo htmlspecialchars($project_details['description'] ?? ''); ?></textarea>
        </div>
        
        <div style="margin-top:1.5rem;">
            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> <?php echo $action === 'add' ? 'Create' : 'Update'; ?> Project</button>
            <a href="projects.php" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

<?php elseif ($action === 'view' && $project_details): ?>
<!-- View Project -->
<div class="card">
    <h3>Project Details</h3>
    <div class="view-details">
        <div class="detail-row"><span>Project Code:</span> <strong><?php echo htmlspecialchars($project_details['project_code']); ?></strong></div>
        <div class="detail-row"><span>Name:</span> <?php echo htmlspecialchars($project_details['name']); ?></div>
        <div class="detail-row"><span>Location:</span> <?php echo htmlspecialchars($project_details['location'] ?? 'N/A'); ?></div>
        <div class="detail-row"><span>Client:</span> <?php echo htmlspecialchars($project_details['client'] ?? 'N/A'); ?></div>
        <div class="detail-row"><span>Status:</span> <span class="badge badge-<?php echo $project_details['status']; ?>"><?php echo ucfirst($project_details['status']); ?></span></div>
        <div class="detail-row"><span>Start Date:</span> <?php echo $project_details['start_date'] ? date('M d, Y', strtotime($project_details['start_date'])) : 'N/A'; ?></div>
        <div class="detail-row"><span>End Date:</span> <?php echo $project_details['end_date'] ? date('M d, Y', strtotime($project_details['end_date'])) : 'N/A'; ?></div>
        <div class="detail-row"><span>Estimated Budget:</span> <strong>₱<?php echo number_format($project_details['estimated_budget'], 2); ?></strong></div>
        <div class="detail-row"><span>Actual Cost:</span> ₱<?php echo number_format($project_details['actual_cost'] ?? 0, 2); ?></div>
        <?php if ($project_details['description']): ?>
            <div class="detail-row"><span>Description:</span> <?php echo nl2br(htmlspecialchars($project_details['description'])); ?></div>
        <?php endif; ?>
        <div class="detail-row"><span>Created:</span> <?php echo date('M d, Y h:i A', strtotime($project_details['created_at'])); ?></div>
        <?php if ($project_details['updated_at'] && $project_details['updated_at'] != $project_details['created_at']): ?>
            <div class="detail-row"><span>Updated:</span> <?php echo date('M d, Y h:i A', strtotime($project_details['updated_at'])); ?></div>
        <?php endif; ?>
    </div>
    <div style="margin-top:1.5rem; display:flex; gap:0.5rem; flex-wrap:wrap;">
        <?php if (canWriteDepartmentData('projects')): ?>
        <a href="?action=edit&id=<?php echo $project_details['id']; ?>" class="btn btn-warning"><i class="fas fa-edit"></i> Edit</a>
        <?php endif; ?>
        <a href="project_pdf.php?id=<?php echo (int)$project_details['id']; ?>" class="btn btn-outline"><i class="fas fa-download"></i> Download PDF</a>
        <a href="project_details.php?id=<?php echo $project_details['id']; ?>" class="btn btn-info"><i class="fas fa-chart-bar"></i> Full Details</a>
        <?php if (canDelete('projects')): ?>
        <a href="?action=delete&delete_id=<?php echo $project_details['id']; ?>" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this project? This action cannot be undone.')"><i class="fas fa-trash"></i> Delete</a>
        <?php endif; ?>
        <a href="projects.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back</a>
    </div>
</div>

<?php else: ?>
<!-- List View -->
<div class="card">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem; flex-wrap:wrap; gap:0.5rem;">
        <h3 style="margin:0;"><i class="fas fa-list"></i> All Projects (<?php echo count($projects); ?>)</h3>
        <div style="display:flex; gap:0.5rem;">
            <input type="text" id="searchProject" placeholder="Search projects..." style="padding:0.4rem 0.8rem; border:1px solid var(--border-color); border-radius:6px; font-size:0.85rem;">
        </div>
    </div>
    <div class="table-responsive">
        <table class="table" id="projectTable">
            <thead>
                <tr>
                    <th onclick="sortTable('projectTable', 0)" style="cursor:pointer;">Code <i class="fas fa-sort"></i></th>
                    <th onclick="sortTable('projectTable', 1)" style="cursor:pointer;">Name <i class="fas fa-sort"></i></th>
                    <th onclick="sortTable('projectTable', 2)" style="cursor:pointer;">Location <i class="fas fa-sort"></i></th>
                    <th onclick="sortTable('projectTable', 3)" style="cursor:pointer;">Status <i class="fas fa-sort"></i></th>
                    <th onclick="sortTable('projectTable', 4)" style="cursor:pointer;">Budget <i class="fas fa-sort"></i></th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($projects)): ?>
                    <tr>
                        <td colspan="6" style="text-align:center;color:#94a3b8;padding:2rem;">
                            <i class="fas fa-building" style="font-size:2rem;display:block;margin-bottom:0.5rem;opacity:0.3;"></i>
                            No projects found. Click "New Project" to create one.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($projects as $project): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars(displayDocumentCode($project['project_code'])); ?></strong></td>
                        <td><?php echo htmlspecialchars($project['name']); ?></td>
                        <td><?php echo htmlspecialchars($project['location'] ?? 'N/A'); ?></td>
                        <td><span class="badge badge-<?php echo $project['status']; ?>"><?php echo ucfirst($project['status']); ?></span></td>
                        <td>₱<?php echo number_format($project['estimated_budget'], 2); ?></td>
                        <td>
                            <div style="display:flex; gap:0.3rem; flex-wrap:wrap;">
                                <a href="?action=view&id=<?php echo $project['id']; ?>" class="btn btn-sm btn-info" title="View"><i class="fas fa-eye"></i></a>
                                <?php if (canWriteDepartmentData('projects')): ?>
                                <a href="?action=edit&id=<?php echo $project['id']; ?>" class="btn btn-sm btn-warning" title="Edit"><i class="fas fa-edit"></i></a>
                                <?php endif; ?>
                                <a href="project_details.php?id=<?php echo $project['id']; ?>" class="btn btn-sm btn-success" title="Details"><i class="fas fa-chart-bar"></i></a>
                                <?php if (canDelete('projects')): ?>
                                <a href="?action=delete&delete_id=<?php echo $project['id']; ?>" class="btn btn-sm btn-danger" title="Delete" onclick="return confirm('Delete this project?')"><i class="fas fa-trash"></i></a>
                                <?php endif; ?>
                            </div>
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
document.getElementById('searchProject')?.addEventListener('keyup', function() {
    const searchTerm = this.value.toLowerCase();
    const rows = document.querySelectorAll('#projectTable tbody tr');
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(searchTerm) ? '' : 'none';
    });
});
</script>
<?php endif; ?>

<?php include '../../includes/footer.php'; ?>