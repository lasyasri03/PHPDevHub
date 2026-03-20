<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

if (!isLoggedIn() || getUserRole() !== 'client') {
    header('Location: ' . appUrl('login.php'));
    exit;
}

try {
    $stripeConfig = require __DIR__ . '/config/stripe.php';
} catch (Throwable $e) {
    header('Location: ' . appUrl('client/dashboard.php') . '?error=' . urlencode($e->getMessage()));
    exit;
}

$clientId = (int) ($_SESSION['user_id'] ?? 0);
$projectId = (int) ($_POST['project_id'] ?? 0);
$developerId = (int) ($_POST['developer_id'] ?? 0);
$contractId = (int) ($_POST['contract_id'] ?? 0);
$submittedAmount = isset($_POST['amount']) ? (float) $_POST['amount'] : 0.0;

if ($projectId <= 0 || $developerId <= 0 || $contractId <= 0 || $submittedAmount <= 0) {
    header('Location: ' . appUrl('client/dashboard.php') . '?error=' . urlencode('Invalid project payment request.'));
    exit;
}

$contractStmt = $pdo->prepare(
    "SELECT hr.id,
            hr.client_id,
            hr.developer_id,
            p.id AS project_id,
            p.title AS project_title,
            p.budget,
            u.name AS developer_name
     FROM hire_requests hr
     INNER JOIN projects p ON p.id = hr.project_id
     INNER JOIN users u ON u.id = hr.developer_id
     WHERE hr.id = ? AND hr.client_id = ? AND hr.developer_id = ? AND p.id = ? AND hr.status = 'accepted'
     LIMIT 1"
);
$contractStmt->execute([$contractId, $clientId, $developerId, $projectId]);
$contract = $contractStmt->fetch(PDO::FETCH_ASSOC);

if (!$contract) {
    header('Location: ' . appUrl('client/dashboard.php') . '?error=' . urlencode('Contract not found or not eligible for payment.'));
    exit;
}

$amount = (float) ($contract['budget'] ?? 0);
if ($amount <= 0 || abs($amount - $submittedAmount) > 0.01) {
    header('Location: ' . appUrl('client/dashboard.php') . '?error=' . urlencode('Payment amount validation failed.'));
    exit;
}

$existingStmt = $pdo->prepare(
    "SELECT id, payment_status
     FROM payments
     WHERE project_id = ? AND client_id = ? AND developer_id = ?
     ORDER BY id DESC
     LIMIT 1"
);
$existingStmt->execute([$projectId, $clientId, $developerId]);
$existingPayment = $existingStmt->fetch(PDO::FETCH_ASSOC);

if ($existingPayment && $existingPayment['payment_status'] === 'Paid') {
    header('Location: ' . appUrl('client/dashboard.php') . '?success=' . urlencode('This project has already been paid.'));
    exit;
}

$paymentId = null;

try {
    if ($existingPayment) {
        $paymentId = (int) $existingPayment['id'];
        $resetStmt = $pdo->prepare(
            "UPDATE payments
             SET amount = ?, payment_status = 'Pending', stripe_session_id = NULL, transaction_id = NULL, paid_at = NULL, created_at = NOW()
             WHERE id = ?"
        );
        $resetStmt->execute([$amount, $paymentId]);
    } else {
        $insertStmt = $pdo->prepare(
            "INSERT INTO payments (project_id, client_id, developer_id, amount, payment_status, created_at)
             VALUES (?, ?, ?, ?, 'Pending', NOW())"
        );
        $insertStmt->execute([$projectId, $clientId, $developerId, $amount]);
        $paymentId = (int) $pdo->lastInsertId();
    }

    $successUrl = appAbsoluteUrl('payment-success.php') . '?session_id={CHECKOUT_SESSION_ID}';
    $cancelUrl = appAbsoluteUrl('payment-cancel.php') . '?payment_id=' . $paymentId;

    $session = \Stripe\Checkout\Session::create([
        'mode' => 'payment',
        'payment_method_types' => ['card'],
        'line_items' => [[
            'price_data' => [
                'currency' => $stripeConfig['currency'],
                'product_data' => [
                    'name' => 'Project Payment - ' . $contract['project_title'],
                ],
                'unit_amount' => (int) round($amount * 100),
            ],
            'quantity' => 1,
        ]],
        'success_url' => $successUrl,
        'cancel_url' => $cancelUrl,
        'client_reference_id' => (string) $paymentId,
        'metadata' => [
            'payment_id' => (string) $paymentId,
            'project_id' => (string) $projectId,
            'client_id' => (string) $clientId,
            'developer_id' => (string) $developerId,
            'contract_id' => (string) $contractId,
        ],
    ]);

    $updateSessionStmt = $pdo->prepare("UPDATE payments SET stripe_session_id = ? WHERE id = ?");
    $updateSessionStmt->execute([$session->id, $paymentId]);

    header('Location: ' . $session->url);
    exit;
} catch (Throwable $e) {
    if ($paymentId !== null) {
        $failStmt = $pdo->prepare("UPDATE payments SET payment_status = 'Pending' WHERE id = ?");
        $failStmt->execute([$paymentId]);
    }
    header('Location: ' . appUrl('client/dashboard.php') . '?error=' . urlencode('Unable to start Stripe Checkout: ' . $e->getMessage()));
    exit;
}
