<?php

declare(strict_types=1);

namespace Wallee\PluginCore\Webhook\Exception;

use Wallee\PluginCore\SharedKernel\AbstractDomainException;

/**
 * Thrown when a webhook processing step is skipped intentionally.
 */
class SkippedStepException extends AbstractDomainException
{
}
