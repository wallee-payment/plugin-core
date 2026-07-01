<?php

declare(strict_types=1);

namespace Wallee\PluginCore\Webhook\Exception;

use Wallee\PluginCore\SharedKernel\AbstractDomainException;

/**
 * Thrown when webhook signature verification fails due to API or network errors.
 */
class WebhookSignatureValidationException extends AbstractDomainException
{
}
