<?php
include '../includes/db.php';
include '../includes/auth.php';
require_once '../includes/role_ui.php';
requireRole('client');

$userId = (int) ($_GET['id'] ?? 0);
if ($userId <= 0) {
    header('Location: ' . appUrl('developers/list.php'));
    exit;
}

$stmt = $pdo->prepare("SELECT u.id, u.name, u.email, d.* FROM users u JOIN developers d ON u.id = d.user_id WHERE u.id = ? AND u.role = 'developer'");
$stmt->execute([$userId]);
$developer = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$developer) {
    header('Location: ' . appUrl('developers/list.php'));
    exit;
}

$ratingStmt = $pdo->prepare(
    "SELECT COUNT(*) AS completed_projects, COALESCE(AVG(rating), 0) AS avg_rating
     FROM developer_ratings
     WHERE developer_id = ?"
);
$ratingStmt->execute([$userId]);
$ratingData = $ratingStmt->fetch(PDO::FETCH_ASSOC) ?: ['completed_projects' => 0, 'avg_rating' => 0];

$acceptedWorkStmt = $pdo->prepare(
    "SELECT COUNT(*) FROM hire_requests WHERE developer_id = ? AND status = 'accepted'"
);
$acceptedWorkStmt->execute([$userId]);
$acceptedProjects = (int) $acceptedWorkStmt->fetchColumn();

$successMessage = $_GET['success'] ?? '';
$errorMessage = $_GET['error'] ?? '';
$existingHireRequest = null;

$hireStmt = $pdo->prepare(
    "SELECT id, status
     FROM hire_requests
     WHERE client_id = ? AND developer_id = ?
     ORDER BY created_at DESC, id DESC
     LIMIT 1"
);
$hireStmt->execute([$_SESSION['user_id'], $userId]);
$existingHireRequest = $hireStmt->fetch(PDO::FETCH_ASSOC) ?: null;

renderRolePageStart('client', 'find-developers', 'Developer Profile', 'Review skills, GitHub profile, experience, and marketplace history before hiring.');
?>
<?php if ($successMessage !== ''): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($successMessage); ?></div>
<?php endif; ?>
<?php if ($errorMessage !== ''): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($errorMessage); ?></div>
<?php endif; ?>

<section class="panel-card">
    <div class="row g-4 align-items-start">
        <div class="col-lg-4">
            <div class="card">
                <?php if (!empty($developer['profile_image'])): ?>
                    <img src="<?php echo htmlspecialchars(appUrl('uploads/profile/' . $developer['profile_image'])); ?>" alt="<?php echo htmlspecialchars($developer['name']); ?>" style="height:320px;object-fit:cover;" class="card-img-top">
                <?php endif; ?>
                <div class="card-body">
                    <h2 class="h4 mb-1"><?php echo htmlspecialchars($developer['name']); ?></h2>
                    <p class="meta-text mb-3"><?php echo htmlspecialchars($developer['location'] ?: 'Location not provided'); ?></p>
                    <div class="d-grid gap-2" id="hire-developer">
                        <?php if (!$existingHireRequest || $existingHireRequest['status'] === 'rejected'): ?>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#hireModal">Hire Developer</button>
                        <?php elseif ($existingHireRequest['status'] === 'pending'): ?>
                            <button class="btn btn-secondary" disabled>Hire Request Pending</button>
                        <?php elseif ($existingHireRequest['status'] === 'accepted'): ?>
                            <button class="btn btn-success" disabled>Hire Accepted</button>
                        <?php endif; ?>
                        <a href="<?php echo htmlspecialchars(appUrl('chat/start_chat.php')); ?>?developer_id=<?php echo (int) $userId; ?>" class="btn btn-outline-primary">Send Message</a>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="row g-3 mb-3">
                <div class="col-md-4"><div class="stat-tile"><span class="stat-label">Experience</span><div class="stat-value"><?php echo (int) ($developer['experience'] ?? 0); ?>y</div></div></div>
                <div class="col-md-4"><div class="stat-tile"><span class="stat-label">Completed Projects</span><div class="stat-value"><?php echo max($acceptedProjects, (int) $ratingData['completed_projects']); ?></div></div></div>
                <div class="col-md-4"><div class="stat-tile"><span class="stat-label">Rating</span><div class="stat-value"><?php echo number_format((float) $ratingData['avg_rating'], 1); ?></div></div></div>
            </div>
            <div class="panel-card" style="margin-bottom:0;">
                <h2 class="section-title">Profile Overview</h2>
                <p><strong>Skills:</strong> <?php echo htmlspecialchars($developer['skills'] ?: 'Not provided'); ?></p>
                <p><strong>Bio:</strong> <?php echo nl2br(htmlspecialchars($developer['bio'] ?: 'No bio added yet.')); ?></p>
                <p><strong>Hourly Rate:</strong> <?php echo $developer['hourly_rate'] !== null ? '$' . number_format((float) $developer['hourly_rate'], 2) . '/hr' : 'Not provided'; ?></p>
                <p><strong>PHP Proficiency:</strong> <?php echo htmlspecialchars($developer['php_proficiency'] ?: 'Not provided'); ?></p>
                <p><strong>Email:</strong> <?php echo htmlspecialchars($developer['email']); ?></p>
                <p>
                    <strong>GitHub Profile:</strong>
                    <?php if (!empty($developer['github_link']) || !empty($developer['github'])): ?>
                        <?php $githubProfile = $developer['github_link'] ?? $developer['github']; ?>
                        <a href="<?php echo htmlspecialchars($githubProfile); ?>" target="_blank" rel="noopener noreferrer"><?php echo htmlspecialchars($githubProfile); ?></a>
                    <?php else: ?>
                        Not provided
                    <?php endif; ?>
                </p>
            </div>
        </div>
    </div>
</section>

<?php if (!$existingHireRequest || $existingHireRequest['status'] === 'rejected'): ?>
    <div class="modal fade" id="hireModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title fs-5">Hire <?php echo htmlspecialchars($developer['name']); ?></h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="post" action="<?php echo htmlspecialchars(appUrl('client/hire.php')); ?>?developer_id=<?php echo (int) $userId; ?>">
                    <div class="modal-body">
                        <label class="form-label" for="message">Hire request message</label>
                        <textarea class="form-control" id="message" name="message" rows="4" required></textarea>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Send Hire Request</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endif; ?>
<?php renderRolePageEnd(); ?>
