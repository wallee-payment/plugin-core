<?php

namespace Wallee\PluginCore\Examples\Common;

use Wallee\PluginCore\Settings\DefaultSettingsProvider;
use Wallee\PluginCore\Settings\IntegrationMode;

class EnvSettingsProvider extends DefaultSettingsProvider
{
    public function getSpaceId(): ?int
    {
        $val = getenv('PLUGINCORE_DEMO_SPACE_ID');
        return $val ? (int)$val : null;
    }

    public function getUserId(): ?int
    {
        $val = getenv('PLUGINCORE_DEMO_USER_ID');
        return $val ? (int)$val : null;
    }

    public function getApiKey(): ?string
    {
        $val = getenv('PLUGINCORE_DEMO_API_SECRET');
        return $val ?: null;
    }

    public function getIntegrationMode(): IntegrationMode
    {
        $mode = getenv('PLUGINCORE_DEMO_INTEGRATION_MODE');

        return match ($mode) {
            'iframe' => IntegrationMode::IFRAME,
            'lightbox' => IntegrationMode::LIGHTBOX,
            default => IntegrationMode::PAYMENT_PAGE,
        };
    }
}
