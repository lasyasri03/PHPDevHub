<?php
include '../includes/db.php';
include '../includes/auth.php';
require_once '../includes/marketplace_helpers.php';
require_once '../includes/role_ui.php';

requireRole('client');

$clientId = (int) $_SESSION['user_id'];

/* SEARCH VALUE */
$search = trim($_GET['search'] ?? '');

/* BASE QUERY */
$sql = "SELECT hr.id,
               p.id AS project_id,
               p.title AS project_title,
               u.name AS developer_name,
               hr.status,
               hr.created_at
        FROM hire_requests hr
        INNER JOIN projects p ON p.id = hr.project_id
        INNER JOIN users u ON u.id = hr.developer_id
        WHERE hr.client_id = :client_id
        AND hr.project_id IS NOT NULL";

/* APPLY SEARCH */
if ($search !== '') {
    $sql .= " AND (p.title LIKE :search OR u.name LIKE :search)";
}

$sql .= " ORDER BY hr.created_at DESC, hr.id DESC";

$stmt = $pdo->prepare($sql);

$stmt->bindValue(':client_id', $clientId, PDO::PARAM_INT);

if ($search !== '') {
    $stmt->bindValue(':search', "%$search%", PDO::PARAM_STR);
}

$stmt->execute();
$applications = $stmt->fetchAll(PDO::FETCH_ASSOC);

renderRolePageStart(
    'client',
    'applications',
    'Applications',
    'Review developer applications across all of your projects.'
);
?>

<section class="panel-card">

<h2 class="section-title">Developer Applications</h2>

<!-- SEARCH BAR -->
<form method="GET" class="mb-3 d-flex gap-2">

<input
type="text"
name="search"
class="form-control"
placeholder="Search developer or project..."
value="<?php echo htmlspecialchars($search); ?>"
>

<button class="btn btn-primary">Search</button>

<a href="<?php echo htmlspecialchars(appUrl('client/applications.php')); ?>" class="btn btn-secondary">
Reset
</a>

</form>

<div class="table-responsive">

<table class="table table-clean align-middle mb-0">

<thead>
<tr>
<th>Application</th>
<th>Project</th>
<th>Developer</th>
<th>Status</th>
<th>Submitted</th>
<th>Review</th>
</tr>
</thead>

<tbody>

<?php if ($applications): ?>

<?php foreach ($applications as $application): ?>

<tr>

<td>#<?php echo (int) $application['id']; ?></td>

<td><?php echo htmlspecialchars($application['project_title']); ?></td>

<td><?php echo htmlspecialchars($application['developer_name']); ?></td>

<td>
<span class="status-badge status-<?php echo htmlspecialchars(badgeClass($application['status'])); ?>">
<?php echo htmlspecialchars(formatStatusLabel($application['status'])); ?>
</span>
</td>

<td>
<?php echo htmlspecialchars(date('M j, Y', strtotime($application['created_at']))); ?>
</td>

<td>
<a
href="<?php echo htmlspecialchars(appUrl('client/proposals.php')); ?>?project_id=<?php echo (int) $application['project_id']; ?>"
class="btn btn-sm btn-outline-primary">
Open Project
</a>
</td>

</tr>

<?php endforeach; ?>

<?php else: ?>

<tr>
<td colspan="6" class="text-center text-muted py-4">
No applications found for your search.
</td>
</tr>

<?php endif; ?>

</tbody>

</table>

</div>

</section>

<?php renderRolePageEnd(); ?>