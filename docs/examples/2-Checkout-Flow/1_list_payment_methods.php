<?php

namespace MyPlugin\ExamplePaymentMethodImplementation;

/**
 * Payment Method Retrieval Example
 *
 * This script demonstrates how to retrieve available payment methods
 * from the Wallee Portal for a specific space, with their
 * titles and descriptions in each of the shop's languages.
 *
 * USAGE:
 * php retrieval.php
 */

error_reporting(E_ALL & ~E_DEPRECATED);

// Load common bootstrap which handles autoloading and shared helpers (SimpleLogger, EnvSettingsProvider)
$components = require_once __DIR__ . '/../Common/bootstrap.php';

use Wallee\PluginCore\PaymentMethod\PaymentMethod;
use Wallee\PluginCore\PaymentMethod\PaymentMethodRepositoryInterface;
use Wallee\PluginCore\PaymentMethod\PaymentMethodService;
use Wallee\PluginCore\Sdk\WebServiceAPIV2\PaymentMethodGateway;

// 📖 Concept documentation: See docs/2-Checkout-Flow/PaymentMethod.md

// --- Helper Classes (Simulating the environment) ---

class SimpleRepository implements PaymentMethodRepositoryInterface
{
    public function getExistingExternalIds(int $spaceId): array
    {
        return [];
    }

    public function create(PaymentMethod $method, int $spaceId): void
    {
        // No-op for retrieval example
    }

    public function update(PaymentMethod $method, int $spaceId): void
    {
        // No-op for retrieval example
    }

    public function deactivateByExternalId(int $externalId, int $spaceId): void
    {
        // No-op for retrieval example
    }
}

// --- Main Execution ---

$spaceId = $components['spaceId'];
$logger = $components['logger'];
$sdkProvider = $components['sdk_provider'];

$gateway = new PaymentMethodGateway($sdkProvider, $logger);
$repository = new SimpleRepository();

$service = new PaymentMethodService($gateway, $repository, $logger);

// The locales your shop displays, as `de_DE` or `de-DE`. Each request returns
// titles and descriptions in one language only, so without this list every
// payment method comes back in English. Each locale adds one more search.
$shopLocales = ['de_DE', 'fr_FR', 'en_US'];

echo "Starting Payment Method Retrieval in Space $spaceId...\n\n";

try {
    $paymentMethods = $service->getPaymentMethods($spaceId, null, $shopLocales);

    echo "Found " . count($paymentMethods) . " payment methods:\n";
    foreach ($paymentMethods as $paymentMethod) {
        $state = $paymentMethod->state->value;
        echo "- [ID: {$paymentMethod->id}] {$paymentMethod->title->getDefault()} (State: $state)\n";
        foreach ($shopLocales as $locale) {
            // localize() falls back to another language when this one has no translation.
            echo "  [$locale] Title: " . ($paymentMethod->title->localize($locale) ?? '') . "\n";
            $description = $paymentMethod->description->localize($locale);
            if ($description !== null && $description !== '') {
                echo "  [$locale] Description: {$description}\n";
            }
        }
        echo "  Image Path: " . $paymentMethod->getRelativeImagePath() . "\n";
    }
} catch (\Exception $e) {
    exit("FAILED: " . $e->getMessage() . "\n");
}

echo "\nFinished Successfully!\n";
