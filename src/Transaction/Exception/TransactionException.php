<?php

declare(strict_types=1);

namespace Wallee\PluginCore\Transaction\Exception;

use Wallee\PluginCore\SharedKernel\AbstractDomainException;

/**
 * Thrown when the transaction creation or update process fails at the logical or API level.
 */
class TransactionException extends AbstractDomainException
{
}
