<?php

include 'includes/db.php';
include 'includes/auth.php';
require_once 'includes/mail-helper.php'; // NEW: email helper

if (isLoggedIn()) {
    header('Location: ' . appUrl('index.php'));
    exit;
}

$allowedLevels = ['Beginner', 'Intermediate', 'Advanced', 'Expert'];
$allowedAvailabilityStatuses = ['Available for Hire', 'Busy', 'Not Available'];
$allowedResumeExtensions = ['pdf', 'doc', 'docx'];
$maxResumeSize = 5 * 1024 * 1024;

$name = '';
$email = '';
$role = 'client';
$phpProficiency = '';
$errors = [];
$successMessage = $_GET['success'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'client';
    $phpProficiency = $role === 'developer' ? trim($_POST['php_proficiency'] ?? '') : '';
    $resumePath = null;

    if (empty($name) || empty($email) || empty($password) || empty($role)) {
        $errors[] = 'Please fill in all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email format.';
    } elseif (strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters.';
    } elseif ($role === 'developer' && !in_array($phpProficiency, $allowedLevels, true)) {
        $errors[] = 'Please select a valid PHP proficiency level.';
    }

    if ($role === 'developer') {
        if (!isset($_FILES['resume']) || $_FILES['resume']['error'] === UPLOAD_ERR_NO_FILE) {
            $errors[] = 'Please fill in all required fields.';
        } elseif ($_FILES['resume']['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'Resume upload failed.';
        } elseif ($_FILES['resume']['size'] > $maxResumeSize) {
            $errors[] = 'Resume must be 5MB or smaller.';
        } else {
            $resumeExtension = strtolower(pathinfo($_FILES['resume']['name'], PATHINFO_EXTENSION));
            if (!in_array($resumeExtension, $allowedResumeExtensions, true)) {
                $errors[] = 'Resume must be a PDF, DOC, or DOCX file.';
            }
        }
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);

        if ($stmt->fetch()) {
            $errors[] = 'Email already exists.';
        } else {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $uploadDir = __DIR__ . '/uploads/resumes/';

            if ($role === 'developer') {
                if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
                    $errors[] = 'Unable to prepare the resume upload directory.';
                } else {
                    $resumeExtension = strtolower(pathinfo($_FILES['resume']['name'], PATHINFO_EXTENSION));
                    $resumeFileName = uniqid('resume_', true) . '.' . $resumeExtension;
                    $resumeTarget = $uploadDir . $resumeFileName;

                    if (!move_uploaded_file($_FILES['resume']['tmp_name'], $resumeTarget)) {
                        $errors[] = 'Unable to save the uploaded resume.';
                    } else {
                        $resumePath = '/uploads/resumes/' . $resumeFileName;
                    }
                }
            }

            if (empty($errors)) {
                try {
                    $pdo->beginTransaction();

                    $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$name, $email, $hashedPassword, $role]);

                    $userId = $pdo->lastInsertId();

                    if ($role === 'developer') {
                        $developerStmt = $pdo->prepare(
                            "INSERT INTO developers (user_id, resume, php_proficiency, availability) VALUES (?, ?, ?, ?)"
                        );
                        $developerStmt->execute([$userId, $resumePath, $phpProficiency, $allowedAvailabilityStatuses[0]]);
                    }

                    $pdo->commit();

                    /* ===============================
                       NEW EMAIL FEATURE START
                    =============================== */

                    // FIX: sendEmail() MUST be called before header() + exit.
                    // Previously the redirect ran first, killing the script
                    // before the email code was ever reached.

                    $subject = "Welcome to PHPDevHub 🎉";

                    $message = "
                    <h2>Welcome to PHPDevHub</h2>

                    <p>Hello $name,</p>

                    <p>Welcome to PHPDevHub! We're excited to have you on board. Please log in to your account from the platform to get started.</p>

                    <p>Regards,<br>PHPDevHub Team</p>
                    ";

                    sendEmail($email, $subject, $message);

header('Location: ' . appUrl('login.php') . '?success=' . urlencode('Signup successful. You can now login.'));
exit; // ← runs BEFORE redirect

                    /* ===============================
                       NEW EMAIL FEATURE END
                    =============================== */

                    header('Location: ' . appUrl('login.php') . '?success=' . urlencode('Signup successful. You can now login.'));
                    exit;

                } catch (PDOException $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    if (!empty($resumePath)) {
                        $resumeFileOnDisk = __DIR__ . $resumePath;
                        if (is_file($resumeFileOnDisk)) {
                            unlink($resumeFileOnDisk);
                        }
                    }
                    $errors[] = 'Registration failed.';
                }
            }
        }
    }
}

$pageTitle = 'Sign Up';
$usePageShell = false;
include 'includes/header.php';
?>

<main class="auth-section">
    <div class="container">
        <div class="auth-card">
            <h1 class="auth-title">Create your account</h1>
            <p class="auth-subtitle">Join PHPDevHub as a client or a PHP developer.</p>

            <?php if (!empty($successMessage)): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($successMessage); ?></div>
            <?php endif; ?>
            <?php if ($errors): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($errors[0]); ?></div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data" class="row g-3">
                <div class="col-12">
                    <label class="form-label" for="name">Full Name</label>
                    <input type="text" id="name" name="name" class="form-control" value="<?php echo htmlspecialchars($name); ?>" placeholder="Enter your full name" required>
                </div>

                <div class="col-12">
                    <label class="form-label" for="email">Email</label>
                    <input type="email" id="email" name="email" class="form-control" value="<?php echo htmlspecialchars($email); ?>" placeholder="you@example.com" required>
                </div>

                <div class="col-12">
                    <label class="form-label" for="password">Password</label>
                    <input type="password" id="password" name="password" class="form-control" placeholder="Minimum 6 characters" required>
                </div>

                <div class="col-12">
                    <label class="form-label" for="roleSelect">Role</label>
                    <select id="roleSelect" name="role" class="form-select" required>
                        <option value="client" <?php echo $role === 'client' ? 'selected' : ''; ?>>Client</option>
                        <option value="developer" <?php echo $role === 'developer' ? 'selected' : ''; ?>>PHP Developer</option>
                    </select>
                </div>

                <div id="developerFields" class="col-12">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label" for="php_proficiency">PHP Proficiency</label>
                            <select id="php_proficiency" name="php_proficiency" class="form-select">
                                <option value="">Select your level</option>
                                <?php foreach ($allowedLevels as $level): ?>
                                    <option value="<?php echo htmlspecialchars($level); ?>" <?php echo $phpProficiency === $level ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($level); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="resume">Upload Resume</label>
                            <input type="file" id="resume" name="resume" class="form-control" accept=".pdf,.doc,.docx">
                            <div class="form-text">Accepted formats: PDF, DOC, DOCX. Maximum size: 5MB.</div>
                        </div>
                    </div>
                </div>

                <div class="col-12">
                    <button type="submit" class="btn btn-primary w-100">Sign Up</button>
                </div>
            </form>

            <p class="text-center mt-4 mb-0">Already have an account? <a href="<?php echo htmlspecialchars(appUrl('login.php')); ?>">Login</a></p>
        </div>
    </div>
</main>

<script>
function toggleDeveloperFields() {
    const role = document.getElementById('roleSelect').value;
    const container = document.getElementById('developerFields');
    container.style.display = role === 'developer' ? 'block' : 'none';
}

document.getElementById('roleSelect').addEventListener('change', toggleDeveloperFields);
toggleDeveloperFields();
</script>

<?php include 'includes/footer.php'; ?>