<?php

/**
 * Common Bootstrap for PluginCore Examples
 */

require_once __DIR__ . '/../../../vendor/autoload.php';

// Manually require shared classes since docs/ is likely not in composer autoload
// Also ensure the LoggerInterface is loaded early to prevent symbol mismatch
require_once __DIR__ . '/../../../src/Log/LoggerInterface.php';
require_once __DIR__ . '/../../../src/Transaction/TransactionPersistenceInterface.php';
require_once __DIR__ . '/SimpleLogger.php';
require_once __DIR__ . '/EnvSettingsProvider.php';
require_once __DIR__ . '/FilePersistence.php';
require_once __DIR__ . '/TransactionIdLoader.php';

use Wallee\PluginCore\Examples\Common\EnvSettingsProvider;
use Wallee\PluginCore\Examples\Common\FilePersistence;
use Wallee\PluginCore\Examples\Common\SimpleLogger;
use Wallee\PluginCore\Examples\Common\TransactionIdLoader;
use Wallee\PluginCore\Sdk\SdkProvider;
use Wallee\PluginCore\Settings\Settings;

// 1. Validate Environment
$required = ['PLUGINCORE_DEMO_SPACE_ID', 'PLUGINCORE_DEMO_USER_ID', 'PLUGINCORE_DEMO_API_SECRET'];
foreach ($required as $var) {
    if (!getenv($var)) {
        fwrite(STDERR, "ERROR: Missing environment variable $var\n");
        exit(1);
    }
}

$spaceId = (int)getenv('PLUGINCORE_DEMO_SPACE_ID');

// 2. Initialize Services
$logger = new SimpleLogger();
$settingsProvider = new EnvSettingsProvider();
$settings = new Settings($settingsProvider);
$sdkProvider = new SdkProvider($settings);

// 3. Initialize Helpers
// Using a session file relative to the calling script's location if needed, 
// but typically examples run from their own dir, so '.' session.json is fine.
$persistence = new FilePersistence('session.json');
$argLoader = new TransactionIdLoader($persistence);

// 4. Return initialized components
return [
    'sdkProvider' => $sdkProvider,
    'settings' => $settings,
    'logger' => $logger,
    'spaceId' => $spaceId,
    'persistence' => $persistence,
    'sdk_provider' => $sdkProvider,
    'settings_provider' => $settingsProvider
];
