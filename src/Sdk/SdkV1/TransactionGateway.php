<?php

declare(strict_types=1);

namespace Wallee\PluginCore\Sdk\SdkV1;

use Wallee\PluginCore\Address\Address;
use Wallee\PluginCore\LineItem\LineItem;
use Wallee\PluginCore\Log\LoggerInterface;
use Wallee\PluginCore\PaymentMethod\PaymentMethod;
use Wallee\PluginCore\PaymentMethodConfiguration\PaymentMethodConfiguration;
use Wallee\PluginCore\Sdk\SdkProvider;
use Wallee\PluginCore\Settings\IntegrationMode as IntegrationModeEnum;
use Wallee\PluginCore\Settings\Settings;
use Wallee\PluginCore\Tax\Tax;
use Wallee\PluginCore\Transaction\State as StateEnum;
use Wallee\PluginCore\Transaction\Transaction;
use Wallee\PluginCore\Transaction\TransactionContext;
use Wallee\PluginCore\Transaction\TransactionGatewayInterface;
use Wallee\PluginCore\Transaction\TransactionSearchCriteria;
use Wallee\Sdk\Model\Address as SdkAddress;
use Wallee\Sdk\Model\AddressCreate as SdkAddressCreate;
use Wallee\Sdk\Model\CreationEntityState as SdkCreationEntityState;
use Wallee\Sdk\Model\CriteriaOperator as SdkCriteriaOperator;
use Wallee\Sdk\Model\EntityQuery as SdkEntityQuery;
use Wallee\Sdk\Model\EntityQueryOrderBy as SdkEntityQueryOrderBy;
use Wallee\Sdk\Model\EntityQueryOrderByType as SdkEntityQueryOrderByType;
use Wallee\Sdk\Model\EntityQueryFilter as SdkEntityQueryFilter;
use Wallee\Sdk\Model\EntityQueryFilterType as SdkEntityQueryFilterType;
use Wallee\Sdk\Model\LineItemCreate as SdkLineItemCreate;
use Wallee\Sdk\Model\LineItemType as SdkLineItemType;
use Wallee\Sdk\Model\PaymentMethodConfiguration as SdkPaymentMethodConfiguration;
use Wallee\Sdk\Model\TaxCreate as SdkTaxCreate;
use Wallee\Sdk\Model\Transaction as SdkTransaction;
use Wallee\Sdk\Model\TransactionCreate as SdkTransactionCreate;
use Wallee\Sdk\Model\TransactionPending as SdkTransactionPending;
use Wallee\Sdk\Service\PaymentMethodConfigurationService as SdkPaymentMethodConfigurationService;
use Wallee\Sdk\Service\TransactionIframeService as SdkTransactionIframeService;
use Wallee\Sdk\Service\TransactionLightboxService as SdkTransactionLightboxService;
use Wallee\Sdk\Service\TransactionPaymentPageService as SdkTransactionPaymentPageService;
use Wallee\Sdk\Service\TransactionService as SdkTransactionService;
use Wallee\Sdk\Model\LineItem as SdkLineItem;
use Wallee\PluginCore\Token\Token;
use Wallee\PluginCore\Token\State as TokenState;
use Wallee\Sdk\Model\Token as SdkToken;

class TransactionGateway implements TransactionGatewayInterface
{
    private SdkPaymentMethodConfigurationService $paymentMethodConfigService;
    private SdkTransactionService $transactionService;

    public function __construct(
        private readonly SdkProvider $sdkProvider,
        private readonly LoggerInterface $logger,
        private readonly Settings $settings,
    ) {
        $this->transactionService = $this->sdkProvider->getService(SdkTransactionService::class);
        $this->paymentMethodConfigService = $this->sdkProvider->getService(SdkPaymentMethodConfigurationService::class);
    }

    /**
     * Creates a new transaction.
     *
     * @param TransactionContext $context The transaction context.
     * @return Transaction The created transaction.
     * @throws \Exception If the transaction creation fails.
     */
    public function create(TransactionContext $context): Transaction
    {
        $this->logger->debug("Gateway: Preparing to CREATE transaction.", [
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
            $sdkTransactionCreate->setTokenizationMode($context->tokenizationMode->value);
        }

        if ($context->shippingMethod) {
            $sdkTransactionCreate->setShippingMethod($context->shippingMethod);
        }

        try {
            $this->logger->debug("Gateway: Sending CREATE request to SDK.");
            $sdkTransaction = $this->transactionService->create($context->spaceId, $sdkTransactionCreate);
            $this->logger->debug("Gateway: Transaction created successfully.", ['id' => $sdkTransaction->getId()]);

            return $this->mapToTransaction($sdkTransaction);
        } catch (\Exception $e) {
            $this->logger->error("Gateway: Failed to create transaction.", ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Finds a transaction by ID.
     *
     * @param int $spaceId The space ID.
     * @param int $transactionId The transaction ID.
     * @return Transaction|null The transaction, or null if not found.
     */
    public function find(int $spaceId, int $transactionId): ?Transaction
    {
        try {
            $sdkTransaction = $this->transactionService->read($spaceId, $transactionId);
            return $this->mapToTransaction($sdkTransaction);
        } catch (\Exception $e) {
            $this->logger->debug("Gateway: Transaction $transactionId not found in Space $spaceId.");
            return null;
        }
    }


    /**
     * Gets a transaction by ID and throws if failed.
     *
     * @param int $spaceId The space ID.
     * @param int $transactionId The transaction ID.
     * @return Transaction The transaction.
     * @throws \Exception If the transaction cannot be read.
     */
    public function get(int $spaceId, int $transactionId): Transaction
    {
        $this->logger->debug("Gateway: Reading transaction $transactionId from Space $spaceId.");

        try {
            $sdkTransaction = $this->transactionService->read($spaceId, $transactionId);
            $result = $this->mapToTransaction($sdkTransaction);

            $this->logger->debug("Gateway: Transaction state is " . $result->state->value);

            return $result;
        } catch (\Exception $e) {
            $this->logger->error("Gateway: Failed to read transaction.", ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Gets available payment methods for a transaction.
     *
     * @param int $spaceId The space ID.
     * @param int $transactionId The transaction ID.
     * @return PaymentMethod[] The available payment methods.
     */
    public function getAvailablePaymentMethods(int $spaceId, int $transactionId): array
    {
        $mode = $this->settings->getIntegrationMode()->value;
        $this->logger->debug("Gateway: Fetching payment methods for mode $mode.");

        $sdkResults = $this->transactionService->fetchPaymentMethods($spaceId, $transactionId, $mode);
        return array_map([$this, 'mapToPaymentMethod'], $sdkResults);
    }


    /**
     * Gets all active payment method configurations.
     *
     * @param int $spaceId The space ID.
     * @return PaymentMethod[] The payment method configurations.
     */
    public function getPaymentMethodConfigurations(int $spaceId): array
    {
        $sdkEntityQuery = new SdkEntityQuery();
        $sdkEntityQueryFilter = new SdkEntityQueryFilter();
        $sdkEntityQueryFilter->setType(SdkEntityQueryFilterType::LEAF);
        $sdkEntityQueryFilter->setOperator(SdkCriteriaOperator::EQUALS);
        $sdkEntityQueryFilter->setFieldName('state');
        $sdkEntityQueryFilter->setValue(SdkCreationEntityState::ACTIVE);
        $sdkEntityQuery->setFilter($sdkEntityQueryFilter);

        $results = $this->paymentMethodConfigService->search($spaceId, $sdkEntityQuery);
        $this->logger->debug(sprintf("Gateway: Fetched %d payment method configurations.", count($results)));

        return array_map(
            [$this, 'mapToPaymentMethod'],
            $results,
        );
    }


    /**
     * Gets the payment URL for a transaction.
     *
     * @param int $spaceId The space ID.
     * @param int $transactionId The transaction ID.
     * @return string The payment URL.
     */
    public function getPaymentUrl(int $spaceId, int $transactionId): string
    {
        $mode = $this->settings->getIntegrationMode();
        $this->logger->debug("Gateway: Fetching payment URL for mode {$mode->value}.");

        return match ($mode) {
            IntegrationModeEnum::PAYMENT_PAGE => $this->sdkProvider
                ->getService(SdkTransactionPaymentPageService::class)
                ->paymentPageUrl($spaceId, $transactionId),

            IntegrationModeEnum::IFRAME => $this->sdkProvider
                ->getService(SdkTransactionIframeService::class)
                ->javascriptUrl($spaceId, $transactionId),

            IntegrationModeEnum::LIGHTBOX => $this->sdkProvider
                ->getService(SdkTransactionLightboxService::class)
                ->javascriptUrl($spaceId, $transactionId),
        };
    }

    /**
     * Updates an existing transaction.
     *
     * @param int $transactionId The transaction ID.
     * @param int $version The transaction version.
     * @param TransactionContext $context The transaction context.
     * @return Transaction The updated transaction.
     * @throws \Exception If the update fails.
     */
    public function update(int $transactionId, int $version, TransactionContext $context): Transaction
    {
        $this->logger->debug("Gateway: Preparing to UPDATE transaction.", ['id' => $transactionId]);

        $sdkTransactionPending = new SdkTransactionPending();

        $sdkTransactionPending->setId($transactionId);
        $sdkTransactionPending->setVersion($version);

        // Map the NEW data from the Context
        $sdkTransactionPending->setBillingAddress($this->mapAddress($context->billingAddress));
        $sdkTransactionPending->setShippingAddress($context->shippingAddress ? $this->mapAddress($context->shippingAddress) : null);
        $sdkTransactionPending->setLineItems(array_map([$this, 'mapLineItem'], $context->lineItems));
        $sdkTransactionPending->setCurrency($context->currencyCode);
        $sdkTransactionPending->setLanguage($context->language);
        $sdkTransactionPending->setCustomerEmailAddress($context->billingAddress->emailAddress);
        $sdkTransactionPending->setCustomerId($context->customerId);
        $sdkTransactionPending->setMerchantReference($context->merchantReference);
        $sdkTransactionPending->setSuccessUrl($context->successUrl);
        $sdkTransactionPending->setFailedUrl($context->failedUrl);

        try {
            $this->logger->debug("Gateway: Sending UPDATE request to SDK.");
            $sdkTransaction = $this->transactionService->update($context->spaceId, $sdkTransactionPending);
            $this->logger->debug("Gateway: Transaction updated successfully.", ['state' => (string) $sdkTransaction->getState()]);

            return $this->mapToTransaction($sdkTransaction);
        } catch (\Exception $e) {
            $this->logger->error("Gateway: Failed to update transaction.", ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * @inheritDoc
     */
    public function search(int $spaceId, TransactionSearchCriteria $criteria): array
    {
        $this->logger->debug("Gateway: Searching transactions in Space $spaceId.");

        $query = new SdkEntityQuery();

        if ($criteria->limit !== null) {
            $query->setNumberOfEntities($criteria->limit);
        }

        if ($criteria->sortField !== null) {
            $orderBy = new SdkEntityQueryOrderBy();
            $orderBy->setFieldName($criteria->sortField);
            $orderBy->setSorting(
                strtoupper($criteria->sortOrder) === 'ASC'
                    ? SdkEntityQueryOrderByType::ASC
                    : SdkEntityQueryOrderByType::DESC
            );
            $query->setOrderBys([$orderBy]);
        }

        if (!empty($criteria->filters)) {
            $filters = [];
            foreach ($criteria->filters as $field => $value) {
                $leaf = new SdkEntityQueryFilter();
                $leaf->setFieldName($field);
                $leaf->setValue($value);
                $leaf->setOperator(SdkCriteriaOperator::EQUALS);
                $leaf->setType(SdkEntityQueryFilterType::LEAF);
                $filters[] = $leaf;
            }

            if (count($filters) === 1) {
                $query->setFilter($filters[0]);
            } elseif (count($filters) > 1) {
                $root = new SdkEntityQueryFilter();
                $root->setType(SdkEntityQueryFilterType::_AND);
                $root->setChildren($filters);
                $query->setFilter($root);
            }
        }

        try {
            $results = $this->transactionService->search($spaceId, $query);
            return array_map([$this, 'mapToTransaction'], $results);
        } catch (\Exception $e) {
            $this->logger->error("Gateway: Failed to search transactions.", ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Maps a domain Address to an SDK AddressCreate.
     *
     * @param Address $source The source address.
     * @return SdkAddressCreate The SDK address.
     */
    private function mapAddress(Address $source): SdkAddressCreate
    {
        $sdkAddressCreate = new SdkAddressCreate();
        $sdkAddressCreate->setCity($source->city);
        $sdkAddressCreate->setCountry($source->country);
        $sdkAddressCreate->setFamilyName($source->familyName);
        $sdkAddressCreate->setGivenName($source->givenName);
        $sdkAddressCreate->setOrganizationName($source->organizationName);
        $sdkAddressCreate->setPhoneNumber($source->phoneNumber);
        $sdkAddressCreate->setPostcode($source->postcode);
        $sdkAddressCreate->setStreet($source->street);
        $sdkAddressCreate->setEmailAddress($source->emailAddress);
        $sdkAddressCreate->setSalutation($source->salutation);
        $sdkAddressCreate->setDateOfBirth($source->dateOfBirth);
        $sdkAddressCreate->setSalesTaxNumber($source->salesTaxNumber);
        return $sdkAddressCreate;
    }

    /**
     * Maps a domain LineItem to an SDK LineItemCreate.
     *
     * @param LineItem $source The source line item.
     * @return SdkLineItemCreate The SDK line item.
     */
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

    /**
     * Maps an SDK PaymentMethodConfiguration to a domain object.
     *
     * @param SdkPaymentMethodConfiguration $sdkPaymentMethodConfiguration The SDK object.
     * @return PaymentMethod The domain object.
     */
    private function mapToPaymentMethod(SdkPaymentMethodConfiguration $sdkPaymentMethodConfiguration): PaymentMethod
    {
        return new PaymentMethod(
            id: (int) $sdkPaymentMethodConfiguration->getId(),
            spaceId: (int) $sdkPaymentMethodConfiguration->getLinkedSpaceId(),
            state: (string) $sdkPaymentMethodConfiguration->getState(),
            name: $this->resolveLocalization($sdkPaymentMethodConfiguration->getResolvedTitle() ?? $sdkPaymentMethodConfiguration->getName()),
            title: $sdkPaymentMethodConfiguration->getResolvedTitle() ?? [],
            description: $this->resolveLocalization($sdkPaymentMethodConfiguration->getResolvedDescription() ?? $sdkPaymentMethodConfiguration->getDescription()),
            descriptionMap: $sdkPaymentMethodConfiguration->getResolvedDescription() ?? $sdkPaymentMethodConfiguration->getDescription() ?? [],
            sortOrder: (int) $sdkPaymentMethodConfiguration->getSortOrder(),
            imageUrl: $sdkPaymentMethodConfiguration->getResolvedImageUrl(),
        );
    }

    /**
     * Maps a domain Tax to an SDK TaxCreate.
     *
     * @param Tax $source The source tax.
     * @return SdkTaxCreate The SDK tax.
     */
    private function mapTax(Tax $source): SdkTaxCreate
    {
        $sdkTaxCreate = new SdkTaxCreate();
        $sdkTaxCreate->setTitle($source->title);
        $sdkTaxCreate->setRate($source->rate);
        return $sdkTaxCreate;
    }

    /**
     * Maps an SDK Transaction to a domain Transaction.
     *
     * @param SdkTransaction $sdkTransaction The SDK transaction.
     * @return Transaction The domain transaction.
     */
    private function mapToTransaction(SdkTransaction $sdkTransaction): Transaction
    {
        $domain = new Transaction();
        $domain->id = $sdkTransaction->getId();
        $domain->spaceId = $sdkTransaction->getLinkedSpaceId();
        $domain->version = $sdkTransaction->getVersion();

        $domain->state = match ((string) $sdkTransaction->getState()) {
            'PENDING' => StateEnum::PENDING,
            'CONFIRMED' => StateEnum::CONFIRMED,
            'PROCESSING' => StateEnum::PROCESSING,
            'FAILED' => StateEnum::FAILED,
            'AUTHORIZED' => StateEnum::AUTHORIZED,
            'VOIDED' => StateEnum::VOIDED,
            'COMPLETED' => StateEnum::COMPLETED,
            'FULFILL' => StateEnum::FULFILL,
            'DECLINE' => StateEnum::DECLINE,
            default => StateEnum::PENDING,
        };

        $domain->merchantReference = $sdkTransaction->getMerchantReference();
        $domain->customerId = $sdkTransaction->getCustomerId();
        $domain->currency = $sdkTransaction->getCurrency();

        $domain->authorizedAmount = $sdkTransaction->getAuthorizationAmount();
        $domain->refundedAmount = $sdkTransaction->getRefundedAmount();

        if ($sdkTransaction->getLineItems()) {
            $domain->lineItems = array_map([$this, 'mapToLineItem'], $sdkTransaction->getLineItems());
        }

        $domain->createdOn = $this->toDateTimeImmutable($sdkTransaction->getCreatedOn());
        $domain->authorizedOn = $this->toDateTimeImmutable($sdkTransaction->getAuthorizedOn());
        $domain->completedOn = $this->toDateTimeImmutable($sdkTransaction->getCompletedOn());
        $domain->failedOn = $this->toDateTimeImmutable($sdkTransaction->getFailedOn());
        $domain->processingOn = $this->toDateTimeImmutable($sdkTransaction->getProcessingOn());

        $domain->userFailureMessage = $sdkTransaction->getUserFailureMessage();

        if ($sdkTransaction->getFailureReason()) {
            $reason = $sdkTransaction->getFailureReason();
            $d = $reason->getDescription() ?? [];
            $n = $reason->getName() ?? [];
            $lang = $sdkTransaction->getLanguage();
            // Try description in lang, then en-US, then first available key. Fallback to name.
            $domain->failureReason = $d[$lang] ?? $d['en-US'] ?? (reset($d) ?: ($n[$lang] ?? $n['en-US'] ?? (reset($n) ?: null)));
        }

        if ($sdkTransaction->getToken()) {
            $domain->token = $this->mapToToken($sdkTransaction->getToken());
        }

        if ($sdkTransaction->getBillingAddress()) {
            $domain->billingAddress = $this->mapToAddress($sdkTransaction->getBillingAddress());
        }

        if ($sdkTransaction->getShippingAddress()) {
            $domain->shippingAddress = $this->mapToAddress($sdkTransaction->getShippingAddress());
        }

        return $domain;
    }

    /**
     * Maps an SDK LineItem to a Domain LineItem.
     *
     * @param SdkLineItem $sdkItem
     * @return LineItem
     */
    private function mapToLineItem(SdkLineItem $sdkItem): LineItem
    {
        $item = new LineItem();
        $item->uniqueId = $sdkItem->getUniqueId();
        $item->sku = $sdkItem->getSku();
        $item->name = $sdkItem->getName();
        $item->quantity = $sdkItem->getQuantity();
        $item->amountIncludingTax = $sdkItem->getAmountIncludingTax();
        $item->type = match ($sdkItem->getType()) {
            SdkLineItemType::DISCOUNT => LineItem::TYPE_DISCOUNT,
            SdkLineItemType::SHIPPING => LineItem::TYPE_SHIPPING,
            SdkLineItemType::FEE => LineItem::TYPE_FEE,
            default => LineItem::TYPE_PRODUCT,
        };
        // Attributes and taxes could be mapped but are not strictly required for current validation needs.
        // If needed, can be added later.

        return $item;
    }

    /**
     * Helper to convert mutable DateTime to Immutable.
     */
    private function toDateTimeImmutable(?\DateTime $date): ?\DateTimeImmutable
    {
        if (!$date) {
            return null;
        }
        return \DateTimeImmutable::createFromMutable($date);
    }

    /**
     * Resolves a localized string (which might be an array) to a single string.
     *
     * @param array<string, string>|string|null $input
     * @return string|null
     */
    private function resolveLocalization(array|string|null $input): ?string
    {
        if (is_string($input) || is_null($input)) {
            return $input;
        }

        if (is_array($input)) {
            // Prefer English
            if (isset($input['en-US'])) {
                return $input['en-US'];
            }
            if (isset($input['en-GB'])) {
                return $input['en-GB'];
            }
            // Fallback to first available
            return reset($input) ?: null;
        }

        return null;
    }

    /**
     * Maps an SDK Token to a domain Token.
     *
     * @param SdkToken $sdkToken
     * @return Token
     */
    private function mapToToken(SdkToken $sdkToken): Token
    {
        $token = new Token();
        $token->id = $sdkToken->getId();
        $token->spaceId = $sdkToken->getLinkedSpaceId();
        $token->version = $sdkToken->getVersion();

        $token->state = match ((string) $sdkToken->getState()) {
            'ACTIVE' => TokenState::ACTIVE,
            'CREATE' => TokenState::CREATE,
            'DELETED' => TokenState::DELETED,
            'DELETING' => TokenState::DELETING,
            'INACTIVE' => TokenState::INACTIVE,
            default => TokenState::ACTIVE,
        };

        return $token;
    }

    /**
     * Maps an SDK Address to a domain Address.
     *
     * @param SdkAddress $sdkAddress
     * @return Address
     */
    private function mapToAddress(SdkAddress $sdkAddress): Address
    {
        $address = new Address();
        $address->city = $sdkAddress->getCity();
        $address->country = $sdkAddress->getCountry();
        $address->familyName = $sdkAddress->getFamilyName();
        $address->givenName = $sdkAddress->getGivenName();
        $address->organizationName = $sdkAddress->getOrganizationName();
        $address->phoneNumber = $sdkAddress->getPhoneNumber();
        $address->postcode = $sdkAddress->getPostcode();
        $address->street = $sdkAddress->getStreet();
        $address->emailAddress = $sdkAddress->getEmailAddress();
        $address->salutation = $sdkAddress->getSalutation();
        $address->dateOfBirth = $this->toDateTimeImmutable($sdkAddress->getDateOfBirth());
        $address->salesTaxNumber = $sdkAddress->getSalesTaxNumber();
        return $address;
    }
}
