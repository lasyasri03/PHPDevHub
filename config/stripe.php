<?php
require_once __DIR__ . '/../vendor/autoload.php';
$stripe_secret_key = "YOUR_STRIPE_SECRET_KEY";
$stripe_publishable_key = "YOUR_STRIPE_PUBLISHABLE_KEY";
if ($stripeSecretKey === '') {
    throw new RuntimeException('Stripe secret key is not configured.');
}

\Stripe\Stripe::setApiKey($stripeSecretKey);

return [
    'publishable_key' => $stripePublishableKey,
    'secret_key' => $stripeSecretKey,
    'currency' => 'usd',
];
