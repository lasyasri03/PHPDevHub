<?php
include '../includes/db.php';
include '../includes/auth.php';
require_once '../includes/marketplace_helpers.php';
require_once '../includes/role_ui.php';
requireRole('developer');

$developerId = (int) $_SESSION['user_id'];
$successMessage = $_GET['success'] ?? '';
$errorMessage = $_GET['error'] ?? '';

$stmt = $pdo->prepare(
    "SELECT hr.id, hr.message, hr.status, hr.created_at, u.name AS client_name
     FROM hire_requests hr
     INNER JOIN users u ON u.id = hr.client_id
     WHERE hr.developer_id = ? AND hr.project_id IS NULL
     ORDER BY hr.created_at DESC, hr.id DESC"
);
$stmt->execute([$developerId]);
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

renderRolePageStart('developer', 'hire-requests', 'Hire Requests', 'Review incoming direct hire requests from clients and accept or reject them.');
?>
<?php if ($successMessage !== ''): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($successMessage); ?></div>
<?php endif; ?>
<?php if ($errorMessage !== ''): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($errorMessage); ?></div>
<?php endif; ?>

<section class="panel-card">
    <h2 class="section-title">Incoming Requests</h2>
    <div class="table-responsive">
        <table class="table table-clean align-middle mb-0">
            <thead>
                <tr>
                    <th>Request</th>
                    <th>Client</th>
                    <th>Message</th>
                    <th>Status</th>
                    <th>Received</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($requests): ?>
                    <?php foreach ($requests as $request): ?>
                        <tr>
                            <td>#<?php echo (int) $request['id']; ?></td>
                            <td><?php echo htmlspecialchars($request['client_name']); ?></td>
                            <td><?php echo nl2br(htmlspecialchars($request['message'] ?: 'No message provided.')); ?></td>
                            <td><span class="status-badge status-<?php echo htmlspecialchars(badgeClass($request['status'])); ?>"><?php echo htmlspecialchars(formatStatusLabel($request['status'])); ?></span></td>
                            <td><?php echo htmlspecialchars(date('M j, Y', strtotime($request['created_at']))); ?></td>
                            <td>
                                <?php if ($request['status'] === 'pending'): ?>
                                    <div class="d-flex gap-2 flex-wrap">
                                        <form method="post" action="<?php echo htmlspecialchars(appUrl('update_request_status.php')); ?>">
                                            <input type="hidden" name="request_id" value="<?php echo (int) $request['id']; ?>">
                                            <button type="submit" name="status" value="accepted" class="btn btn-success btn-sm">Accept</button>
                                        </form>
                                        <form method="post" action="<?php echo htmlspecialchars(appUrl('update_request_status.php')); ?>">
                                            <input type="hidden" name="request_id" value="<?php echo (int) $request['id']; ?>">
                                            <button type="submit" name="status" value="rejected" class="btn btn-outline-danger btn-sm">Reject</button>
                                        </form>
                                    </div>
                                <?php elseif ($request['status'] === 'accepted'): ?>
                                    <a href="<?php echo htmlspecialchars(appUrl('chat/project_chat.php')); ?>?request_id=<?php echo (int) $request['id']; ?>" class="btn btn-primary btn-sm">Open Chat</a>
                                <?php else: ?>
                                    <span class="text-muted">No actions</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="6" class="text-center text-muted py-4">No direct hire requests yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php renderRolePageEnd(); ?>

