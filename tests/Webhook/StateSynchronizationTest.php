<?php

declare(strict_types=1);

namespace Wallee\PluginCore\Tests\Webhook;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Wallee\PluginCore\DeliveryIndication\State as PluginCoreDeliveryIndicationState;
use Wallee\PluginCore\ManualTask\State as PluginCoreManualTaskState;
use Wallee\PluginCore\Refund\State as PluginCoreRefundState;
use Wallee\PluginCore\Token\Version\State as PluginCoreTokenVersionState;
use Wallee\PluginCore\Transaction\Completion\State as PluginCoreTransactionCompletionState;
use Wallee\PluginCore\Transaction\Invoice\State as PluginCoreTransactionInvoiceState;
use Wallee\PluginCore\Transaction\State as PluginCoreTransactionState;
use Wallee\PluginCore\Transaction\Void\State as PluginCoreTransactionVoidState;
use Wallee\Sdk\Model\DeliveryIndicationState as SdkDeliveryIndicationState;
use Wallee\Sdk\Model\ManualTaskState as SdkManualTaskState;
use Wallee\Sdk\Model\RefundState as SdkRefundState;
use Wallee\Sdk\Model\TokenVersionState as SdkTokenVersionState;
use Wallee\Sdk\Model\TransactionCompletionState as SdkTransactionCompletionState;
use Wallee\Sdk\Model\TransactionInvoiceState as SdkTransactionInvoiceState;
use Wallee\Sdk\Model\TransactionState as SdkTransactionState;
use Wallee\Sdk\Model\TransactionVoidState as SdkTransactionVoidState;

class StateSynchronizationTest extends TestCase
{
    /**
     * @return array<string, array{0: class-string, 1: class-string}>
     */
    public static function stateMappingProvider(): array
    {
        return [
            'Delivery Indication States' => [
                SdkDeliveryIndicationState::class,
                PluginCoreDeliveryIndicationState::class,
            ],
            'Refund States' => [
                SdkRefundState::class,
                PluginCoreRefundState::class,
            ],
            'Manual Task States' => [
                SdkManualTaskState::class,
                PluginCoreManualTaskState::class,
            ],
            'Token Version States' => [
                SdkTokenVersionState::class,
                PluginCoreTokenVersionState::class,
            ],
            'Transaction States' => [
                SdkTransactionState::class,
                PluginCoreTransactionState::class,
            ],
            'Transaction Completion States' => [
                SdkTransactionCompletionState::class,
                PluginCoreTransactionCompletionState::class,
            ],
            'Transaction Invoice States' => [
                SdkTransactionInvoiceState::class,
                PluginCoreTransactionInvoiceState::class,
            ],
            'Transaction Void States' => [
                SdkTransactionVoidState::class,
                PluginCoreTransactionVoidState::class,
            ],
        ];
    }

    #[DataProvider('stateMappingProvider')]
    public function testInternalEnumCoversAllSdkStates(string $sdkStateClass, string $internalEnumClass): void
    {
        $sdkStates = $sdkStateClass::getAllowableEnumValues();
        $internalEnumValues = array_map(fn ($case) => $case->value, $internalEnumClass::cases());

        foreach ($sdkStates as $sdkState) {
            $this->assertContains(
                $sdkState,
                $internalEnumValues,
                "SDK state '{$sdkState}' is missing from internal enum {$internalEnumClass}",
            );
        }
    }
}
