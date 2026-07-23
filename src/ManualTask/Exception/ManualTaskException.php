<?php

declare(strict_types=1);

namespace Wallee\PluginCore\ManualTask\Exception;

use Wallee\PluginCore\SharedKernel\AbstractDomainException;

/**
 * Thrown when a manual task operation fails at the API or transport level.
 */
class ManualTaskException extends AbstractDomainException
{
}
