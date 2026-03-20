<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

$developerId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($developerId <= 0) {
    header('Location: ' . appUrl('admin/developers.php'));
    exit;
}

$developerStmt = $conn->prepare("SELECT * FROM users WHERE id = ? AND role = 'developer'");
$developerStmt->bind_param('i', $developerId);
$developerStmt->execute();
$developer = $developerStmt->get_result()->fetch_assoc();

if (!$developer) {
    header('Location: ' . appUrl('admin/developers.php') . '?error=' . urlencode('Developer not found.'));
    exit;
}

$profileStmt = $conn->prepare("SELECT * FROM developers WHERE user_id = ?");
$profileStmt->bind_param('i', $developerId);
$profileStmt->execute();
$profile = $profileStmt->get_result()->fetch_assoc();
$githubProfile = $profile['github_link'] ?? $profile['github'] ?? '';

function getDeveloperStat(mysqli $conn, int $developerId, string $condition = ''): int
{
    $sql = "SELECT COUNT(*) AS total FROM hire_requests WHERE developer_id = ?";
    if ($condition !== '') {
        $sql .= " AND " . $condition;
    }

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $developerId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    return (int) ($row['total'] ?? 0);
}

$stats = [
    'applications' => getDeveloperStat($conn, $developerId),
    'accepted' => getDeveloperStat($conn, $developerId, "status = 'accepted'"),
    'rejected' => getDeveloperStat($conn, $developerId, "status = 'rejected'"),
    'pending' => getDeveloperStat($conn, $developerId, "status = 'pending'"),
];

$projectsStmt = $conn->prepare(
    "SELECT p.title, p.status, p.created_at
     FROM hire_requests hr
     INNER JOIN projects p ON p.id = hr.project_id
     WHERE hr.developer_id = ?
       AND hr.status = 'accepted'
     ORDER BY p.created_at DESC"
);
$projectsStmt->bind_param('i', $developerId);
$projectsStmt->execute();
$projects = $projectsStmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Developer Profile Analytics</title>
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
        .content { flex: 1; padding: 28px; }
        .topbar { display: flex; justify-content: space-between; align-items: center; gap: 16px; margin-bottom: 24px; flex-wrap: wrap; }
        .page-title { margin: 0; font-size: 30px; }
        .topbar-text { margin: 6px 0 0; color: #6b7280; }
        .logout-btn { background: #dc2626; color: #fff; padding: 10px 16px; border-radius: 10px; font-weight: 600; }
        .panel { background: #fff; border-radius: 16px; padding: 22px; box-shadow: 0 12px 30px rgba(15, 23, 42, 0.08); }
        .profile-grid { display: grid; grid-template-columns: minmax(0, 1.2fr) minmax(0, 1fr); gap: 22px; margin-bottom: 24px; }
        .profile-list { display: grid; gap: 14px; }
        .profile-item { border-bottom: 1px solid #e5e7eb; padding-bottom: 12px; }
        .profile-item:last-child { border-bottom: 0; padding-bottom: 0; }
        .profile-label { display: block; color: #6b7280; font-size: 13px; text-transform: uppercase; letter-spacing: 0.04em; margin-bottom: 6px; }
        .profile-value { margin: 0; font-size: 16px; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 18px; margin-bottom: 24px; }
        .stat-card { background: #fff; border-radius: 16px; padding: 22px; box-shadow: 0 12px 30px rgba(15, 23, 42, 0.08); }
        .stat-label { margin: 0 0 8px; color: #6b7280; font-size: 14px; }
        .stat-value { margin: 0; font-size: 34px; font-weight: 700; color: #111827; }
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: 14px 12px; border-bottom: 1px solid #e5e7eb; }
        th { color: #6b7280; font-size: 13px; text-transform: uppercase; letter-spacing: 0.04em; }
        .badge { display: inline-block; padding: 6px 10px; border-radius: 999px; font-size: 12px; font-weight: 700; text-transform: capitalize; }
        .open { background: #dbeafe; color: #1d4ed8; }
        .in_progress { background: #fef3c7; color: #92400e; }
        .completed { background: #dcfce7; color: #166534; }
        .empty-state { color: #6b7280; margin: 0; }
        @media (max-width: 960px) {
            .admin-layout { flex-direction: column; }
            .sidebar { width: 100%; }
            .content { padding: 20px; }
            .profile-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="admin-layout">
        <aside class="sidebar">
            <div class="brand">Admin Panel</div>
            <div class="brand-subtitle">PHPDevHub control center</div>
            <a class="nav-link" href="<?php echo htmlspecialchars(appUrl('admin/dashboard.php')); ?>">Dashboard</a>
            <a class="nav-link active" href="<?php echo htmlspecialchars(appUrl('admin/developers.php')); ?>">Developers</a>
            <a class="nav-link" href="<?php echo htmlspecialchars(appUrl('admin/clients.php')); ?>">Clients</a>
            <a class="nav-link" href="<?php echo htmlspecialchars(appUrl('admin/projects.php')); ?>">Projects</a>
            <a class="nav-link" href="<?php echo htmlspecialchars(appUrl('admin/tasks.php')); ?>">Tasks</a>
            <a class="nav-link" href="<?php echo htmlspecialchars(appUrl('admin/logout.php')); ?>">Logout</a>
        </aside>

        <main class="content">
            <div class="topbar">
                <div>
                    <h1 class="page-title">Developer Profile</h1>
                    <p class="topbar-text">View profile details, marketplace application analytics, and current projects.</p>
                </div>
                <a class="logout-btn" href="<?php echo htmlspecialchars(appUrl('admin/developers.php')); ?>">Back to Developers</a>
            </div>

            <section class="profile-grid">
                <div class="panel">
                    <h2 style="margin-top: 0;">Basic Information</h2>
                    <div class="profile-list">
                        <div class="profile-item">
                            <span class="profile-label">Name</span>
                            <p class="profile-value"><?php echo htmlspecialchars($developer['name']); ?></p>
                        </div>
                        <div class="profile-item">
                            <span class="profile-label">Email</span>
                            <p class="profile-value"><?php echo htmlspecialchars($developer['email']); ?></p>
                        </div>
                        <div class="profile-item">
                            <span class="profile-label">Role</span>
                            <p class="profile-value"><?php echo htmlspecialchars($developer['role']); ?></p>
                        </div>
                        <div class="profile-item">
                            <span class="profile-label">Joined Date</span>
                            <p class="profile-value"><?php echo htmlspecialchars($developer['created_at']); ?></p>
                        </div>
                    </div>
                </div>

                <div class="panel">
                    <h2 style="margin-top: 0;">Developer Details</h2>
                    <div class="profile-list">
                        <div class="profile-item">
                            <span class="profile-label">Skills</span>
                            <p class="profile-value"><?php echo htmlspecialchars($profile['skills'] ?? 'Not provided'); ?></p>
                        </div>
                        <div class="profile-item">
                            <span class="profile-label">Experience</span>
                            <p class="profile-value"><?php echo isset($profile['experience']) && $profile['experience'] !== null ? (int) $profile['experience'] . ' years' : 'Not provided'; ?></p>
                        </div>
                        <div class="profile-item">
                            <span class="profile-label">Bio</span>
                            <p class="profile-value"><?php echo htmlspecialchars($profile['bio'] ?? 'Not provided'); ?></p>
                        </div>
                        <div class="profile-item">
                            <span class="profile-label">GitHub Profile</span>
                            <p class="profile-value">
                                <?php if ($githubProfile !== ''): ?>
                                    <a href="<?php echo htmlspecialchars($githubProfile); ?>" target="_blank" rel="noopener noreferrer">
                                        <?php echo htmlspecialchars($githubProfile); ?>
                                    </a>
                                <?php else: ?>
                                    Not provided
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                </div>
            </section>

            <section class="stats-grid">
                <div class="stat-card">
                    <p class="stat-label">Total Applications</p>
                    <p class="stat-value"><?php echo $stats['applications']; ?></p>
                </div>
                <div class="stat-card">
                    <p class="stat-label">Accepted Projects</p>
                    <p class="stat-value"><?php echo $stats['accepted']; ?></p>
                </div>
                <div class="stat-card">
                    <p class="stat-label">Rejected Applications</p>
                    <p class="stat-value"><?php echo $stats['rejected']; ?></p>
                </div>
                <div class="stat-card">
                    <p class="stat-label">Pending Applications</p>
                    <p class="stat-value"><?php echo $stats['pending']; ?></p>
                </div>
            </section>

            <section class="panel">
                <h2 style="margin-top: 0;">Current Projects</h2>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Project Title</th>
                                <th>Status</th>
                                <th>Created Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($projects->num_rows > 0): ?>
                                <?php while ($project = $projects->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($project['title']); ?></td>
                                        <td>
                                            <span class="badge <?php echo htmlspecialchars($project['status']); ?>">
                                                <?php echo htmlspecialchars(str_replace('_', ' ', $project['status'])); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($project['created_at']); ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="3"><p class="empty-state">This developer has no accepted projects yet.</p></td>
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
