<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';

requireDepartment(['engineering']);

$page_title = 'Accomplishments';
$completed = [];
$personnel = [];

try {
    $completed = $pdo->query("
        SELECT id, project_code, name, location, end_date, actual_cost, estimated_budget
        FROM projects
        WHERE status = 'completed'
        ORDER BY end_date DESC, updated_at DESC
    ")->fetchAll();

    $personnel = $pdo->query("
        SELECT p.*, pr.project_code, pr.name AS project_name
        FROM personnel p
        LEFT JOIN projects pr ON p.project_id = pr.id
        ORDER BY p.created_at DESC
        LIMIT 50
    ")->fetchAll();
} catch (PDOException $e) {
    $error = userDatabaseError($e);
}

include '../../includes/header.php';
?>

<div class="page-header">
    <h1><i class="fas fa-clipboard-check"></i> Accomplishments</h1>
</div>

<?php if (isset($error)): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<div class="card">
    <h3>Completed projects</h3>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Name</th>
                    <th>Location</th>
                    <th>Ended</th>
                    <th>Budget / Actual</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($completed)): ?>
                    <tr><td colspan="6" class="table-empty">No completed projects yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($completed as $row): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($row['project_code']); ?></strong></td>
                        <td><?php echo htmlspecialchars($row['name']); ?></td>
                        <td><?php echo htmlspecialchars($row['location'] ?? 'N/A'); ?></td>
                        <td><?php echo !empty($row['end_date']) ? date('M d, Y', strtotime($row['end_date'])) : 'N/A'; ?></td>
                        <td>₱<?php echo number_format((float) $row['estimated_budget'], 2); ?> / ₱<?php echo number_format((float) $row['actual_cost'], 2); ?></td>
                        <td><a href="project_details.php?id=<?php echo (int) $row['id']; ?>" class="btn btn-sm btn-info"><i class="fas fa-eye"></i></a></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <h3>Personnel assignments</h3>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Project</th>
                    <th>Name</th>
                    <th>Position</th>
                    <th>Department</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($personnel)): ?>
                    <tr><td colspan="4" class="table-empty">No personnel records yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($personnel as $row): ?>
                    <tr>
                        <td><?php echo htmlspecialchars(($row['project_code'] ?? '') . ' ' . ($row['project_name'] ?? '')); ?></td>
                        <td><?php echo htmlspecialchars($row['name'] ?? $row['full_name'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($row['position'] ?? $row['role'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($row['department'] ?? 'N/A'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
