<?php

namespace MyPlugin\ExampleVoidImplementation;

/**
 * Void Example
 *
 * This script demonstrates how to void an authorized transaction.
 *
 * USAGE:
 * php void.php [transaction_id]
 */

use Wallee\PluginCore\Examples\Common\TransactionIdLoader;
use Wallee\PluginCore\Sdk\SdkV1\TransactionCompletionGateway;
use Wallee\PluginCore\Transaction\Completion\TransactionCompletionService;

error_reporting(E_ALL & ~E_DEPRECATED);

/** @var array $common */
$common = require __DIR__ . '/../../examples/Common/bootstrap.php';

$spaceId = $common['spaceId'];
$sdkProvider = $common['sdkProvider'];
$logger = $common['logger'];
$settings = $common['settings'];
// 1. Load Transaction ID
try {
    $transactionId = TransactionIdLoader::load($argv);
} catch (\Exception $e) {
    exit($e->getMessage());
}

// 2. Setup Services
$gateway = new TransactionCompletionGateway($sdkProvider);
$service = new TransactionCompletionService($gateway, $logger);

// 3. Void Transaction
try {
    echo "Voiding Transaction $transactionId..." . PHP_EOL;
    $state = $service->void((int)$spaceId, $transactionId);
    echo "Result: Void state is $state" . PHP_EOL;
} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . PHP_EOL;
}
