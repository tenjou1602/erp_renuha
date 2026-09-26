<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';

requireDepartment(['accounting']);

$page_title = 'Assign Project In-Charge';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_pic'])) {
    if (!canAssignProjectInCharge()) {
        $_SESSION['error'] = 'Administrators have view-only access.';
        header('Location: assign_in_charge.php');
        exit();
    }
    $project_id = (int)($_POST['project_id'] ?? 0);
    $in_charge_id = (int)($_POST['in_charge_id'] ?? 0);
    if ($project_id <= 0 || $in_charge_id <= 0) {
        $_SESSION['error'] = 'Select a project and an Engineering personnel.';
    } else {
        try {
            $check = $pdo->prepare("SELECT id, full_name, department FROM users WHERE id = ? AND status = 'active' AND department = 'engineering'");
            $check->execute([$in_charge_id]);
            $person = $check->fetch();
            if (!$person) {
                $_SESSION['error'] = 'Choose an active Engineering user.';
            } else {
                $stmt = $pdo->prepare("UPDATE projects SET in_charge_id = ? WHERE id = ?");
                $stmt->execute([$in_charge_id, $project_id]);
                logActivity($_SESSION['user_id'], 'Assigned project in-charge', 'Accounting', $person['full_name'] . ' to project ' . $project_id);
                $_SESSION['success'] = 'Project In-Charge assigned. Engineering can now implement the project.';
            }
        } catch (PDOException $e) {
            $_SESSION['error'] = userDatabaseError($e, 'Accounting');
        }
    }
    header('Location: assign_in_charge.php');
    exit();
}

$projects = [];
$engineers = [];
try {
    $projects = $pdo->query("
        SELECT p.id, p.project_code, p.name, p.status, p.estimated_budget, p.in_charge_id, u.full_name AS in_charge_name
        FROM projects p
        LEFT JOIN users u ON p.in_charge_id = u.id
        ORDER BY (p.in_charge_id IS NULL) DESC, p.created_at DESC
    ")->fetchAll();
    $engineers = $pdo->query("SELECT id, full_name, role FROM users WHERE department = 'engineering' AND status = 'active' ORDER BY full_name")->fetchAll();
} catch (PDOException $e) {
    $error = userDatabaseError($e, 'Accounting');
}

include '../../includes/header.php';
?>

<div class="page-header">
    <h1><i class="fas fa-user-tie"></i> Assign Project In-Charge</h1>
    <a href="project_financials.php" class="btn btn-outline">Project Financials</a>
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

<p class="muted-note">Accounting receives the encoded project from Executive Admin, records finances, then assigns an available Engineering / Project In-Charge.</p>

<?php if (canAssignProjectInCharge()): ?>
<div class="card">
    <h3>Assign personnel</h3>
    <form method="POST">
        <div class="form-group">
            <label>Project</label>
            <select name="project_id" required>
                <option value="">Select project</option>
                <?php foreach ($projects as $project): ?>
                    <option value="<?php echo (int)$project['id']; ?>">
                        <?php echo htmlspecialchars($project['project_code'] . ' — ' . $project['name'] . ($project['in_charge_name'] ? ' (now: ' . $project['in_charge_name'] . ')' : ' — unassigned')); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Engineering / Project In-Charge</label>
            <select name="in_charge_id" required>
                <option value="">Select personnel</option>
                <?php foreach ($engineers as $eng): ?>
                    <option value="<?php echo (int)$eng['id']; ?>"><?php echo htmlspecialchars($eng['full_name'] . ' (' . $eng['role'] . ')'); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" name="assign_pic" class="btn btn-primary">Assign</button>
    </form>
</div>
<?php endif; ?>

<div class="card">
    <h3>Projects</h3>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Name</th>
                    <th>Status</th>
                    <th>Budget</th>
                    <th>In-Charge</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($projects)): ?>
                    <tr><td colspan="5" class="table-empty">No encoded projects yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($projects as $project): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars(displayDocumentCode($project['project_code'])); ?></strong></td>
                        <td><?php echo htmlspecialchars($project['name']); ?></td>
                        <td><span class="badge badge-<?php echo htmlspecialchars($project['status']); ?>"><?php echo ucfirst($project['status']); ?></span></td>
                        <td>₱<?php echo number_format((float)$project['estimated_budget'], 2); ?></td>
                        <td><?php echo $project['in_charge_name'] ? htmlspecialchars($project['in_charge_name']) : '<span class="text-danger">Unassigned</span>'; ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
