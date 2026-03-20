<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

if (!isLoggedIn() || getUserRole() !== 'client') {
    header('Location: ' . appUrl('login.php'));
    exit;
}

try {
    require __DIR__ . '/config/stripe.php';
} catch (Throwable $e) {
    header('Location: ' . appUrl('client/dashboard.php') . '?error=' . urlencode($e->getMessage()));
    exit;
}

$sessionId = trim($_GET['session_id'] ?? '');
if ($sessionId === '') {
    header('Location: ' . appUrl('client/dashboard.php') . '?error=' . urlencode('Missing Stripe session identifier.'));
    exit;
}

try {
    $checkoutSession = \Stripe\Checkout\Session::retrieve($sessionId);
} catch (Throwable $e) {
    header('Location: ' . appUrl('client/dashboard.php') . '?error=' . urlencode('Unable to verify Stripe payment session.'));
    exit;
}

if (($checkoutSession->payment_status ?? '') !== 'paid') {
    header('Location: ' . appUrl('client/dashboard.php') . '?error=' . urlencode('Stripe payment has not been completed.'));
    exit;
}

$paymentId = (int) ($checkoutSession->client_reference_id ?? $checkoutSession->metadata->payment_id ?? 0);
if ($paymentId <= 0) {
    header('Location: ' . appUrl('client/dashboard.php') . '?error=' . urlencode('Invalid Stripe payment reference.'));
    exit;
}

$paymentStmt = $pdo->prepare(
    "SELECT id, client_id, payment_status
     FROM payments
     WHERE id = ? AND stripe_session_id = ?
     LIMIT 1"
);
$paymentStmt->execute([$paymentId, $sessionId]);
$payment = $paymentStmt->fetch(PDO::FETCH_ASSOC);

if (!$payment || (int) $payment['client_id'] !== (int) ($_SESSION['user_id'] ?? 0)) {
    header('Location: ' . appUrl('client/dashboard.php') . '?error=' . urlencode('Payment record not found.'));
    exit;
}

if ($payment['payment_status'] !== 'Paid') {
    $transactionId = (string) ($checkoutSession->payment_intent ?? $checkoutSession->id);
    $updateStmt = $pdo->prepare(
        "UPDATE payments
         SET payment_status = 'Paid', transaction_id = ?, paid_at = NOW()
         WHERE id = ?"
    );
    $updateStmt->execute([$transactionId, $paymentId]);
}

$pageTitle = 'Payment Success';
include __DIR__ . '/includes/header.php';
?>
<section class="panel-card">
    <h1 class="section-title mb-3">Payment Successful</h1>
    <p class="section-copy mb-4">Your Stripe payment was completed successfully and recorded in PHPDevHub.</p>
    <div class="d-flex gap-2 flex-wrap">
        <a href="<?php echo htmlspecialchars(appUrl('client/dashboard.php')); ?>?success=<?php echo urlencode('Payment successful.'); ?>" class="btn btn-primary">Back to Client Dashboard</a>
        <a href="<?php echo htmlspecialchars(appUrl('client/contracts.php')); ?>" class="btn btn-outline-primary">View Contracts</a>
    </div>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
