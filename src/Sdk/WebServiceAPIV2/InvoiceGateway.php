<?php

declare(strict_types=1);

namespace Wallee\PluginCore\Sdk\WebServiceAPIV2;

use Wallee\PluginCore\Localization\LocalizedString;
use Wallee\PluginCore\Log\DomainLoggerTrait;
use Wallee\PluginCore\Log\LogContext;
use Wallee\PluginCore\Log\LoggerInterface;
use Wallee\PluginCore\Sdk\SdkProvider;
use Wallee\PluginCore\Sdk\InvoiceMapperTrait;
use Wallee\PluginCore\Transaction\Invoice\Exception\InvoiceException;
use Wallee\PluginCore\Transaction\Invoice\Invoice;
use Wallee\PluginCore\Transaction\Invoice\InvoiceCollection;
use Wallee\PluginCore\Transaction\Invoice\InvoiceGatewayInterface;
use Wallee\PluginCore\Transaction\Invoice\InvoiceSearchCriteria;
use Wallee\Sdk\ApiException;
use Wallee\Sdk\Service\TransactionInvoicesService as SdkTransactionInvoicesService;

#[LogContext(domain: 'transaction', subdomain: 'invoice')]
class InvoiceGateway implements InvoiceGatewayInterface
{
    use DomainLoggerTrait;
    use InvoiceMapperTrait;

    private SdkTransactionInvoicesService $transactionInvoicesService;

    public function __construct(
        private readonly SdkProvider $sdkProvider,
        LoggerInterface $logger,
    ) {
        $this->initializeLogger($logger);
        $this->transactionInvoicesService = $this->sdkProvider->getService(SdkTransactionInvoicesService::class);
    }

    public function find(int $spaceId, int $invoiceId): ?Invoice
    {
        try {
            $sdkInvoice = $this->transactionInvoicesService->getPaymentTransactionsInvoicesId($invoiceId, $spaceId);
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
            throw new InvoiceException(
                "Failed to find transaction invoice {$invoiceId}: " . $e->getMessage(),
                new LocalizedString('An error occurred while retrieving the transaction invoice.'),
                $e,
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
            $sdkInvoice = $this->transactionInvoicesService->getPaymentTransactionsInvoicesId($invoiceId, $spaceId);
            return $this->mapToInvoice($sdkInvoice);
        } catch (\Throwable $e) {
            $this->logger->error('Gateway: Failed to read transaction invoice.', [
                'exception' => $e,
                'invoiceId' => $invoiceId,
                'spaceId' => $spaceId,
            ]);
            throw new InvoiceException(
                "Failed to read transaction invoice {$invoiceId}: " . $e->getMessage(),
                new LocalizedString('An error occurred while retrieving the transaction invoice.'),
                $e,
            );
        }
    }

    public function search(int $spaceId, InvoiceSearchCriteria $criteria): InvoiceCollection
    {
        $this->logger->debug('Gateway: Searching transaction invoices.', ['spaceId' => $spaceId]);

        // V2 Search: build the 'field:value' query string.
        $queryParts = [];
        foreach ($criteria->filters as $field => $value) {
            $queryParts[] = "$field:$value";
        }
        $queryString = implode(' ', $queryParts);

        // The V2 'order' parameter follows the 'field:DIRECTION' format.
        $order = null;
        if ($criteria->sortField !== null) {
            $order = $criteria->sortField . ':' . ($criteria->sortOrder ?? 'DESC');
        }

        try {
            $results = $this->transactionInvoicesService->getPaymentTransactionsInvoicesSearch($spaceId, null, $criteria->limit, null, $order, $queryString);
            $items = (is_object($results) && method_exists($results, 'getData')) ? $results->getData() : (array)$results;
            return new InvoiceCollection(...array_map([$this, 'mapToInvoice'], $items));
        } catch (\Throwable $e) {
            $this->logger->error('Gateway: Failed to search transaction invoices.', [
                'exception' => $e,
                'spaceId' => $spaceId,
            ]);
            throw new InvoiceException(
                'Failed to search transaction invoices: ' . $e->getMessage(),
                new LocalizedString('An error occurred while searching transaction invoices.'),
                $e,
            );
        }
    }
}
