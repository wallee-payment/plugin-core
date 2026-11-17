<?php

declare(strict_types=1);

namespace Wallee\PluginCore\Refund\Exception;

/**
 * Thrown when a refund's line item reductions do not add up to its total amount.
 *
 * Signals that strict line-item accounting is not possible for the request, for
 * example because an indivisible remainder cannot be spread over the units. The
 * refund service catches it and falls back to an amount-only refund; it does not
 * reach the caller.
 */
class RefundMathMismatchException extends RefundException
{
    protected bool $retryable = false;
}
