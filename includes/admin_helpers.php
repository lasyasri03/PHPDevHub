<?php

function adminLogAction(mysqli $conn, int $adminId, string $action, string $targetUser): void
{
    $stmt = $conn->prepare(
        "INSERT INTO admin_logs (admin_id, action, target_user, created_at)
         VALUES (?, ?, ?, NOW())"
    );
    $stmt->bind_param('iss', $adminId, $action, $targetUser);
    $stmt->execute();
}

function createAdminNotification(mysqli $conn, string $type, string $message): void
{
    $stmt = $conn->prepare(
        "INSERT INTO admin_notifications (type, message, is_read, created_at)
         VALUES (?, ?, 0, NOW())"
    );
    $stmt->bind_param('ss', $type, $message);
    $stmt->execute();
}
?>
