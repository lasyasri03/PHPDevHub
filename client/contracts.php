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
            p.budget,
            p.status AS project_status,
            hr.developer_id,
            u.name AS developer_name,
            hr.created_at AS contract_date,
            pay.payment_status,
            pay.transaction_id
     FROM hire_requests hr
     INNER JOIN projects p ON p.id = hr.project_id
     INNER JOIN users u ON u.id = hr.developer_id
     LEFT JOIN payments pay
        ON pay.project_id = p.id
       AND pay.client_id = hr.client_id
       AND pay.developer_id = hr.developer_id
     WHERE hr.client_id = :client_id
     AND hr.status = 'accepted'";

/* APPLY SEARCH FILTER */
if ($search !== '') {
    $sql .= " AND (p.title LIKE :search OR u.name LIKE :search)";
}

$sql .= " ORDER BY hr.created_at DESC, hr.id DESC";

$stmt = $pdo->prepare($sql);

/* BIND PARAMETERS */
$stmt->bindValue(':client_id', $clientId, PDO::PARAM_INT);

if ($search !== '') {
    $stmt->bindValue(':search', "%$search%", PDO::PARAM_STR);
}

$stmt->execute();
$contracts = $stmt->fetchAll(PDO::FETCH_ASSOC);

renderRolePageStart(
    'client',
    'contracts',
    'Contracts',
    'Accepted hires become shared contracts across the marketplace.'
);
?>

<section class="panel-card">

<h2 class="section-title">Active Contract Records</h2>

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

<a href="<?php echo htmlspecialchars(appUrl('client/contracts.php')); ?>" class="btn btn-secondary">
Reset
</a>

</form>

<div class="table-responsive">

<table class="table table-clean align-middle mb-0">

<thead>
<tr>
<th>Contract</th>
<th>Project</th>
<th>Developer</th>
<th>Budget</th>
<th>Status</th>
<th>Payment</th>
<th>Started</th>
<th>Actions</th>
</tr>
</thead>

<tbody>

<?php if ($contracts): ?>

<?php foreach ($contracts as $contract): ?>

<?php $paymentStatus = $contract['payment_status'] ?: 'Pending'; ?>

<tr>

<td>#<?php echo (int) $contract['id']; ?></td>

<td><?php echo htmlspecialchars($contract['project_title']); ?></td>

<td><?php echo htmlspecialchars($contract['developer_name']); ?></td>

<td>$<?php echo number_format((float) $contract['budget'], 2); ?></td>

<td>
<span class="status-badge status-<?php echo htmlspecialchars(badgeClass($contract['project_status'])); ?>">
<?php echo htmlspecialchars(formatStatusLabel($contract['project_status'])); ?>
</span>
</td>

<td>
<span class="status-badge status-<?php echo htmlspecialchars(badgeClass((string) strtolower($paymentStatus))); ?>">
<?php echo htmlspecialchars($paymentStatus); ?>
</span>
</td>

<td>
<?php echo htmlspecialchars(date('M j, Y', strtotime($contract['contract_date']))); ?>
</td>

<td class="d-flex gap-2 flex-wrap">

<a
class="btn btn-sm btn-primary"
href="<?php echo htmlspecialchars(appUrl('chat/project_chat.php')); ?>?request_id=<?php echo (int) $contract['id']; ?>">
Open Chat
</a>

<a
class="btn btn-sm btn-outline-primary"
href="<?php echo htmlspecialchars(appUrl('client/disputes.php')); ?>?project_id=<?php echo (int) $contract['project_id']; ?>">
Open Dispute
</a>

<?php if ($paymentStatus !== 'Paid'): ?>

<form action="<?php echo htmlspecialchars(appUrl('create-checkout-session.php')); ?>" method="POST" class="d-inline">

<input type="hidden" name="contract_id" value="<?php echo (int) $contract['id']; ?>">

<input type="hidden" name="project_id" value="<?php echo (int) $contract['project_id']; ?>">

<input type="hidden" name="developer_id" value="<?php echo (int) $contract['developer_id']; ?>">

<input type="hidden" name="amount" value="<?php echo htmlspecialchars((string) $contract['budget']); ?>">

<button type="submit" class="btn btn-sm btn-primary">Pay Project</button>

</form>

<?php else: ?>

<span class="text-muted small">
<?php echo htmlspecialchars($contract['transaction_id'] ? 'Txn: ' . $contract['transaction_id'] : 'Paid'); ?>
</span>

<?php endif; ?>

</td>

</tr>

<?php endforeach; ?>

<?php else: ?>

<tr>
<td colspan="8" class="text-center text-muted py-4">
No contracts found for your search.
</td>
</tr>

<?php endif; ?>

</tbody>

</table>

</div>

</section>

<?php renderRolePageEnd(); ?>