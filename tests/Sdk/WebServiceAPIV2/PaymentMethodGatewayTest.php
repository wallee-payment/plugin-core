<?php

declare(strict_types=1);

namespace Wallee\PluginCore\Tests\Sdk\WebServiceAPIV2;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Wallee\PluginCore\Log\LoggerInterface;
use Wallee\PluginCore\PaymentMethod\Exception\PaymentMethodException;
use Wallee\PluginCore\PaymentMethod\PaymentMethod;
use Wallee\PluginCore\PaymentMethod\State;
use Wallee\PluginCore\Sdk\SdkProvider;
use Wallee\PluginCore\Sdk\WebServiceAPIV2\PaymentMethodGateway;
use Wallee\Sdk\Model\PaymentMethodConfiguration as SdkPaymentMethodConfiguration;
use Wallee\Sdk\Model\CreationEntityState as SdkCreationEntityState;
use Wallee\Sdk\Model\PaymentMethodConfigurationSearchResponse as SdkPaymentMethodConfigurationSearchResponse;
use Wallee\Sdk\Service\PaymentMethodConfigurationsService as SdkPaymentMethodConfigurationService;

class PaymentMethodGatewayTest extends TestCase
{
    private PaymentMethodGateway $gateway;
    private MockObject|SdkProvider $sdkProvider;
    private MockObject|LoggerInterface $logger;
    private MockObject|SdkPaymentMethodConfigurationService $service;

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

    public function testFetchByIdReturnsPaymentMethod(): void
    {
        $spaceId = 1;
        $id = 10;

        $sdkConfig = new SdkPaymentMethodConfiguration();
        $sdkConfig->setId($id);
        $sdkConfig->setLinkedSpaceId($spaceId);
        $sdkConfig->setState(SdkCreationEntityState::ACTIVE);
        $sdkConfig->setResolvedTitle(['en-US' => 'Credit Card', 'de-DE' => 'Kreditkarte']);
        $sdkConfig->setResolvedDescription(['en-US' => 'Pay significantly later']);
        $sdkConfig->setSortOrder(5);
        $sdkConfig->setResolvedImageUrl('http://image.url');

        // V2: getPaymentMethodConfigurationsId($id, $space)
        $this->service->expects($this->once())
            ->method('getPaymentMethodConfigurationsId')
            ->with($id, $spaceId)
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
            ->method('getPaymentMethodConfigurationsId')
            ->willThrowException(new \Exception("Not found"));

        $this->gateway->fetchById(1, 10);
    }

    /**
     * Wraps configurations in the paged response shape the SDK actually returns.
     *
     * @param list<SdkPaymentMethodConfiguration> $configs
     */
    private function searchResponse(array $configs, bool $hasMore = false): SdkPaymentMethodConfigurationSearchResponse
    {
        $response = new SdkPaymentMethodConfigurationSearchResponse();
        $response->setData($configs);
        $response->setHasMore($hasMore);

        return $response;
    }

    public function testFetchBySpaceIdReturnsArrayOfPaymentMethods(): void
    {
        $spaceId = 1;

        $sdkConfig1 = new SdkPaymentMethodConfiguration();
        $sdkConfig1->setId(11);
        $sdkConfig1->setLinkedSpaceId($spaceId);
        $sdkConfig1->setState(SdkCreationEntityState::ACTIVE);
        $sdkConfig1->setResolvedTitle(['en-US' => 'Test Method']);
        $sdkConfig1->setSortOrder(1);

        // V2 Search: getPaymentMethodConfigurationsSearch($space, $expand, $limit, $offset, $order, $query)
        $this->service->expects($this->once())
            ->method('getPaymentMethodConfigurationsSearch')
            ->with($spaceId, null, 100, 0, null, '-state:DELETED')
            ->willReturn($this->searchResponse([$sdkConfig1]));

        $results = $this->gateway->fetchBySpaceId($spaceId);

        $this->assertCount(1, $results);
        $this->assertInstanceOf(PaymentMethod::class, $results->all()[0]);
        $this->assertEquals(11, $results->all()[0]->id);
    }

    public function testFetchBySpaceIdPagesUntilTheApiReportsNoMore(): void
    {
        $spaceId = 1;

        $makeConfig = static function (int $id) use ($spaceId): SdkPaymentMethodConfiguration {
            $config = new SdkPaymentMethodConfiguration();
            $config->setId($id);
            $config->setLinkedSpaceId($spaceId);
            $config->setState(SdkCreationEntityState::ACTIVE);
            $config->setResolvedTitle(['en-US' => "Method $id"]);
            $config->setSortOrder(1);

            return $config;
        };

        $firstPage = new SdkPaymentMethodConfigurationSearchResponse();
        $firstPage->setData([$makeConfig(11)]);
        $firstPage->setHasMore(true);

        $secondPage = new SdkPaymentMethodConfigurationSearchResponse();
        $secondPage->setData([$makeConfig(12)]);
        $secondPage->setHasMore(false);

        $this->service->expects($this->exactly(2))
            ->method('getPaymentMethodConfigurationsSearch')
            ->willReturnCallback(
                static function (int $space, $expand, int $limit, int $offset, $order, string $query) use ($spaceId, $firstPage, $secondPage) {
                    self::assertSame($spaceId, $space);
                    self::assertSame(100, $limit);
                    self::assertSame('-state:DELETED', $query);

                    return $offset === 0 ? $firstPage : $secondPage;
                },
            );

        $results = $this->gateway->fetchBySpaceId($spaceId);

        $this->assertCount(2, $results);
        $this->assertEquals([11, 12], array_map(static fn (PaymentMethod $method) => $method->id, $results->all()));
    }

    /**
     * Builds one configuration as API V2 returns it for a single Accept-Language.
     *
     * @param array<string, string> $title
     * @param array<string, string>|null $description
     */
    private function config(int $id, array $title, ?array $description = null, int $sortOrder = 1): SdkPaymentMethodConfiguration
    {
        $config = new SdkPaymentMethodConfiguration();
        $config->setId($id);
        $config->setLinkedSpaceId(1);
        $config->setState(SdkCreationEntityState::ACTIVE);
        $config->setResolvedTitle($title);
        if ($description !== null) {
            $config->setResolvedDescription($description);
        }
        $config->setSortOrder($sortOrder);

        return $config;
    }

    /**
     * Routes getLocalizedService() to one service mock per locale and records each request.
     *
     * @param array<string, SdkPaymentMethodConfigurationService> $servicesByLocale
     * @param list<string> $requestedLocales Filled with each locale asked for, in order.
     */
    private function routeLocalizedServices(array $servicesByLocale, array &$requestedLocales): void
    {
        $this->sdkProvider->method('getLocalizedService')
            ->willReturnCallback(
                static function (string $class, string $locale) use ($servicesByLocale, &$requestedLocales) {
                    self::assertSame(SdkPaymentMethodConfigurationService::class, $class);
                    self::assertArrayHasKey($locale, $servicesByLocale, "Unexpected locale $locale requested.");
                    $requestedLocales[] = $locale;

                    return $servicesByLocale[$locale];
                },
            );
    }

    /**
     * A service mock answering a search with the given pages, keyed by offset.
     *
     * @param array<int, SdkPaymentMethodConfigurationSearchResponse> $pagesByOffset
     */
    private function searchService(array $pagesByOffset): SdkPaymentMethodConfigurationService
    {
        $service = $this->createMock(SdkPaymentMethodConfigurationService::class);
        $service->expects($this->exactly(count($pagesByOffset)))
            ->method('getPaymentMethodConfigurationsSearch')
            ->willReturnCallback(
                static function (int $space, $expand, int $limit, int $offset, $order, string $query) use ($pagesByOffset) {
                    self::assertSame(1, $space);
                    self::assertSame(100, $limit);
                    self::assertSame('-state:DELETED', $query);
                    self::assertArrayHasKey($offset, $pagesByOffset);

                    return $pagesByOffset[$offset];
                },
            );

        return $service;
    }

    public function testFetchBySpaceIdMergesOneSearchPerLocaleIntoMultiLocaleMaps(): void
    {
        $requested = [];
        $this->routeLocalizedServices([
            'de-DE' => $this->searchService([0 => $this->searchResponse([
                $this->config(11, ['de-DE' => 'Kredit-/Debitkarte'], ['de-DE' => 'Mit Karte bezahlen'], 3),
            ])]),
            'fr-FR' => $this->searchService([0 => $this->searchResponse([
                $this->config(11, ['fr-FR' => 'Carte de crédit / débit'], ['fr-FR' => 'Payer par carte'], 3),
            ])]),
        ], $requested);
        // Every locale is fetched through its own localized service, never the shared one.
        $this->service->expects($this->never())->method('getPaymentMethodConfigurationsSearch');

        $results = $this->gateway->fetchBySpaceId(1, null, ['de_DE', 'fr-FR']);

        $this->assertSame(['de-DE', 'fr-FR'], $requested);
        $this->assertCount(1, $results);
        $method = $results->first();
        $this->assertSame(
            ['de-DE' => 'Kredit-/Debitkarte', 'fr-FR' => 'Carte de crédit / débit'],
            $method->title->jsonSerialize(),
        );
        $this->assertSame('Kredit-/Debitkarte', $method->title->localize('de_DE'));
        $this->assertSame('Kredit-/Debitkarte', $method->title->localize('de-CH'));
        $this->assertSame('Carte de crédit / débit', $method->title->localize('fr_FR'));
        $this->assertSame('Payer par carte', $method->description->localize('fr-FR'));
        $this->assertSame(3, $method->sortOrder);
    }

    public function testFetchBySpaceIdNormalizesAndDeduplicatesLocales(): void
    {
        $requested = [];
        $this->routeLocalizedServices([
            'en-US' => $this->searchService([0 => $this->searchResponse([$this->config(11, ['en-US' => 'Credit / Debit Card'])])]),
            'de-DE' => $this->searchService([0 => $this->searchResponse([$this->config(11, ['de-DE' => 'Kredit-/Debitkarte'])])]),
        ], $requested);
        // en-US is requested explicitly, so no separate headerless call is made for the base fields.
        $this->service->expects($this->never())->method('getPaymentMethodConfigurationsSearch');

        $results = $this->gateway->fetchBySpaceId(1, null, ['en_US', 'de_DE', 'de-DE', ' en-US ', '']);

        $this->assertSame(['en-US', 'de-DE'], $requested);
        $this->assertSame(
            ['en-US' => 'Credit / Debit Card', 'de-DE' => 'Kredit-/Debitkarte'],
            $results->first()->title->jsonSerialize(),
        );
    }

    public function testFetchBySpaceIdWithoutLocalesSendsNoAcceptLanguage(): void
    {
        $this->sdkProvider->expects($this->never())->method('getLocalizedService');
        $this->service->expects($this->once())
            ->method('getPaymentMethodConfigurationsSearch')
            ->with(1, null, 100, 0, null, '-state:DELETED')
            ->willReturn($this->searchResponse([$this->config(11, ['en-US' => 'Credit / Debit Card'])]));

        $results = $this->gateway->fetchBySpaceId(1);

        $this->assertSame(['en-US' => 'Credit / Debit Card'], $results->first()->title->jsonSerialize());
    }

    public function testFetchBySpaceIdPagesThroughEveryLocale(): void
    {
        $requested = [];
        $this->routeLocalizedServices([
            'de-DE' => $this->searchService([
                0 => $this->searchResponse([$this->config(11, ['de-DE' => 'Karte'])], true),
                1 => $this->searchResponse([$this->config(12, ['de-DE' => 'Rechnung'])]),
            ]),
            'it-IT' => $this->searchService([
                0 => $this->searchResponse([$this->config(11, ['it-IT' => 'Carta'])], true),
                1 => $this->searchResponse([$this->config(12, ['it-IT' => 'Fattura'])]),
            ]),
        ], $requested);

        $results = $this->gateway->fetchBySpaceId(1, null, ['de-DE', 'it-IT']);

        $this->assertSame([11, 12], array_map(static fn (PaymentMethod $method) => $method->id, $results->all()));
        $this->assertSame(['de-DE' => 'Karte', 'it-IT' => 'Carta'], $results->all()[0]->title->jsonSerialize());
        $this->assertSame(['de-DE' => 'Rechnung', 'it-IT' => 'Fattura'], $results->all()[1]->title->jsonSerialize());
    }

    public function testFetchBySpaceIdDoesNotLeakTheLocaleIntoALaterCall(): void
    {
        $requested = [];
        $this->routeLocalizedServices([
            'de-DE' => $this->searchService([0 => $this->searchResponse([$this->config(11, ['de-DE' => 'Kredit-/Debitkarte'])])]),
        ], $requested);
        $this->service->expects($this->once())
            ->method('getPaymentMethodConfigurationsSearch')
            ->willReturn($this->searchResponse([$this->config(11, ['en-US' => 'Credit / Debit Card'])]));

        $this->gateway->fetchBySpaceId(1, null, ['de-DE']);
        $later = $this->gateway->fetchBySpaceId(1);

        $this->assertSame(['de-DE'], $requested);
        $this->assertSame(['en-US' => 'Credit / Debit Card'], $later->first()->title->jsonSerialize());
    }

    public function testFetchBySpaceIdKeepsTheOtherTranslationsWhenALocaleLacksAConfiguration(): void
    {
        $requested = [];
        $this->routeLocalizedServices([
            'de-DE' => $this->searchService([0 => $this->searchResponse([
                $this->config(11, ['de-DE' => 'Karte']),
                $this->config(12, ['de-DE' => 'Rechnung']),
            ])]),
            'fr-FR' => $this->searchService([0 => $this->searchResponse([
                $this->config(11, ['fr-FR' => 'Carte']),
            ])]),
        ], $requested);

        $results = $this->gateway->fetchBySpaceId(1, null, ['de-DE', 'fr-FR']);

        $this->assertCount(2, $results);
        $this->assertSame(['de-DE' => 'Rechnung'], $results->all()[1]->title->jsonSerialize());
    }

    public function testFetchBySpaceIdLogsARequestedLocaleMissingFromTheResponse(): void
    {
        $requested = [];
        $this->routeLocalizedServices([
            'de-DE' => $this->searchService([0 => $this->searchResponse([$this->config(11, ['de-DE' => 'Karte'])])]),
            'sv-SE' => $this->searchService([0 => $this->searchResponse([$this->config(11, ['en-US' => 'Card'])])]),
        ], $requested);

        $logged = [];
        $this->logger->method('debug')
            ->willReturnCallback(static function (string $message, array $context) use (&$logged): void {
                if (str_contains($message, 'missing for a requested locale')) {
                    $logged[] = $context;
                }
            });

        $this->gateway->fetchBySpaceId(1, null, ['de-DE', 'sv-SE']);

        $this->assertCount(1, $logged);
        $this->assertSame('sv-SE', $logged[0]['locale']);
        $this->assertSame(['en-US'], $logged[0]['returnedLocales']);
        $this->assertSame([11], $logged[0]['paymentMethodIds']);
    }

    public function testFetchByIdMergesOneReadPerLocale(): void
    {
        $german = $this->createMock(SdkPaymentMethodConfigurationService::class);
        $german->expects($this->once())
            ->method('getPaymentMethodConfigurationsId')
            ->with(10, 1)
            ->willReturn($this->config(10, ['de-DE' => 'Kreditkarte'], ['de-DE' => 'Später bezahlen']));
        $english = $this->createMock(SdkPaymentMethodConfigurationService::class);
        $english->expects($this->once())
            ->method('getPaymentMethodConfigurationsId')
            ->with(10, 1)
            ->willReturn($this->config(10, ['en-US' => 'Credit Card'], ['en-US' => 'Pay later']));
        $requested = [];
        $this->routeLocalizedServices(['de-DE' => $german, 'en-US' => $english], $requested);
        $this->service->expects($this->never())->method('getPaymentMethodConfigurationsId');

        $method = $this->gateway->fetchById(1, 10, ['de_DE', 'en_US']);

        $this->assertSame(['de-DE', 'en-US'], $requested);
        $this->assertSame(['de-DE' => 'Kreditkarte', 'en-US' => 'Credit Card'], $method->title->jsonSerialize());
        $this->assertSame('Pay later', $method->description->localize('en-US'));
    }

    public function testFetchByIdWrapsAFailureOfALocalizedRead(): void
    {
        $failing = $this->createMock(SdkPaymentMethodConfigurationService::class);
        $failing->method('getPaymentMethodConfigurationsId')->willThrowException(new \Exception('Not found'));
        $requested = [];
        $this->routeLocalizedServices(['de-DE' => $failing], $requested);

        $this->expectException(PaymentMethodException::class);
        $this->expectExceptionMessage('paymentMethodId=10');

        $this->gateway->fetchById(1, 10, ['de-DE']);
    }
}
