<?php
include '../includes/db.php';
include '../includes/auth.php';
require_once '../includes/marketplace_helpers.php';
require_once '../includes/role_ui.php';
requireRole('developer');

$developerId = (int) $_SESSION['user_id'];
$stmt = $pdo->prepare(
    "SELECT hr.id, hr.status, hr.created_at, u.name AS client_name, p.title AS project_title
     FROM hire_requests hr
     INNER JOIN users u ON u.id = hr.client_id
     LEFT JOIN projects p ON p.id = hr.project_id
     WHERE hr.developer_id = ? AND hr.status = 'accepted'
     ORDER BY hr.created_at DESC, hr.id DESC"
);
$stmt->execute([$developerId]);
$threads = $stmt->fetchAll(PDO::FETCH_ASSOC);

renderRolePageStart('developer', 'messages', 'Messages', 'Open active conversations with clients from accepted contracts and hire requests.');
?>
<section class="panel-card">
    <h2 class="section-title">Active Conversations</h2>
    <div class="table-responsive">
        <table class="table table-clean align-middle mb-0">
            <thead>
                <tr>
                    <th>Chat</th>
                    <th>Client</th>
                    <th>Project</th>
                    <th>Status</th>
                    <th>Started</th>
                    <th>Open</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($threads): ?>
                    <?php foreach ($threads as $thread): ?>
                        <tr>
                            <td>#<?php echo (int) $thread['id']; ?></td>
                            <td><?php echo htmlspecialchars($thread['client_name']); ?></td>
                            <td><?php echo htmlspecialchars($thread['project_title'] ?: 'Direct hire request'); ?></td>
                            <td><span class="status-badge status-<?php echo htmlspecialchars(badgeClass($thread['status'])); ?>"><?php echo htmlspecialchars(formatStatusLabel($thread['status'])); ?></span></td>
                            <td><?php echo htmlspecialchars(date('M j, Y', strtotime($thread['created_at']))); ?></td>
                            <td><a href="<?php echo htmlspecialchars(appUrl('chat/project_chat.php')); ?>?request_id=<?php echo (int) $thread['id']; ?>" class="btn btn-primary btn-sm">Open Chat</a></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="6" class="text-center text-muted py-4">No active messages yet. Chats become available after a hire request is accepted.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php renderRolePageEnd(); ?>
