<?php

declare(strict_types=1);

namespace Wallee\PluginCore\Sdk\WebServiceAPIV2;

use Wallee\PluginCore\LineItem\LineItemCollection;
use Wallee\PluginCore\Localization\LocalizedString;
use Wallee\PluginCore\Log\DomainLoggerTrait;
use Wallee\PluginCore\Log\LogContext;
use Wallee\PluginCore\Log\LoggerInterface;
use Wallee\PluginCore\Refund\Exception\RefundException;
use Wallee\PluginCore\Refund\Refund;
use Wallee\PluginCore\Refund\RefundCollection;
use Wallee\PluginCore\Refund\RefundContext;
use Wallee\PluginCore\Refund\RefundGatewayInterface;
use Wallee\PluginCore\Refund\State as StateEnum;
use Wallee\PluginCore\Sdk\DateTimeMapperTrait;
use Wallee\PluginCore\Sdk\FailureReasonMapperTrait;
use Wallee\PluginCore\Sdk\LineItemMapperTrait;
use Wallee\PluginCore\Sdk\SdkProvider;
use Wallee\Sdk\Model\LineItemReductionCreate as SdkLineItemReductionCreate;
use Wallee\Sdk\Model\Refund as SdkRefund;
use Wallee\Sdk\Model\RefundCreate as SdkRefundCreate;
use Wallee\Sdk\Model\RefundType as SdkRefundType;
use Wallee\Sdk\Service\RefundsService as SdkRefundService;

#[LogContext(domain: 'refund')]
class RefundGateway implements RefundGatewayInterface
{
    use DomainLoggerTrait;
    use DateTimeMapperTrait;
    use FailureReasonMapperTrait;
    use LineItemMapperTrait;

    private SdkRefundService $sdkRefundService;

    public function __construct(
        private readonly SdkProvider $sdkProvider,
        LoggerInterface $logger,
    ) {
        $this->initializeLogger($logger);
        $this->sdkRefundService = $this->sdkProvider->getService(SdkRefundService::class);
    }

    public function findByTransaction(int $spaceId, int $transactionId): RefundCollection
    {
        // V2 Search: using query string 'transaction.id:<id>'
        // SEARCH for refunds linked to the transaction using the 'query' filter.
        $query = "transaction.id:$transactionId";

        try {
            $sdkRefunds = $this->sdkRefundService->getPaymentRefundsSearch($spaceId, null, null, null, null, $query);
            $refunds = [];
            // Handle ListResponse or Array
            $items = (is_object($sdkRefunds) && method_exists($sdkRefunds, 'getData')) ? $sdkRefunds->getData() : (array)$sdkRefunds;
            foreach ($items as $sdkRefund) {
                $refunds[] = $this->mapToRefund($sdkRefund, $transactionId);
            }
            return new RefundCollection(...$refunds);
        } catch (\Throwable $e) {
            $this->logger->error(
                'Failed to find refunds for transaction.',
                [
                    'errorMessage' => $e->getMessage(),
                    'exception' => $e,
                    'spaceId' => $spaceId,
                    'transactionId' => $transactionId,
                ],
            );
            throw SdkProvider::wrapException(
                $e,
                RefundException::class,
                'getPaymentRefundsSearch',
                ['spaceId' => $spaceId, 'transactionId' => $transactionId],
                'An error occurred while retrieving refunds.',
            );
        }
    }

    public function refund(int $spaceId, RefundContext $context): Refund
    {
        $this->logger->debug("Preparing refund.", [
            'transactionId' => $context->transactionId,
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

            if (!$context->lineItems->isEmpty()) {
                $sdkReductions = [];
                foreach ($context->lineItems as $item) {
                    $uniqueId = $item->uniqueId;
                    $qty = $item->returnedQuantity;
                    $amt = $item->unitPriceReduction;

                    $this->logger->debug("Adding refund line item reduction.", [
                        'uniqueId' => $uniqueId,
                        'quantity' => $qty,
                        'amount' => $amt,
                    ]);

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

            $sdkRefund = $this->sdkRefundService->postPaymentRefunds($spaceId, $sdkRefundCreate);

            $this->logger->debug("Refund created successfully.", [
                'refundId' => $sdkRefund->getId(),
                'state' => $sdkRefund->getState(),
                'transactionId' => $context->transactionId,
                'spaceId' => $spaceId,
            ]);

            return $this->mapToRefund($sdkRefund, $context->transactionId);
        } catch (\Throwable $e) {
            $this->logger->error("Refund failed.", [
                'transactionId' => $context->transactionId,
                'spaceId' => $spaceId,
                'exception' => $e,
            ]);
            $exception = SdkProvider::wrapException(
                $e,
                RefundException::class,
                'postPaymentRefunds',
                ['spaceId' => $spaceId, 'transactionId' => $context->transactionId],
                'An error occurred while processing the refund.',
            );


            throw $exception;
        }
    }

    public function findById(int $spaceId, int $refundId): Refund
    {
        $this->logger->debug("Reading refund.", [
            'refundId' => $refundId,
            'spaceId' => $spaceId,
        ]);

        try {
            $sdkRefund = $this->sdkRefundService->getPaymentRefundsId($refundId, $spaceId);
            return $this->mapToRefund($sdkRefund, (int)$sdkRefund->getTransaction()?->getId());
        } catch (\Throwable $e) {
            $this->logger->error(
                'Failed to read refund.',
                [
                    'errorMessage' => $e->getMessage(),
                    'exception' => $e,
                    'refundId' => $refundId,
                    'spaceId' => $spaceId,
                ],
            );
            throw SdkProvider::wrapException(
                $e,
                RefundException::class,
                'getPaymentRefundsId',
                ['spaceId' => $spaceId, 'refundId' => $refundId],
                'An error occurred while retrieving the refund.',
            );
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

        $reason = $sdkRefund->getFailureReason();
        if ($reason !== null) {
            $refund->failureReason = $this->mapSdkFailureReason($reason);
        }

        $refund->createdOn = $this->toDateTimeImmutable($sdkRefund->getCreatedOn());
        $refund->failedOn = $this->toDateTimeImmutable($sdkRefund->getFailedOn());

        // The SDK's line items carry the reductions applied by this refund.
        $sdkLineItems = $sdkRefund->getLineItems();
        if (!empty($sdkLineItems)) {
            $refund->lineItems = new LineItemCollection(...array_map([$this, 'mapToLineItem'], $sdkLineItems));
        }

        // The reduced line items carry the post-refund cart state.
        $sdkReducedLineItems = $sdkRefund->getReducedLineItems();
        if (!empty($sdkReducedLineItems)) {
            $refund->reducedLineItems = new LineItemCollection(...array_map([$this, 'mapToLineItem'], $sdkReducedLineItems));
        }

        return $refund;
    }

}
