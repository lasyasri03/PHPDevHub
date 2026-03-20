<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/admin_helpers.php';
require_once __DIR__ . '/../includes/admin_ui.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

$developerId = isset($_GET['developer_id']) ? (int) $_GET['developer_id'] : 0;
if ($developerId <= 0) {
    header('Location: ' . appUrl('admin/developers.php') . '?error=' . urlencode('Invalid developer selected.'));
    exit;
}

$developerStmt = $conn->prepare(
    "SELECT u.id, u.name, u.email, u.role, u.created_at, d.skills, d.experience, d.bio, d.github_link, d.availability
     FROM users u
     LEFT JOIN developers d ON d.user_id = u.id
     WHERE u.id = ? AND u.role = 'developer'"
);
$developerStmt->bind_param('i', $developerId);
$developerStmt->execute();
$developer = $developerStmt->get_result()->fetch_assoc();

if (!$developer) {
    header('Location: ' . appUrl('admin/developers.php') . '?error=' . urlencode('Developer not found.'));
    exit;
}

adminLogAction($conn, (int) $_SESSION['user_id'], 'Profile Viewed', $developer['name']);

function fetchDeveloperCount(mysqli $conn, int $developerId, string $sql): int
{
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $developerId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    return (int) ($row['total'] ?? 0);
}

$stats = [
    'applied' => fetchDeveloperCount($conn, $developerId, "SELECT COUNT(*) AS total FROM hire_requests WHERE developer_id = ? AND project_id IS NOT NULL"),
    'hired' => fetchDeveloperCount($conn, $developerId, "SELECT COUNT(*) AS total FROM hire_requests WHERE developer_id = ? AND status = 'accepted' AND project_id IS NOT NULL"),
    'rejected' => fetchDeveloperCount($conn, $developerId, "SELECT COUNT(*) AS total FROM hire_requests WHERE developer_id = ? AND status = 'rejected' AND project_id IS NOT NULL"),
    'in_progress' => fetchDeveloperCount(
        $conn,
        $developerId,
        "SELECT COUNT(*) AS total
         FROM hire_requests hr
         INNER JOIN projects p ON p.id = hr.project_id
         WHERE hr.developer_id = ? AND hr.status = 'accepted' AND p.status = 'in_progress'"
    ),
    'completed' => fetchDeveloperCount(
        $conn,
        $developerId,
        "SELECT COUNT(*) AS total
         FROM hire_requests hr
         INNER JOIN projects p ON p.id = hr.project_id
         WHERE hr.developer_id = ? AND hr.status = 'accepted' AND p.status = 'completed'"
    ),
];
$successRate = $stats['hired'] > 0 ? round(($stats['completed'] / $stats['hired']) * 100, 2) : 0;

$appliedProjectsStmt = $conn->prepare(
    "SELECT p.title, c.name AS client_name, p.budget, hr.status, hr.created_at
     FROM hire_requests hr
     INNER JOIN projects p ON p.id = hr.project_id
     INNER JOIN users c ON c.id = p.client_id
     WHERE hr.developer_id = ?
     ORDER BY hr.created_at DESC, hr.id DESC"
);
$appliedProjectsStmt->bind_param('i', $developerId);
$appliedProjectsStmt->execute();
$appliedProjects = $appliedProjectsStmt->get_result();

$currentProjectsStmt = $conn->prepare(
    "SELECT p.title, c.name AS client_name, p.budget, p.status, hr.created_at AS start_date
     FROM hire_requests hr
     INNER JOIN projects p ON p.id = hr.project_id
     INNER JOIN users c ON c.id = p.client_id
     WHERE hr.developer_id = ?
       AND hr.status = 'accepted'
       AND p.status = 'in_progress'
     ORDER BY hr.created_at DESC, hr.id DESC"
);
$currentProjectsStmt->bind_param('i', $developerId);
$currentProjectsStmt->execute();
$currentProjects = $currentProjectsStmt->get_result();

$hiredByClientsStmt = $conn->prepare(
    "SELECT c.name AS client_name, p.title, p.budget, hr.status
     FROM hire_requests hr
     INNER JOIN projects p ON p.id = hr.project_id
     INNER JOIN users c ON c.id = p.client_id
     WHERE hr.developer_id = ?
       AND hr.status = 'accepted'
     ORDER BY hr.created_at DESC, hr.id DESC"
);
$hiredByClientsStmt->bind_param('i', $developerId);
$hiredByClientsStmt->execute();
$hiredByClients = $hiredByClientsStmt->get_result();

$completedProjectsStmt = $conn->prepare(
    "SELECT p.title, c.name AS client_name, p.budget, hr.created_at AS completed_date
     FROM hire_requests hr
     INNER JOIN projects p ON p.id = hr.project_id
     INNER JOIN users c ON c.id = p.client_id
     WHERE hr.developer_id = ?
       AND hr.status = 'accepted'
       AND p.status = 'completed'
     ORDER BY p.created_at DESC, p.id DESC"
);
$completedProjectsStmt->bind_param('i', $developerId);
$completedProjectsStmt->execute();
$completedProjects = $completedProjectsStmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Developer Dashboard</title>
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
        .back-btn { background: #2563eb; color: #fff; padding: 10px 16px; border-radius: 10px; font-weight: 600; }
        .panel { background: #fff; border-radius: 16px; padding: 22px; box-shadow: 0 12px 30px rgba(15, 23, 42, 0.08); margin-bottom: 24px; }
        .profile-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 18px; }
        .profile-item { padding: 14px 16px; border: 1px solid #e5e7eb; border-radius: 14px; background: #fbfdff; }
        .profile-label { display: block; margin-bottom: 6px; color: #6b7280; font-size: 13px; text-transform: uppercase; letter-spacing: 0.04em; }
        .profile-value { margin: 0; font-size: 16px; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 18px; margin-bottom: 24px; }
        .stat-card { background: #fff; border-radius: 16px; padding: 22px; box-shadow: 0 12px 30px rgba(15, 23, 42, 0.08); }
        .stat-card h3 { margin: 0 0 10px; color: #6b7280; font-size: 15px; font-weight: 600; }
        .stat-card .value { font-size: 34px; font-weight: 700; color: #111827; }
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: 14px 12px; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
        th { color: #6b7280; font-size: 13px; text-transform: uppercase; letter-spacing: 0.04em; }
        tbody tr:hover { background: #f8fbff; }
        .badge { display: inline-block; padding: 6px 10px; border-radius: 999px; font-size: 12px; font-weight: 700; text-transform: capitalize; }
        .pending { background: #fef3c7; color: #92400e; }
        .accepted { background: #dcfce7; color: #166534; }
        .rejected { background: #fee2e2; color: #991b1b; }
        .open { background: #dbeafe; color: #1d4ed8; }
        .in_progress { background: #fef3c7; color: #92400e; }
        .completed { background: #dcfce7; color: #166534; }
        .section-title { margin: 0 0 16px; font-size: 22px; }
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
        <?php renderAdminSidebar('developers'); ?>

        <main class="content">
            <div class="topbar">
                <div>
                    <h1 class="page-title">Developer Dashboard</h1>
                    <p class="topbar-text">Complete marketplace activity and performance details for this developer.</p>
                </div>
                <a class="back-btn" href="<?php echo htmlspecialchars(appUrl('admin/developers.php')); ?>">Back to Developers List</a>
            </div>

            <section class="panel">
                <h2 class="section-title">Developer Profile</h2>
                <div class="profile-grid">
                    <div class="profile-item">
                        <span class="profile-label">Name</span>
                        <p class="profile-value"><?php echo htmlspecialchars($developer['name']); ?></p>
                    </div>
                    <div class="profile-item">
                        <span class="profile-label">Email</span>
                        <p class="profile-value"><?php echo htmlspecialchars($developer['email']); ?></p>
                    </div>
                    <div class="profile-item">
                        <span class="profile-label">Skills</span>
                        <p class="profile-value"><?php echo htmlspecialchars($developer['skills'] ?: 'Not provided'); ?></p>
                    </div>
                    <div class="profile-item">
                        <span class="profile-label">Experience</span>
                        <p class="profile-value"><?php echo $developer['experience'] !== null ? (int) $developer['experience'] . ' years' : 'Not provided'; ?></p>
                    </div>
                    <div class="profile-item">
                        <span class="profile-label">Status</span>
                        <p class="profile-value"><?php echo htmlspecialchars($developer['availability'] ?: 'Unknown'); ?></p>
                    </div>
                    <div class="profile-item">
                        <span class="profile-label">Joined Date</span>
                        <p class="profile-value"><?php echo htmlspecialchars($developer['created_at']); ?></p>
                    </div>
                    <div class="profile-item">
                        <span class="profile-label">Bio</span>
                        <p class="profile-value"><?php echo htmlspecialchars($developer['bio'] ?: 'Not provided'); ?></p>
                    </div>
                    <div class="profile-item">
                        <span class="profile-label">GitHub Profile</span>
                        <p class="profile-value">
                            <?php if (!empty($developer['github_link'])): ?>
                                <a href="<?php echo htmlspecialchars($developer['github_link']); ?>" target="_blank" rel="noopener noreferrer"><?php echo htmlspecialchars($developer['github_link']); ?></a>
                            <?php else: ?>
                                Not provided
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
            </section>

            <section class="stats-grid">
                <div class="stat-card">
                    <h3>Total Projects Applied</h3>
                    <div class="value"><?php echo $stats['applied']; ?></div>
                </div>
                <div class="stat-card">
                    <h3>Projects Hired</h3>
                    <div class="value"><?php echo $stats['hired']; ?></div>
                </div>
                <div class="stat-card">
                    <h3>Projects Rejected</h3>
                    <div class="value"><?php echo $stats['rejected']; ?></div>
                </div>
                <div class="stat-card">
                    <h3>Projects In Progress</h3>
                    <div class="value"><?php echo $stats['in_progress']; ?></div>
                </div>
                <div class="stat-card">
                    <h3>Completed Projects</h3>
                    <div class="value"><?php echo $stats['completed']; ?></div>
                </div>
                <div class="stat-card">
                    <h3>Success Rate</h3>
                    <div class="value"><?php echo number_format($successRate, 2); ?>%</div>
                </div>
            </section>

            <section class="panel">
                <h2 class="section-title">Projects Applied</h2>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Project Title</th>
                                <th>Client Name</th>
                                <th>Budget</th>
                                <th>Application Status</th>
                                <th>Date Applied</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($appliedProjects->num_rows > 0): ?>
                                <?php while ($project = $appliedProjects->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($project['title']); ?></td>
                                        <td><?php echo htmlspecialchars($project['client_name']); ?></td>
                                        <td>$<?php echo number_format((float) $project['budget'], 2); ?></td>
                                        <td><span class="badge <?php echo htmlspecialchars($project['status']); ?>"><?php echo htmlspecialchars(ucfirst($project['status'])); ?></span></td>
                                        <td><?php echo htmlspecialchars($project['created_at']); ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5"><p class="empty-state">This developer has not applied to any marketplace projects yet.</p></td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="panel">
                <h2 class="section-title">Projects Currently Working On</h2>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Project Title</th>
                                <th>Client Name</th>
                                <th>Budget</th>
                                <th>Status</th>
                                <th>Start Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($currentProjects->num_rows > 0): ?>
                                <?php while ($project = $currentProjects->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($project['title']); ?></td>
                                        <td><?php echo htmlspecialchars($project['client_name']); ?></td>
                                        <td>$<?php echo number_format((float) $project['budget'], 2); ?></td>
                                        <td><span class="badge <?php echo htmlspecialchars($project['status']); ?>"><?php echo htmlspecialchars(str_replace('_', ' ', $project['status'])); ?></span></td>
                                        <td><?php echo htmlspecialchars($project['start_date']); ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5"><p class="empty-state">No active in-progress projects for this developer right now.</p></td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="panel">
                <h2 class="section-title">Clients Who Hired This Developer</h2>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Client Name</th>
                                <th>Project Title</th>
                                <th>Budget</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($hiredByClients->num_rows > 0): ?>
                                <?php while ($client = $hiredByClients->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($client['client_name']); ?></td>
                                        <td><?php echo htmlspecialchars($client['title']); ?></td>
                                        <td>$<?php echo number_format((float) $client['budget'], 2); ?></td>
                                        <td><span class="badge accepted"><?php echo htmlspecialchars(ucfirst($client['status'])); ?></span></td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4"><p class="empty-state">No clients have hired this developer through the marketplace yet.</p></td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="panel">
                <h2 class="section-title">Completed Projects</h2>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Project Title</th>
                                <th>Client</th>
                                <th>Budget</th>
                                <th>Completed Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($completedProjects->num_rows > 0): ?>
                                <?php while ($project = $completedProjects->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($project['title']); ?></td>
                                        <td><?php echo htmlspecialchars($project['client_name']); ?></td>
                                        <td>$<?php echo number_format((float) $project['budget'], 2); ?></td>
                                        <td><?php echo htmlspecialchars($project['completed_date']); ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4"><p class="empty-state">No completed projects found for this developer.</p></td>
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
