<?php

declare(strict_types=1);

namespace MyPlugin\ExampleWebhookImplementation;

use Wallee\PluginCore\Webhook\WebhookSignatureGatewayInterface;

// 📖 Concept documentation: See docs/4-Background-Tasks/Webhook-Processor.md

/**
 * FOR LOCAL TESTING ONLY: accepts every signature.
 *
 * Lets this example feed mock payloads through the real DefaultStateFetcher
 * without a signing key. In a plugin, pass WebhookSignatureGateway instead,
 * so every signed payload is actually verified.
 */
class AcceptAllSignatureGateway implements WebhookSignatureGatewayInterface
{
    public function validate(string $signatureHeader, string $payload): bool
    {
        echo "SignatureGateway: Accepting signature without checking it (example only).\n";
        return true;
    }
}
