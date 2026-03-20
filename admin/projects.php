<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/admin_helpers.php';
require_once __DIR__ . '/../includes/admin_ui.php';

if (!isLoggedIn() || getUserRole() !== 'admin') {
    header('Location: ' . appUrl('login.php'));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $projectId = isset($_POST['project_id']) ? (int) $_POST['project_id'] : 0;
    $action = $_POST['action'] ?? '';

    if ($projectId > 0 && in_array($action, ['approve', 'reject'], true)) {
        $status = $action === 'approve' ? 'approved' : 'rejected';
        $stmt = $conn->prepare("UPDATE projects SET status = ? WHERE id = ?");
        $stmt->bind_param('si', $status, $projectId);
        $stmt->execute();

        if ($stmt->affected_rows >= 0) {
            adminLogAction($conn, (int) $_SESSION['user_id'], 'Project ' . ucfirst($action), 'Project #' . $projectId);
            header('Location: ' . appUrl('admin/projects.php') . '?success=' . urlencode('Project ' . $status . ' successfully.'));
            exit;
        }
    }

    header('Location: ' . appUrl('admin/projects.php') . '?error=' . urlencode('Unable to update project status.'));
    exit;
}

$successMessage = $_GET['success'] ?? '';
$errorMessage = $_GET['error'] ?? '';

$projects = $conn->query(
    "SELECT p.id, p.title, p.budget, p.status, p.created_at, u.name AS client_name,
            COALESCE(GROUP_CONCAT(DISTINCT d.name SEPARATOR ', '), 'Not assigned') AS developer_names
     FROM projects p
     INNER JOIN users u ON u.id = p.client_id
     LEFT JOIN hire_requests hr ON hr.project_id = p.id AND hr.status = 'accepted'
     LEFT JOIN users d ON d.id = hr.developer_id
     GROUP BY p.id, p.title, p.budget, p.status, p.created_at, u.name
     ORDER BY p.created_at DESC"
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Projects</title>
    <link rel="stylesheet" href="<?php echo htmlspecialchars(appUrl('assets/css/style.css')); ?>">
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Arial, sans-serif; background: #f4f7fb; color: #1f2937; }
        a { text-decoration: none; }
        .admin-layout { min-height: 100vh; display: flex; }
        .sidebar { width: 260px; background: #111827; color: #fff; padding: 24px 18px; }
        .brand { font-size: 24px; font-weight: 700; margin-bottom: 8px; }
        .brand-subtitle { color: #9ca3af; font-size: 14px; margin-bottom: 26px; }
        .nav-link { display: block; color: #d1d5db; padding: 12px 14px; border-radius: 10px; margin-bottom: 8px; }
        .nav-link:hover, .nav-link.active { background: #2563eb; color: #fff; }
        .sidebar-section { margin-bottom: 20px; }
        .sidebar-heading { color: #6b7280; font-size: 11px; font-weight: 700; letter-spacing: 0.12em; margin: 0 0 10px 12px; }
        .content { flex: 1; padding: 28px; }
        .topbar { display: flex; justify-content: space-between; align-items: center; gap: 16px; margin-bottom: 24px; flex-wrap: wrap; }
        .page-title { margin: 0; font-size: 30px; }
        .topbar-text { margin: 6px 0 0; color: #6b7280; }
        .logout-btn { background: #dc2626; color: #fff; padding: 10px 16px; border-radius: 10px; font-weight: 600; }
        .panel { background: #fff; border-radius: 16px; padding: 22px; box-shadow: 0 12px 30px rgba(15, 23, 42, 0.08); }
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: 14px 12px; border-bottom: 1px solid #e5e7eb; }
        th { color: #6b7280; font-size: 13px; text-transform: uppercase; letter-spacing: 0.04em; }
        tbody tr:hover { background: #f8fbff; }
        .badge { display: inline-block; padding: 6px 10px; border-radius: 999px; font-size: 12px; font-weight: 700; text-transform: capitalize; }
        .pending { background: #e5e7eb; color: #374151; }
        .approved { background: #dbeafe; color: #1d4ed8; }
        .in_progress { background: #fef3c7; color: #92400e; }
        .completed { background: #dcfce7; color: #166534; }
        .rejected { background: #fee2e2; color: #991b1b; }
        .btn { border: 0; border-radius: 10px; padding: 8px 12px; cursor: pointer; font-weight: 600; }
        .btn-approve { background: #16a34a; color: #fff; }
        .btn-reject { background: #dc2626; color: #fff; }
        .actions { display: flex; gap: 8px; flex-wrap: wrap; }
        .alert { padding: 14px 16px; border-radius: 12px; margin-bottom: 20px; }
        .alert-success { background: #dcfce7; color: #166534; }
        .alert-error { background: #fee2e2; color: #991b1b; }
        .empty-state { color: #6b7280; margin: 0; }
        @media (max-width: 900px) {
            .admin-layout { flex-direction: column; }
            .sidebar { width: 100%; }
            .content { padding: 20px; }
        }
    </style>
</head>
<body>
    <div class="admin-layout">
        <?php renderAdminSidebar('projects'); ?>

        <main class="content">
            <div class="topbar">
                <div>
                    <h1 class="page-title">Projects</h1>
                    <p class="topbar-text">Approve incoming projects and monitor live marketplace delivery.</p>
                </div>
                <a class="logout-btn" href="<?php echo htmlspecialchars(appUrl('admin/logout.php')); ?>">Logout</a>
            </div>

            <?php if ($successMessage !== ''): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($successMessage); ?></div>
            <?php endif; ?>
            <?php if ($errorMessage !== ''): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($errorMessage); ?></div>
            <?php endif; ?>

            <section class="panel">
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Project Title</th>
                                <th>Client Name</th>
                                <th>Developer</th>
                                <th>Budget</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($projects->num_rows > 0): ?>
                                <?php while ($project = $projects->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo (int) $project['id']; ?></td>
                                        <td><?php echo htmlspecialchars($project['title']); ?></td>
                                        <td><?php echo htmlspecialchars($project['client_name']); ?></td>
                                        <td><?php echo htmlspecialchars($project['developer_names']); ?></td>
                                        <td>$<?php echo number_format((float) $project['budget'], 2); ?></td>
                                        <td>
                                            <span class="badge <?php echo htmlspecialchars($project['status']); ?>">
                                                <?php echo htmlspecialchars(str_replace('_', ' ', $project['status'])); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($project['created_at']); ?></td>
                                        <td>
                                            <?php if ($project['status'] === 'pending'): ?>
                                                <div class="actions">
                                                    <form method="post">
                                                        <input type="hidden" name="project_id" value="<?php echo (int) $project['id']; ?>">
                                                        <button type="submit" name="action" value="approve" class="btn btn-approve">Approve</button>
                                                    </form>
                                                    <form method="post">
                                                        <input type="hidden" name="project_id" value="<?php echo (int) $project['id']; ?>">
                                                        <button type="submit" name="action" value="reject" class="btn btn-reject">Reject</button>
                                                    </form>
                                                </div>
                                            <?php else: ?>
                                                <span class="empty-state">No actions</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8"><p class="empty-state">No projects found.</p></td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </main>
    </div>
</body>
</html>
