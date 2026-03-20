<?php
include '../includes/db.php';
include '../includes/auth.php';
requireRole('client');

$clientId = (int) ($_SESSION['user_id'] ?? 0);
$developerId = (int) ($_GET['developer_id'] ?? 0);

if ($developerId <= 0) {
    header('Location: ' . appUrl('developers/list.php') . '?error=' . urlencode('Invalid developer selected.'));
    exit;
}

$requestStmt = $pdo->prepare(
    "SELECT id
     FROM hire_requests
     WHERE client_id = ? AND developer_id = ? AND status = 'accepted'
     ORDER BY created_at DESC, id DESC
     LIMIT 1"
);
$requestStmt->execute([$clientId, $developerId]);
$requestId = (int) $requestStmt->fetchColumn();

if ($requestId <= 0) {
    header('Location: ' . appUrl('developers/profile.php') . '?id=' . $developerId . '&error=' . urlencode('Chat becomes available after the hire request is accepted.'));
    exit;
}

header('Location: ' . appUrl('chat/project_chat.php') . '?request_id=' . $requestId);
exit;
