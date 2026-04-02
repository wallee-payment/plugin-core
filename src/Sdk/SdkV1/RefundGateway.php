<?php

declare(strict_types=1);

namespace Wallee\PluginCore\Sdk\SdkV1;

use Wallee\PluginCore\Log\LoggerInterface;
use Wallee\PluginCore\Refund\Refund;
use Wallee\PluginCore\Refund\RefundContext;
use Wallee\PluginCore\Refund\RefundGatewayInterface;
use Wallee\PluginCore\Refund\State as StateEnum;
use Wallee\PluginCore\Sdk\SdkProvider;
use Wallee\Sdk\Model\CriteriaOperator as SdkCriteriaOperator;
use Wallee\Sdk\Model\EntityQuery as SdkEntityQuery;
use Wallee\Sdk\Model\EntityQueryFilter as SdkEntityQueryFilter;
use Wallee\Sdk\Model\EntityQueryFilterType as SdkEntityQueryFilterType;
use Wallee\Sdk\Model\LineItemReductionCreate as SdkLineItemReductionCreate;
use Wallee\Sdk\Model\Refund as SdkRefund;
use Wallee\Sdk\Model\RefundCreate as SdkRefundCreate;
use Wallee\Sdk\Model\RefundType as SdkRefundType;
use Wallee\Sdk\Service\RefundService as SdkRefundService;

class RefundGateway implements RefundGatewayInterface
{
    private SdkRefundService $sdkRefundService;

    public function __construct(
        private readonly SdkProvider $sdkProvider,
        private readonly LoggerInterface $logger,
    ) {
        $this->sdkRefundService = $this->sdkProvider->getService(SdkRefundService::class);
    }

    /**
     * @return Refund[]
     */
    public function findByTransaction(int $spaceId, int $transactionId): array
    {
        $query = new SdkEntityQuery();
        $filter = new SdkEntityQueryFilter();
        $filter->setType(SdkEntityQueryFilterType::LEAF);
        $filter->setOperator(SdkCriteriaOperator::EQUALS);
        $filter->setFieldName('transaction.id');
        $filter->setValue($transactionId);
        $query->setFilter($filter);

        try {
            $sdkRefunds = $this->sdkRefundService->search($spaceId, $query);
            $refunds = [];
            foreach ($sdkRefunds as $sdkRefund) {
                $refunds[] = $this->mapToRefund($sdkRefund, $transactionId);
            }
            return $refunds;
        } catch (\Throwable $e) {
            $this->logger->error("Failed to find refunds for Transaction $transactionId: {$e->getMessage()}");
            return [];
        }
    }

    private function mapToRefund(SdkRefund $sdkRefund, int $transactionId): Refund
    {
        $refund = new Refund();
        $refund->id = (int)$sdkRefund->getId();
        $refund->amount = (float)$sdkRefund->getAmount();
        $refund->transactionId = $transactionId;
        $refund->externalId = $sdkRefund->getExternalId();

        $refund->state = match ((string)$sdkRefund->getState()) {
            'CREATE' => StateEnum::CREATE,
            'SCHEDULED' => StateEnum::SCHEDULED,
            'PENDING' => StateEnum::PENDING,
            'MANUAL_CHECK' => StateEnum::MANUAL_CHECK,
            'FAILED' => StateEnum::FAILED,
            'SUCCESSFUL' => StateEnum::SUCCESSFUL,
            default => StateEnum::PENDING, // Safe fallback
        };

        return $refund;
    }

    public function refund(int $spaceId, RefundContext $context): Refund
    {
        $this->logger->debug("Preparing refund for Transaction {$context->transactionId}", [
            'amount' => $context->amount,
            'spaceId' => $spaceId,
        ]);

        try {
            $sdkRefundCreate = new SdkRefundCreate();
            $sdkRefundCreate->setTransaction($context->transactionId);
            $sdkRefundCreate->setAmount($context->amount);
            $sdkRefundCreate->setMerchantReference($context->merchantReference);
            $sdkRefundCreate->setExternalId(uniqid((string)$context->transactionId . '-', true));
            $sdkRefundCreate->setType(match ($context->type->value) {
                'MERCHANT_INITIATED_ONLINE' => SdkRefundType::MERCHANT_INITIATED_ONLINE,
                'MERCHANT_INITIATED_OFFLINE' => SdkRefundType::MERCHANT_INITIATED_OFFLINE,
                'CUSTOMER_INITIATED_AUTOMATIC' => SdkRefundType::CUSTOMER_INITIATED_AUTOMATIC,
                'CUSTOMER_INITIATED_MANUAL' => SdkRefundType::CUSTOMER_INITIATED_MANUAL,
                default => SdkRefundType::MERCHANT_INITIATED_ONLINE,
            });

            if (!empty($context->lineItems)) {
                $sdkReductions = [];
                foreach ($context->lineItems as $item) {
                    $uniqueId = $item['uniqueId'] ?? null;
                    $qty = (float)($item['quantity'] ?? 0);
                    $amt = (float)($item['amount'] ?? 0);

                    $this->logger->debug("Adding Reduction: ID=$uniqueId, Qty=$qty, Amt=$amt");

                    $sdkReduction = new SdkLineItemReductionCreate();
                    $sdkReduction->setLineItemUniqueId($uniqueId);
                    $sdkReduction->setQuantityReduction($qty);
                    $sdkReduction->setUnitPriceReduction($amt);
                    $sdkReductions[] = $sdkReduction;
                }
                $sdkRefundCreate->setReductions($sdkReductions);
            }

            $this->logger->debug("Refund Create Payload", [
                'amount' => $sdkRefundCreate->getAmount(),
                'reductions_count' => count($sdkRefundCreate->getReductions() ?? []),
            ]);

            $sdkRefund = $this->sdkRefundService->refund($spaceId, $sdkRefundCreate);

            $this->logger->debug("Refund created successfully. ID: {$sdkRefund->getId()}, State: {$sdkRefund->getState()}");

            return $this->mapToRefund($sdkRefund, $context->transactionId);
        } catch (\Throwable $e) {
            $this->logger->error("Refund failed for Transaction {$context->transactionId}: {$e->getMessage()}", [
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }
}
