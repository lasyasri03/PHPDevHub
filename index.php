<?php
include 'includes/auth.php';

$successMessage = $_GET['success'] ?? null;
$pageTitle = 'Home';
$usePageShell = false;
include 'includes/header.php';
?>

<main class="home-shell">
<div class="container">

<?php if (!empty($successMessage)): ?>
<div class="alert alert-success mb-4">
<?php echo htmlspecialchars($successMessage); ?>
</div>
<?php endif; ?>


<!-- HERO SECTION -->
<div class="hero-card mb-4">
<div class="row g-4 align-items-center">

<div class="col-lg-7">

<span class="eyebrow">PHPDevHub Platform</span>

<h1>Hire PHP developers and manage delivery from a dashboard built for speed.</h1>

<p class="mb-0">
PHPDevHub is a platform where clients can hire skilled PHP developers and manage projects efficiently.
Clients can post projects, developers apply for work, and after successful completion secure payment is released to the developer.
</p>

<div class="hero-actions">
<a href="<?php echo htmlspecialchars(appUrl('signup.php')); ?>" class="btn btn-primary btn-lg">Create Account</a>

<a href="<?php echo htmlspecialchars(appUrl('login.php')); ?>" class="btn btn-outline-primary btn-lg">Login</a>
</div>

</div>


<div class="col-lg-5">

<div class="stats-grid mb-0">

<div class="stat-card">
<span class="stat-label">Clients</span>
<div class="stat-value">01</div>

<div class="stat-note">
Clients create accounts, post projects, review developer applications and hire developers.
</div>
</div>


<div class="stat-card">
<span class="stat-label">Developers</span>
<div class="stat-value">02</div>

<div class="stat-note">
Developers browse projects, apply for work, communicate with clients and complete contracts.
</div>
</div>

</div>

</div>

</div>
</div>



<!-- HOW PHPDEVHUB WORKS -->

<div class="row g-4 mt-5">

<div class="col-12 text-center mb-3">
<h2>How PHPDevHub Works</h2>
<p class="text-muted">A simple workflow connecting clients and PHP developers.</p>
</div>

<div class="col-md-4">
<div class="mini-card">
<h3>1. Create Account</h3>
<p>Clients and developers sign up and access their dashboards.</p>
</div>
</div>

<div class="col-md-4">
<div class="mini-card">
<h3>2. Post a Project</h3>
<p>Clients submit project details including description, budget and deadline.</p>
</div>
</div>

<div class="col-md-4">
<div class="mini-card">
<h3>3. Developers Apply</h3>
<p>Developers browse available projects and submit proposals.</p>
</div>
</div>

<div class="col-md-4">
<div class="mini-card">
<h3>4. Hire a Developer</h3>
<p>Clients review applications and select the best developer.</p>
</div>
</div>

<div class="col-md-4">
<div class="mini-card">
<h3>5. Complete the Project</h3>
<p>The developer completes the assigned work.</p>
</div>
</div>

<div class="col-md-4">
<div class="mini-card">
<h3>6. Secure Payment</h3>
<p>After approval payment is released securely.</p>
</div>
</div>

</div>



<!-- PLATFORM FEATURES -->

<div class="row g-4 mt-5">

<div class="col-12 text-center mb-3">
<h2>Platform Features</h2>
<p class="text-muted">Tools designed to simplify hiring and collaboration.</p>
</div>


<div class="col-md-4">
<div class="mini-card text-center">

<i class="bi bi-kanban fs-1 text-primary"></i>

<h3 class="mt-3">Project Posting</h3>

<p>
Clients can post projects with description, budget and deadlines.
</p>

</div>
</div>


<div class="col-md-4">
<div class="mini-card text-center">

<i class="bi bi-person-check fs-1 text-primary"></i>

<h3 class="mt-3">Developer Applications</h3>

<p>
Developers browse projects and submit proposals.
</p>

</div>
</div>


<div class="col-md-4">
<div class="mini-card text-center">

<i class="bi bi-chat-dots fs-1 text-primary"></i>

<h3 class="mt-3">Secure Messaging</h3>

<p>
Clients and developers communicate directly through the platform.
</p>

</div>
</div>


<div class="col-md-4">
<div class="mini-card text-center">

<i class="bi bi-search fs-1 text-primary"></i>

<h3 class="mt-3">Find Developers</h3>

<p>
Clients can explore developer profiles and skills.
</p>

</div>
</div>


<div class="col-md-4">
<div class="mini-card text-center">

<i class="bi bi-person-plus fs-1 text-primary"></i>

<h3 class="mt-3">Direct Hiring</h3>

<p>
Clients can directly hire developers from the Find Developers page.
</p>

</div>
</div>


<div class="col-md-4">
<div class="mini-card text-center">

<i class="bi bi-shield-check fs-1 text-primary"></i>

<h3 class="mt-3">Secure Payments</h3>

<p>
Payments are released securely after project approval.
</p>

</div>
</div>

</div>



<!-- WHY CHOOSE PHPDEVHUB -->

<div class="row g-4 mt-5 mb-5">

<div class="col-12 text-center mb-3">

<h2>Why Choose PHPDevHub</h2>

<p class="text-muted">
A focused marketplace designed for PHP development projects.
</p>

</div>


<div class="col-md-4">
<div class="mini-card">
<h3>Focused on PHP Development</h3>
<p>The platform is dedicated to PHP developers and PHP-based projects.</p>
</div>
</div>


<div class="col-md-4">
<div class="mini-card">
<h3>Easy Hiring Process</h3>
<p>Clients can quickly post projects and review developer applications.</p>
</div>
</div>


<div class="col-md-4">
<div class="mini-card">
<h3>Secure Collaboration</h3>
<p>Built-in tools help clients and developers collaborate efficiently.</p>
</div>
</div>

</div>


</div>
</main>

<?php include 'includes/footer.php'; ?>