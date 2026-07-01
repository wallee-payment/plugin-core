<?php

declare(strict_types=1);

namespace Wallee\PluginCore\Transaction\Exception;

use Wallee\PluginCore\Localization\LocalizedString;

/**
 * Thrown when transaction total calculation yields a negative value.
 */
class TransactionTotalNegativeException extends TransactionException
{
    public function __construct(
        ?LocalizedString $localizedMessage = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct(
            "Transaction total cannot be negative.",
            $localizedMessage ?? new LocalizedString("Transaction total cannot be negative."),
            $previous,
        );
    }
}
