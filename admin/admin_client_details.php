<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/admin_ui.php';

if (!isLoggedIn() || getUserRole() !== 'admin') {
    header('Location: ' . appUrl('login.php'));
    exit;
}

$clientId = isset($_GET['client_id']) ? (int) $_GET['client_id'] : 0;
if ($clientId <= 0) {
    header('Location: ' . appUrl('admin/clients.php') . '?error=' . urlencode('Invalid client selected.'));
    exit;
}

$clientStmt = $conn->prepare(
    "SELECT id, name, email, role, account_status, created_at
     FROM users
     WHERE id = ? AND role = 'client'"
);
$clientStmt->bind_param('i', $clientId);
$clientStmt->execute();
$client = $clientStmt->get_result()->fetch_assoc();

if (!$client) {
    header('Location: ' . appUrl('admin/clients.php') . '?error=' . urlencode('Client not found.'));
    exit;
}

$statsStmt = $conn->prepare(
    "SELECT
        COUNT(DISTINCT p.id) AS projects_posted,
        COUNT(DISTINCT CASE WHEN p.status = 'in_progress' THEN p.id END) AS active_projects,
        COUNT(DISTINCT CASE WHEN p.status = 'completed' THEN p.id END) AS completed_projects
     FROM projects p
     WHERE p.client_id = ?"
);
$statsStmt->bind_param('i', $clientId);
$statsStmt->execute();
$stats = $statsStmt->get_result()->fetch_assoc() ?: ['projects_posted' => 0, 'active_projects' => 0, 'completed_projects' => 0];

$projectsStmt = $conn->prepare(
    "SELECT id, title, budget, status, created_at
     FROM projects
     WHERE client_id = ?
     ORDER BY created_at DESC"
);
$projectsStmt->bind_param('i', $clientId);
$projectsStmt->execute();
$projects = $projectsStmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Client Profile</title>
    <link rel="stylesheet" href="<?php echo htmlspecialchars(appUrl('assets/css/style.css')); ?>">
    <style>*{box-sizing:border-box}body{margin:0;font-family:Arial,sans-serif;background:#f4f7fb;color:#1f2937}a{text-decoration:none}.admin-layout{min-height:100vh;display:flex}.sidebar{width:260px;background:#111827;color:#fff;padding:24px 18px}.brand{font-size:24px;font-weight:700;margin-bottom:8px}.brand-subtitle{color:#9ca3af;font-size:14px;margin-bottom:26px}.nav-link{display:block;color:#d1d5db;padding:12px 14px;border-radius:10px;margin-bottom:8px}.nav-link:hover,.nav-link.active{background:#2563eb;color:#fff}.sidebar-section{margin-bottom:20px}.sidebar-heading{color:#6b7280;font-size:11px;font-weight:700;letter-spacing:.12em;margin:0 0 10px 12px}.content{flex:1;padding:28px}.topbar{display:flex;justify-content:space-between;align-items:center;gap:16px;margin-bottom:24px;flex-wrap:wrap}.page-title{margin:0;font-size:30px}.topbar-text{margin:6px 0 0;color:#6b7280}.back-btn{background:#2563eb;color:#fff;padding:10px 16px;border-radius:10px;font-weight:600}.panel,.stat-card{background:#fff;border-radius:16px;padding:22px;box-shadow:0 12px 30px rgba(15,23,42,.08)}.profile-grid,.stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:18px}.stats-grid{margin:24px 0}.profile-item{padding:14px 16px;border:1px solid #e5e7eb;border-radius:14px;background:#fbfdff}.profile-label{display:block;margin-bottom:6px;color:#6b7280;font-size:13px;text-transform:uppercase;letter-spacing:.04em}.profile-value{margin:0;font-size:16px}.stat-card h3{margin:0 0 10px;color:#6b7280;font-size:15px;font-weight:600}.value{font-size:34px;font-weight:700}.table-wrap{overflow-x:auto}table{width:100%;border-collapse:collapse}th,td{text-align:left;padding:14px 12px;border-bottom:1px solid #e5e7eb}th{color:#6b7280;font-size:13px;text-transform:uppercase;letter-spacing:.04em}tbody tr:hover{background:#f8fbff}.badge{display:inline-block;padding:6px 10px;border-radius:999px;font-size:12px;font-weight:700;text-transform:capitalize;background:#e5e7eb;color:#374151}.empty-state{color:#6b7280;margin:0}</style>
</head>
<body><div class="admin-layout"><?php renderAdminSidebar('clients'); ?><main class="content"><div class="topbar"><div><h1 class="page-title">Client Profile</h1><p class="topbar-text">Client account summary and posted projects.</p></div><a class="back-btn" href="<?php echo htmlspecialchars(appUrl('admin/clients.php')); ?>">Back to Clients</a></div><section class="panel"><div class="profile-grid"><div class="profile-item"><span class="profile-label">Name</span><p class="profile-value"><?php echo htmlspecialchars($client['name']); ?></p></div><div class="profile-item"><span class="profile-label">Email</span><p class="profile-value"><?php echo htmlspecialchars($client['email']); ?></p></div><div class="profile-item"><span class="profile-label">Status</span><p class="profile-value"><?php echo htmlspecialchars($client['account_status']); ?></p></div><div class="profile-item"><span class="profile-label">Joined</span><p class="profile-value"><?php echo htmlspecialchars($client['created_at']); ?></p></div></div></section><section class="stats-grid"><div class="stat-card"><h3>Projects Posted</h3><div class="value"><?php echo (int) $stats['projects_posted']; ?></div></div><div class="stat-card"><h3>Active Projects</h3><div class="value"><?php echo (int) $stats['active_projects']; ?></div></div><div class="stat-card"><h3>Completed Projects</h3><div class="value"><?php echo (int) $stats['completed_projects']; ?></div></div></section><section class="panel"><h2 style="margin-top:0">Projects</h2><div class="table-wrap"><table><thead><tr><th>Project</th><th>Budget</th><th>Status</th><th>Created</th></tr></thead><tbody><?php if ($projects->num_rows > 0): while ($project = $projects->fetch_assoc()): ?><tr><td><a href="<?php echo htmlspecialchars(appUrl('admin/admin_project_details.php')); ?>?project_id=<?php echo (int) $project['id']; ?>"><?php echo htmlspecialchars($project['title']); ?></a></td><td>$<?php echo number_format((float) $project['budget'], 2); ?></td><td><span class="badge"><?php echo htmlspecialchars(str_replace('_', ' ', $project['status'])); ?></span></td><td><?php echo htmlspecialchars($project['created_at']); ?></td></tr><?php endwhile; else: ?><tr><td colspan="4"><p class="empty-state">This client has not posted any projects yet.</p></td></tr><?php endif; ?></tbody></table></div></section></main></div></body></html>
