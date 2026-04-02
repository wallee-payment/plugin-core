<?php

declare(strict_types=1);

namespace Wallee\PluginCore\Tests\Sdk\SdkV1;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Wallee\PluginCore\Log\LoggerInterface;
use Wallee\PluginCore\Sdk\SdkProvider;
use Wallee\PluginCore\Sdk\SdkV1\RecurringTransactionGateway;
use Wallee\PluginCore\Transaction\State;
use Wallee\PluginCore\Transaction\Transaction;
use Wallee\Sdk\Model\Transaction as SdkTransaction;
use Wallee\Sdk\Model\TransactionState as SdkTransactionState;
use Wallee\Sdk\Service\TransactionService as SdkTransactionService;

class RecurringTransactionGatewayTest extends TestCase
{
    private RecurringTransactionGateway $gateway;
    private MockObject|LoggerInterface $logger;
    private MockObject|SdkProvider $sdkProvider;
    private MockObject|SdkTransactionService $transactionService;

    protected function setUp(): void
    {
        $this->sdkProvider = $this->createMock(SdkProvider::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->transactionService = $this->createMock(SdkTransactionService::class);

        $this->sdkProvider->method('getService')
            ->with(SdkTransactionService::class)
            ->willReturn($this->transactionService);

        $this->gateway = new RecurringTransactionGateway($this->sdkProvider, $this->logger);
    }

    public function testProcessRecurringPaymentReturnsTransaction(): void
    {
        $spaceId = 1;
        $transactionId = 200;

        $sdkTransaction = new SdkTransaction();
        $sdkTransaction->setId($transactionId);
        $sdkTransaction->setLinkedSpaceId($spaceId);
        $sdkTransaction->setVersion(1);
        $sdkTransaction->setState(SdkTransactionState::AUTHORIZED);

        $this->transactionService->expects($this->once())
            ->method('processWithoutUserInteraction')
            ->with($spaceId, $transactionId)
            ->willReturn($sdkTransaction);

        $result = $this->gateway->processRecurringPayment($spaceId, $transactionId);

        $this->assertInstanceOf(Transaction::class, $result);
        $this->assertEquals($transactionId, $result->id);
        $this->assertEquals($spaceId, $result->spaceId);
        $this->assertEquals(State::AUTHORIZED, $result->state);
    }

    public function testProcessRecurringPaymentThrowsExceptionOnError(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("API Error");

        $this->transactionService->expects($this->once())
            ->method('processWithoutUserInteraction')
            ->willThrowException(new \Exception("API Error"));

        $this->gateway->processRecurringPayment(1, 200);
    }
}
