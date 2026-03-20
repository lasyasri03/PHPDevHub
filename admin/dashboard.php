<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/admin_helpers.php';
require_once __DIR__ . '/../includes/admin_ui.php';

if (!isLoggedIn() || getUserRole() !== 'admin') {
    header('Location: ' . appUrl('login.php'));
    exit;
}

function getCount(mysqli $conn, string $sql): int
{
    $result = $conn->query($sql);
    $row = $result->fetch_assoc();

    return (int) ($row['total'] ?? 0);
}

function getAmount(mysqli $conn, string $sql): float
{
    $result = $conn->query($sql);
    $row = $result->fetch_assoc();

    return (float) ($row['total'] ?? 0);
}

$successMessage = $_GET['success'] ?? '';
$search = trim($_GET['search'] ?? '');

$stats = [
    'developers' => getCount($conn, "SELECT COUNT(*) AS total FROM users WHERE role = 'developer'"),
    'clients' => getCount($conn, "SELECT COUNT(*) AS total FROM users WHERE role = 'client'"),
    'projects' => getCount($conn, "SELECT COUNT(*) AS total FROM projects"),
    'active_projects' => getCount($conn, "SELECT COUNT(*) AS total FROM projects WHERE status = 'in_progress'"),
    'completed_projects' => getCount($conn, "SELECT COUNT(*) AS total FROM projects WHERE status = 'completed'"),
    'revenue' => getAmount($conn, "SELECT COALESCE(SUM(amount), 0) AS total FROM platform_earnings"),
    'monthly_revenue' => getAmount($conn, "SELECT COALESCE(SUM(amount), 0) AS total FROM platform_earnings WHERE YEAR(created_at) = YEAR(CURRENT_DATE()) AND MONTH(created_at) = MONTH(CURRENT_DATE())"),
];

$recentNotifications = $conn->query(
    "SELECT id, type, message, is_read, created_at
     FROM admin_notifications
     ORDER BY created_at DESC, id DESC
     LIMIT 8"
);

$ongoingProjects = $conn->query(
    "SELECT p.title, c.name AS client_name, COALESCE(GROUP_CONCAT(DISTINCT d.name SEPARATOR ', '), 'Not assigned') AS developer_names, p.budget, p.status, p.created_at
     FROM projects p
     INNER JOIN users c ON c.id = p.client_id
     LEFT JOIN hire_requests hr ON hr.project_id = p.id AND hr.status = 'accepted'
     LEFT JOIN users d ON d.id = hr.developer_id
     WHERE p.status = 'in_progress'
     GROUP BY p.id, p.title, c.name, p.budget, p.status, p.created_at
     ORDER BY p.created_at DESC, p.id DESC
     LIMIT 8"
);

$recentUsers = $conn->query(
    "SELECT id, name, email, role, created_at
     FROM users
     ORDER BY created_at DESC
     LIMIT 8"
);

$paymentSummary = $conn->query(
    "SELECT
        COUNT(*) AS total_payments,
        COALESCE(SUM(CASE WHEN payment_status = 'Paid' THEN amount ELSE 0 END),0) AS paid_amount
     FROM payments"
)->fetch_assoc();



$recentPayments = $conn->query(
    "SELECT p.title AS project_title, 
            c.name AS client_name, 
            d.name AS developer_name, 
            pay.amount, 
            pay.payment_status, 
            pay.paid_at, 
            pay.created_at
     FROM payments pay
     INNER JOIN projects p ON p.id = pay.project_id
     INNER JOIN users c ON c.id = pay.client_id
     INNER JOIN users d ON d.id = pay.developer_id
     WHERE pay.payment_status != 'Pending'
     ORDER BY COALESCE(pay.paid_at, pay.created_at) DESC, pay.id DESC
     LIMIT 6"
);

$developerResults = [];
$clientResults = [];
$projectResults = [];

if ($search !== '') {
    $searchLike = '%' . $search . '%';

    $developerStmt = $conn->prepare(
        "SELECT u.name, u.email, d.skills
         FROM users u
         LEFT JOIN developers d ON d.user_id = u.id
         WHERE u.role = 'developer'
           AND (u.name LIKE ? OR COALESCE(d.skills, '') LIKE ?)
         ORDER BY u.created_at DESC
         LIMIT 10"
    );
    $developerStmt->bind_param('ss', $searchLike, $searchLike);
    $developerStmt->execute();
    $developerResults = $developerStmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $clientStmt = $conn->prepare(
        "SELECT name, email
         FROM users
         WHERE role = 'client'
           AND (name LIKE ? OR email LIKE ?)
         ORDER BY created_at DESC
         LIMIT 10"
    );
    $clientStmt->bind_param('ss', $searchLike, $searchLike);
    $clientStmt->execute();
    $clientResults = $clientStmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $projectStmt = $conn->prepare(
        "SELECT p.title, p.description, p.budget, p.status, c.name AS client_name
         FROM projects p
         INNER JOIN users c ON c.id = p.client_id
         WHERE p.title LIKE ? OR COALESCE(p.description, '') LIKE ?
         ORDER BY p.created_at DESC, p.id DESC
         LIMIT 10"
    );
    $projectStmt->bind_param('ss', $searchLike, $searchLike);
    $projectStmt->execute();
    $projectResults = $projectStmt->get_result()->fetch_all(MYSQLI_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f4f7fb;
            color: #1f2937;
        }
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
        .logout-btn { background: #dc2626; color: #fff; padding: 10px 16px; border-radius: 10px; font-weight: 600; border: 0; cursor: pointer; }
        .topbar-actions, .topbar-search-form { display: flex; align-items: center; gap: 14px; flex-wrap: wrap; }
        .search-shell { position: relative; width: min(420px, 100%); }
        .search-icon { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: #94a3b8; pointer-events: none; font-size: 15px; }
        .admin-search { width: 100%; padding: 12px 16px 12px 38px; border-radius: 14px; border: 1px solid #dbe3ef; background: #fff; box-shadow: 0 10px 24px rgba(15, 23, 42, 0.06); }
        .alert { padding: 14px 16px; border-radius: 12px; margin-bottom: 20px; }
        .alert-success { background: #dcfce7; color: #166534; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 18px; margin-bottom: 26px; }
        .stat-card, .panel { background: #fff; border-radius: 16px; padding: 22px; box-shadow: 0 12px 30px rgba(15, 23, 42, 0.08); }
        .stat-card h3 { margin: 0 0 10px; color: #6b7280; font-size: 15px; font-weight: 600; }
        .stat-card .value { font-size: 34px; font-weight: 700; color: #111827; }
        .quick-links { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 14px; margin-top: 18px; }
        .quick-link { display: block; padding: 16px; border: 1px solid #dbe3ef; border-radius: 12px; color: #1d4ed8; font-weight: 600; background: #f8fbff; }
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: 14px 12px; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
        th { color: #6b7280; font-size: 13px; text-transform: uppercase; letter-spacing: 0.04em; }
        tbody tr:hover { background: #f8fbff; }
        .badge { display: inline-block; padding: 6px 10px; border-radius: 999px; font-size: 12px; font-weight: 700; text-transform: capitalize; background: #e5e7eb; color: #374151; }
        .empty-state { color: #6b7280; margin: 0; }
        @media (max-width: 900px) {
            .admin-layout { flex-direction: column; }
            .sidebar { width: 100%; }
            .content { padding: 20px; }
            .topbar-search-form { width: 100%; }
        }
    </style>
</head>
<body>
    <div class="admin-layout">
        <?php renderAdminSidebar('dashboard'); ?>

        <main class="content">
            <div class="topbar">
                <div>
                    <h1 class="page-title">Dashboard</h1>
                    <p class="topbar-text">Overview of developers, clients, projects, revenue, and marketplace activity.</p>
                </div>
                <div class="topbar-actions">
                    <form method="get" action="" class="topbar-search-form">
                        <div class="search-shell">
                            <span class="search-icon">⌕</span>
                            <input type="search" name="search" class="admin-search" placeholder="Search developers, clients, projects..." value="<?php echo htmlspecialchars($search); ?>" autocomplete="off">
                        </div>
                        <button type="submit" class="logout-btn" style="background:#2563eb;">Search</button>
                    </form>
                    <a class="logout-btn" href="<?php echo htmlspecialchars(appUrl('admin/logout.php')); ?>">Logout</a>
                </div>
            </div>

            <?php if ($successMessage !== ''): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($successMessage); ?></div>
            <?php endif; ?>

            <?php if ($search !== ''): ?>
                <section class="panel" style="margin-bottom: 24px;">
                    <h2 style="margin-top: 0;">Search Results for "<?php echo htmlspecialchars($search); ?>"</h2>
                    <?php if (!$developerResults && !$clientResults && !$projectResults): ?>
                        <p class="empty-state">No results found.</p>
                    <?php else: ?>
                        <?php if ($developerResults): ?>
                            <div class="table-wrap" style="margin-top: 18px;">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Email</th>
                                            <th>Skills</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($developerResults as $developer): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($developer['name']); ?></td>
                                                <td><?php echo htmlspecialchars($developer['email']); ?></td>
                                                <td><?php echo htmlspecialchars($developer['skills'] ?: 'Not provided'); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>

                        <?php if ($clientResults): ?>
                            <div class="table-wrap" style="margin-top: 24px;">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Email</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($clientResults as $client): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($client['name']); ?></td>
                                                <td><?php echo htmlspecialchars($client['email']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>

                        <?php if ($projectResults): ?>
                            <div class="table-wrap" style="margin-top: 24px;">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Title</th>
                                            <th>Client</th>
                                            <th>Description</th>
                                            <th>Budget</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($projectResults as $project): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($project['title']); ?></td>
                                                <td><?php echo htmlspecialchars($project['client_name']); ?></td>
                                                <td><?php echo htmlspecialchars($project['description'] ?: 'No description'); ?></td>
                                                <td>$<?php echo number_format((float) $project['budget'], 2); ?></td>
                                                <td><span class="badge"><?php echo htmlspecialchars(str_replace('_', ' ', $project['status'])); ?></span></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </section>
            <?php endif; ?>

            <section class="stats-grid">
                <div class="stat-card">
                    <h3>Total Developers</h3>
                    <div class="value"><?php echo $stats['developers']; ?></div>
                </div>
                <div class="stat-card">
                    <h3>Total Clients</h3>
                    <div class="value"><?php echo $stats['clients']; ?></div>
                </div>
                <div class="stat-card">
                    <h3>Total Projects Posted</h3>
                    <div class="value"><?php echo $stats['projects']; ?></div>
                </div>
                <div class="stat-card">
                    <h3>Active Projects</h3>
                    <div class="value"><?php echo $stats['active_projects']; ?></div>
                </div>
                <div class="stat-card">
                    <h3>Completed Projects</h3>
                    <div class="value"><?php echo $stats['completed_projects']; ?></div>
                </div>

                <div class="stat-card">
                    <h3>Payments Recorded</h3>
                    <div class="value"><?php echo (int) ($paymentSummary['total_payments'] ?? 0); ?></div>
                </div>
                <div class="stat-card">
                    <h3>Paid Amount</h3>
                    <div class="value">$<?php echo number_format((float) ($paymentSummary['paid_amount'] ?? 0), 2); ?></div>
                </div>
                
            </section>

            <section class="panel">
                <h2 style="margin-top: 0;">Quick Actions</h2>
                <div class="quick-links">
                    <a class="quick-link" href="<?php echo htmlspecialchars(appUrl('admin/developers.php')); ?>">Manage Developers</a>
                    <a class="quick-link" href="<?php echo htmlspecialchars(appUrl('admin/clients.php')); ?>">Manage Clients</a>
                    <a class="quick-link" href="<?php echo htmlspecialchars(appUrl('admin/projects.php')); ?>">View Projects</a>
                    <a class="quick-link" href="<?php echo htmlspecialchars(appUrl('admin/tasks.php')); ?>">Track Tasks</a>
                    <a class="quick-link" href="<?php echo htmlspecialchars(appUrl('admin/admin_disputes.php')); ?>">Manage Disputes</a>
                    <a class="quick-link" href="<?php echo htmlspecialchars(appUrl('admin/admin_announcements.php')); ?>">Announcements</a>
                    <a class="quick-link" href="<?php echo htmlspecialchars(appUrl('admin/admin_leaderboard.php')); ?>">Leaderboard</a>
                    <a class="quick-link" href="<?php echo htmlspecialchars(appUrl('admin/admin_logs.php')); ?>">Activity Logs</a>
                </div>
            </section>

            <section class="panel" style="margin-top: 24px;">
                <h2 style="margin-top: 0;">Recent Payments</h2>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Project</th>
                                <th>Client</th>
                                <th>Developer</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($recentPayments->num_rows > 0): ?>
                                <?php while ($payment = $recentPayments->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($payment['project_title']); ?></td>
                                        <td><?php echo htmlspecialchars($payment['client_name']); ?></td>
                                        <td><?php echo htmlspecialchars($payment['developer_name']); ?></td>
                                        <td>$<?php echo number_format((float) $payment['amount'], 2); ?></td>
                                        <td><span class="badge"><?php echo htmlspecialchars($payment['payment_status']); ?></span></td>
                                        <td><?php echo htmlspecialchars($payment['paid_at'] ?: $payment['created_at']); ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6"><p class="empty-state">No payment records yet.</p></td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="panel" style="margin-top: 24px;">
                <h2 style="margin-top: 0;">Admin Notifications</h2>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th>Message</th>
                                <th>Status</th>
                                <th>Created</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($recentNotifications->num_rows > 0): ?>
                                <?php while ($notification = $recentNotifications->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars(str_replace('_', ' ', $notification['type'])); ?></td>
                                        <td><?php echo htmlspecialchars($notification['message']); ?></td>
                                        <td><span class="badge"><?php echo $notification['is_read'] ? 'Read' : 'New'; ?></span></td>
                                        <td><?php echo htmlspecialchars($notification['created_at']); ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4"><p class="empty-state">No admin notifications yet.</p></td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="panel" style="margin-top: 24px;">
                <h2 style="margin-top: 0;">Ongoing Projects</h2>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Project Title</th>
                                <th>Client</th>
                                <th>Developer</th>
                                <th>Budget</th>
                                <th>Status</th>
                                <th>Start Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($ongoingProjects->num_rows > 0): ?>
                                <?php while ($project = $ongoingProjects->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($project['title']); ?></td>
                                        <td><?php echo htmlspecialchars($project['client_name']); ?></td>
                                        <td><?php echo htmlspecialchars($project['developer_names']); ?></td>
                                        <td>$<?php echo number_format((float) $project['budget'], 2); ?></td>
                                        <td><span class="badge"><?php echo htmlspecialchars(str_replace('_', ' ', $project['status'])); ?></span></td>
                                        <td><?php echo htmlspecialchars($project['created_at']); ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6"><p class="empty-state">No ongoing projects right now.</p></td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="panel" style="margin-top: 24px;">
                <h2 style="margin-top: 0;">Recent Users</h2>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>SN</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Joined</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($recentUsers->num_rows > 0): ?>
                                <?php $sn = 1; ?>
                                <?php while ($user = $recentUsers->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo $sn++; ?></td>
                                        <td><?php echo htmlspecialchars($user['name']); ?></td>
                                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                                        <td><span class="badge"><?php echo htmlspecialchars($user['role']); ?></span></td>
                                        <td><?php echo htmlspecialchars($user['created_at']); ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5"><p class="empty-state">No users found.</p></td>
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
