<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';

requireDepartment(['projects']);

$page_title = 'Projects Dashboard';

// Get statistics
try {
    $stats = [];
    $stats['total_projects'] = $pdo->query("SELECT COUNT(*) FROM projects")->fetchColumn() ?? 0;
    $stats['ongoing_projects'] = $pdo->query("SELECT COUNT(*) FROM projects WHERE status = 'ongoing'")->fetchColumn() ?? 0;
    $stats['completed_projects'] = $pdo->query("SELECT COUNT(*) FROM projects WHERE status = 'completed'")->fetchColumn() ?? 0;
    $stats['planning_projects'] = $pdo->query("SELECT COUNT(*) FROM projects WHERE status = 'planning'")->fetchColumn() ?? 0;
    $stats['on_hold_projects'] = $pdo->query("SELECT COUNT(*) FROM projects WHERE status = 'on_hold'")->fetchColumn() ?? 0;
    $stats['total_budget'] = $pdo->query("SELECT COALESCE(SUM(estimated_budget), 0) FROM projects")->fetchColumn() ?? 0;
    $stats['total_cost'] = $pdo->query("SELECT COALESCE(SUM(actual_cost), 0) FROM projects WHERE status IN ('ongoing', 'completed')")->fetchColumn() ?? 0;
    
    // Recent projects
    $recent_projects = $pdo->query("SELECT * FROM projects ORDER BY created_at DESC LIMIT 5")->fetchAll();
    
} catch (PDOException $e) {
    $error = 'Database error: ' . $e->getMessage();
}

include '../../includes/header.php';
?>

<div class="page-header">
    <h1><i class="fas fa-hard-hat"></i> Projects Dashboard</h1>
</div>

<?php if (isset($error)): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<!-- Stats Cards -->
<div class="stats-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:1rem;margin-bottom:1.5rem;">
    <div class="card" style="border-left:4px solid #4e73df;">
        <div style="font-size:0.85rem;color:#64748b;">Total Projects</div>
        <div style="font-size:1.8rem;font-weight:700;"><?php echo number_format($stats['total_projects'] ?? 0); ?></div>
    </div>
    <div class="card" style="border-left:4px solid #f6c23e;">
        <div style="font-size:0.85rem;color:#64748b;">Ongoing</div>
        <div style="font-size:1.8rem;font-weight:700;"><?php echo number_format($stats['ongoing_projects'] ?? 0); ?></div>
    </div>
    <div class="card" style="border-left:4px solid #1cc88a;">
        <div style="font-size:0.85rem;color:#64748b;">Completed</div>
        <div style="font-size:1.8rem;font-weight:700;"><?php echo number_format($stats['completed_projects'] ?? 0); ?></div>
    </div>
    <div class="card" style="border-left:4px solid #fef3c7;">
        <div style="font-size:0.85rem;color:#64748b;">Planning</div>
        <div style="font-size:1.8rem;font-weight:700;"><?php echo number_format($stats['planning_projects'] ?? 0); ?></div>
    </div>
</div>

<div class="stats-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:1rem;margin-bottom:1.5rem;">
    <div class="card" style="border-left:4px solid #36b9cc;">
        <div style="font-size:0.85rem;color:#64748b;">Total Budget</div>
        <div style="font-size:1.8rem;font-weight:700;">₱<?php echo number_format($stats['total_budget'] ?? 0, 2); ?></div>
    </div>
    <div class="card" style="border-left:4px solid #e74a3b;">
        <div style="font-size:0.85rem;color:#64748b;">Actual Cost</div>
        <div style="font-size:1.8rem;font-weight:700;">₱<?php echo number_format($stats['total_cost'] ?? 0, 2); ?></div>
    </div>
</div>

<!-- Quick Actions -->
<div class="card">
    <h3 style="margin-bottom:1rem;">Quick Actions</h3>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1rem;">
        <a href="projects.php?action=add" class="btn btn-primary" style="justify-content:center;"><i class="fas fa-plus"></i> New Project</a>
        <a href="projects.php" class="btn btn-info" style="justify-content:center;"><i class="fas fa-list"></i> View All Projects</a>
        <a href="project_details.php" class="btn btn-warning" style="justify-content:center;"><i class="fas fa-chart-bar"></i> Project Details</a>
    </div>
</div>

<!-- Recent Projects -->
<div class="card">
    <h3 style="margin-bottom:1rem;"><i class="fas fa-clock"></i> Recent Projects</h3>
    <?php if (empty($recent_projects)): ?>
        <div class="empty-state">
            <i class="fas fa-building"></i>
            <p>No projects yet.</p>
            <a href="projects.php?action=add" class="btn btn-primary btn-sm" style="margin-top:0.5rem;">Create First Project</a>
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
                        <th>Budget</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_projects as $project): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($project['project_code']); ?></strong></td>
                        <td><?php echo htmlspecialchars($project['name']); ?></td>
                        <td><?php echo htmlspecialchars($project['location'] ?? 'N/A'); ?></td>
                        <td><span class="badge badge-<?php echo $project['status']; ?>"><?php echo ucfirst($project['status']); ?></span></td>
                        <td>₱<?php echo number_format($project['estimated_budget'], 2); ?></td>
                        <td><a href="project_details.php?id=<?php echo $project['id']; ?>" class="btn btn-sm btn-info"><i class="fas fa-eye"></i></a></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php include '../../includes/footer.php'; ?>