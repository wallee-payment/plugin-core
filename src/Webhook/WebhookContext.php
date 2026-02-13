<?php

declare(strict_types=1);

namespace Wallee\PluginCore\Webhook;

/**
 * An immutable value object that holds the context of a webhook event.
 */
class WebhookContext
{
    public function __construct(
        public readonly string $remoteState,
        public readonly ?string $lastProcessedState,
        public readonly int $entityId,
        public readonly int $spaceId,
    ) {
    }
}
