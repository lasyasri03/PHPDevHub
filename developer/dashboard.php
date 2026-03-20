<?php

/* =========================
   Includes & Auth
========================= */

include '../includes/db.php';
include '../includes/auth.php';
require_once '../includes/marketplace_helpers.php';
require_once '../includes/role_ui.php';

requireRole('developer');


/* =========================
   Basic Variables
========================= */

$userId = (int) $_SESSION['user_id'];

$successMessage = $_GET['success'] ?? '';
$errorMessage   = $_GET['error'] ?? '';


/* =========================
   SECTION 1 + 2
   Dashboard Statistics
   (available, accepted, work,
    earnings, pending, completed)
========================= */

$statsStmt = $pdo->prepare(
    "SELECT
        (SELECT COUNT(*)
         FROM projects p
         WHERE p.status IN ('approved', 'open', 'active')
         AND NOT EXISTS (
            SELECT 1 FROM hire_requests hr
            WHERE hr.project_id  = p.id
            AND   hr.developer_id = ?
         )
        ) AS available_projects,

        (SELECT COUNT(*)
         FROM hire_requests
         WHERE developer_id = ?
         AND   status       = 'accepted'
        ) AS accepted_projects,

        (SELECT COUNT(*)
         FROM hire_requests
         WHERE developer_id = ?
         AND   status       = 'pending'
         AND   project_id   IS NULL
        ) AS pending_requests,

        (SELECT COALESCE(SUM(amount), 0)
         FROM payments
         WHERE developer_id  = ?
         AND   payment_status = 'completed'
        ) AS total_earnings,

        (SELECT COUNT(*)
         FROM payments
         WHERE developer_id  = ?
         AND   payment_status = 'pending'
        ) AS pending_payments,

        (SELECT COUNT(*)
         FROM payments
         WHERE developer_id  = ?
         AND   payment_status = 'completed'
        ) AS completed_payments"
);

$statsStmt->execute([
    $userId, $userId, $userId,
    $userId, $userId, $userId,
]);

$stats = $statsStmt->fetch(PDO::FETCH_ASSOC) ?: [];


/* =========================
   SECTION 3
   Available Projects
   (not yet applied, latest 5)
========================= */

$availableProjectsStmt = $pdo->prepare(
    "SELECT
        p.id,
        p.title,
        p.budget,
        p.deadline,
        p.created_at,
        u.name AS client_name
     FROM projects p
     INNER JOIN users u ON u.id = p.client_id
     WHERE p.status IN ('approved', 'open', 'active')
     AND NOT EXISTS (
        SELECT 1 FROM hire_requests hr
        WHERE hr.project_id   = p.id
        AND   hr.developer_id = ?
     )
     ORDER BY p.created_at DESC, p.id DESC
     LIMIT 5"
);

$availableProjectsStmt->execute([$userId]);
$availableProjects = $availableProjectsStmt->fetchAll(PDO::FETCH_ASSOC);


/* =========================
   SECTION 4
   Accepted Projects
   (developer was hired, latest 5)
========================= */

$acceptedProjectsStmt = $pdo->prepare(
    "SELECT
        hr.id           AS hire_id,
        p.id            AS project_id,
        p.title,
        p.budget,
        p.status        AS project_status,
        hr.created_at,
        u.name          AS client_name
     FROM hire_requests hr
     LEFT JOIN projects p ON p.id = hr.project_id
     LEFT JOIN users    u ON u.id = hr.client_id
     WHERE hr.developer_id = ?
     AND   hr.status       = 'accepted'
     ORDER BY hr.created_at DESC, hr.id DESC
     LIMIT 5"
);

$acceptedProjectsStmt->execute([$userId]);
$acceptedProjects = $acceptedProjectsStmt->fetchAll(PDO::FETCH_ASSOC);


/* =========================
   SECTION 6
   Payment Records
   (recent 5, all statuses)
========================= */

$paymentRecordsStmt = $pdo->prepare(
    "SELECT
        p.title         AS project_title,
        u.name          AS client_name,
        pay.amount,
        pay.payment_status,
        pay.transaction_id,
        pay.created_at,
        pay.paid_at
     FROM payments pay
     INNER JOIN projects p ON p.id = pay.project_id
     INNER JOIN users    u ON u.id = pay.client_id
     WHERE pay.developer_id = ?
     ORDER BY COALESCE(pay.paid_at, pay.created_at) DESC, pay.id DESC
     LIMIT 5"
);

$paymentRecordsStmt->execute([$userId]);
$paymentRecords = $paymentRecordsStmt->fetchAll(PDO::FETCH_ASSOC);


/* =========================
   Derived Calculations
========================= */

$totalEarnings     = (float)($stats['total_earnings']     ?? 0);
$completedPayments = (int)  ($stats['completed_payments'] ?? 0);
$pendingPayments   = (int)  ($stats['pending_payments']   ?? 0);
$averageBudget     = $completedPayments > 0
                        ? ($totalEarnings / $completedPayments)
                        : 0;


/* =========================
   SECTION 2.5
   Pending Hire Requests
   (direct from clients)
========================= */

$pendingRequestsStmt = $pdo->prepare(
    "SELECT
        hr.id AS hire_id,
        hr.message,
        hr.created_at,
        u.name AS client_name
     FROM hire_requests hr
     INNER JOIN users u ON u.id = hr.client_id
     WHERE hr.developer_id = ?
     AND   hr.status       = 'pending'
     AND   hr.project_id   IS NULL
     ORDER BY hr.created_at DESC, hr.id DESC
     LIMIT 5"
);

$pendingRequestsStmt->execute([$userId]);
$pendingRequests = $pendingRequestsStmt->fetchAll(PDO::FETCH_ASSOC);


/* =========================
   Page Shell Start
========================= */

renderRolePageStart(
    'developer',
    'dashboard',
    'Developer Dashboard',
    'Review new opportunities, keep accepted work moving, and monitor your earnings in one place.'
);

?>

<?php if ($successMessage !== ''): ?>
<div class="alert alert-success">
    <?php echo htmlspecialchars($successMessage); ?>
</div>
<?php endif; ?>

<?php if ($errorMessage !== ''): ?>
<div class="alert alert-danger">
    <?php echo htmlspecialchars($errorMessage); ?>
</div>
<?php endif; ?>


<!-- ═══════════════════════════════════════════════════
     SECTION 1 — Statistics Cards
════════════════════════════════════════════════════ -->
<section class="stat-grid">

    <div class="stat-card">
        <span class="stat-label">Available Projects</span>
        <div class="stat-value"><?php echo (int)($stats['available_projects'] ?? 0); ?></div>
        <div class="stat-note">Open opportunities you have not applied to yet.</div>
    </div>

    <div class="stat-card">
        <span class="stat-label">Accepted Projects</span>
        <div class="stat-value"><?php echo (int)($stats['accepted_projects'] ?? 0); ?></div>
        <div class="stat-note">Projects where your hire request was accepted.</div>
    </div>

    <div class="stat-card">
        <span class="stat-label">Pending Requests</span>
        <div class="stat-value"><?php echo (int)($stats['pending_requests'] ?? 0); ?></div>
        <div class="stat-note">Incoming hire requests waiting for your response.</div>
    </div>

    <div class="stat-card">
        <span class="stat-label">Total Earnings</span>
        <div class="stat-value">$<?php echo number_format($totalEarnings, 0); ?></div>
        <div class="stat-note">Confirmed payments received through the platform.</div>
    </div>

</section><!-- /stat-grid -->


<!-- ═══════════════════════════════════════════════════
     SECTION 2 — Payment Overview
════════════════════════════════════════════════════ -->
<section class="dash-section">

    <div class="dash-section-header">
        <h2 class="dash-section-title">Payment Overview</h2>
    </div>

    <div class="payment-overview-grid">

        <div class="payment-overview-card payment-overview-card--pending">
            <div class="po-label">Pending Payments</div>
            <div class="po-value"><?php echo $pendingPayments; ?></div>
            <div class="po-note">Awaiting release from accepted contracts.</div>
        </div>

        <div class="payment-overview-card payment-overview-card--completed">
            <div class="po-label">Completed Payments</div>
            <div class="po-value"><?php echo $completedPayments; ?></div>
            <div class="po-note">Successfully paid out to your account.</div>
        </div>

        <div class="payment-overview-card payment-overview-card--avg">
            <div class="po-label">Average per Project</div>
            <div class="po-value">$<?php echo number_format($averageBudget, 0); ?></div>
            <div class="po-note">Based on your completed payment history.</div>
        </div>

    </div>

</section><!-- /payment-overview -->


<!-- ═══════════════════════════════════════════════════
     SECTION 2.5 — Pending Hire Requests
════════════════════════════════════════════════════ -->
<section class="dash-section">

    <div class="dash-section-header">
        <h2 class="dash-section-title">Pending Hire Requests</h2>
        <a href="/developer/hire-requests.php" class="btn btn-primary btn-sm">View All</a>
    </div>

    <?php if (empty($pendingRequests)): ?>
        <div class="empty-state">
            <p>No new direct hire requests right now. Wait for clients to reach out.</p>
        </div>
    <?php else: ?>
        <div class="project-list">
            <?php foreach ($pendingRequests as $req): ?>
            <div class="project-row">

                <div class="project-row__main">
                    <div class="project-row__title">
                        Direct Request from <?php echo htmlspecialchars($req['client_name']); ?>
                    </div>
                    <div class="project-row__meta">
                        <span class="meta-item">
                            &#128100;&nbsp;<?php echo htmlspecialchars($req['client_name']); ?>
                        </span>
                        <span class="meta-item">
                            &#128336;&nbsp;Received <?php echo htmlspecialchars(date('M j, Y', strtotime($req['created_at']))); ?>
                        </span>
                    </div>
                </div>

                <div class="project-row__actions">
                    <a href="/developer/hire-requests.php"
                       class="btn btn-primary btn-sm">Review</a>
                </div>

            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</section><!-- /pending-hire-requests -->


<!-- ═══════════════════════════════════════════════════
     SECTION 3 — Available Projects
════════════════════════════════════════════════════ -->
<section class="dash-section">

    <div class="dash-section-header">
        <h2 class="dash-section-title">Available Projects</h2>
        <a href="/developer/browse-projects.php" class="btn btn-primary btn-sm">Browse All</a>
    </div>

    <?php if (empty($availableProjects)): ?>
        <div class="empty-state">
            <p>No new projects available right now. Check back soon or
               <a href="/developer/browse-projects.php">browse all projects</a>.</p>
        </div>
    <?php else: ?>
        <div class="project-list">
            <?php foreach ($availableProjects as $project): ?>
            <div class="project-row">

                <div class="project-row__main">
                    <div class="project-row__title">
                        <?php echo htmlspecialchars($project['title']); ?>
                    </div>
                    <div class="project-row__meta">
                        <span class="meta-item">
                            &#128100;&nbsp;<?php echo htmlspecialchars($project['client_name']); ?>
                        </span>
                        <span class="meta-item">
                            &#128176;&nbsp;$<?php echo number_format((float)($project['budget'] ?? 0), 0); ?>
                        </span>
                        <?php if (!empty($project['deadline'])): ?>
                        <span class="meta-item">
                            &#128197;&nbsp;Due <?php echo htmlspecialchars(date('M j, Y', strtotime($project['deadline']))); ?>
                        </span>
                        <?php endif; ?>
                        <span class="meta-item">
                            &#128336;&nbsp;Posted <?php echo htmlspecialchars(date('M j, Y', strtotime($project['created_at']))); ?>
                        </span>
                    </div>
                </div>

                <div class="project-row__actions">
                    <form method="post" action="/developer/apply-project.php" style="margin: 0;">
                        <input type="hidden" name="project_id" value="<?php echo (int)$project['id']; ?>">
                        <button type="submit" class="btn btn-primary btn-sm">Apply</button>
                    </form>
                </div>

            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</section><!-- /available-projects -->


<!-- ═══════════════════════════════════════════════════
     ROW 4 — Two-column: Accepted Projects + Earnings
════════════════════════════════════════════════════ -->
<div class="dash-two-col">

<!-- SECTION 4 — Accepted Projects (left) -->
<section class="dash-section">

    <div class="dash-section-header">
        <h2 class="dash-section-title">Accepted Projects</h2>
    </div>

    <?php if (empty($acceptedProjects)): ?>
        <div class="empty-state">
            <p>No accepted projects yet. Once a client hires you, projects will appear here.</p>
        </div>
    <?php else: ?>
        <div class="project-list">
            <?php foreach ($acceptedProjects as $project):
                $rawStatus   = strtolower($project['project_status'] ?? '');
                $isCompleted = in_array($rawStatus, ['completed', 'done', 'closed'], true);
                $badgeClass  = $isCompleted ? 'badge badge--completed' : 'badge badge--inprogress';
                $badgeLabel  = $isCompleted ? 'Completed'              : 'In Progress';
            ?>
            <div class="project-row">

                <div class="project-row__main">
                    <div class="project-row__title">
                        <?php echo htmlspecialchars($project['title'] ?? 'Untitled Project'); ?>
                    </div>
                    <div class="project-row__meta">
                        <span class="meta-item">
                            &#128100;&nbsp;<?php echo htmlspecialchars($project['client_name'] ?? '—'); ?>
                        </span>
                        <span class="meta-item">
                            &#128176;&nbsp;$<?php echo number_format((float)($project['budget'] ?? 0), 0); ?>
                        </span>
                        <span class="meta-item">
                            &#128197;&nbsp;Accepted <?php echo htmlspecialchars(date('M j, Y', strtotime($project['created_at']))); ?>
                        </span>
                    </div>
                </div>

                <div class="project-row__actions">
                    <span class="<?php echo $badgeClass; ?>"><?php echo $badgeLabel; ?></span>
                    <a href="/developer/project-detail.php?id=<?php echo (int)($project['project_id'] ?? 0); ?>"
                       class="btn btn-outline btn-sm">View</a>
                </div>

            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</section><!-- /accepted-projects -->


<!-- SECTION 5 — Earnings Summary (right) -->
<section class="dash-section">

    <div class="dash-section-header">
        <h2 class="dash-section-title">Earnings Summary</h2>
    </div>

    <div class="earnings-summary-grid">

        <div class="earnings-card earnings-card--total">
            <div class="ec-label">Total Earnings</div>
            <div class="ec-value">$<?php echo number_format($totalEarnings, 2); ?></div>
            <div class="ec-note">All completed payments received to date</div>
        </div>

        <div class="earnings-card earnings-card--pending">
            <div class="ec-label">Pending Payments</div>
            <div class="ec-value"><?php echo $pendingPayments; ?></div>
            <div class="ec-note">Contracts awaiting client checkout</div>
        </div>

        <div class="earnings-card earnings-card--done">
            <div class="ec-label">Completed Payments</div>
            <div class="ec-value"><?php echo $completedPayments; ?></div>
            <div class="ec-note">Payments successfully processed</div>
        </div>

        <div class="earnings-card earnings-card--avg">
            <div class="ec-label">Avg. Per Project</div>
            <div class="ec-value">$<?php echo number_format($averageBudget, 2); ?></div>
            <div class="ec-note">Based on completed payment records</div>
        </div>

    </div>

</section><!-- /earnings-summary -->

</div><!-- /dash-two-col -->


<!-- ═══════════════════════════════════════════════════
     SECTION 6 — Payment Status Table
════════════════════════════════════════════════════ -->
<section class="dash-section">

    <div class="dash-section-header">
        <h2 class="dash-section-title">Payment Status</h2>
        <a href="/developer/contracts.php" class="btn btn-primary btn-sm">View Contracts</a>
    </div>

    <?php if (empty($paymentRecords)): ?>
        <div class="empty-state">
            <p>No payment records yet. Paid contracts will appear here once clients complete checkout.</p>
        </div>
    <?php else: ?>
        <div class="table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Project</th>
                        <th>Client</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($paymentRecords as $record):
                        $payStatus   = strtolower($record['payment_status'] ?? '');
                        $statusClass = match ($payStatus) {
                            'completed', 'paid' => 'badge badge--completed',
                            'pending'           => 'badge badge--pending',
                            'failed'            => 'badge badge--failed',
                            default             => 'badge badge--neutral',
                        };
                        $statusLabel = ucfirst(htmlspecialchars($record['payment_status'] ?? 'Unknown'));
                        $displayDate = !empty($record['paid_at'])
                                         ? date('M j, Y', strtotime($record['paid_at']))
                                         : date('M j, Y', strtotime($record['created_at']));
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($record['project_title']); ?></td>
                        <td><?php echo htmlspecialchars($record['client_name']); ?></td>
                        <td>$<?php echo number_format((float)($record['amount'] ?? 0), 2); ?></td>
                        <td><span class="<?php echo $statusClass; ?>"><?php echo $statusLabel; ?></span></td>
                        <td><?php echo $displayDate; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

</section><!-- /payment-status -->


<?php renderRolePageEnd(); ?>