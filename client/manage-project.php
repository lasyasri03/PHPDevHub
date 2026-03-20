<?php
include '../includes/db.php';
include '../includes/auth.php';
requireRole('client');

$clientId = (int) ($_SESSION['user_id'] ?? 0);
$selectedProjectId = isset($_GET['project_id']) ? (int) $_GET['project_id'] : (int) ($_POST['project_id'] ?? 0);
$selectedDeveloperId = (int) ($_POST['developer_id'] ?? 0);
$title = trim($_POST['title'] ?? '');
$description = trim($_POST['description'] ?? '');
$successMessage = null;
$errorMessage = null;

$projectStmt = $pdo->prepare(
    "SELECT id, title, status
     FROM projects
     WHERE client_id = ?
     ORDER BY created_at DESC, id DESC"
);
$projectStmt->execute([$clientId]);
$projects = $projectStmt->fetchAll(PDO::FETCH_ASSOC);

$hiredDevelopers = [];
if ($selectedProjectId > 0) {
    $developerStmt = $pdo->prepare(
        "SELECT DISTINCT u.id, u.name
         FROM hire_requests hr
         INNER JOIN users u ON u.id = hr.developer_id
         INNER JOIN projects p ON p.id = hr.project_id
         WHERE hr.project_id = ? AND hr.status = 'accepted' AND p.client_id = ?
         ORDER BY u.name ASC"
    );
    $developerStmt->execute([$selectedProjectId, $clientId]);
    $hiredDevelopers = $developerStmt->fetchAll(PDO::FETCH_ASSOC);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($selectedProjectId <= 0) {
        $errorMessage = 'Please select a project.';
    } elseif ($selectedDeveloperId <= 0) {
        $errorMessage = 'Please select a hired developer.';
    } elseif ($title === '') {
        $errorMessage = 'Task title is required.';
    } elseif ($description === '') {
        $errorMessage = 'Task description is required.';
    } else {
        $projectCheckStmt = $pdo->prepare("SELECT id FROM projects WHERE id = ? AND client_id = ?");
        $projectCheckStmt->execute([$selectedProjectId, $clientId]);
        $projectExists = $projectCheckStmt->fetchColumn();

        $developerCheckStmt = $pdo->prepare(
            "SELECT COUNT(*)
             FROM hire_requests hr
             INNER JOIN projects p ON p.id = hr.project_id
             WHERE hr.project_id = ? AND hr.developer_id = ? AND hr.status = 'accepted' AND p.client_id = ?"
        );
        $developerCheckStmt->execute([$selectedProjectId, $selectedDeveloperId, $clientId]);
        $developerIsHired = (int) $developerCheckStmt->fetchColumn() > 0;

        if (!$projectExists) {
            $errorMessage = 'Selected project was not found.';
        } elseif (!$developerIsHired) {
            $errorMessage = 'You can only assign tasks to hired developers.';
        } else {
            try {
                $insertStmt = $pdo->prepare(
                    "INSERT INTO tasks (project_id, developer_id, title, description, status, created_at)
                     VALUES (?, ?, ?, ?, 'pending', NOW())"
                );
                $insertStmt->execute([$selectedProjectId, $selectedDeveloperId, $title, $description]);

                $successMessage = 'Task assigned successfully.';
                $title = '';
                $description = '';
            } catch (PDOException $e) {
                $errorMessage = 'Unable to assign task right now.';
            }
        }
    }
}

$taskStmt = null;
$assignedTasks = [];
if ($selectedProjectId > 0) {
    $taskStmt = $pdo->prepare(
        "SELECT t.*, u.name AS developer_name
         FROM tasks t
         INNER JOIN projects p ON p.id = t.project_id
         INNER JOIN users u ON u.id = t.developer_id
         WHERE t.project_id = ? AND p.client_id = ?
         ORDER BY t.created_at DESC, t.id DESC"
    );
    $taskStmt->execute([$selectedProjectId, $clientId]);
    $assignedTasks = $taskStmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<?php include '../includes/header.php'; ?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h2 class="mb-1">Manage Project Tasks</h2>
        <p class="text-muted mb-0">Assign tasks to developers you have already hired for a project.</p>
    </div>
    <a href="<?php echo htmlspecialchars(appUrl('client/my-projects.php')); ?>" class="btn btn-outline-secondary">Back to My Projects</a>
</div>

<?php if ($successMessage): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($successMessage); ?></div>
<?php endif; ?>
<?php if ($errorMessage): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($errorMessage); ?></div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-lg-5">
        <div class="card shadow-sm">
            <div class="card-body">
                <h4 class="mb-3">Assign New Task</h4>
                <form method="post">
                    <div class="mb-3">
                        <label for="project_id" class="form-label">Select Project</label>
                        <select name="project_id" id="project_id" class="form-select" onchange="this.form.submit()" required>
                            <option value="">Choose project</option>
                            <?php foreach ($projects as $project): ?>
                                <option value="<?php echo (int) $project['id']; ?>" <?php echo $selectedProjectId === (int) $project['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($project['title']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="developer_id" class="form-label">Select Hired Developer</label>
                        <select name="developer_id" id="developer_id" class="form-select" required>
                            <option value="">Choose developer</option>
                            <?php foreach ($hiredDevelopers as $developer): ?>
                                <option value="<?php echo (int) $developer['id']; ?>" <?php echo $selectedDeveloperId === (int) $developer['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($developer['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="title" class="form-label">Task Title</label>
                        <input type="text" class="form-control" id="title" name="title" value="<?php echo htmlspecialchars($title); ?>" required>
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label">Task Description</label>
                        <textarea class="form-control" id="description" name="description" rows="5" required><?php echo htmlspecialchars($description); ?></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary">Assign Task</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card shadow-sm">
            <div class="card-body">
                <h4 class="mb-3">Assigned Tasks</h4>
                <?php if ($selectedProjectId <= 0): ?>
                    <p class="text-muted mb-0">Select a project to view and assign tasks.</p>
                <?php elseif ($assignedTasks): ?>
                    <div class="table-responsive">
                        <table class="table table-striped align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Developer</th>
                                    <th>Task</th>
                                    <th>Status</th>
                                    <th>Created</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($assignedTasks as $task): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($task['developer_name']); ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($task['title']); ?></strong>
                                            <div class="text-muted small"><?php echo htmlspecialchars($task['description']); ?></div>
                                        </td>
                                        <td><span class="badge bg-<?php echo $task['status'] === 'completed' ? 'success' : ($task['status'] === 'in_progress' ? 'warning text-dark' : 'secondary'); ?>"><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $task['status']))); ?></span></td>
                                        <td><?php echo htmlspecialchars(date('M j, Y', strtotime($task['created_at']))); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted mb-0">No tasks assigned for this project yet.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
