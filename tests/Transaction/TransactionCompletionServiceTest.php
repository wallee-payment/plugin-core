<?php

declare(strict_types=1);

namespace Wallee\PluginCore\Tests\Transaction;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Wallee\PluginCore\Log\LoggerInterface;
use Wallee\PluginCore\Transaction\Completion\State;
use Wallee\PluginCore\Transaction\Completion\TransactionCompletion;
use Wallee\PluginCore\Transaction\Completion\TransactionCompletionGatewayInterface;
use Wallee\PluginCore\Transaction\Completion\TransactionCompletionService;
use Wallee\PluginCore\Transaction\Exception\TransactionException;

class TransactionCompletionServiceTest extends TestCase
{
    private MockObject|TransactionCompletionGatewayInterface $gateway;
    private MockObject|LoggerInterface $logger;
    private TransactionCompletionService $service;

    protected function setUp(): void
    {
        $this->gateway = $this->createMock(TransactionCompletionGatewayInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->service = new TransactionCompletionService($this->gateway, $this->logger);
    }

    public function testCaptureFailure(): void
    {
        $this->gateway->expects($this->once())
            ->method('capture')
            ->willThrowException(new \Exception("SDK Error"));

        $this->expectException(TransactionException::class);
        $this->service->capture(1, 123);
    }

    public function testCaptureSuccess(): void
    {
        $spaceId = 1;
        $transactionId = 123;
        $completion = new TransactionCompletion();
        $completion->id = 456;
        $completion->state = State::SUCCESSFUL;

        $this->gateway->expects($this->once())
            ->method('capture')
            ->with($spaceId, $transactionId)
            ->willReturn($completion);

        $result = $this->service->capture($spaceId, $transactionId);
        $this->assertSame($completion, $result);
    }

    public function testVoidFailure(): void
    {
        $this->gateway->expects($this->once())
            ->method('void')
            ->willThrowException(new \Exception("SDK Error"));

        $this->expectException(TransactionException::class);
        $this->service->void(1, 123);
    }

    public function testVoidSuccess(): void
    {
        $spaceId = 1;
        $transactionId = 123;
        $state = 'SUCCESSFUL';

        $this->gateway->expects($this->once())
            ->method('void')
            ->with($spaceId, $transactionId)
            ->willReturn($state);

        $result = $this->service->void($spaceId, $transactionId);
        $this->assertSame($state, $result);
    }
}
