<?php

function renderAdminSidebar(string $activePage): void
{
    $navItems = [
        'main' => [
            ['key' => 'dashboard', 'label' => 'Dashboard', 'path' => 'admin/dashboard.php'],
        ],
        'marketplace' => [
            ['key' => 'developers', 'label' => 'Developers', 'path' => 'admin/developers.php'],
            ['key' => 'clients', 'label' => 'Clients', 'path' => 'admin/clients.php'],
            ['key' => 'projects', 'label' => 'Projects', 'path' => 'admin/projects.php'],
        ],
        'workflow' => [
            ['key' => 'applications', 'label' => 'Applications', 'path' => 'admin/admin_applications.php'],
            ['key' => 'contracts', 'label' => 'Contracts', 'path' => 'admin/admin_contracts.php'],
        ],
        'operations' => [
            ['key' => 'disputes', 'label' => 'Disputes', 'path' => 'admin/admin_disputes.php'],
        ],
        'finance' => [
            ['key' => 'payments', 'label' => 'Payments', 'path' => 'admin/payments.php'],
        ],
        'system' => [
            ['key' => 'announcements', 'label' => 'Announcements', 'path' => 'admin/admin_announcements.php'],
            ['key' => 'logs', 'label' => 'Activity Logs', 'path' => 'admin/admin_logs.php'],
        ],
    ];

    $sectionLabels = [
        'main' => 'MAIN',
        'marketplace' => 'MARKETPLACE',
        'workflow' => 'WORKFLOW',
        'operations' => 'OPERATIONS',
        'finance' => 'FINANCE',
        'system' => 'SYSTEM',
    ];
    ?>
    <aside class="sidebar">
        <div class="brand">Admin Panel</div>
        <div class="brand-subtitle">PHPDevHub control center</div>
        <?php foreach ($navItems as $section => $items): ?>
            <div class="sidebar-section">
                <div class="sidebar-heading"><?php echo htmlspecialchars($sectionLabels[$section]); ?></div>
                <?php foreach ($items as $item): ?>
                    <a class="nav-link <?php echo $activePage === $item['key'] ? 'active' : ''; ?>" href="<?php echo htmlspecialchars(appUrl($item['path'])); ?>">
                        <?php echo htmlspecialchars($item['label']); ?>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
        <div class="sidebar-section">
            <div class="sidebar-heading">ACCOUNT</div>
            <a class="nav-link" href="<?php echo htmlspecialchars(appUrl('admin/logout.php')); ?>">Logout</a>
        </div>
    </aside>
    <?php
}
?>
