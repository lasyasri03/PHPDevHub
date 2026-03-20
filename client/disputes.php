<?php
include '../includes/db.php';
include '../includes/auth.php';
require_once '../includes/marketplace_helpers.php';
require_once '../includes/role_ui.php';
requireRole('client');

$clientId = (int) $_SESSION['user_id'];
$projectId = (int) ($_GET['project_id'] ?? $_POST['project_id'] ?? 0);
$successMessage = '';
$errorMessage = '';
$descriptionValue = '';
$reasonValue = 'Work not delivered';
$project = null;
$reasonOptions = [
    'Work not delivered',
    'Poor quality work',
    'Deadline missed',
    'Payment issue',
    'Other',
];

if ($projectId <= 0) {
    $errorMessage = 'Invalid project link.';
} else {
    $projectStmt = $pdo->prepare(
        "SELECT p.id, p.title, p.client_id,
                hr.developer_id,
                u.name AS developer_name
         FROM projects p
         LEFT JOIN hire_requests hr
            ON hr.id = (
                SELECT hr2.id
                FROM hire_requests hr2
                WHERE hr2.project_id = p.id
                ORDER BY CASE WHEN hr2.status = 'accepted' THEN 0 ELSE 1 END, hr2.created_at DESC, hr2.id DESC
                LIMIT 1
            )
         LEFT JOIN users u ON u.id = hr.developer_id
         WHERE p.id = ?
         LIMIT 1"
    );
    $projectStmt->execute([$projectId]);
    $project = $projectStmt->fetch(PDO::FETCH_ASSOC);

    if (!$project) {
        $errorMessage = 'Invalid project link.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $project) {
    $reasonValue = trim($_POST['reason'] ?? '');
    $descriptionValue = trim($_POST['description'] ?? '');

    if (!in_array($reasonValue, $reasonOptions, true)) {
        $errorMessage = 'Please select a valid dispute reason.';
    } elseif ($descriptionValue === '') {
        $errorMessage = 'Please explain the issue before submitting.';
    } elseif (empty($project['developer_id'])) {
        $errorMessage = 'Unable to submit a dispute for this project right now.';
    } else {
        $insertStmt = $pdo->prepare(
            "INSERT INTO disputes (project_id, client_id, developer_id, reason, description, complaint, status, created_at)
             VALUES (?, ?, ?, ?, ?, ?, 'open', NOW())"
        );
        $insertStmt->execute([
            $projectId,
            (int) $project['client_id'],
            (int) $project['developer_id'],
            $reasonValue,
            $descriptionValue,
            $descriptionValue,
        ]);

        logUserActivity($pdo, $clientId, 'client', 'Opened dispute for project ' . $project['title']);
        $successMessage = 'Your dispute has been submitted and is now under review.';
        $descriptionValue = '';
        $reasonValue = 'Work not delivered';
    }
}

$disputes = [];
$responsesByDispute = [];
if ($project) {
    $disputesStmt = $pdo->prepare(
        "SELECT d.id, d.reason, d.description, d.status, d.admin_note, d.created_at
         FROM disputes d
         WHERE d.project_id = ? AND d.client_id = ?
         ORDER BY d.created_at DESC, d.id DESC"
    );
    $disputesStmt->execute([$projectId, $clientId]);
    $disputes = $disputesStmt->fetchAll(PDO::FETCH_ASSOC);

    $responseStmt = $pdo->prepare(
        "SELECT dr.dispute_id, dr.response, dr.created_at, u.name
         FROM dispute_responses dr
         INNER JOIN users u ON u.id = dr.user_id
         WHERE dr.dispute_id IN (
             SELECT id FROM disputes WHERE project_id = ? AND client_id = ?
         )
         ORDER BY dr.created_at DESC, dr.id DESC"
    );
    $responseStmt->execute([$projectId, $clientId]);
    foreach ($responseStmt->fetchAll(PDO::FETCH_ASSOC) as $response) {
        $responsesByDispute[$response['dispute_id']][] = $response;
    }
}

renderRolePageStart('client', 'disputes', 'Open Dispute', 'If you are facing an issue with the project, please explain the problem below. The admin team will review your request.');
?>
<?php if ($successMessage !== ''): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($successMessage); ?></div>
<?php endif; ?>
<?php if ($errorMessage !== ''): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($errorMessage); ?></div>
<?php endif; ?>

<section class="panel-card">
    <h2 class="section-title">Open Dispute</h2>
    <?php if ($project): ?>
        <div class="mb-3">
            <div><strong>Project:</strong> <?php echo htmlspecialchars($project['title']); ?></div>
            <?php if (!empty($project['developer_name'])): ?>
                <div class="meta-text">Developer: <?php echo htmlspecialchars($project['developer_name']); ?></div>
            <?php else: ?>
                <div class="meta-text">Developer information will appear here when available.</div>
            <?php endif; ?>
        </div>
        <form method="post" class="row g-3">
            <input type="hidden" name="project_id" value="<?php echo (int) $projectId; ?>">
            <div class="col-md-6">
                <label class="form-label" for="reason">Reason</label>
                <select class="form-select" id="reason" name="reason" required>
                    <?php foreach ($reasonOptions as $option): ?>
                        <option value="<?php echo htmlspecialchars($option); ?>" <?php echo $reasonValue === $option ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($option); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12">
                <label class="form-label" for="description">Description</label>
                <textarea class="form-control" id="description" name="description" rows="5" required><?php echo htmlspecialchars($descriptionValue); ?></textarea>
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-primary">Submit Dispute</button>
            </div>
        </form>
    <?php else: ?>
        <p class="text-muted mb-0">Invalid project link.</p>
    <?php endif; ?>
</section>

<?php if ($project): ?>
<section class="panel-card">
    <h2 class="section-title">Dispute History</h2>
    <?php if ($disputes): ?>
        <?php foreach ($disputes as $dispute): ?>
            <div class="announcement-item">
                <div class="d-flex justify-content-between gap-3 flex-wrap">
                    <strong><?php echo htmlspecialchars($dispute['reason'] ?? 'General dispute'); ?></strong>
                    <span class="status-badge status-<?php echo htmlspecialchars(badgeClass($dispute['status'])); ?>"><?php echo htmlspecialchars(formatStatusLabel($dispute['status'])); ?></span>
                </div>
                <p class="mb-2 mt-2"><?php echo nl2br(htmlspecialchars($dispute['description'] ?? '')); ?></p>
                <?php if (!empty($dispute['admin_note'])): ?>
                    <div class="alert alert-light border mb-2">Admin update: <?php echo nl2br(htmlspecialchars($dispute['admin_note'])); ?></div>
                <?php endif; ?>
                <?php foreach ($responsesByDispute[$dispute['id']] ?? [] as $response): ?>
                    <div class="alert alert-light border mb-2">
                        <strong><?php echo htmlspecialchars($response['name']); ?>:</strong>
                        <?php echo nl2br(htmlspecialchars($response['response'])); ?>
                    </div>
                <?php endforeach; ?>
                <div class="meta-text"><?php echo htmlspecialchars(date('M j, Y g:i A', strtotime($dispute['created_at']))); ?></div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <p class="text-muted mb-0">No disputes have been submitted for this project yet.</p>
    <?php endif; ?>
</section>
<?php endif; ?>
<?php renderRolePageEnd(); ?>
