<?php

declare(strict_types=1);

namespace Wallee\PluginCore\Tests\Sdk\SdkV1;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Wallee\PluginCore\Sdk\SdkProvider;
use Wallee\PluginCore\Sdk\SdkV1\TransactionCompletionGateway;
use Wallee\PluginCore\Transaction\Completion\TransactionCompletion;
use Wallee\PluginCore\Transaction\Completion\State;
use Wallee\Sdk\Model\TransactionCompletion as SdkTransactionCompletion;
use Wallee\Sdk\Model\TransactionCompletionState;
use Wallee\Sdk\Model\TransactionVoid as SdkTransactionVoid;
use Wallee\Sdk\Model\TransactionVoidState;
use Wallee\Sdk\Service\TransactionCompletionService as SdkTransactionCompletionService;
use Wallee\Sdk\Service\TransactionVoidService as SdkTransactionVoidService;

class TransactionCompletionGatewayTest extends TestCase
{
    private TransactionCompletionGateway $gateway;
    private MockObject|SdkProvider $sdkProvider;
    private MockObject|SdkTransactionCompletionService $completionService;
    private MockObject|SdkTransactionVoidService $voidService;

    protected function setUp(): void
    {
        $this->sdkProvider = $this->createMock(SdkProvider::class);
        $this->completionService = $this->createMock(SdkTransactionCompletionService::class);
        $this->voidService = $this->createMock(SdkTransactionVoidService::class);

        $this->sdkProvider->method('getService')
            ->willReturnMap([
                [SdkTransactionCompletionService::class, $this->completionService],
                [SdkTransactionVoidService::class, $this->voidService],
            ]);

        $this->gateway = new TransactionCompletionGateway($this->sdkProvider);
    }

    public function testCaptureReturnsCompletion(): void
    {
        $spaceId = 1;
        $transactionId = 2;

        $sdkCompletion = new SdkTransactionCompletion();
        $sdkCompletion->setId(10);
        $sdkCompletion->setLinkedTransaction($transactionId);
        $sdkCompletion->setState(TransactionCompletionState::SUCCESSFUL);

        $this->completionService->expects($this->once())
            ->method('completeOnline')
            ->with($spaceId, $transactionId)
            ->willReturn($sdkCompletion);

        $result = $this->gateway->capture($spaceId, $transactionId);

        $this->assertInstanceOf(TransactionCompletion::class, $result);
        $this->assertEquals(10, $result->id);
        $this->assertEquals($transactionId, $result->linkedTransactionId);
        $this->assertEquals(State::SUCCESSFUL, $result->state);
    }

    public function testVoidReturnsStateString(): void
    {
        $spaceId = 1;
        $transactionId = 2;

        $sdkVoid = new SdkTransactionVoid();
        $sdkVoid->setState(TransactionVoidState::SUCCESSFUL);

        $this->voidService->expects($this->once())
            ->method('voidOnline')
            ->with($spaceId, $transactionId)
            ->willReturn($sdkVoid);

        $result = $this->gateway->void($spaceId, $transactionId);

        $this->assertEquals('SUCCESSFUL', $result);
    }
}
