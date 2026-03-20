<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

$paymentId = (int) ($_GET['payment_id'] ?? 0);
if ($paymentId > 0 && isLoggedIn() && getUserRole() === 'client') {
    $cancelStmt = $pdo->prepare(
        "UPDATE payments
         SET payment_status = CASE WHEN payment_status = 'Paid' THEN 'Paid' ELSE 'Cancelled' END
         WHERE id = ? AND client_id = ?"
    );
    $cancelStmt->execute([$paymentId, (int) ($_SESSION['user_id'] ?? 0)]);
}

$pageTitle = 'Payment Cancelled';
include __DIR__ . '/includes/header.php';
?>
<section class="panel-card">
    <h1 class="section-title mb-3">Payment Cancelled</h1>
    <p class="section-copy mb-4">The Stripe Checkout session was cancelled. You can return and try the payment again at any time.</p>
    <a href="<?php echo htmlspecialchars(appUrl('client/dashboard.php')); ?>" class="btn btn-primary">Back to Client Dashboard</a>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
