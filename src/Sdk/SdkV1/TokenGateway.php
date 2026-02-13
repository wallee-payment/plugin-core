<?php

declare(strict_types=1);

namespace Wallee\PluginCore\Sdk\SdkV1;

use Wallee\PluginCore\Token\Token;
use Wallee\PluginCore\Token\TokenGatewayInterface;
use Wallee\PluginCore\Token\State as StateEnum;
use Wallee\Sdk\Service\TokenService as SdkTokenService;
use Wallee\Sdk\Model\Token as SdkToken;
use Wallee\PluginCore\Sdk\SdkProvider;
use Psr\Log\LoggerInterface;

/**
 * SDK implementation of the TokenGatewayInterface.
 */
class TokenGateway implements TokenGatewayInterface
{
    private SdkTokenService $tokenService;

    public function __construct(SdkProvider $sdkProvider, LoggerInterface $logger)
    {
        $this->tokenService = $sdkProvider->getService(SdkTokenService::class);
    }

    public function createToken(int $spaceId, int $transactionId): Token
    {
        $sdkToken = $this->tokenService->createToken($spaceId, $transactionId);
        return $this->mapToDomain($sdkToken);
    }

    private function mapToDomain(SdkToken $sdkToken): Token
    {
        $token = new Token();
        $token->id = $sdkToken->getId();
        $token->spaceId = $sdkToken->getLinkedSpaceId();
        $token->version = $sdkToken->getVersion();

        // Map State
        $stateString = (string)$sdkToken->getState();
        $token->state = StateEnum::tryFrom($stateString) ?? StateEnum::ACTIVE; // Fallback to ACTIVE if unknown

        return $token;
    }
}
