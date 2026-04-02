<?php

declare(strict_types=1);

namespace Wallee\PluginCore\Transaction\Completion;

use Wallee\PluginCore\LineItem\LineItem;
use Wallee\PluginCore\Render\JsonStringableTrait;

/**
 * Domain object representing a Transaction Completion (capture).
 *
 * This is a pure PHP object with no SDK dependencies.
 */
class TransactionCompletion
{
    use JsonStringableTrait;

    /**
     * @var int The completion ID.
     */
    public int $id;

    /**
     * @var array<LineItem>|null The line items to capture (null for full capture).
     */
    public ?array $lineItems = null;

    /**
     * @var int The ID of the transaction being captured.
     */
    public int $linkedTransactionId;

    /**
     * @var State The completion state.
     */
    public State $state;
}
