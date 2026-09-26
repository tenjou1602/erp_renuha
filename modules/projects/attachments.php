<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';

requireDepartment(['engineering']);

$page_title = 'Project Attachments';
$projects = [];

try {
    $projects = $pdo->query("SELECT id, project_code, name, status FROM projects ORDER BY created_at DESC")->fetchAll();
} catch (PDOException $e) {
    $error = userDatabaseError($e);
}

include '../../includes/header.php';
?>

<div class="page-header">
    <h1><i class="fas fa-paperclip"></i> Attachments</h1>
</div>

<?php if (isset($error)): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<div class="card">
    <p class="muted-note">Download the project PDF report for each job. File uploads can be added here later without changing department access.</p>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Name</th>
                    <th>Status</th>
                    <th>Documents</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($projects)): ?>
                    <tr><td colspan="4" class="table-empty">No projects found.</td></tr>
                <?php else: ?>
                    <?php foreach ($projects as $row): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($row['project_code']); ?></strong></td>
                        <td><?php echo htmlspecialchars($row['name']); ?></td>
                        <td><span class="badge badge-<?php echo htmlspecialchars($row['status']); ?>"><?php echo ucfirst(str_replace('_', ' ', $row['status'])); ?></span></td>
                        <td>
                            <a href="project_pdf.php?id=<?php echo (int) $row['id']; ?>" class="btn btn-sm btn-outline"><i class="fas fa-file-pdf"></i> PDF</a>
                            <a href="project_details.php?id=<?php echo (int) $row['id']; ?>" class="btn btn-sm btn-info"><i class="fas fa-folder-open"></i> Details</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
