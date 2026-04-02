<?php

declare(strict_types=1);

namespace Wallee\PluginCore\Webhook;

use Wallee\PluginCore\Render\JsonStringableTrait;

/**
 * An immutable value object that holds the context of a webhook event.
 */
class WebhookContext
{
    use JsonStringableTrait;

    public function __construct(
        public readonly string $remoteState,
        public readonly ?string $lastProcessedState,
        public readonly int $entityId,
        public readonly int $spaceId,
    ) {
    }
}
