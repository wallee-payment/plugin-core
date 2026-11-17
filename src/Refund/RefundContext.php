<?php

declare(strict_types=1);

namespace Wallee\PluginCore\Refund;

use Wallee\PluginCore\Refund\LineItem\RefundLineItemCollection;
use Wallee\PluginCore\SharedKernel\JsonStringableTrait;

/**
 * The standardized input required to create a refund.
 */
class RefundContext
{
    use JsonStringableTrait;

    /**
     * @param int $transactionId
     * @param float $amount
     * @param string $merchantReference
     * @param Type $type
     * @param RefundLineItemCollection $lineItems Optional list of line item reductions.
     *                         NOTE: {@see RefundLineItem::$unitPriceReduction} is the Unit Price Reduction per
     *                         remaining item, NOT the total reduction amount.
     *                         See docs/3-Post-Payment/Refund.md for calculation formula.
     */
    public function __construct(
        public readonly int $transactionId,
        public readonly float $amount,
        public readonly string $merchantReference,
        public readonly Type $type,
        public readonly RefundLineItemCollection $lineItems = new RefundLineItemCollection(),
        public ?string $externalId = null,
    ) {
    }

    /**
     * Returns a copy of this context with every line item removed.
     *
     * Turns a line-item refund into an amount-only refund for the same total: the
     * transaction, amount, merchant reference, type and external ID are kept.
     */
    public function withoutLineItems(): self
    {
        return new self(
            transactionId: $this->transactionId,
            amount: $this->amount,
            merchantReference: $this->merchantReference,
            type: $this->type,
            lineItems: new RefundLineItemCollection(),
            externalId: $this->externalId,
        );
    }
}
