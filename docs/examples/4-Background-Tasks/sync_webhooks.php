<?php

namespace MyPlugin\ExampleWebhookImplementation;

/**
 * Webhook Synchronization Example
 *
 * Subscribes the space to exactly the webhooks your plugin handles. The same
 * WebhookListenerRegistry drives both sides: WebhookProcessor uses it to route
 * incoming webhooks, and synchronizeWebhooks() uses it to create one listener
 * per registered entity, for the states registered for it.
 *
 * Run it when the plugin is installed or upgraded, or when the merchant saves
 * the configuration. Listeners that already exist are left alone, so running
 * it again is safe.
 *
 * USAGE:
 * php sync_webhooks.php [--force]
 *   --force  Recreate existing listeners, to pick up newly registered states.
 */

error_reporting(E_ALL & ~E_DEPRECATED);

use MyPlugin\ExampleWebhookImplementation\Refund\RefundListener;
use MyPlugin\ExampleWebhookImplementation\Transaction\TransactionListener;
use Wallee\PluginCore\Sdk\WebServiceAPIV2\WebhookManagementGateway;
use Wallee\PluginCore\Webhook\Enum\WebhookListener;
use Wallee\PluginCore\Webhook\Exception\WebhookManagementException;
use Wallee\PluginCore\Webhook\Listener\WebhookListenerRegistry;
use Wallee\PluginCore\Webhook\WebhookService;

// 📖 Concept documentation: See docs/4-Background-Tasks/Webhook-Management.md

/** @var array $common */
$common = require __DIR__ . '/../Common/bootstrap.php';

// The listeners from the webhook-processor example. In a plugin, the registry
// you build for WebhookProcessor is the one you pass to synchronizeWebhooks().
require_once __DIR__ . '/webhook-processor/src/Transaction/TransactionListener.php';
require_once __DIR__ . '/webhook-processor/src/Refund/RefundListener.php';

$spaceId = (int)$common['spaceId'];
$logger = $common['logger'];
$sdkProvider = $common['sdkProvider'];
$force = in_array('--force', $argv, true);

$registry = new WebhookListenerRegistry();
$transactionListener = new TransactionListener($logger);
foreach (['CREATE', 'PENDING', 'CONFIRMED', 'PROCESSING', 'AUTHORIZED', 'COMPLETED', 'FULFILL'] as $state) {
    $registry->addListener(WebhookListener::TRANSACTION, $state, $transactionListener);
}
$registry->addListener(WebhookListener::REFUND, 'SUCCESSFUL', new RefundListener($logger));

$webhookService = new WebhookService(
    new WebhookManagementGateway($sdkProvider, $logger),
    $logger,
);

echo "Synchronizing webhooks in space $spaceId" . ($force ? ' (recreating existing listeners)' : '') . "...\n";

try {
    $webhookService->synchronizeWebhooks(
        $spaceId,
        'https://example.com/webhook/callback',
        'My Plugin',
        $registry,
        $force,
    );
} catch (WebhookManagementException $e) {
    exit("FAILED: " . $e->getMessage() . "\n");
}

echo "SUCCESS: the space has one listener per registered entity, pointing at the callback URL.\n";
