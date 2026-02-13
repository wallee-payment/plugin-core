<?php

declare(strict_types=1);

namespace Wallee\PluginCore\Tests\Sdk\SdkV1;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Wallee\PluginCore\Log\LoggerInterface;
use Wallee\PluginCore\Sdk\SdkProvider;
use Wallee\PluginCore\Sdk\SdkV1\WebhookSignatureGateway;
use Wallee\Sdk\Service\WebhookEncryptionService as SdkWebhookEncryptionService;

class WebhookSignatureGatewayTest extends TestCase
{
    private WebhookSignatureGateway $gateway;
    private MockObject|SdkProvider $sdkProvider;
    private MockObject|LoggerInterface $logger;
    private MockObject|SdkWebhookEncryptionService $encryptionService;

    protected function setUp(): void
    {
        $this->sdkProvider = $this->createMock(SdkProvider::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->encryptionService = $this->createMock(SdkWebhookEncryptionService::class);

        $this->sdkProvider->method('getService')
            ->with(SdkWebhookEncryptionService::class)
            ->willReturn($this->encryptionService);

        $this->gateway = new WebhookSignatureGateway($this->sdkProvider, $this->logger);
    }

    public function testValidateReturnsTrueForValidSignature(): void
    {
        $header = 'valid-sig';
        $payload = 'data';

        $this->encryptionService->expects($this->once())
            ->method('isContentValid')
            ->with($header, $payload)
            ->willReturn(true);

        $this->assertTrue($this->gateway->validate($header, $payload));
    }

    public function testValidateReturnsFalseForInvalidSignature(): void
    {
        $header = 'invalid-sig';
        $payload = 'data';

        $this->encryptionService->expects($this->once())
            ->method('isContentValid')
            ->with($header, $payload)
            ->willThrowException(new \Exception("Invalid signature"));

        $this->assertFalse($this->gateway->validate($header, $payload));
    }
}
