<?php
include '../includes/db.php';
include '../includes/auth.php';
require_once '../includes/payment_helpers.php';
requireRole('client');

$clientId = (int) ($_SESSION['user_id'] ?? 0);
$contractId = (int) ($_GET['contract_id'] ?? 0);

if ($contractId <= 0) {
    header('Location: ' . appUrl('client/dashboard.php') . '?error=' . urlencode('Invalid contract selected for payment.'));
    exit;
}

if (!isRazorpayConfigured()) {
    header('Location: ' . appUrl('client/dashboard.php') . '?error=' . urlencode('Razorpay is not configured. Set RAZORPAY_KEY_ID and RAZORPAY_KEY_SECRET first.'));
    exit;
}

$contractStmt = $pdo->prepare(
    "SELECT hr.id,
            hr.client_id,
            hr.developer_id,
            p.id AS project_id,
            p.title AS project_title,
            p.description,
            p.budget,
            u.name AS developer_name
     FROM hire_requests hr
     INNER JOIN projects p ON p.id = hr.project_id
     INNER JOIN users u ON u.id = hr.developer_id
     WHERE hr.id = ? AND hr.client_id = ? AND hr.status = 'accepted'
     LIMIT 1"
);
$contractStmt->execute([$contractId, $clientId]);
$contract = $contractStmt->fetch(PDO::FETCH_ASSOC);

if (!$contract) {
    header('Location: ' . appUrl('client/dashboard.php') . '?error=' . urlencode('Contract not found or not eligible for payment.'));
    exit;
}

$amount = (float) ($contract['budget'] ?? 0);
if ($amount <= 0) {
    header('Location: ' . appUrl('client/dashboard.php') . '?error=' . urlencode('This project does not have a valid payable budget.'));
    exit;
}

$paidCheckStmt = $pdo->prepare(
    "SELECT id FROM payments
     WHERE project_id = ? AND client_id = ? AND developer_id = ? AND payment_status = 'Paid'
     ORDER BY id DESC
     LIMIT 1"
);
$paidCheckStmt->execute([(int) $contract['project_id'], $clientId, (int) $contract['developer_id']]);
if ($paidCheckStmt->fetch()) {
    header('Location: ' . appUrl('client/dashboard.php') . '?success=' . urlencode('This project has already been paid.'));
    exit;
}

try {
    $receipt = 'phpdevhub_' . $contract['project_id'] . '_' . $contractId . '_' . time();
    $order = createRazorpayOrder(
        (int) round($amount * 100),
        $receipt,
        [
            'project_id' => (string) $contract['project_id'],
            'contract_id' => (string) $contractId,
            'client_id' => (string) $clientId,
            'developer_id' => (string) $contract['developer_id'],
        ]
    );

    $existingPaymentStmt = $pdo->prepare(
        "SELECT id
         FROM payments
         WHERE project_id = ? AND client_id = ? AND developer_id = ?
         ORDER BY id DESC
         LIMIT 1"
    );
    $existingPaymentStmt->execute([(int) $contract['project_id'], $clientId, (int) $contract['developer_id']]);
    $existingPayment = $existingPaymentStmt->fetch(PDO::FETCH_ASSOC);

    if ($existingPayment) {
        $paymentId = (int) $existingPayment['id'];
        $updatePaymentStmt = $pdo->prepare(
            "UPDATE payments
             SET amount = ?, payment_status = 'Pending', transaction_id = NULL, razorpay_order_id = ?, created_at = NOW(), paid_at = NULL
             WHERE id = ?"
        );
        $updatePaymentStmt->execute([$amount, $order['id'], $paymentId]);
    } else {
        $insertPaymentStmt = $pdo->prepare(
            "INSERT INTO payments (project_id, client_id, developer_id, amount, payment_status, transaction_id, razorpay_order_id, created_at)
             VALUES (?, ?, ?, ?, 'Pending', NULL, ?, NOW())"
        );
        $insertPaymentStmt->execute([(int) $contract['project_id'], $clientId, (int) $contract['developer_id'], $amount, $order['id']]);
        $paymentId = (int) $pdo->lastInsertId();
    }
} catch (Throwable $e) {
    header('Location: ' . appUrl('client/dashboard.php') . '?error=' . urlencode($e->getMessage()));
    exit;
}

$config = getRazorpayConfig();
$pageTitle = 'Pay Project';
$usePageShell = true;
include '../includes/header.php';
?>
<section class="panel-card">
    <div class="panel-header">
        <div>
            <h1 class="section-title">Complete Project Payment</h1>
            <p class="section-copy">Razorpay checkout will open automatically for this contract.</p>
        </div>
        <a href="<?php echo htmlspecialchars(appUrl('client/dashboard.php')); ?>" class="btn btn-outline-primary">Back to Dashboard</a>
    </div>

    <div class="list-item">
        <div class="list-item-top">
            <div>
                <h3><?php echo htmlspecialchars($contract['project_title']); ?></h3>
                <p class="meta-text mb-0">Developer: <?php echo htmlspecialchars($contract['developer_name']); ?></p>
            </div>
            <span class="info-pill">Rs <?php echo number_format($amount, 2); ?></span>
        </div>
        <p class="meta-text mb-0"><?php echo htmlspecialchars($contract['description'] ?: 'Project payment via PHPDevHub escrow workflow.'); ?></p>
    </div>

    <div class="mt-4 d-flex gap-2 flex-wrap">
        <button type="button" id="openPaymentButton" class="btn btn-primary">Pay with Razorpay</button>
        <a href="<?php echo htmlspecialchars(appUrl('client/dashboard.php')); ?>" class="btn btn-outline-secondary">Cancel</a>
    </div>
</section>

<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
<script>
    (function () {
        const options = {
            key: <?php echo json_encode($config['key_id']); ?>,
            amount: <?php echo json_encode((int) round($amount * 100)); ?>,
            currency: "INR",
            name: "PHPDevHub",
            description: <?php echo json_encode('Payment for ' . $contract['project_title']); ?>,
            order_id: <?php echo json_encode($order['id']); ?>,
            prefill: {
                name: <?php echo json_encode($_SESSION['name'] ?? ''); ?>,
                email: <?php echo json_encode($_SESSION['email'] ?? ''); ?>
            },
            notes: {
                project_id: <?php echo json_encode((string) $contract['project_id']); ?>,
                contract_id: <?php echo json_encode((string) $contractId); ?>
            },
            theme: {
                color: "#2563EB"
            },
            handler: function (response) {
                fetch(<?php echo json_encode(appUrl('client/payment_success.php')); ?>, {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/x-www-form-urlencoded"
                    },
                    body: new URLSearchParams({
                        payment_record_id: <?php echo json_encode((string) $paymentId); ?>,
                        razorpay_payment_id: response.razorpay_payment_id,
                        razorpay_order_id: response.razorpay_order_id,
                        razorpay_signature: response.razorpay_signature
                    })
                })
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    if (data.ok) {
                        window.location.href = data.redirect;
                        return;
                    }
                    window.location.href = <?php echo json_encode(appUrl('client/dashboard.php') . '?error='); ?> + encodeURIComponent(data.message || "Payment verification failed.");
                })
                .catch(function () {
                    window.location.href = <?php echo json_encode(appUrl('client/dashboard.php') . '?error='); ?> + encodeURIComponent("Payment verification failed.");
                });
            },
            modal: {
                ondismiss: function () {
                    window.location.href = <?php echo json_encode(appUrl('client/dashboard.php') . '?error='); ?> + encodeURIComponent("Payment was cancelled.");
                }
            }
        };

        const razorpay = new Razorpay(options);
        document.getElementById("openPaymentButton").addEventListener("click", function () {
            razorpay.open();
        });
        razorpay.open();
    })();
</script>
<?php include '../includes/footer.php'; ?>
