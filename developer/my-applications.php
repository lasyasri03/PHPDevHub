<?php
include '../includes/db.php';
include '../includes/auth.php';
require_once '../includes/marketplace_helpers.php';
require_once '../includes/role_ui.php';
requireRole('developer');

$developerId = (int) $_SESSION['user_id'];
$stmt = $pdo->prepare(
    "SELECT hr.id, p.title AS project_title, u.name AS client_name, p.budget, p.status AS project_status, hr.status AS application_status, hr.created_at
     FROM hire_requests hr
     LEFT JOIN projects p ON p.id = hr.project_id
     LEFT JOIN users u ON u.id = hr.client_id
     WHERE hr.developer_id = ? AND hr.project_id IS NOT NULL
     ORDER BY hr.created_at DESC, hr.id DESC"
);
$stmt->execute([$developerId]);
$applications = $stmt->fetchAll(PDO::FETCH_ASSOC);

renderRolePageStart('developer', 'my-applications', 'My Applications', 'Track the projects you have applied to and the contracts you are working on.');
?>
<section class="panel-card">
    <h2 class="section-title">Application History</h2>
    <div class="table-responsive">
        <table class="table table-clean align-middle mb-0">
            <thead>
                <tr>
                    <th>Application</th>
                    <th>Project</th>
                    <th>Client</th>
                    <th>Budget</th>
                    <th>Application Status</th>
                    <th>Project Status</th>
                    <th>Submitted</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($applications): ?>
                    <?php foreach ($applications as $application): ?>
                        <tr>
                            <td>#<?php echo (int) $application['id']; ?></td>
                            <td><?php echo htmlspecialchars($application['project_title'] ?? 'Direct engagement'); ?></td>
                            <td><?php echo htmlspecialchars($application['client_name'] ?? 'N/A'); ?></td>
                            <td><?php echo $application['budget'] !== null ? '$' . number_format((float) $application['budget'], 2) : 'N/A'; ?></td>
                            <td><span class="status-badge status-<?php echo htmlspecialchars(badgeClass($application['application_status'])); ?>"><?php echo htmlspecialchars(formatStatusLabel($application['application_status'])); ?></span></td>
                            <td><span class="status-badge status-<?php echo htmlspecialchars(badgeClass((string) ($application['project_status'] ?? 'secondary'))); ?>"><?php echo htmlspecialchars(formatStatusLabel((string) ($application['project_status'] ?? 'not_available'))); ?></span></td>
                            <td><?php echo htmlspecialchars(date('M j, Y', strtotime($application['created_at']))); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">You have not applied to any projects yet.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php renderRolePageEnd(); ?>
