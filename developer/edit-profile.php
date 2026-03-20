<?php
include '../includes/db.php';
include '../includes/auth.php';
require_once '../includes/role_ui.php';
require_once '../includes/marketplace_helpers.php';
requireRole('developer');

$allowedLevels = ['Beginner', 'Intermediate', 'Advanced', 'Expert'];
$allowedAvailabilityStatuses = ['Available for Hire', 'Busy', 'Not Available'];
$allowedResumeExtensions = ['pdf', 'doc', 'docx'];
$allowedImageExtensions = ['jpg', 'jpeg', 'png'];
$maxResumeSize = 5 * 1024 * 1024;

$userId = (int) $_SESSION['user_id'];
$errors = [];

$stmt = $pdo->prepare("SELECT u.name, d.* FROM users u LEFT JOIN developers d ON d.user_id = u.id WHERE u.id = ? LIMIT 1");
$stmt->execute([$userId]);
$profile = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

$name = trim((string) ($profile['name'] ?? $_SESSION['name'] ?? ''));
$skills = trim((string) ($profile['skills'] ?? ''));
$experience = isset($profile['experience']) ? (string) $profile['experience'] : '';
$bio = trim((string) ($profile['bio'] ?? ''));
$location = trim((string) ($profile['location'] ?? ''));
$phpProficiency = trim((string) ($profile['php_proficiency'] ?? ''));
$availability = trim((string) ($profile['availability'] ?? 'Available for Hire'));
$hourlyRate = isset($profile['hourly_rate']) && $profile['hourly_rate'] !== null ? (string) $profile['hourly_rate'] : '';
$githubLink = trim((string) ($profile['github_link'] ?? $profile['github'] ?? ''));
$currentProfileImage = (string) ($profile['profile_image'] ?? '');
$currentResume = (string) ($profile['resume'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $skills = trim($_POST['skills'] ?? '');
    $experience = trim($_POST['experience'] ?? '');
    $bio = trim($_POST['bio'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $phpProficiency = trim($_POST['php_proficiency'] ?? '');
    $availability = trim($_POST['availability'] ?? '');
    $hourlyRate = trim($_POST['hourly_rate'] ?? '');
    $githubLink = trim($_POST['github_link'] ?? '');
    $profileImage = $currentProfileImage;
    $resumePath = $currentResume;

    if ($name === '' || $skills === '' || $experience === '' || $location === '' || $bio === '') {
        $errors[] = 'Please complete all required profile fields.';
    }
    if (!ctype_digit($experience) || (int) $experience < 0) {
        $errors[] = 'Experience must be a valid number of years.';
    }
    if (!in_array($phpProficiency, $allowedLevels, true)) {
        $errors[] = 'Please select a valid PHP proficiency level.';
    }
    if (!in_array($availability, $allowedAvailabilityStatuses, true)) {
        $errors[] = 'Please select a valid availability status.';
    }
    if ($hourlyRate !== '' && (!is_numeric($hourlyRate) || (float) $hourlyRate < 0)) {
        $errors[] = 'Hourly rate must be a valid amount.';
    }
    if ($githubLink !== '' && filter_var($githubLink, FILTER_VALIDATE_URL) === false) {
        $errors[] = 'GitHub Link must be a valid URL.';
    }

    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($_FILES['profile_image']['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'Profile image upload failed.';
        } else {
            $imageExtension = strtolower(pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION));
            if (!in_array($imageExtension, $allowedImageExtensions, true)) {
                $errors[] = 'Profile image must be a JPG, JPEG, or PNG file.';
            }
        }
    }

    if (isset($_FILES['resume']) && $_FILES['resume']['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($_FILES['resume']['error'] !== UPLOAD_ERR_OK) {
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

    if (empty($errors) && isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../uploads/profile/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        $imageExtension = strtolower(pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION));
        $imageFileName = uniqid('profile_', true) . '.' . $imageExtension;
        if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $uploadDir . $imageFileName)) {
            $profileImage = $imageFileName;
        } else {
            $errors[] = 'Unable to save the uploaded profile image.';
        }
    }

    if (empty($errors) && isset($_FILES['resume']) && $_FILES['resume']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../uploads/resumes/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        $resumeExtension = strtolower(pathinfo($_FILES['resume']['name'], PATHINFO_EXTENSION));
        $resumeFileName = uniqid('resume_', true) . '.' . $resumeExtension;
        if (move_uploaded_file($_FILES['resume']['tmp_name'], $uploadDir . $resumeFileName)) {
            $resumePath = '/uploads/resumes/' . $resumeFileName;
        } else {
            $errors[] = 'Unable to save the uploaded resume.';
        }
    }

    if (empty($errors)) {
        $pdo->beginTransaction();
        try {
            $updateUserStmt = $pdo->prepare("UPDATE users SET name = ? WHERE id = ?");
            $updateUserStmt->execute([$name, $userId]);

            $existsStmt = $pdo->prepare("SELECT COUNT(*) FROM developers WHERE user_id = ?");
            $existsStmt->execute([$userId]);
            $exists = (int) $existsStmt->fetchColumn() > 0;

            if ($exists) {
                $saveStmt = $pdo->prepare(
                    "UPDATE developers
                     SET skills = ?, experience = ?, github_link = ?, bio = ?, location = ?, profile_image = ?, resume = ?, php_proficiency = ?, hourly_rate = ?, availability = ?
                     WHERE user_id = ?"
                );
                $saveStmt->execute([$skills, (int) $experience, $githubLink !== '' ? $githubLink : null, $bio, $location, $profileImage, $resumePath, $phpProficiency, $hourlyRate !== '' ? (float) $hourlyRate : null, $availability, $userId]);
            } else {
                $saveStmt = $pdo->prepare(
                    "INSERT INTO developers (user_id, skills, experience, github_link, bio, location, profile_image, resume, php_proficiency, hourly_rate, availability)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
                );
                $saveStmt->execute([$userId, $skills, (int) $experience, $githubLink !== '' ? $githubLink : null, $bio, $location, $profileImage, $resumePath, $phpProficiency, $hourlyRate !== '' ? (float) $hourlyRate : null, $availability]);
            }

            $pdo->commit();
            $_SESSION['name'] = $name;
            header('Location: ' . appUrl('developer/dashboard.php') . '?success=' . urlencode('Profile updated successfully.'));
            exit;
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = 'Unable to save your profile right now.';
        }
    }
}

renderRolePageStart('developer', 'profile', 'Edit Profile', 'Create or update your professional profile so clients can discover and hire you.');
?>
<?php if ($errors): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($errors[0]); ?></div>
<?php endif; ?>

<section class="panel-card">
    <form method="post" enctype="multipart/form-data" class="row g-3">
        <div class="col-md-6">
            <label class="form-label" for="name">Name</label>
            <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($name); ?>" required>
        </div>
        <div class="col-md-6">
            <label class="form-label" for="skills">Skills</label>
            <input type="text" class="form-control" id="skills" name="skills" value="<?php echo htmlspecialchars($skills); ?>" required>
        </div>
        <div class="col-md-4">
            <label class="form-label" for="experience">Experience</label>
            <input type="number" min="0" class="form-control" id="experience" name="experience" value="<?php echo htmlspecialchars($experience); ?>" required>
        </div>
        <div class="col-md-4">
            <label class="form-label" for="location">Location</label>
            <input type="text" class="form-control" id="location" name="location" value="<?php echo htmlspecialchars($location); ?>" required>
        </div>
        <div class="col-md-4">
            <label class="form-label" for="hourly_rate">Hourly Rate</label>
            <input type="number" min="0" step="0.01" class="form-control" id="hourly_rate" name="hourly_rate" value="<?php echo htmlspecialchars($hourlyRate); ?>">
        </div>
        <div class="col-12">
            <label class="form-label" for="github_link">GitHub Link</label>
            <input type="url" class="form-control" id="github_link" name="github_link" value="<?php echo htmlspecialchars($githubLink); ?>" placeholder="https://github.com/username">
        </div>
        <div class="col-md-6">
            <label class="form-label" for="php_proficiency">PHP Proficiency</label>
            <select class="form-select" id="php_proficiency" name="php_proficiency" required>
                <option value="">Select level</option>
                <?php foreach ($allowedLevels as $level): ?>
                    <option value="<?php echo htmlspecialchars($level); ?>" <?php echo $phpProficiency === $level ? 'selected' : ''; ?>><?php echo htmlspecialchars($level); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-6">
            <label class="form-label" for="availability">Availability</label>
            <select class="form-select" id="availability" name="availability" required>
                <?php foreach ($allowedAvailabilityStatuses as $status): ?>
                    <option value="<?php echo htmlspecialchars($status); ?>" <?php echo $availability === $status ? 'selected' : ''; ?>><?php echo htmlspecialchars($status); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12">
            <label class="form-label" for="bio">Bio</label>
            <textarea class="form-control" id="bio" name="bio" rows="5" required><?php echo htmlspecialchars($bio); ?></textarea>
        </div>
        <div class="col-md-6">
            <label class="form-label" for="profile_image">Profile Image</label>
            <input type="file" class="form-control" id="profile_image" name="profile_image" accept=".jpg,.jpeg,.png">
            <?php if ($currentProfileImage !== ''): ?>
                <div class="mt-2"><img src="<?php echo htmlspecialchars(appUrl('uploads/profile/' . $currentProfileImage)); ?>" alt="Current profile image" style="width:72px;height:72px;object-fit:cover;border-radius:50%;"></div>
            <?php endif; ?>
        </div>
        <div class="col-md-6">
            <label class="form-label" for="resume">Resume</label>
            <input type="file" class="form-control" id="resume" name="resume" accept=".pdf,.doc,.docx">
            <?php if ($currentResume !== ''): ?>
                <div class="mt-2"><a href="<?php echo htmlspecialchars($currentResume); ?>" target="_blank">View current resume</a></div>
            <?php endif; ?>
        </div>
        <div class="col-12">
            <button type="submit" class="btn btn-primary">Save Profile</button>
        </div>
    </form>
</section>
<?php renderRolePageEnd(); ?>
