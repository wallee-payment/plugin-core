<?php

declare(strict_types=1);

namespace Wallee\PluginCore\Webhook\Command;

use Wallee\PluginCore\Log\LoggerInterface;
use Wallee\PluginCore\Webhook\Exception\CommandException;
use Wallee\PluginCore\Webhook\WebhookContext;

/**
 * An abstract base class for webhook commands.
 *
 * Client plugins should extend this class to implement the specific logic
 * required for a webhook, such as updating an order in the database or
 * sending a confirmation email.
 */
abstract class WebhookCommand implements WebhookCommandInterface
{
    public function __construct(
        protected readonly WebhookContext $context,
        protected readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Executes the command's domain-specific logic.
     *
     * This method must be implemented by the concrete command class in the client plugin.
     * It should contain the business logic that needs to run when the webhook is received.
     *
     * @return mixed
     * @throws CommandException On failure.
     */
    abstract public function execute(): mixed;
}
