<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/admin_helpers.php';
require_once __DIR__ . '/../includes/admin_ui.php';

if (!isLoggedIn() || getUserRole() !== 'admin') {
    header('Location: ' . appUrl('login.php'));
    exit;
}

$successMessage = $_GET['success'] ?? '';
$errorMessage = $_GET['error'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if ($title === '' || $message === '') {
        $errorMessage = 'Title and message are required.';
    } else {
        $stmt = $conn->prepare(
            "INSERT INTO announcements (title, message, created_at)
             VALUES (?, ?, NOW())"
        );
        $stmt->bind_param('ss', $title, $message);
        $stmt->execute();
        adminLogAction($conn, (int) $_SESSION['user_id'], 'Announcement Created', $title);
        header('Location: ' . appUrl('admin/admin_announcements.php') . '?success=' . urlencode('Announcement created successfully.'));
        exit;
    }
}

$announcements = $conn->query(
    "SELECT id, title, message, created_at
     FROM announcements
     ORDER BY created_at DESC, id DESC"
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Announcements</title>
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
        .panel { background: #fff; border-radius: 16px; padding: 22px; box-shadow: 0 12px 30px rgba(15, 23, 42, 0.08); margin-bottom: 24px; }
        .alert { padding: 14px 16px; border-radius: 12px; margin-bottom: 20px; }
        .alert-success { background: #dcfce7; color: #166534; }
        .alert-error { background: #fee2e2; color: #991b1b; }
        .form-grid { display: grid; gap: 16px; }
        .btn { border: 0; border-radius: 10px; padding: 10px 14px; cursor: pointer; font-weight: 600; background: #2563eb; color: #fff; }
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: 14px 12px; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
        th { color: #6b7280; font-size: 13px; text-transform: uppercase; letter-spacing: 0.04em; }
        tbody tr:hover { background: #f8fbff; }
    </style>
</head>
<body>
    <div class="admin-layout">
        <?php renderAdminSidebar('announcements'); ?>
        <main class="content">
            <div class="topbar">
                <div>
                    <h1 class="page-title">Announcements</h1>
                    <p class="topbar-text">Publish updates that appear on client and developer dashboards.</p>
                </div>
                <a class="logout-btn" href="<?php echo htmlspecialchars(appUrl('admin/logout.php')); ?>">Logout</a>
            </div>
            <?php if ($successMessage !== ''): ?><div class="alert alert-success"><?php echo htmlspecialchars($successMessage); ?></div><?php endif; ?>
            <?php if ($errorMessage !== ''): ?><div class="alert alert-error"><?php echo htmlspecialchars($errorMessage); ?></div><?php endif; ?>
            <section class="panel">
                <h2 style="margin-top: 0;">Create Announcement</h2>
                <form method="post" class="form-grid">
                    <div>
                        <label for="title">Title</label>
                        <input id="title" name="title" class="form-control" required>
                    </div>
                    <div>
                        <label for="message">Message</label>
                        <textarea id="message" name="message" class="form-control" rows="5" required></textarea>
                    </div>
                    <div><button type="submit" class="btn">Publish Announcement</button></div>
                </form>
            </section>
            <section class="panel">
                <h2 style="margin-top: 0;">Recent Announcements</h2>
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>Title</th><th>Message</th><th>Created</th></tr></thead>
                        <tbody>
                            <?php if ($announcements->num_rows > 0): ?>
                                <?php while ($announcement = $announcements->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($announcement['title']); ?></td>
                                        <td><?php echo nl2br(htmlspecialchars($announcement['message'])); ?></td>
                                        <td><?php echo htmlspecialchars($announcement['created_at']); ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="3">No announcements found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </main>
    </div>
</body>
</html>
