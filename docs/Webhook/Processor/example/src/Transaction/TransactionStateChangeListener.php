<?php

declare(strict_types=1);

namespace MyPlugin\ExampleWebhookImplementation\Transaction;

use Wallee\PluginCore\Log\LoggerInterface;
use Wallee\PluginCore\Webhook\Command\WebhookCommandInterface;
use Wallee\PluginCore\Webhook\Listener\WebhookListenerInterface;
use Wallee\PluginCore\Webhook\WebhookContext;

class TransactionStateChangeListener implements WebhookListenerInterface
{
    public function __construct(private readonly LoggerInterface $logger)
    {}

    public function getCommand(WebhookContext $context): WebhookCommandInterface
    {
        return new UpdateTransactionStateCommand($context, $this->logger);
    }
}
