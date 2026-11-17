<?php

declare(strict_types=1);

namespace Wallee\PluginCore\Tests\Sdk;

use PHPUnit\Framework\TestCase;
use Wallee\PluginCore\SharedKernel\CacheInterface;
use Wallee\PluginCore\GlobalData\Exception\GlobalDataException;
use Wallee\PluginCore\Refund\Exception\RefundException;
use Wallee\Sdk\ApiException;
use Wallee\PluginCore\Sdk\ClientMetadata;
use Wallee\PluginCore\Sdk\ClientMetadataProviderInterface;
use Wallee\PluginCore\Sdk\SdkProvider;
use Wallee\PluginCore\Settings\Settings;
use Wallee\Sdk\Configuration as SdkConfiguration;
use Wallee\Sdk\Service\PaymentMethodConfigurationsService as SdkPaymentMethodConfigurationsService;
use Wallee\Sdk\Service\TransactionsService as SdkTransactionsService; // Example service

class SdkProviderTest extends TestCase
{
    private Settings $settingsMock;
    private SdkProvider $sdkProvider;

    /**
     * Sets up the test environment by creating a mock for the Settings and initializing the SdkProvider.
     */
    protected function setUp(): void
    {
        $this->settingsMock = $this->createMock(Settings::class);
        // Configure the settings mock to return valid credentials
        $this->settingsMock->method('getUserId')->willReturn(123);
        $this->settingsMock->method('getApiKey')->willReturn('test-key');

        $this->sdkProvider = new SdkProvider($this->settingsMock);
    }

    /**
     * Verifies that getConfiguration() returns the correct instance of SdkConfiguration.
     *
     * This ensures that consumer applications can reliably access the underlying
     * SDK configuration object.
     */
    public function testGetConfigurationReturnsCorrectInstance(): void
    {
        // --- Act ---
        $actualConfig = $this->sdkProvider->getConfiguration();

        // --- Assert ---
        $this->assertInstanceOf(SdkConfiguration::class, $actualConfig);
    }

    /**
     * Caching is strictly opt-in: a provider built without a cache must report
     * none, so a gateway using CacheAwareTrait falls through to an uncached call.
     */
    public function testGetCacheReturnsNullWhenNoneWasConfigured(): void
    {
        $this->assertNull($this->sdkProvider->getCache());
    }

    public function testGetCacheReturnsTheConfiguredInstance(): void
    {
        $cache = $this->createMock(CacheInterface::class);

        $sdkProvider = new SdkProvider($this->settingsMock, null, $cache);

        $this->assertSame($cache, $sdkProvider->getCache());
    }

    /**
     * Verifies that the getService method correctly instantiates an SDK service
     * with the provider's internal configuration.
     */
    public function testGetServiceCreatesServiceWithCorrectConfiguration(): void
    {
        // --- Act ---
        $service = $this->sdkProvider->getService(SdkTransactionsService::class);

        // --- Assert ---
        $this->assertInstanceOf(SdkTransactionsService::class, $service);

        // Use reflection to check the private config property inside the service
        $reflection = new \ReflectionClass(SdkTransactionsService::class);
        $configProp = $reflection->getProperty('config');
        $configProp->setAccessible(true);
        $actualConfig = $configProp->getValue($service);

        $this->assertInstanceOf(SdkConfiguration::class, $actualConfig);
        // We can't directly check the User ID/Key inside the SDK configuration easily,
        // but we've verified it was correctly passed to the service.
    }

    /**
     * Verifies that subsequent calls to getService for the same class return the same instance.
     *
     * This ensures that we are not unnecessarily recreating service instances.
     */
    public function testGetServiceReturnsSameInstanceOnSubsequentCalls(): void
    {
        $service1 = $this->sdkProvider->getService(SdkTransactionsService::class);
        $service2 = $this->sdkProvider->getService(SdkTransactionsService::class);

        $this->assertSame($service1, $service2);
    }

    /**
     * Verifies that getService throws an InvalidArgumentException when provided with an invalid class name.
     */
    public function testGetServiceThrowsExceptionForInvalidClass(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->sdkProvider->getService(\stdClass::class); // stdClass doesn't have the right constructor
    }

    /**
     * Verifies the "Smart URL" logic that automatically adds the API path if it's missing from the base URL.
     *
     * @dataProvider smartUrlDataProvider
     */
    public function testSmartUrlHandling(string $inputUrl, string $expectedHost): void
    {
        // --- Setup ---
        $settings = $this->createMock(Settings::class);
        $settings->method('getUserId')->willReturn(123);
        $settings->method('getApiKey')->willReturn('test-key');
        $settings->method('getBaseUrl')->willReturn($inputUrl);

        $provider = new SdkProvider($settings);

        // --- Act ---
        $service = $provider->getService(SdkTransactionsService::class);

        // --- Assert ---
        $reflection = new \ReflectionClass(SdkTransactionsService::class);
        $configProp = $reflection->getProperty('config');
        $configProp->setAccessible(true);
        /** @var SdkConfiguration $actualConfig */
        $actualConfig = $configProp->getValue($service);

        $this->assertSame($expectedHost, $actualConfig->getHost());
    }

    /**
     * Data provider for testSmartUrlHandling.
     *
     * Provides variations of input URLs and their expected normalized "Smart URL" output.
     *
     * @return array<string, array{string, string}>
     */
    public static function smartUrlDataProvider(): array
    {
        return [
            'Basic domain' => [
                'staging-wallee.com',
                'https://staging-wallee.com/api/v2.0',
            ],
            'Localhost with port' => [
                'http://localhost:8080',
                'http://localhost:8080/api/v2.0',
            ],
            'Custom URI' => [
                'custom-domain.com/my-api',
                'https://custom-domain.com/my-api',
            ],
            'Standard domain with path' => [
                'https://app-wallee.com/api/v2.0',
                'https://app-wallee.com/api/v2.0',
            ],
        ];
    }

    /**
     * Verifies that a supplied ClientMetadataProvider registers all four
     * identification headers on the instantiated SDK client.
     */
    public function testClientMetadataHeadersAreRegisteredOnTheConfiguration(): void
    {
        $provider = $this->createMock(ClientMetadataProviderInterface::class);
        $provider->method('getClientMetadata')
            ->willReturn(new ClientMetadata('magento', '2.4.9', '1.2.0'));

        $sdkProvider = new SdkProvider($this->settingsMock, $provider);
        $headers = $sdkProvider->getConfiguration()->getDefaultHeaders();

        $this->assertSame('magento', $headers['x-meta-shop-system']);
        $this->assertSame('2.4.9', $headers['x-meta-shop-system-version']);
        $this->assertSame('magento-2.4', $headers['x-meta-shop-system-and-version']);
        $this->assertSame('1.2.0', $headers['x-meta-plugin-version']);
    }

    /**
     * Verifies that the SDK's own x-meta-sdk-* headers survive the metadata being
     * added. This SDK replaces the whole default-header array when set, so a merge
     * bug here would silently strip the SDK's own identification.
     */
    public function testClientMetadataDoesNotDisplaceTheSdksOwnDefaultHeaders(): void
    {
        $withoutMetadata = (new SdkProvider($this->settingsMock))->getConfiguration()->getDefaultHeaders();

        $provider = $this->createMock(ClientMetadataProviderInterface::class);
        $provider->method('getClientMetadata')
            ->willReturn(new ClientMetadata('magento', '2.4.9', '1.2.0'));

        $withMetadata = (new SdkProvider($this->settingsMock, $provider))
            ->getConfiguration()
            ->getDefaultHeaders();

        foreach ($withoutMetadata as $name => $value) {
            $this->assertArrayHasKey($name, $withMetadata);
            $this->assertSame($value, $withMetadata[$name]);
        }
    }

    /**
     * Verifies that omitting the provider entirely leaves no identification headers.
     */
    public function testNoClientMetadataProviderAddsNoHeaders(): void
    {
        $headers = (new SdkProvider($this->settingsMock))->getConfiguration()->getDefaultHeaders();

        $this->assertArrayNotHasKey('x-meta-shop-system', $headers);
        $this->assertArrayNotHasKey('x-meta-shop-system-version', $headers);
        $this->assertArrayNotHasKey('x-meta-shop-system-and-version', $headers);
        $this->assertArrayNotHasKey('x-meta-plugin-version', $headers);
    }

    /**
     * Verifies that a provider answering null is a supported outcome, not a failure:
     * an integration that cannot determine its versions still works.
     */
    public function testClientMetadataProviderReturningNullAddsNoHeaders(): void
    {
        $provider = $this->createMock(ClientMetadataProviderInterface::class);
        $provider->method('getClientMetadata')->willReturn(null);

        $sdkProvider = new SdkProvider($this->settingsMock, $provider);
        $headers = $sdkProvider->getConfiguration()->getDefaultHeaders();

        $this->assertArrayNotHasKey('x-meta-shop-system', $headers);
        $this->assertArrayNotHasKey('x-meta-plugin-version', $headers);
    }

    public function testWrapExceptionMarksConnectionFailuresAsRetryable(): void
    {
        $wrapped = SdkProvider::wrapException(
            new ApiException('Connection refused', 0),
            RefundException::class,
            'refund',
            ['spaceId' => 42],
            'The payment service is temporarily unreachable.',
        );

        $this->assertTrue($wrapped->isRetryable());
    }

    public function testWrapExceptionTreatsApiRejectionsAsTerminal(): void
    {
        $wrapped = SdkProvider::wrapException(
            new ApiException('Bad Request', 400),
            RefundException::class,
            'refund',
            ['spaceId' => 42],
            'The refund was rejected.',
        );

        $this->assertFalse($wrapped->isRetryable());
    }

    /**
     * @dataProvider retryableHttpStatusCodeProvider
     */
    public function testWrapExceptionMarksPortalGatewayErrorsAsRetryable(int $statusCode): void
    {
        $wrapped = SdkProvider::wrapException(
            new ApiException('Bad Gateway', $statusCode),
            RefundException::class,
            'refund',
            ['spaceId' => 42],
            'The payment service is temporarily unavailable.',
        );

        $this->assertTrue($wrapped->isRetryable());
    }

    /**
     * @return array<string, array{int}>
     */
    public static function retryableHttpStatusCodeProvider(): array
    {
        return [
            'Bad Gateway' => [502],
            'Service Unavailable' => [503],
            'Gateway Timeout' => [504],
        ];
    }

    /**
     * @dataProvider terminalHttpStatusCodeProvider
     */
    public function testWrapExceptionTreatsOtherHttpStatusCodesAsTerminal(int $statusCode): void
    {
        $wrapped = SdkProvider::wrapException(
            new ApiException('Rejected', $statusCode),
            RefundException::class,
            'refund',
            ['spaceId' => 42],
            'The refund was rejected.',
        );

        $this->assertFalse($wrapped->isRetryable());
    }

    /**
     * @return array<string, array{int}>
     */
    public static function terminalHttpStatusCodeProvider(): array
    {
        return [
            'Bad Request' => [400],
            'Unauthorized' => [401],
            'Not Found' => [404],
            'Unprocessable Entity' => [422],
            'Internal Server Error' => [500],
        ];
    }

    public function testWrapExceptionAppliesTheSameClassificationToEveryDomain(): void
    {
        // The point of classifying centrally: a domain that never touches
        // withRetryable() still reports a connection failure as retryable.
        $wrapped = SdkProvider::wrapException(
            new ApiException('Connection refused', 0),
            GlobalDataException::class,
            'getCurrencies',
            [],
            'Reference data is temporarily unavailable.',
        );

        $this->assertTrue($wrapped->isRetryable());
    }

    /**
     * Verifies that a localized service sends Accept-Language on top of every
     * default header the shared configuration carries, so the SDK and client
     * identification headers still go out with localized requests.
     */
    public function testLocalizedServiceAddsAcceptLanguageToTheExistingDefaultHeaders(): void
    {
        $metadataProvider = $this->createMock(ClientMetadataProviderInterface::class);
        $metadataProvider->method('getClientMetadata')
            ->willReturn(new ClientMetadata('magento', '2.4.9', '1.2.0'));
        $sdkProvider = new SdkProvider($this->settingsMock, $metadataProvider);
        $sharedHeaders = $sdkProvider->getConfiguration()->getDefaultHeaders();

        $service = $sdkProvider->getLocalizedService(SdkPaymentMethodConfigurationsService::class, 'de-DE');

        $this->assertInstanceOf(SdkPaymentMethodConfigurationsService::class, $service);
        $this->assertSame(
            $sharedHeaders + ['Accept-Language' => 'de-DE'],
            self::configOf($service)->getDefaultHeaders(),
        );
        $this->assertSame('magento', self::configOf($service)->getDefaultHeaders()['x-meta-shop-system']);
        $this->assertArrayHasKey('x-meta-sdk-version', self::configOf($service)->getDefaultHeaders());
    }

    /**
     * Verifies that the header stays on the localized service: the shared
     * configuration, the services getService() hands out before and after, and
     * services for other locales never see it.
     */
    public function testLocalizedServiceDoesNotLeakAcceptLanguage(): void
    {
        $sharedHeaders = $this->sdkProvider->getConfiguration()->getDefaultHeaders();
        $before = $this->sdkProvider->getService(SdkPaymentMethodConfigurationsService::class);

        $german = $this->sdkProvider->getLocalizedService(SdkPaymentMethodConfigurationsService::class, 'de-DE');
        $french = $this->sdkProvider->getLocalizedService(SdkPaymentMethodConfigurationsService::class, 'fr-FR');
        $after = $this->sdkProvider->getService(SdkPaymentMethodConfigurationsService::class);

        $this->assertSame($sharedHeaders, $this->sdkProvider->getConfiguration()->getDefaultHeaders());
        $this->assertArrayNotHasKey('Accept-Language', self::configOf($before)->getDefaultHeaders());
        $this->assertArrayNotHasKey('Accept-Language', self::configOf($after)->getDefaultHeaders());
        $this->assertSame($this->sdkProvider->getConfiguration(), self::configOf($after));
        $this->assertNotSame($this->sdkProvider->getConfiguration(), self::configOf($german));
        $this->assertSame('de-DE', self::configOf($german)->getDefaultHeaders()['Accept-Language']);
        $this->assertSame('fr-FR', self::configOf($french)->getDefaultHeaders()['Accept-Language']);
    }

    /**
     * Verifies that the localized copy keeps the host and credentials of the shared configuration.
     */
    public function testLocalizedServiceKeepsHostAndCredentials(): void
    {
        $shared = $this->sdkProvider->getConfiguration();

        $config = $this->sdkProvider
            ->getLocalizedService(SdkPaymentMethodConfigurationsService::class, 'de-DE');
        $config = self::configOf($config);

        $this->assertSame($shared->getHost(), $config->getHost());
        $this->assertSame($shared->getUserId(), $config->getUserId());
        $this->assertSame($shared->getAuthenticationKey(), $config->getAuthenticationKey());
    }

    public function testGetLocalizedServiceThrowsExceptionForInvalidClass(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->sdkProvider->getLocalizedService(\stdClass::class, 'de-DE');
    }

    /**
     * Reads the configuration a service builds its requests from.
     *
     * Read by reflection: the SDK's own getConfig() declares a return type that
     * does not exist, which static analysis rejects.
     */
    private static function configOf(object $service): SdkConfiguration
    {
        $config = (new \ReflectionProperty($service, 'config'))->getValue($service);
        self::assertInstanceOf(SdkConfiguration::class, $config);

        return $config;
    }
}
