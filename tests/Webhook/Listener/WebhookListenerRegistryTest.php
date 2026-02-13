<?php

declare(strict_types=1);

namespace Wallee\PluginCore\Tests\Webhook\Listener;

use PHPUnit\Framework\TestCase;
use Wallee\PluginCore\Webhook\Enum\WebhookListener;
use Wallee\PluginCore\Webhook\Listener\WebhookListenerInterface;
use Wallee\PluginCore\Webhook\Listener\WebhookListenerRegistry;

class WebhookListenerRegistryTest extends TestCase
{
    public function testFindsCorrectListenerWhenOneMatches(): void
    {
        // --- Arrange ---
        $listenerToFind = $this->createMock(WebhookListenerInterface::class);

        $registry = new WebhookListenerRegistry();
        // Add the listener for a specific name and state
        $registry->addListener(WebhookListener::TRANSACTION, 'COMPLETED', $listenerToFind);

        // --- Act ---
        // Try to find the listener we just added
        $result = $registry->findListener(WebhookListener::TRANSACTION, 'COMPLETED');

        // --- Assert ---
        $this->assertSame($listenerToFind, $result);
    }

    public function testReturnsNullWhenNoListenerMatches(): void
    {
        // --- Arrange ---
        $registry = new WebhookListenerRegistry();
        // (We don't add any listeners)

        // --- Act ---
        // Search for a listener that hasn't been registered
        $result = $registry->findListener(WebhookListener::REFUND, 'SUCCESSFUL');

        // --- Assert ---
        $this->assertNull($result);
    }
}
