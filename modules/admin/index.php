<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';

requireAdmin();

$page_title = 'Admin Dashboard';

// Get statistics
try {
    $stats = [];
    $stats['total_users'] = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn() ?? 0;
    $stats['active_users'] = $pdo->query("SELECT COUNT(*) FROM users WHERE status = 'active'")->fetchColumn() ?? 0;
    $stats['inactive_users'] = $pdo->query("SELECT COUNT(*) FROM users WHERE status = 'inactive'")->fetchColumn() ?? 0;
    $stats['total_activity'] = $pdo->query("SELECT COUNT(*) FROM activity_log")->fetchColumn() ?? 0;
    
    $recent_activity = $pdo->query("
        SELECT al.*, u.full_name 
        FROM activity_log al 
        LEFT JOIN users u ON al.user_id = u.id 
        ORDER BY al.created_at DESC 
        LIMIT 20
    ")->fetchAll();
    
} catch (PDOException $e) {
    $error = 'Database error: ' . $e->getMessage();
}

include '../../includes/header.php';
?>

<div class="page-header">
    <h1><i class="fas fa-crown"></i> Admin Dashboard</h1>
</div>

<?php if (isset($error)): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<!-- Stats Cards -->
<div class="stats-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1rem;margin-bottom:1.5rem;">
    <div class="card" style="border-left:4px solid #4e73df;">
        <div style="font-size:0.85rem;color:#64748b;">Total Users</div>
        <div style="font-size:1.8rem;font-weight:700;"><?php echo number_format($stats['total_users'] ?? 0); ?></div>
    </div>
    <div class="card" style="border-left:4px solid #1cc88a;">
        <div style="font-size:0.85rem;color:#64748b;">Active Users</div>
        <div style="font-size:1.8rem;font-weight:700;"><?php echo number_format($stats['active_users'] ?? 0); ?></div>
    </div>
    <div class="card" style="border-left:4px solid #e74a3b;">
        <div style="font-size:0.85rem;color:#64748b;">Inactive Users</div>
        <div style="font-size:1.8rem;font-weight:700;"><?php echo number_format($stats['inactive_users'] ?? 0); ?></div>
    </div>
    <div class="card" style="border-left:4px solid #f6c23e;">
        <div style="font-size:0.85rem;color:#64748b;">Total Activity Logs</div>
        <div style="font-size:1.8rem;font-weight:700;"><?php echo number_format($stats['total_activity'] ?? 0); ?></div>
    </div>
</div>

<!-- Quick Actions -->
<div class="card">
    <h3 style="margin-bottom:1rem;">Quick Actions</h3>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1rem;">
        <a href="users.php" class="btn btn-primary" style="justify-content:center;"><i class="fas fa-users-cog"></i> Manage Users</a>
        <a href="users.php?action=add" class="btn btn-success" style="justify-content:center;"><i class="fas fa-user-plus"></i> Add User</a>
        <a href="settings.php" class="btn btn-warning" style="justify-content:center;"><i class="fas fa-cog"></i> Settings</a>
        <a href="../../index.php" class="btn btn-secondary" style="justify-content:center;"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
    </div>
</div>

<!-- Recent Activity -->
<div class="card">
    <h3 style="margin-bottom:1rem;"><i class="fas fa-clock"></i> Recent Activity</h3>
    <?php if (empty($recent_activity)): ?>
        <div class="empty-state">
            <i class="fas fa-info-circle"></i>
            <p>No activity recorded yet.</p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Action</th>
                        <th>Module</th>
                        <th>Details</th>
                        <th>Time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_activity as $activity): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($activity['full_name'] ?? 'System'); ?></td>
                        <td><?php echo htmlspecialchars($activity['action']); ?></td>
                        <td><?php echo htmlspecialchars($activity['module']); ?></td>
                        <td><?php echo htmlspecialchars($activity['details'] ?? ''); ?></td>
                        <td><?php echo date('M d, Y h:i A', strtotime($activity['created_at'])); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php include '../../includes/footer.php'; ?>