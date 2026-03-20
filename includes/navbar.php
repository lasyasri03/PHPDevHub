<?php
$currentPath = basename(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '');
$role = getUserRole();

if ($role === 'client') {
    $dashboardUrl = appUrl('client/dashboard.php');
} elseif ($role === 'developer') {
    $dashboardUrl = appUrl('developer/dashboard.php');
} elseif ($role === 'admin') {
    $dashboardUrl = appUrl('admin/dashboard.php');
} else {
    $dashboardUrl = appUrl('login.php');
}
?>
<nav class="navbar navbar-expand-lg app-navbar sticky-top">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center gap-2" href="<?php echo htmlspecialchars(appUrl('index.php')); ?>">
            <span class="brand-mark">P</span>
            <span>PHPDevHub</span>
        </a>
        <button class="navbar-toggler border-0 shadow-none" type="button" data-bs-toggle="collapse" data-bs-target="#appNavbar" aria-controls="appNavbar" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="appNavbar">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link <?php echo in_array($currentPath, ['', 'index.php'], true) ? 'active' : ''; ?>" href="<?php echo htmlspecialchars(appUrl('index.php')); ?>">Home</a>
                </li>
            </ul>
            <div class="d-flex flex-column flex-lg-row align-items-lg-center gap-2 ms-lg-auto">
                <?php if (isLoggedIn()): ?>
                    <a class="btn btn-nav-outline" href="<?php echo htmlspecialchars($dashboardUrl); ?>">Dashboard</a>
                    <a class="btn btn-primary" href="<?php echo htmlspecialchars(appUrl('logout.php')); ?>">Logout</a>
                <?php else: ?>
                    <a class="btn btn-nav-outline" href="<?php echo htmlspecialchars(appUrl('login.php')); ?>">Login</a>
                    <a class="btn btn-primary" href="<?php echo htmlspecialchars(appUrl('signup.php')); ?>">Sign Up</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>
