<?php
function renderAppTopbar(
    string $title,
    string $subtitle = ''
): void {

    $userName = $_SESSION['name'] ?? 'User';
?>
<div class="topbar">
    <div>
        <h1 class="page-title"><?php echo htmlspecialchars($title); ?></h1>
        <?php if ($subtitle): ?>
            <p class="page-subtitle"><?php echo htmlspecialchars($subtitle); ?></p>
        <?php endif; ?>
    </div>

    <div class="topbar-actions">
        <span class="user-name"><?php echo htmlspecialchars($userName); ?></span>

        <a href="/logout.php" class="btn-logout">Logout</a>
    </div>
</div>
<?php
}
?>