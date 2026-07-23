<?php

declare(strict_types=1);

namespace Wallee\PluginCore\Sdk;

use Wallee\PluginCore\LineItem\LineItem;
use Wallee\Sdk\Model\LineItem as SdkLineItem;
use Wallee\Sdk\Model\LineItemType as SdkLineItemType;

/**
 * Shared SDK LineItem -> domain mapping, reused by the transaction and refund
 * gateways.
 */
trait LineItemMapperTrait
{
    /**
     * Maps an SDK LineItem to a domain LineItem.
     *
     * @param SdkLineItem $sdkItem
     * @return LineItem
     */
    protected function mapToLineItem(SdkLineItem $sdkItem): LineItem
    {
        $item = new LineItem();
        $item->uniqueId = $sdkItem->getUniqueId();
        $item->sku = $sdkItem->getSku();
        $item->name = $sdkItem->getName();
        $item->quantity = $sdkItem->getQuantity();
        $item->amountIncludingTax = $sdkItem->getAmountIncludingTax();
        $item->unitPriceIncludingTax = $sdkItem->getUnitPriceIncludingTax();
        $item->discountIncludingTax = $sdkItem->getDiscountIncludingTax();
        $item->type = match ($sdkItem->getType()) {
            SdkLineItemType::DISCOUNT => LineItem::TYPE_DISCOUNT,
            SdkLineItemType::SHIPPING => LineItem::TYPE_SHIPPING,
            SdkLineItemType::FEE => LineItem::TYPE_FEE,
            default => LineItem::TYPE_PRODUCT,
        };

        return $item;
    }
}
