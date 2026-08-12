<?php

declare(strict_types=1);

namespace Wallee\PluginCore\DeliveryIndication\Exception;

use Wallee\PluginCore\SharedKernel\AbstractDomainException;

/**
 * Thrown when a delivery indication operation fails at the API or transport level.
 */
class DeliveryIndicationException extends AbstractDomainException
{
}
