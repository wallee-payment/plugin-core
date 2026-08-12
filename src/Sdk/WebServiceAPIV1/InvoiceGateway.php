<?php

declare(strict_types=1);

namespace Wallee\PluginCore\Sdk\WebServiceAPIV1;

use Wallee\PluginCore\Log\DomainLoggerTrait;
use Wallee\PluginCore\Log\LogContext;
use Wallee\PluginCore\Log\LoggerInterface;
use Wallee\PluginCore\Sdk\InvoiceMapperTrait;
use Wallee\PluginCore\Sdk\SdkProvider;
use Wallee\PluginCore\Transaction\Invoice\Exception\InvoiceException;
use Wallee\PluginCore\Transaction\Invoice\Invoice;
use Wallee\PluginCore\Transaction\Invoice\InvoiceCollection;
use Wallee\PluginCore\Transaction\Invoice\InvoiceGatewayInterface;
use Wallee\PluginCore\Transaction\Invoice\InvoiceSearchCriteria;
use Wallee\Sdk\ApiException;
use Wallee\Sdk\Model\CriteriaOperator as SdkCriteriaOperator;
use Wallee\Sdk\Model\EntityQuery as SdkEntityQuery;
use Wallee\Sdk\Model\EntityQueryFilter as SdkEntityQueryFilter;
use Wallee\Sdk\Model\EntityQueryFilterType as SdkEntityQueryFilterType;
use Wallee\Sdk\Model\EntityQueryOrderBy as SdkEntityQueryOrderBy;
use Wallee\Sdk\Model\EntityQueryOrderByType as SdkEntityQueryOrderByType;
use Wallee\Sdk\Service\TransactionInvoiceService as SdkTransactionInvoiceService;

#[LogContext(domain: 'transaction', subdomain: 'invoice')]
class InvoiceGateway implements InvoiceGatewayInterface
{
    use DomainLoggerTrait;
    use InvoiceMapperTrait;

    private SdkTransactionInvoiceService $transactionInvoiceService;

    public function __construct(
        private readonly SdkProvider $sdkProvider,
        LoggerInterface $logger,
    ) {
        $this->initializeLogger($logger);
        $this->transactionInvoiceService = $this->sdkProvider->getService(SdkTransactionInvoiceService::class);
    }

    public function find(int $spaceId, int $invoiceId): ?Invoice
    {
        $this->logger->debug('Gateway: Finding transaction invoice.', [
            'invoiceId' => $invoiceId,
            'spaceId' => $spaceId,
        ]);

        try {
            $sdkInvoice = $this->transactionInvoiceService->read($spaceId, $invoiceId);
            return $this->mapToInvoice($sdkInvoice);
        } catch (\Throwable $e) {
            if ($e instanceof ApiException && $e->getCode() === 404) {
                $this->logger->debug('Gateway: Transaction invoice not found.', [
                    'invoiceId' => $invoiceId,
                    'spaceId' => $spaceId,
                ]);
                return null;
            }

            $this->logger->error('Gateway: Failed to find transaction invoice.', [
                'exception' => $e,
                'invoiceId' => $invoiceId,
                'spaceId' => $spaceId,
            ]);
            throw SdkProvider::wrapException(
                $e,
                InvoiceException::class,
                'read',
                ['spaceId' => $spaceId, 'invoiceId' => $invoiceId],
                'An error occurred while retrieving the transaction invoice.',
            );
        }
    }

    public function get(int $spaceId, int $invoiceId): Invoice
    {
        $this->logger->debug('Gateway: Reading transaction invoice.', [
            'invoiceId' => $invoiceId,
            'spaceId' => $spaceId,
        ]);

        try {
            $sdkInvoice = $this->transactionInvoiceService->read($spaceId, $invoiceId);
            return $this->mapToInvoice($sdkInvoice);
        } catch (\Throwable $e) {
            $this->logger->error('Gateway: Failed to read transaction invoice.', [
                'exception' => $e,
                'invoiceId' => $invoiceId,
                'spaceId' => $spaceId,
            ]);
            throw SdkProvider::wrapException(
                $e,
                InvoiceException::class,
                'read',
                ['spaceId' => $spaceId, 'invoiceId' => $invoiceId],
                'An error occurred while retrieving the transaction invoice.',
            );
        }
    }

    public function search(int $spaceId, InvoiceSearchCriteria $criteria): InvoiceCollection
    {
        $this->logger->debug('Gateway: Searching transaction invoices.', ['spaceId' => $spaceId]);

        $query = new SdkEntityQuery();

        if ($criteria->limit !== null) {
            $query->setNumberOfEntities($criteria->limit);
        }

        if ($criteria->sortField !== null) {
            $orderBy = new SdkEntityQueryOrderBy();
            $orderBy->setFieldName($criteria->sortField);
            $orderBy->setSorting(
                strtoupper($criteria->sortOrder ?? 'DESC') === 'ASC'
                    ? SdkEntityQueryOrderByType::ASC
                    : SdkEntityQueryOrderByType::DESC,
            );
            $query->setOrderBys([$orderBy]);
        }

        if (!empty($criteria->filters)) {
            $filters = [];
            foreach ($criteria->filters as $field => $value) {
                $leaf = new SdkEntityQueryFilter();
                $leaf->setFieldName($field);
                /** @var mixed $value */
                $leaf->setValue($value);
                $leaf->setOperator(SdkCriteriaOperator::EQUALS);
                $leaf->setType(SdkEntityQueryFilterType::LEAF);
                $filters[] = $leaf;
            }

            if (count($filters) === 1) {
                $query->setFilter($filters[0]);
            } else {
                $root = new SdkEntityQueryFilter();
                $root->setType(SdkEntityQueryFilterType::_AND);
                $root->setChildren($filters);
                $query->setFilter($root);
            }
        }

        try {
            $results = $this->transactionInvoiceService->search($spaceId, $query);
            return new InvoiceCollection(...array_map([$this, 'mapToInvoice'], $results));
        } catch (\Throwable $e) {
            $this->logger->error('Gateway: Failed to search transaction invoices.', [
                'exception' => $e,
                'spaceId' => $spaceId,
            ]);
            throw SdkProvider::wrapException(
                $e,
                InvoiceException::class,
                'search',
                ['spaceId' => $spaceId],
                'An error occurred while searching transaction invoices.',
            );
        }
    }
}
