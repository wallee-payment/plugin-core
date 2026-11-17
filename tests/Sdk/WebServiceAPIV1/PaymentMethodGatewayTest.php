<?php

declare(strict_types=1);

namespace Wallee\PluginCore\Tests\Sdk\WebServiceAPIV1;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Wallee\PluginCore\Log\LoggerInterface;
use Wallee\PluginCore\PaymentMethod\Exception\PaymentMethodException;
use Wallee\PluginCore\PaymentMethod\PaymentMethod;
use Wallee\PluginCore\PaymentMethod\State;
use Wallee\PluginCore\Sdk\SdkProvider;
use Wallee\PluginCore\Sdk\WebServiceAPIV1\PaymentMethodGateway;
use Wallee\Sdk\Model\CreationEntityState;
use Wallee\Sdk\Model\PaymentMethodConfiguration as SdkPaymentMethodConfiguration;
use Wallee\Sdk\Service\PaymentMethodConfigurationService as SdkPaymentMethodConfigurationService;

class PaymentMethodGatewayTest extends TestCase
{
    private PaymentMethodGateway $gateway;
    private MockObject|LoggerInterface $logger;
    private MockObject|SdkProvider $sdkProvider;
    private MockObject|SdkPaymentMethodConfigurationService $service;

    /**
     * @param list<array<string, mixed>> $logged Filled with the context of each missing-locale entry.
     */
    private function captureMissingLocaleLogs(array &$logged): void
    {
        $this->logger->method('debug')
            ->willReturnCallback(static function (string $message, array $context) use (&$logged): void {
                if (str_contains($message, 'missing for a requested locale')) {
                    $logged[] = $context;
                }
            });
    }

    /**
     * @param array<string, string> $title
     */
    private function config(int $id, array $title): SdkPaymentMethodConfiguration
    {
        $config = new SdkPaymentMethodConfiguration();
        $config->setId($id);
        $config->setLinkedSpaceId(1);
        $config->setState(CreationEntityState::ACTIVE);
        $config->setResolvedTitle($title);
        $config->setSortOrder(1);

        return $config;
    }

    protected function setUp(): void
    {
        $this->sdkProvider = $this->createMock(SdkProvider::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->service = $this->createMock(SdkPaymentMethodConfigurationService::class);

        $this->sdkProvider->method('getService')
            ->with(SdkPaymentMethodConfigurationService::class)
            ->willReturn($this->service);

        $this->gateway = new PaymentMethodGateway($this->sdkProvider, $this->logger);
    }

    public function testFetchByIdLogsARequestedLocaleMissingFromTheResponse(): void
    {
        $this->service->method('read')->with(1, 11)->willReturn($this->config(11, ['en-US' => 'Card']));
        $logged = [];
        $this->captureMissingLocaleLogs($logged);

        $method = $this->gateway->fetchById(1, 11, ['it-IT']);

        $this->assertSame('Card', $method->title->localize('it-IT'));
        $this->assertCount(1, $logged);
        $this->assertSame('it-IT', $logged[0]['locale']);
    }

    public function testFetchByIdReturnsPaymentMethod(): void
    {
        $spaceId = 1;
        $id = 10;

        $sdkConfig = new SdkPaymentMethodConfiguration();
        $sdkConfig->setId($id);
        $sdkConfig->setLinkedSpaceId($spaceId);
        $sdkConfig->setState(CreationEntityState::ACTIVE);
        $sdkConfig->setResolvedTitle(['en-US' => 'Credit Card', 'de-DE' => 'Kreditkarte']);
        $sdkConfig->setResolvedDescription(['en-US' => 'Pay significantly later']);
        $sdkConfig->setSortOrder(5);
        $sdkConfig->setResolvedImageUrl('http://image.url');

        $this->service->expects($this->once())
            ->method('read')
            ->with($spaceId, $id)
            ->willReturn($sdkConfig);

        $result = $this->gateway->fetchById($spaceId, $id);

        $this->assertInstanceOf(PaymentMethod::class, $result);
        $this->assertEquals($id, $result->id);
        $this->assertEquals($spaceId, $result->spaceId);
        $this->assertEquals(State::ACTIVE, $result->state);
        $this->assertEquals('ACTIVE', $result->state->value);
        $this->assertEquals('Credit Card', $result->title->localize('en-US'));
        $this->assertEquals('Pay significantly later', $result->description->localize('en-US'));
        $this->assertEquals(5, $result->sortOrder);
        $this->assertEquals('http://image.url', $result->imageUrl);
    }

    public function testFetchByIdThrowsExceptionIfNotFound(): void
    {
        $this->expectException(PaymentMethodException::class);
        // wrapException() composes one standard message; the identifying context
        // and the SDK's own reason are what matter and both survive.
        $this->expectExceptionMessage('paymentMethodId=10');

        $this->service->expects($this->once())
            ->method('read')
            ->willThrowException(new \Exception("Not found"));

        $this->gateway->fetchById(1, 10);
    }

    public function testFetchBySpaceIdLogsARequestedLocaleMissingFromTheResponse(): void
    {
        $this->service->method('search')->willReturn([
            $this->config(11, ['de-DE' => 'Karte', 'en-US' => 'Card']),
            $this->config(12, ['de-DE' => 'Rechnung', 'en-US' => 'Invoice', 'sv-SE' => 'Faktura']),
        ]);
        $logged = [];
        $this->captureMissingLocaleLogs($logged);

        $this->gateway->fetchBySpaceId(1, null, ['de_DE', 'sv_SE', 'sv-SE']);

        $this->assertCount(1, $logged);
        $this->assertSame('sv-SE', $logged[0]['locale']);
        $this->assertSame(['de-DE', 'en-US', 'sv-SE'], $logged[0]['returnedLocales']);
        $this->assertSame([11], $logged[0]['paymentMethodIds']);
    }

    public function testFetchBySpaceIdReturnsArrayOfPaymentMethods(): void
    {
        $spaceId = 1;

        $sdkConfig1 = new SdkPaymentMethodConfiguration();
        $sdkConfig1->setId(11);
        $sdkConfig1->setLinkedSpaceId($spaceId);
        $sdkConfig1->setState(CreationEntityState::ACTIVE);
        $sdkConfig1->setResolvedTitle(['en-US' => 'Test Method']);
        $sdkConfig1->setSortOrder(1);

        $this->service->expects($this->once())
            ->method('search')
            ->with($spaceId, $this->anything()) // Helper specific to PHPUnit mock to match any argument
            ->willReturn([$sdkConfig1]);

        $results = $this->gateway->fetchBySpaceId($spaceId);

        $this->assertCount(1, $results);
        $this->assertInstanceOf(PaymentMethod::class, $results->all()[0]);
        $this->assertEquals(11, $results->all()[0]->id);
    }

    public function testFetchBySpaceIdReturnsEveryLocaleWhicheverLocalesArePassed(): void
    {
        $title = ['de-DE' => 'Kredit-/Debitkarte', 'en-US' => 'Credit / Debit Card', 'fr-FR' => 'Carte de crédit / débit'];
        $this->service->expects($this->once())
            ->method('search')
            ->willReturn([$this->config(11, $title)]);
        $logged = [];
        $this->captureMissingLocaleLogs($logged);

        $results = $this->gateway->fetchBySpaceId(1, null, ['de_DE', 'de-CH']);

        $this->assertSame($title, $results->first()->title->jsonSerialize());
        $this->assertSame('Kredit-/Debitkarte', $results->first()->title->localize('de_CH'));
        $this->assertCount(0, $logged);
    }

    public function testNoLocalesLogsNothing(): void
    {
        $this->service->method('search')->willReturn([$this->config(11, ['en-US' => 'Card'])]);
        $logged = [];
        $this->captureMissingLocaleLogs($logged);

        $this->gateway->fetchBySpaceId(1);

        $this->assertCount(0, $logged);
    }
}
