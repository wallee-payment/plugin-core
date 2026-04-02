<?php

declare(strict_types=1);

namespace Wallee\PluginCore\Sdk\SdkV2;

use Wallee\PluginCore\Sdk\SdkProvider;
use Wallee\PluginCore\Transaction\Completion\State;
use Wallee\PluginCore\Transaction\Completion\TransactionCompletion;
use Wallee\PluginCore\Transaction\Completion\TransactionCompletionGatewayInterface;
use Wallee\Sdk\Model\TransactionCompletion as SdkTransactionCompletion;
use Wallee\Sdk\Service\TransactionsService as SdkTransactionsService;

/**
 * SDK v2 implementation of the transaction completion gateway.
 *
 * This class interacts with the Wallee SDK to perform capture and void operations.
 */
class TransactionCompletionGateway implements TransactionCompletionGatewayInterface
{
    private SdkTransactionsService $transactionsService;

    public function __construct(
        private readonly SdkProvider $sdkProvider,
    ) {
        $this->transactionsService = $this->sdkProvider->getService(SdkTransactionsService::class);
    }

    /**
     * Captures an authorized transaction by creating a completion online.
     *
     * @param int $spaceId The space ID.
     * @param int $transactionId The transaction ID to capture.
     * @return TransactionCompletion The resulting completion domain object.
     */
    public function capture(int $spaceId, int $transactionId): TransactionCompletion
    {
        // The SDK method returns the resulting completion object.
        $sdkResult = $this->transactionsService->postPaymentTransactionsIdCompleteOnline($transactionId, $spaceId);

        return $this->mapToTransactionCompletion($sdkResult);
    }

    /**
     * Maps an SDK TransactionCompletion to our domain TransactionCompletion.
     *
     * @param SdkTransactionCompletion $sdkCompletion The SDK completion object.
     * @return TransactionCompletion The domain completion object.
     */
    private function mapToTransactionCompletion(SdkTransactionCompletion $sdkCompletion): TransactionCompletion
    {
        $completion = new TransactionCompletion();

        $completion->id = $sdkCompletion->getId();
        $completion->linkedTransactionId = $sdkCompletion->getLinkedTransaction();
        if ($sdkCompletion->getState()) {
            $completion->state = State::from((string)$sdkCompletion->getState());
        }

        $completion->lineItems = $sdkCompletion->getLineItems() ?? [];

        return $completion;
    }

    /**
     * Voids an authorized transaction.
     *
     * @param int $spaceId The space ID.
     * @param int $transactionId The transaction ID to void.
     * @return string The state of the void operation.
     */
    public function void(int $spaceId, int $transactionId): string
    {
        // Voids the transaction using the SDK's online void method.
        $void = $this->transactionsService->postPaymentTransactionsIdVoidOnline($transactionId, $spaceId);

        return (string)$void->getState();
    }
}
