<?php

declare(strict_types=1);

namespace Wallee\PluginCore\Tests\Sdk\WebServiceAPIV1;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Wallee\PluginCore\Log\LoggerInterface;
use Wallee\PluginCore\Sdk\SdkProvider;
use Wallee\PluginCore\Sdk\WebServiceAPIV1\TokenGateway;
use Wallee\PluginCore\Token\Exception\MissingTokenException;
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

        $this->gateway = new TokenGateway(
            $this->sdkProvider,
            $this->logger,
        );
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
        $sdkToken->setCustomerEmailAddress('customer@example.com');
        $sdkToken->setCreatedOn(new \DateTime('2026-06-19 12:00:00'));

        $this->tokenService->expects($this->once())
            ->method('createToken')
            ->with($spaceId, $transactionId)
            ->willReturn($sdkToken);

        $result = $this->gateway->createToken($spaceId, $transactionId);

        $this->assertInstanceOf(Token::class, $result);
        $this->assertEquals(100, $result->id);
        $this->assertEquals($spaceId, $result->spaceId);
        $this->assertEquals('ACTIVE', $result->state->value);
        $this->assertEquals('customer@example.com', $result->customerIdentifier);
        $this->assertInstanceOf(\DateTimeImmutable::class, $result->createdOn);
        $this->assertEquals('2026-06-19 12:00:00', $result->createdOn->format('Y-m-d H:i:s'));
    }

    public function testCreateTokenThrowsExceptionIfTokenIsMissing(): void
    {
        $spaceId = 1;
        $transactionId = 2;

        $this->tokenService->expects($this->once())
            ->method('createToken')
            ->with($spaceId, $transactionId)
            ->willReturn(null);

        $this->logger->expects($this->once())
            ->method('error')
            ->with($this->stringContains('Token creation failed'));

        $this->expectException(MissingTokenException::class);
        $this->gateway->createToken($spaceId, $transactionId);
    }
}
