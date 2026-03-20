<?php
include '../includes/db.php';
include '../includes/auth.php';
require_once '../includes/marketplace_helpers.php';
require_once '../includes/role_ui.php';
requireRole('client');

$title = '';
$description = '';
$budget = '';
$deadline = '';
$developersNeeded = '1';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $budget = trim($_POST['budget'] ?? '');
    $deadline = trim($_POST['deadline'] ?? '');
    $developersNeeded = trim($_POST['developers_needed'] ?? '1');
    $clientId = (int) ($_SESSION['user_id'] ?? 0);

    if ($title === '') {
        $errors[] = 'Project title is required.';
    }
    if ($description === '') {
        $errors[] = 'Project description is required.';
    }
    if ($budget === '' || !is_numeric($budget) || (float) $budget < 0) {
        $errors[] = 'Please enter a valid budget.';
    }
    if ($deadline === '') {
        $errors[] = 'Project deadline is required.';
    } elseif (!DateTime::createFromFormat('Y-m-d', $deadline)) {
        $errors[] = 'Please enter a valid deadline.';
    }
    if ($developersNeeded === '' || filter_var($developersNeeded, FILTER_VALIDATE_INT) === false || (int) $developersNeeded < 1) {
        $errors[] = 'Please enter a valid number of developers needed.';
    }

    if (!$errors) {
        try {
            $stmt = $pdo->prepare(
                "INSERT INTO projects (client_id, title, description, budget, deadline, developers_needed, status, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, 'open', NOW())"
            );
            $stmt->execute([$clientId, $title, $description, (float) $budget, $deadline, (int) $developersNeeded]);

            $notificationStmt = $pdo->prepare(
                "INSERT INTO admin_notifications (type, message, is_read, created_at)
                 VALUES ('project_posted', ?, 0, NOW())"
            );
            $notificationMessage = 'New project posted by ' . ($_SESSION['name'] ?? 'client') . ': ' . $title;
            $notificationStmt->execute([$notificationMessage]);

            logUserActivity($pdo, $clientId, 'client', 'Posted project: ' . $title);
            header('Location: ' . appUrl('client/dashboard.php') . '?success=' . urlencode('Project posted successfully and is awaiting admin approval.'));
            exit;
        } catch (PDOException $e) {
            $errors[] = 'Unable to post project right now.';
        }
    }
}

renderRolePageStart('client', 'post-project', 'Post Project', 'Create a new project brief and publish it into the marketplace workflow.');
?>
<?php if ($errors): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($errors[0]); ?></div>
<?php endif; ?>
<section class="panel-card">
    <form method="post" class="row g-3">
        <div class="col-12">
            <label for="title" class="form-label">Project Title</label>
            <input type="text" class="form-control" id="title" name="title" value="<?php echo htmlspecialchars($title); ?>" required>
        </div>
        <div class="col-12">
            <label for="description" class="form-label">Project Description</label>
            <textarea class="form-control" id="description" name="description" rows="6" required><?php echo htmlspecialchars($description); ?></textarea>
        </div>
        <div class="col-md-4">
            <label for="budget" class="form-label">Budget</label>
            <input type="number" class="form-control" id="budget" name="budget" min="0" step="0.01" value="<?php echo htmlspecialchars($budget); ?>" required>
        </div>
        <div class="col-md-4">
            <label for="deadline" class="form-label">Deadline</label>
            <input type="date" class="form-control" id="deadline" name="deadline" value="<?php echo htmlspecialchars($deadline); ?>" required>
        </div>
        <div class="col-md-4">
            <label for="developers_needed" class="form-label">Developers Needed</label>
            <input type="number" class="form-control" id="developers_needed" name="developers_needed" min="1" value="<?php echo htmlspecialchars($developersNeeded); ?>" required>
        </div>
        <div class="col-12 d-flex gap-2">
            <button type="submit" class="btn btn-primary">Post Project</button>
            <a href="<?php echo htmlspecialchars(appUrl('client/dashboard.php')); ?>" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>
</section>
<?php renderRolePageEnd(); ?>
