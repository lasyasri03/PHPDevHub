<?php
include '../includes/db.php';
include '../includes/auth.php';
require_once '../includes/marketplace_helpers.php';
requireRole('developer');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . appUrl('developer/browse-projects.php'));
    exit;
}

$projectId = (int) ($_POST['project_id'] ?? 0);
$developerId = (int) ($_SESSION['user_id'] ?? 0);

if ($projectId <= 0) {
    header('Location: ' . appUrl('developer/browse-projects.php') . '?error=' . urlencode('Invalid project selected.'));
    exit;
}

$projectStmt = $pdo->prepare("SELECT id, client_id, status FROM projects WHERE id = ?");
$projectStmt->execute([$projectId]);
$project = $projectStmt->fetch(PDO::FETCH_ASSOC);

if (!$project || !in_array($project['status'], ['approved', 'open'], true)) {
    header('Location: ' . appUrl('developer/browse-projects.php') . '?error=' . urlencode('This project is no longer open for applications.'));
    exit;
}

$existingStmt = $pdo->prepare(
    "SELECT id, status
     FROM hire_requests
     WHERE project_id = ? AND developer_id = ?
     ORDER BY created_at DESC, id DESC
     LIMIT 1"
);
$existingStmt->execute([$projectId, $developerId]);
$existingRequest = $existingStmt->fetch(PDO::FETCH_ASSOC);

if ($existingRequest) {
    header('Location: ' . appUrl('developer/browse-projects.php') . '?error=' . urlencode('You have already applied to this project.'));
    exit;
}

$insertStmt = $pdo->prepare(
    "INSERT INTO hire_requests (client_id, developer_id, project_id, status, created_at)
     VALUES (?, ?, ?, 'pending', NOW())"
);
$insertStmt->execute([(int) $project['client_id'], $developerId, $projectId]);

$notificationStmt = $pdo->prepare(
    "INSERT INTO admin_notifications (type, message, is_read, created_at)
     VALUES ('developer_applied', ?, 0, NOW())"
);
$notificationMessage = ($_SESSION['name'] ?? 'Developer') . ' applied to project #' . $projectId;
$notificationStmt->execute([$notificationMessage]);

logUserActivity($pdo, $developerId, 'developer', 'Applied to project #' . $projectId);

header('Location: ' . appUrl('developer/browse-projects.php') . '?success=' . urlencode('Application submitted. Waiting for client approval.'));
exit;
