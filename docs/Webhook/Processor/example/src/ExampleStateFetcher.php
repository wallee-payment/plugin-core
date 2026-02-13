<?php

declare(strict_types=1);

namespace MyPlugin\ExampleWebhookImplementation;

use Wallee\PluginCore\Http\Request;
use Wallee\PluginCore\Webhook\StateFetcherInterface;

/**
 * An example State Fetcher for testing purposes.
 * It reads the state directly from the request payload.
 */
class ExampleStateFetcher implements StateFetcherInterface
{
    public function fetchState(Request $request, int $entityId): string
    {
        echo "StateFetcher: Reading 'state' directly from request payload.\n";
        $state = $request->get('state');
        if (empty($state)) {
            throw new \InvalidArgumentException("For this example, the 'state' must be in the request.");
        }
        return $state;
    }
}
