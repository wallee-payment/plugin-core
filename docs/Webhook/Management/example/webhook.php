<?php

namespace MyPlugin\ExampleWebhookImplementation;

/**
 * Webhook Management Example
 *
 * This script demonstrates the Webhook Management functionality:
 * 1. Installing a Webhook (URL + Listener).
 * 2. Listing Webhook URLs and Listeners.
 * 3. Updating a Webhook URL.
 * 4. Uninstalling a Webhook.
 *
 * USAGE:
 * php webhook.php
 */

use Wallee\PluginCore\Sdk\SdkV1\WebhookManagementGateway;
use Wallee\PluginCore\Sdk\SdkV1\WebhookSignatureGateway;
use Wallee\PluginCore\Transaction\State as TransactionState;
use Wallee\PluginCore\Webhook\Enum\WebhookListener;
use Wallee\PluginCore\Webhook\WebhookConfig;
use Wallee\PluginCore\Webhook\WebhookService;

error_reporting(E_ALL & ~E_DEPRECATED);

/** @var array $common */
$common = require __DIR__ . '/../../../examples/Common/bootstrap.php';

$spaceId = $common['spaceId'];
$sdkProvider = $common['sdkProvider'];
$logger = $common['logger'];
$settings = $common['settings'];
// Webhook example doesn't need persistence or argLoader typically, but they are available.

// 1. Setup Services
$managementGateway = new WebhookManagementGateway($sdkProvider, $logger);
$signatureGateway = new WebhookSignatureGateway($sdkProvider, $logger);

$webhookService = new WebhookService(
    $managementGateway,
    $signatureGateway,
    $logger
);

echo "Starting Webhook Management Demo in Space $spaceId...\n\n";

// 2. STEP 1: Installation
echo "--- STEP 1: Installing Webhook ---\n";
// Use uniqid to ensure URL is unique (SDK constraint)
$uniqueId = uniqid();
$config = new WebhookConfig(
    url: 'https://example.com/webhook/callback/' . $uniqueId,
    name: 'Demo Webhook ' . $uniqueId,
    entity: WebhookListener::TRANSACTION, // Enum
    eventStates: [TransactionState::AUTHORIZED->value] // Array of states
);

try {
    $webhookService->installWebhook((int)$spaceId, $config);
    echo "SUCCESS: Webhook installed.\n";
} catch (\Exception $e) {
    exit("FAILED: " . $e->getMessage() . "\n");
}

// 3. STEP 2: Listing
echo "\n--- STEP 2: Listing Webhooks ---\n";
try {
    $urls = $webhookService->listUrls((int)$spaceId);

    echo "Found " . count($urls) . " Webhook URL(s).\n";

    // Find our recently created URL and Listener
    $myUrl = null;
    foreach ($urls as $url) {
        if ($url->name === $config->name) {
            $myUrl = $url;
            echo "URL Found: ID=" . $url->id . ", Name=" . $url->name . ", URL=" . $url->url . "\n";
            break;
        }
    }

    $myListener = null;
    if ($myUrl) {
        // Fetch listeners specifically for this URL
        $listeners = $webhookService->getWebhookListeners((int)$spaceId, $myUrl->id);
        foreach ($listeners as $listener) {
            // In the installWebhook method, we use the same name for the listener and URL
            if ($listener->name === $config->name) {
                $myListener = $listener;
                echo "Listener Found: ID=" . $listener->id . ", Name=" . $listener->name . "\n";
                break;
            }
        }
    }
} catch (\Exception $e) {
    echo "FAILED: " . $e->getMessage() . "\n";
}

// 4. STEP 3: Update URL
if ($myUrl) {
    echo "\n--- STEP 3: Updating Webhook URL ---\n";
    try {
        $newUrl = 'https://example.com/webhook/v2/' . uniqid(); // Ensure uniqueness
        $webhookService->updateWebhookUrl((int)$spaceId, $myUrl->id, $newUrl);
        echo "SUCCESS: Webhook URL updated to: $newUrl\n";
    } catch (\Exception $e) {
        echo "FAILED to update URL: " . $e->getMessage() . "\n";
    }
}

// 5. STEP 4: Uninstall (Cleanup)
if ($myUrl) {
    echo "\n--- STEP 4: Uninstalling (Cleanup) ---\n";
    try {
        // We delete listeners for this URL to be clean, or just delete the URL?
        // WebhookService::deleteWebhookListenersForUrl removes listeners.
        // There is no single "uninstall" method that removes URL+Listeners in one go in the service I see?
        // Let's check WebhookService.
        // It has deleteWebhookListenersForUrl($spaceId, $urlId).
        // It implies the URL itself might remain unless we delete it via SDK directly?
        // The service doesn't seem to expose 'deleteUrl'.
        // However, installWebhook creates both.
        // For cleanup, let's remove the listeners we added.

        $webhookService->deleteWebhookListenersForUrl((int)$spaceId, $myUrl->id);
        echo "SUCCESS: Webhook Listeners removed for URL ID " . $myUrl->id . ".\n";

        // Note: The URL entity likely persists in the portal unless deleted. 
        // But the service interface for deletion seems focused on listener cleanup.

    } catch (\Exception $e) {
        echo "FAILED to uninstall: " . $e->getMessage() . "\n";
    }
}

echo "\nDone.\n";
