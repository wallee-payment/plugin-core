<?php

declare(strict_types=1);

namespace Wallee\PluginCore\Refund;

use Wallee\PluginCore\Refund\Type as TypeEnum;

/**
 * Validated data for creating a refund.
 */
class RefundContext
{
    /**
     * @param int $transactionId
     * @param float $amount
     * @param string $merchantReference
     * @param TypeEnum $type
     * @param array $lineItems Optional list of line item reductions: [['uniqueId' => string, 'quantity' => float, 'amount' => float]].
     *                         NOTE: 'amount' is the Unit Price Reduction per remaining item, NOT the total reduction amount.
     *                         See docs/Refund/README.md for calculation formula.
     */
    public function __construct(
        public readonly int $transactionId,
        public readonly float $amount,
        public readonly string $merchantReference,
        public readonly TypeEnum $type,
        public readonly array $lineItems = [],
    ) {}
}
