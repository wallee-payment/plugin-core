<?php

namespace MyPlugin\ExampleCheckoutImplementation;

use Wallee\PluginCore\Examples\Common\FilePersistence;
use Wallee\PluginCore\LineItem\LineItemConsistencyService;
use Wallee\PluginCore\Render\IntegratedPaymentRenderService;
use Wallee\PluginCore\Sdk\SdkV1\TransactionGateway;
use Wallee\PluginCore\Transaction\TransactionService;

error_reporting(E_ALL & ~E_DEPRECATED);

// Force Lightbox Mode
putenv('PLUGINCORE_DEMO_INTEGRATION_MODE=lightbox');

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
$renderService = new IntegratedPaymentRenderService();

// 2. Load Session
$transactionId = $persistence->get('transaction_id');

if (!$transactionId) {
    exit("ERROR: No active session. Run '1_start_checkout.php' first.\n");
}

echo "Confirming Checkout for Transaction ID: $transactionId (Mode: Lightbox)\n";

// 3. Generate Simulation
try {
    $mode = 'lightbox';

    // Fetch Available Methods
    $paymentMethods = $gateway->getAvailablePaymentMethods((int)$spaceId, $transactionId);
    if (empty($paymentMethods)) {
        exit("\n[ERROR] No payment methods available for this transaction.\n");
    }

    // Pick the first one
    $method = reset($paymentMethods);
    echo "Selected Payment Method: " . $method->name . " (ID: " . $method->id . ")\n";

    // Get JS URL
    $javascriptUrl = $service->getPaymentUrl((int)$spaceId, $transactionId);

    // Render HTML Block
    $blockHtml = $renderService->render($javascriptUrl, $method->id, $mode, 'payment-form');

    // Load Host Template & Inject
    $templatePath = __DIR__ . '/resources/integrated_checkout_host.html';
    if (!file_exists($templatePath)) {
        exit("\n[ERROR] Host template not found at: $templatePath\n");
    }
    $templateHtml = file_get_contents($templatePath);
    $finalHtml = str_replace('{{content}}', $blockHtml, $templateHtml);

    // Save Simulation File
    $outputFile = __DIR__ . "/checkout_simulation_lightbox_{$transactionId}.html";
    file_put_contents($outputFile, $finalHtml);

    echo "\n---------------------------------------------------\n";
    echo "CHECKOUT SIMULATION READY (Lightbox)\n";
    echo "---------------------------------------------------\n";
    echo "HTML file generated at: $outputFile\n";
    echo "Open this file in your browser to test the Lightbox integration.\n";
    echo "---------------------------------------------------\n";
} catch (\Exception $e) {
    echo "\n[ERROR] Could not generate checkout simulation.\n";
    echo "Reason: " . $e->getMessage() . "\n";
    exit(1);
}
