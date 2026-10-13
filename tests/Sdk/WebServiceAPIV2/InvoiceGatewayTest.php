<?php

declare(strict_types=1);

namespace Wallee\PluginCore\Tests\Sdk\WebServiceAPIV2;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Wallee\PluginCore\Log\LoggerInterface;
use Wallee\PluginCore\Sdk\SdkProvider;
use Wallee\PluginCore\Sdk\WebServiceAPIV2\InvoiceGateway;
use Wallee\PluginCore\Transaction\Invoice\InvoiceSearchCriteria;
use Wallee\Sdk\Model\LineItem as SdkLineItem;
use Wallee\Sdk\Model\TransactionInvoice as SdkTransactionInvoice;
use Wallee\Sdk\Model\InvoiceSearchResponse as SdkInvoiceSearchResponse;
use Wallee\Sdk\Service\TransactionInvoicesService as SdkTransactionInvoicesService;

class InvoiceGatewayTest extends TestCase
{
    private InvoiceGateway $gateway;
    private MockObject|SdkTransactionInvoicesService $invoiceService;

    protected function setUp(): void
    {
        $sdkProvider = $this->createMock(SdkProvider::class);
        $this->invoiceService = $this->createMock(SdkTransactionInvoicesService::class);
        $sdkProvider->method('getService')
            ->with(SdkTransactionInvoicesService::class)
            ->willReturn($this->invoiceService);

        $this->gateway = new InvoiceGateway($sdkProvider, $this->createMock(LoggerInterface::class));
    }

    private function sdkInvoice(int $id): SdkTransactionInvoice
    {
        $item = new SdkLineItem();
        $item->setUniqueId('product-1');
        $item->setSku('product-1');
        $item->setName('Product 1');
        $item->setQuantity(1.0);
        $item->setAmountIncludingTax(40.0);
        $item->setUnitPriceIncludingTax(40.0);

        $invoice = new SdkTransactionInvoice();
        $invoice->setId($id);
        $invoice->setLineItems([$item]);

        return $invoice;
    }

    public function testFindExpandsLineItemsAndMapsThem(): void
    {
        $this->invoiceService->expects($this->once())
            ->method('getPaymentTransactionsInvoicesId')
            ->with(7, 1, ['lineItems'])
            ->willReturn($this->sdkInvoice(7));

        $invoice = $this->gateway->find(1, 7);

        $this->assertCount(1, $invoice->lineItems);
        $this->assertSame('product-1', $invoice->lineItems->first()->uniqueId);
    }

    public function testGetExpandsLineItems(): void
    {
        $this->invoiceService->expects($this->once())
            ->method('getPaymentTransactionsInvoicesId')
            ->with(7, 1, ['lineItems'])
            ->willReturn($this->sdkInvoice(7));

        $this->assertCount(1, $this->gateway->get(1, 7)->lineItems);
    }

    public function testSearchExpandsLineItems(): void
    {
        $response = new SdkInvoiceSearchResponse();
        $response->setData([$this->sdkInvoice(8)]);

        $this->invoiceService->expects($this->once())
            ->method('getPaymentTransactionsInvoicesSearch')
            ->with(1, ['lineItems'])
            ->willReturn($response);

        $result = $this->gateway->search(1, new InvoiceSearchCriteria());

        $this->assertCount(1, $result);
        $this->assertCount(1, $result->first()->lineItems);
    }
}
