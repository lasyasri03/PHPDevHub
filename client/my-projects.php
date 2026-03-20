<?php
include '../includes/db.php';
include '../includes/auth.php';
require_once '../includes/marketplace_helpers.php';
require_once '../includes/role_ui.php';

requireRole('client');

$clientId = (int) ($_SESSION['user_id'] ?? 0);

/* SEARCH VALUE */
$search = trim($_GET['search'] ?? '');

/* BASE QUERY */
$sql = "SELECT p.*,
        COUNT(DISTINCT hr.id) AS proposal_count,
        COUNT(DISTINCT CASE WHEN hr.status = 'accepted' THEN hr.id END) AS hired_count
        FROM projects p
        LEFT JOIN hire_requests hr ON hr.project_id = p.id
        WHERE p.client_id = :client_id";

/* APPLY SEARCH FILTER */
if ($search !== '') {
    $sql .= " AND p.title LIKE :search";
}

$sql .= " GROUP BY p.id, p.client_id, p.title, p.description, p.budget, 
          p.deadline, p.developers_needed, p.status, p.created_at
          ORDER BY p.created_at DESC, p.id DESC";

$stmt = $pdo->prepare($sql);

/* BIND PARAMETERS */
$stmt->bindValue(':client_id', $clientId, PDO::PARAM_INT);

if ($search !== '') {
    $stmt->bindValue(':search', "%$search%", PDO::PARAM_STR);
}

$stmt->execute();
$projects = $stmt->fetchAll(PDO::FETCH_ASSOC);

renderRolePageStart('client', 'projects', 'My Projects', 'Only projects posted by you are visible here.');
?>

<section class="panel-card">

<div class="d-flex justify-content-between align-items-center gap-3 flex-wrap mb-3">

<div>
<h2 class="section-title mb-1">Project Records</h2>
<p class="meta-text mb-0">Review project status, incoming applications, and dispute access from one table.</p>
</div>

<a href="<?php echo htmlspecialchars(appUrl('client/post-project.php')); ?>" class="btn btn-primary">
Post New Project
</a>

</div>


<!-- SEARCH FORM -->
<form method="GET" class="mb-3 d-flex gap-2">

<input
type="text"
name="search"
class="form-control"
placeholder="Search project title..."
value="<?php echo htmlspecialchars($search); ?>"
>

<button class="btn btn-primary">Search</button>

<a href="<?php echo htmlspecialchars(appUrl('client/my-projects.php')); ?>" class="btn btn-secondary">
Reset
</a>

</form>


<div class="table-responsive">

<table class="table table-clean align-middle mb-0">

<thead>

<tr>
<th>Title</th>
<th>Budget</th>
<th>Status</th>
<th>Applications</th>
<th>Contracts</th>
<th>Deadline</th>
<th>Actions</th>
</tr>

</thead>

<tbody>

<?php if ($projects): ?>

<?php foreach ($projects as $project): ?>

<tr>

<td>

<strong><?php echo htmlspecialchars($project['title']); ?></strong>

<div class="meta-text">

<?php echo htmlspecialchars(date('M j, Y', strtotime($project['created_at']))); ?>

</div>

</td>

<td>$<?php echo number_format((float) $project['budget'], 2); ?></td>

<td>

<span class="status-badge status-<?php echo htmlspecialchars(badgeClass($project['status'])); ?>">

<?php echo htmlspecialchars(formatStatusLabel($project['status'])); ?>

</span>

</td>

<td><?php echo (int) $project['proposal_count']; ?></td>

<td><?php echo (int) $project['hired_count']; ?></td>

<td>

<?php echo !empty($project['deadline'])
? htmlspecialchars(date('M j, Y', strtotime($project['deadline'])))
: 'N/A'; ?>

</td>

<td class="d-flex gap-2 flex-wrap">

<a href="<?php echo htmlspecialchars(appUrl('client/proposals.php')); ?>?project_id=<?php echo (int) $project['id']; ?>"
class="btn btn-sm btn-outline-primary">
Applications
</a>

<a href="<?php echo htmlspecialchars(appUrl('client/manage-project.php')); ?>?project_id=<?php echo (int) $project['id']; ?>"
class="btn btn-sm btn-outline-secondary">
Tasks
</a>

<?php if ((int) $project['hired_count'] > 0): ?>

<a href="<?php echo htmlspecialchars(appUrl('client/disputes.php')); ?>?project_id=<?php echo (int) $project['id']; ?>"
class="btn btn-sm btn-outline-danger">
Open Dispute
</a>

<?php endif; ?>

</td>

</tr>

<?php endforeach; ?>

<?php else: ?>

<tr>

<td colspan="7" class="text-center text-muted py-4">
No projects found for your search.
</td>

</tr>

<?php endif; ?>

</tbody>

</table>

</div>

</section>

<?php renderRolePageEnd(); ?>