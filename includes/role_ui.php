<?php
require_once __DIR__ . '/sidebar.php';
require_once __DIR__ . '/topbar.php';

function renderRolePageStart(
    string $role,
    string $activePage,
    string $title,
    string $subtitle = ''
): void
{
    $pageTitle = $title;
    $usePageShell = false;
    include __DIR__ . '/header.php';
    ?>
    <main class="app-layout">
        <?php renderAppSidebar($role, $activePage); ?>
        <section class="content">
            <?php renderAppTopbar($title, $subtitle); ?>
    <?php
}

function renderRolePageEnd(): void
{
    ?>
        </section>
    </main>
    <?php include __DIR__ . '/footer.php'; ?>
    <?php
}