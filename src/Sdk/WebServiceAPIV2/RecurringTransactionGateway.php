<?php

declare(strict_types=1);

namespace Wallee\PluginCore\Sdk\WebServiceAPIV2;

use Wallee\PluginCore\Localization\LocalizedString;
use Wallee\PluginCore\Log\DomainLoggerTrait;
use Wallee\PluginCore\Log\LogContext;
use Wallee\PluginCore\Log\LoggerInterface;
use Wallee\PluginCore\Sdk\SdkProvider;
use Wallee\PluginCore\Sdk\TransactionMapperTrait;
use Wallee\PluginCore\Transaction\Exception\TransactionException;
use Wallee\PluginCore\Transaction\RecurringTransactionGatewayInterface;
use Wallee\PluginCore\Transaction\Transaction;
use Wallee\Sdk\Service\TransactionsService as SdkTransactionsService;

/**
 * Implementation of the RecurringTransactionGatewayInterface using the SDK V2.
 *
 * Uses `processWithToken` to charge the transaction against the token's stored
 * payment credentials (MIT — Merchant Initiated Transaction).
 */
#[LogContext(domain: 'transaction', subdomain: 'recurring')]
class RecurringTransactionGateway implements RecurringTransactionGatewayInterface
{
    use DomainLoggerTrait;
    use TransactionMapperTrait;

    /**
     * @var SdkTransactionsService The SDK transaction service.
     */
    private SdkTransactionsService $transactionsService;

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
        $this->transactionsService = $this->sdkProvider->getService(SdkTransactionsService::class);
    }

    /**
     * Processes a recurring payment for an existing transaction.
     *
     * Charges the transaction using `processWithToken`, which leverages the
     * stored payment credentials from the linked token. Then fetches the
     * updated transaction to return it as the domain object.
     *
     * @param int $spaceId The space ID.
     * @param int $transactionId The transaction ID.
     * @return Transaction The processed transaction.
     * @throws \Exception If the processing fails.
     */
    public function processRecurringPayment(int $spaceId, int $transactionId): Transaction
    {
        $this->logger->debug("Processing recurring payment via token.", [
            'transactionId' => $transactionId,
            'spaceId' => $spaceId,
        ]);

        try {
            // V2: processWithToken charges the transaction using the token's stored
            // payment credentials. Returns a Charge object, not a Transaction.
            $sdkCharge = $this->transactionsService->postPaymentTransactionsIdProcessWithToken(
                $transactionId,
                $spaceId,
            );

            $this->logger->debug("Charge completed.", [
                'transactionId' => $transactionId,
                'spaceId' => $spaceId,
                'chargeState' => (string) $sdkCharge->getState(),
            ]);

            // Fetch the updated transaction after the charge to return it
            $sdkTransaction = $this->transactionsService->getPaymentTransactionsId(
                $transactionId,
                $spaceId,
            );

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
