<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/admin_helpers.php';
require_once __DIR__ . '/../includes/admin_ui.php';

if (!isLoggedIn() || getUserRole() !== 'admin') {
    header('Location: ' . appUrl('login.php'));
    exit;
}

function deleteDeveloper(mysqli $conn, int $developerId): bool
{
    $conn->begin_transaction();

    try {
        $hireRequestIds = [];
        $hireStmt = $conn->prepare("SELECT id FROM hire_requests WHERE developer_id = ?");
        $hireStmt->bind_param('i', $developerId);
        $hireStmt->execute();
        $hireResult = $hireStmt->get_result();

        while ($row = $hireResult->fetch_assoc()) {
            $hireRequestIds[] = (int) $row['id'];
        }

        if ($hireRequestIds !== []) {
            $messageStmt = $conn->prepare("DELETE FROM messages WHERE hire_request_id = ?");
            foreach ($hireRequestIds as $hireRequestId) {
                $messageStmt->bind_param('i', $hireRequestId);
                $messageStmt->execute();
            }
        }

        $deleteHireStmt = $conn->prepare("DELETE FROM hire_requests WHERE developer_id = ?");
        $deleteHireStmt->bind_param('i', $developerId);
        $deleteHireStmt->execute();

        $deleteProfileStmt = $conn->prepare("DELETE FROM developers WHERE user_id = ?");
        $deleteProfileStmt->bind_param('i', $developerId);
        $deleteProfileStmt->execute();

        $deleteUserStmt = $conn->prepare("DELETE FROM users WHERE id = ? AND role = 'developer'");
        $deleteUserStmt->bind_param('i', $developerId);
        $deleteUserStmt->execute();

        $conn->commit();
        return $deleteUserStmt->affected_rows > 0;
    } catch (mysqli_sql_exception $e) {
        $conn->rollback();
        return false;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $deleteId = (int) $_POST['delete_id'];
    $deleted = $deleteId > 0 ? deleteDeveloper($conn, $deleteId) : false;

    if ($deleted) {
        adminLogAction($conn, (int) $_SESSION['user_id'], 'Developer Deleted', 'Developer #' . $deleteId);
        header('Location: ' . appUrl('admin/developers.php') . '?success=' . urlencode('Developer deleted successfully.'));
    } else {
        header('Location: ' . appUrl('admin/developers.php') . '?error=' . urlencode('Unable to delete developer.'));
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['developer_id'], $_POST['account_action'])) {
    $developerId = (int) $_POST['developer_id'];
    $accountAction = $_POST['account_action'];

    if ($developerId > 0 && in_array($accountAction, ['verify', 'suspended', 'banned', 'active'], true)) {
        if ($accountAction === 'verify') {
            $stmt = $conn->prepare("UPDATE developers SET is_verified = 1 WHERE user_id = ?");
            $stmt->bind_param('i', $developerId);
            $stmt->execute();
            adminLogAction($conn, (int) $_SESSION['user_id'], 'Developer Verified', 'Developer #' . $developerId);
        } else {
            $stmt = $conn->prepare("UPDATE users SET account_status = ? WHERE id = ? AND role = 'developer'");
            $stmt->bind_param('si', $accountAction, $developerId);
            $stmt->execute();
            adminLogAction($conn, (int) $_SESSION['user_id'], 'Developer ' . ucfirst($accountAction), 'Developer #' . $developerId);
        }

        header('Location: ' . appUrl('admin/developers.php') . '?success=' . urlencode('Developer updated successfully.'));
        exit;
    }

    header('Location: ' . appUrl('admin/developers.php') . '?error=' . urlencode('Unable to update developer.'));
    exit;
}

$successMessage = $_GET['success'] ?? '';
$errorMessage = $_GET['error'] ?? '';

$skillFilter = trim($_GET['skill'] ?? '');
$experienceFilter = trim($_GET['experience'] ?? '');
$availabilityFilter = trim($_GET['availability'] ?? '');
$verifiedFilter = trim($_GET['verified'] ?? '');

$developerSql = "SELECT u.id, u.name, u.email, u.created_at, u.account_status, d.skills, d.experience, d.availability, d.is_verified
                 FROM users u
                 LEFT JOIN developers d ON d.user_id = u.id
                 WHERE u.role = 'developer'";
$types = '';
$params = [];

if ($skillFilter !== '') {
    $developerSql .= " AND d.skills LIKE ?";
    $types .= 's';
    $params[] = '%' . $skillFilter . '%';
}

if ($experienceFilter !== '' && ctype_digit($experienceFilter)) {
    $developerSql .= " AND d.experience >= ?";
    $types .= 'i';
    $params[] = (int) $experienceFilter;
}

if ($availabilityFilter !== '') {
    $developerSql .= " AND d.availability = ?";
    $types .= 's';
    $params[] = $availabilityFilter;
}

if ($verifiedFilter === '1') {
    $developerSql .= " AND d.is_verified = 1";
}

$developerSql .= " ORDER BY u.created_at DESC";

$developerStmt = $conn->prepare($developerSql);
if ($types !== '') {
    $developerStmt->bind_param($types, ...$params);
}
$developerStmt->execute();
$developers = $developerStmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Developers</title>
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
        .alert { padding: 14px 16px; border-radius: 12px; margin-bottom: 20px; }
        .alert-success { background: #dcfce7; color: #166534; }
        .alert-error { background: #fee2e2; color: #991b1b; }
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: 14px 12px; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
        th { color: #6b7280; font-size: 13px; text-transform: uppercase; letter-spacing: 0.04em; }
        tbody tr:hover { background: #f8fbff; }
        .status-badge { display: inline-block; padding: 6px 10px; border-radius: 999px; font-size: 12px; font-weight: 700; text-transform: capitalize; }
        .status-active { background: #dcfce7; color: #166534; }
        .status-suspended { background: #fef3c7; color: #92400e; }
        .status-banned { background: #fee2e2; color: #991b1b; }
        .availability-badge { background: #dbeafe; color: #1d4ed8; }
        .verified-badge { display: inline-block; padding: 6px 10px; border-radius: 999px; background: #dcfce7; color: #166534; font-size: 12px; font-weight: 700; }
        .btn { border: 0; border-radius: 10px; padding: 10px 14px; cursor: pointer; font-weight: 600; }
        .btn-view { background: #2563eb; color: #fff; }
        .btn-delete { background: #dc2626; color: #fff; }
        .developer-link { color: #1d4ed8; font-weight: 700; }
        .developer-link:hover { text-decoration: underline; }
        .filter-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 14px; margin-bottom: 18px; }
        .filter-actions { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 10px; }
        .name-stack { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
        .badge-stack { display: flex; gap: 8px; flex-wrap: wrap; }
        .actions-cell { text-align: right; }
        .actions-menu { position: relative; display: inline-block; }
        tr .actions-menu { opacity: 0; pointer-events: none; transition: opacity 0.18s ease; }
        tr:hover .actions-menu, tr .actions-menu.open { opacity: 1; pointer-events: auto; }
        .actions-toggle {
            border: 1px solid #d1d5db;
            background: #fff;
            color: #374151;
            width: 40px;
            height: 40px;
            border-radius: 10px;
            font-size: 22px;
            line-height: 1;
            cursor: pointer;
        }
        .actions-toggle:hover { background: #f8fafc; }
        .actions-dropdown {
            position: absolute;
            right: 0;
            top: calc(100% + 8px);
            min-width: 220px;
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            box-shadow: 0 18px 40px rgba(15, 23, 42, 0.14);
            padding: 8px;
            z-index: 20;
            display: none;
        }
        .actions-menu.open .actions-dropdown { display: block; }
        .dropdown-link,
        .dropdown-button {
            display: block;
            width: 100%;
            padding: 10px 12px;
            border: 0;
            background: transparent;
            color: #1f2937;
            text-align: left;
            border-radius: 10px;
            font-size: 14px;
            cursor: pointer;
        }
        .dropdown-link:hover,
        .dropdown-button:hover { background: #f3f4f6; }
        .dropdown-danger { color: #b91c1c; }
        .dropdown-form { margin: 0; }
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
                    <h1 class="page-title">Developers</h1>
                    <p class="topbar-text">Review all registered PHP developers and manage their accounts.</p>
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
                <form method="get">
                    <div class="filter-grid">
                        <div>
                            <label for="skill">Skill</label>
                            <input id="skill" name="skill" class="form-control" value="<?php echo htmlspecialchars($skillFilter); ?>">
                        </div>
                        <div>
                            <label for="experience">Minimum Experience</label>
                            <input id="experience" name="experience" type="number" min="0" class="form-control" value="<?php echo htmlspecialchars($experienceFilter); ?>">
                        </div>
                        <div>
                            <label for="availability">Availability</label>
                            <input id="availability" name="availability" class="form-control" value="<?php echo htmlspecialchars($availabilityFilter); ?>">
                        </div>
                        <div>
                            <label for="verified">Verified Developers</label>
                            <select id="verified" name="verified" class="form-select">
                                <option value="">All</option>
                                <option value="1" <?php echo $verifiedFilter === '1' ? 'selected' : ''; ?>>Verified Only</option>
                            </select>
                        </div>
                    </div>
                    <div class="filter-actions">
                        <button type="submit" class="btn btn-view">Apply Filters</button>
                        <a class="btn btn-delete" href="<?php echo htmlspecialchars(appUrl('admin/developers.php')); ?>">Reset</a>
                    </div>
                </form>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>SN</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Skills</th>
                                <th>Experience</th>
                                <th>Status</th>
                                <th>Joined</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($developers->num_rows > 0): ?>
                                <?php $sn = 1; ?>
                                <?php while ($developer = $developers->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo $sn++; ?></td>
                                        <td>
                                            <div class="name-stack">
                                                <a class="developer-link" href="<?php echo htmlspecialchars(appUrl('admin/admin_developer_details.php')); ?>?developer_id=<?php echo (int) $developer['id']; ?>">
                                                    <?php echo htmlspecialchars($developer['name']); ?>
                                                </a>
                                            </div>
                                        </td>
                                        <td><?php echo htmlspecialchars($developer['email']); ?></td>
                                        <td><?php echo htmlspecialchars($developer['skills'] ?: 'N/A'); ?></td>
                                        <td><?php echo $developer['experience'] !== null ? (int) $developer['experience'] . ' years' : 'N/A'; ?></td>
                                        <td>
                                            <div class="badge-stack">
                                                <span class="status-badge availability-badge"><?php echo htmlspecialchars($developer['availability'] ?: 'Unknown'); ?></span>
                                                <span class="status-badge status-<?php echo htmlspecialchars($developer['account_status'] ?: 'active'); ?>"><?php echo htmlspecialchars($developer['account_status'] ?: 'active'); ?></span>
                                                <?php if (!empty($developer['is_verified'])): ?>
                                                    <span class="verified-badge">Verified</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td><?php echo htmlspecialchars($developer['created_at']); ?></td>
                                        <td class="actions-cell">
                                            <div class="actions-menu">
                                                <button type="button" class="actions-toggle" aria-label="Actions menu">⋮</button>
                                                <div class="actions-dropdown">
                                                    <a class="dropdown-link" href="<?php echo htmlspecialchars(appUrl('developers/profile.php')); ?>?id=<?php echo (int) $developer['id']; ?>">View Profile</a>
                                                    <a class="dropdown-link" href="<?php echo htmlspecialchars(appUrl('admin/admin_developer_details.php')); ?>?developer_id=<?php echo (int) $developer['id']; ?>">View Developer Dashboard</a>
                                                    <?php if (empty($developer['is_verified'])): ?>
                                                        <form method="post" class="dropdown-form">
                                                            <input type="hidden" name="developer_id" value="<?php echo (int) $developer['id']; ?>">
                                                            <button type="submit" name="account_action" value="verify" class="dropdown-button">Verify Developer</button>
                                                        </form>
                                                    <?php endif; ?>
                                                    <form method="post" class="dropdown-form">
                                                        <input type="hidden" name="developer_id" value="<?php echo (int) $developer['id']; ?>">
                                                        <button type="submit" name="account_action" value="suspended" class="dropdown-button">Suspend Account</button>
                                                    </form>
                                                    <form method="post" class="dropdown-form">
                                                        <input type="hidden" name="developer_id" value="<?php echo (int) $developer['id']; ?>">
                                                        <button type="submit" name="account_action" value="banned" class="dropdown-button">Ban Account</button>
                                                    </form>
                                                    <form method="post" class="dropdown-form" onsubmit="return confirm('Delete this developer account?');">
                                                        <input type="hidden" name="delete_id" value="<?php echo (int) $developer['id']; ?>">
                                                        <button type="submit" class="dropdown-button dropdown-danger">Delete Account</button>
                                                    </form>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8"><p class="empty-state">No developers found.</p></td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </main>
    </div>
    <script>
        (function () {
            const menus = document.querySelectorAll('.actions-menu');

            menus.forEach(function (menu) {
                const toggle = menu.querySelector('.actions-toggle');
                toggle.addEventListener('click', function (event) {
                    event.stopPropagation();
                    menus.forEach(function (otherMenu) {
                        if (otherMenu !== menu) {
                            otherMenu.classList.remove('open');
                        }
                    });
                    menu.classList.toggle('open');
                });
            });

            document.addEventListener('click', function () {
                menus.forEach(function (menu) {
                    menu.classList.remove('open');
                });
            });
        })();
    </script>
</body>
</html>
