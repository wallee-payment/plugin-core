<?php

declare(strict_types=1);

namespace Wallee\PluginCore\Transaction\Completion;

/**
 * Domain object representing a Transaction Completion (capture).
 * 
 * This is a pure PHP object with no SDK dependencies.
 */
class TransactionCompletion
{
    /**
     * @var int The completion ID.
     */
    public int $id;

    /**
     * @var int The ID of the transaction being captured.
     */
    public int $linkedTransactionId;

    /**
     * @var State The completion state.
     */
    public State $state;

    /**
     * @var array|null The line items to capture (null for full capture).
     */
    public ?array $lineItems = null;
}
