<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/admin_helpers.php';
require_once __DIR__ . '/../includes/admin_ui.php';

if (!isLoggedIn() || getUserRole() !== 'admin') {
    header('Location: ' . appUrl('login.php'));
    exit;
}

$allowedStatuses = ['under_review', 'resolved', 'closed'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $disputeId = (int) ($_POST['dispute_id'] ?? 0);
    $status = $_POST['status'] ?? '';
    $adminNote = trim($_POST['admin_note'] ?? '');

    if ($disputeId > 0 && in_array($status, $allowedStatuses, true)) {
        $stmt = $conn->prepare("UPDATE disputes SET status = ?, admin_note = ? WHERE id = ?");
        $stmt->bind_param('ssi', $status, $adminNote, $disputeId);
        $stmt->execute();
        adminLogAction($conn, (int) $_SESSION['user_id'], 'Dispute ' . ucwords(str_replace('_', ' ', $status)), 'Dispute #' . $disputeId);
        header('Location: ' . appUrl('admin/admin_disputes.php') . '?success=' . urlencode('Dispute updated.'));
        exit;
    }
}

$successMessage = $_GET['success'] ?? '';
$disputes = $conn->query(
    "SELECT d.id,
            p.title AS project_title,
            c.name AS client_name,
            u.name AS developer_name,
            COALESCE(d.reason, 'General dispute') AS reason,
            COALESCE(d.description, d.complaint, '') AS description,
            d.status,
            d.admin_note,
            d.created_at
     FROM disputes d
     INNER JOIN projects p ON p.id = d.project_id
     INNER JOIN users c ON c.id = d.client_id
     INNER JOIN users u ON u.id = d.developer_id
     ORDER BY d.created_at DESC, d.id DESC"
);

$responses = [];
$responseResult = $conn->query(
    "SELECT dr.dispute_id, dr.response, dr.created_at, u.name
     FROM dispute_responses dr
     INNER JOIN users u ON u.id = dr.user_id
     ORDER BY dr.created_at DESC, dr.id DESC"
);
while ($row = $responseResult->fetch_assoc()) {
    $responses[(int) $row['dispute_id']][] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Disputes</title>
    <link rel="stylesheet" href="<?php echo htmlspecialchars(appUrl('assets/css/style.css')); ?>">
    <style>
        * { box-sizing: border-box; } body { margin: 0; font-family: Arial, sans-serif; background: #f4f7fb; color: #1f2937; } a { text-decoration: none; }
        .admin-layout { min-height: 100vh; display: flex; } .sidebar { width: 260px; background: #111827; color: #fff; padding: 24px 18px; }
        .brand { font-size: 24px; font-weight: 700; margin-bottom: 8px; } .brand-subtitle { color: #9ca3af; font-size: 14px; margin-bottom: 26px; }
        .nav-link { display: block; color: #d1d5db; padding: 12px 14px; border-radius: 10px; margin-bottom: 8px; } .nav-link:hover, .nav-link.active { background: #2563eb; color: #fff; }
        .sidebar-section{margin-bottom:20px}.sidebar-heading{color:#6b7280;font-size:11px;font-weight:700;letter-spacing:.12em;margin:0 0 10px 12px}
        .content { flex: 1; padding: 28px; } .topbar { display: flex; justify-content: space-between; align-items: center; gap: 16px; margin-bottom: 24px; flex-wrap: wrap; }
        .page-title { margin: 0; font-size: 30px; } .topbar-text { margin: 6px 0 0; color: #6b7280; } .logout-btn { background: #dc2626; color: #fff; padding: 10px 16px; border-radius: 10px; font-weight: 600; }
        .panel { background: #fff; border-radius: 16px; padding: 22px; box-shadow: 0 12px 30px rgba(15, 23, 42, 0.08); margin-bottom: 18px; }
        .alert { padding: 14px 16px; border-radius: 12px; margin-bottom: 20px; background: #dcfce7; color: #166534; }
        .table-wrap { overflow-x: auto; } table { width: 100%; border-collapse: collapse; } th, td { text-align: left; padding: 14px 12px; border-bottom: 1px solid #e5e7eb; vertical-align: top; } th { color: #6b7280; font-size: 13px; text-transform: uppercase; letter-spacing: 0.04em; } tbody tr:hover{background:#f8fbff}
        .badge { display: inline-block; padding: 6px 10px; border-radius: 999px; font-size: 12px; font-weight: 700; text-transform: capitalize; background: #e5e7eb; color: #374151; }
        .badge.warning { background: #fef3c7; color: #92400e; } .badge.success { background: #dcfce7; color: #166534; } .badge.danger { background: #fee2e2; color: #991b1b; }
        .form-grid { display: grid; gap: 10px; } .form-control, .form-select { width: 100%; border: 1px solid #d1d5db; border-radius: 10px; padding: 10px 12px; }
        .btn { border: 0; border-radius: 10px; padding: 9px 12px; cursor: pointer; font-weight: 600; color: #fff; background: #2563eb; }
        .response-box { background: #f8fafc; border: 1px solid #e5e7eb; border-radius: 12px; padding: 10px 12px; margin-top: 10px; }
    </style>
</head>
<body>
<div class="admin-layout"><?php renderAdminSidebar('disputes'); ?><main class="content">
    <div class="topbar"><div><h1 class="page-title">Disputes</h1><p class="topbar-text">Review marketplace disputes, developer responses, and update case status.</p></div><a class="logout-btn" href="<?php echo htmlspecialchars(appUrl('admin/logout.php')); ?>">Logout</a></div>
    <?php if ($successMessage !== ''): ?><div class="alert"><?php echo htmlspecialchars($successMessage); ?></div><?php endif; ?>
    <section class="panel"><div class="table-wrap"><table><thead><tr><th>ID</th><th>Project</th><th>Client</th><th>Developer</th><th>Details</th><th>Status</th><th>Admin Action</th></tr></thead><tbody>
    <?php if ($disputes->num_rows > 0): while ($dispute = $disputes->fetch_assoc()): ?>
        <?php $badgeClass = $dispute['status'] === 'resolved' ? 'success' : ($dispute['status'] === 'closed' ? 'danger' : 'warning'); ?>
        <tr>
            <td><?php echo (int) $dispute['id']; ?></td>
            <td><?php echo htmlspecialchars($dispute['project_title']); ?></td>
            <td><?php echo htmlspecialchars($dispute['client_name']); ?></td>
            <td><?php echo htmlspecialchars($dispute['developer_name']); ?></td>
            <td>
                <strong><?php echo htmlspecialchars($dispute['reason']); ?></strong>
                <p style="margin:8px 0 0;"><?php echo nl2br(htmlspecialchars($dispute['description'])); ?></p>
                <?php if (!empty($dispute['admin_note'])): ?>
                    <div class="response-box"><strong>Admin note:</strong> <?php echo nl2br(htmlspecialchars($dispute['admin_note'])); ?></div>
                <?php endif; ?>
                <?php foreach ($responses[(int) $dispute['id']] ?? [] as $response): ?>
                    <div class="response-box">
                        <strong><?php echo htmlspecialchars($response['name']); ?>:</strong>
                        <?php echo nl2br(htmlspecialchars($response['response'])); ?>
                    </div>
                <?php endforeach; ?>
            </td>
            <td><span class="badge <?php echo $badgeClass; ?>"><?php echo htmlspecialchars(str_replace('_', ' ', $dispute['status'])); ?></span></td>
            <td>
                <form method="post" class="form-grid">
                    <input type="hidden" name="dispute_id" value="<?php echo (int) $dispute['id']; ?>">
                    <select name="status" class="form-select" required>
                        <?php foreach ($allowedStatuses as $status): ?>
                            <option value="<?php echo htmlspecialchars($status); ?>" <?php echo $dispute['status'] === $status ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $status))); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <textarea name="admin_note" rows="3" class="form-control" placeholder="Add a resolution update for client and developer."><?php echo htmlspecialchars((string) $dispute['admin_note']); ?></textarea>
                    <button class="btn" type="submit">Save Update</button>
                </form>
            </td>
        </tr>
    <?php endwhile; else: ?><tr><td colspan="7"><p class="empty-state">No disputes yet. Client complaints and resolution cases will appear here when support intervention is needed.</p></td></tr><?php endif; ?>
    </tbody></table></div></section>
</main></div>
</body>
</html>
