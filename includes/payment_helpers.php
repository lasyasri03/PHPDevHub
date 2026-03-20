<?php
require_once __DIR__ . '/auth.php';

function getRazorpayConfig(): array
{
    return [
        'key_id' => trim((string) (getenv('RAZORPAY_KEY_ID') ?: '')),
        'key_secret' => trim((string) (getenv('RAZORPAY_KEY_SECRET') ?: '')),
        'currency' => 'INR',
        'company_name' => 'PHPDevHub',
    ];
}

function isRazorpayConfigured(): bool
{
    $config = getRazorpayConfig();
    return $config['key_id'] !== '' && $config['key_secret'] !== '';
}

function createRazorpayOrder(int $amountInSubunits, string $receipt, array $notes = []): array
{
    $config = getRazorpayConfig();
    if (!isRazorpayConfigured()) {
        throw new RuntimeException('Razorpay credentials are not configured.');
    }

    $payload = [
        'amount' => $amountInSubunits,
        'currency' => $config['currency'],
        'receipt' => $receipt,
        'notes' => $notes,
    ];

    $ch = curl_init('https://api.razorpay.com/v1/orders');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
        CURLOPT_USERPWD => $config['key_id'] . ':' . $config['key_secret'],
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_TIMEOUT => 30,
    ]);

    $response = curl_exec($ch);
    if ($response === false) {
        $error = curl_error($ch);
        curl_close($ch);
        throw new RuntimeException('Unable to connect to Razorpay: ' . $error);
    }

    $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $decoded = json_decode($response, true);
    if ($statusCode < 200 || $statusCode >= 300 || !is_array($decoded) || empty($decoded['id'])) {
        $message = $decoded['error']['description'] ?? 'Razorpay order creation failed.';
        throw new RuntimeException($message);
    }

    return $decoded;
}

function verifyRazorpayPaymentSignature(string $orderId, string $paymentId, string $signature): bool
{
    $config = getRazorpayConfig();
    if ($config['key_secret'] === '') {
        return false;
    }

    $generatedSignature = hash_hmac('sha256', $orderId . '|' . $paymentId, $config['key_secret']);
    return hash_equals($generatedSignature, $signature);
}
