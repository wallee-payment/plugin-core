<?php

declare(strict_types=1);

namespace Wallee\PluginCore\Webhook\Exception;

use Wallee\PluginCore\SharedKernel\AbstractDomainException;

/**
 * Base exception for errors that occur while managing webhook URLs and listeners.
 */
class WebhookManagementException extends AbstractDomainException
{
}
