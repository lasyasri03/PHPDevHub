<?php
include '../includes/db.php';
include '../includes/auth.php';
require_once '../includes/marketplace_helpers.php';
requireRole('client');

$developerId = isset($_GET['developer_id']) ? (int) $_GET['developer_id'] : 0;
$clientId = (int) ($_SESSION['user_id'] ?? 0);

if ($developerId <= 0) {
    header('Location: /developers/list.php');
    exit;
}

$developerStmt = $pdo->prepare("SELECT u.id, u.role FROM users u WHERE u.id = ? AND u.role = 'developer'");
$developerStmt->execute([$developerId]);
$developer = $developerStmt->fetch(PDO::FETCH_ASSOC);

if (!$developer) {
    header('Location: /developers/list.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /developers/profile.php?id=' . $developerId);
    exit;
}

$message = trim($_POST['message'] ?? '');
if ($message === '') {
    header('Location: /developers/profile.php?id=' . $developerId . '&error=' . urlencode('Please enter a hire request message.'));
    exit;
}

$existingStmt = $pdo->prepare(
    "SELECT id, status
     FROM hire_requests
     WHERE client_id = ? AND developer_id = ?
     ORDER BY created_at DESC, id DESC
     LIMIT 1"
);
$existingStmt->execute([$clientId, $developerId]);
$existingRequest = $existingStmt->fetch(PDO::FETCH_ASSOC);

if ($existingRequest) {
    if ($existingRequest['status'] === 'pending') {
        header('Location: /developers/profile.php?id=' . $developerId . '&error=' . urlencode('You have already sent a hire request to this developer.'));
        exit;
    }

    if ($existingRequest['status'] === 'accepted') {
        header('Location: /developers/profile.php?id=' . $developerId . '&error=' . urlencode('You have already hired this developer.'));
        exit;
    }
}

$insertStmt = $pdo->prepare(
    "INSERT INTO hire_requests (client_id, developer_id, message, status, created_at)
     VALUES (?, ?, ?, 'pending', NOW())"
);
$insertStmt->execute([$clientId, $developerId, $message]);
logUserActivity($pdo, $clientId, 'client', 'Sent direct hire request to developer #' . $developerId);

header('Location: /developers/profile.php?id=' . $developerId . '&success=' . urlencode('Hire request sent successfully.'));
exit;
