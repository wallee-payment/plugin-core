<?php

declare(strict_types=1);

namespace Wallee\PluginCore\Token\Exception;

use Wallee\PluginCore\SharedKernel\AbstractDomainException;

/**
 * Thrown when token creation fails at the API or transport level.
 */
class TokenException extends AbstractDomainException
{
}
