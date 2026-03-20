<?php
include '../includes/db.php';
include '../includes/auth.php';
require_once '../includes/role_ui.php';
requireRole('client');

$clientId = (int) $_SESSION['user_id'];

$stmt = $pdo->prepare(
    "SELECT id, name, email, role, account_status, created_at
     FROM users
     WHERE id = ?"
);
$stmt->execute([$clientId]);
$profile = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

$summaryStmt = $pdo->prepare(
    "SELECT
        (SELECT COUNT(*) FROM projects WHERE client_id = ?) AS projects_count,
        (SELECT COUNT(*) FROM hire_requests WHERE client_id = ? AND status = 'accepted') AS hires_count,
        (SELECT COUNT(*) FROM disputes WHERE client_id = ?) AS disputes_count"
);
$summaryStmt->execute([$clientId, $clientId, $clientId]);
$summary = $summaryStmt->fetch(PDO::FETCH_ASSOC) ?: [];

renderRolePageStart('client', 'profile', 'Profile', 'A quick view of your client account and marketplace activity.');
?>
<section class="stat-grid">
    <div class="stat-card">
        <span class="stat-label">Projects</span>
        <div class="stat-value"><?php echo (int) ($summary['projects_count'] ?? 0); ?></div>
    </div>
    <div class="stat-card">
        <span class="stat-label">Accepted Hires</span>
        <div class="stat-value"><?php echo (int) ($summary['hires_count'] ?? 0); ?></div>
    </div>
    <div class="stat-card">
        <span class="stat-label">Disputes</span>
        <div class="stat-value"><?php echo (int) ($summary['disputes_count'] ?? 0); ?></div>
    </div>
</section>

<section class="panel-card">
    <h2 class="section-title mb-3">Account Details</h2>
    <div class="row g-3">
        <div class="col-md-6">
            <div class="list-item h-100">
                <span class="stat-label">Name</span>
                <div class="fw-semibold"><?php echo htmlspecialchars($profile['name'] ?? $_SESSION['name'] ?? ''); ?></div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="list-item h-100">
                <span class="stat-label">Email</span>
                <div class="fw-semibold"><?php echo htmlspecialchars($profile['email'] ?? $_SESSION['email'] ?? ''); ?></div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="list-item h-100">
                <span class="stat-label">Role</span>
                <div class="fw-semibold text-capitalize"><?php echo htmlspecialchars($profile['role'] ?? 'client'); ?></div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="list-item h-100">
                <span class="stat-label">Account Status</span>
                <div class="fw-semibold text-capitalize"><?php echo htmlspecialchars($profile['account_status'] ?? 'active'); ?></div>
            </div>
        </div>
        <div class="col-12">
            <div class="list-item">
                <span class="stat-label">Member Since</span>
                <div class="fw-semibold"><?php echo !empty($profile['created_at']) ? htmlspecialchars(date('F j, Y', strtotime($profile['created_at']))) : 'N/A'; ?></div>
            </div>
        </div>
    </div>
</section>
<?php renderRolePageEnd(); ?>
