<?php

declare(strict_types=1);

namespace Wallee\PluginCore\Transaction\Void;

use Wallee\PluginCore\Localization\LocalizedString;
use Wallee\PluginCore\SharedKernel\JsonStringableTrait;

/**
 * Domain object representing a Transaction Void.
 *
 * This is a pure PHP object with no SDK dependencies.
 */
class TransactionVoid
{
    use JsonStringableTrait;

    /**
     * @var LocalizedString|null The localized failure reason from the API.
     */
    public ?LocalizedString $failureReason = null;

    /**
     * @var State The void state.
     */
    public State $state;
}
