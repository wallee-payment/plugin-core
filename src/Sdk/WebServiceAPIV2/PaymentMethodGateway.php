<?php

declare(strict_types=1);

namespace Wallee\PluginCore\Sdk\WebServiceAPIV2;

use Wallee\PluginCore\Log\DomainLoggerTrait;
use Wallee\PluginCore\Log\LogContext;
use Wallee\PluginCore\Log\LoggerInterface;
use Wallee\PluginCore\PaymentMethod\PaymentMethod;
use Wallee\PluginCore\PaymentMethod\PaymentMethodCollection;
use Wallee\PluginCore\PaymentMethod\PaymentMethodGatewayInterface;
use Wallee\PluginCore\Sdk\PaymentMethodMapperTrait;
use Wallee\PluginCore\Sdk\SdkProvider;
use Wallee\PluginCore\Sdk\SearchPaginationTrait;
use Wallee\PluginCore\PaymentMethod\Exception\PaymentMethodException;
use Wallee\Sdk\Service\PaymentMethodConfigurationsService as SdkPaymentMethodConfigurationService;

/**
 * Gateway implementation using the SDK V2.
 */
#[LogContext(domain: 'sync')]
class PaymentMethodGateway implements PaymentMethodGatewayInterface
{
    use DomainLoggerTrait;
    use PaymentMethodMapperTrait;
    use SearchPaginationTrait;

    /**
     * @param SdkProvider $sdkProvider The SDK provider.
     * @param LoggerInterface $logger The logger instance.
     */
    public function __construct(
        private readonly SdkProvider $sdkProvider,
        LoggerInterface $logger,
    ) {
        $this->initializeLogger($logger);
    }

    /**
     * @inheritDoc
     */
    public function fetchById(int $spaceId, int $id, array $locales = []): PaymentMethod
    {
        try {
            $methods = $this->fetchLocalizedPaymentMethods(
                $this->sdkProvider,
                $this->logger,
                $locales,
                static fn (SdkPaymentMethodConfigurationService $service): array => [
                    $service->getPaymentMethodConfigurationsId($id, $spaceId),
                ],
            );

            return $methods[0];
        } catch (\Throwable $e) {
            $this->logger->error("PaymentMethodGateway: Failed to fetch payment method from SDK.", [
                'paymentMethodId' => $id,
                'spaceId' => $spaceId,
                'exception' => $e,
            ]);
            throw SdkProvider::wrapException(
                $e,
                PaymentMethodException::class,
                'read',
                ['spaceId' => $spaceId, 'paymentMethodId' => $id],
                'Payment method not found.',
            );
        }
    }

    /**
     * @inheritDoc
     */
    public function fetchBySpaceId(int $spaceId, ?string $state = null, array $locales = []): PaymentMethodCollection
    {
        try {
            // V2 Search: query string
            $query = null;
            if ($state !== null) {
                // Filter by a specific state.
                $query = "state:$state";
            } else {
                // By default, exclude deleted payment methods to match V1 behavior.
                // In V2 query syntax, prepending '-' to a field name excludes the value.
                $query = "-state:DELETED";
            }

            // getPaymentMethodConfigurationsSearch signature: ($space, $expand, $limit, $offset, $order, $query)
            // Paged rather than read in one call: callers are promised every
            // configuration in the space, and a truncated list makes the sync
            // deactivate methods that are still active.
            $methods = $this->fetchLocalizedPaymentMethods(
                $this->sdkProvider,
                $this->logger,
                $locales,
                fn (SdkPaymentMethodConfigurationService $service): \Generator => $this->paginateSearch(
                    static fn (int $offset): object => $service->getPaymentMethodConfigurationsSearch(
                        $spaceId,
                        null,
                        SdkProvider::MAX_PAGE_SIZE,
                        $offset,
                        null,
                        $query,
                    ),
                ),
            );

            return new PaymentMethodCollection(...$methods);
        } catch (\Throwable $e) {
            $this->logger->error("PaymentMethodGateway: Failed to fetch payment methods from SDK.", [
                'spaceId' => $spaceId,
                'exception' => $e,
            ]);
            throw SdkProvider::wrapException(
                $e,
                PaymentMethodException::class,
                'search',
                ['spaceId' => $spaceId],
                'Unable to fetch payment methods.',
            );
        }
    }

}
