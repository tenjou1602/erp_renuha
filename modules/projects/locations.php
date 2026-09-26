<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';

requireDepartment(['engineering']);

$page_title = 'Project Locations';
$locations = [];

try {
    $locations = $pdo->query("
        SELECT
            COALESCE(NULLIF(TRIM(location), ''), 'Unspecified') AS location_name,
            COUNT(*) AS project_count,
            SUM(CASE WHEN status = 'ongoing' THEN 1 ELSE 0 END) AS ongoing_count,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed_count
        FROM projects
        GROUP BY COALESCE(NULLIF(TRIM(location), ''), 'Unspecified')
        ORDER BY location_name
    ")->fetchAll();

    $projects_by_location = $pdo->query("
        SELECT id, project_code, name, location, status
        FROM projects
        ORDER BY location IS NULL, location, name
    ")->fetchAll();
} catch (PDOException $e) {
    $error = userDatabaseError($e);
    $projects_by_location = [];
}

include '../../includes/header.php';
?>

<div class="page-header">
    <h1><i class="fas fa-map-marker-alt"></i> Locations</h1>
</div>

<?php if (isset($error)): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<div class="card">
    <h3>Sites</h3>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Location</th>
                    <th>Projects</th>
                    <th>Ongoing</th>
                    <th>Completed</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($locations)): ?>
                    <tr><td colspan="4" class="table-empty">No locations recorded yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($locations as $row): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($row['location_name']); ?></strong></td>
                        <td><?php echo (int) $row['project_count']; ?></td>
                        <td><?php echo (int) $row['ongoing_count']; ?></td>
                        <td><?php echo (int) $row['completed_count']; ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <h3>Projects by location</h3>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Name</th>
                    <th>Location</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($projects_by_location)): ?>
                    <tr><td colspan="5" class="table-empty">No projects found.</td></tr>
                <?php else: ?>
                    <?php foreach ($projects_by_location as $row): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($row['project_code']); ?></strong></td>
                        <td><?php echo htmlspecialchars($row['name']); ?></td>
                        <td><?php echo htmlspecialchars($row['location'] ?: 'Unspecified'); ?></td>
                        <td><span class="badge badge-<?php echo htmlspecialchars($row['status']); ?>"><?php echo ucfirst(str_replace('_', ' ', $row['status'])); ?></span></td>
                        <td><a href="project_details.php?id=<?php echo (int) $row['id']; ?>" class="btn btn-sm btn-info"><i class="fas fa-eye"></i></a></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
