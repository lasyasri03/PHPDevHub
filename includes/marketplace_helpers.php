<?php

function logUserActivity(PDO $pdo, int $userId, string $role, string $action): void
{
    $stmt = $pdo->prepare(
        "INSERT INTO user_activity_logs (user_id, role, action, created_at)
         VALUES (?, ?, ?, NOW())"
    );
    $stmt->execute([$userId, $role, $action]);
}

function badgeClass(string $status): string
{
    return match ($status) {
        'accepted', 'approved', 'resolved', 'completed', 'active', 'success', 'paid' => 'success',
        'pending', 'under_review', 'in_progress', 'warning' => 'warning',
        'rejected', 'closed', 'danger', 'failed', 'cancelled' => 'danger',
        default => 'secondary',
    };
}

function formatStatusLabel(string $status): string
{
    return ucwords(str_replace('_', ' ', $status));
}

function getRoleNavigation(string $role): array
{
    if ($role === 'client') {
        return [
            ['key' => 'dashboard', 'label' => 'Dashboard', 'path' => 'client/dashboard.php'],
            ['key' => 'post-project', 'label' => 'Post Project', 'path' => 'client/post-project.php'],
            ['key' => 'find-developers', 'label' => 'Find Developers', 'path' => 'developers/list.php'],
            ['key' => 'projects', 'label' => 'My Projects', 'path' => 'client/my-projects.php'],
            ['key' => 'applications', 'label' => 'Applications', 'path' => 'client/applications.php'],
            ['key' => 'contracts', 'label' => 'Contracts', 'path' => 'client/contracts.php'],
            ['key' => 'disputes', 'label' => 'Disputes', 'path' => 'client/disputes.php'],
            ['key' => 'announcements', 'label' => 'Announcements', 'path' => 'client/announcements.php'],
        ];
    }

    return [
        ['key' => 'dashboard', 'label' => 'Dashboard', 'path' => 'developer/dashboard.php'],
        ['key' => 'available-projects', 'label' => 'Browse Projects', 'path' => 'developer/browse-projects.php'],
        ['key' => 'my-applications', 'label' => 'My Applications', 'path' => 'developer/my-applications.php'],
        ['key' => 'active-contracts', 'label' => 'Active Contracts', 'path' => 'developer/contracts.php'],
        ['key' => 'messages', 'label' => 'Messages', 'path' => 'developer/messages.php'],
        ['key' => 'profile', 'label' => 'Profile', 'path' => 'developer/edit-profile.php'],
    ];
}
