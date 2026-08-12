<?php

declare(strict_types=1);

namespace Wallee\PluginCore\Charge\Exception;

use Wallee\PluginCore\SharedKernel\AbstractDomainException;

/**
 * Thrown when a charge operation fails at the API or transport level.
 *
 * Covers both halves of the domain: applying a charge flow, and reading the
 * charge attempt it produced. They share a gateway, a failure mode and a caller
 * response, so one exception type serves both.
 */
class ChargeException extends AbstractDomainException
{
}
