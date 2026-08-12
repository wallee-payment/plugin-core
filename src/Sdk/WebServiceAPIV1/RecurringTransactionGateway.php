<?php

declare(strict_types=1);

namespace Wallee\PluginCore\Sdk\WebServiceAPIV1;

use Wallee\PluginCore\Log\DomainLoggerTrait;
use Wallee\PluginCore\Log\LogContext;
use Wallee\PluginCore\Log\LoggerInterface;
use Wallee\PluginCore\Sdk\SdkProvider;
use Wallee\PluginCore\Sdk\TransactionMapperTrait;
use Wallee\PluginCore\Transaction\Exception\TransactionException;
use Wallee\PluginCore\Transaction\RecurringTransactionGatewayInterface;
use Wallee\PluginCore\Transaction\Transaction;
use Wallee\Sdk\Service\TransactionService as SdkTransactionService;

/**
 * Class RecurringTransactionGateway
 *
 * Implementation of the RecurringTransactionGatewayInterface using the SDK V1.
 */
#[LogContext(domain: 'transaction', subdomain: 'recurring')]
class RecurringTransactionGateway implements RecurringTransactionGatewayInterface
{
    use DomainLoggerTrait;
    use TransactionMapperTrait;

    /**
     * @var SdkTransactionService The SDK transaction service.
     */
    private SdkTransactionService $transactionService;

    /**
     * RecurringTransactionGateway constructor.
     *
     * @param SdkProvider $sdkProvider The SDK provider.
     * @param LoggerInterface $logger The logger instance.
     */
    public function __construct(
        private readonly SdkProvider $sdkProvider,
        LoggerInterface $logger,
    ) {
        $this->initializeLogger($logger);
        $this->transactionService = $this->sdkProvider->getService(SdkTransactionService::class);
    }

    /**
     * Processes a recurring payment for an existing transaction.
     *
     * @param int $spaceId The space ID.
     * @param int $transactionId The transaction ID.
     * @return Transaction The processed transaction.
     * @throws \Exception If the processing fails.
     */
    public function processRecurringPayment(int $spaceId, int $transactionId): Transaction
    {
        $this->logger->debug("Processing recurring payment.", [
            'transactionId' => $transactionId,
            'spaceId' => $spaceId,
        ]);

        try {
            $sdkTransaction = $this->transactionService->processWithoutUserInteraction($spaceId, $transactionId);
            $this->logger->debug("Recurring payment processed successfully.", [
                'transactionId' => $transactionId,
                'spaceId' => $spaceId,
            ]);

            return $this->mapToTransaction($sdkTransaction);
        } catch (\Throwable $e) {
            $this->logger->error("Failed to process recurring payment.", [
                'transactionId' => $transactionId,
                'spaceId' => $spaceId,
                'exception' => $e,
            ]);
            throw SdkProvider::wrapException(
                $e,
                TransactionException::class,
                'processWithoutUserInteraction',
                ['spaceId' => $spaceId, 'transactionId' => $transactionId],
                'The recurring payment could not be processed.',
            );
        }
    }
}
