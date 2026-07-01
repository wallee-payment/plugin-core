<?php

declare(strict_types=1);

namespace Wallee\PluginCore\Refund\Exception;

use Wallee\PluginCore\SharedKernel\AbstractDomainException;

/**
 * Thrown when a refund operation fails at the API or transport level.
 */
class RefundException extends AbstractDomainException
{
}
