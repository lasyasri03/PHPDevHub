<?php
require_once __DIR__ . '/marketplace_helpers.php';

function renderAppSidebar(string $role, string $activePage): void
{
    $navItems = getRoleNavigation($role);
    $heading = $role === 'client' ? 'Client Panel' : 'Developer Panel';
    ?>
    <aside class="sidebar">
        <div class="brand"><?php echo htmlspecialchars($heading); ?></div>
        <div class="brand-subtitle">PHPDevHub workspace</div>
        <div class="sidebar-section">
            <div class="sidebar-heading">MAIN</div>
            <?php foreach ($navItems as $item): ?>
                <a class="nav-link <?php echo $activePage === $item['key'] ? 'active' : ''; ?>" href="<?php echo htmlspecialchars(appUrl($item['path'])); ?>">
                    <?php echo htmlspecialchars($item['label']); ?>
                </a>
            <?php endforeach; ?>
        </div>
        <div class="sidebar-section">
            <div class="sidebar-heading">ACCOUNT</div>
            <a class="nav-link" href="<?php echo htmlspecialchars(appUrl('logout.php')); ?>">Logout</a>
        </div>
    </aside>
    <?php
}
