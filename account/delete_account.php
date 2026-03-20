<?php
include '../includes/db.php';
include '../includes/auth.php';
requireLogin();

$userId = (int) ($_SESSION['user_id'] ?? 0);
$userRole = getUserRole();
$errorMessage = null;
$cancelUrl = '/index.php';

if ($userRole === 'client') {
    $cancelUrl = '/client/dashboard.php';
} elseif ($userRole === 'developer') {
    $cancelUrl = '/developer/dashboard.php';
} elseif ($userRole === 'admin') {
    $cancelUrl = '/admin/dashboard.php';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_delete'])) {
    try {
        $pdo->beginTransaction();

        if ($userRole === 'developer') {
            $profileStmt = $pdo->prepare("SELECT profile_image, resume FROM developers WHERE user_id = ?");
            $profileStmt->execute([$userId]);
            $developerProfile = $profileStmt->fetch(PDO::FETCH_ASSOC) ?: [];

            $requestStmt = $pdo->prepare("SELECT id FROM hire_requests WHERE developer_id = ?");
            $requestStmt->execute([$userId]);
            $hireRequestIds = $requestStmt->fetchAll(PDO::FETCH_COLUMN);

            foreach ($hireRequestIds as $hireRequestId) {
                $messageStmt = $pdo->prepare("DELETE FROM messages WHERE hire_request_id = ?");
                $messageStmt->execute([(int) $hireRequestId]);
            }

            $deleteRequestsStmt = $pdo->prepare("DELETE FROM hire_requests WHERE developer_id = ?");
            $deleteRequestsStmt->execute([$userId]);

            $deleteDeveloperStmt = $pdo->prepare("DELETE FROM developers WHERE user_id = ?");
            $deleteDeveloperStmt->execute([$userId]);
        } elseif ($userRole === 'client') {
            $requestStmt = $pdo->prepare("SELECT id FROM hire_requests WHERE client_id = ?");
            $requestStmt->execute([$userId]);
            $hireRequestIds = $requestStmt->fetchAll(PDO::FETCH_COLUMN);

            foreach ($hireRequestIds as $hireRequestId) {
                $messageStmt = $pdo->prepare("DELETE FROM messages WHERE hire_request_id = ?");
                $messageStmt->execute([(int) $hireRequestId]);
            }

            $deleteRequestsStmt = $pdo->prepare("DELETE FROM hire_requests WHERE client_id = ?");
            $deleteRequestsStmt->execute([$userId]);
        }

        $deleteUserStmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $deleteUserStmt->execute([$userId]);

        $pdo->commit();

        if (!empty($developerProfile['profile_image'])) {
            $profileImagePath = '../uploads/profile/' . $developerProfile['profile_image'];
            if (is_file($profileImagePath)) {
                unlink($profileImagePath);
            }
        }

        if (!empty($developerProfile['resume'])) {
            $resumePath = '..' . $developerProfile['resume'];
            if (is_file($resumePath)) {
                unlink($resumePath);
            }
        }

        session_destroy();
        header('Location: /index.php?success=' . urlencode('Your account has been successfully deleted.'));
        exit;
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $errorMessage = 'Unable to delete your account right now.';
    }
}
?>
<?php include '../includes/header.php'; ?>

<div class="row justify-content-center">
    <div class="col-md-8 col-lg-6">
        <div class="card shadow-sm">
            <div class="card-body">
                <h2 class="h4 mb-3">Delete Account</h2>
                <p class="mb-3">Are you sure you want to delete your account?</p>
                <p class="text-danger mb-4">This action cannot be undone.</p>

                <?php if (!empty($errorMessage)): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($errorMessage); ?></div>
                <?php endif; ?>

                <div class="d-flex gap-2">
                    <a href="<?php echo htmlspecialchars($cancelUrl); ?>" class="btn btn-outline-secondary">Cancel</a>
                    <form method="POST" class="m-0">
                        <button type="submit" name="confirm_delete" value="1" class="btn btn-danger">Delete Account</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
