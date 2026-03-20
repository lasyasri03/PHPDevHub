<?php
include 'includes/db.php';
include 'includes/auth.php';

if (isLoggedIn()) {
    header('Location: ' . appUrl('index.php'));
    exit;
}

$successMessage = $_GET['success'] ?? null;
$errors = [];
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $errors[] = 'Please enter both email and password.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            loginUser($user);
            if ($user['role'] === 'developer') {
                header('Location: ' . appUrl('developer/dashboard.php') . '?success=' . urlencode('Login successful.'));
            } elseif ($user['role'] === 'client') {
                header('Location: ' . appUrl('client/dashboard.php') . '?success=' . urlencode('Login successful.'));
            } elseif ($user['role'] === 'admin') {
                header('Location: ' . appUrl('admin/dashboard.php') . '?success=' . urlencode('Login successful.'));
            } else {
                header('Location: ' . appUrl('index.php') . '?success=' . urlencode('Login successful.'));
            }
            exit;
        }

        $errors[] = 'Invalid email or password.';
    }
}

$pageTitle = 'Login';
$usePageShell = false;
include 'includes/header.php';
?>

<main class="auth-shell">
    <div class="container">
        <div class="auth-card">
            <h1 class="auth-title">Welcome back</h1>
            <p class="auth-subtitle">Sign in to continue into your PHPDevHub dashboard.</p>

            <?php if (!empty($successMessage)): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($successMessage); ?></div>
            <?php endif; ?>
            <?php if ($errors): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($errors[0]); ?></div>
            <?php endif; ?>

            <form method="post" class="row g-3">
                <div class="col-12">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" placeholder="you@example.com" required>
                </div>
                <div class="col-12">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" class="form-control" id="password" name="password" placeholder="Enter your password" required>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary w-100">Login</button>
                </div>
            </form>

            <p class="text-center mt-4 mb-0">Don't have an account? <a href="<?php echo htmlspecialchars(appUrl('signup.php')); ?>">Sign Up</a></p>
        </div>
    </div>
</main>

<?php include 'includes/footer.php'; ?>
