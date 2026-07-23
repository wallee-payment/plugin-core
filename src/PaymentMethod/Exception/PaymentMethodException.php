<?php

declare(strict_types=1);

namespace Wallee\PluginCore\PaymentMethod\Exception;

use Wallee\PluginCore\SharedKernel\AbstractDomainException;

/**
 * Base exception for errors that occur while fetching payment methods.
 */
class PaymentMethodException extends AbstractDomainException
{
}
