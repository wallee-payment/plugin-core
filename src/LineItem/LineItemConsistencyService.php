<?php

declare(strict_types=1);

namespace Wallee\PluginCore\LineItem;

use Wallee\PluginCore\LineItem\Exception\LineItemConsistencyException;
use Wallee\PluginCore\Log\LoggerInterface;
use Wallee\PluginCore\Settings\Settings;
use Wallee\PluginCore\LineItem\RoundingStrategy as RoundingStrategyEnum;

class LineItemConsistencyService
{
    private const MAX_ALLOWED_DIFFERENCE = 0.10;
    private const ADJUSTMENT_SKU = 'rounding-adjustment';
    private const ADJUSTMENT_NAME = 'Rounding Adjustment';

    public function __construct(
        private readonly Settings $settings,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Calculates the sum of line items.
     *
     * @param LineItem[] $lineItems The line items.
     * @return float The sum.
     */
    private function calculateSum(array $lineItems): float
    {
        $strategy = $this->settings->getLineItemRoundingStrategy();

        $this->logger->debug("Calculating sum using strategy: " . $strategy->value);

        $sum = 0.0;
        foreach ($lineItems as $item) {
            if ($strategy === RoundingStrategyEnum::BY_LINE_ITEM) {
                $sum += round($item->amountIncludingTax, 2);
            } else {
                $sum += $item->amountIncludingTax;
            }
        }

        $result = round($sum, 2);
        $this->logger->debug("Calculated total: $result");

        return $result;
    }


    /**
     * Ensures consistency between line items and expected total.
     *
     * @param LineItem[] $lineItems The line items.
     * @param float $expectedTotal The expected total.
     * @param string $currencyCode The currency code.
     * @return LineItem[] The consistent line items.
     * @throws LineItemConsistencyException If consistency cannot be ensured.
     */
    public function ensureConsistency(array $lineItems, float $expectedTotal, string $currencyCode): array
    {
        $calculatedTotal = $this->calculateSum($lineItems);
        $difference = $expectedTotal - $calculatedTotal;

        // 1. Perfect Match
        if (abs($difference) < 0.000001) {
            // Only log if you really need deep trace, otherwise this is too noisy
            // $this->logger->debug("Line item totals match expected grand total.");
            return $lineItems;
        }

        $this->logger->debug(sprintf(
            "Consistency Mismatch Detected: Shop Total: %f, Line Item Sum: %f, Diff: %f",
            $expectedTotal,
            $calculatedTotal,
            $difference,
        ));

        // 2. Feature Disabled?
        if (!$this->settings->isLineItemConsistencyEnabled()) {
            $msg = sprintf("Mismatch found (%f) but auto-correction is DISABLED.", $difference);
            $this->logger->warning($msg);
            throw new LineItemConsistencyException($msg);
        }

        // 3. Safety Guard
        if (abs($difference) > self::MAX_ALLOWED_DIFFERENCE) {
            $msg = sprintf("Rounding difference (%f) exceeds safety threshold (%f). Aborting.", $difference, self::MAX_ALLOWED_DIFFERENCE);
            $this->logger->error($msg);
            throw new LineItemConsistencyException($msg);
        }

        // 4. Fix it
        $this->logger->info("Auto-correcting rounding difference of $difference by adding adjustment line item.");

        $adjustmentItem = new LineItem();
        $adjustmentItem->uniqueId = self::ADJUSTMENT_SKU;
        $adjustmentItem->sku = self::ADJUSTMENT_SKU;
        $adjustmentItem->name = self::ADJUSTMENT_NAME;
        $adjustmentItem->quantity = 1;
        $adjustmentItem->amountIncludingTax = round($difference, 2);
        $adjustmentItem->type = LineItem::TYPE_FEE;
        $adjustmentItem->shippingRequired = false;

        $lineItems[] = $adjustmentItem;

        return $lineItems;
    }

    /**
     * Sanitizes negative line items of type DISCOUNT if the total sum is negative.
     * It adjusts the DISCOUNT items proportionally to make the total exactly zero.
     *
     * @param LineItem[] $lineItems
     * @return LineItem[]
     */
    public function sanitizeNegativeLineItems(array $lineItems): array
    {
        $totalSum = 0.0;
        $discountSum = 0.0;

        foreach ($lineItems as $item) {
            $totalSum += $item->amountIncludingTax;
            if ($item->type === LineItem::TYPE_DISCOUNT && $item->amountIncludingTax < 0) {
                $discountSum += $item->amountIncludingTax;
            }
        }

        // If total is non-negative (within float epsilon), nothing to do
        if ($totalSum >= -0.00000001) {
            return $lineItems;
        }

        // If no discounts found to heal, we can't do anything here
        if (abs($discountSum) < 0.00000001) {
            return $lineItems;
        }

        $this->logger->warning("Transaction total was negative. Auto-capped discounts to equal product value.");

        /**
         * Logic:
         * We want NewTotalSum = 0.
         * NewTotalSum = (TotalSum - DiscountSum) + NewDiscountSum.
         * 0 = (TotalSum - DiscountSum) + (DiscountSum * Factor).
         * -(TotalSum - DiscountSum) = DiscountSum * Factor.
         * Factor = (DiscountSum - TotalSum) / DiscountSum.
         */
        $factor = ($discountSum - $totalSum) / $discountSum;

        $sanitizedItems = [];
        foreach ($lineItems as $item) {
            $cloned = clone $item;
            if ($cloned->type === LineItem::TYPE_DISCOUNT && $cloned->amountIncludingTax < 0) {
                $cloned->amountIncludingTax = round($cloned->amountIncludingTax * $factor, 8);
            }
            $sanitizedItems[] = $cloned;
        }

        return $sanitizedItems;
    }
}
