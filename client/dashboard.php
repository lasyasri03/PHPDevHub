<?php
include '../includes/db.php';
include '../includes/auth.php';
require_once '../includes/marketplace_helpers.php';
require_once '../includes/role_ui.php';
requireRole('client');

$userId = (int) $_SESSION['user_id'];
$successMessage = $_GET['success'] ?? '';
$errorMessage = $_GET['error'] ?? '';
$search = trim($_GET['search'] ?? '');

$statsStmt = $pdo->prepare(
    "SELECT
        (SELECT COUNT(*) FROM projects WHERE client_id = ?) AS projects_count,
        (SELECT COUNT(*) FROM hire_requests WHERE client_id = ?) AS hire_requests_count,
        (SELECT COUNT(*) FROM hire_requests WHERE client_id = ? AND status = 'accepted') AS active_hires,
        (SELECT COUNT(*) FROM messages m INNER JOIN hire_requests hr ON hr.id = m.hire_request_id WHERE hr.client_id = ?) AS messages_count,
        (SELECT COUNT(*) FROM payments WHERE client_id = ? AND payment_status = 'Paid') AS paid_payments_count,
        (SELECT COUNT(*) FROM payments WHERE client_id = ? AND payment_status = 'Pending') AS pending_payments_count"
);
$statsStmt->execute([$userId, $userId, $userId, $userId, $userId, $userId]);
$stats = $statsStmt->fetch(PDO::FETCH_ASSOC) ?: [];

$recentHireRequestsStmt = $pdo->prepare(
    "SELECT hr.id, hr.status, hr.created_at, u.name AS developer_name, p.title AS project_title
     FROM hire_requests hr
     INNER JOIN users u ON u.id = hr.developer_id
     LEFT JOIN projects p ON p.id = hr.project_id
     WHERE hr.client_id = ?
     ORDER BY hr.created_at DESC, hr.id DESC
     LIMIT 5"
);
$recentHireRequestsStmt->execute([$userId]);
$recentHireRequests = $recentHireRequestsStmt->fetchAll(PDO::FETCH_ASSOC);

$recentDevelopersStmt = $pdo->query(
    "SELECT u.id, u.name, d.php_proficiency, d.skills, d.hourly_rate, d.availability
     FROM users u
     INNER JOIN developers d ON d.user_id = u.id
     WHERE u.role = 'developer'
     ORDER BY u.created_at DESC, u.id DESC
     LIMIT 4"
);
$recentDevelopers = $recentDevelopersStmt->fetchAll(PDO::FETCH_ASSOC);

$activeContractsStmt = $pdo->prepare(
    "SELECT hr.id,
            p.id AS project_id,
            p.title AS project_title,
            p.budget,
            p.status AS project_status,
            hr.developer_id,
            u.name AS developer_name,
            pay.payment_status,
            pay.transaction_id
     FROM hire_requests hr
     INNER JOIN projects p ON p.id = hr.project_id
     INNER JOIN users u ON u.id = hr.developer_id
     LEFT JOIN payments pay
        ON pay.project_id = p.id
       AND pay.client_id = hr.client_id
       AND pay.developer_id = hr.developer_id
     WHERE hr.client_id = ? AND hr.status = 'accepted'
     ORDER BY hr.created_at DESC, hr.id DESC
     LIMIT 5"
);
$activeContractsStmt->execute([$userId]);
$activeContracts = $activeContractsStmt->fetchAll(PDO::FETCH_ASSOC);

$developerResults = [];
$projectResults = [];
$contractResults = [];

if ($search !== '') {
    $searchLike = '%' . $search . '%';

    $developerSearchStmt = $pdo->prepare(
        "SELECT u.id, u.name, d.skills, d.php_proficiency, d.availability
         FROM users u
         INNER JOIN developers d ON d.user_id = u.id
         WHERE u.role = 'developer'
           AND (u.name LIKE ? OR COALESCE(d.skills, '') LIKE ?)
         ORDER BY u.created_at DESC, u.id DESC
         LIMIT 8"
    );
    $developerSearchStmt->execute([$searchLike, $searchLike]);
    $developerResults = $developerSearchStmt->fetchAll(PDO::FETCH_ASSOC);

    $projectSearchStmt = $pdo->prepare(
        "SELECT id, title, description, budget, status
         FROM projects
         WHERE client_id = ?
           AND title LIKE ?
         ORDER BY created_at DESC, id DESC
         LIMIT 8"
    );
    $projectSearchStmt->execute([$userId, $searchLike]);
    $projectResults = $projectSearchStmt->fetchAll(PDO::FETCH_ASSOC);

    $contractSearchStmt = $pdo->prepare(
        "SELECT hr.id, p.title AS project_title, p.status AS project_status, u.name AS developer_name
         FROM hire_requests hr
         INNER JOIN projects p ON p.id = hr.project_id
         INNER JOIN users u ON u.id = hr.developer_id
         WHERE hr.client_id = ?
           AND hr.status = 'accepted'
           AND (p.title LIKE ? OR u.name LIKE ?)
         ORDER BY hr.created_at DESC, hr.id DESC
         LIMIT 8"
    );
    $contractSearchStmt->execute([$userId, $searchLike, $searchLike]);
    $contractResults = $contractSearchStmt->fetchAll(PDO::FETCH_ASSOC);
}

renderRolePageStart('client', 'dashboard', 'Client Dashboard', 'Post projects, review hire requests, and discover PHP developers from one consistent workspace.', 'Search projects, developers, and contracts...', $search);
?>
<?php if ($successMessage !== ''): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($successMessage); ?></div>
<?php endif; ?>
<?php if ($errorMessage !== ''): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($errorMessage); ?></div>
<?php endif; ?>

<?php if ($search !== ''): ?>
    <section class="panel-card">
        <div class="panel-header">
            <div>
                <h2 class="section-title">Search Results for "<?php echo htmlspecialchars($search); ?>"</h2>
                <p class="section-copy">Matching developers, projects, and contracts from your client workspace.</p>
            </div>
        </div>

        <?php if (!$developerResults && !$projectResults && !$contractResults): ?>
            <p class="empty-state">No results found.</p>
        <?php else: ?>
            <?php if ($developerResults): ?>
                <div class="row g-3 mb-4">
                    <?php foreach ($developerResults as $developer): ?>
                        <div class="col-md-6">
                            <article class="list-item h-100">
                                <div class="list-item-top">
                                    <div>
                                        <h3><?php echo htmlspecialchars($developer['name']); ?></h3>
                                        <p class="meta-text mb-0"><?php echo htmlspecialchars($developer['php_proficiency'] ?: 'PHP level not set'); ?></p>
                                    </div>
                                    <span class="status-badge status-<?php echo htmlspecialchars(badgeClass((string) ($developer['availability'] ?? 'secondary'))); ?>">
                                        <?php echo htmlspecialchars($developer['availability'] ?: 'Not available'); ?>
                                    </span>
                                </div>
                                <p class="meta-text mb-0"><?php echo htmlspecialchars($developer['skills'] ?: 'Skills not provided'); ?></p>
                            </article>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($projectResults): ?>
                <div class="list-stack mb-4">
                    <?php foreach ($projectResults as $project): ?>
                        <article class="list-item">
                            <div class="list-item-top">
                                <div>
                                    <h3><?php echo htmlspecialchars($project['title']); ?></h3>
                                    <p class="meta-text mb-0"><?php echo htmlspecialchars($project['description'] ?: 'No description added'); ?></p>
                                </div>
                                <span class="status-badge status-<?php echo htmlspecialchars(badgeClass((string) $project['status'])); ?>">
                                    <?php echo htmlspecialchars(formatStatusLabel((string) $project['status'])); ?>
                                </span>
                            </div>
                            <div class="pill-row">
                                <span class="info-pill">$<?php echo number_format((float) $project['budget'], 2); ?></span>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($contractResults): ?>
                <div class="list-stack">
                    <?php foreach ($contractResults as $contract): ?>
                        <article class="list-item">
                            <div class="list-item-top">
                                <div>
                                    <h3><?php echo htmlspecialchars($contract['project_title']); ?></h3>
                                    <p class="meta-text mb-0">Developer: <?php echo htmlspecialchars($contract['developer_name']); ?></p>
                                </div>
                                <span class="status-badge status-<?php echo htmlspecialchars(badgeClass((string) $contract['project_status'])); ?>">
                                    <?php echo htmlspecialchars(formatStatusLabel((string) $contract['project_status'])); ?>
                                </span>
                            </div>
                            <div class="pill-row">
                                <span class="info-pill">Contract #<?php echo (int) $contract['id']; ?></span>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </section>
<?php endif; ?>

<section class="stat-grid">
    <div class="stat-card">
        <span class="stat-label">Projects Posted</span>
        <div class="stat-value"><?php echo (int) ($stats['projects_count'] ?? 0); ?></div>
        <div class="stat-note">Total project listings you have published.</div>
    </div>
    <div class="stat-card">
        <span class="stat-label">Hire Requests</span>
        <div class="stat-value"><?php echo (int) ($stats['hire_requests_count'] ?? 0); ?></div>
        <div class="stat-note">All requests sent to developers.</div>
    </div>
    <div class="stat-card">
        <span class="stat-label">Active Hires</span>
        <div class="stat-value"><?php echo (int) ($stats['active_hires'] ?? 0); ?></div>
        <div class="stat-note">Accepted engagements in progress.</div>
    </div>
    <div class="stat-card">
        <span class="stat-label">Messages</span>
        <div class="stat-value"><?php echo (int) ($stats['messages_count'] ?? 0); ?></div>
        <div class="stat-note">Conversation history across hires.</div>
    </div>
    <div class="stat-card">
        <span class="stat-label">Paid Payments</span>
        <div class="stat-value"><?php echo (int) ($stats['paid_payments_count'] ?? 0); ?></div>
        <div class="stat-note">Contracts already paid through the platform.</div>
    </div>

</section>

<section class="panel-card">
    <div class="panel-header">
        <div>
            <h2 class="section-title">Active Contracts & Payments</h2>
            <p class="section-copy">Track accepted project work and complete payments through Stripe Checkout.</p>
        </div>
        <a href="<?php echo htmlspecialchars(appUrl('client/contracts.php')); ?>" class="btn btn-outline-primary">View Contracts</a>
    </div>

    <?php if ($activeContracts): ?>
        <div class="list-stack">
            <?php foreach ($activeContracts as $contract): ?>
                <?php $paymentStatus = $contract['payment_status'] ?: 'Pending'; ?>
                <article class="list-item">
                    <div class="list-item-top">
                        <div>
                            <h3><?php echo htmlspecialchars($contract['project_title']); ?></h3>
                            <p class="meta-text mb-0">Developer: <?php echo htmlspecialchars($contract['developer_name']); ?></p>
                        </div>
                        <span class="status-badge status-<?php echo htmlspecialchars(badgeClass((string) strtolower($paymentStatus))); ?>">
                            <?php echo htmlspecialchars($paymentStatus); ?>
                        </span>
                    </div>
                    <div class="pill-row">
                        <span class="info-pill">$<?php echo number_format((float) $contract['budget'], 2); ?></span>
                        <span class="info-pill"><?php echo htmlspecialchars(formatStatusLabel((string) $contract['project_status'])); ?></span>
                        <?php if ($paymentStatus !== 'Paid'): ?>
                            <form action="<?php echo htmlspecialchars(appUrl('create-checkout-session.php')); ?>" method="POST" class="d-inline">
                                <input type="hidden" name="contract_id" value="<?php echo (int) $contract['id']; ?>">
                                <input type="hidden" name="project_id" value="<?php echo (int) $contract['project_id']; ?>">
                                <input type="hidden" name="developer_id" value="<?php echo (int) ($contract['developer_id'] ?? 0); ?>">
                                <input type="hidden" name="amount" value="<?php echo htmlspecialchars((string) $contract['budget']); ?>">
                                <button type="submit" class="btn btn-primary btn-sm">Pay Project</button>
                            </form>
                        <?php else: ?>
                            <span class="info-pill"><?php echo htmlspecialchars($contract['transaction_id'] ? 'Txn: ' . $contract['transaction_id'] : 'Payment captured'); ?></span>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="empty-state">No active contracts yet. Accepted hires will appear here once you start working with a developer.</div>
    <?php endif; ?>
</section>

<section class="panel-card">
    <div class="panel-header">
        <div>
            <h2 class="section-title">Recent Hire Requests</h2>
            <p class="section-copy">Track the latest outreach and acceptance status for your developer hiring pipeline.</p>
        </div>
        <a href="<?php echo htmlspecialchars(appUrl('client/applications.php')); ?>" class="btn btn-outline-primary">View All</a>
    </div>

    <?php if ($recentHireRequests): ?>
        <div class="list-stack">
            <?php foreach ($recentHireRequests as $request): ?>
                <article class="list-item">
                    <div class="list-item-top">
                        <div>
                            <h3><?php echo htmlspecialchars($request['developer_name']); ?></h3>
                            <p class="meta-text mb-0"><?php echo htmlspecialchars($request['project_title'] ?: 'Direct hire request'); ?></p>
                        </div>
                        <span class="status-badge status-<?php echo htmlspecialchars(badgeClass($request['status'])); ?>">
                            <?php echo htmlspecialchars(formatStatusLabel($request['status'])); ?>
                        </span>
                    </div>
                    <div class="pill-row">
                        <span class="info-pill">Request #<?php echo (int) $request['id']; ?></span>
                        <span class="info-pill"><?php echo htmlspecialchars(date('M j, Y', strtotime($request['created_at']))); ?></span>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="empty-state">No hire requests yet. Start by browsing developers and sending your first request.</div>
    <?php endif; ?>
</section>

<section class="panel-card">
    <div class="panel-header">
        <div>
            <h2 class="section-title">Recent Developers</h2>
            <p class="section-copy">A quick view of talent recently available on the platform.</p>
        </div>
        <a href="<?php echo htmlspecialchars(appUrl('developers/list.php')); ?>" class="btn btn-primary">Find Developers</a>
    </div>

    <?php if ($recentDevelopers): ?>
        <div class="row g-3">
            <?php foreach ($recentDevelopers as $developer): ?>
                <div class="col-md-6">
                    <article class="list-item h-100">
                        <div class="list-item-top">
                            <div>
                                <h3><?php echo htmlspecialchars($developer['name']); ?></h3>
                                <p class="meta-text mb-0"><?php echo htmlspecialchars($developer['php_proficiency'] ?: 'PHP level not set'); ?></p>
                            </div>
                            <span class="status-badge status-<?php echo htmlspecialchars(badgeClass((string) ($developer['availability'] ?? 'secondary'))); ?>">
                                <?php echo htmlspecialchars($developer['availability'] ?: 'Not available'); ?>
                            </span>
                        </div>
                        <p class="meta-text mb-2"><?php echo htmlspecialchars($developer['skills'] ?: 'Skills will appear once the profile is completed.'); ?></p>
                        <div class="pill-row">
                            <span class="info-pill">
                                <?php echo $developer['hourly_rate'] !== null ? '$' . number_format((float) $developer['hourly_rate'], 2) . '/hr' : 'Rate not set'; ?>
                            </span>
                            <a href="<?php echo htmlspecialchars(appUrl('developers/profile.php')); ?>?id=<?php echo (int) $developer['id']; ?>" class="btn btn-outline-primary btn-sm">View Profile</a>
                        </div>
                    </article>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="empty-state">Developer profiles will appear here once talent joins the marketplace.</div>
    <?php endif; ?>
</section>

<?php renderRolePageEnd(); ?>
