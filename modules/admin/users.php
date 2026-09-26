<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';

requireAdmin();

$page_title = 'User Management';
$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_user']) || isset($_POST['update_user'])) {
        $username = trim($_POST['username'] ?? '');
        $full_name = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $department = $_POST['department'] ?? '';
        $role = $_POST['role'] ?? 'staff';
        $status = $_POST['status'] ?? 'active';
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        $errors = [];
        if (empty($username)) $errors[] = 'Username is required.';
        if (empty($full_name)) $errors[] = 'Full name is required.';
        if (empty($email)) $errors[] = 'Email is required.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email format.';
        if (empty($department)) $errors[] = 'Department is required.';
        
        // Check unique username and email
        if ($action === 'add') {
            $check = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $check->execute([$username, $email]);
            if ($check->fetch()) {
                $errors[] = 'Username or email already exists.';
            }
            if (empty($password)) $errors[] = 'Password is required.';
            if ($password !== $confirm_password) $errors[] = 'Passwords do not match.';
            if (strlen($password) < 6) $errors[] = 'Password must be at least 6 characters.';
        } else {
            $check = $pdo->prepare("SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?");
            $check->execute([$username, $email, $id]);
            if ($check->fetch()) {
                $errors[] = 'Username or email already exists.';
            }
            if (!empty($password) && $password !== $confirm_password) {
                $errors[] = 'Passwords do not match.';
            }
            if (!empty($password) && strlen($password) < 6) {
                $errors[] = 'Password must be at least 6 characters.';
            }
        }
        
        if (empty($errors)) {
            try {
                if ($action === 'add') {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("INSERT INTO users (username, password, full_name, email, department, role, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$username, $hashed_password, $full_name, $email, $department, $role, $status]);
                    
                    logActivity($_SESSION['user_id'], 'Created user', 'Admin', "User: $username");
                    $_SESSION['success'] = "User created successfully!";
                } else {
                    if (!empty($password)) {
                        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("UPDATE users SET username = ?, full_name = ?, email = ?, department = ?, role = ?, status = ?, password = ? WHERE id = ?");
                        $stmt->execute([$username, $full_name, $email, $department, $role, $status, $hashed_password, $id]);
                    } else {
                        $stmt = $pdo->prepare("UPDATE users SET username = ?, full_name = ?, email = ?, department = ?, role = ?, status = ? WHERE id = ?");
                        $stmt->execute([$username, $full_name, $email, $department, $role, $status, $id]);
                    }
                    
                    logActivity($_SESSION['user_id'], 'Updated user', 'Admin', "User: $username");
                    $_SESSION['success'] = "User updated successfully!";
                }
                header('Location: users.php');
                exit();
            } catch (PDOException $e) {
                $error = userDatabaseError($e);
            }
        }
    }
    
    if (isset($_POST['delete_user'])) {
        $delete_id = (int)($_POST['user_id'] ?? 0);
        
        // Prevent deleting own account
        if ($delete_id == $_SESSION['user_id']) {
            $error = 'You cannot delete your own account.';
        } else {
            try {
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$delete_id]);
                
                logActivity($_SESSION['user_id'], 'Deleted user', 'Admin', "User ID: $delete_id");
                $_SESSION['success'] = "User deleted successfully!";
                header('Location: users.php');
                exit();
            } catch (PDOException $e) {
                $error = userDatabaseError($e);
            }
        }
    }
}

// Get users
$users = [];
try {
    $query = "SELECT * FROM users ORDER BY created_at DESC";
    $users = $pdo->query($query)->fetchAll();
} catch (PDOException $e) {
    $error = userDatabaseError($e);
}

// Get single user for edit
$user_details = null;
if ($action === 'edit' || $action === 'view') {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$id]);
    $user_details = $stmt->fetch();
    
    if (!$user_details) {
        header('Location: users.php');
        exit();
    }
}

include '../../includes/header.php';
?>

<div class="page-header">
    <h1><i class="fas fa-users-cog"></i> <?php echo $page_title; ?></h1>
    <?php if ($action === 'list'): ?>
        <a href="?action=add" class="btn btn-primary"><i class="fas fa-user-plus"></i> Add User</a>
    <?php endif; ?>
</div>

<?php if (isset($_SESSION['success'])): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
<?php endif; ?>
<?php if (isset($error)): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<?php if ($action === 'add' || $action === 'edit'): ?>
<!-- Add/Edit Form -->
<div class="card">
    <h3><?php echo $action === 'add' ? 'Create New' : 'Edit'; ?> User</h3>
    <form method="POST" data-validate>
        <?php if ($action === 'edit'): ?>
            <input type="hidden" name="update_user" value="1">
        <?php else: ?>
            <input type="hidden" name="add_user" value="1">
        <?php endif; ?>
        
        <div class="form-row">
            <div class="form-group">
                <label class="required">Username</label>
                <input type="text" name="username" required value="<?php echo htmlspecialchars($user_details['username'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label class="required">Full Name</label>
                <input type="text" name="full_name" required value="<?php echo htmlspecialchars($user_details['full_name'] ?? ''); ?>">
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label class="required">Email</label>
                <input type="email" name="email" required value="<?php echo htmlspecialchars($user_details['email'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label class="required">Department</label>
                <select name="department" required>
                    <option value="">Select Department</option>
                    <option value="procurement" <?php echo ($user_details['department'] ?? '') == 'procurement' ? 'selected' : ''; ?>>Procurement</option>
                    <option value="engineering" <?php echo ($user_details['department'] ?? '') === 'engineering' ? 'selected' : ''; ?>>Engineering</option>
                    <option value="accounting" <?php echo ($user_details['department'] ?? '') == 'accounting' ? 'selected' : ''; ?>>Accounting</option>
                    <option value="warehouse" <?php echo ($user_details['department'] ?? '') == 'warehouse' ? 'selected' : ''; ?>>Warehouse</option>
                    <option value="admin" <?php echo ($user_details['department'] ?? '') == 'admin' ? 'selected' : ''; ?>>Administrator</option>
                </select>
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label class="required">Role</label>
                <select name="role" required>
                    <option value="staff" <?php echo ($user_details['role'] ?? '') == 'staff' ? 'selected' : ''; ?>>Staff</option>
                    <option value="manager" <?php echo ($user_details['role'] ?? '') == 'manager' ? 'selected' : ''; ?>>Manager</option>
                    <option value="admin" <?php echo ($user_details['role'] ?? '') == 'admin' ? 'selected' : ''; ?>>Admin</option>
                </select>
            </div>
            <div class="form-group">
                <label class="required">Status</label>
                <select name="status" required>
                    <option value="active" <?php echo ($user_details['status'] ?? '') == 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="inactive" <?php echo ($user_details['status'] ?? '') == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                </select>
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label><?php echo $action === 'add' ? 'Password *' : 'Password (leave blank to keep current)'; ?></label>
                <input type="password" name="password" <?php echo $action === 'add' ? 'required' : ''; ?> minlength="6" placeholder="Min 6 characters">
            </div>
            <div class="form-group">
                <label><?php echo $action === 'add' ? 'Confirm Password *' : 'Confirm Password'; ?></label>
                <input type="password" name="confirm_password" <?php echo $action === 'add' ? 'required' : ''; ?> placeholder="Confirm password">
            </div>
        </div>
        
        <div style="margin-top:1.5rem;">
            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> <?php echo $action === 'add' ? 'Create' : 'Update'; ?> User</button>
            <a href="users.php" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

<?php elseif ($action === 'view' && $user_details): ?>
<!-- View User -->
<div class="card">
    <h3>User Details</h3>
    <div class="view-details">
        <div class="detail-row"><span>ID:</span> <?php echo $user_details['id']; ?></div>
        <div class="detail-row"><span>Username:</span> <strong><?php echo htmlspecialchars($user_details['username']); ?></strong></div>
        <div class="detail-row"><span>Full Name:</span> <?php echo htmlspecialchars($user_details['full_name']); ?></div>
        <div class="detail-row"><span>Email:</span> <?php echo htmlspecialchars($user_details['email']); ?></div>
        <div class="detail-row"><span>Department:</span> <span class="badge badge-primary"><?php echo ucfirst($user_details['department']); ?></span></div>
        <div class="detail-row"><span>Role:</span> <span class="badge badge-<?php echo $user_details['role'] === 'admin' ? 'danger' : ($user_details['role'] === 'manager' ? 'warning' : 'secondary'); ?>"><?php echo ucfirst($user_details['role']); ?></span></div>
        <div class="detail-row"><span>Status:</span> <span class="badge badge-<?php echo $user_details['status']; ?>"><?php echo ucfirst($user_details['status']); ?></span></div>
        <div class="detail-row"><span>Created:</span> <?php echo date('M d, Y h:i A', strtotime($user_details['created_at'])); ?></div>
        <?php if ($user_details['updated_at']): ?>
            <div class="detail-row"><span>Last Updated:</span> <?php echo date('M d, Y h:i A', strtotime($user_details['updated_at'])); ?></div>
        <?php endif; ?>
    </div>
    <div style="margin-top:1.5rem;">
        <a href="?action=edit&id=<?php echo $user_details['id']; ?>" class="btn btn-warning"><i class="fas fa-edit"></i> Edit</a>
        <a href="users.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back</a>
    </div>
</div>

<?php else: ?>
<!-- List View -->
<div class="card">
    <div class="table-responsive">
        <table class="table" id="userTable">
            <thead>
                <tr>
                    <th>ID</th>
                    <th onclick="sortTable('userTable', 1)">Username</th>
                    <th onclick="sortTable('userTable', 2)">Full Name</th>
                    <th onclick="sortTable('userTable', 3)">Email</th>
                    <th onclick="sortTable('userTable', 4)">Department</th>
                    <th onclick="sortTable('userTable', 5)">Role</th>
                    <th onclick="sortTable('userTable', 6)">Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($users)): ?>
                    <tr>
                        <td colspan="8" style="text-align:center;color:#94a3b8;padding:2rem;">No users found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?php echo $user['id']; ?></td>
                        <td><strong><?php echo htmlspecialchars($user['username']); ?></strong></td>
                        <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                        <td><span class="badge badge-primary"><?php echo ucfirst($user['department']); ?></span></td>
                        <td><span class="badge badge-<?php echo $user['role'] === 'admin' ? 'danger' : ($user['role'] === 'manager' ? 'warning' : 'secondary'); ?>"><?php echo ucfirst($user['role']); ?></span></td>
                        <td><span class="badge badge-<?php echo $user['status']; ?>"><?php echo ucfirst($user['status']); ?></span></td>
                        <td>
                            <a href="?action=view&id=<?php echo $user['id']; ?>" class="btn btn-sm btn-info"><i class="fas fa-eye"></i></a>
                            <a href="?action=edit&id=<?php echo $user['id']; ?>" class="btn btn-sm btn-warning"><i class="fas fa-edit"></i></a>
                            <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                <button onclick="confirmDelete(<?php echo $user['id']; ?>)" class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Delete Modal -->
<div id="deleteModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Confirm Delete</h3>
            <button class="modal-close" onclick="closeModal('deleteModal')">&times;</button>
        </div>
        <p>Are you sure you want to delete this user? This action cannot be undone.</p>
        <form method="POST" style="margin-top:1rem;">
            <input type="hidden" name="user_id" id="delete_user_id">
            <input type="hidden" name="delete_user" value="1">
            <div style="display:flex;gap:1rem;">
                <button type="submit" class="btn btn-danger">Delete</button>
                <button type="button" class="btn btn-secondary" onclick="closeModal('deleteModal')">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
function confirmDelete(userId) {
    document.getElementById('delete_user_id').value = userId;
    openModal('deleteModal');
}
</script>
<?php endif; ?>

<?php include '../../includes/footer.php'; ?>