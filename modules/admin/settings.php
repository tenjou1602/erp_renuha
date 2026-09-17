<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';

requireAdmin();

$page_title = 'System Settings';

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Basic settings - in a real system, these would be stored in a settings table
    $settings = [
        'app_name' => $_POST['app_name'] ?? 'RUNEHA INC. ERP',
        'timezone' => $_POST['timezone'] ?? 'Asia/Manila',
        'currency' => $_POST['currency'] ?? 'PHP',
        'maintenance_mode' => isset($_POST['maintenance_mode']) ? 1 : 0
    ];
    
    $_SESSION['settings'] = $settings;
    $_SESSION['success'] = "Settings updated successfully!";
    logActivity($_SESSION['user_id'], 'Updated system settings', 'Admin', 'Settings updated');
    header('Location: settings.php');
    exit();
}

// Get current settings (from session or defaults)
$settings = $_SESSION['settings'] ?? [
    'app_name' => 'RUNEHA INC. ERP',
    'timezone' => 'Asia/Manila',
    'currency' => 'PHP',
    'maintenance_mode' => 0
];

include '../../includes/header.php';
?>

<div class="page-header">
    <h1><i class="fas fa-cog"></i> <?php echo $page_title; ?></h1>
</div>

<?php if (isset($_SESSION['success'])): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
<?php endif; ?>

<div class="card">
    <h3>General Settings</h3>
    <form method="POST">
        <div class="form-row">
            <div class="form-group">
                <label>Application Name</label>
                <input type="text" name="app_name" value="<?php echo htmlspecialchars($settings['app_name']); ?>">
            </div>
            <div class="form-group">
                <label>Time Zone</label>
                <select name="timezone">
                    <?php 
                    $timezones = ['Asia/Manila', 'Asia/Singapore', 'Asia/Tokyo', 'Asia/Dubai', 'UTC', 'America/New_York', 'Europe/London'];
                    foreach ($timezones as $tz):
                    ?>
                        <option value="<?php echo $tz; ?>" <?php echo ($settings['timezone'] ?? '') == $tz ? 'selected' : ''; ?>><?php echo $tz; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label>Currency</label>
                <select name="currency">
                    <option value="PHP" <?php echo ($settings['currency'] ?? '') == 'PHP' ? 'selected' : ''; ?>>PHP - Philippine Peso</option>
                    <option value="USD" <?php echo ($settings['currency'] ?? '') == 'USD' ? 'selected' : ''; ?>>USD - US Dollar</option>
                    <option value="EUR" <?php echo ($settings['currency'] ?? '') == 'EUR' ? 'selected' : ''; ?>>EUR - Euro</option>
                    <option value="SGD" <?php echo ($settings['currency'] ?? '') == 'SGD' ? 'selected' : ''; ?>>SGD - Singapore Dollar</option>
                </select>
            </div>
            <div class="form-group">
                <label style="display:flex;align-items:center;gap:0.5rem;">
                    <input type="checkbox" name="maintenance_mode" value="1" <?php echo ($settings['maintenance_mode'] ?? 0) ? 'checked' : ''; ?>>
                    Maintenance Mode
                </label>
                <small class="help-text">When enabled, only admins can access the system.</small>
            </div>
        </div>
        
        <div style="margin-top:1.5rem;">
            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Settings</button>
        </div>
    </form>
</div>

<div class="card">
    <h3>System Information</h3>
    <div class="view-details">
        <div class="detail-row"><span>PHP Version:</span> <?php echo phpversion(); ?></div>
        <div class="detail-row"><span>MySQL Version:</span> <?php echo $pdo->getAttribute(PDO::ATTR_SERVER_VERSION); ?></div>
        <div class="detail-row"><span>Server:</span> <?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'N/A'; ?></div>
        <div class="detail-row"><span>Database:</span> <?php echo DB_NAME; ?></div>
        <div class="detail-row"><span>Application Version:</span> 1.0.0</div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>