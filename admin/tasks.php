<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/admin_ui.php';

if (!isLoggedIn() || getUserRole() !== 'admin') {
    header('Location: ' . appUrl('login.php'));
    exit;
}

$tasks = $conn->query(
    "SELECT t.id, t.title, t.description, t.status, t.created_at,
            p.title AS project_title,
            u.name AS developer_name
     FROM tasks t
     INNER JOIN projects p ON p.id = t.project_id
     INNER JOIN users u ON u.id = t.developer_id
     ORDER BY t.created_at DESC, t.id DESC"
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Project Tasks</title>
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
        th, td { text-align: left; padding: 14px 12px; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
        th { color: #6b7280; font-size: 13px; text-transform: uppercase; letter-spacing: 0.04em; }
        tbody tr:hover { background: #f8fbff; }
        .badge { display: inline-block; padding: 6px 10px; border-radius: 999px; font-size: 12px; font-weight: 700; text-transform: capitalize; }
        .pending { background: #e5e7eb; color: #374151; }
        .in_progress { background: #fef3c7; color: #92400e; }
        .completed { background: #dcfce7; color: #166534; }
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
        <?php renderAdminSidebar('contracts'); ?>

        <main class="content">
            <div class="topbar">
                <div>
                    <h1 class="page-title">Tasks</h1>
                    <p class="topbar-text">Monitor task assignments and developer progress across projects.</p>
                </div>
                <a class="logout-btn" href="<?php echo htmlspecialchars(appUrl('admin/logout.php')); ?>">Logout</a>
            </div>

            <section class="panel">
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Project</th>
                                <th>Developer</th>
                                <th>Task Title</th>
                                <th>Description</th>
                                <th>Status</th>
                                <th>Created</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($tasks->num_rows > 0): ?>
                                <?php while ($task = $tasks->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo (int) $task['id']; ?></td>
                                        <td><?php echo htmlspecialchars($task['project_title']); ?></td>
                                        <td><?php echo htmlspecialchars($task['developer_name']); ?></td>
                                        <td><?php echo htmlspecialchars($task['title']); ?></td>
                                        <td><?php echo htmlspecialchars($task['description']); ?></td>
                                        <td>
                                            <span class="badge <?php echo htmlspecialchars($task['status']); ?>">
                                                <?php echo htmlspecialchars(str_replace('_', ' ', $task['status'])); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($task['created_at']); ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7"><p class="empty-state">No tasks found.</p></td>
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
