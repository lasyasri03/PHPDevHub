<?php
include '../includes/db.php';
include '../includes/auth.php';
require_once '../includes/marketplace_helpers.php';
require_once '../includes/role_ui.php';
requireRole('developer');

$developerId = (int) $_SESSION['user_id'];
$projectId = (int) ($_GET['project_id'] ?? $_POST['project_id'] ?? 0);
$successMessage = '';
$errorMessage = '';
$descriptionValue = '';
$reasonValue = 'Work not delivered';
$responseDrafts = [];
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
                u.name AS client_name
         FROM projects p
         LEFT JOIN users u ON u.id = p.client_id
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
    if (isset($_POST['submit_dispute'])) {
        $reasonValue = trim($_POST['reason'] ?? '');
        $descriptionValue = trim($_POST['description'] ?? '');

        if (!in_array($reasonValue, $reasonOptions, true)) {
            $errorMessage = 'Please select a valid dispute reason.';
        } elseif ($descriptionValue === '') {
            $errorMessage = 'Please explain the issue before submitting.';
        } else {
            $insertStmt = $pdo->prepare(
                "INSERT INTO disputes (project_id, client_id, developer_id, reason, description, complaint, status, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, 'open', NOW())"
            );
            $insertStmt->execute([
                $projectId,
                (int) $project['client_id'],
                $developerId,
                $reasonValue,
                $descriptionValue,
                $descriptionValue,
            ]);

            logUserActivity($pdo, $developerId, 'developer', 'Opened dispute for project ' . $project['title']);
            $successMessage = 'Your dispute has been submitted and is now under review.';
            $descriptionValue = '';
            $reasonValue = 'Work not delivered';
        }
    } elseif (isset($_POST['submit_response'])) {
        $disputeId = (int) ($_POST['dispute_id'] ?? 0);
        $responseText = trim($_POST['response'] ?? '');
        $responseDrafts[$disputeId] = $responseText;

        if ($disputeId <= 0 || $responseText === '') {
            $errorMessage = 'A response is required.';
        } else {
            $checkStmt = $pdo->prepare("SELECT id FROM disputes WHERE id = ? AND project_id = ? AND developer_id = ?");
            $checkStmt->execute([$disputeId, $projectId, $developerId]);
            $dispute = $checkStmt->fetch(PDO::FETCH_ASSOC);

            if (!$dispute) {
                $errorMessage = 'Dispute not found.';
            } else {
                $insertStmt = $pdo->prepare(
                    "INSERT INTO dispute_responses (dispute_id, user_id, response, created_at)
                     VALUES (?, ?, ?, NOW())"
                );
                $insertStmt->execute([$disputeId, $developerId, $responseText]);
                logUserActivity($pdo, $developerId, 'developer', 'Responded to dispute #' . $disputeId);
                $successMessage = 'Response submitted successfully.';
                $responseDrafts = [];
            }
        }
    }
}

$disputes = [];
$responsesByDispute = [];
if ($project) {
    $disputesStmt = $pdo->prepare(
        "SELECT d.id, d.reason, d.description, d.status, d.admin_note, d.created_at
         FROM disputes d
         WHERE d.project_id = ? AND d.developer_id = ?
         ORDER BY d.created_at DESC, d.id DESC"
    );
    $disputesStmt->execute([$projectId, $developerId]);
    $disputes = $disputesStmt->fetchAll(PDO::FETCH_ASSOC);

    $responseStmt = $pdo->prepare(
        "SELECT dr.dispute_id, dr.response, dr.created_at, u.name
         FROM dispute_responses dr
         INNER JOIN users u ON u.id = dr.user_id
         WHERE dr.dispute_id IN (
             SELECT id FROM disputes WHERE project_id = ? AND developer_id = ?
         )
         ORDER BY dr.created_at DESC, dr.id DESC"
    );
    $responseStmt->execute([$projectId, $developerId]);
    foreach ($responseStmt->fetchAll(PDO::FETCH_ASSOC) as $response) {
        $responsesByDispute[$response['dispute_id']][] = $response;
    }
}

renderRolePageStart('developer', 'disputes', 'Open Dispute', 'If you are facing an issue with the project, please explain the problem below. The admin team will review your request.');
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
            <div class="meta-text">Client: <?php echo htmlspecialchars($project['client_name']); ?></div>
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
                <button type="submit" name="submit_dispute" value="1" class="btn btn-primary">Submit Dispute</button>
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
                    <div class="alert alert-light border mb-3">Admin update: <?php echo nl2br(htmlspecialchars($dispute['admin_note'])); ?></div>
                <?php endif; ?>
                <?php foreach ($responsesByDispute[$dispute['id']] ?? [] as $response): ?>
                    <div class="alert alert-light border mb-2">
                        <strong><?php echo htmlspecialchars($response['name']); ?>:</strong>
                        <?php echo nl2br(htmlspecialchars($response['response'])); ?>
                    </div>
                <?php endforeach; ?>
                <?php if (!in_array($dispute['status'], ['resolved', 'closed'], true)): ?>
                    <form method="post" class="mt-3">
                        <input type="hidden" name="project_id" value="<?php echo (int) $projectId; ?>">
                        <input type="hidden" name="dispute_id" value="<?php echo (int) $dispute['id']; ?>">
                        <div class="mb-2">
                            <textarea class="form-control" name="response" rows="3" placeholder="Add your response to this dispute." required><?php echo htmlspecialchars($responseDrafts[$dispute['id']] ?? ''); ?></textarea>
                        </div>
                        <button type="submit" name="submit_response" value="1" class="btn btn-outline-primary btn-sm">Submit Response</button>
                    </form>
                <?php endif; ?>
                <div class="meta-text mt-2"><?php echo htmlspecialchars(date('M j, Y g:i A', strtotime($dispute['created_at']))); ?></div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <p class="text-muted mb-0">No disputes have been submitted for this project yet.</p>
    <?php endif; ?>
</section>
<?php endif; ?>
<?php renderRolePageEnd(); ?>
