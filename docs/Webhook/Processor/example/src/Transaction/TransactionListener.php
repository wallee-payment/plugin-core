<?php
declare(strict_types=1);
namespace MyPlugin\ExampleWebhookImplementation\Transaction;

use Wallee\PluginCore\Webhook\Listener\WebhookListenerInterface;
use Wallee\PluginCore\Webhook\Command\WebhookCommandInterface;
use Wallee\PluginCore\Webhook\WebhookContext;
use Wallee\PluginCore\Log\LoggerInterface;
use Wallee\PluginCore\Transaction\State;

class TransactionListener implements WebhookListenerInterface {
    public function __construct(private readonly LoggerInterface $logger) {}

    public function getCommand(WebhookContext $context): WebhookCommandInterface {
        // Route to specific commands based on state
        return match ($context->remoteState) {
            State::AUTHORIZED->value => new AuthorizedCommand($context, $this->logger),
            State::FULFILL->value    => new FulfillCommand($context, $this->logger),
            default                  => new GenericCommand($context, $this->logger),
        };
    }
}
