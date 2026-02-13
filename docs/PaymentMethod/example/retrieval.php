<?php

namespace MyPlugin\ExamplePaymentMethodImplementation;

/**
 * Payment Method Retrieval Example
 * 
 * This script demonstrates how to retrieve available payment methods
 * from the Portal for a specific space.
 * 
 * USAGE:
 * php retrieval.php
 */

error_reporting(E_ALL & ~E_DEPRECATED);

require_once __DIR__ . '/../../../vendor/autoload.php';
// Assuming these helper classes exist in the example/src directory relative to Webhook
// We need to adjust paths or assume a common bootstrap.
// For this example, we will assume we are in docs/PaymentMethod/example
// and we can access the project root.

// We will replicate the setup from the Webhook example but adapted for this location.
// Ideally, we should have a common bootstrap, but we'll include what's necessary here.

// Mocking/Using SimpleLogger and EnvSettingsProvider from the Webhook example content
// to keep consistency if they are available project-wide, otherwise definitions are needed.
// Checking the file structure, they seem to be local to the webhook example. 
// I will define simple inline classes for the example to be standalone or use if they are in a shared dev/ path.
// Based on the user instruction "get inspiration", I will implement minimal versions here if I can't find shared ones.
// However, looking at the previous view_file of webhook.php, it required:
// require_once __DIR__ . '/src/EnvSettingsProvider.php';
// require_once __DIR__ . '/src/SimpleLogger.php';
// I should probably check if I can reuse them or if I should create them.
// For now, I'll create a simple logger and settings provider inline or use the ones from the project if available.
// Actually, to make it robust, I'll define them here to ensure it runs without external non-vendor dependencies.

use Wallee\PluginCore\Sdk\SdkProvider;
use Wallee\PluginCore\Sdk\SdkV1\PaymentMethodGateway;
use Wallee\PluginCore\Settings\Settings;
use Wallee\PluginCore\Settings\SettingsProviderInterface;
use Wallee\PluginCore\Settings\IntegrationMode;
use Wallee\PluginCore\LineItem\RoundingStrategy;
use Wallee\PluginCore\Log\LoggerInterface;
use Wallee\PluginCore\PaymentMethod\PaymentMethodService;

use Wallee\PluginCore\PaymentMethod\PaymentMethodRepositoryInterface;

// --- Helper Classes (Simulating the environment) ---

class SimpleRepository implements PaymentMethodRepositoryInterface
{
    public function sync(int $spaceId, array $paymentMethods): void
    {
        echo "[REPOSITORY] Syncing " . count($paymentMethods) . " methods for space $spaceId (No-op)\n";
    }
}

class SimpleLogger implements LoggerInterface
{
    public function emergency(string|\Stringable $message, array $context = []): void
    {
        echo "[EMERGENCY] $message\n";
    }
    public function alert(string|\Stringable $message, array $context = []): void
    {
        echo "[ALERT] $message\n";
    }
    public function critical(string|\Stringable $message, array $context = []): void
    {
        echo "[CRITICAL] $message\n";
    }
    public function error(string|\Stringable $message, array $context = []): void
    {
        echo "[ERROR] $message\n";
    }
    public function warning(string|\Stringable $message, array $context = []): void
    {
        echo "[WARNING] $message\n";
    }
    public function notice(string|\Stringable $message, array $context = []): void
    {
        echo "[NOTICE] $message\n";
    }
    public function info(string|\Stringable $message, array $context = []): void
    {
        echo "[INFO] $message\n";
    }
    public function debug(string|\Stringable $message, array $context = []): void
    { /* echo "[DEBUG] $message\n"; */
    }
    public function log($level, string|\Stringable $message, array $context = []): void
    {
        echo "[$level] $message\n";
    }
    public function __toString(): string
    {
        return 'SimpleLogger';
    }
}

class EnvSettingsProvider implements SettingsProviderInterface
{
    public function getSpaceId(): ?int
    {
        return (int)getenv('PLUGINCORE_DEMO_SPACE_ID') ?: null;
    }
    public function getUserId(): ?int
    {
        return (int)getenv('PLUGINCORE_DEMO_USER_ID') ?: null;
    }
    public function getApiKey(): ?string
    {
        return getenv('PLUGINCORE_DEMO_API_SECRET') ?: null;
    }
    public function getLogLevel(): ?string
    {
        return 'INFO';
    }
    public function getLineItemConsistencyEnabled(): ?bool
    {
        return true;
    }
    public function getLineItemRoundingStrategy(): ?RoundingStrategy
    {
        return RoundingStrategy::BY_LINE_ITEM;
    }
    public function getIntegrationMode(): IntegrationMode
    {
        return IntegrationMode::PAYMENT_PAGE;
    }
    public function getBaseUrl(): ?string
    {
        return getenv('PLUGINCORE_DEMO_BASE_URL') ?: null;
    }
}

// --- Main Execution ---

// 1. Credentials
$spaceId = getenv('PLUGINCORE_DEMO_SPACE_ID');
$userId = getenv('PLUGINCORE_DEMO_USER_ID');
$apiSecret = getenv('PLUGINCORE_DEMO_API_SECRET');

if (!$spaceId || !$userId || !$apiSecret) {
    exit("ERROR: Missing Credentials (PLUGINCORE_DEMO_SPACE_ID, PLUGINCORE_DEMO_USER_ID, PLUGINCORE_DEMO_API_SECRET).\n");
}

$spaceId = (int)$spaceId;

// 2. Setup Services
$logger = new SimpleLogger();
$repository = new SimpleRepository();
$settingsProvider = new EnvSettingsProvider();
$settings = new Settings($settingsProvider);

$sdkProvider = new SdkProvider($settings);
$gateway = new PaymentMethodGateway($sdkProvider, $logger);

$service = new PaymentMethodService($gateway, $repository, $logger);

echo "Starting Payment Method Retrieval in Space $spaceId...\n\n";

try {
    $paymentMethods = $service->getPaymentMethods($spaceId);

    echo "Found " . count($paymentMethods) . " payment methods:\n";
    foreach ($paymentMethods as $paymentMethod) {
        $state = $paymentMethod->state;
        echo "- [ID: {$paymentMethod->id}] {$paymentMethod->name} (State: $state)\n";
        if ($paymentMethod->description) {
            echo "  Description: {$paymentMethod->description}\n";
        }
    }
} catch (\Exception $e) {
    exit("FAILED: " . $e->getMessage() . "\n");
}

echo "\nFinished Successfully!\n";
