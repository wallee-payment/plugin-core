<?php

namespace MyPlugin\ExampleCheckoutImplementation;

use Wallee\PluginCore\Address\Address;
use Wallee\PluginCore\Examples\Common\FilePersistence;
use Wallee\PluginCore\LineItem\LineItem;
use Wallee\PluginCore\LineItem\LineItemConsistencyService;
use Wallee\PluginCore\Sdk\SdkV1\TransactionGateway;
use Wallee\PluginCore\Tax\Tax;
use Wallee\PluginCore\Transaction\TransactionContext;
use Wallee\PluginCore\Transaction\TransactionService;

error_reporting(E_ALL & ~E_DEPRECATED);

/** @var array $common */
$common = require __DIR__ . '/../../examples/Common/bootstrap.php';

$spaceId = $common['spaceId'];
$sdkProvider = $common['sdkProvider'];
$logger = $common['logger'];
$settings = $common['settings'];
/** @var FilePersistence $persistence */
$persistence = $common['persistence'];

// Initialize the core services needed for the transaction.
$gateway = new TransactionGateway($sdkProvider, $logger, $settings);
$consistency = new LineItemConsistencyService($settings, $logger);
$service = new TransactionService($gateway, $consistency, $logger);

// Initialize the session by clearing any existing session data.
$sessionFile = __DIR__ . '/session.json';
if (file_exists($sessionFile)) {
    unlink($sessionFile);
    echo "Refreshed Session (Deleted old session.json)\n";
}

// Build the initial cart with a sample product.
echo "Building Cart (1x Swiss Watch)...\n";

$context = new TransactionContext();
$context->spaceId = (int)$spaceId;
$context->merchantReference = 'DEMO-' . uniqid();
$context->currencyCode = 'CHF';
$context->language = 'en-US';
$context->customerId = 'guest-123';
$context->transactionId = null;

$context->successUrl = 'https://example.com/success';
$context->failedUrl = 'https://example.com/fail';

$billing = new Address();
$billing->givenName = 'John';
$billing->familyName = 'Doe';
$billing->street = 'Bahnhofstrasse 1';
$billing->city = 'Zurich';
$billing->postcode = '8000';
$billing->country = 'CH';
$billing->emailAddress = 'test@example.com';
$context->billingAddress = $billing;

$item = new LineItem();
$item->uniqueId = 'sku-123';
$item->sku = 'sku-123';
$item->name = 'Swiss Watch';
$item->quantity = 1;
$item->amountIncludingTax = 150.00;
$item->type = LineItem::TYPE_PRODUCT;
$item->addTax(new Tax('VAT', 7.7));
$context->lineItems = [$item];
$context->expectedGrandTotal = 150.00;

// Execute the upsert operation to create the transaction in the portal.
echo "Sending to Wallee...\n";

try {
    // Execute the upsert operation.
    // The persistence object is used to store the transaction ID for subsequent steps.
    $transaction = $service->upsert($context, $persistence);

    echo "\n[SUCCESS] Transaction Created: " . $transaction->id . "\n";
    echo "State: " . $transaction->state->value . "\n";
    echo "NEXT: Run '2_modify_cart.php'\n";
} catch (\Exception $e) {
    echo "\n[ERROR] Transaction Creation Failed.\n";
    echo "Reason: " . $e->getMessage() . "\n";
    exit(1);
}
