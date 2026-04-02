<?php

declare(strict_types=1);

namespace Wallee\PluginCore\Tests\Sdk\SdkV1;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Wallee\PluginCore\Sdk\SdkProvider;
use Wallee\PluginCore\Sdk\SdkV1\TokenGateway;
use Wallee\PluginCore\Token\Token;
use Wallee\Sdk\Model\CreationEntityState;
use Wallee\Sdk\Model\Token as SdkToken;
use Wallee\Sdk\Service\TokenService as SdkTokenService;

class TokenGatewayTest extends TestCase
{
    private TokenGateway $gateway;
    private MockObject|LoggerInterface $logger;
    private MockObject|SdkProvider $sdkProvider;
    private MockObject|SdkTokenService $tokenService;

    protected function setUp(): void
    {
        $this->sdkProvider = $this->createMock(SdkProvider::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->tokenService = $this->createMock(SdkTokenService::class);

        $this->sdkProvider->method('getService')
            ->with(SdkTokenService::class)
            ->willReturn($this->tokenService);

        $this->gateway = new TokenGateway($this->sdkProvider, $this->logger);
    }

    public function testCreateTokenReturnsToken(): void
    {
        $spaceId = 1;
        $transactionId = 2;

        $sdkToken = new SdkToken();
        $sdkToken->setId(100);
        $sdkToken->setLinkedSpaceId($spaceId);
        $sdkToken->setVersion(1);
        $sdkToken->setState(CreationEntityState::ACTIVE);

        $this->tokenService->expects($this->once())
            ->method('createToken')
            ->with($spaceId, $transactionId)
            ->willReturn($sdkToken);

        $result = $this->gateway->createToken($spaceId, $transactionId);

        $this->assertInstanceOf(Token::class, $result);
        $this->assertEquals(100, $result->id);
        $this->assertEquals($spaceId, $result->spaceId);
        $this->assertEquals('ACTIVE', $result->state->value);
    }
}
