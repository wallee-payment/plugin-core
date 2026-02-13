<?php

declare(strict_types=1);

namespace Wallee\PluginCore\Sdk;

use Wallee\PluginCore\Settings\Settings;
use Wallee\Sdk\ApiClient;

class SdkProvider
{
    private ApiClient $apiClient;
    private int $spaceId;
    private array $serviceInstances = [];

    public function __construct(Settings $settings)
    {
        // Use the getter methods to retrieve the values
        $this->apiClient = new ApiClient($settings->getUserId(), $settings->getApiKey());
        $this->spaceId = $settings->getSpaceId();
    }

    /**
     * Gets or creates an instance of the requested SDK service.
     * @template T of object
     * @param class-string<T> $serviceClass
     * @return T
     */
    public function getService(string $serviceClass): object
    {
        if (!isset($this->serviceInstances[$serviceClass])) {
            if (!class_exists($serviceClass) || !method_exists($serviceClass, '__construct')) {
                throw new \InvalidArgumentException("Invalid SDK service class provided: {$serviceClass}");
            }
            $this->serviceInstances[$serviceClass] = new $serviceClass($this->apiClient);
        }
        return $this->serviceInstances[$serviceClass];
    }

    /**
     * Gets the configured Space ID.
     */
    public function getSpaceId(): int
    {
        return $this->spaceId;
    }
}
