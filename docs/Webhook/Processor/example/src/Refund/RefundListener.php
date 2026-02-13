<?php
declare(strict_types=1);
namespace MyPlugin\ExampleWebhookImplementation\Refund;
use Wallee\PluginCore\Webhook\Listener\WebhookListenerInterface;
use Wallee\PluginCore\Webhook\Command\WebhookCommandInterface;
use Wallee\PluginCore\Webhook\WebhookContext;
use Wallee\PluginCore\Log\LoggerInterface;

class RefundListener implements WebhookListenerInterface {
    public function __construct(private readonly LoggerInterface $logger) {}
    public function getCommand(WebhookContext $context): WebhookCommandInterface {
        return new SuccessfulCommand($context, $this->logger);
    }
}
