<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../vendor/autoload.php';

use Dompdf\Dompdf;

requireDepartment(['engineering']);

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    $_SESSION['error'] = 'Invalid project selected for PDF download.';
    header('Location: projects.php');
    exit();
}

try {
    $stmt = $pdo->prepare("SELECT * FROM projects WHERE id = ?");
    $stmt->execute([$id]);
    $project = $stmt->fetch();
    if (!$project) {
        $_SESSION['error'] = 'Project not found.';
        header('Location: projects.php');
        exit();
    }
} catch (PDOException $e) {
    $_SESSION['error'] = 'Unable to load project for PDF.';
    header('Location: projects.php');
    exit();
}

logActivity(
    $_SESSION['user_id'],
    'Downloaded project PDF',
    'Projects',
    'Project: ' . $project['project_code']
);

$status = ucfirst(str_replace('_', ' ', (string) $project['status']));
$start = !empty($project['start_date']) ? date('M d, Y', strtotime($project['start_date'])) : 'N/A';
$end = !empty($project['end_date']) ? date('M d, Y', strtotime($project['end_date'])) : 'N/A';
$budget = number_format((float) ($project['estimated_budget'] ?? 0), 2);
$actual = number_format((float) ($project['actual_cost'] ?? 0), 2);
$created = !empty($project['created_at']) ? date('M d, Y h:i A', strtotime($project['created_at'])) : 'N/A';
$description = trim((string) ($project['description'] ?? ''));
if ($description === '') {
    $description = 'No description provided.';
}

$html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>
body { font-family: DejaVu Sans, sans-serif; color: #0f172a; font-size: 12px; margin: 0; }
.header { background: linear-gradient(135deg, #1e2a3a, #0f172a); color: #fff; padding: 22px 28px; }
.header h1 { margin: 0 0 4px; font-size: 20px; }
.header .sub { color: #f59e0b; font-size: 11px; letter-spacing: 0.6px; text-transform: uppercase; }
.content { padding: 24px 28px; }
.meta { margin-bottom: 18px; color: #64748b; font-size: 11px; }
.table { width: 100%; border-collapse: collapse; margin-bottom: 18px; }
.table th, .table td { text-align: left; padding: 10px 8px; border-bottom: 1px solid #e2e8f0; vertical-align: top; }
.table th { width: 32%; color: #64748b; font-weight: 600; background: #f8fafc; }
.badge { display: inline-block; padding: 3px 8px; border-radius: 999px; background: #fef3c7; color: #92400e; font-size: 11px; font-weight: 700; }
.section-title { font-size: 14px; margin: 0 0 8px; color: #1e2a3a; border-left: 3px solid #f59e0b; padding-left: 8px; }
.desc { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px; line-height: 1.5; white-space: pre-wrap; }
.footer { margin-top: 28px; font-size: 10px; color: #94a3b8; border-top: 1px solid #e2e8f0; padding-top: 10px; }
</style></head><body>
<div class="header">
  <div class="sub">RUNEHA INC. ERP</div>
  <h1>Project Report</h1>
</div>
<div class="content">
  <div class="meta">Generated on ' . htmlspecialchars(date('M d, Y h:i A')) . '</div>
  <table class="table">
    <tr><th>Project Code</th><td><strong>' . htmlspecialchars($project['project_code']) . '</strong></td></tr>
    <tr><th>Name</th><td>' . htmlspecialchars($project['name']) . '</td></tr>
    <tr><th>Client</th><td>' . htmlspecialchars($project['client'] ?? 'N/A') . '</td></tr>
    <tr><th>Location</th><td>' . htmlspecialchars($project['location'] ?? 'N/A') . '</td></tr>
    <tr><th>Status</th><td><span class="badge">' . htmlspecialchars($status) . '</span></td></tr>
    <tr><th>Start Date</th><td>' . htmlspecialchars($start) . '</td></tr>
    <tr><th>End Date</th><td>' . htmlspecialchars($end) . '</td></tr>
    <tr><th>Estimated Budget</th><td>PHP ' . $budget . '</td></tr>
    <tr><th>Actual Cost</th><td>PHP ' . $actual . '</td></tr>
    <tr><th>Created</th><td>' . htmlspecialchars($created) . '</td></tr>
  </table>
  <h2 class="section-title">Description</h2>
  <div class="desc">' . nl2br(htmlspecialchars($description)) . '</div>
  <div class="footer">Confidential — for internal RUNEHA INC. use only.</div>
</div>
</body></html>';

$dompdf = new Dompdf();
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$dompdf->stream('project-' . preg_replace('/[^A-Za-z0-9_-]/', '_', $project['project_code']) . '.pdf', ['Attachment' => true]);
exit();
