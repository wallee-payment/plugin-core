<?php

declare(strict_types=1);

namespace Wallee\PluginCore\Tests\SharedKernel;

use PHPUnit\Framework\TestCase;
use Wallee\PluginCore\Refund\Exception\InvalidRefundException;
use Wallee\PluginCore\SharedKernel\AbstractDomainException;
use Wallee\PluginCore\Webhook\Exception\TransientWebhookException;

class AbstractDomainExceptionTest extends TestCase
{
    public function testIsRetryableDefaultsToFalse(): void
    {
        $exception = new class ('technical message') extends AbstractDomainException {
        };

        $this->assertFalse($exception->isRetryable());
    }

    public function testWithRetryableOverridesTheInstance(): void
    {
        $exception = new class ('technical message') extends AbstractDomainException {
        };

        $result = $exception->withRetryable(true);

        $this->assertSame($exception, $result);
        $this->assertTrue($exception->isRetryable());
    }

    public function testWithRetryableCanBeSetBackToFalse(): void
    {
        $exception = new class ('technical message') extends AbstractDomainException {
        };

        $exception->withRetryable(true);
        $exception->withRetryable(false);

        $this->assertFalse($exception->isRetryable());
    }

    public function testInvalidRefundExceptionIsTerminalByDefault(): void
    {
        $exception = new InvalidRefundException('bad request');

        $this->assertFalse($exception->isRetryable());
    }

    public function testTransientWebhookExceptionIsRetryableByDefault(): void
    {
        $exception = new TransientWebhookException('lock contention');

        $this->assertTrue($exception->isRetryable());
    }
}
