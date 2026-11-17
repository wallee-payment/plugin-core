<?php

declare(strict_types=1);

namespace Wallee\PluginCore\Tests\Refund;

use PHPUnit\Framework\TestCase;
use Wallee\PluginCore\Refund\LineItem\RefundLineItem;
use Wallee\PluginCore\Refund\LineItem\RefundLineItemCollection;
use Wallee\PluginCore\Refund\RefundContext;
use Wallee\PluginCore\Refund\Type;

class RefundContextTest extends TestCase
{
    public function testToString(): void
    {
        $context = new RefundContext(
            1001,
            10.50,
            'REF-001-REQ',
            Type::MERCHANT_INITIATED_ONLINE,
            new RefundLineItemCollection(new RefundLineItem('ITEM-1', 1.0, 10.50)),
        );

        $json = (string) $context;
        $this->assertJson($json);
        $decoded = json_decode($json, true);

        $this->assertEquals(1001, $decoded['transactionId']);
        $this->assertEquals(10.50, $decoded['amount']);
        $this->assertEquals('REF-001-REQ', $decoded['merchantReference']);
        $this->assertArrayHasKey('type', $decoded);
        $this->assertEquals(
            [['uniqueId' => 'ITEM-1', 'returnedQuantity' => 1.0, 'unitPriceReduction' => 10.50]],
            $decoded['lineItems'],
        );
    }

    public function testWithoutLineItemsReturnsAnAmountOnlyCopy(): void
    {
        $context = new RefundContext(
            1001,
            10.50,
            'REF-001-REQ',
            Type::MERCHANT_INITIATED_ONLINE,
            new RefundLineItemCollection(new RefundLineItem('ITEM-1', 1.0, 10.50)),
            'external-1',
        );

        $stripped = $context->withoutLineItems();

        $this->assertNotSame($context, $stripped);
        $this->assertTrue($stripped->lineItems->isEmpty());
        $this->assertSame(1001, $stripped->transactionId);
        $this->assertSame(10.50, $stripped->amount);
        $this->assertSame('REF-001-REQ', $stripped->merchantReference);
        $this->assertSame(Type::MERCHANT_INITIATED_ONLINE, $stripped->type);
        $this->assertSame('external-1', $stripped->externalId);
        // The original is left untouched.
        $this->assertCount(1, $context->lineItems);
    }
}
