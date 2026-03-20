<?php
session_start();
include 'includes/db.php';
include 'includes/auth.php';
require_once 'includes/marketplace_helpers.php';

if (!isLoggedIn()) {
    header('Location: /login.php');
    exit;
}

if (getUserRole() !== 'developer') {
    header('Location: /index.php');
    exit;
}

$requestId = isset($_POST['request_id']) ? (int) $_POST['request_id'] : 0;
$status = $_POST['status'] ?? '';

$allowedStatuses = ['accepted', 'rejected'];
if ($requestId <= 0 || !in_array($status, $allowedStatuses, true)) {
    header('Location: /developer/dashboard.php?error=' . urlencode('Invalid request status.'));
    exit;
}

try {
    $stmt = $pdo->prepare('UPDATE hire_requests SET status = ? WHERE id = ? AND developer_id = ?');
    $stmt->execute([$status, $requestId, $_SESSION['user_id']]);

    if ($stmt->rowCount() === 0) {
        header('Location: /developer/dashboard.php?error=' . urlencode('Request not found or permission denied.'));
        exit;
    }

    if ($status === 'accepted') {
        logUserActivity($pdo, (int) $_SESSION['user_id'], 'developer', 'Accepted a direct hire request');
    }

    header('Location: /developer/dashboard.php?success=' . urlencode('Request status updated.'));
    exit;
} catch (PDOException $e) {
    header('Location: /developer/dashboard.php?error=' . urlencode('Database error: ' . $e->getMessage()));
    exit;
}
