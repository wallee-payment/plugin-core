<?php

declare(strict_types=1);

namespace Wallee\PluginCore\Webhook\Exception;

/**
 * Thrown when a webhook processing step is skipped intentionally
 * (e.g., due to a race condition or idempotency check).
 */
class SkippedStepException extends \Exception
{
}
