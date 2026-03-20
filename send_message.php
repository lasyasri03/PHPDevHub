<?php
include 'includes/db.php';
include 'includes/auth.php';
requireLogin();

$userId = (int) ($_SESSION['user_id'] ?? 0);
$requestId = isset($_POST['hire_request_id']) ? (int) $_POST['hire_request_id'] : 0;
$message = trim($_POST['message'] ?? '');
$dashboardUrl = getUserRole() === 'developer' ? '/developer/dashboard.php' : '/client/dashboard.php';

if ($requestId <= 0 || $message === '') {
    header('Location: /chat/project_chat.php?request_id=' . $requestId . '&error=' . urlencode('Message cannot be empty.'));
    exit;
}

$stmt = $pdo->prepare('SELECT client_id, developer_id, status FROM hire_requests WHERE id = ?');
$stmt->execute([$requestId]);
$hireRequest = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$hireRequest) {
    header('Location: ' . $dashboardUrl);
    exit;
}

$clientId = (int) $hireRequest['client_id'];
$developerId = (int) $hireRequest['developer_id'];

if ($userId !== $clientId && $userId !== $developerId) {
    header('Location: ' . $dashboardUrl);
    exit;
}

if ($hireRequest['status'] !== 'accepted') {
    header('Location: /chat/project_chat.php?request_id=' . $requestId . '&error=' . urlencode('Chat is available only for accepted hire requests.'));
    exit;
}

try {
    $insert = $pdo->prepare('INSERT INTO messages (hire_request_id, sender_id, message) VALUES (?, ?, ?)');
    $insert->execute([$requestId, $userId, $message]);
} catch (PDOException $e) {
    header('Location: /chat/project_chat.php?request_id=' . $requestId . '&error=' . urlencode('Unable to send message right now.'));
    exit;
}

header('Location: /chat/project_chat.php?request_id=' . $requestId);
exit;
