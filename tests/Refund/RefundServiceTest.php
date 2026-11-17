<?php

declare(strict_types=1);

namespace Wallee\PluginCore\Tests\Refund;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Wallee\PluginCore\LineItem\LineItem;
use Wallee\PluginCore\LineItem\LineItemCollection;
use Wallee\PluginCore\Log\LoggerInterface;
use Wallee\PluginCore\Refund\Exception\InvalidRefundException;
use Wallee\PluginCore\Refund\Exception\RefundException;
use Wallee\PluginCore\Refund\LineItem\RefundLineItem;
use Wallee\PluginCore\Refund\LineItem\RefundLineItemCollection;
use Wallee\PluginCore\Refund\Refund;
use Wallee\PluginCore\Refund\RefundCollection;
use Wallee\PluginCore\Refund\RefundContext;
use Wallee\PluginCore\Refund\RefundGatewayInterface;
use Wallee\PluginCore\Refund\RefundService;
use Wallee\PluginCore\Refund\State as StateEnum;
use Wallee\PluginCore\Refund\Type as TypeEnum;
use Wallee\PluginCore\Transaction\Transaction;
use Wallee\PluginCore\Transaction\TransactionService;

class RefundServiceTest extends TestCase
{
    private MockObject|RefundGatewayInterface $gateway;
    private MockObject|TransactionService $transactionService;
    private MockObject|LoggerInterface $logger;
    private RefundService $service;

    protected function setUp(): void
    {
        $this->gateway = $this->createMock(RefundGatewayInterface::class);
        $this->transactionService = $this->createMock(TransactionService::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->service = new RefundService(
            $this->gateway,
            $this->transactionService,
            $this->logger,
        );
    }

    public function testValidateRefundAmountExceedsTotal(): void
    {
        $spaceId = 1;
        $transactionId = 123;

        $transaction = new Transaction();
        $transaction->id = $transactionId;
        $transaction->authorizedAmount = 100.00;
        $transaction->refundedAmount = 0.00;

        $this->transactionService->method('getTransaction')
            ->willReturn($transaction);

        $context = new RefundContext(
            transactionId: $transactionId,
            amount: 150.00, // Exceeds 100
            merchantReference: 'ref-1',
            type: TypeEnum::MERCHANT_INITIATED_ONLINE,
        );

        $this->expectException(InvalidRefundException::class);
        $this->expectExceptionMessage("Refund amount 150 exceeds the remaining authorized amount 100 for transaction 123.");

        $this->service->createRefund($spaceId, $context);
    }

    public function testValidateRefundLineItemExceedsOriginal(): void
    {
        $spaceId = 1;
        $transactionId = 123;

        $itemA = new LineItem();
        $itemA->uniqueId = 'item-a';
        $itemA->quantity = 1;
        $itemA->amountIncludingTax = 50.00;
        $itemA->unitPriceIncludingTax = 50.00;

        $transaction = new Transaction();
        $transaction->id = $transactionId;
        $transaction->authorizedAmount = 100.00;
        $transaction->lineItems = [$itemA];

        $this->transactionService->method('getTransaction')
            ->willReturn($transaction);

        $context = new RefundContext(
            transactionId: $transactionId,
            amount: 60.00,
            merchantReference: 'ref-1',
            type: TypeEnum::MERCHANT_INITIATED_ONLINE,
            lineItems: new RefundLineItemCollection(
                new RefundLineItem('item-a', 1, 60.00), // Exceeds 50.00
            ),
        );

        $this->gateway->expects($this->never())->method('refund');

        $this->expectException(InvalidRefundException::class);
        $this->expectExceptionMessage("Consistency Error: Total provided refund amount (60.00) does not match the sum of line item reductions (50.00) for transaction 123.");

        // The reductions sum to 50.00 but the total claims 60.00: far more than a rounding remainder.
        $this->service->createRefund($spaceId, $context);
    }

    public function testSuccessfulFullRefund(): void
    {
        $spaceId = 1;
        $transactionId = 123;

        $transaction = new Transaction();
        $transaction->id = $transactionId;
        $transaction->authorizedAmount = 100.00;

        $this->transactionService->method('getTransaction')
            ->willReturn($transaction);

        $context = new RefundContext(
            transactionId: $transactionId,
            amount: 100.00,
            merchantReference: 'ref-full',
            type: TypeEnum::MERCHANT_INITIATED_ONLINE,
        );

        $expectedRefund = new Refund();
        $expectedRefund->id = 777;
        $expectedRefund->state = StateEnum::SUCCESSFUL;

        $this->gateway->expects($this->once())
            ->method('refund')
            ->with($spaceId, $context)
            ->willReturn($expectedRefund);

        $result = $this->service->createRefund($spaceId, $context);

        $this->assertSame($expectedRefund, $result);
        $this->assertEquals(StateEnum::SUCCESSFUL, $result->state);
    }

    public function testSuccessfulPartialRefund(): void
    {
        $spaceId = 1;
        $transactionId = 123;

        $transaction = new Transaction();
        $transaction->id = $transactionId;
        $transaction->authorizedAmount = 100.00;

        $this->transactionService->method('getTransaction')
            ->willReturn($transaction);

        $context = new RefundContext(
            transactionId: $transactionId,
            amount: 20.00,
            merchantReference: 'ref-partial',
            type: TypeEnum::MERCHANT_INITIATED_ONLINE,
        );

        $expectedRefund = new Refund();
        $expectedRefund->id = 778;
        $expectedRefund->amount = 20.00;
        $expectedRefund->state = StateEnum::SUCCESSFUL;

        $this->gateway->expects($this->once())
            ->method('refund')
            ->with($spaceId, $context)
            ->willReturn($expectedRefund);

        $result = $this->service->createRefund($spaceId, $context);

        $this->assertEquals(20.00, $result->amount);
    }

    public function testGatewayFailure(): void
    {
        $spaceId = 1;
        $transactionId = 123;

        $transaction = new Transaction();
        $transaction->id = $transactionId;
        $transaction->authorizedAmount = 100.00;

        $this->transactionService->method('getTransaction')
            ->willReturn($transaction);

        $context = new RefundContext(
            transactionId: $transactionId,
            amount: 50.00,
            merchantReference: 'ref-fail',
            type: TypeEnum::MERCHANT_INITIATED_ONLINE,
        );

        $this->gateway->expects($this->once())
            ->method('refund')
            ->willThrowException(new \Exception("SDK Error"));

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("SDK Error");

        $this->service->createRefund($spaceId, $context);
    }
    public function testValidateRefundItemTotalExceedsOriginal(): void
    {
        $spaceId = 1;
        $transactionId = 123;

        $itemA = new LineItem();
        $itemA->uniqueId = 'item-a';
        $itemA->quantity = 2;
        $itemA->amountIncludingTax = 50.00; // 25.00 each
        $itemA->unitPriceIncludingTax = 25.00;

        $transaction = new Transaction();
        $transaction->id = $transactionId;
        $transaction->authorizedAmount = 100.00;
        $transaction->lineItems = [$itemA];

        $this->transactionService->method('getTransaction')
            ->willReturn($transaction);

        $context = new RefundContext(
            transactionId: $transactionId,
            amount: 60.00,
            merchantReference: 'ref-1',
            type: TypeEnum::MERCHANT_INITIATED_ONLINE,
            lineItems: new RefundLineItemCollection(
                new RefundLineItem('item-a', 0, 30.00), // (0*25) + (2*30) = 60.00. Exceeds 50.00
            ),
        );

        $this->expectException(InvalidRefundException::class);
        $this->expectExceptionMessage("Refund amount 60.00 for item 'item-a' exceeds original item amount 50.00 in transaction 123.");

        $this->service->createRefund($spaceId, $context);
    }
    public function testListRefunds(): void
    {
        $spaceId = 1;
        $transactionId = 123;

        $refundA = new Refund();
        $refundA->id = 1;
        $refundA->amount = 10.00;

        $refundB = new Refund();
        $refundB->id = 2;
        $refundB->amount = 20.00;

        $expectedRefunds = [$refundA, $refundB];
        $expectedCollection = new RefundCollection(...$expectedRefunds, );

        $this->gateway->expects($this->once())
            ->method('findByTransaction')
            ->with($spaceId, $transactionId, )
            ->willReturn($expectedCollection);

        $result = $this->service->getRefunds($spaceId, $transactionId, );

        $this->assertCount(2, $result, );
        $this->assertSame($refundA, $result->all()[0], );
        $this->assertSame($refundB, $result->all()[1], );
    }

    public function testGetRefundableLineItemsFallsBackToOriginalCart(): void
    {
        $spaceId = 1;
        $transactionId = 123;

        $product = new LineItem();
        $product->uniqueId = 'product-1';
        $product->amountIncludingTax = 100.00;

        // Discounts and zero-amount items must be filtered out of the fallback.
        $discount = new LineItem();
        $discount->uniqueId = 'discount-1';
        $discount->type = LineItem::TYPE_DISCOUNT;
        $discount->amountIncludingTax = -20.00;

        $freeGift = new LineItem();
        $freeGift->uniqueId = 'gift-1';
        $freeGift->amountIncludingTax = 0.00;

        $transaction = new Transaction();
        $transaction->id = $transactionId;
        $transaction->lineItems = [$product, $discount, $freeGift];

        // No successful refunds exist, so the original cart is refundable.
        $this->gateway->expects($this->once())
            ->method('findByTransaction')
            ->with($spaceId, $transactionId)
            ->willReturn(new RefundCollection());

        $this->transactionService->expects($this->once())
            ->method('getTransaction')
            ->with($spaceId, $transactionId)
            ->willReturn($transaction);

        $result = $this->service->getRefundableLineItems($spaceId, $transactionId);

        $this->assertCount(1, $result);
        $this->assertSame($product, $result->first());
    }

    public function testGetRefundableLineItemsReturnsLatestSuccessfulReducedState(): void
    {
        $spaceId = 1;
        $transactionId = 123;

        $older = new Refund();
        $older->id = 1;
        $older->state = StateEnum::SUCCESSFUL;
        $older->createdOn = new \DateTimeImmutable('2026-01-01T10:00:00Z');
        $older->reducedLineItems = new LineItemCollection();

        $remainingItem = new LineItem();
        $remainingItem->uniqueId = 'product-1';
        $remainingItem->amountIncludingTax = 50.00;

        // A discount in the reduced state must be filtered out client-side.
        $remainingDiscount = new LineItem();
        $remainingDiscount->uniqueId = 'discount-1';
        $remainingDiscount->type = LineItem::TYPE_DISCOUNT;
        $remainingDiscount->amountIncludingTax = -10.00;

        $latest = new Refund();
        $latest->id = 2;
        $latest->state = StateEnum::SUCCESSFUL;
        $latest->createdOn = new \DateTimeImmutable('2026-01-02T10:00:00Z');
        $latest->reducedLineItems = new LineItemCollection($remainingItem, $remainingDiscount);

        // A newer but FAILED refund must not win.
        $failed = new Refund();
        $failed->id = 3;
        $failed->state = StateEnum::FAILED;
        $failed->createdOn = new \DateTimeImmutable('2026-01-03T10:00:00Z');

        $this->gateway->expects($this->once())
            ->method('findByTransaction')
            ->with($spaceId, $transactionId)
            ->willReturn(new RefundCollection($older, $latest, $failed));

        // The original transaction must not be fetched when a successful refund exists.
        $this->transactionService->expects($this->never())
            ->method('getTransaction');

        $result = $this->service->getRefundableLineItems($spaceId, $transactionId);

        $this->assertCount(1, $result);
        $this->assertSame($remainingItem, $result->first());
    }

    public function testGetRefundableLineItemsThrowsWhenLatestSuccessfulRefundReportsNoReducedState(): void
    {
        $spaceId = 1;
        $transactionId = 123;

        $refund = new Refund();
        $refund->id = 7;
        $refund->state = StateEnum::SUCCESSFUL;
        $refund->reducedLineItems = null;

        $this->gateway->method('findByTransaction')
            ->willReturn(new RefundCollection($refund));

        $this->transactionService->expects($this->never())->method('getTransaction');

        $this->expectException(RefundException::class);
        $this->expectExceptionMessage('Successful refund 7 of transaction 123 in space 1 reports no reduced line items.');

        $this->service->getRefundableLineItems($spaceId, $transactionId);
    }

    public function testGetRefundableLineItemsReturnsEmptyWhenNothingIsLeftToRefund(): void
    {
        $refund = new Refund();
        $refund->id = 8;
        $refund->state = StateEnum::SUCCESSFUL;
        $refund->reducedLineItems = new LineItemCollection();

        $this->gateway->method('findByTransaction')
            ->willReturn(new RefundCollection($refund));

        $this->assertCount(0, $this->service->getRefundableLineItems(1, 123));
    }

    public function testRefundFailsOnDiscountItem(): void
    {
        $spaceId = 1;
        $transactionId = 123;

        $discountItem = new LineItem();
        $discountItem->uniqueId = 'discount-1';
        $discountItem->type = LineItem::TYPE_DISCOUNT;
        $discountItem->amountIncludingTax = -10.00;
        $discountItem->quantity = 1;

        $transaction = new Transaction();
        $transaction->id = $transactionId;
        $transaction->authorizedAmount = 50.00;
        $transaction->lineItems = [$discountItem];

        $this->transactionService->method('getTransaction')->willReturn($transaction);

        $context = new RefundContext(
            transactionId: $transactionId,
            amount: 10.00,
            merchantReference: 'ref-fail',
            type: TypeEnum::MERCHANT_INITIATED_ONLINE,
            lineItems: new RefundLineItemCollection(
                new RefundLineItem('discount-1', 1, 10.00),
            ),
        );

        $this->expectException(InvalidRefundException::class);
        $this->expectExceptionMessage("Cannot refund line item 'discount-1'. Discounts cannot be refunded.");

        $this->service->createRefund($spaceId, $context);
    }

    public function testRefundFailsOnZeroAmountItem(): void
    {
        $spaceId = 1;
        $transactionId = 123;

        $freeItem = new LineItem();
        $freeItem->uniqueId = 'free-1';
        $freeItem->type = LineItem::TYPE_PRODUCT;
        $freeItem->amountIncludingTax = 0.00;
        $freeItem->quantity = 1;

        $transaction = new Transaction();
        $transaction->id = $transactionId;
        $transaction->authorizedAmount = 50.00;
        $transaction->lineItems = [$freeItem];

        $this->transactionService->method('getTransaction')->willReturn($transaction);

        $context = new RefundContext(
            transactionId: $transactionId,
            amount: 0.00,
            merchantReference: 'ref-fail',
            type: TypeEnum::MERCHANT_INITIATED_ONLINE,
            lineItems: new RefundLineItemCollection(
                new RefundLineItem('free-1', 1, 0.00),
            ),
        );

        $this->expectException(InvalidRefundException::class);
        $this->expectExceptionMessage("Cannot refund line item 'free-1'. Items with zero or negative amounts cannot be refunded.");

        $this->service->createRefund($spaceId, $context);
    }

    public function testFindByIdDelegatesToGatewayAndReturnsRefund(): void
    {
        $refund = new Refund();
        $refund->id = 555;
        $refund->transactionId = 123;

        $this->gateway->expects($this->once())
            ->method('findById')
            ->with(1, 555)
            ->willReturn($refund);

        $this->assertSame($refund, $this->service->findById(1, 555));
    }

    public function testFindByIdPropagatesGatewayException(): void
    {
        $this->gateway->method('findById')
            ->willThrowException(new RefundException('boom'));

        $this->expectException(RefundException::class);

        $this->service->findById(1, 555);
    }

    #[DataProvider('remainingAmountWithFloatNoiseProvider')]
    public function testRefundOfExactRemainingAmountIsNotRejectedByFloatPrecision(
        float $authorizedAmount,
        float $refundedAmount,
        float $refundAmount,
    ): void {
        $spaceId = 1;
        $transactionId = 123;

        $transaction = new Transaction();
        $transaction->id = $transactionId;
        $transaction->authorizedAmount = $authorizedAmount;
        $transaction->refundedAmount = $refundedAmount;

        $this->transactionService->method('getTransaction')
            ->willReturn($transaction);

        $context = new RefundContext(
            transactionId: $transactionId,
            amount: $refundAmount,
            merchantReference: 'ref-remaining',
            type: TypeEnum::MERCHANT_INITIATED_ONLINE,
        );

        $expectedRefund = new Refund();
        $expectedRefund->amount = $refundAmount;

        $this->gateway->expects($this->once())
            ->method('refund')
            ->willReturn($expectedRefund);

        $result = $this->service->createRefund($spaceId, $context);

        $this->assertEquals($refundAmount, $result->amount);
    }

    /**
     * @return array<string, array{float, float, float}>
     */
    public static function remainingAmountWithFloatNoiseProvider(): array
    {
        return [
            '115.57 - 28.43' => [115.57, 28.43, 87.14],
            '110.53 - 34.49' => [110.53, 34.49, 76.04],
        ];
    }

    private function kuwaitiDinarTransaction(int $transactionId, float $itemAmount): Transaction
    {
        $item = new LineItem();
        $item->uniqueId = 'item-a';
        $item->quantity = 1;
        $item->amountIncludingTax = $itemAmount;
        $item->unitPriceIncludingTax = $itemAmount;

        $transaction = new Transaction();
        $transaction->id = $transactionId;
        $transaction->currency = 'KWD';
        $transaction->authorizedAmount = 100.000;
        $transaction->refundedAmount = 0.0;
        $transaction->lineItems = [$item];

        return $transaction;
    }

    public function testRefundOfExactRemainingAmountIsAcceptedForThreeDecimalCurrency(): void
    {
        $transaction = new Transaction();
        $transaction->id = 123;
        $transaction->currency = 'KWD';
        $transaction->authorizedAmount = 115.570;
        $transaction->refundedAmount = 28.430;

        $this->transactionService->method('getTransaction')->willReturn($transaction);
        $this->gateway->expects($this->once())->method('refund')->willReturn(new Refund());

        $this->service->createRefund(1, new RefundContext(
            transactionId: 123,
            amount: 87.140,
            merchantReference: 'ref-kwd-remaining',
            type: TypeEnum::MERCHANT_INITIATED_ONLINE,
        ));
    }

    public function testLineItemReductionExceedingOriginalByOneSubunitIsRejectedForThreeDecimalCurrency(): void
    {
        $this->transactionService->method('getTransaction')
            ->willReturn($this->kuwaitiDinarTransaction(123, 10.000));
        $this->gateway->expects($this->never())->method('refund');

        $this->expectException(InvalidRefundException::class);
        $this->expectExceptionMessage("Refund amount 10.005 for item 'item-a' exceeds original item amount 10.000 in transaction 123.");

        // 10.005 KWD against a 10.000 KWD item: 5 fils too much.
        $this->service->createRefund(1, new RefundContext(
            transactionId: 123,
            amount: 10.005,
            merchantReference: 'ref-kwd-item',
            type: TypeEnum::MERCHANT_INITIATED_ONLINE,
            lineItems: new RefundLineItemCollection(
                new RefundLineItem('item-a', 0, 10.005),
            ),
        ));
    }

    public function testLineItemSumDifferingFromTotalByOneSubunitIsRejectedForThreeDecimalCurrency(): void
    {
        $this->transactionService->method('getTransaction')
            ->willReturn($this->kuwaitiDinarTransaction(123, 10.000));
        $this->gateway->expects($this->never())->method('refund');

        $this->expectException(InvalidRefundException::class);
        $this->expectExceptionMessage('Total provided refund amount (5.005) does not match the sum of line item reductions (5.000)');

        // Items reduce 5.000 KWD but the total claims 5.005 KWD: 5 fils apart.
        $this->service->createRefund(1, new RefundContext(
            transactionId: 123,
            amount: 5.005,
            merchantReference: 'ref-kwd-total',
            type: TypeEnum::MERCHANT_INITIATED_ONLINE,
            lineItems: new RefundLineItemCollection(
                new RefundLineItem('item-a', 0, 5.000),
            ),
        ));
    }

    public function testLineItemsAreKeptWhenReductionsMatchAmount(): void
    {
        $this->transactionService->method('getTransaction')
            ->willReturn($this->threeUnitTransaction(123));

        // 3 units x 0.33 = 0.99, exactly the requested amount.
        $context = new RefundContext(
            transactionId: 123,
            amount: 0.99,
            merchantReference: 'ref-exact',
            type: TypeEnum::MERCHANT_INITIATED_ONLINE,
            lineItems: new RefundLineItemCollection(
                new RefundLineItem('item-a', 0, 0.33),
            ),
        );

        $this->logger->expects($this->never())->method('warning');
        $this->gateway->expects($this->once())
            ->method('refund')
            ->with(1, $this->identicalTo($context))
            ->willReturn(new Refund());

        $this->service->createRefund(1, $context);
    }

    public function testFallsBackToAmountOnlyWhenReductionsDoNotMatchAmount(): void
    {
        $this->transactionService->method('getTransaction')
            ->willReturn($this->threeUnitTransaction(123));

        // 1.00 over 3 units cannot be split into whole-cent reductions: 3 x 0.33 = 0.99.
        $context = new RefundContext(
            transactionId: 123,
            amount: 1.00,
            merchantReference: 'ref-remainder',
            type: TypeEnum::MERCHANT_INITIATED_ONLINE,
            lineItems: new RefundLineItemCollection(
                new RefundLineItem('item-a', 0, 0.33),
            ),
            externalId: 'external-1',
        );

        $this->logger->expects($this->once())
            ->method('warning')
            ->with($this->stringContains('falling back to Amount-Only refund'));

        $expectedRefund = new Refund();
        $expectedRefund->id = 888;

        $this->gateway->expects($this->once())
            ->method('refund')
            ->with(1, $this->callback(
                static fn (RefundContext $sent): bool => $sent->lineItems->isEmpty()
                    && $sent->transactionId === 123
                    && $sent->amount === 1.00
                    && $sent->merchantReference === 'ref-remainder'
                    && $sent->type === TypeEnum::MERCHANT_INITIATED_ONLINE
                    && $sent->externalId === 'external-1',
            ))
            ->willReturn($expectedRefund);

        $this->assertSame($expectedRefund, $this->service->createRefund(1, $context));
    }

    /**
     * Each case builds one line item of $quantity units at $unitPrice and refunds it with a single
     * reduction. The reductions sum to ($returnedQuantity * $unitPrice) + (remaining * $unitPriceReduction),
     * and $amount is what the caller asks for. The tolerance is one smallest currency unit per remaining unit.
     */
    #[DataProvider('roundingToleranceProvider')]
    public function testRoundingTolerance(
        string $currency,
        int $quantity,
        float $unitPrice,
        float $returnedQuantity,
        float $unitPriceReduction,
        float $amount,
        string $expected,
    ): void {
        $this->transactionService->method('getTransaction')
            ->willReturn($this->transactionWithItem($quantity, $unitPrice, $currency));

        $context = new RefundContext(
            transactionId: 123,
            amount: $amount,
            merchantReference: 'ref-tolerance',
            type: TypeEnum::MERCHANT_INITIATED_ONLINE,
            lineItems: new RefundLineItemCollection(
                new RefundLineItem('item-a', $returnedQuantity, $unitPriceReduction),
            ),
        );

        if ($expected === 'rejected') {
            $this->gateway->expects($this->never())->method('refund');
            $this->expectException(InvalidRefundException::class);
            $this->expectExceptionMessage('Consistency Error');

            $this->service->createRefund(1, $context);

            return;
        }

        $this->gateway->expects($this->once())
            ->method('refund')
            ->with(1, $this->callback(
                static fn (RefundContext $sent): bool => $expected === 'kept'
                    ? $sent === $context
                    : $sent->lineItems->isEmpty() && $sent->amount === $amount,
            ))
            ->willReturn(new Refund());

        $this->service->createRefund(1, $context);
    }

    /**
     * @return array<string, array{string, int, float, float, float, float, string}>
     */
    public static function roundingToleranceProvider(): array
    {
        // currency, quantity, unit price, returned quantity, unit price reduction, amount, expected
        return [
            'exact match keeps the line items' => ['EUR', 3, 1.00, 0, 0.33, 0.99, 'kept'],
            'one cent over 3 units' => ['EUR', 3, 1.00, 0, 0.33, 1.00, 'fallback'],
            'reductions above the amount' => ['EUR', 3, 1.00, 0, 0.34, 1.00, 'fallback'],
            'at the tolerance of 3 units' => ['EUR', 3, 1.00, 0, 0.33, 1.02, 'fallback'],
            'five cents beyond 3 units' => ['EUR', 3, 1.00, 0, 0.33, 1.04, 'rejected'],
            'at the tolerance of 7 units' => ['EUR', 7, 1.00, 0, 0.14, 1.05, 'fallback'],
            'one cent past the tolerance of 7 units' => ['EUR', 7, 1.00, 0, 0.14, 1.06, 'rejected'],
            'returned unit leaves 3 units, within tolerance' => ['EUR', 4, 2.50, 1, 0.33, 3.52, 'fallback'],
            'returned unit leaves 3 units, beyond tolerance' => ['EUR', 4, 2.50, 1, 0.33, 3.53, 'rejected'],
            'all units returned leaves no tolerance' => ['EUR', 2, 5.00, 2, 0.0, 9.99, 'rejected'],
            'three-decimal currency within tolerance' => ['KWD', 3, 1.000, 0, 0.333, 1.000, 'fallback'],
            'three-decimal currency beyond tolerance' => ['KWD', 3, 1.000, 0, 0.333, 1.004, 'rejected'],
            'zero-decimal currency within tolerance' => ['JPY', 3, 100.0, 0, 33.0, 100.0, 'fallback'],
            'zero-decimal currency beyond tolerance' => ['JPY', 3, 100.0, 0, 33.0, 103.0, 'rejected'],
        ];
    }

    private function transactionWithItem(int $quantity, float $unitPrice, string $currency): Transaction
    {
        $item = new LineItem();
        $item->uniqueId = 'item-a';
        $item->quantity = $quantity;
        $item->amountIncludingTax = $quantity * $unitPrice;
        $item->unitPriceIncludingTax = $unitPrice;

        $transaction = new Transaction();
        $transaction->id = 123;
        $transaction->currency = $currency;
        $transaction->authorizedAmount = $quantity * $unitPrice;
        $transaction->lineItems = [$item];

        return $transaction;
    }

    public function testFallbackDoesNotBypassTheRemainingAmountCheck(): void
    {
        $this->transactionService->method('getTransaction')
            ->willReturn($this->threeUnitTransaction(123));
        $this->gateway->expects($this->never())->method('refund');

        $this->expectException(InvalidRefundException::class);

        // Mismatching reductions AND an amount above the 3.00 balance: still rejected.
        $this->service->createRefund(1, new RefundContext(
            transactionId: 123,
            amount: 3.01,
            merchantReference: 'ref-too-much',
            type: TypeEnum::MERCHANT_INITIATED_ONLINE,
            lineItems: new RefundLineItemCollection(
                new RefundLineItem('item-a', 0, 0.33),
            ),
        ));
    }

    private function threeUnitTransaction(int $transactionId): Transaction
    {
        $item = new LineItem();
        $item->uniqueId = 'item-a';
        $item->quantity = 3;
        $item->amountIncludingTax = 3.00;
        $item->unitPriceIncludingTax = 1.00;

        $transaction = new Transaction();
        $transaction->id = $transactionId;
        $transaction->authorizedAmount = 3.00;
        $transaction->lineItems = [$item];

        return $transaction;
    }
}
