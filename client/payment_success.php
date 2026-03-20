<?php
include '../includes/db.php';
include '../includes/auth.php';
require_once '../includes/payment_helpers.php';
requireRole('client');

header('Content-Type: application/json');

$paymentRecordId = (int) ($_POST['payment_record_id'] ?? 0);
$razorpayPaymentId = trim($_POST['razorpay_payment_id'] ?? '');
$razorpayOrderId = trim($_POST['razorpay_order_id'] ?? '');
$razorpaySignature = trim($_POST['razorpay_signature'] ?? '');
$clientId = (int) ($_SESSION['user_id'] ?? 0);

if ($paymentRecordId <= 0 || $razorpayPaymentId === '' || $razorpayOrderId === '' || $razorpaySignature === '') {
    echo json_encode(['ok' => false, 'message' => 'Incomplete payment verification data.']);
    exit;
}

$paymentStmt = $pdo->prepare(
    "SELECT p.id, p.project_id, p.client_id, p.developer_id, p.payment_status, p.razorpay_order_id
     FROM payments p
     WHERE p.id = ? AND p.client_id = ?
     LIMIT 1"
);
$paymentStmt->execute([$paymentRecordId, $clientId]);
$payment = $paymentStmt->fetch(PDO::FETCH_ASSOC);

if (!$payment) {
    echo json_encode(['ok' => false, 'message' => 'Payment record not found.']);
    exit;
}

if ($payment['payment_status'] === 'Paid') {
    echo json_encode(['ok' => true, 'redirect' => appUrl('client/dashboard.php') . '?success=' . urlencode('Payment already recorded.')]);
    exit;
}

if (!hash_equals((string) ($payment['razorpay_order_id'] ?? ''), $razorpayOrderId)) {
    echo json_encode(['ok' => false, 'message' => 'Order mismatch detected.']);
    exit;
}

if (!verifyRazorpayPaymentSignature($razorpayOrderId, $razorpayPaymentId, $razorpaySignature)) {
    echo json_encode(['ok' => false, 'message' => 'Invalid Razorpay signature.']);
    exit;
}

$updateStmt = $pdo->prepare(
    "UPDATE payments
     SET payment_status = 'Paid', transaction_id = ?, paid_at = NOW()
     WHERE id = ?"
);
$updateStmt->execute([$razorpayPaymentId, $paymentRecordId]);

echo json_encode([
    'ok' => true,
    'redirect' => appUrl('client/dashboard.php') . '?success=' . urlencode('Payment completed successfully.')
]);
