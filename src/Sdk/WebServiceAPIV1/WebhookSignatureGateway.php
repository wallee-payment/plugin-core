<?php

declare(strict_types=1);

namespace Wallee\PluginCore\Sdk\WebServiceAPIV1;

use Wallee\PluginCore\Localization\LocalizedString;
use Wallee\PluginCore\Log\LoggerInterface;
use Wallee\PluginCore\Sdk\SdkProvider;
use Wallee\PluginCore\Webhook\Exception\WebhookSignatureValidationException;
use Wallee\PluginCore\Webhook\WebhookSignatureGatewayInterface;
use Wallee\Sdk\Service\WebhookEncryptionService as SdkWebhookEncryptionService;

/**
 * Class WebhookSignatureGateway
 *
 * Implementation of the WebhookSignatureGatewayInterface using the Wallee SDK.
 */
class WebhookSignatureGateway implements WebhookSignatureGatewayInterface
{
    /**
     * @var SdkWebhookEncryptionService
     */
    private SdkWebhookEncryptionService $webhookEncryptionService;

    /**
     * WebhookSignatureGateway constructor.
     *
     * @param SdkProvider $sdkProvider
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly SdkProvider $sdkProvider,
        private readonly LoggerInterface $logger,
    ) {
        $this->webhookEncryptionService = $this->sdkProvider->getService(SdkWebhookEncryptionService::class);
    }

    /**
     * Validates the payload signature.
     *
     * @param string $signatureHeader The signature string from the request headers.
     * @param string $payload The raw request body content.
     * @return bool True if the signature is valid, false otherwise.
     * @throws WebhookSignatureValidationException If signature validation fails due to key/API errors.
     */
    public function validate(string $signatureHeader, string $payload): bool
    {
        try {
            return (bool)$this->webhookEncryptionService->isContentValid($signatureHeader, $payload);
        } catch (\Exception $e) {
            // TODO: Include spaceId and transactionId in log context when available
            $this->logger->error(
                'Webhook signature validation failed: {errorMessage}',
                [
                    'errorMessage' => $e->getMessage(),
                    'exception' => $e,
                ],
            );
            throw new WebhookSignatureValidationException(
                "Webhook signature validation failed: " . $e->getMessage(),
                new LocalizedString("Webhook signature validation failed."),
                $e,
            );
        }
    }
}
