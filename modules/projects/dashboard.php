<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';

requireDepartment(['engineering']);

$page_title = 'Engineering Dashboard';

$stats = [
    'active_projects' => 0,
    'upcoming_projects' => 0,
    'ongoing_projects' => 0,
    'completed_projects' => 0,
    'total_budget' => 0,
    'actual_cost' => 0,
    'accomplishments' => 0,
    'attachments' => 0,
    'material_requirements' => 0,
];
$recent_projects = [];
$recent_accomplishments = [];

try {
    $stats['active_projects'] = $pdo->query("SELECT COUNT(*) FROM projects WHERE status IN ('planning', 'ongoing')")->fetchColumn() ?? 0;
    $stats['upcoming_projects'] = $pdo->query("SELECT COUNT(*) FROM projects WHERE status = 'planning' AND start_date > CURDATE()")->fetchColumn() ?? 0;
    $stats['ongoing_projects'] = $pdo->query("SELECT COUNT(*) FROM projects WHERE status = 'ongoing'")->fetchColumn() ?? 0;
    $stats['completed_projects'] = $pdo->query("SELECT COUNT(*) FROM projects WHERE status = 'completed'")->fetchColumn() ?? 0;
    $stats['total_budget'] = $pdo->query("SELECT COALESCE(SUM(estimated_budget), 0) FROM projects")->fetchColumn() ?? 0;
    $stats['actual_cost'] = $pdo->query("SELECT COALESCE(SUM(actual_cost), 0) FROM projects")->fetchColumn() ?? 0;

    try {
        $stats['accomplishments'] = $pdo->query("SELECT COUNT(*) FROM accomplishments")->fetchColumn() ?? 0;
    } catch (PDOException $e) {
        logActivity($_SESSION['user_id'] ?? null, 'Database error', 'Projects', $e->getMessage());
        $stats['accomplishments'] = 0;
    }

    try {
        $stats['attachments'] = $pdo->query("SELECT COUNT(*) FROM attachments")->fetchColumn() ?? 0;
    } catch (PDOException $e) {
        logActivity($_SESSION['user_id'] ?? null, 'Database error', 'Projects', $e->getMessage());
        $stats['attachments'] = 0;
    }

    $stats['material_requirements'] = $pdo->query("SELECT COUNT(*) FROM materials WHERE current_stock <= min_stock AND min_stock > 0 AND status = 'active'")->fetchColumn() ?? 0;
    $recent_projects = $pdo->query("SELECT id, project_code, name, status, location, start_date, end_date FROM projects ORDER BY created_at DESC LIMIT 5")->fetchAll();

    try {
        $recent_accomplishments = $pdo->query("
            SELECT a.*, p.project_code, p.name as project_name
            FROM accomplishments a
            LEFT JOIN projects p ON a.project_id = p.id
            ORDER BY a.created_at DESC
            LIMIT 5
        ")->fetchAll();
    } catch (PDOException $e) {
        logActivity($_SESSION['user_id'] ?? null, 'Database error', 'Projects', $e->getMessage());
        $recent_accomplishments = [];
    }
} catch (PDOException $e) {
    $error = userDatabaseError($e, 'Projects');
}

include '../../includes/header.php';
?>

<div class="page-header engineering-dash-header">
    <h1><i class="fas fa-hard-hat"></i> <?php echo $page_title; ?></h1>
    <div class="button-row">
        <a href="projects.php?action=add" class="btn btn-primary"><i class="fas fa-plus"></i> New Project</a>
        <a href="material_requirements.php" class="btn btn-outline"><i class="fas fa-clipboard-list"></i> Material Requirements</a>
        <a href="accomplishments.php" class="btn btn-outline"><i class="fas fa-check-circle"></i> Accomplishments</a>
    </div>
</div>

<?php if (isset($error)): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<div class="stats-grid stats-grid-3">
    <div class="stat-card accent-success">
        <div class="stat-icon"><i class="fas fa-building"></i></div>
        <div class="stat-value"><?php echo number_format($stats['active_projects']); ?></div>
        <div class="stat-label">Active Projects</div>
    </div>
    <div class="stat-card accent-warehouse">
        <div class="stat-icon"><i class="fas fa-clock"></i></div>
        <div class="stat-value"><?php echo number_format($stats['upcoming_projects']); ?></div>
        <div class="stat-label">Upcoming Projects</div>
    </div>
    <div class="stat-card accent-projects">
        <div class="stat-icon"><i class="fas fa-play-circle"></i></div>
        <div class="stat-value"><?php echo number_format($stats['ongoing_projects']); ?></div>
        <div class="stat-label">Ongoing</div>
    </div>
    <div class="stat-card accent-success">
        <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
        <div class="stat-value"><?php echo number_format($stats['completed_projects']); ?></div>
        <div class="stat-label">Completed</div>
    </div>
    <div class="stat-card accent-procurement">
        <div class="stat-icon"><i class="fas fa-coins"></i></div>
        <div class="stat-value"><span class="currency">₱</span><?php echo number_format((float) $stats['total_budget'], 2); ?></div>
        <div class="stat-label">Total Budget</div>
    </div>
    <div class="stat-card accent-warning">
        <div class="stat-icon"><i class="fas fa-receipt"></i></div>
        <div class="stat-value"><span class="currency">₱</span><?php echo number_format((float) $stats['actual_cost'], 2); ?></div>
        <div class="stat-label">Actual Cost</div>
    </div>
    <div class="stat-card accent-accounting">
        <div class="stat-icon"><i class="fas fa-clipboard-check"></i></div>
        <div class="stat-value"><?php echo number_format($stats['accomplishments']); ?></div>
        <div class="stat-label">Accomplishments</div>
    </div>
    <div class="stat-card accent-attachments">
        <div class="stat-icon"><i class="fas fa-paperclip"></i></div>
        <div class="stat-value"><?php echo number_format($stats['attachments']); ?></div>
        <div class="stat-label">Attachments</div>
    </div>
    <div class="stat-card accent-warehouse">
        <div class="stat-icon"><i class="fas fa-boxes"></i></div>
        <div class="stat-value"><?php echo number_format($stats['material_requirements']); ?></div>
        <div class="stat-label">Material Requirements</div>
    </div>
</div>

<div class="card">
    <h3><i class="fas fa-building"></i> Recent Projects</h3>
    <?php if (empty($recent_projects)): ?>
        <div class="empty-state compact">
            <i class="fas fa-building"></i>
            <p>No projects yet.</p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Project Code</th>
                        <th>Name</th>
                        <th>Location</th>
                        <th>Status</th>
                        <th>Start Date</th>
                        <th>End Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_projects as $project): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars(displayDocumentCode($project['project_code'])); ?></strong></td>
                        <td><?php echo htmlspecialchars($project['name']); ?></td>
                        <td><?php echo htmlspecialchars($project['location'] ?? 'N/A'); ?></td>
                        <td><span class="status-dot status-<?php echo htmlspecialchars($project['status']); ?>"></span><?php echo ucfirst(str_replace('_', ' ', $project['status'])); ?></td>
                        <td><?php echo $project['start_date'] ? date('M d, Y', strtotime($project['start_date'])) : 'N/A'; ?></td>
                        <td><?php echo $project['end_date'] ? date('M d, Y', strtotime($project['end_date'])) : 'N/A'; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php if (!empty($recent_accomplishments)): ?>
<div class="card">
    <h3><i class="fas fa-clipboard-check"></i> Recent Accomplishments</h3>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Project</th>
                    <th>Description</th>
                    <th>Completion %</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recent_accomplishments as $accomplishment): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars(displayDocumentCode($accomplishment['project_code'] ?? 'N/A')); ?></strong></td>
                    <td><?php echo htmlspecialchars(substr($accomplishment['description'], 0, 50)) . (strlen($accomplishment['description'] ?? '') > 50 ? '...' : ''); ?></td>
                    <td><?php echo number_format((float) $accomplishment['completion_percentage'], 1); ?>%</td>
                    <td><?php echo date('M d, Y', strtotime($accomplishment['created_at'])); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php include '../../includes/footer.php'; ?>
