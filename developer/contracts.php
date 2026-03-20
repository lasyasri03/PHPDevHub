<?php
include '../includes/db.php';
include '../includes/auth.php';
require_once '../includes/marketplace_helpers.php';
require_once '../includes/role_ui.php';
requireRole('developer');

$developerId = (int) $_SESSION['user_id'];
$stmt = $pdo->prepare(
    "SELECT hr.id,
            p.id AS project_id,
            p.title AS project_title,
            p.budget,
            p.status AS project_status,
            u.name AS client_name,
            hr.created_at AS contract_date,
            pay.payment_status,
            pay.transaction_id
     FROM hire_requests hr
     INNER JOIN projects p ON p.id = hr.project_id
     INNER JOIN users u ON u.id = hr.client_id
     LEFT JOIN payments pay
        ON pay.project_id = p.id
       AND pay.client_id = hr.client_id
       AND pay.developer_id = hr.developer_id
     WHERE hr.developer_id = ? AND hr.status = 'accepted'
     ORDER BY hr.created_at DESC, hr.id DESC"
);
$stmt->execute([$developerId]);
$contracts = $stmt->fetchAll(PDO::FETCH_ASSOC);

renderRolePageStart('developer', 'active-contracts', 'My Contracts', 'Accepted project engagements are visible here from the shared contract flow.');
?>
<section class="panel-card">
    <h2 class="section-title">Contract Records</h2>
    <div class="table-responsive">
        <table class="table table-clean align-middle mb-0">
            <thead>
                <tr>
                    <th>Contract</th>
                    <th>Project</th>
                    <th>Client</th>
                    <th>Budget</th>
                    <th>Status</th>
                    <th>Payment</th>
                    <th>Started</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($contracts): ?>
                    <?php foreach ($contracts as $contract): ?>
                        <?php $paymentStatus = $contract['payment_status'] ?: 'Pending'; ?>
                        <tr>
                            <td>#<?php echo (int) $contract['id']; ?></td>
                            <td><?php echo htmlspecialchars($contract['project_title']); ?></td>
                            <td><?php echo htmlspecialchars($contract['client_name']); ?></td>
                            <td>$<?php echo number_format((float) $contract['budget'], 2); ?></td>
                            <td><span class="status-badge status-<?php echo htmlspecialchars(badgeClass($contract['project_status'])); ?>"><?php echo htmlspecialchars(formatStatusLabel($contract['project_status'])); ?></span></td>
                            <td>
                                <span class="status-badge status-<?php echo htmlspecialchars(badgeClass((string) strtolower($paymentStatus))); ?>">
                                    <?php echo htmlspecialchars($paymentStatus); ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars(date('M j, Y', strtotime($contract['contract_date']))); ?></td>
                            <td class="d-flex gap-2 flex-wrap">
                                <a class="btn btn-sm btn-primary" href="<?php echo htmlspecialchars(appUrl('chat/project_chat.php')); ?>?request_id=<?php echo (int) $contract['id']; ?>">Open Chat</a>
                                <a class="btn btn-sm btn-outline-primary" href="<?php echo htmlspecialchars(appUrl('developer/disputes.php')); ?>?project_id=<?php echo (int) $contract['project_id']; ?>">Open Dispute</a>
                                <?php if ($contract['transaction_id']): ?>
                                    <span class="text-muted small"><?php echo htmlspecialchars('Txn: ' . $contract['transaction_id']); ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">No contracts yet. Accepted project applications will appear here.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php renderRolePageEnd(); ?>
