<?php
include '../includes/db.php';
include '../includes/auth.php';
require_once '../includes/marketplace_helpers.php';
requireRole('client');

$clientId = (int) ($_SESSION['user_id'] ?? 0);
$projectId = isset($_GET['project_id']) ? (int) $_GET['project_id'] : (int) ($_POST['project_id'] ?? 0);
$successMessage = null;
$errorMessage = null;

$projectStmt = $pdo->prepare("SELECT * FROM projects WHERE id = ? AND client_id = ?");
$projectStmt->execute([$projectId, $clientId]);
$project = $projectStmt->fetch(PDO::FETCH_ASSOC);

if (!$project) {
    header('Location: ' . appUrl('client/my-projects.php') . '?error=' . urlencode('Project not found.'));
    exit;
}

$acceptedCountStmt = $pdo->prepare("SELECT COUNT(*) FROM hire_requests WHERE project_id = ? AND status = 'accepted'");
$acceptedCountStmt->execute([$projectId]);
$acceptedCount = (int) $acceptedCountStmt->fetchColumn();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $requestId = (int) ($_POST['request_id'] ?? 0);
    $action = $_POST['action'] ?? '';

    if ($requestId <= 0 || !in_array($action, ['accept', 'reject'], true)) {
        $errorMessage = 'Invalid proposal action.';
    } else {
        try {
            $pdo->beginTransaction();

            $requestStmt = $pdo->prepare(
                "SELECT hr.*
                 FROM hire_requests hr
                 INNER JOIN projects p ON p.id = hr.project_id
                 WHERE hr.id = ? AND hr.project_id = ? AND p.client_id = ?"
            );
            $requestStmt->execute([$requestId, $projectId, $clientId]);
            $request = $requestStmt->fetch(PDO::FETCH_ASSOC);

            if (!$request) {
                throw new RuntimeException('Proposal not found.');
            }

            if ($action === 'accept') {
                $countStmt = $pdo->prepare("SELECT COUNT(*) FROM hire_requests WHERE project_id = ? AND status = 'accepted'");
                $countStmt->execute([$projectId]);
                $currentAcceptedCount = (int) $countStmt->fetchColumn();

                if ($currentAcceptedCount >= (int) $project['developers_needed']) {
                    throw new RuntimeException('Developer limit reached.');
                }

                $acceptStmt = $pdo->prepare("UPDATE hire_requests SET status = 'accepted' WHERE id = ?");
                $acceptStmt->execute([$requestId]);

                $projectUpdateStmt = $pdo->prepare("UPDATE projects SET status = 'in_progress' WHERE id = ?");
                $projectUpdateStmt->execute([$projectId]);

                $earningCheckStmt = $pdo->prepare("SELECT COUNT(*) FROM platform_earnings WHERE project_id = ?");
                $earningCheckStmt->execute([$projectId]);
                $earningExists = (int) $earningCheckStmt->fetchColumn() > 0;

                if (!$earningExists) {
                    $earningInsertStmt = $pdo->prepare(
                        "INSERT INTO platform_earnings (project_id, amount, created_at)
                         VALUES (?, ?, NOW())"
                    );
                    $earningInsertStmt->execute([$projectId, round(((float) ($project['budget'] ?? 0)) * 0.10, 2)]);
                }

                $developerNameStmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
                $developerNameStmt->execute([(int) $request['developer_id']]);
                $developerName = (string) $developerNameStmt->fetchColumn();

                $notificationStmt = $pdo->prepare(
                    "INSERT INTO admin_notifications (type, message, is_read, created_at)
                     VALUES ('developer_hired', ?, 0, NOW())"
                );
                $notificationMessage = ($_SESSION['name'] ?? 'Client') . ' hired ' . $developerName . ' for project ' . $project['title'];
                $notificationStmt->execute([$notificationMessage]);

                logUserActivity($pdo, $clientId, 'client', 'Hired developer ' . $developerName . ' for project ' . $project['title']);
                logUserActivity($pdo, (int) $request['developer_id'], 'developer', 'Started project ' . $project['title']);

                $successMessage = 'Developer hired successfully.';
                $project['status'] = 'in_progress';
                $acceptedCount = $currentAcceptedCount + 1;
            } else {
                $rejectStmt = $pdo->prepare("UPDATE hire_requests SET status = 'rejected' WHERE id = ?");
                $rejectStmt->execute([$requestId]);
                $successMessage = 'Proposal rejected successfully.';
            }

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errorMessage = $e->getMessage() === 'Developer limit reached.' ? 'You have already hired the required number of developers for this project.' : 'Unable to update proposal right now.';
        }
    }
}

$proposalStmt = $pdo->prepare(
    "SELECT hr.id, hr.status, hr.created_at, u.name AS developer_name, d.skills, d.experience
     FROM hire_requests hr
     INNER JOIN users u ON u.id = hr.developer_id
     LEFT JOIN developers d ON d.user_id = u.id
     WHERE hr.project_id = ?
     ORDER BY
         CASE hr.status
             WHEN 'accepted' THEN 0
             WHEN 'pending' THEN 1
             ELSE 2
         END,
         hr.created_at DESC"
);
$proposalStmt->execute([$projectId]);
$proposals = $proposalStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<?php include '../includes/header.php'; ?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h2 class="mb-1">Project Proposals</h2>
        <p class="text-muted mb-0">
            <?php echo htmlspecialchars($project['title']); ?> |
            Status: <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $project['status']))); ?> |
            Developers Needed: <?php echo (int) $project['developers_needed']; ?> |
            Accepted: <?php echo (int) $acceptedCount; ?>
        </p>
    </div>
    <a href="<?php echo htmlspecialchars(appUrl('client/my-projects.php')); ?>" class="btn btn-outline-secondary">Back to My Projects</a>
</div>

<?php if ($successMessage): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($successMessage); ?></div>
<?php endif; ?>
<?php if ($errorMessage): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($errorMessage); ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-body">
        <?php if ($proposals): ?>
            <div class="table-responsive">
                <table class="table table-striped align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Developer Name</th>
                            <th>Skills</th>
                            <th>Experience</th>
                            <th>Status</th>
                            <th>Applied</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($proposals as $proposal): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($proposal['developer_name']); ?></td>
                                <td><?php echo htmlspecialchars($proposal['skills'] ?: 'Not provided'); ?></td>
                                <td><?php echo htmlspecialchars($proposal['experience'] !== null ? $proposal['experience'] . ' years' : 'Not provided'); ?></td>
                                <td>
                                    <span class="badge bg-<?php echo $proposal['status'] === 'accepted' ? 'success' : ($proposal['status'] === 'pending' ? 'warning text-dark' : 'danger'); ?>">
                                        <?php echo htmlspecialchars(ucfirst($proposal['status'])); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars(date('M j, Y', strtotime($proposal['created_at']))); ?></td>
                                <td>
                                    <?php if ($proposal['status'] === 'pending' && $acceptedCount < (int) $project['developers_needed']): ?>
                                        <form method="post" class="d-inline">
                                            <input type="hidden" name="project_id" value="<?php echo (int) $projectId; ?>">
                                            <input type="hidden" name="request_id" value="<?php echo (int) $proposal['id']; ?>">
                                            <button type="submit" name="action" value="accept" class="btn btn-success btn-sm">Hire</button>
                                        </form>
                                        <form method="post" class="d-inline">
                                            <input type="hidden" name="project_id" value="<?php echo (int) $projectId; ?>">
                                            <input type="hidden" name="request_id" value="<?php echo (int) $proposal['id']; ?>">
                                            <button type="submit" name="action" value="reject" class="btn btn-outline-danger btn-sm">Reject</button>
                                        </form>
                                    <?php elseif ($proposal['status'] === 'pending'): ?>
                                        <button type="button" class="btn btn-success btn-sm" disabled>Hire Disabled</button>
                                    <?php else: ?>
                                        <span class="text-muted small">No actions available</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="text-muted mb-0">No developers have applied to this project yet.</p>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
