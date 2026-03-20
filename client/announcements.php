<?php
include '../includes/db.php';
include '../includes/auth.php';
require_once '../includes/role_ui.php';

requireRole('client');

/* SEARCH VALUE */
$search = trim($_GET['search'] ?? '');

/* BASE QUERY */
$sql = "SELECT title, message, created_at
        FROM announcements";

/* APPLY SEARCH */
if ($search !== '') {
    $sql .= " WHERE title LIKE :search OR message LIKE :search";
}

$sql .= " ORDER BY created_at DESC";

$stmt = $pdo->prepare($sql);

if ($search !== '') {
    $stmt->bindValue(':search', "%$search%", PDO::PARAM_STR);
}

$stmt->execute();
$announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);

renderRolePageStart(
    'client',
    'announcements',
    'Announcements',
    'Platform updates published by admin appear here automatically.'
);
?>

<section class="panel-card">

<h2 class="section-title">Latest Updates</h2>

<!-- SEARCH BAR -->
<form method="GET" class="mb-3 d-flex gap-2">

<input
type="text"
name="search"
class="form-control"
placeholder="Search announcements..."
value="<?php echo htmlspecialchars($search); ?>"
>

<button class="btn btn-primary">Search</button>

<a href="<?php echo htmlspecialchars(appUrl('client/announcements.php')); ?>" class="btn btn-secondary">
Reset
</a>

</form>

<?php if ($announcements): ?>

<?php foreach ($announcements as $announcement): ?>

<div class="announcement-item">

<div class="d-flex justify-content-between gap-3 flex-wrap">

<strong><?php echo htmlspecialchars($announcement['title']); ?></strong>

<span class="meta-text">
<?php echo htmlspecialchars(date('M j, Y', strtotime($announcement['created_at']))); ?>
</span>

</div>

<p class="mb-0 mt-2">
<?php echo nl2br(htmlspecialchars($announcement['message'])); ?>
</p>

</div>

<?php endforeach; ?>

<?php else: ?>

<p class="text-muted mb-0">No announcements found for your search.</p>

<?php endif; ?>

</section>

<?php renderRolePageEnd(); ?>