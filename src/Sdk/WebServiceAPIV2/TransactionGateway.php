<?php

declare(strict_types=1);

namespace Wallee\PluginCore\Sdk\WebServiceAPIV2;

use Wallee\PluginCore\Address\Address;
use Wallee\PluginCore\LineItem\LineItem;
use Wallee\PluginCore\Localization\LocalizedString;
use Wallee\PluginCore\Log\LoggerInterface;
use Wallee\PluginCore\PaymentMethod\PaymentMethod;
use Wallee\PluginCore\PaymentMethod\PaymentMethodCollection;
use Wallee\PluginCore\PaymentMethod\State as PaymentMethodState;
use Wallee\PluginCore\Sdk\PaymentMethodMapperTrait;
use Wallee\PluginCore\Sdk\SdkProvider;
use Wallee\PluginCore\Sdk\TransactionMapperTrait;
use Wallee\PluginCore\Settings\IntegrationMode as IntegrationModeEnum;
use Wallee\PluginCore\Settings\Settings;
use Wallee\PluginCore\Tax\Tax;
use Wallee\PluginCore\Token\State as TokenState;
use Wallee\PluginCore\Token\Token;
use Wallee\PluginCore\Transaction\Exception\TransactionException;
use Wallee\PluginCore\Transaction\PaymentUrl;
use Wallee\PluginCore\Transaction\State as StateEnum;
use Wallee\PluginCore\Transaction\Transaction;
use Wallee\PluginCore\Transaction\TransactionCollection;
use Wallee\PluginCore\Transaction\TransactionContext;
use Wallee\PluginCore\Transaction\TransactionGatewayInterface;
use Wallee\PluginCore\Transaction\TransactionSearchCriteria;
use Wallee\Sdk\ApiException;
use Wallee\Sdk\Model\Address as SdkAddress;
use Wallee\Sdk\Model\AddressCreate as SdkAddressCreate;
use Wallee\Sdk\Model\CreationEntityState as SdkCreationEntityState;
use Wallee\Sdk\Model\LineItem as SdkLineItem;
use Wallee\Sdk\Model\LineItemCreate as SdkLineItemCreate;
use Wallee\Sdk\Model\LineItemType as SdkLineItemType;
use Wallee\Sdk\Model\PaymentMethodConfiguration as SdkPaymentMethodConfiguration;
use Wallee\Sdk\Model\TaxCreate as SdkTaxCreate;
use Wallee\Sdk\Model\Token as SdkToken;
use Wallee\Sdk\Model\Transaction as SdkTransaction;
use Wallee\Sdk\Model\TransactionCreate as SdkTransactionCreate;
use Wallee\Sdk\Model\TransactionPending as SdkTransactionPending;
use Wallee\Sdk\Service\PaymentMethodConfigurationsService as SdkPaymentMethodConfigurationsService;
use Wallee\Sdk\Service\TransactionsService as SdkTransactionsService;

class TransactionGateway implements TransactionGatewayInterface
{
    use PaymentMethodMapperTrait;
    use TransactionMapperTrait;

    private SdkPaymentMethodConfigurationsService $paymentMethodConfigService;
    private SdkTransactionsService $transactionsService;

    public function __construct(
        private readonly SdkProvider $sdkProvider,
        private readonly LoggerInterface $logger,
        private readonly Settings $settings,
    ) {
        $this->transactionsService = $this->sdkProvider->getService(SdkTransactionsService::class);
        $this->paymentMethodConfigService = $this->sdkProvider->getService(SdkPaymentMethodConfigurationsService::class);
    }

    public function create(TransactionContext $context): Transaction
    {
        $this->logger->debug("Gateway: Preparing to CREATE transaction (V2).", [
            'merchantRef' => $context->merchantReference,
            'spaceId' => $context->spaceId,
        ]);

        $sdkBillingAddress = $this->mapAddress($context->billingAddress);
        $sdkShippingAddress = $context->shippingAddress
            ? $this->mapAddress($context->shippingAddress)
            : $sdkBillingAddress;

        $sdkLineItems = array_map([$this, 'mapLineItem'], $context->lineItems);

        $sdkTransactionCreate = new SdkTransactionCreate();
        $sdkTransactionCreate->setBillingAddress($sdkBillingAddress);
        $sdkTransactionCreate->setShippingAddress($sdkShippingAddress);
        $sdkTransactionCreate->setLineItems($sdkLineItems);

        $sdkTransactionCreate->setCurrency($context->currencyCode);
        $sdkTransactionCreate->setLanguage($context->language);
        $sdkTransactionCreate->setCustomerEmailAddress($context->billingAddress->emailAddress);
        $sdkTransactionCreate->setCustomerId($context->customerId);
        $sdkTransactionCreate->setMerchantReference($context->merchantReference);

        $sdkTransactionCreate->setSuccessUrl($context->successUrl);
        $sdkTransactionCreate->setFailedUrl($context->failedUrl);
        $sdkTransactionCreate->setAutoConfirmationEnabled($context->autoConfirmationEnabled);
        $sdkTransactionCreate->setChargeRetryEnabled($context->chargeRetryEnabled);

        if ($context->spaceViewId !== null) {
            $sdkTransactionCreate->setSpaceViewId($context->spaceViewId);
        }

        if ($context->token) {
            $sdkTransactionCreate->setToken($context->token->id);
        }

        if ($context->tokenizationMode) {
            // Map the PluginCore enum to the SDK's string constant
            $sdkTransactionCreate->setTokenizationMode($context->tokenizationMode->value);
        }

        if ($context->shippingMethod) {
            $sdkTransactionCreate->setShippingMethod($context->shippingMethod);
        }

        try {
            $this->logger->debug("Gateway: Sending CREATE request to SDK (postPaymentTransactions).");
            $sdkTransaction = $this->transactionsService->postPaymentTransactions($context->spaceId, $sdkTransactionCreate);
            $this->logger->debug("Gateway: Transaction created successfully.", ['id' => $sdkTransaction->getId()]);

            return $this->mapToTransaction($sdkTransaction);
        } catch (\Throwable $e) {
            $this->logger->error("Gateway: Failed to create transaction.", [
                'spaceId' => $context->spaceId,
                'exception' => $e,
            ]);
            throw new TransactionException(
                "Unable to create transaction: {$e->getMessage()}",
                new LocalizedString('Unable to create transaction.'),
                $e,
            );
        }
    }

    public function find(int $spaceId, int $transactionId): ?Transaction
    {
        try {
            $sdkTransaction = $this->transactionsService->getPaymentTransactionsId($transactionId, $spaceId, ['billingAddress', 'shippingAddress', 'lineItems', 'token']);
            return $this->mapToTransaction($sdkTransaction);
        } catch (\Throwable $e) {
            if ($e instanceof ApiException && $e->getCode() === 404) {
                $this->logger->debug(
                    'Gateway: Transaction {transactionId} not found in Space {spaceId}.',
                    [
                        'spaceId' => $spaceId,
                        'transactionId' => $transactionId,
                    ],
                );
                return null;
            }

            $this->logger->error(
                'Gateway: Failed to find transaction: {errorMessage}',
                [
                    'errorMessage' => $e->getMessage(),
                    'exception' => $e,
                    'spaceId' => $spaceId,
                    'transactionId' => $transactionId,
                ],
            );
            throw $e;
        }
    }

    public function get(int $spaceId, int $transactionId): Transaction
    {
        $this->logger->debug("Gateway: Reading transaction.", [
            'transactionId' => $transactionId,
            'spaceId' => $spaceId,
        ]);

        try {
            $sdkTransaction = $this->transactionsService->getPaymentTransactionsId($transactionId, $spaceId, ['billingAddress', 'shippingAddress', 'lineItems', 'token']);
            $result = $this->mapToTransaction($sdkTransaction);

            $this->logger->debug("Gateway: Transaction read.", [
                'transactionId' => $transactionId,
                'spaceId' => $spaceId,
                'state' => $result->state->value,
            ]);

            return $result;
        } catch (\Throwable $e) {
            $this->logger->error("Gateway: Failed to read transaction.", [
                'transactionId' => $transactionId,
                'spaceId' => $spaceId,
                'exception' => $e,
            ]);
            throw new TransactionException(
                "Unable to read transaction {$transactionId} in space {$spaceId}: {$e->getMessage()}",
                new LocalizedString('Unable to read transaction.'),
                $e,
            );
        }
    }

    public function getAvailablePaymentMethods(int $spaceId, int $transactionId): PaymentMethodCollection
    {
        $mode = $this->settings->getIntegrationMode()->value;
        $this->logger->debug("Gateway: Fetching payment methods.", [
            'mode' => $mode,
            'transactionId' => $transactionId,
            'spaceId' => $spaceId,
        ]);

        // V2: getPaymentTransactionsIdPaymentMethodConfigurations
        $sdkResults = $this->transactionsService->getPaymentTransactionsIdPaymentMethodConfigurations($transactionId, $mode, $spaceId);
        $items = (is_object($sdkResults) && method_exists($sdkResults, 'getData')) ? $sdkResults->getData() : (array)$sdkResults;
        return new PaymentMethodCollection(...array_map([$this, 'mapToPaymentMethod'], $items));
    }

    public function getPaymentMethodConfigurations(int $spaceId): PaymentMethodCollection
    {
        // Search for active payment method configurations using the V2 query syntax.
        $query = "state:ACTIVE";

        try {
            $results = $this->paymentMethodConfigService->getPaymentMethodConfigurationsSearch($spaceId, null, null, null, null, $query);
            $items = (is_object($results) && method_exists($results, 'getData')) ? $results->getData() : (array)$results;
            $this->logger->debug("Gateway: Fetched payment method configurations.", [
                'count' => count($items),
                'spaceId' => $spaceId,
            ]);

            return new PaymentMethodCollection(...array_map([$this, 'mapToPaymentMethod'], $items));
        } catch (\Throwable $e) {
            $this->logger->error("Gateway: Failed to fetch payment method configurations.", [
                'spaceId' => $spaceId,
                'exception' => $e,
            ]);
            throw new TransactionException(
                "Gateway: Failed to fetch payment method configurations: " . $e->getMessage(),
                new LocalizedString('Failed to fetch payment method configurations.'),
                $e,
            );
        }
    }

    public function getPaymentUrl(int $spaceId, int $transactionId): PaymentUrl
    {
        $mode = $this->settings->getIntegrationMode();
        $this->logger->debug("Gateway: Fetching payment URL.", [
            'mode' => $mode->value,
            'transactionId' => $transactionId,
            'spaceId' => $spaceId,
        ]);

        $url = match ($mode) {
            IntegrationModeEnum::PAYMENT_PAGE => $this->transactionsService
                ->getPaymentTransactionsIdPaymentPageUrl($transactionId, $spaceId),

            IntegrationModeEnum::IFRAME => $this->transactionsService
                ->getPaymentTransactionsIdIframeJavascriptUrl($transactionId, $spaceId),

            IntegrationModeEnum::LIGHTBOX => $this->transactionsService
                ->getPaymentTransactionsIdLightboxJavascriptUrl($transactionId, $spaceId),
        };

        return new PaymentUrl($url);
    }

    private function mapAddress(Address $source): SdkAddressCreate
    {
        $sdkAddressCreate = new SdkAddressCreate();
        $sdkAddressCreate->setCity($source->city);
        $sdkAddressCreate->setCountry($source->country);

        if ($source->familyName !== null) {
            $sdkAddressCreate->setFamilyName($source->familyName);
        }
        if ($source->givenName !== null) {
            $sdkAddressCreate->setGivenName($source->givenName);
        }
        if ($source->organizationName !== null) {
            $sdkAddressCreate->setOrganizationName($source->organizationName);
        }
        if ($source->phoneNumber !== null) {
            $sdkAddressCreate->setPhoneNumber($source->phoneNumber);
        }
        if ($source->postcode !== null) {
            $sdkAddressCreate->setPostcode($source->postcode);
        }
        if ($source->street !== null) {
            $sdkAddressCreate->setStreet($source->street);
        }
        if ($source->emailAddress !== null) {
            $sdkAddressCreate->setEmailAddress($source->emailAddress);
        }
        if ($source->salutation !== null) {
            $sdkAddressCreate->setSalutation($source->salutation);
        }
        if ($source->dateOfBirth !== null) {
            $sdkAddressCreate->setDateOfBirth(\DateTime::createFromImmutable($source->dateOfBirth));
        }
        if ($source->salesTaxNumber !== null) {
            $sdkAddressCreate->setSalesTaxNumber($source->salesTaxNumber);
        }

        return $sdkAddressCreate;
    }

    private function mapLineItem(LineItem $source): SdkLineItemCreate
    {
        $sdkLineItemCreate = new SdkLineItemCreate();
        $sdkLineItemCreate->setUniqueId($source->uniqueId);
        $sdkLineItemCreate->setSku($source->sku);
        $sdkLineItemCreate->setName($source->name);
        $sdkLineItemCreate->setQuantity($source->quantity);
        $sdkLineItemCreate->setAmountIncludingTax($source->amountIncludingTax);
        $sdkLineItemCreate->setShippingRequired($source->shippingRequired);

        if (!empty($source->attributes)) {
            $sdkLineItemCreate->setAttributes($source->attributes);
        }

        $sdkLineItemCreate->setType(match ($source->type) {
            LineItem::TYPE_DISCOUNT => SdkLineItemType::DISCOUNT,
            LineItem::TYPE_SHIPPING => SdkLineItemType::SHIPPING,
            LineItem::TYPE_FEE => SdkLineItemType::FEE,
            default => SdkLineItemType::PRODUCT,
        });

        if (!empty($source->getTaxes())) {
            $taxes = [];
            foreach ($source->getTaxes() as $taxDto) {
                $taxes[] = $this->mapTax($taxDto);
            }
            $sdkLineItemCreate->setTaxes($taxes);
        }
        return $sdkLineItemCreate;
    }

    private function mapTax(Tax $source): SdkTaxCreate
    {
        $sdkTaxCreate = new SdkTaxCreate();
        $sdkTaxCreate->setTitle($source->title);
        $sdkTaxCreate->setRate($source->rate);
        return $sdkTaxCreate;
    }


    public function search(int $spaceId, TransactionSearchCriteria $criteria): TransactionCollection
    {
        $this->logger->debug("Gateway: Searching transactions.", ['spaceId' => $spaceId]);

        // V2 Search: Build query string
        $queryParts = [];
        if (!empty($criteria->filters)) {
            foreach ($criteria->filters as $field => $value) {
                // Simple equality
                $queryParts[] = "$field:$value";
            }
        }
        $queryString = implode(" ", $queryParts);

        // Order
        // V2 expects 'order'? format is unclear.
        // Ignoring sort order for now or using null.
        // Criteria has sortField and sortOrder.
        // The V2 'order' parameter is expected to follow the 'field:DIRECTION' format.
        $order = null;
        if ($criteria->sortField !== null) {
            // V2 expects a colon (':') as the separator for sorting fields and their order.
            $order = $criteria->sortField . ":" . ($criteria->sortOrder ?? 'ASC');
        }

        try {
            $results = $this->transactionsService->getPaymentTransactionsSearch($spaceId, null, $criteria->limit, null, $order, $queryString);
            $items = (is_object($results) && method_exists($results, 'getData')) ? $results->getData() : (array)$results;
            return new TransactionCollection(...array_map([$this, 'mapToTransaction'], $items));
        } catch (\Throwable $e) {
            $this->logger->error("Gateway: Failed to search transactions.", [
                'spaceId' => $spaceId,
                'exception' => $e,
            ]);
            throw new TransactionException(
                "Unable to search transactions: {$e->getMessage()}",
                new LocalizedString('Unable to search transactions.'),
                $e,
            );
        }
    }

    public function update(int $transactionId, int $version, TransactionContext $context): Transaction
    {
        $this->logger->debug("Gateway: Preparing to UPDATE transaction (V2).", ['id' => $transactionId]);

        $sdkTransactionPending = new SdkTransactionPending();
        $sdkTransactionPending->setVersion($version);

        // Map the NEW data from the Context
        if ($context->billingAddress) {
            $sdkTransactionPending->setBillingAddress($this->mapAddress($context->billingAddress));
        }
        if ($context->shippingAddress) {
            $sdkTransactionPending->setShippingAddress($this->mapAddress($context->shippingAddress));
        }
        $sdkTransactionPending->setLineItems(array_map([$this, 'mapLineItem'], $context->lineItems));
        $sdkTransactionPending->setCurrency($context->currencyCode);
        $sdkTransactionPending->setLanguage($context->language);
        if ($context->billingAddress->emailAddress) {
            $sdkTransactionPending->setCustomerEmailAddress($context->billingAddress->emailAddress);
        }
        $sdkTransactionPending->setCustomerId($context->customerId);
        $sdkTransactionPending->setMerchantReference($context->merchantReference);
        $sdkTransactionPending->setSuccessUrl($context->successUrl);
        $sdkTransactionPending->setFailedUrl($context->failedUrl);

        try {
            $this->logger->debug("Gateway: Sending UPDATE request to SDK (patchPaymentTransactionsId).");
            // V2: patchPaymentTransactionsId
            // Arguments: $id, $space, $transaction_pending
            $sdkTransaction = $this->transactionsService->patchPaymentTransactionsId($transactionId, $context->spaceId, $sdkTransactionPending);
            $this->logger->debug("Gateway: Transaction updated successfully.", ['state' => (string) $sdkTransaction->getState()]);

            return $this->mapToTransaction($sdkTransaction);
        } catch (\Throwable $e) {
            $this->logger->error("Gateway: Failed to update transaction.", [
                'transactionId' => $transactionId,
                'spaceId' => $context->spaceId,
                'exception' => $e,
            ]);
            throw new TransactionException(
                "Unable to update transaction {$transactionId} in space {$context->spaceId}: {$e->getMessage()}",
                new LocalizedString('Unable to update transaction.'),
                $e,
            );
        }
    }
}
