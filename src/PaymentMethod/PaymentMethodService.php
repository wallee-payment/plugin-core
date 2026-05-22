<?php

declare(strict_types=1);

namespace Wallee\PluginCore\PaymentMethod;

use Wallee\PluginCore\Log\LoggerInterface;

/**
 * Service for managing payment methods.
 */
class PaymentMethodService
{
    /**
     * @param PaymentMethodGatewayInterface $gateway The gateway to fetch payment methods.
     * @param PaymentMethodRepositoryInterface $repository The repository to store payment methods.
     * @param LoggerInterface $logger The logger instance.
     */
    public function __construct(
        private readonly PaymentMethodGatewayInterface $gateway,
        private readonly PaymentMethodRepositoryInterface $repository,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Retrieves a specific payment method by its ID.
     *
     * @param int $spaceId The ID of the space.
     * @param int $paymentMethodId The ID of the payment method.
     * @return PaymentMethod The payment method.
     * @throws \Exception If the payment method cannot be fetched.
     */
    public function getPaymentMethod(int $spaceId, int $paymentMethodId): PaymentMethod
    {
        try {
            return $this->gateway->fetchById($spaceId, $paymentMethodId);
        } catch (\Exception $e) {
            $this->logger->error(sprintf('Error fetching payment method %d for space %d: %s', $paymentMethodId, $spaceId, $e->getMessage()));
            throw $e;
        }
    }

    /**
     * Retrieves available payment methods for a specific space.
     *
     * @param int $spaceId The ID of the space.
     * @param string|null $state The optional state to filter by.
     * @return PaymentMethod[] The list of available payment methods.
     */
    public function getPaymentMethods(int $spaceId, ?string $state = null): array
    {
        try {
            $methods = $this->gateway->fetchBySpaceId($spaceId, $state);
            $this->logger->info(sprintf('Fetched %d payment methods for space %d.', count($methods), $spaceId));

            return $methods;
        } catch (\Exception $e) {
            $this->logger->error(sprintf('Error fetching payment methods for space %d: %s', $spaceId, $e->getMessage()));

            throw $e;
        }
    }

    /**
     * Synchronizes payment methods from the API gateway into the local database.
     *
     * This method owns the diffing algorithm: it compares the external API state
     * against the locally persisted IDs and delegates granular create/update/deactivate
     * operations to the repository. Client plugins never need to implement this logic.
     *
     * @param int $spaceId The ID of the space to synchronize.
     * @throws \Exception If the gateway request fails; repository methods are never called in that case.
     */
    public function synchronize(int $spaceId): void
    {
        $this->logger->debug(sprintf('Starting payment method synchronization for space %d.', $spaceId));

        // Fetch the current state from the API.
        $externalMethods = $this->gateway->fetchBySpaceId($spaceId);
        $this->logger->debug(sprintf('Fetched %d payment methods from the API for space %d.', count($externalMethods), $spaceId));

        // Fetch the IDs of methods already persisted in the shop's local database.
        $existingIds = $this->repository->getExistingExternalIds($spaceId);
        $this->logger->debug(sprintf('Found %d existing payment method IDs in the local database for space %d.', count($existingIds), $spaceId));

        // Track which external IDs were processed to detect orphans afterwards.
        $processedIds = [];
        $createdCount = 0;
        $updatedCount = 0;

        // Diff each API method against the local state.
        foreach ($externalMethods as $method) {
            $processedIds[] = $method->id;

            if (in_array($method->id, $existingIds, true)) {
                $this->repository->update($method, $spaceId);
                $updatedCount++;
            } else {
                $this->repository->create($method, $spaceId);
                $createdCount++;
            }
        }

        // Orphans are local methods that the API no longer returns.
        $orphanedIds = array_diff($existingIds, $processedIds);
        foreach ($orphanedIds as $orphanedId) {
            $this->repository->deactivateByExternalId($orphanedId, $spaceId);
        }

        $this->logger->info(sprintf(
            'Payment method sync completed for space %d: %d created, %d updated, %d deactivated.',
            $spaceId,
            $createdCount,
            $updatedCount,
            count($orphanedIds),
        ));
    }
}
