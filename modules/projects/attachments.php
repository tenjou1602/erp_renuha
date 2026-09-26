<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';

requireDepartment(['engineering']);

$page_title = 'Project Attachments';
$upload_root = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'projects';

$allowed_ext = ['pdf', 'png', 'jpg', 'jpeg', 'gif', 'webp', 'doc', 'docx', 'xls', 'xlsx', 'txt', 'csv'];
$max_bytes = 10 * 1024 * 1024;

if (isset($_GET['download'])) {
    $id = (int)$_GET['download'];
    $stmt = $pdo->prepare("SELECT * FROM attachments WHERE id = ?");
    $stmt->execute([$id]);
    $file = $stmt->fetch();
    if (!$file) {
        $_SESSION['error'] = 'File not found.';
        header('Location: attachments.php');
        exit();
    }
    $full = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $file['file_path']);
    $real_root = realpath(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'uploads');
    $real_file = realpath($full);
    if (!$real_file || !$real_root || strpos($real_file, $real_root) !== 0 || !is_file($real_file)) {
        $_SESSION['error'] = 'File is missing on disk.';
        header('Location: attachments.php');
        exit();
    }
    header('Content-Type: ' . ($file['file_type'] ?: 'application/octet-stream'));
    header('Content-Disposition: attachment; filename="' . basename($file['file_name']) . '"');
    header('Content-Length: ' . filesize($real_file));
    readfile($real_file);
    exit();
}

if (isset($_GET['delete_id'])) {
    if (!canDelete('projects')) {
        $_SESSION['error'] = 'You cannot delete attachments.';
        header('Location: attachments.php');
        exit();
    }
    $id = (int)$_GET['delete_id'];
    $stmt = $pdo->prepare("SELECT * FROM attachments WHERE id = ?");
    $stmt->execute([$id]);
    $file = $stmt->fetch();
    if ($file) {
        $full = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $file['file_path']);
        if (is_file($full)) {
            @unlink($full);
        }
        $pdo->prepare("DELETE FROM attachments WHERE id = ?")->execute([$id]);
        logActivity($_SESSION['user_id'], 'Deleted attachment', 'Projects', $file['file_name']);
        $_SESSION['success'] = 'Attachment deleted.';
    }
    header('Location: attachments.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_attachment'])) {
    if (!canWriteDepartmentData('projects')) {
        $_SESSION['error'] = 'Administrators have view-only access.';
        header('Location: attachments.php');
        exit();
    }
    $project_id = (int)($_POST['project_id'] ?? 0);
    $file = $_FILES['attachment'] ?? null;
    if ($project_id <= 0 || !$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        $_SESSION['error'] = 'Select a project and a file.';
    } elseif ($file['error'] !== UPLOAD_ERR_OK) {
        $_SESSION['error'] = 'Upload failed.';
    } elseif ($file['size'] > $max_bytes) {
        $_SESSION['error'] = 'File must be 10 MB or smaller.';
    } else {
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed_ext, true)) {
            $_SESSION['error'] = 'File type not allowed.';
        } else {
            $dir = $upload_root . DIRECTORY_SEPARATOR . $project_id;
            if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
                $_SESSION['error'] = 'Could not create upload folder.';
            } else {
                $stored = bin2hex(random_bytes(8)) . '.' . $ext;
                $dest = $dir . DIRECTORY_SEPARATOR . $stored;
                if (!move_uploaded_file($file['tmp_name'], $dest)) {
                    $_SESSION['error'] = 'Could not save the file.';
                } else {
                    $rel = 'uploads/projects/' . $project_id . '/' . $stored;
                    try {
                        $stmt = $pdo->prepare("INSERT INTO attachments (project_id, file_name, file_path, file_type, file_size, uploaded_by) VALUES (?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$project_id, $file['name'], $rel, $file['type'] ?: null, (int)$file['size'], $_SESSION['user_id']]);
                        logActivity($_SESSION['user_id'], 'Uploaded attachment', 'Projects', $file['name']);
                        $_SESSION['success'] = 'File uploaded.';
                        header('Location: attachments.php');
                        exit();
                    } catch (PDOException $e) {
                        @unlink($dest);
                        $error = userDatabaseError($e, 'Projects');
                    }
                }
            }
        }
    }
}

$projects = [];
$files = [];
try {
    $projects = $pdo->query("SELECT id, project_code, name, status FROM projects ORDER BY created_at DESC")->fetchAll();
    $files = $pdo->query("
        SELECT a.*, p.project_code, p.name AS project_name, u.full_name AS uploaded_by_name
        FROM attachments a
        LEFT JOIN projects p ON a.project_id = p.id
        LEFT JOIN users u ON a.uploaded_by = u.id
        ORDER BY a.created_at DESC
    ")->fetchAll();
} catch (PDOException $e) {
    $error = userDatabaseError($e, 'Projects');
}

include '../../includes/header.php';
?>

<div class="page-header">
    <h1><i class="fas fa-paperclip"></i> Attachments</h1>
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
    <h3><i class="fas fa-upload"></i> Upload file</h3>
    <form method="POST" enctype="multipart/form-data">
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
            <label>File (max 10 MB)</label>
            <input type="file" name="attachment" required>
        </div>
        <button type="submit" name="upload_attachment" class="btn btn-primary">Upload</button>
    </form>
</div>
<?php endif; ?>

<div class="card">
    <p class="muted-note">Project PDFs are generated from project details. Uploaded files are stored per project.</p>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>File</th>
                    <th>Project</th>
                    <th>Size</th>
                    <th>Uploaded</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($files)): ?>
                    <tr><td colspan="5" class="table-empty">No attachments yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($files as $row): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($row['file_name']); ?></strong></td>
                        <td><?php echo htmlspecialchars(displayDocumentCode($row['project_code'] ?? 'N/A')); ?></td>
                        <td><?php echo $row['file_size'] ? number_format($row['file_size'] / 1024, 1) . ' KB' : '—'; ?></td>
                        <td><?php echo htmlspecialchars($row['uploaded_by_name'] ?? 'N/A'); ?><br><?php echo date('M d, Y', strtotime($row['created_at'])); ?></td>
                        <td>
                            <a href="attachments.php?download=<?php echo (int)$row['id']; ?>" class="btn btn-sm btn-outline"><i class="fas fa-download"></i></a>
                            <a href="project_pdf.php?id=<?php echo (int)$row['project_id']; ?>" class="btn btn-sm btn-outline"><i class="fas fa-file-pdf"></i></a>
                            <?php if (canDelete('projects')): ?>
                            <a href="attachments.php?delete_id=<?php echo (int)$row['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this file?');"><i class="fas fa-trash"></i></a>
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
