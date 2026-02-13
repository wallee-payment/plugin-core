<?php

namespace MyPlugin\ExampleCheckoutImplementation;

use Wallee\PluginCore\Examples\Common\FilePersistence;
use Wallee\PluginCore\LineItem\LineItemConsistencyService;
use Wallee\PluginCore\Sdk\SdkV1\TransactionGateway;
use Wallee\PluginCore\Transaction\TransactionService;

error_reporting(E_ALL & ~E_DEPRECATED);

// Force Payment Page Mode
putenv('PLUGINCORE_DEMO_INTEGRATION_MODE=payment_page');

/** @var array $common */
$common = require __DIR__ . '/../../examples/Common/bootstrap.php';

$spaceId = $common['spaceId'];
$sdkProvider = $common['sdkProvider'];
$logger = $common['logger'];
$settings = $common['settings'];
/** @var FilePersistence $persistence */
$persistence = $common['persistence'];

// 1. Services
$gateway = new TransactionGateway($sdkProvider, $logger, $settings);
$consistency = new LineItemConsistencyService($settings, $logger);
$service = new TransactionService($gateway, $consistency, $logger);

// 2. Load Session
$transactionId = $persistence->get('transaction_id');

if (!$transactionId) {
    exit("ERROR: No active session. Run '1_start_checkout.php' first.\n");
}

echo "Confirming Checkout for Transaction ID: $transactionId (Mode: Payment Page)\n";

// 3. Generate URL
try {
    $paymentUrl = $service->getPaymentUrl((int)$spaceId, $transactionId);

    echo "\n---------------------------------------------------\n";
    echo "CHECKOUT READY\n";
    echo "---------------------------------------------------\n";
    echo "Please open this URL in your browser to pay:\n\n";
    echo $paymentUrl . "\n\n";
    echo "---------------------------------------------------\n";
} catch (\Exception $e) {
    echo "\n[ERROR] Could not generate payment URL.\n";
    echo "Reason: " . $e->getMessage() . "\n";
    exit(1);
}
