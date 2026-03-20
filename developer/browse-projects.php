<?php
include '../includes/db.php';
include '../includes/auth.php';
require_once '../includes/marketplace_helpers.php';
require_once '../includes/role_ui.php';
requireRole('developer');

$developerId = (int) ($_SESSION['user_id'] ?? 0);
$successMessage = $_GET['success'] ?? '';
$errorMessage = $_GET['error'] ?? '';

$stmt = $pdo->prepare(
    "SELECT p.*,
            u.name AS client_name,
            hr.id AS application_id,
            hr.status AS application_status
     FROM projects p
     INNER JOIN users u ON u.id = p.client_id
     LEFT JOIN hire_requests hr
        ON hr.project_id = p.id
       AND hr.developer_id = ?
     WHERE p.status IN ('approved', 'open', 'in_progress')
     ORDER BY p.created_at DESC, p.id DESC"
);
$stmt->execute([$developerId]);
$projects = $stmt->fetchAll(PDO::FETCH_ASSOC);

renderRolePageStart('developer', 'available-projects', 'Browse Projects', 'Browse approved marketplace projects and see the status of your own applications.');
?>
<?php if ($successMessage !== ''): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($successMessage); ?></div>
<?php endif; ?>
<?php if ($errorMessage !== ''): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($errorMessage); ?></div>
<?php endif; ?>
<section class="panel-card">
    <h2 class="section-title">Marketplace Projects</h2>
    <div class="table-responsive">
        <table class="table table-clean align-middle mb-0">
            <thead>
                <tr>
                    <th>Project</th>
                    <th>Client</th>
                    <th>Budget</th>
                    <th>Needed</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($projects): ?>
                    <?php foreach ($projects as $project): ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($project['title']); ?></strong>
                                <div class="meta-text"><?php echo htmlspecialchars($project['description']); ?></div>
                            </td>
                            <td><?php echo htmlspecialchars($project['client_name']); ?></td>
                            <td>$<?php echo number_format((float) $project['budget'], 2); ?></td>
                            <td><?php echo (int) $project['developers_needed']; ?></td>
                            <td><span class="status-badge status-<?php echo htmlspecialchars(badgeClass($project['status'])); ?>"><?php echo htmlspecialchars(formatStatusLabel($project['status'])); ?></span></td>
                            <td>
                                <?php if ($project['application_id'] && $project['application_status'] === 'accepted'): ?>
                                    <div class="d-flex gap-2 flex-wrap">
                                        <span class="status-badge status-<?php echo htmlspecialchars(badgeClass($project['application_status'])); ?>"><?php echo htmlspecialchars(formatStatusLabel($project['application_status'])); ?></span>
                                        <a href="<?php echo htmlspecialchars(appUrl('developer/disputes.php')); ?>?project_id=<?php echo (int) $project['id']; ?>" class="btn btn-sm btn-outline-primary">Open Dispute</a>
                                    </div>
                                <?php elseif ($project['application_id']): ?>
                                    <span class="status-badge status-<?php echo htmlspecialchars(badgeClass($project['application_status'])); ?>"><?php echo htmlspecialchars(formatStatusLabel($project['application_status'])); ?></span>
                                <?php elseif ($project['status'] === 'in_progress'): ?>
                                    <span class="text-muted">Closed for new applications</span>
                                <?php else: ?>
                                    <form method="post" action="<?php echo htmlspecialchars(appUrl('developer/apply-project.php')); ?>">
                                        <input type="hidden" name="project_id" value="<?php echo (int) $project['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-primary">Apply</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">There are no marketplace projects available right now.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php renderRolePageEnd(); ?>
