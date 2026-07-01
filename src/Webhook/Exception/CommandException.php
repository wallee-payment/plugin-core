<?php

declare(strict_types=1);

namespace Wallee\PluginCore\Webhook\Exception;

use Wallee\PluginCore\SharedKernel\AbstractDomainException;

/**
 * Base exception for errors that occur during a webhook command execution.
 */
class CommandException extends AbstractDomainException
{
}
