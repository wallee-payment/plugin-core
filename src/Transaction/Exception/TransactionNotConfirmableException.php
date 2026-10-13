<?php

declare(strict_types=1);

namespace Wallee\PluginCore\Transaction\Exception;

use Wallee\PluginCore\Localization\LocalizedString;
use Wallee\PluginCore\Transaction\State;

/**
 * Thrown when a transaction cannot be confirmed because it is no longer PENDING, for example
 * because it already failed or was voided.
 */
class TransactionNotConfirmableException extends TransactionException
{
    /**
     * @param State $state The state the transaction was found in.
     * @param string $technicalMessage The message for logs.
     * @param LocalizedString|null $localizedMessage The message that may be shown to the customer.
     */
    public function __construct(
        public readonly State $state,
        string $technicalMessage,
        ?LocalizedString $localizedMessage = null,
    ) {
        parent::__construct($technicalMessage, $localizedMessage);
    }
}
