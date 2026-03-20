<?php
include '../includes/db.php';
include '../includes/auth.php';
require_once '../includes/role_ui.php';
requireRole('developer');

$stmt = $pdo->query("SELECT title, message, created_at FROM announcements ORDER BY created_at DESC");
$announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);

renderRolePageStart('developer', 'announcements', 'Announcements', 'Admin announcements are synchronized here for all developers.');
?>
<section class="panel-card">
    <h2 class="section-title">Latest Updates</h2>
    <?php if ($announcements): ?>
        <?php foreach ($announcements as $announcement): ?>
            <div class="announcement-item">
                <div class="d-flex justify-content-between gap-3 flex-wrap">
                    <strong><?php echo htmlspecialchars($announcement['title']); ?></strong>
                    <span class="meta-text"><?php echo htmlspecialchars(date('M j, Y', strtotime($announcement['created_at']))); ?></span>
                </div>
                <p class="mb-0 mt-2"><?php echo nl2br(htmlspecialchars($announcement['message'])); ?></p>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <p class="text-muted mb-0">No announcements have been published yet.</p>
    <?php endif; ?>
</section>
<?php renderRolePageEnd(); ?>

