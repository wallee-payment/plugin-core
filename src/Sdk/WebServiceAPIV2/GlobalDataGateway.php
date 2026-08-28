<?php

declare(strict_types=1);

namespace Wallee\PluginCore\Sdk\WebServiceAPIV2;

use Wallee\PluginCore\GlobalData\Currency\CurrencyCollection;
use Wallee\PluginCore\GlobalData\Exception\GlobalDataException;
use Wallee\PluginCore\GlobalData\GlobalDataGatewayInterface;
use Wallee\PluginCore\GlobalData\LabelDescriptor\LabelDescriptorCollection;
use Wallee\PluginCore\GlobalData\LabelDescriptorGroup\LabelDescriptorGroupCollection;
use Wallee\PluginCore\GlobalData\Language\LanguageCollection;
use Wallee\PluginCore\GlobalData\PaymentConnector\PaymentConnectorCollection;
use Wallee\PluginCore\Localization\LocalizedString;
use Wallee\PluginCore\Log\DomainLoggerTrait;
use Wallee\PluginCore\Log\LogContext;
use Wallee\PluginCore\Log\LoggerInterface;
use Wallee\PluginCore\Sdk\CurrencyMapperTrait;
use Wallee\PluginCore\Sdk\LabelDescriptorGroupMapperTrait;
use Wallee\PluginCore\Sdk\LabelDescriptorMapperTrait;
use Wallee\PluginCore\Sdk\LanguageMapperTrait;
use Wallee\PluginCore\Sdk\PaymentConnectorMapperTrait;
use Wallee\PluginCore\Sdk\SdkProvider;
use Wallee\PluginCore\Sdk\SearchPaginationTrait;
use Wallee\Sdk\Model\CurrencyListResponse as SdkCurrencyListResponse;
use Wallee\Sdk\Model\LabelDescriptor as SdkLabelDescriptor;
use Wallee\Sdk\Model\LabelDescriptorGroup as SdkLabelDescriptorGroup;
use Wallee\Sdk\Model\LabelDescriptorGroupSearchResponse as SdkLabelDescriptorGroupSearchResponse;
use Wallee\Sdk\Model\LabelDescriptorSearchResponse as SdkLabelDescriptorSearchResponse;
use Wallee\Sdk\Model\LanguageListResponse as SdkLanguageListResponse;
use Wallee\Sdk\Model\PaymentConnector as SdkPaymentConnector;
use Wallee\Sdk\Model\PaymentConnectorSearchResponse as SdkPaymentConnectorSearchResponse;
use Wallee\Sdk\Model\RestApiErrorResponse as SdkRestApiErrorResponse;
use Wallee\Sdk\Model\RestCurrency as SdkRestCurrency;
use Wallee\Sdk\Model\RestLanguage as SdkRestLanguage;
use Wallee\Sdk\Service\CurrenciesService as SdkCurrenciesService;
use Wallee\Sdk\Service\LabelDescriptorsService as SdkLabelDescriptorsService;
use Wallee\Sdk\Service\LanguagesService as SdkLanguagesService;
use Wallee\Sdk\Service\PaymentConnectorsService as SdkPaymentConnectorsService;

/**
 * Gateway for retrieving the Wallee Portal's global reference data using the SDK.
 *
 * This data is global to the Wallee Portal, so no call here is space-scoped. Unlike most
 * other gateways in PluginCore, there is therefore no per-call identifying context
 * (no space ID, no entity ID) to include in log records or exception messages —
 * the operation name alone identifies what was attempted.
 *
 * Three things differ from WebServiceAPIV1 and are absorbed here so consumers
 * never see them:
 *
 * - **Pagination.** Currencies and languages come back complete in one response,
 *   but connectors, label descriptors and descriptor groups are only exposed
 *   through paginated search endpoints. Those three page through transparently, so
 *   a caller always receives the complete collection exactly as it would from
 *   WebServiceAPIV1's `all()`. The cost scales with the number of entities (one
 *   HTTP call per 100) rather than being a single lookup.
 * - **Error models.** Operations report some failures by returning an error model
 *   rather than throwing, so each response's shape is verified before it is read
 *   and that union never reaches a caller.
 * - **Service layout.** Label descriptors and their groups share one SDK service
 *   here, where WebServiceAPIV1 splits them across two.
 *
 * Converting SDK models into domain entities — including the payload-shape
 * differences behind those entities — is the mapper traits' job; this class owns
 * the calls, their observability and their failure handling.
 */
#[LogContext(domain: 'global_data')]
class GlobalDataGateway implements GlobalDataGatewayInterface
{
    use CurrencyMapperTrait;
    use DomainLoggerTrait;
    use LabelDescriptorGroupMapperTrait;
    use LabelDescriptorMapperTrait;
    use LanguageMapperTrait;
    use PaymentConnectorMapperTrait;
    use SearchPaginationTrait;


    private SdkCurrenciesService $currenciesService;
    private SdkLabelDescriptorsService $labelDescriptorsService;
    private SdkLanguagesService $languagesService;
    private SdkPaymentConnectorsService $paymentConnectorsService;

    /**
     * @param SdkProvider $sdkProvider The SDK provider.
     * @param LoggerInterface $logger The logger instance.
     */
    public function __construct(
        private readonly SdkProvider $sdkProvider,
        LoggerInterface $logger,
    ) {
        $this->initializeLogger($logger);
        $this->currenciesService = $this->sdkProvider->getService(SdkCurrenciesService::class);
        $this->languagesService = $this->sdkProvider->getService(SdkLanguagesService::class);
        $this->paymentConnectorsService = $this->sdkProvider->getService(SdkPaymentConnectorsService::class);
        $this->labelDescriptorsService = $this->sdkProvider->getService(SdkLabelDescriptorsService::class);
    }

    /**
     * @inheritDoc
     */
    public function getCurrencies(): CurrencyCollection
    {
        $operation = 'getCurrencies';

        // Returns the complete set in one response — no pagination on this endpoint.
        $this->logger->debug('Calling global data operation.', ['operation' => $operation]);

        try {
            $response = $this->currenciesService->getCurrencies();
        } catch (\Throwable $e) {
            $this->logger->error(
                'Global data operation failed.',
                ['operation' => $operation, 'errorMessage' => $e->getMessage(), 'exception' => $e],
            );

            throw SdkProvider::wrapException(
                $e,
                GlobalDataException::class,
                $operation,
                [],
                'The payment configuration could not be loaded. Please try again later.',
            );
        }

        if (!$response instanceof SdkCurrencyListResponse) {
            $errorContext = ['operation' => $operation, 'responseType' => get_debug_type($response)];

            if ($response instanceof SdkRestApiErrorResponse) {
                $errorContext['errorMessage'] = $response->getMessage();
                $errorContext['errorCode'] = $response->getCode();
            }

            $this->logger->error('Global data operation returned an unexpected response.', $errorContext);

            throw SdkProvider::unexpectedResponseException(
                GlobalDataException::class,
                $operation,
                [],
                'The payment configuration could not be loaded. Please try again later.',
            );
        }

        $currencies = [];
        foreach ($response->getData() ?? [] as $sdkCurrency) {
            if (!$sdkCurrency instanceof SdkRestCurrency) {
                $this->skippedEntry($operation, $sdkCurrency);

                continue;
            }

            $currencies[] = $this->mapToCurrency($sdkCurrency);
        }

        $this->succeeded($operation, count($currencies));

        return new CurrencyCollection(...$currencies);
    }

    /**
     * @inheritDoc
     */
    public function getLabelDescriptorGroups(): LabelDescriptorGroupCollection
    {
        $operation = 'getLabelDescriptorsGroupsSearch';

        $groups = [];

        // Paginated on this API version: page through until the API reports no more.
        $sdkGroups = $this->paginateSearch(
            function (int $offset) use ($operation): object {
                $this->logger->debug('Calling global data operation.', ['operation' => $operation]);

                try {
                    $response = $this->labelDescriptorsService->getLabelDescriptorsGroupsSearch(
                        null,
                        SdkProvider::MAX_PAGE_SIZE,
                        $offset,
                        null,
                        null,
                    );
                } catch (\Throwable $e) {
                    $this->logger->error(
                        'Global data operation failed.',
                        ['operation' => $operation, 'errorMessage' => $e->getMessage(), 'exception' => $e],
                    );

                    throw SdkProvider::wrapException(
                        $e,
                        GlobalDataException::class,
                        $operation,
                        [],
                        'The payment configuration could not be loaded. Please try again later.',
                    );
                }

                if (!$response instanceof SdkLabelDescriptorGroupSearchResponse) {
                    $errorContext = ['operation' => $operation, 'responseType' => get_debug_type($response)];

                    if ($response instanceof SdkRestApiErrorResponse) {
                        $errorContext['errorMessage'] = $response->getMessage();
                        $errorContext['errorCode'] = $response->getCode();
                    }

                    $this->logger->error('Global data operation returned an unexpected response.', $errorContext);

                    throw SdkProvider::unexpectedResponseException(
                        GlobalDataException::class,
                        $operation,
                        [],
                        'The payment configuration could not be loaded. Please try again later.',
                    );
                }

                return $response;
            },
        );

        foreach ($sdkGroups as $sdkGroup) {
            if (!$sdkGroup instanceof SdkLabelDescriptorGroup) {
                $this->skippedEntry($operation, $sdkGroup);

                continue;
            }

            $groups[] = $this->mapToLabelDescriptorGroup($sdkGroup);
        }

        $this->succeeded($operation, count($groups));

        return new LabelDescriptorGroupCollection(...$groups);
    }

    /**
     * @inheritDoc
     */
    public function getLabelDescriptors(): LabelDescriptorCollection
    {
        $operation = 'getLabelDescriptorsSearch';

        $descriptors = [];

        // Paginated on this API version: page through until the API reports no more.
        $sdkDescriptors = $this->paginateSearch(
            function (int $offset) use ($operation): object {
                $this->logger->debug('Calling global data operation.', ['operation' => $operation]);

                try {
                    $response = $this->labelDescriptorsService->getLabelDescriptorsSearch(
                        null,
                        SdkProvider::MAX_PAGE_SIZE,
                        $offset,
                        null,
                        null,
                    );
                } catch (\Throwable $e) {
                    $this->logger->error(
                        'Global data operation failed.',
                        ['operation' => $operation, 'errorMessage' => $e->getMessage(), 'exception' => $e],
                    );

                    throw SdkProvider::wrapException(
                        $e,
                        GlobalDataException::class,
                        $operation,
                        [],
                        'The payment configuration could not be loaded. Please try again later.',
                    );
                }

                if (!$response instanceof SdkLabelDescriptorSearchResponse) {
                    $errorContext = ['operation' => $operation, 'responseType' => get_debug_type($response)];

                    if ($response instanceof SdkRestApiErrorResponse) {
                        $errorContext['errorMessage'] = $response->getMessage();
                        $errorContext['errorCode'] = $response->getCode();
                    }

                    $this->logger->error('Global data operation returned an unexpected response.', $errorContext);

                    throw SdkProvider::unexpectedResponseException(
                        GlobalDataException::class,
                        $operation,
                        [],
                        'The payment configuration could not be loaded. Please try again later.',
                    );
                }

                return $response;
            },
        );

        foreach ($sdkDescriptors as $sdkDescriptor) {
            if (!$sdkDescriptor instanceof SdkLabelDescriptor) {
                $this->skippedEntry($operation, $sdkDescriptor);

                continue;
            }

            $descriptors[] = $this->mapToLabelDescriptor($sdkDescriptor);
        }

        $this->succeeded($operation, count($descriptors));

        return new LabelDescriptorCollection(...$descriptors);
    }

    /**
     * @inheritDoc
     */
    public function getLanguages(): LanguageCollection
    {
        $operation = 'getLanguages';

        // Returns the complete set in one response — no pagination on this endpoint.
        $this->logger->debug('Calling global data operation.', ['operation' => $operation]);

        try {
            $response = $this->languagesService->getLanguages();
        } catch (\Throwable $e) {
            $this->logger->error(
                'Global data operation failed.',
                ['operation' => $operation, 'errorMessage' => $e->getMessage(), 'exception' => $e],
            );

            throw SdkProvider::wrapException(
                $e,
                GlobalDataException::class,
                $operation,
                [],
                'The payment configuration could not be loaded. Please try again later.',
            );
        }

        if (!$response instanceof SdkLanguageListResponse) {
            $errorContext = ['operation' => $operation, 'responseType' => get_debug_type($response)];

            if ($response instanceof SdkRestApiErrorResponse) {
                $errorContext['errorMessage'] = $response->getMessage();
                $errorContext['errorCode'] = $response->getCode();
            }

            $this->logger->error('Global data operation returned an unexpected response.', $errorContext);

            throw SdkProvider::unexpectedResponseException(
                GlobalDataException::class,
                $operation,
                [],
                'The payment configuration could not be loaded. Please try again later.',
            );
        }

        $languages = [];
        foreach ($response->getData() ?? [] as $sdkLanguage) {
            if (!$sdkLanguage instanceof SdkRestLanguage) {
                $this->skippedEntry($operation, $sdkLanguage);

                continue;
            }

            $languages[] = $this->mapToLanguage($sdkLanguage);
        }

        $this->succeeded($operation, count($languages));

        return new LanguageCollection(...$languages);
    }

    /**
     * @inheritDoc
     */
    public function getPaymentConnectors(): PaymentConnectorCollection
    {
        $operation = 'getPaymentConnectorsSearch';

        $connectors = [];

        // Paginated on this API version: page through until the API reports no more.
        $sdkConnectors = $this->paginateSearch(
            function (int $offset) use ($operation): object {
                $this->logger->debug('Calling global data operation.', ['operation' => $operation]);

                try {
                    $response = $this->paymentConnectorsService->getPaymentConnectorsSearch(
                        null,
                        SdkProvider::MAX_PAGE_SIZE,
                        $offset,
                        null,
                        null,
                    );
                } catch (\Throwable $e) {
                    $this->logger->error(
                        'Global data operation failed.',
                        ['operation' => $operation, 'errorMessage' => $e->getMessage(), 'exception' => $e],
                    );

                    throw SdkProvider::wrapException(
                        $e,
                        GlobalDataException::class,
                        $operation,
                        [],
                        'The payment configuration could not be loaded. Please try again later.',
                    );
                }

                if (!$response instanceof SdkPaymentConnectorSearchResponse) {
                    $errorContext = ['operation' => $operation, 'responseType' => get_debug_type($response)];

                    if ($response instanceof SdkRestApiErrorResponse) {
                        $errorContext['errorMessage'] = $response->getMessage();
                        $errorContext['errorCode'] = $response->getCode();
                    }

                    $this->logger->error('Global data operation returned an unexpected response.', $errorContext);

                    throw SdkProvider::unexpectedResponseException(
                        GlobalDataException::class,
                        $operation,
                        [],
                        'The payment configuration could not be loaded. Please try again later.',
                    );
                }

                return $response;
            },
        );

        foreach ($sdkConnectors as $sdkConnector) {
            if (!$sdkConnector instanceof SdkPaymentConnector) {
                $this->skippedEntry($operation, $sdkConnector);

                continue;
            }

            $connectors[] = $this->mapToPaymentConnector($sdkConnector);
        }

        $this->succeeded($operation, count($connectors));

        return new PaymentConnectorCollection(...$connectors);
    }

    /**
     * Records that one entry of a list could not be interpreted.
     *
     * A malformed entry does not invalidate the rest of the list; the missing one is
     * simply absent from the collection, and this is the record of why.
     *
     * @param string $operation The SDK operation name, for log context.
     * @param mixed $entry The unusable entry.
     * @return void
     */
    private function skippedEntry(string $operation, mixed $entry): void
    {
        $this->logger->warning(
            'Skipping a global data entry that was not the expected type.',
            ['operation' => $operation, 'entryType' => get_debug_type($entry)],
        );
    }

    /**
     * Records a successful retrieval and how much it returned.
     *
     * Logged at debug, not info: these are routine, high-frequency reads of static
     * reference data, and a confirmation that one worked carries no business meaning
     * — unlike a state change, which does belong at info. Failures are unaffected and
     * still surface through {@see call()} at error level.
     *
     * @param string $operation The SDK operation name, for log context.
     * @param int $count How many entities were mapped.
     * @return void
     */
    private function succeeded(string $operation, int $count): void
    {
        $this->logger->debug(
            'Global data operation succeeded.',
            ['operation' => $operation, 'count' => $count],
        );
    }

}
