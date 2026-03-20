<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/admin_ui.php';

if (!isLoggedIn() || getUserRole() !== 'admin') {
    header('Location: ' . appUrl('login.php'));
    exit;
}

$projectId = isset($_GET['project_id']) ? (int) $_GET['project_id'] : 0;
if ($projectId <= 0) {
    header('Location: ' . appUrl('admin/projects.php') . '?error=' . urlencode('Invalid project selected.'));
    exit;
}

$projectStmt = $conn->prepare(
    "SELECT p.*, u.name AS client_name, u.id AS client_id
     FROM projects p
     INNER JOIN users u ON u.id = p.client_id
     WHERE p.id = ?"
);
$projectStmt->bind_param('i', $projectId);
$projectStmt->execute();
$project = $projectStmt->get_result()->fetch_assoc();

if (!$project) {
    header('Location: ' . appUrl('admin/projects.php') . '?error=' . urlencode('Project not found.'));
    exit;
}

$developersStmt = $conn->prepare(
    "SELECT d.id, d.name, hr.status, hr.created_at
     FROM hire_requests hr
     INNER JOIN users d ON d.id = hr.developer_id
     WHERE hr.project_id = ?
     ORDER BY hr.created_at DESC"
);
$developersStmt->bind_param('i', $projectId);
$developersStmt->execute();
$developers = $developersStmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Project Details</title>
    <link rel="stylesheet" href="<?php echo htmlspecialchars(appUrl('assets/css/style.css')); ?>">
    <style>*{box-sizing:border-box}body{margin:0;font-family:Arial,sans-serif;background:#f4f7fb;color:#1f2937}a{text-decoration:none}.admin-layout{min-height:100vh;display:flex}.sidebar{width:260px;background:#111827;color:#fff;padding:24px 18px}.brand{font-size:24px;font-weight:700;margin-bottom:8px}.brand-subtitle{color:#9ca3af;font-size:14px;margin-bottom:26px}.nav-link{display:block;color:#d1d5db;padding:12px 14px;border-radius:10px;margin-bottom:8px}.nav-link:hover,.nav-link.active{background:#2563eb;color:#fff}.sidebar-section{margin-bottom:20px}.sidebar-heading{color:#6b7280;font-size:11px;font-weight:700;letter-spacing:.12em;margin:0 0 10px 12px}.content{flex:1;padding:28px}.topbar{display:flex;justify-content:space-between;align-items:center;gap:16px;margin-bottom:24px;flex-wrap:wrap}.page-title{margin:0;font-size:30px}.topbar-text{margin:6px 0 0;color:#6b7280}.back-btn{background:#2563eb;color:#fff;padding:10px 16px;border-radius:10px;font-weight:600}.panel{background:#fff;border-radius:16px;padding:22px;box-shadow:0 12px 30px rgba(15,23,42,.08);margin-bottom:24px}.profile-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:18px}.profile-item{padding:14px 16px;border:1px solid #e5e7eb;border-radius:14px;background:#fbfdff}.profile-label{display:block;margin-bottom:6px;color:#6b7280;font-size:13px;text-transform:uppercase;letter-spacing:.04em}.profile-value{margin:0;font-size:16px}.table-wrap{overflow-x:auto}table{width:100%;border-collapse:collapse}th,td{text-align:left;padding:14px 12px;border-bottom:1px solid #e5e7eb}th{color:#6b7280;font-size:13px;text-transform:uppercase;letter-spacing:.04em}tbody tr:hover{background:#f8fbff}.badge{display:inline-block;padding:6px 10px;border-radius:999px;font-size:12px;font-weight:700;text-transform:capitalize;background:#e5e7eb;color:#374151}.empty-state{color:#6b7280;margin:0}</style>
</head>
<body><div class="admin-layout"><?php renderAdminSidebar('projects'); ?><main class="content"><div class="topbar"><div><h1 class="page-title">Project Details</h1><p class="topbar-text">Project overview, owner, and developer activity.</p></div><a class="back-btn" href="<?php echo htmlspecialchars(appUrl('admin/projects.php')); ?>">Back to Projects</a></div><section class="panel"><div class="profile-grid"><div class="profile-item"><span class="profile-label">Title</span><p class="profile-value"><?php echo htmlspecialchars($project['title']); ?></p></div><div class="profile-item"><span class="profile-label">Client</span><p class="profile-value"><a href="<?php echo htmlspecialchars(appUrl('admin/admin_client_details.php')); ?>?client_id=<?php echo (int) $project['client_id']; ?>"><?php echo htmlspecialchars($project['client_name']); ?></a></p></div><div class="profile-item"><span class="profile-label">Budget</span><p class="profile-value">$<?php echo number_format((float) $project['budget'], 2); ?></p></div><div class="profile-item"><span class="profile-label">Status</span><p class="profile-value"><?php echo htmlspecialchars(str_replace('_', ' ', $project['status'])); ?></p></div><div class="profile-item"><span class="profile-label">Deadline</span><p class="profile-value"><?php echo htmlspecialchars($project['deadline'] ?? 'Not set'); ?></p></div><div class="profile-item"><span class="profile-label">Created</span><p class="profile-value"><?php echo htmlspecialchars($project['created_at']); ?></p></div><div class="profile-item" style="grid-column:1/-1"><span class="profile-label">Description</span><p class="profile-value"><?php echo nl2br(htmlspecialchars($project['description'] ?? '')); ?></p></div></div></section><section class="panel"><h2 style="margin-top:0">Applications and Hires</h2><div class="table-wrap"><table><thead><tr><th>Developer</th><th>Status</th><th>Date</th></tr></thead><tbody><?php if ($developers->num_rows > 0): while ($developer = $developers->fetch_assoc()): ?><tr><td><a href="<?php echo htmlspecialchars(appUrl('admin/admin_developer_details.php')); ?>?developer_id=<?php echo (int) $developer['id']; ?>"><?php echo htmlspecialchars($developer['name']); ?></a></td><td><span class="badge"><?php echo htmlspecialchars($developer['status']); ?></span></td><td><?php echo htmlspecialchars($developer['created_at']); ?></td></tr><?php endwhile; else: ?><tr><td colspan="3"><p class="empty-state">No developer activity for this project yet.</p></td></tr><?php endif; ?></tbody></table></div></section></main></div></body></html>
