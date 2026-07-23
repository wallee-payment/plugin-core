<?php

declare(strict_types=1);

namespace Wallee\PluginCore\Transaction\Invoice\Exception;

use Wallee\PluginCore\SharedKernel\AbstractDomainException;

/**
 * Base exception for errors that occur while reading transaction invoices.
 */
class InvoiceException extends AbstractDomainException
{
}
