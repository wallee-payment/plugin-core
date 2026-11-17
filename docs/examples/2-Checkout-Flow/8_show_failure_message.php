<?php

namespace MyPlugin\ExampleCheckoutImplementation;

/**
 * Failure Message Example
 *
 * When a payment fails, the customer comes back to your shop's failure page.
 * getFailureMessage() returns the reason to show them: the message the
 * Wallee Portal provides for the customer, in the shop's
 * language, or your own default when there is none. It never throws, so a
 * failure page can call it without a try/catch.
 *
 * USAGE:
 * php 8_show_failure_message.php [transaction_id] [--locale=de-DE]
 * (Run 3_start_checkout.php first, then fail the payment via any of the
 * 5_confirm_*.php scripts.)
 */

use Wallee\PluginCore\Examples\Common\TransactionIdLoader;
use Wallee\PluginCore\LineItem\LineItemConsistencyService;
use Wallee\PluginCore\Sdk\WebServiceAPIV1\TransactionGateway;
use Wallee\PluginCore\Transaction\TransactionService;

// 📖 Concept documentation: See docs/2-Checkout-Flow/Checkout.md

error_reporting(E_ALL & ~E_DEPRECATED);

/** @var array $common */
$common = require __DIR__ . '/../Common/bootstrap.php';

$spaceId = (int)$common['spaceId'];
$sdkProvider = $common['sdkProvider'];
$logger = $common['logger'];
$settings = $common['settings'];

// The shop's locale, e.g. --locale=de-DE ('de_DE' works too). Defaults to en-US.
$locale = 'en-US';
$args = [];
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--locale=')) {
        $locale = substr($arg, strlen('--locale='));
        continue;
    }
    $args[] = $arg;
}

// Load the transaction ID created by 3_start_checkout.php (or pass it as an argument).
try {
    $transactionId = TransactionIdLoader::load($args);
} catch (\Exception $e) {
    exit($e->getMessage());
}

$transactionService = new TransactionService(
    new TransactionGateway($sdkProvider, $logger, $settings),
    new LineItemConsistencyService($settings, $logger),
    $logger,
);

$message = $transactionService->getFailureMessage(
    $spaceId,
    $transactionId,
    $locale,
    'Your payment could not be completed. Please try again or choose another payment method.',
);

echo "Message for the customer ($locale) on Transaction ID $transactionId:\n";
echo " > $message\n";
