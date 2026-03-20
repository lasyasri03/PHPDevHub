<?php
include '../includes/db.php';
include '../includes/auth.php';
requireRole('developer');

$developerId = (int) ($_SESSION['user_id'] ?? 0);
$successMessage = null;
$errorMessage = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $taskId = (int) ($_POST['task_id'] ?? 0);
    $status = $_POST['status'] ?? '';
    $allowedStatuses = ['pending', 'in_progress', 'completed'];

    if ($taskId <= 0 || !in_array($status, $allowedStatuses, true)) {
        $errorMessage = 'Invalid task update.';
    } else {
        $taskStmt = $pdo->prepare("SELECT id, status FROM tasks WHERE id = ? AND developer_id = ?");
        $taskStmt->execute([$taskId, $developerId]);
        $task = $taskStmt->fetch(PDO::FETCH_ASSOC);

        if (!$task) {
            $errorMessage = 'Task not found.';
        } else {
            $currentStatus = $task['status'];
            $validTransitions = [
                'pending' => ['pending', 'in_progress'],
                'in_progress' => ['in_progress', 'completed'],
                'completed' => ['completed'],
            ];

            if (!in_array($status, $validTransitions[$currentStatus] ?? [], true)) {
                $errorMessage = 'Invalid task status transition.';
            } else {
                $updateStmt = $pdo->prepare("UPDATE tasks SET status = ? WHERE id = ? AND developer_id = ?");
                $updateStmt->execute([$status, $taskId, $developerId]);
                $successMessage = 'Task status updated successfully.';
            }
        }
    }
}

$stmt = $pdo->prepare(
    "SELECT t.*, p.title AS project_title
     FROM tasks t
     INNER JOIN projects p ON p.id = t.project_id
     WHERE t.developer_id = ?
     ORDER BY
         CASE t.status
             WHEN 'pending' THEN 0
             WHEN 'in_progress' THEN 1
             ELSE 2
         END,
         t.created_at DESC,
         t.id DESC"
);
$stmt->execute([$developerId]);
$tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<?php include '../includes/header.php'; ?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h2 class="mb-1">My Tasks</h2>
        <p class="text-muted mb-0">Track assigned work across your marketplace projects.</p>
    </div>
    <a href="<?php echo htmlspecialchars(appUrl('developer/dashboard.php')); ?>" class="btn btn-outline-secondary">Back to Dashboard</a>
</div>

<?php if ($successMessage): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($successMessage); ?></div>
<?php endif; ?>
<?php if ($errorMessage): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($errorMessage); ?></div>
<?php endif; ?>

<div class="card shadow-sm">
    <div class="card-body">
        <?php if ($tasks): ?>
            <div class="table-responsive">
                <table class="table table-striped align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Project</th>
                            <th>Task Title</th>
                            <th>Description</th>
                            <th>Status</th>
                            <th>Update Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tasks as $task): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($task['project_title']); ?></td>
                                <td><?php echo htmlspecialchars($task['title']); ?></td>
                                <td><?php echo htmlspecialchars($task['description']); ?></td>
                                <td><span class="badge bg-<?php echo $task['status'] === 'completed' ? 'success' : ($task['status'] === 'in_progress' ? 'warning text-dark' : 'secondary'); ?>"><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $task['status']))); ?></span></td>
                                <td>
                                    <form method="post" class="d-flex gap-2">
                                        <input type="hidden" name="task_id" value="<?php echo (int) $task['id']; ?>">
                                        <select name="status" class="form-select form-select-sm" style="max-width: 180px;">
                                            <?php if ($task['status'] === 'pending'): ?>
                                                <option value="pending" selected>Pending</option>
                                                <option value="in_progress">In Progress</option>
                                            <?php elseif ($task['status'] === 'in_progress'): ?>
                                                <option value="in_progress" selected>In Progress</option>
                                                <option value="completed">Completed</option>
                                            <?php else: ?>
                                                <option value="completed" selected>Completed</option>
                                            <?php endif; ?>
                                        </select>
                                        <button type="submit" class="btn btn-primary btn-sm">Save</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="text-muted mb-0">No tasks have been assigned to you yet.</p>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
