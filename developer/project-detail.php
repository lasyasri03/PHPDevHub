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
   Input Validation
========================= */

$projectId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($projectId <= 0) {
    header('Location: /developer/dashboard.php?error=invalid_project');
    exit;
}

$userId = (int) $_SESSION['user_id'];


/* =========================
   Fetch Project
   — must exist AND the logged-in
     developer must have an accepted
     hire_request for it, so a
     developer cannot view someone
     else's project detail by
     guessing an id.
========================= */

$projectStmt = $pdo->prepare(
    "SELECT
        p.id,
        p.title,
        p.description,
        p.budget,
        p.deadline,
        p.status        AS project_status,
        p.created_at    AS project_created_at,
        u.name          AS client_name,
        u.email         AS client_email,
        hr.status       AS hire_status,
        hr.created_at   AS hired_at
     FROM projects p
     INNER JOIN users u ON u.id = p.client_id
     INNER JOIN hire_requests hr
            ON  hr.project_id   = p.id
            AND hr.developer_id = ?
            AND hr.status       = 'accepted'
     WHERE p.id = ?
     LIMIT 1"
);

$projectStmt->execute([$userId, $projectId]);
$project = $projectStmt->fetch(PDO::FETCH_ASSOC);


/* =========================
   404 Guard
   If no row returned the project
   either doesn't exist or this
   developer was not hired for it.
========================= */

if (!$project) {
    header('Location: /developer/dashboard.php?error=project_not_found');
    exit;
}


/* =========================
   Derived Display Values
========================= */

$rawStatus    = strtolower($project['project_status'] ?? '');
$isCompleted  = in_array($rawStatus, ['completed', 'done', 'closed'], true);
$statusBadge  = match (true) {
    $isCompleted                          => ['label' => 'Completed',   'class' => 'badge badge--completed'],
    in_array($rawStatus, ['active'])      => ['label' => 'Active',      'class' => 'badge badge--inprogress'],
    in_array($rawStatus, ['open',
             'approved', 'pending'])      => ['label' => ucfirst($rawStatus), 'class' => 'badge badge--pending'],
    default                               => ['label' => ucfirst($project['project_status'] ?? 'Unknown'),
                                              'class' => 'badge badge--neutral'],
};

$deadlineDisplay = !empty($project['deadline'])
    ? date('F j, Y', strtotime($project['deadline']))
    : 'No deadline set';

$hiredDisplay = !empty($project['hired_at'])
    ? date('F j, Y', strtotime($project['hired_at']))
    : '—';

$postedDisplay = !empty($project['project_created_at'])
    ? date('F j, Y', strtotime($project['project_created_at']))
    : '—';


/* =========================
   Page Shell Start
========================= */

renderRolePageStart(
    'developer',
    'dashboard',                          /* keep Dashboard active in sidebar */
    htmlspecialchars($project['title']),
    'Project details and contract information.'
);

?>


<!-- ═══════════════════════════════════════════════════
     Breadcrumb
════════════════════════════════════════════════════ -->
<nav class="pd-breadcrumb">
    <a href="/developer/dashboard.php">Dashboard</a>
    <span class="pd-breadcrumb__sep">&#8250;</span>
    <a href="/developer/dashboard.php#accepted-projects">Accepted Projects</a>
    <span class="pd-breadcrumb__sep">&#8250;</span>
    <span class="pd-breadcrumb__current">
        <?php echo htmlspecialchars($project['title']); ?>
    </span>
</nav>


<!-- ═══════════════════════════════════════════════════
     Project Header Card
════════════════════════════════════════════════════ -->
<div class="pd-header-card">

    <div class="pd-header-card__left">
        <h1 class="pd-project-title">
            <?php echo htmlspecialchars($project['title']); ?>
        </h1>
        <div class="pd-header-meta">
            <span class="meta-item">
                &#128100;&nbsp;<?php echo htmlspecialchars($project['client_name']); ?>
            </span>
            <span class="meta-item">
                &#128336;&nbsp;Posted <?php echo $postedDisplay; ?>
            </span>
            <?php if (!empty($project['client_email'])): ?>
            <span class="meta-item">
                &#9993;&nbsp;<?php echo htmlspecialchars($project['client_email']); ?>
            </span>
            <?php endif; ?>
        </div>
    </div>

    <div class="pd-header-card__right">
        <span class="<?php echo $statusBadge['class']; ?> badge--lg">
            <?php echo htmlspecialchars($statusBadge['label']); ?>
        </span>
    </div>

</div><!-- /pd-header-card -->


<!-- ═══════════════════════════════════════════════════
     Detail Grid (4 key facts)
════════════════════════════════════════════════════ -->
<div class="pd-detail-grid">

    <div class="pd-detail-card">
        <div class="pd-detail-card__icon pd-icon--blue">&#128176;</div>
        <div class="pd-detail-card__body">
            <div class="pd-detail-card__label">Project Budget</div>
            <div class="pd-detail-card__value">
                $<?php echo number_format((float)($project['budget'] ?? 0), 2); ?>
            </div>
        </div>
    </div>

    <div class="pd-detail-card">
        <div class="pd-detail-card__icon pd-icon--green">&#128197;</div>
        <div class="pd-detail-card__body">
            <div class="pd-detail-card__label">Deadline</div>
            <div class="pd-detail-card__value"><?php echo $deadlineDisplay; ?></div>
        </div>
    </div>

    <div class="pd-detail-card">
        <div class="pd-detail-card__icon pd-icon--amber">&#9989;</div>
        <div class="pd-detail-card__body">
            <div class="pd-detail-card__label">Project Status</div>
            <div class="pd-detail-card__value">
                <?php echo htmlspecialchars(ucfirst($project['project_status'] ?? 'Unknown')); ?>
            </div>
        </div>
    </div>

    <div class="pd-detail-card">
        <div class="pd-detail-card__icon pd-icon--purple">&#128203;</div>
        <div class="pd-detail-card__body">
            <div class="pd-detail-card__label">Hired On</div>
            <div class="pd-detail-card__value"><?php echo $hiredDisplay; ?></div>
        </div>
    </div>

</div><!-- /pd-detail-grid -->


<!-- ═══════════════════════════════════════════════════
     Description (if present)
════════════════════════════════════════════════════ -->
<?php if (!empty($project['description'])): ?>
<div class="dash-section" style="margin-bottom: 22px;">
    <div class="dash-section-header">
        <h2 class="dash-section-title">Project Description</h2>
    </div>
    <div class="pd-description">
        <?php echo nl2br(htmlspecialchars($project['description'])); ?>
    </div>
</div>
<?php endif; ?>


<!-- ═══════════════════════════════════════════════════
     Action Buttons
════════════════════════════════════════════════════ -->
<div class="pd-actions">
    <a href="/developer/dashboard.php" class="btn btn-outline btn-sm">
        &#8592;&nbsp;Back to Dashboard
    </a>
    <a href="/developer/contracts.php" class="btn btn-primary btn-sm">
        My Work
    </a>
</div>


<!-- ═══════════════════════════════════════════════════
     project-detail.css  (scoped, inline)
     All classes are prefixed pd- so they cannot
     conflict with style.css or dashboard layout.
════════════════════════════════════════════════════ -->
<style>

/* ── Breadcrumb ─────────────────────────────────── */
.pd-breadcrumb {
    font-size   : 13px;
    color       : #6b7280;
    margin-bottom: 20px;
    display     : flex;
    align-items : center;
    gap         : 6px;
    flex-wrap   : wrap;
}
.pd-breadcrumb a {
    color           : #2563eb;
    text-decoration : none;
}
.pd-breadcrumb a:hover { text-decoration: underline; }
.pd-breadcrumb__sep     { color: #9ca3af; font-size: 16px; }
.pd-breadcrumb__current { color: #374151; font-weight: 600; }

/* ── Header Card ────────────────────────────────── */
.pd-header-card {
    background    : #ffffff;
    border        : 1px solid #dbe3ef;
    border-radius : 16px;
    box-shadow    : 0 12px 30px rgba(15,23,42,.08);
    padding       : 24px 26px;
    display       : flex;
    align-items   : flex-start;
    justify-content: space-between;
    gap           : 20px;
    margin-bottom : 20px;
    flex-wrap     : wrap;
}
.pd-project-title {
    margin        : 0 0 10px;
    font-size     : 22px;
    font-weight   : 700;
    color         : #111827;
    line-height   : 1.25;
}
.pd-header-meta {
    display     : flex;
    flex-wrap   : wrap;
    gap         : 14px;
    align-items : center;
}
.pd-header-card__right {
    flex-shrink : 0;
    padding-top : 4px;
}

/* ── Badge size variant ─────────────────────────── */
.badge--lg {
    font-size : 13px;
    padding   : 6px 14px;
}

/* ── Detail Grid ────────────────────────────────── */
.pd-detail-grid {
    display               : grid;
    grid-template-columns : repeat(4, 1fr);
    gap                   : 16px;
    margin-bottom         : 22px;
}
.pd-detail-card {
    background    : #ffffff;
    border        : 1px solid #dbe3ef;
    border-radius : 14px;
    box-shadow    : 0 12px 30px rgba(15,23,42,.06);
    padding       : 18px 20px;
    display       : flex;
    align-items   : flex-start;
    gap           : 14px;
}
.pd-detail-card__icon {
    width         : 40px;
    height        : 40px;
    border-radius : 10px;
    display       : grid;
    place-items   : center;
    font-size     : 18px;
    flex-shrink   : 0;
}
.pd-icon--blue   { background: #dbeafe; }
.pd-icon--green  { background: #dcfce7; }
.pd-icon--amber  { background: #fef3c7; }
.pd-icon--purple { background: #ede9fe; }

.pd-detail-card__label {
    font-size      : 11.5px;
    font-weight    : 700;
    text-transform : uppercase;
    letter-spacing : .6px;
    color          : #6b7280;
    margin-bottom  : 5px;
}
.pd-detail-card__value {
    font-size   : 16px;
    font-weight : 700;
    color       : #111827;
    line-height : 1.2;
}

/* ── Description ────────────────────────────────── */
.pd-description {
    padding     : 18px 20px;
    font-size   : 14.5px;
    color       : #374151;
    line-height : 1.75;
}

/* ── Action Buttons Row ─────────────────────────── */
.pd-actions {
    display     : flex;
    gap         : 12px;
    flex-wrap   : wrap;
    margin-top  : 4px;
}

/* ── Responsive ─────────────────────────────────── */
@media (max-width: 960px) {
    .pd-detail-grid { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 560px) {
    .pd-detail-grid { grid-template-columns: 1fr; }
    .pd-header-card { flex-direction: column; }
    .pd-actions .btn-sm { width: 100%; text-align: center; }
}

</style>


<?php renderRolePageEnd(); ?>