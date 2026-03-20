<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/admin_helpers.php';
require_once __DIR__ . '/../includes/admin_ui.php';

if (!isLoggedIn() || getUserRole() !== 'admin') {
    header('Location: ' . appUrl('login.php'));
    exit;
}

function deleteClient(mysqli $conn, int $clientId): bool
{
    $conn->begin_transaction();

    try {
        $hireRequestIds = [];
        $hireStmt = $conn->prepare("SELECT id FROM hire_requests WHERE client_id = ?");
        $hireStmt->bind_param('i', $clientId);
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

        $deleteHireStmt = $conn->prepare("DELETE FROM hire_requests WHERE client_id = ?");
        $deleteHireStmt->bind_param('i', $clientId);
        $deleteHireStmt->execute();

        $deleteProjectStmt = $conn->prepare("DELETE FROM projects WHERE client_id = ?");
        $deleteProjectStmt->bind_param('i', $clientId);
        $deleteProjectStmt->execute();

        $deleteUserStmt = $conn->prepare("DELETE FROM users WHERE id = ? AND role = 'client'");
        $deleteUserStmt->bind_param('i', $clientId);
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
    $deleted = $deleteId > 0 ? deleteClient($conn, $deleteId) : false;

    if ($deleted) {
        adminLogAction($conn, (int) $_SESSION['user_id'], 'Client Deleted', 'Client #' . $deleteId);
        header('Location: ' . appUrl('admin/clients.php') . '?success=' . urlencode('Client deleted successfully.'));
    } else {
        header('Location: ' . appUrl('admin/clients.php') . '?error=' . urlencode('Unable to delete client.'));
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['client_id'], $_POST['account_action'])) {
    $clientId = (int) $_POST['client_id'];
    $accountAction = $_POST['account_action'];

    if ($clientId > 0 && in_array($accountAction, ['suspended', 'banned', 'active'], true)) {
        $stmt = $conn->prepare("UPDATE users SET account_status = ? WHERE id = ? AND role = 'client'");
        $stmt->bind_param('si', $accountAction, $clientId);
        $stmt->execute();

        if ($stmt->affected_rows >= 0) {
            adminLogAction($conn, (int) $_SESSION['user_id'], 'Client ' . ucfirst($accountAction), 'Client #' . $clientId);
            header('Location: ' . appUrl('admin/clients.php') . '?success=' . urlencode('Client status updated.'));
            exit;
        }
    }

    header('Location: ' . appUrl('admin/clients.php') . '?error=' . urlencode('Unable to update client status.'));
    exit;
}

$successMessage = $_GET['success'] ?? '';
$errorMessage = $_GET['error'] ?? '';

$clients = $conn->query(
    "SELECT id, name, email, created_at, account_status
     FROM users
     WHERE role = 'client'
     ORDER BY created_at DESC"
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Clients</title>
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
        th, td { text-align: left; padding: 14px 12px; border-bottom: 1px solid #e5e7eb; }
        th { color: #6b7280; font-size: 13px; text-transform: uppercase; letter-spacing: 0.04em; }
        tbody tr:hover { background: #f8fbff; }
        .status-badge { display: inline-block; padding: 6px 10px; border-radius: 999px; font-size: 12px; font-weight: 700; text-transform: capitalize; }
        .status-active { background: #dcfce7; color: #166534; }
        .status-suspended { background: #fef3c7; color: #92400e; }
        .status-banned { background: #fee2e2; color: #991b1b; }
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
            min-width: 200px;
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            box-shadow: 0 18px 40px rgba(15, 23, 42, 0.14);
            padding: 8px;
            z-index: 20;
            display: none;
        }
        .actions-menu.open .actions-dropdown { display: block; }
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
        <?php renderAdminSidebar('clients'); ?>

        <main class="content">
            <div class="topbar">
                <div>
                    <h1 class="page-title">Clients</h1>
                    <p class="topbar-text">Manage all client accounts from one place.</p>
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
                                <th>SN</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Status</th>
                                <th>Joined</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($clients->num_rows > 0): ?>
                                <?php $sn = 1; ?>
                                <?php while ($client = $clients->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo $sn++; ?></td>
                                        <td><?php echo htmlspecialchars($client['name']); ?></td>
                                        <td><?php echo htmlspecialchars($client['email']); ?></td>
                                        <td><span class="status-badge status-<?php echo htmlspecialchars($client['account_status'] ?: 'active'); ?>"><?php echo htmlspecialchars($client['account_status'] ?: 'active'); ?></span></td>
                                        <td><?php echo htmlspecialchars($client['created_at']); ?></td>
                                        <td class="actions-cell">
                                            <div class="actions-menu">
                                                <button type="button" class="actions-toggle" aria-label="Actions menu">⋮</button>
                                                <div class="actions-dropdown">
                                                    <form method="post" class="dropdown-form">
                                                        <input type="hidden" name="client_id" value="<?php echo (int) $client['id']; ?>">
                                                        <button type="submit" name="account_action" value="suspended" class="dropdown-button">Suspend Account</button>
                                                    </form>
                                                    <form method="post" class="dropdown-form">
                                                        <input type="hidden" name="client_id" value="<?php echo (int) $client['id']; ?>">
                                                        <button type="submit" name="account_action" value="banned" class="dropdown-button">Ban Account</button>
                                                    </form>
                                                    <form method="post" class="dropdown-form" onsubmit="return confirm('Delete this client account?');">
                                                        <input type="hidden" name="delete_id" value="<?php echo (int) $client['id']; ?>">
                                                        <button type="submit" class="dropdown-button dropdown-danger">Delete Account</button>
                                                    </form>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6"><p class="empty-state">No clients found.</p></td>
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
