<?php

declare(strict_types=1);

namespace Wallee\PluginCore\Webhook\Listener;

use Wallee\PluginCore\Webhook\Command\WebhookCommandInterface;
use Wallee\PluginCore\Webhook\WebhookContext;

/**
 * Defines the contract for a webhook listener.
 * A listener is responsible for identifying if it should handle a
 * specific webhook and providing the corresponding command.
 */
interface WebhookListenerInterface
{
    /**
     * Returns the command to be executed for the webhook.
     *
     * This method should only be called after supports() has returned true.
     *
     * @return WebhookCommandInterface The command to execute.
     */
    public function getCommand(WebhookContext $context): WebhookCommandInterface;
}
