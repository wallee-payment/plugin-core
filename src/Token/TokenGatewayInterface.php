<?php

declare(strict_types=1);

namespace Wallee\PluginCore\Token;

/**
 * Interface for interacting with the Token mechanism.
 */
interface TokenGatewayInterface
{
    /**
     * Creates a new token for the given transaction.
     *
     * @param int $spaceId
     * @param int $transactionId
     * @return Token
     */
    public function createToken(int $spaceId, int $transactionId): Token;
}
