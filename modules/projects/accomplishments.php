<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';

requireDepartment(['engineering']);

$page_title = 'Accomplishments';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_accomplishment'])) {
    if (!canWriteDepartmentData('projects')) {
        $_SESSION['error'] = 'Administrators have view-only access.';
        header('Location: accomplishments.php');
        exit();
    }
    $project_id = (int)($_POST['project_id'] ?? 0);
    $description = trim($_POST['description'] ?? '');
    $pct = (float)($_POST['completion_percentage'] ?? 0);
    if ($project_id <= 0 || $description === '') {
        $_SESSION['error'] = 'Project and description are required.';
    } elseif ($pct < 0 || $pct > 100) {
        $_SESSION['error'] = 'Completion must be between 0 and 100.';
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO accomplishments (project_id, description, completion_percentage, created_by) VALUES (?, ?, ?, ?)");
            $stmt->execute([$project_id, $description, $pct, $_SESSION['user_id']]);
            logActivity($_SESSION['user_id'], 'Added accomplishment', 'Projects', "Project ID: $project_id");
            $_SESSION['success'] = 'Accomplishment recorded.';
            header('Location: accomplishments.php');
            exit();
        } catch (PDOException $e) {
            $error = userDatabaseError($e, 'Projects');
        }
    }
}

if (isset($_GET['delete_id'])) {
    if (!canDelete('projects')) {
        $_SESSION['error'] = 'You cannot delete accomplishments.';
        header('Location: accomplishments.php');
        exit();
    }
    try {
        $stmt = $pdo->prepare("DELETE FROM accomplishments WHERE id = ?");
        $stmt->execute([(int)$_GET['delete_id']]);
        logActivity($_SESSION['user_id'], 'Deleted accomplishment', 'Projects', 'ID: ' . (int)$_GET['delete_id']);
        $_SESSION['success'] = 'Accomplishment deleted.';
    } catch (PDOException $e) {
        $_SESSION['error'] = userDatabaseError($e, 'Projects');
    }
    header('Location: accomplishments.php');
    exit();
}

$projects = [];
$records = [];
try {
    $projects = $pdo->query("SELECT id, project_code, name, status FROM projects ORDER BY created_at DESC")->fetchAll();
    $records = $pdo->query("
        SELECT a.*, p.project_code, p.name AS project_name, u.full_name AS created_by_name
        FROM accomplishments a
        LEFT JOIN projects p ON a.project_id = p.id
        LEFT JOIN users u ON a.created_by = u.id
        ORDER BY a.created_at DESC
    ")->fetchAll();
} catch (PDOException $e) {
    $error = userDatabaseError($e, 'Projects');
}

include '../../includes/header.php';
?>

<div class="page-header">
    <h1><i class="fas fa-clipboard-check"></i> Accomplishments</h1>
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

<?php if (canWriteDepartmentData('projects')): ?>
<div class="card">
    <h3><i class="fas fa-plus"></i> Record accomplishment</h3>
    <form method="POST">
        <div class="form-group">
            <label>Project</label>
            <select name="project_id" required>
                <option value="">Select project</option>
                <?php foreach ($projects as $project): ?>
                    <option value="<?php echo (int) $project['id']; ?>"><?php echo htmlspecialchars($project['project_code'] . ' - ' . $project['name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Description</label>
            <textarea name="description" rows="3" required></textarea>
        </div>
        <div class="form-group">
            <label>Completion %</label>
            <input type="number" name="completion_percentage" min="0" max="100" step="0.1" value="0" required>
        </div>
        <button type="submit" name="add_accomplishment" class="btn btn-primary">Save</button>
    </form>
</div>
<?php endif; ?>

<div class="card">
    <h3>Project accomplishments</h3>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Project</th>
                    <th>Description</th>
                    <th>Completion</th>
                    <th>Recorded by</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($records)): ?>
                    <tr><td colspan="6" class="table-empty">No accomplishments yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($records as $row): ?>
                    <tr>
                        <td><?php echo date('M d, Y', strtotime($row['created_at'])); ?></td>
                        <td><strong><?php echo htmlspecialchars(displayDocumentCode($row['project_code'] ?? 'N/A')); ?></strong><br><?php echo htmlspecialchars($row['project_name'] ?? ''); ?></td>
                        <td><?php echo nl2br(htmlspecialchars($row['description'])); ?></td>
                        <td><?php echo number_format((float)$row['completion_percentage'], 1); ?>%</td>
                        <td><?php echo htmlspecialchars($row['created_by_name'] ?? 'N/A'); ?></td>
                        <td>
                            <a href="project_details.php?id=<?php echo (int)$row['project_id']; ?>" class="btn btn-sm btn-info"><i class="fas fa-eye"></i></a>
                            <?php if (canDelete('projects')): ?>
                            <a href="accomplishments.php?delete_id=<?php echo (int)$row['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this accomplishment?');"><i class="fas fa-trash"></i></a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
